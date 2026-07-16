<?php

namespace App\Support\Reports;

use Carbon\CarbonImmutable;

/**
 * Resolves the "Daily / Monthly / Yearly" filter (PLAN.md's reports
 * requirement) plus a reference date into a concrete [from, to] range that
 * both report queries and their CSV exports use identically, so the export
 * always matches whatever's currently on screen.
 */
class ReportPeriod
{
    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function resolve(?string $period, ?string $referenceDate): array
    {
        $reference = $referenceDate ? CarbonImmutable::parse($referenceDate) : CarbonImmutable::now();

        return match ($period) {
            'monthly' => [$reference->startOfMonth(), $reference->endOfMonth()],
            'yearly' => [$reference->startOfYear(), $reference->endOfYear()],
            default => [$reference->startOfDay(), $reference->endOfDay()],
        };
    }

    public static function label(?string $period, ?string $referenceDate): string
    {
        [$from, $to] = self::resolve($period, $referenceDate);

        return match ($period) {
            'monthly' => $from->format('F Y'),
            'yearly' => $from->format('Y'),
            default => $from->format('M j, Y'),
        };
    }
}
