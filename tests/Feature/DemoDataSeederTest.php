<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockRequest;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards against the exact class of bug this seeder hit once already: a
 * demo demander requesting a product outside their user-group's permitted
 * item-groups, which throws InventoryRuleException and aborts the whole
 * seed run.
 */
class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_data_seeds_without_error_and_is_internally_consistent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoDataSeeder::class);

        $this->assertGreaterThanOrEqual(10, Product::count());
        $this->assertGreaterThanOrEqual(3, StockRequest::count());
        $this->assertGreaterThanOrEqual(6, User::count());
    }

    public function test_running_demo_data_seeder_twice_does_not_duplicate_records(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoDataSeeder::class);

        $productCount = Product::count();
        $requestCount = StockRequest::count();
        $userCount = User::count();

        $this->seed(DemoDataSeeder::class);

        $this->assertSame($productCount, Product::count());
        $this->assertSame($requestCount, StockRequest::count());
        $this->assertSame($userCount, User::count());
    }
}
