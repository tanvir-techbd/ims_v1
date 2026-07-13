<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockRequest;
use App\Models\StockRequestItem;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Deliberately does NOT use RefreshDatabase: that trait wraps each test in
 * an uncommitted transaction on the default connection, which would make
 * the setup data invisible to the second, independent connection this test
 * needs in order to prove StockRequestItem::issue()'s lockForUpdate() is a
 * real database-level lock (not just an in-process guard). Since nothing
 * here is wrapped in a rolled-back transaction, every row created is
 * deleted explicitly in tearDown() to keep the testing database clean.
 */
class ConcurrentIssuanceLockTest extends TestCase
{
    private array $createdModelIds = [];

    protected function tearDown(): void
    {
        DB::table('stock_movements')->whereIn('product_id', $this->createdModelIds['products'] ?? [])->delete();
        DB::table('stock_request_items')->whereIn('id', $this->createdModelIds['items'] ?? [])->delete();
        DB::table('stock_requests')->whereIn('id', $this->createdModelIds['requests'] ?? [])->delete();
        DB::table('products')->whereIn('id', $this->createdModelIds['products'] ?? [])->delete();
        DB::table('categories')->whereIn('id', $this->createdModelIds['categories'] ?? [])->delete();
        DB::table('units')->whereIn('id', $this->createdModelIds['units'] ?? [])->delete();
        DB::table('users')->whereIn('id', $this->createdModelIds['users'] ?? [])->delete();

        parent::tearDown();
    }

    public function test_a_held_row_lock_blocks_a_concurrent_issuance_attempt(): void
    {
        $category = Category::factory()->create();
        $unit = Unit::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'current_stock' => 10,
        ]);

        $requester = User::factory()->create();
        $approver = User::factory()->create();
        $storekeeper = User::factory()->create();

        $request = StockRequest::factory()->create(['requester_id' => $requester->id]);
        $item = StockRequestItem::factory()->create([
            'stock_request_id' => $request->id,
            'product_id' => $product->id,
            'requested_qty' => 10,
        ]);

        $this->createdModelIds = [
            'categories' => [$category->id],
            'units' => [$unit->id],
            'products' => [$product->id],
            'requests' => [$request->id],
            'items' => [$item->id],
            'users' => [$requester->id, $approver->id, $storekeeper->id],
        ];

        $item->approve(10, $approver);

        // The *blocked* connection is the one whose lock-wait-timeout applies
        // while it waits — that's the default connection issue() runs on,
        // not the one holding the lock. Shorten it so the test fails fast
        // instead of waiting out MySQL's ~50s default.
        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

        // Open a second, independent connection to the same database and
        // hold a row lock on the product from within an uncommitted
        // transaction on that connection.
        $second = DB::connection('mysql_lock_test');
        $second->beginTransaction();
        $second->select('SELECT * FROM products WHERE id = ? FOR UPDATE', [$product->id]);

        try {
            // The main connection's issue() call must block on the lock held
            // above and fail with a lock-wait-timeout, proving it's a real
            // row lock rather than an application-level check that could be
            // bypassed by a second concurrent request.
            $this->expectException(QueryException::class);
            $this->expectExceptionMessageMatches('/Lock wait timeout exceeded/i');

            $item->issue(5, $storekeeper);
        } finally {
            $second->rollBack();
        }
    }
}
