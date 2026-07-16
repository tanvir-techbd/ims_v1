<?php

namespace App\Filament\Widgets;

use App\Enums\RequestStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\StockIssuance;
use App\Models\StockRequest;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class AdminStatsWidget extends BaseWidget
{
    public static function canView(): bool
    {
        return Auth::user()?->hasRole('Admin') ?? false;
    }

    protected function getStats(): array
    {
        $totalProducts = Product::count();
        $totalCategories = Category::count();
        $lowStockCount = Product::where('current_stock', '<=', (int) Setting::get('low_stock_threshold', 10))->count();

        $pendingRequests = StockRequest::whereIn('status', [
            RequestStatus::Pending,
            RequestStatus::PartiallyApproved,
        ])->count();

        $todaysIssuances = StockIssuance::whereDate('created_at', today())->with('stockRequestItem')->get();
        $issuedTodayUnits = $todaysIssuances->sum('issued_qty');
        $issuedTodayRequests = $todaysIssuances->pluck('stockRequestItem.stock_request_id')->unique()->count();

        return [
            Stat::make('Total Products', $totalProducts)
                ->description("across {$totalCategories} categories"),
            Stat::make('Low Stock Alerts', $lowStockCount)
                ->description('at or below threshold')
                ->color('danger'),
            Stat::make('Pending Requests', $pendingRequests)
                ->description('awaiting approval')
                ->color('warning'),
            Stat::make('Issued Today', $issuedTodayUnits)
                ->description("units across {$issuedTodayRequests} requests")
                ->color('success'),
        ];
    }
}
