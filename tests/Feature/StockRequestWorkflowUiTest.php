<?php

namespace Tests\Feature;

use App\Enums\RequestItemStatus;
use App\Enums\RequestStatus;
use App\Filament\Resources\StockRequestResource\Pages\CreateStockRequest;
use App\Filament\Resources\StockRequestResource\Pages\ListStockRequests;
use App\Filament\Resources\StockRequestResource\Pages\ViewStockRequest;
use App\Filament\Resources\StockRequestResource\RelationManagers\ItemsRelationManager;
use App\Models\Category;
use App\Models\ItemGroup;
use App\Models\Product;
use App\Models\StockRequest;
use App\Models\StockRequestItem;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * End-to-end tests through the actual Filament/Livewire UI layer (not just
 * the model methods, which are already covered by InventoryWorkflowTest and
 * ItemGroupOrderingPermissionTest) — this is what catches wiring bugs like
 * a form field name that doesn't match what handleRecordCreation() expects.
 */
class StockRequestWorkflowUiTest extends TestCase
{
    use RefreshDatabase;

    private function demander(): User
    {
        $role = Role::firstOrCreate(['name' => 'Demander', 'guard_name' => 'web']);
        $role->givePermissionTo([
            Permission::firstOrCreate(['name' => 'view_any_product', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'view_product', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'view_any_stock::request', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'view_stock::request', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'create_stock::request', 'guard_name' => 'web']),
        ]);

        $demander = User::factory()->create();
        $demander->assignRole('Demander');

        return $demander;
    }

    private function approver(): User
    {
        $role = Role::firstOrCreate(['name' => 'Approver', 'guard_name' => 'web']);
        $role->givePermissionTo([
            Permission::firstOrCreate(['name' => 'view_any_stock::request', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'view_stock::request', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'approve_request', 'guard_name' => 'web']),
        ]);

        $approver = User::factory()->create();
        $approver->assignRole('Approver');

        return $approver;
    }

    private function storekeeper(): User
    {
        $role = Role::firstOrCreate(['name' => 'Storekeeper', 'guard_name' => 'web']);
        $role->givePermissionTo([
            Permission::firstOrCreate(['name' => 'view_any_stock::request', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'view_stock::request', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'issue_request', 'guard_name' => 'web']),
        ]);

        $storekeeper = User::factory()->create();
        $storekeeper->assignRole('Storekeeper');

        return $storekeeper;
    }

    private function product(int $stock = 50): Product
    {
        return Product::factory()->create([
            'category_id' => Category::factory(),
            'unit_id' => Unit::factory(),
            'current_stock' => $stock,
        ]);
    }

