<?php

namespace App\Filament\Widgets;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class SupplierStatsWidget extends BaseWidget
{
    public static function canView(): bool
    {
        return Auth::user()?->hasRole('Supplier') ?? false;
    }

    protected function getStats(): array
    {
        $totalProducts = Product::count();
        $totalCategories = Category::count();
        $lowStockCount = Product::where('current_stock', '<=', (int) Setting::get('low_stock_threshold', 10))->count();
        $lastUpdated = Product::max('updated_at');

        return [
            Stat::make('Total Products', $totalProducts)
                ->description("across {$totalCategories} categories"),
            Stat::make('Low Stock Items', $lowStockCount)
                ->description('may need replenishment')
                ->color('danger'),
            Stat::make('Last Updated', $lastUpdated ? Carbon::parse($lastUpdated)->diffForHumans() : '—'),
        ];
    }
}
