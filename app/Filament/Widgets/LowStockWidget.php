<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use App\Models\Setting;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class LowStockWidget extends BaseWidget
{
    protected static ?string $heading = 'Low Stock Watchlist';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Auth::user()?->can('view_stock_alerts') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Product::query()->where('current_stock', '<=', (int) Setting::get('low_stock_threshold', 10))
            )
            ->heading('Low Stock Watchlist')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Product'),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category'),
                Tables\Columns\TextColumn::make('current_stock')
                    ->label('Stock')
                    ->badge()
                    ->color('danger'),
            ])
            ->defaultSort('current_stock')
            ->paginated(false)
            ->emptyStateHeading('Nothing low on stock')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
