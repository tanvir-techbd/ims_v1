<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

/**
 * Replaces Filament's default AccountWidget ("Welcome / Sign out" card) and
 * FilamentInfoWidget (version/docs/GitHub links) — generic framework
 * boilerplate with nothing to do with this app. Just a simple, role-aware
 * greeting instead.
 */
class WelcomeWidget extends Widget
{
    protected static string $view = 'filament.widgets.welcome-widget';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -10;

    public function getGreeting(): string
    {
        $hour = (int) now()->format('G');

        return match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default => 'Good evening',
        };
    }

    public function getRoleLabel(): ?string
    {
        return Auth::user()?->getRoleNames()->first();
    }
}
