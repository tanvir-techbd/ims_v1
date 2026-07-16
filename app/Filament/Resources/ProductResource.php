<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('sku')
                    ->label('SKU')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Forms\Components\Select::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('unit_id')
                    ->label('Unit')
                    ->relationship('unit', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('itemGroups')
                    ->label('Item Groups')
                    ->relationship('itemGroups', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->helperText('Leave empty for a product any Demander may order. Assign to one or more item-groups to restrict ordering to user-groups permitted for that item-group — see the Item Groups page.'),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('current_stock')
                    ->label('Current Stock')
                    ->numeric()
                    ->default(0)
                    ->disabled()
                    ->dehydrated(fn (string $operation) => $operation === 'create')
                    ->helperText('New products start at 0. Stock is only ever changed through the "Stock In" action or issuance — never edited directly here, so the movement ledger stays accurate.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable(),
                Tables\Columns\TextColumn::make('unit.name')
                    ->label('Unit')
                    ->sortable(),
                Tables\Columns\TextColumn::make('current_stock')
                    ->label('Stock')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color(fn (Product $record) => $record->isLowStock() ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name'),
                Tables\Filters\Filter::make('low_stock')
                    ->label('Low stock only')
                    ->query(fn (Builder $query) => $query->where(
                        'current_stock', '<=', (int) Setting::get('low_stock_threshold', 10)
                    )),
            ])
            ->actions([
                Tables\Actions\Action::make('stockIn')
                    ->label('Stock In')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->visible(fn () => Auth::user()?->can('record_stock_in') ?? false)
                    ->form([
                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantity Received')
                            ->numeric()
                            ->required()
                            ->minValue(1),
                        Forms\Components\Textarea::make('note')
                            ->label('Note')
                            ->placeholder('e.g. PO-2291 delivery from Global Supplies Ltd.'),
                    ])
                    ->action(function (Product $record, array $data): void {
                        $record->recordStockIn((int) $data['quantity'], Auth::user(), $data['note'] ?? null);

                        Notification::make()
                            ->title("Added {$data['quantity']} units to \"{$record->name}\"")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Demanders only see products they're permitted to order (PLAN.md §3a).
     * Keyed off "lacks any full-visibility role" rather than "has Demander
     * role" — a user could hold Demander alongside e.g. Approver, and that
     * combination should still see the full catalog, not the scoped view.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        if ($user && ! $user->hasAnyRole(['Admin', 'Approver', 'Storekeeper', 'Supplier'])) {
            $query->where(function (Builder $query) use ($user) {
                $query->whereDoesntHave('itemGroups')
                    ->orWhereHas('itemGroups', function (Builder $itemGroupQuery) use ($user) {
                        $itemGroupQuery->whereIn('item_groups.id', $user->permittedItemGroupIds());
                    });
            });
        }

        return $query;
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
