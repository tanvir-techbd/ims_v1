<?php

namespace Tests\Feature;

use App\Enums\RequestItemStatus;
use App\Enums\RequestStatus;
use App\Exceptions\InventoryRuleException;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockRequest;
use App\Models\StockRequestItem;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(int $stock = 50): Product
    {
        return Product::factory()->create([
            'category_id' => Category::factory(),
            'unit_id' => Unit::factory(),
            'current_stock' => $stock,
        ]);
    }

    private function makeItem(Product $product, int $requestedQty): StockRequestItem
    {
        $request = StockRequest::factory()->create([
            'requester_id' => User::factory(),
        ]);

        return StockRequestItem::factory()->create([
            'stock_request_id' => $request->id,
            'product_id' => $product->id,
            'requested_qty' => $requestedQty,
        ]);
    }

    public function test_approval_cannot_exceed_requested_quantity(): void
    {
        $item = $this->makeItem($this->makeProduct(), requestedQty: 10);
        $approver = User::factory()->create();

        $this->expectException(InventoryRuleException::class);

        $item->approve(11, $approver);
    }

    public function test_approving_within_the_requested_quantity_updates_item_and_request_status(): void
    {
        $item = $this->makeItem($this->makeProduct(), requestedQty: 10);
        $approver = User::factory()->create();

        $item->approve(7, $approver);
        $item->refresh();

        $this->assertSame(7, $item->approved_qty);
        $this->assertSame(RequestItemStatus::Approved, $item->status);
        $this->assertSame(RequestStatus::Approved, $item->stockRequest->fresh()->status);
        $this->assertDatabaseHas('request_approvals', [
            'stock_request_item_id' => $item->id,
            'approver_id' => $approver->id,
            'approved_qty' => 7,
        ]);
    }

    public function test_rejecting_an_item_sets_approved_qty_to_zero_and_status_rejected(): void
    {
        $item = $this->makeItem($this->makeProduct(), requestedQty: 10);
        $approver = User::factory()->create();

        $item->reject($approver, 'Not needed this cycle.');
        $item->refresh();

        $this->assertSame(0, $item->approved_qty);
        $this->assertSame(RequestItemStatus::Rejected, $item->status);
        $this->assertSame(RequestStatus::Rejected, $item->stockRequest->fresh()->status);
    }

    public function test_issuance_cannot_exceed_approved_quantity(): void
    {
        $item = $this->makeItem($this->makeProduct(100), requestedQty: 10);
        $approver = User::factory()->create();
        $storekeeper = User::factory()->create();

        $item->approve(5, $approver);

        $this->expectException(InventoryRuleException::class);

        $item->issue(6, $storekeeper);
    }

    public function test_issuance_is_capped_by_available_stock_and_item_becomes_partially_issued(): void
    {
        $product = $this->makeProduct(stock: 3);
        $item = $this->makeItem($product, requestedQty: 10);
        $approver = User::factory()->create();
        $storekeeper = User::factory()->create();

        $item->approve(10, $approver);
        $issuance = $item->issue(10, $storekeeper);
        $item->refresh();

        $this->assertSame(3, $issuance->issued_qty);
        $this->assertSame(3, $item->issued_qty);
        $this->assertSame(RequestItemStatus::PartiallyIssued, $item->status);
        $this->assertSame(RequestStatus::PartiallyIssued, $item->stockRequest->fresh()->status);
        $this->assertSame(0, $product->fresh()->current_stock);
    }

    public function test_full_happy_path_from_request_to_full_issuance(): void
    {
        $product = $this->makeProduct(stock: 20);
        $item = $this->makeItem($product, requestedQty: 10);
        $approver = User::factory()->create();
        $storekeeper = User::factory()->create();

        $item->approve(10, $approver);
        $item->issue(10, $storekeeper);
        $item->refresh();

        $this->assertSame(RequestItemStatus::Issued, $item->status);
        $this->assertSame(RequestStatus::Issued, $item->stockRequest->fresh()->status);
        $this->assertSame(10, $product->fresh()->current_stock);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'out',
            'quantity' => 10,
        ]);
    }

    public function test_cannot_reject_an_item_that_already_had_stock_issued(): void
    {
        $product = $this->makeProduct(stock: 20);
        $item = $this->makeItem($product, requestedQty: 10);
        $approver = User::factory()->create();
        $storekeeper = User::factory()->create();

        $item->approve(10, $approver);
        $item->issue(4, $storekeeper);

        $this->expectException(InventoryRuleException::class);

        $item->reject($approver);
    }

    public function test_sequential_issuances_never_oversell_stock(): void
    {
        $product = $this->makeProduct(stock: 5);
        $item = $this->makeItem($product, requestedQty: 10);
        $approver = User::factory()->create();
        $storekeeper = User::factory()->create();

        $item->approve(10, $approver);

        // First issuance takes all 5 in stock, even though 8 were requested.
        $first = $item->issue(8, $storekeeper);
        $this->assertSame(5, $first->issued_qty);
        $this->assertSame(0, $product->fresh()->current_stock);

        // A second issuance attempt with nothing left in stock must not oversell.
        $this->expectException(InventoryRuleException::class);
        $item->issue(2, $storekeeper);
    }

    public function test_recording_stock_in_increases_current_stock_and_logs_movement(): void
    {
        $product = $this->makeProduct(stock: 10);
        $actor = User::factory()->create();

        $product->recordStockIn(25, $actor, 'PO-1001 delivery');

        $this->assertSame(35, $product->fresh()->current_stock);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'in',
            'quantity' => 25,
            'created_by' => $actor->id,
        ]);
    }

}