    public function test_demander_can_create_a_request_for_an_unrestricted_product(): void
    {
        $demander = $this->demander();
        $product = $this->product();

        Livewire::actingAs($demander)
            ->test(CreateStockRequest::class)
            ->fillForm([
                'notes' => 'Need these for the front desk.',
                'items' => [
                    ['product_id' => $product->id, 'requested_qty' => 5],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('stock_requests', ['requester_id' => $demander->id]);
        $this->assertDatabaseHas('stock_request_items', [
            'product_id' => $product->id,
            'requested_qty' => 5,
        ]);
    }

    public function test_demander_cannot_create_a_request_for_a_product_their_group_is_not_permitted_for(): void
    {
        $demander = $this->demander();
        $product = $this->product();
        $itemGroup = ItemGroup::factory()->create();
        $product->itemGroups()->attach($itemGroup);

        // No UserGroup granted for $itemGroup, so $demander has no permitted path to it.
        Livewire::actingAs($demander)
            ->test(CreateStockRequest::class)
            ->fillForm([
                'items' => [
                    ['product_id' => $product->id, 'requested_qty' => 5],
                ],
            ])
            ->call('create');

        // The whole creation is wrapped in a transaction, so a denied item
        // rolls back the request too — nothing should have been persisted.
        $this->assertDatabaseCount('stock_requests', 0);
        $this->assertDatabaseCount('stock_request_items', 0);
    }

    public function test_demander_only_sees_their_own_requests_in_the_list(): void
    {
        $demander = $this->demander();
        $otherDemander = User::factory()->create();
        $otherDemander->assignRole('Demander');

        $mine = StockRequest::factory()->create(['requester_id' => $demander->id]);
        $theirs = StockRequest::factory()->create(['requester_id' => $otherDemander->id]);

        Livewire::actingAs($demander)
            ->test(ListStockRequests::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_approver_can_approve_an_item_within_the_requested_quantity(): void
    {
        $approver = $this->approver();
        $product = $this->product();
        $stockRequest = StockRequest::factory()->create(['requester_id' => User::factory()->create()->id]);
        $item = StockRequestItem::factory()->create([
            'stock_request_id' => $stockRequest->id,
            'product_id' => $product->id,
            'requested_qty' => 10,
        ]);

        Livewire::actingAs($approver)
            ->test(ItemsRelationManager::class, [
                'ownerRecord' => $stockRequest,
                'pageClass' => ViewStockRequest::class,
            ])
            ->callTableAction('approve', $item, data: ['approved_qty' => 7, 'remarks' => 'Reduced slightly.'])
            ->assertHasNoTableActionErrors();

        $item->refresh();
        $this->assertSame(7, $item->approved_qty);
        $this->assertSame(RequestItemStatus::Approved, $item->status);
        $this->assertDatabaseHas('request_approvals', ['approver_id' => $approver->id, 'approved_qty' => 7]);
    }

    public function test_approver_cannot_approve_more_than_requested_via_the_form(): void
    {
        $approver = $this->approver();
        $product = $this->product();
        $stockRequest = StockRequest::factory()->create(['requester_id' => User::factory()->create()->id]);
        $item = StockRequestItem::factory()->create([
            'stock_request_id' => $stockRequest->id,
            'product_id' => $product->id,
            'requested_qty' => 10,
        ]);

        Livewire::actingAs($approver)
            ->test(ItemsRelationManager::class, [
                'ownerRecord' => $stockRequest,
                'pageClass' => ViewStockRequest::class,
            ])
            ->callTableAction('approve', $item, data: ['approved_qty' => 15])
            ->assertHasTableActionErrors(['approved_qty']);

        $this->assertNull($item->fresh()->approved_qty);
    }

    public function test_storekeeper_can_issue_an_approved_item_capped_by_stock(): void
    {
        $storekeeper = $this->storekeeper();
        $product = $this->product(stock: 4);
        $stockRequest = StockRequest::factory()->create(['requester_id' => User::factory()->create()->id]);
        $item = StockRequestItem::factory()->create([
            'stock_request_id' => $stockRequest->id,
            'product_id' => $product->id,
            'requested_qty' => 10,
            'approved_qty' => 10,
            'status' => RequestItemStatus::Approved,
        ]);

        Livewire::actingAs($storekeeper)
            ->test(ItemsRelationManager::class, [
                'ownerRecord' => $stockRequest,
                'pageClass' => ViewStockRequest::class,
            ])
            ->callTableAction('issue', $item, data: ['issue_qty' => 10])
            ->assertHasNoTableActionErrors();

        $item->refresh();
        $this->assertSame(4, $item->issued_qty);
        $this->assertSame(RequestItemStatus::PartiallyIssued, $item->status);
        $this->assertSame(0, $product->fresh()->current_stock);
    }

    public function test_requester_can_cancel_their_own_pending_request(): void
    {
        $demander = $this->demander();
        $stockRequest = StockRequest::factory()->create(['requester_id' => $demander->id]);

        Livewire::actingAs($demander)
            ->test(ListStockRequests::class)
            ->callTableAction('cancel', $stockRequest);

        $this->assertSame(RequestStatus::Cancelled, $stockRequest->fresh()->status);
    }
}
