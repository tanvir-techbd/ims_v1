<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Models\Setting;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Dedicated "trigger an alert or display it in a dedicated alert list"
 * surface (requirements doc) — LowStockWidget on the Dashboard covers the
 * at-a-glance case, this page is the full searchable/paginated list.
 */
class StockAlerts extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationLabel = 'Stock Alerts';

    protected static ?string $title = 'Stock Alerts';

    protected static string $view = 'filament.pages.stock-alerts';

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->can('view_stock_alerts') ?? false;
    }

    public function table(Table $table): Table
    {
        $threshold = (int) Setting::get('low_stock_threshold', 10);

        return $table
            ->query(Product::query()->where('current_stock', '<=', $threshold))
            ->heading("Products At or Below Threshold ({$threshold})")
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category'),
                Tables\Columns\TextColumn::make('unit.name')
                    ->label('Unit'),
                Tables\Columns\TextColumn::make('current_stock')
                    ->label('Stock on Hand')
                    ->badge()
                    ->color('danger')
                    ->sortable(),
            ])
            ->defaultSort('current_stock')
            ->emptyStateHeading('Nothing low on stock')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
