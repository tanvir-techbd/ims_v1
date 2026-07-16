<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

/**
 * Admin-only. Currently just the one global low-stock threshold (PLAN.md
 * §1/§8) — kept as a single custom page rather than a full Settings
 * resource since there's exactly one thing to edit; revisit if more
 * system-wide settings get added later.
 */
class Settings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Access Control';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole('Admin') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'low_stock_threshold' => (int) Setting::get('low_stock_threshold', 10),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('low_stock_threshold')
                    ->label('Global Low-Stock Threshold')
                    ->numeric()
                    ->minValue(0)
                    ->required()
                    ->helperText('Products at or below this stock level appear on the Stock Alerts page and dashboard widget, regardless of category.'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::set('low_stock_threshold', (int) $data['low_stock_threshold']);

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}
