<?php

namespace App\Support\Reports;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Detailed user activity records" per PLAN.md's reports requirement — one
 * row per user who did *something* in the range (requested, approved, or
 * issued); users with zero activity in the range are omitted rather than
 * padding the report with all-zero rows.
 */
class UserActivityReport
{
    public static function query(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return User::query()
            ->withCount([
                'stockRequests' => fn (Builder $q) => $q->whereBetween('created_at', [$from, $to]),
                'approvalsMade' => fn (Builder $q) => $q->whereBetween('created_at', [$from, $to]),
                'issuancesMade' => fn (Builder $q) => $q->whereBetween('created_at', [$from, $to]),
            ])
            ->havingRaw('stock_requests_count > 0 OR approvals_made_count > 0 OR issuances_made_count > 0')
            ->orderByDesc('stock_requests_count');
    }

    /**
     * @return array<int, string>
     */
    public static function csvHeaders(): array
    {
        return ['User', 'Email', 'Requests Created', 'Approvals Made', 'Issuances Made'];
    }

    /**
     * @return array<int, string|int>
     */
    public static function toCsvRow(User $user): array
    {
        return [
            $user->name,
            $user->email,
            $user->stock_requests_count,
            $user->approvals_made_count,
            $user->issuances_made_count,
        ];
    }
}
