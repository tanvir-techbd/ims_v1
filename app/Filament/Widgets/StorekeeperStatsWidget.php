<?php

namespace App\Filament\Widgets;

use App\Enums\RequestItemStatus;
use App\Models\Product;
use App\Models\Setting;
use App\Models\StockIssuance;
use App\Models\StockRequestItem;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class StorekeeperStatsWidget extends BaseWidget
{
    public static function canView(): bool
    {
        return Auth::user()?->hasRole('Storekeeper') ?? false;
    }

    protected function getStats(): array
    {
        $readyToIssue = StockRequestItem::whereIn('status', [
            RequestItemStatus::Approved,
            RequestItemStatus::PartiallyIssued,
        ])->count();

        $todaysIssuances = StockIssuance::whereDate('created_at', today())->with('stockRequestItem')->get();
        $issuedTodayUnits = $todaysIssuances->sum('issued_qty');
        $issuedTodayRequests = $todaysIssuances->pluck('stockRequestItem.stock_request_id')->unique()->count();

        $lowStockCount = Product::where('current_stock', '<=', (int) Setting::get('low_stock_threshold', 10))->count();

        return [
            Stat::make('Ready to Issue', $readyToIssue)
                ->description('approved items awaiting pickup')
                ->color('warning'),
            Stat::make('Issued Today', $issuedTodayUnits)
                ->description("units across {$issuedTodayRequests} requests")
                ->color('success'),
            Stat::make('Low Stock Items', $lowStockCount)
                ->description('may block full issuance')
                ->color('danger'),
        ];
    }
}
