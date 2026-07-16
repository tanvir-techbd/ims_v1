<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ItemGroup;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Not a substitute for opening the panel in a browser, but catches the
 * cheap-to-catch class of bug: a resource's form()/table() referencing a
 * relationship/column that doesn't exist, which only blows up once the
 * page actually renders for an authenticated user (route registration
 * alone doesn't execute those methods).
 */
class AdminPanelSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::create(['name' => 'Admin', 'guard_name' => 'web']));

        return $admin;
    }

    public function test_admin_can_load_every_new_resource_index_page(): void
    {
        $admin = $this->admin();

        $paths = [
            '/admin',
            '/admin/categories',
            '/admin/units',
            '/admin/item-groups',
            '/admin/user-groups',
            '/admin/products',
            '/admin/users',
        ];

        foreach ($paths as $path) {
            $this->actingAs($admin)->get($path)->assertOk();
        }
    }

    public function test_admin_can_load_every_new_resource_create_page(): void
    {
        $admin = $this->admin();

        $paths = [
            '/admin/categories/create',
            '/admin/units/create',
            '/admin/item-groups/create',
            '/admin/user-groups/create',
            '/admin/products/create',
            '/admin/users/create',
        ];

        foreach ($paths as $path) {
            $this->actingAs($admin)->get($path)->assertOk();
        }
    }

    public function test_admin_can_load_edit_pages_for_a_seeded_record_of_each_type(): void
    {
        $admin = $this->admin();
        $category = Category::factory()->create();
        $unit = Unit::factory()->create();
        $itemGroup = ItemGroup::factory()->create();
        $userGroup = UserGroup::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
        ]);

        $paths = [
            "/admin/categories/{$category->id}/edit",
            "/admin/units/{$unit->id}/edit",
            "/admin/item-groups/{$itemGroup->id}/edit",
            "/admin/user-groups/{$userGroup->id}/edit",
            "/admin/products/{$product->id}/edit",
            "/admin/users/{$admin->id}/edit",
        ];

        foreach ($paths as $path) {
            $this->actingAs($admin)->get($path)->assertOk();
        }
    }

    public function test_demander_only_sees_products_they_are_permitted_to_order(): void
    {
        $demanderRole = Role::create(['name' => 'Demander', 'guard_name' => 'web']);
        $demanderRole->givePermissionTo([
            Permission::firstOrCreate(['name' => 'view_any_product', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'view_product', 'guard_name' => 'web']),
        ]);
        $demander = User::factory()->create();
        $demander->assignRole('Demander');

        $category = Category::factory()->create();
        $unit = Unit::factory()->create();

        $openProduct = Product::factory()->create([
            'name' => 'Open Product',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
        ]);

        $restrictedProduct = Product::factory()->create([
            'name' => 'Restricted Product',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
        ]);
        $itemGroup = ItemGroup::factory()->create();
        $restrictedProduct->itemGroups()->attach($itemGroup);

        $response = $this->actingAs($demander)->get('/admin/products');

        $response->assertOk();
        $response->assertSee('Open Product');
        $response->assertDontSee('Restricted Product');
    }

    public function test_a_user_with_no_role_cannot_access_the_panel(): void
    {
        $roleless = User::factory()->create();

        $this->actingAs($roleless)->get('/admin')->assertForbidden();
    }
}
