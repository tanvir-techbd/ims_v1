<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class SupplierStockLevelsWidget extends BaseWidget
{
    protected static ?string $heading = 'Current Stock Levels';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Auth::user()?->hasRole('Supplier') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Product::query()->orderBy('name'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Product')
                    ->searchable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category'),
                Tables\Columns\TextColumn::make('unit.name')
                    ->label('Unit'),
                Tables\Columns\TextColumn::make('current_stock')
                    ->label('Stock on Hand')
                    ->numeric()
                    ->badge()
                    ->color(fn (Product $record) => $record->isLowStock() ? 'danger' : 'success'),
            ])
            ->defaultSort('name')
            ->emptyStateHeading('No products found')
            ->emptyStateIcon('heroicon-o-cube');
    }
}
