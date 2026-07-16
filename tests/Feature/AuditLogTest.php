<?php

namespace Tests\Feature;

use App\Filament\Resources\ActivityResource;
use App\Filament\Resources\ActivityResource\Pages\ListActivities;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']));

        return $admin;
    }

    public function test_editing_a_model_produces_a_readable_log_entry_with_old_and_new_values(): void
    {
        $category = Category::factory()->create(['description' => 'Before']);
        $category->update(['description' => 'After']);

        $activity = Activity::query()->where('event', 'updated')->latest('id')->first();

        $this->assertNotNull($activity);
        $this->assertSame(Category::class, $activity->subject_type);
        $this->assertSame('Before', $activity->attribute_changes->get('old')['description']);
        $this->assertSame('After', $activity->attribute_changes->get('attributes')['description']);
    }

    public function test_causer_is_captured_as_the_acting_authenticated_user(): void
    {
        $admin = $this->admin();
        Auth::login($admin);

        Category::factory()->create();

        $activity = Activity::query()->where('event', 'created')->latest('id')->first();

        $this->assertSame($admin->id, $activity->causer_id);
        $this->assertSame(User::class, $activity->causer_type);
    }

    public function test_stock_in_does_not_produce_duplicate_noisy_product_update_logs(): void
    {
        $product = Product::factory()->create([
            'category_id' => Category::factory(),
            'unit_id' => Unit::factory(),
            'current_stock' => 10,
        ]);

        $actor = User::factory()->create();
        $product->recordStockIn(5, $actor);

        // current_stock is deliberately excluded from Product's activity log
        // (StockMovement is already the purpose-built ledger for that) — so
        // recordStockIn should not have produced an "updated" Product entry.
        $this->assertSame(
            0,
            Activity::query()->where('subject_type', Product::class)->where('event', 'updated')->count()
        );
    }

    public function test_user_password_changes_are_never_logged(): void
    {
        $user = User::factory()->create();
        $user->update(['password' => 'a-new-password']);

        $activity = Activity::query()->where('subject_type', User::class)->where('event', 'updated')->latest('id')->first();

        if ($activity) {
            $this->assertArrayNotHasKey('password', $activity->attribute_changes->get('attributes', []));
        } else {
            // No log at all is also correct here: password was the only
            // dirty attribute and it's excluded, so dontLogEmptyChanges()
            // means nothing gets written.
            $this->assertTrue(true);
        }
    }

    public function test_activity_log_is_accessible_to_admin_and_forbidden_to_non_admin(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(ActivityResource::getUrl())->assertOk();

        $role = Role::firstOrCreate(['name' => 'Storekeeper', 'guard_name' => 'web']);
        $storekeeper = User::factory()->create();
        $storekeeper->assignRole($role);

        $this->actingAs($storekeeper)->get(ActivityResource::getUrl())->assertForbidden();
    }

    public function test_activity_log_can_be_filtered_by_model_type(): void
    {
        $admin = $this->admin();
        Auth::login($admin);

        Category::factory()->create(['name' => 'Widgets']);
        Unit::factory()->create(['name' => 'Box']);

        Livewire::actingAs($admin)
            ->test(ListActivities::class)
            ->filterTable('subject_type', Category::class)
            ->assertCanSeeTableRecords(
                Activity::query()->where('subject_type', Category::class)->get()
            )
            ->assertCanNotSeeTableRecords(
                Activity::query()->where('subject_type', Unit::class)->get()
            );
    }

    public function test_activity_log_can_be_filtered_by_date_range(): void
    {
        $admin = $this->admin();
        Auth::login($admin);

        $inRange = Category::factory()->create(['name' => 'In Range']);
        Activity::query()->where('subject_type', Category::class)
            ->where('subject_id', $inRange->id)
            ->update(['created_at' => '2026-07-16 10:00:00']);

        $outOfRange = Category::factory()->create(['name' => 'Out Of Range']);
        Activity::query()->where('subject_type', Category::class)
            ->where('subject_id', $outOfRange->id)
            ->update(['created_at' => '2026-06-01 10:00:00']);

        Livewire::actingAs($admin)
            ->test(ListActivities::class)
            ->filterTable('created_at', ['from' => '2026-07-15', 'until' => '2026-07-17'])
            ->assertCanSeeTableRecords(
                Activity::query()->where('subject_id', $inRange->id)->get()
            )
            ->assertCanNotSeeTableRecords(
                Activity::query()->where('subject_id', $outOfRange->id)->get()
            );
    }
}
