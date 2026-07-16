<?php

namespace App\Filament\Pages;

use App\Support\Reports\CsvExport;
use App\Support\Reports\ProductsIssuedReport;
use App\Support\Reports\ReportPeriod;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Daily / Monthly / Yearly products-issued report (PLAN.md's reports
 * requirement). Implements HasTable directly on the page — like
 * StockAlerts — rather than wrapping a separate TableWidget component.
 *
 * This isn't just a style preference: the original implementation used
 * `App\Filament\Widgets\ProductsIssuedReportWidget` rendered via
 * `<x-filament-widgets::widgets>` (first with a page-level shared filter
 * via HasFiltersForm, then with its own native table filter — see git
 * history). Both versions reproducibly triggered concurrent
 * /livewire/update requests that failed CSRF validation (419) on every
 * single page load, found via a real browser walkthrough. Splitting into
 * one-widget-per-page didn't help either — it was 100% reproducible even
 * with a single TableWidget. StockAlerts, which has never exhibited this
 * in the same testing, is the one page in this codebase that puts
 * InteractsWithTable directly on the Page class instead of a nested
 * Widget component (a separate child Livewire component embedded via
 * @livewire() in the page's Blade view). Converting to that same pattern
 * eliminated the failure entirely (verified with repeated reloads, not
 * just one clean run). If reintroducing a Widget-based report later,
 * re-verify with >5 consecutive page loads before trusting it — 1-2 clean
 * loads is not enough to know it's actually fixed.
 */
class Reports extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Products Issued Report';

    protected static ?string $title = 'Products Issued Report';

    protected static ?string $navigationGroup = 'Requests';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.reports';

    public static function canAccess(): bool
    {
        return Auth::user()?->can('view_reports') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ProductsIssuedReport::baseQuery())
            ->columns([
                Tables\Columns\TextColumn::make('product_name')->label('Product'),
                Tables\Columns\TextColumn::make('category_name')->label('Category'),
                Tables\Columns\TextColumn::make('total_issued')->label('Qty Issued')->numeric(),
                Tables\Columns\TextColumn::make('request_count')->label('Requests')->numeric(),
                Tables\Columns\TextColumn::make('last_issued_at')->label('Last Issued')->dateTime(),
            ])
            ->emptyStateHeading('Nothing issued in this period')
            ->filters([
                Tables\Filters\Filter::make('period')
                    ->form([
                        Forms\Components\Select::make('period')
                            ->label('Period')
                            ->options(['daily' => 'Daily', 'monthly' => 'Monthly', 'yearly' => 'Yearly'])
                            ->default('daily')
                            ->native(false)
                            ->required(),
                        Forms\Components\DatePicker::make('reference_date')
                            ->label('Reference Date')
                            ->default(now()->toDateString())
                            ->native(false)
                            ->required(),
                    ])
                    ->columns(2)
                    ->query(function ($query, array $data) {
                        [$from, $to] = ReportPeriod::resolve($data['period'] ?? null, $data['reference_date'] ?? null);

                        return ProductsIssuedReport::applyDateRange($query, $from, $to);
                    })
                    ->indicateUsing(fn (array $data) => ReportPeriod::label($data['period'] ?? null, $data['reference_date'] ?? null)),
            ])
            ->persistFiltersInSession(false)
            ->headerActions([
                Tables\Actions\Action::make('export')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn () => $this->exportCsv()),
            ]);
    }

    private function exportCsv(): StreamedResponse
    {
        $data = $this->getTableFilterState('period') ?? [];
        [$from, $to] = ReportPeriod::resolve($data['period'] ?? null, $data['reference_date'] ?? null);

        $rows = ProductsIssuedReport::query($from, $to)->get();

        return CsvExport::stream(
            'products-issued-'.$from->toDateString().'-to-'.$to->toDateString().'.csv',
            ProductsIssuedReport::csvHeaders(),
            $rows->map(fn ($row) => ProductsIssuedReport::toCsvRow($row)),
        );
    }
}
