<?php

namespace App\Filament\Widgets;

use App\Support\Reports\CsvExport;
use App\Support\Reports\ReportPeriod;
use App\Support\Reports\UserActivityReport;
use Carbon\CarbonImmutable;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserActivityReportWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        [$from, $to] = ReportPeriod::resolve($this->filters['period'] ?? null, $this->filters['reference_date'] ?? null);

        return $table
            ->query(UserActivityReport::query($from, $to))
            ->heading('User Activity — '.ReportPeriod::label($this->filters['period'] ?? null, $this->filters['reference_date'] ?? null))
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('User'),
                Tables\Columns\TextColumn::make('email'),
                Tables\Columns\TextColumn::make('stock_requests_count')->label('Requests Created')->numeric(),
                Tables\Columns\TextColumn::make('approvals_made_count')->label('Approvals Made')->numeric(),
                Tables\Columns\TextColumn::make('issuances_made_count')->label('Issuances Made')->numeric(),
            ])
            ->emptyStateHeading('No user activity in this period')
            ->headerActions([
                Tables\Actions\Action::make('export')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn () => $this->exportCsv($from, $to)),
            ]);
    }

    private function exportCsv(CarbonImmutable $from, CarbonImmutable $to): StreamedResponse
    {
        $rows = UserActivityReport::query($from, $to)->get();

        return CsvExport::stream(
            'user-activity-'.$from->toDateString().'-to-'.$to->toDateString().'.csv',
            UserActivityReport::csvHeaders(),
            $rows->map(fn ($user) => UserActivityReport::toCsvRow($user)),
        );
    }
}
