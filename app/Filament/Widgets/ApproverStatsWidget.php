<?php

namespace App\Filament\Widgets;

use App\Enums\ApprovalDecision;
use App\Enums\RequestItemStatus;
use App\Models\RequestApproval;
use App\Models\StockRequestItem;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class ApproverStatsWidget extends BaseWidget
{
    public static function canView(): bool
    {
        return Auth::user()?->hasRole('Approver') ?? false;
    }

    protected function getStats(): array
    {
        $pendingItems = StockRequestItem::where('status', RequestItemStatus::Pending)->get();
        $pendingRequestCount = $pendingItems->pluck('stock_request_id')->unique()->count();

        $approvedThisWeek = RequestApproval::where('decision', ApprovalDecision::Approved)
            ->where('created_at', '>=', now()->startOfWeek())
            ->get();

        $rejectedThisWeek = RequestApproval::where('decision', ApprovalDecision::Rejected)
            ->where('created_at', '>=', now()->startOfWeek())
            ->count();

        return [
            Stat::make('Awaiting Your Approval', $pendingItems->count())
                ->description("items across {$pendingRequestCount} requests")
                ->color('warning'),
            Stat::make('Approved This Week', $approvedThisWeek->count())
                ->description("items, {$approvedThisWeek->sum('approved_qty')} units total")
                ->color('success'),
            Stat::make('Rejected This Week', $rejectedThisWeek)
                ->description('see remarks in request detail')
                ->color('danger'),
        ];
    }
}
