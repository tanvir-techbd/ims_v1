<?php

namespace App\Filament\Pages;

use App\Support\Reports\CsvExport;
use App\Support\Reports\ReportPeriod;
use App\Support\Reports\UserActivityReport as UserActivityReportSupport;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Detailed user activity records" (PLAN.md's reports requirement).
 * HasTable directly on the page — see Reports's docblock for why (a real,
 * 100%-reproducible CSRF/419 bug with the Widget-based version this
 * replaced).
 */
class UserActivityReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'User Activity Report';

    protected static ?string $title = 'User Activity Report';

    protected static ?string $navigationGroup = 'Requests';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.user-activity-report';

    public static function canAccess(): bool
    {
        return Auth::user()?->can('view_reports') ?? false;
    }

    public function table(Table $table): Table
    {
        [$defaultFrom, $defaultTo] = ReportPeriod::resolve(null, null);

        return $table
            ->query(UserActivityReportSupport::query($defaultFrom, $defaultTo))
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('User'),
                Tables\Columns\TextColumn::make('email'),
                Tables\Columns\TextColumn::make('stock_requests_count')->label('Requests Created')->numeric(),
                Tables\Columns\TextColumn::make('approvals_made_count')->label('Approvals Made')->numeric(),
                Tables\Columns\TextColumn::make('issuances_made_count')->label('Issuances Made')->numeric(),
            ])
            ->emptyStateHeading('No user activity in this period')
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

                        return UserActivityReportSupport::query($from, $to);
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

        $rows = UserActivityReportSupport::query($from, $to)->get();

        return CsvExport::stream(
            'user-activity-'.$from->toDateString().'-to-'.$to->toDateString().'.csv',
            UserActivityReportSupport::csvHeaders(),
            $rows->map(fn ($user) => UserActivityReportSupport::toCsvRow($user)),
        );
    }
}
