<?php

namespace App\Filament\Resources\StockRequestResource\RelationManagers;

use App\Enums\RequestItemStatus;
use App\Exceptions\InventoryRuleException;
use App\Models\StockRequestItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Items are only ever created through StockRequestResource's create form
 * (which routes through StockRequest::addItem()) — no create/edit/delete
 * actions here. This table is the "fully trackable" workflow surface:
 * approve, reject, issue, and view the full per-item trail.
 */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Requested Items';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product'),
                Tables\Columns\TextColumn::make('requested_qty')
                    ->label('Requested')
                    ->numeric(),
                Tables\Columns\TextColumn::make('approved_qty')
                    ->label('Approved')
                    ->numeric()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('issued_qty')
                    ->label('Issued')
                    ->numeric(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (RequestItemStatus $state) => $state->label())
                    ->color(fn (RequestItemStatus $state) => $state->color()),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (StockRequestItem $record) => $record->status === RequestItemStatus::Pending
                        && (Auth::user()?->can('approve_request') ?? false))
                    ->form(fn (StockRequestItem $record) => [
                        Forms\Components\TextInput::make('approved_qty')
                            ->label('Approved Quantity')
                            ->numeric()
                            ->default($record->requested_qty)
                            ->minValue(0)
                            ->maxValue($record->requested_qty)
                            ->helperText("Requested: {$record->requested_qty}. Cannot exceed this.")
                            ->required(),
                        Forms\Components\Textarea::make('remarks')
                            ->label('Remarks (optional)'),
                    ])
                    ->action(function (StockRequestItem $record, array $data): void {
                        try {
                            $record->approve((int) $data['approved_qty'], Auth::user(), $data['remarks'] ?? null);
                        } catch (InventoryRuleException $e) {
                            Notification::make()->title('Approval failed')->body($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Item approved')->success()->send();

                        Notification::make()
                            ->title("Your request for \"{$record->product->name}\" was approved ({$data['approved_qty']} of {$record->requested_qty})")
                            ->success()
                            ->sendToDatabase($record->stockRequest->requester);
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (StockRequestItem $record) => $record->status === RequestItemStatus::Pending
                        && (Auth::user()?->can('approve_request') ?? false))
                    ->form([
                        Forms\Components\Textarea::make('remarks')
                            ->label('Reason (optional, but the requester will see it)'),
                    ])
                    ->action(function (StockRequestItem $record, array $data): void {
                        try {
                            $record->reject(Auth::user(), $data['remarks'] ?? null);
                        } catch (InventoryRuleException $e) {
                            Notification::make()->title('Rejection failed')->body($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Item rejected')->success()->send();

                        Notification::make()
                            ->title("Your request for \"{$record->product->name}\" was rejected")
                            ->body($data['remarks'] ?? null)
                            ->danger()
                            ->sendToDatabase($record->stockRequest->requester);
                    }),

                Tables\Actions\Action::make('issue')
                    ->label('Issue')
                    ->icon('heroicon-o-truck')
                    ->color('primary')
                    ->visible(fn (StockRequestItem $record) => in_array($record->status, [
                        RequestItemStatus::Approved, RequestItemStatus::PartiallyIssued,
                    ], true) && (Auth::user()?->can('issue_request') ?? false))
                    ->form(fn (StockRequestItem $record) => [
                        Forms\Components\TextInput::make('issue_qty')
                            ->label('Quantity to Issue Now')
                            ->numeric()
                            ->default(max(0, $record->approved_qty - $record->issued_qty))
                            ->minValue(1)
                            ->maxValue(max(1, $record->approved_qty - $record->issued_qty))
                            ->helperText(
                                'Remaining approved: '.($record->approved_qty - $record->issued_qty).
                                '. Actual stock on hand: '.$record->product->current_stock.
                                ' — if less than requested here, only what\'s in stock will be issued.'
                            )
                            ->required(),
                        Forms\Components\Textarea::make('remarks')
                            ->label('Remarks (optional)'),
                    ])
                    ->action(function (StockRequestItem $record, array $data): void {
                        try {
                            $issuance = $record->issue((int) $data['issue_qty'], Auth::user(), $data['remarks'] ?? null);
                        } catch (InventoryRuleException $e) {
                            Notification::make()->title('Issuance failed')->body($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()
                            ->title("Issued {$issuance->issued_qty} units of \"{$record->product->name}\"")
                            ->success()
                            ->send();

                        Notification::make()
                            ->title("{$issuance->issued_qty} units of \"{$record->product->name}\" were issued to you")
                            ->success()
                            ->sendToDatabase($record->stockRequest->requester);
                    }),

                Tables\Actions\Action::make('trail')
                    ->label('View Trail')
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->infolist(fn (StockRequestItem $record) => [
                        TextEntry::make('summary')
                            ->hiddenLabel()
                            ->state("{$record->product->name} — requested {$record->requested_qty}, approved ".
                                ($record->approved_qty ?? '—').", issued {$record->issued_qty}"),
                        RepeatableEntry::make('approvals')
                            ->label('Approval History')
                            ->schema([
                                TextEntry::make('decision')
                                    ->badge()
                                    ->formatStateUsing(fn ($state) => $state->label())
                                    ->color(fn ($state) => $state->value === 'approved' ? 'success' : 'danger'),
                                TextEntry::make('approved_qty')->label('Qty'),
                                TextEntry::make('approver.name')->label('By'),
                                TextEntry::make('remarks')->placeholder('—'),
                                TextEntry::make('created_at')->dateTime()->label('When'),
                            ])
                            ->columns(5)
                            ->visible(fn () => $record->approvals->isNotEmpty()),
                        RepeatableEntry::make('issuances')
                            ->label('Issuance History')
                            ->schema([
                                TextEntry::make('issued_qty')->label('Qty'),
                                TextEntry::make('storekeeper.name')->label('By'),
                                TextEntry::make('remarks')->placeholder('—'),
                                TextEntry::make('created_at')->dateTime()->label('When'),
                            ])
                            ->columns(4)
                            ->visible(fn () => $record->issuances->isNotEmpty()),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->bulkActions([]);
    }
}
