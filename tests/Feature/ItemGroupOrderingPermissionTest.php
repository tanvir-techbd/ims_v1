<?php

namespace Tests\Feature;

use App\Exceptions\InventoryRuleException;
use App\Models\Category;
use App\Models\ItemGroup;
use App\Models\Product;
use App\Models\StockRequest;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ItemGroupOrderingPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(): Product
    {
        return Product::factory()->create([
            'category_id' => Category::factory(),
            'unit_id' => Unit::factory(),
        ]);
    }

    public function test_a_product_with_no_item_groups_is_orderable_by_anyone(): void
    {
        $product = $this->makeProduct();
        $demander = User::factory()->create();

        $this->assertTrue($demander->canOrderProduct($product));
    }

    public function test_a_classified_product_is_not_orderable_without_a_matching_permission(): void
    {
        $product = $this->makeProduct();
        $itemGroup = ItemGroup::factory()->create();
        $product->itemGroups()->attach($itemGroup);

        $demander = User::factory()->create();

        $this->assertFalse($demander->canOrderProduct($product));
    }

    public function test_a_classified_product_is_orderable_once_the_demanders_group_is_granted_access(): void
    {
        $product = $this->makeProduct();
        $itemGroup = ItemGroup::factory()->create();
        $product->itemGroups()->attach($itemGroup);

        $userGroup = UserGroup::factory()->create();
        $userGroup->grantItemGroup($itemGroup);

        $demander = User::factory()->create();
        $demander->userGroups()->attach($userGroup);

        $this->assertTrue($demander->canOrderProduct($product));
    }

    public function test_permitted_item_groups_are_the_union_across_all_of_a_users_groups(): void
    {
        $productA = $this->makeProduct();
        $groupA = ItemGroup::factory()->create();
        $productA->itemGroups()->attach($groupA);

        $productB = $this->makeProduct();
        $groupB = ItemGroup::factory()->create();
        $productB->itemGroups()->attach($groupB);

        $userGroup1 = UserGroup::factory()->create();
        $userGroup1->grantItemGroup($groupA);

        $userGroup2 = UserGroup::factory()->create();
        $userGroup2->grantItemGroup($groupB);

        $demander = User::factory()->create();
        $demander->userGroups()->attach([$userGroup1->id, $userGroup2->id]);

        $this->assertTrue($demander->canOrderProduct($productA));
        $this->assertTrue($demander->canOrderProduct($productB));
    }

    public function test_admin_bypasses_the_item_group_restriction_entirely(): void
    {
        $product = $this->makeProduct();
        $itemGroup = ItemGroup::factory()->create();
        $product->itemGroups()->attach($itemGroup);

        $admin = User::factory()->create();
        $admin->assignRole(Role::create(['name' => 'Admin', 'guard_name' => 'web']));

        $this->assertTrue($admin->canOrderProduct($product));
    }

    public function test_stock_request_add_item_rejects_a_product_the_requester_cannot_order(): void
    {
        $product = $this->makeProduct();
        $itemGroup = ItemGroup::factory()->create();
        $product->itemGroups()->attach($itemGroup);

        $demander = User::factory()->create();
        $request = StockRequest::factory()->create(['requester_id' => $demander->id]);

        $this->expectException(InventoryRuleException::class);

        $request->addItem($product, 5);
    }

    public function test_stock_request_add_item_succeeds_for_a_permitted_product(): void
    {
        $product = $this->makeProduct();
        $itemGroup = ItemGroup::factory()->create();
        $product->itemGroups()->attach($itemGroup);

        $userGroup = UserGroup::factory()->create();
        $userGroup->grantItemGroup($itemGroup);

        $demander = User::factory()->create();
        $demander->userGroups()->attach($userGroup);

        $request = StockRequest::factory()->create(['requester_id' => $demander->id]);
        $item = $request->addItem($product, 5);

        $this->assertDatabaseHas('stock_request_items', [
            'id' => $item->id,
            'stock_request_id' => $request->id,
            'product_id' => $product->id,
            'requested_qty' => 5,
        ]);
    }
}
