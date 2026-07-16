<?php

namespace App\Filament\Widgets;

use App\Enums\RequestStatus;
use App\Models\StockRequest;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class DemanderStatsWidget extends BaseWidget
{
    public static function canView(): bool
    {
        $user = Auth::user();

        return $user !== null && ! $user->hasAnyRole(['Admin', 'Approver', 'Storekeeper', 'Supplier']);
    }

    protected function getStats(): array
    {
        $userId = Auth::id();

        $pending = StockRequest::where('requester_id', $userId)
            ->whereIn('status', [RequestStatus::Pending, RequestStatus::PartiallyApproved])
            ->count();

        $issuedThisMonth = StockRequest::where('requester_id', $userId)
            ->whereIn('status', [RequestStatus::Issued, RequestStatus::PartiallyIssued])
            ->where('updated_at', '>=', now()->startOfMonth())
            ->count();

        $rejected = StockRequest::where('requester_id', $userId)
            ->where('status', RequestStatus::Rejected)
            ->count();

        return [
            Stat::make('Pending Requests', $pending)
                ->description('awaiting approver decision')
                ->color('warning'),
            Stat::make('Issued This Month', $issuedThisMonth)
                ->description('requests fully or partly issued')
                ->color('success'),
            Stat::make('Rejected', $rejected)
                ->description('see remarks for reason')
                ->color('danger'),
        ];
    }
}
