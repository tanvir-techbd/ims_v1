<?php

namespace App\Filament\Widgets;

use App\Support\Reports\CsvExport;
use App\Support\Reports\ProductsIssuedReport;
use App\Support\Reports\ReportPeriod;
use Carbon\CarbonImmutable;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductsIssuedReportWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        [$from, $to] = ReportPeriod::resolve($this->filters['period'] ?? null, $this->filters['reference_date'] ?? null);

        return $table
            ->query(ProductsIssuedReport::query($from, $to))
            ->heading('Products Issued — '.ReportPeriod::label($this->filters['period'] ?? null, $this->filters['reference_date'] ?? null))
            ->columns([
                Tables\Columns\TextColumn::make('product_name')->label('Product'),
                Tables\Columns\TextColumn::make('category_name')->label('Category'),
                Tables\Columns\TextColumn::make('total_issued')->label('Qty Issued')->numeric(),
                Tables\Columns\TextColumn::make('request_count')->label('Requests')->numeric(),
                Tables\Columns\TextColumn::make('last_issued_at')->label('Last Issued')->dateTime(),
            ])
            ->emptyStateHeading('Nothing issued in this period')
            ->headerActions([
                Tables\Actions\Action::make('export')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn () => $this->exportCsv($from, $to)),
            ]);
    }

    private function exportCsv(CarbonImmutable $from, CarbonImmutable $to): StreamedResponse
    {
        $rows = ProductsIssuedReport::query($from, $to)->get();

        return CsvExport::stream(
            'products-issued-'.$from->toDateString().'-to-'.$to->toDateString().'.csv',
            ProductsIssuedReport::csvHeaders(),
            $rows->map(fn ($row) => ProductsIssuedReport::toCsvRow($row)),
        );
    }
}
