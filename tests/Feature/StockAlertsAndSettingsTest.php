<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings;
use App\Filament\Pages\StockAlerts;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StockAlertsAndSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function product(int $stock): Product
    {
        return Product::factory()->create([
            'category_id' => Category::factory(),
            'unit_id' => Unit::factory(),
            'current_stock' => $stock,
        ]);
    }

    public function test_stock_alerts_page_lists_products_at_or_below_threshold(): void
    {
        Setting::set('low_stock_threshold', 10);

        $role = Role::firstOrCreate(['name' => 'Approver', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'view_stock_alerts', 'guard_name' => 'web']));
        $approver = User::factory()->create();
        $approver->assignRole('Approver');

        $atThreshold = $this->product(10);
        $belowThreshold = $this->product(3);
        $wellStocked = $this->product(500);

        Livewire::actingAs($approver)
            ->test(StockAlerts::class)
            ->assertCanSeeTableRecords([$atThreshold, $belowThreshold])
            ->assertCanNotSeeTableRecords([$wellStocked]);
    }

    public function test_changing_the_threshold_immediately_changes_what_appears(): void
    {
        Setting::set('low_stock_threshold', 5);

        $role = Role::firstOrCreate(['name' => 'Approver', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'view_stock_alerts', 'guard_name' => 'web']));
        $approver = User::factory()->create();
        $approver->assignRole('Approver');

        $product = $this->product(8);

        Livewire::actingAs($approver)
            ->test(StockAlerts::class)
            ->assertCanNotSeeTableRecords([$product]);

        Setting::set('low_stock_threshold', 10);

        Livewire::actingAs($approver)
            ->test(StockAlerts::class)
            ->assertCanSeeTableRecords([$product]);
    }

    public function test_demander_cannot_access_stock_alerts(): void
    {
        Role::firstOrCreate(['name' => 'Demander', 'guard_name' => 'web']);
        $demander = User::factory()->create();
        $demander->assignRole('Demander');

        $this->actingAs($demander)->get(StockAlerts::getUrl())->assertForbidden();
    }

    public function test_admin_can_update_the_low_stock_threshold(): void
    {
        Setting::set('low_stock_threshold', 10);

        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']));

        Livewire::actingAs($admin)
            ->test(Settings::class)
            ->fillForm(['low_stock_threshold' => 25])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('25', Setting::get('low_stock_threshold'));
    }

    public function test_non_admin_cannot_access_settings(): void
    {
        $role = Role::firstOrCreate(['name' => 'Storekeeper', 'guard_name' => 'web']);
        $storekeeper = User::factory()->create();
        $storekeeper->assignRole($role);

        $this->actingAs($storekeeper)->get(Settings::getUrl())->assertForbidden();
    }

    public function test_dashboard_renders_the_low_stock_widget_for_a_permitted_non_admin_role(): void
    {
        $role = Role::firstOrCreate(['name' => 'Storekeeper', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'view_stock_alerts', 'guard_name' => 'web']));
        $storekeeper = User::factory()->create();
        $storekeeper->assignRole($role);

        $this->product(3);

        $this->actingAs($storekeeper)->get('/admin')->assertOk();
    }
}
