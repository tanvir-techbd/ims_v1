<?php

namespace Tests\Feature;

use App\Filament\Pages\Reports;
use App\Filament\Pages\UserActivityReport as UserActivityReportPage;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockIssuance;
use App\Models\StockRequest;
use App\Models\StockRequestItem;
use App\Models\Unit;
use App\Models\User;
use App\Support\Reports\ProductsIssuedReport;
use App\Support\Reports\ReportPeriod;
use App\Support\Reports\UserActivityReport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private function approver(): User
    {
        $role = Role::firstOrCreate(['name' => 'Approver', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'view_reports', 'guard_name' => 'web']));
        $approver = User::factory()->create();
        $approver->assignRole('Approver');

        return $approver;
    }

    private function issuanceOn(string $datetime, int $qty = 5): StockIssuance
    {
        $product = Product::factory()->create([
            'category_id' => Category::factory(),
            'unit_id' => Unit::factory(),
        ]);
        $stockRequest = StockRequest::factory()->create(['requester_id' => User::factory()->create()->id]);
        $item = StockRequestItem::factory()->create([
            'stock_request_id' => $stockRequest->id,
            'product_id' => $product->id,
        ]);

        $issuance = StockIssuance::factory()->create([
            'stock_request_item_id' => $item->id,
            'issued_qty' => $qty,
        ]);
        $issuance->forceFill(['created_at' => $datetime])->save();

        return $issuance;
    }

    public function test_report_period_resolves_daily_monthly_yearly_ranges_correctly(): void
    {
        [$from, $to] = ReportPeriod::resolve('daily', '2026-07-16');
        $this->assertTrue($from->isSameDay(CarbonImmutable::parse('2026-07-16 00:00:00')));
        $this->assertTrue($to->isSameDay(CarbonImmutable::parse('2026-07-16 23:59:59')));

        [$from, $to] = ReportPeriod::resolve('monthly', '2026-07-16');
        $this->assertSame('2026-07-01', $from->toDateString());
        $this->assertSame('2026-07-31', $to->toDateString());

        [$from, $to] = ReportPeriod::resolve('yearly', '2026-07-16');
        $this->assertSame('2026-01-01', $from->toDateString());
        $this->assertSame('2026-12-31', $to->toDateString());
    }

    public function test_products_issued_report_only_counts_issuances_within_range(): void
    {
        $inRange = $this->issuanceOn('2026-07-16 10:00:00', qty: 7);
        $this->issuanceOn('2026-07-15 10:00:00', qty: 100); // outside range, must not count

        [$from, $to] = ReportPeriod::resolve('daily', '2026-07-16');
        $rows = ProductsIssuedReport::query($from, $to)->get();

        $this->assertCount(1, $rows);
        $this->assertSame(7, (int) $rows->first()->total_issued);
        $this->assertSame($inRange->stockRequestItem->product->name, $rows->first()->product_name);
    }

    public function test_products_issued_report_sums_multiple_issuances_of_the_same_product(): void
    {
        $product = Product::factory()->create(['category_id' => Category::factory(), 'unit_id' => Unit::factory()]);
        $stockRequest = StockRequest::factory()->create(['requester_id' => User::factory()->create()->id]);

        foreach ([3, 4] as $qty) {
            $item = StockRequestItem::factory()->create([
                'stock_request_id' => $stockRequest->id,
                'product_id' => $product->id,
            ]);
            $issuance = StockIssuance::factory()->create(['stock_request_item_id' => $item->id, 'issued_qty' => $qty]);
            $issuance->forceFill(['created_at' => '2026-07-16 09:00:00'])->save();
        }

        [$from, $to] = ReportPeriod::resolve('daily', '2026-07-16');
        $rows = ProductsIssuedReport::query($from, $to)->get();

        $this->assertCount(1, $rows);
        $this->assertSame(7, (int) $rows->first()->total_issued);
        $this->assertSame(1, (int) $rows->first()->request_count);
    }

    public function test_user_activity_report_counts_requests_approvals_and_issuances_and_omits_inactive_users(): void
    {
        $demander = User::factory()->create();
        $approver = User::factory()->create();
        $inactiveUser = User::factory()->create();

        $product = Product::factory()->create([
            'category_id' => Category::factory(),
            'unit_id' => Unit::factory(),
            'current_stock' => 100,
        ]);
        $stockRequest = StockRequest::factory()->create(['requester_id' => $demander->id]);
        $stockRequest->forceFill(['created_at' => '2026-07-16 08:00:00'])->save();

        $item = StockRequestItem::factory()->create([
            'stock_request_id' => $stockRequest->id,
            'product_id' => $product->id,
            'requested_qty' => 10,
        ]);
        $item->approve(10, $approver);
        $item->refresh();
        $item->approvals()->update(['created_at' => '2026-07-16 09:00:00']);

        [$from, $to] = ReportPeriod::resolve('daily', '2026-07-16');
        $rows = UserActivityReport::query($from, $to)->get()->keyBy('id');

        $this->assertTrue($rows->has($demander->id));
        $this->assertSame(1, $rows[$demander->id]->stock_requests_count);

        $this->assertTrue($rows->has($approver->id));
        $this->assertSame(1, $rows[$approver->id]->approvals_made_count);

        $this->assertFalse($rows->has($inactiveUser->id));
    }

    public function test_reports_page_is_accessible_to_approver_and_forbidden_to_demander(): void
    {
        $approver = $this->approver();
        $this->actingAs($approver)->get(Reports::getUrl())->assertOk();

        Role::firstOrCreate(['name' => 'Demander', 'guard_name' => 'web']);
        $demander = User::factory()->create();
        $demander->assignRole('Demander');
        $this->actingAs($demander)->get(Reports::getUrl())->assertForbidden();
    }

    public function test_products_issued_report_page_renders_and_reacts_to_the_period_filter(): void
    {
        $approver = $this->approver();
        $this->issuanceOn('2026-07-16 10:00:00', qty: 9);

        Livewire::actingAs($approver)
            ->test(Reports::class)
            ->filterTable('period', ['period' => 'daily', 'reference_date' => '2026-07-16'])
            ->assertSuccessful()
            ->assertSee('9');
    }

    public function test_products_issued_export_streams_a_csv_response(): void
    {
        $approver = $this->approver();
        $this->issuanceOn('2026-07-16 10:00:00', qty: 9);

        Livewire::actingAs($approver)
            ->test(Reports::class)
            ->filterTable('period', ['period' => 'daily', 'reference_date' => '2026-07-16'])
            ->callTableAction('export')
            ->assertFileDownloaded('products-issued-2026-07-16-to-2026-07-16.csv');
    }

    public function test_user_activity_report_page_renders(): void
    {
        $approver = $this->approver();

        Livewire::actingAs($approver)
            ->test(UserActivityReportPage::class)
            ->assertSuccessful();
    }
}
