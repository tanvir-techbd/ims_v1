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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class StockRequestResource extends Resource
{
    protected static ?string $model = StockRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Stock Requests';

    protected static ?string $navigationGroup = 'Requests';

    protected static ?int $navigationSort = 1;

    public static function getGloballySearchableAttributes(): array
    {
        return ['id', 'requester.name'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return "REQ-{$record->id}";
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Requester' => $record->requester?->name,
            'Status' => $record->status->label(),
        ];
    }

    /**
     * A tick-and-quantity catalog, grouped by category, rather than a
     * one-product-at-a-time repeater — a Demander browsing what's
     * orderable wants to scan the whole list and check off several things
     * at once, not add rows one by one from a dropdown. Each product gets
     * a checkbox (`products.{id}.selected`) and a quantity field
     * (`products.{id}.qty`, only shown once ticked); CreateStockRequest
     * turns whichever ones end up checked into real items via addItem().
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Textarea::make('notes')
                    ->label('Notes')
                    ->placeholder('Why are these items needed?')
                    ->columnSpanFull(),
                Forms\Components\Section::make('Products')
                    ->description('Tick each product you need and enter a quantity.')
                    ->schema(static::orderableProductsFormSchema())
                    ->columnSpanFull(),
            ]);
    }

    /**
     * One collapsible Section per category, each containing a checkbox +
     * quantity row per orderable product. Scoped to permitted products for
     * a pure Demander (PLAN.md §3a) — mirrors
     * ProductResource::getEloquentQuery()'s scoping logic; this is a UI
     * convenience only, StockRequest::addItem() is the real backend
     * enforcement.
     *
     * @return array<\Filament\Forms\Components\Component>
     */
    protected static function orderableProductsFormSchema(): array
    {
        $user = Auth::user();
        $query = Product::query()->with('category')->orderBy('name');

        if ($user && ! $user->hasAnyRole(['Admin', 'Approver', 'Storekeeper', 'Supplier'])) {
            $permitted = $user->permittedItemGroupIds();

            $query->where(function (Builder $query) use ($permitted) {
                $query->whereDoesntHave('itemGroups')
                    ->orWhereHas('itemGroups', fn (Builder $q) => $q->whereIn('item_groups.id', $permitted));
            });
        }

        $byCategory = $query->get()->groupBy(fn (Product $product) => $product->category->name ?? 'Uncategorized');

        if ($byCategory->isEmpty()) {
            return [
                Forms\Components\Placeholder::make('no_products')
                    ->hiddenLabel()
                    ->content('No products are currently orderable.'),
            ];
        }

        return $byCategory->map(fn ($products, string $categoryName) => Forms\Components\Section::make($categoryName)
            ->description($products->count().' product'.($products->count() === 1 ? '' : 's'))
            ->collapsible()
            ->schema(
                $products->map(fn (Product $product) => Forms\Components\Grid::make(12)
                    ->schema([
                        Forms\Components\Checkbox::make("products.{$product->id}.selected")
                            ->label($product->name)
                            ->helperText("SKU: {$product->sku} · In stock: {$product->current_stock}")
                            ->live()
                            ->columnSpan(8),
                        Forms\Components\TextInput::make("products.{$product->id}.qty")
                            ->label('Quantity')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->visible(fn (Forms\Get $get) => (bool) $get("products.{$product->id}.selected"))
                            ->required(fn (Forms\Get $get) => (bool) $get("products.{$product->id}.selected"))
                            ->columnSpan(4),
                    ]))
                    ->toArray()
            ))
            ->values()
            ->toArray();
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
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')->native(false),
                        Forms\Components\DatePicker::make('until')->native(false),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date))),
            ])
            ->emptyStateHeading('No stock requests yet')
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('markReceived')
                    ->label('Mark as Received')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Confirms you physically received the issued item(s). This cannot be undone.')
                    ->visible(fn (StockRequest $record) => in_array($record->status, [
                        RequestStatus::Issued, RequestStatus::PartiallyIssued,
                    ], true) && ($record->requester_id === Auth::id() || (Auth::user()?->hasRole('Admin') ?? false)))
                    ->action(function (StockRequest $record): void {
                        $record->markReceived(Auth::user());

                        Notification::make()->title('Marked as received')->success()->send();
                    }),
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
