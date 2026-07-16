<?php

namespace App\Filament\Resources;

use App\Enums\RequestStatus;
use App\Filament\Resources\StockRequestResource\Pages;
use App\Filament\Resources\StockRequestResource\RelationManagers;
use App\Models\Product;
use App\Models\StockRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class StockRequestResource extends Resource
{
    protected static ?string $model = StockRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Stock Requests';

    protected static ?string $navigationGroup = 'Requests';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Textarea::make('notes')
                    ->label('Notes')
                    ->placeholder('Why are these items needed?')
                    ->columnSpanFull(),
                Forms\Components\Repeater::make('items')
                    ->label('Requested Items')
                    ->schema([
                        Forms\Components\Select::make('product_id')
                            ->label('Product')
                            ->options(fn () => static::orderableProductOptions())
                            ->searchable()
                            ->required(),
                        Forms\Components\TextInput::make('requested_qty')
                            ->label('Quantity')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                    ])
                    ->columns(2)
                    ->minItems(1)
                    ->required()
                    ->addActionLabel('Add another item')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Options for the item picker — the full catalog for anyone with full
     * product visibility, scoped to permitted products for a pure Demander
     * (PLAN.md §3a). Mirrors ProductResource::getEloquentQuery()'s scoping
     * logic; this is a UI convenience only, StockRequest::addItem() is the
     * real backend enforcement.
     */
    protected static function orderableProductOptions(): array
    {
        $user = Auth::user();
        $query = Product::query();

        if ($user && ! $user->hasAnyRole(['Admin', 'Approver', 'Storekeeper', 'Supplier'])) {
            $permitted = $user->permittedItemGroupIds();

            $query->where(function (Builder $query) use ($permitted) {
                $query->whereDoesntHave('itemGroups')
                    ->orWhereHas('itemGroups', fn (Builder $q) => $q->whereIn('item_groups.id', $permitted));
            });
        }

        return $query->orderBy('name')->pluck('name', 'id')->all();
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            TextEntry::make('id')->label('Request #')->formatStateUsing(fn (int $state) => "REQ-{$state}"),
            TextEntry::make('requester.name')->label('Requester'),
            TextEntry::make('status')
                ->badge()
                ->formatStateUsing(fn (RequestStatus $state) => $state->label())
                ->color(fn (RequestStatus $state) => $state->color()),
            TextEntry::make('created_at')->label('Submitted')->dateTime(),
            TextEntry::make('notes')->placeholder('—')->columnSpanFull(),
        ])->columns(4);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Request #')
                    ->formatStateUsing(fn (int $state) => "REQ-{$state}")
                    ->sortable(),
                Tables\Columns\TextColumn::make('requester.name')
                    ->label('Requester')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Items'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (RequestStatus $state) => $state->label())
                    ->color(fn (RequestStatus $state) => $state->color()),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(RequestStatus::cases())->mapWithKeys(
                        fn (RequestStatus $status) => [$status->value => $status->label()]
                    )),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (StockRequest $record) => $record->status === RequestStatus::Pending
                        && ($record->requester_id === Auth::id() || (Auth::user()?->hasRole('Admin') ?? false)))
                    ->action(function (StockRequest $record): void {
                        $record->cancel();

                        Notification::make()->title('Request cancelled')->success()->send();
                    }),
            ]);
    }

    /**
     * Demanders only see their own requests. Everyone else who can reach
     * this resource at all (Admin/Approver/Storekeeper, per the permissions
     * granted in PermissionSeeder) sees every request.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        if ($user && ! $user->hasAnyRole(['Admin', 'Approver', 'Storekeeper'])) {
            $query->where('requester_id', $user->id);
        }

        return $query;
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockRequests::route('/'),
            'create' => Pages\CreateStockRequest::route('/create'),
            'view' => Pages\ViewStockRequest::route('/{record}'),
        ];
    }
}
