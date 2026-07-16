<?php

namespace Tests\Feature;

use App\Filament\Resources\StockRequestResource\Pages\ListStockRequests;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockRequest;
use App\Models\Unit;
use App\Models\User;
use Filament\Livewire\GlobalSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GlobalSearchAndFiltersTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']));

        return $admin;
    }

    public function test_global_search_finds_a_product_by_sku(): void
    {
        $admin = $this->admin();
        Product::factory()->create([
            'name' => 'Definitely Findable Widget',
            'sku' => 'UNIQUE-SKU-4471',
            'category_id' => Category::factory(),
            'unit_id' => Unit::factory(),
        ]);

        $results = Livewire::actingAs($admin)
            ->test(GlobalSearch::class)
            ->set('search', 'UNIQUE-SKU-4471')
            ->instance()
            ->getResults();

        $titles = collect($results?->getCategories())
            ->flatMap(fn ($category) => $category)
            ->map(fn ($result) => $result->title);

        $this->assertTrue($titles->contains('Definitely Findable Widget'));
    }

    public function test_global_search_finds_a_stock_request_by_id(): void
    {
        $admin = $this->admin();
        $stockRequest = StockRequest::factory()->create(['requester_id' => $admin->id]);

        $results = Livewire::actingAs($admin)
            ->test(GlobalSearch::class)
            ->set('search', (string) $stockRequest->id)
            ->instance()
            ->getResults();

        $titles = collect($results?->getCategories())
            ->flatMap(fn ($category) => $category)
            ->map(fn ($result) => $result->title);

        $this->assertTrue($titles->contains("REQ-{$stockRequest->id}"));
    }

    public function test_stock_request_list_can_be_filtered_by_date_range(): void
    {
        $admin = $this->admin();

        $inRange = StockRequest::factory()->create(['requester_id' => $admin->id]);
        $inRange->forceFill(['created_at' => '2026-07-16 10:00:00'])->save();

        $outOfRange = StockRequest::factory()->create(['requester_id' => $admin->id]);
        $outOfRange->forceFill(['created_at' => '2026-06-01 10:00:00'])->save();

        Livewire::actingAs($admin)
            ->test(ListStockRequests::class)
            ->filterTable('created_at', ['from' => '2026-07-15', 'until' => '2026-07-17'])
            ->assertCanSeeTableRecords([$inRange])
            ->assertCanNotSeeTableRecords([$outOfRange]);
    }
}
