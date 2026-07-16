<?php

namespace Tests\Feature;

use App\Enums\RequestItemStatus;
use App\Enums\RequestStatus;
use Filament\Facades\Filament;
use Tests\TestCase;

/**
 * Guards against a real bug found during the Phase 8 browser walkthrough:
 * RequestStatus/RequestItemStatus's color() methods return 'purple' for the
 * partial states, but 'purple' isn't one of Filament's built-in semantic
 * badge colors (primary/secondary/success/warning/danger/info/gray) — it
 * has to be explicitly registered via the panel's ->colors([...]) or the
 * badge silently renders with no background/text color at all (no error,
 * no exception — just plain unstyled text sitting next to properly-styled
 * badges). Screenshots caught it; this test makes sure it can't come back
 * quietly if someone adds a new status color without registering it.
 */
class BadgeColorRegistrationTest extends TestCase
{
    private const BUILT_IN_COLORS = ['primary', 'secondary', 'success', 'warning', 'danger', 'info', 'gray'];

    public function test_every_request_status_color_is_a_registered_or_built_in_filament_color(): void
    {
        $registered = array_keys(Filament::getPanel('admin')->getColors());

        foreach (RequestStatus::cases() as $status) {
            $color = $status->color();

            $this->assertTrue(
                in_array($color, self::BUILT_IN_COLORS, true) || in_array($color, $registered, true),
                "RequestStatus::{$status->name}->color() returns '{$color}', which is neither a built-in ".
                'Filament color nor registered in AdminPanelProvider->colors() — the badge will render unstyled.'
            );
        }
    }

    public function test_every_request_item_status_color_is_a_registered_or_built_in_filament_color(): void
    {
        $registered = array_keys(Filament::getPanel('admin')->getColors());

        foreach (RequestItemStatus::cases() as $status) {
            $color = $status->color();

            $this->assertTrue(
                in_array($color, self::BUILT_IN_COLORS, true) || in_array($color, $registered, true),
                "RequestItemStatus::{$status->name}->color() returns '{$color}', which is neither a built-in ".
                'Filament color nor registered in AdminPanelProvider->colors() — the badge will render unstyled.'
            );
        }
    }
}
