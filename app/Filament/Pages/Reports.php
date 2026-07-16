<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ProductsIssuedReportWidget;
use App\Filament\Widgets\UserActivityReportWidget;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

/**
 * Daily / Monthly / Yearly reports (PLAN.md's reports requirement) — one
 * page, a shared period filter, two report tables (products issued, user
 * activity) that both react to the same filter via Filament's dashboard-
 * style widget filters (HasFiltersForm + InteractsWithPageFilters), rather
 * than three separate pages per period.
 */
class Reports extends Page
{
    use HasFiltersForm;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Requests';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.reports';

    public static function canAccess(): bool
    {
        return Auth::user()?->can('view_reports') ?? false;
    }

    public function filtersForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('period')
                    ->label('Period')
                    ->options([
                        'daily' => 'Daily',
                        'monthly' => 'Monthly',
                        'yearly' => 'Yearly',
                    ])
                    ->default('daily')
                    ->native(false)
                    ->required(),
                Forms\Components\DatePicker::make('reference_date')
                    ->label('Reference Date')
                    ->default(now()->toDateString())
                    ->native(false)
                    ->required()
                    ->helperText('For Monthly/Yearly, any date within the period works — the whole month/year is used.'),
            ])
            ->columns(2);
    }

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            ProductsIssuedReportWidget::class,
            UserActivityReportWidget::class,
        ];
    }

    public function getColumns(): int|string|array
    {
        return 1;
    }
}
