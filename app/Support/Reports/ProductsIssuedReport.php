<?php

namespace App\Support\Reports;

use App\Models\StockIssuance;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Which product and how many were issued" per PLAN.md's reports
 * requirement — one row per product, aggregated over the given range.
 * Row identity for Filament's table (and CSV export) is the product id,
 * exposed as `id` via the select even though the base query is StockIssuance.
 */
class ProductsIssuedReport
{
    /**
     * The joined/grouped/aggregated query with no date constraint — the
     * widget's table filter applies the actual range via applyDateRange().
     * Kept separate so the filter's ->query() callback can add the
     * constraint rather than needing to replace one already baked in.
     */
    public static function baseQuery(): Builder
    {
        return StockIssuance::query()
            ->join('stock_request_items', 'stock_issuances.stock_request_item_id', '=', 'stock_request_items.id')
            ->join('products', 'stock_request_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->groupBy('products.id', 'products.name', 'categories.name')
            ->selectRaw(
                'products.id as id, '.
                'products.name as product_name, '.
                'categories.name as category_name, '.
                'SUM(stock_issuances.issued_qty) as total_issued, '.
                'COUNT(DISTINCT stock_request_items.stock_request_id) as request_count, '.
                'MAX(stock_issuances.created_at) as last_issued_at'
            )
            ->orderByDesc('total_issued');
    }

    public static function applyDateRange(Builder $query, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return $query->whereBetween('stock_issuances.created_at', [$from, $to]);
    }

    public static function query(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return static::applyDateRange(static::baseQuery(), $from, $to);
    }

    /**
     * @return array<int, string>
     */
    public static function csvHeaders(): array
    {
        return ['Product', 'Category', 'Total Issued', 'Requests', 'Last Issued At'];
    }

    /**
     * @return array<int, string|int>
     */
    public static function toCsvRow(object $row): array
    {
        return [
            $row->product_name,
            $row->category_name,
            $row->total_issued,
            $row->request_count,
            $row->last_issued_at,
        ];
    }
}
