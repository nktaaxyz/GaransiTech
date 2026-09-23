<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warranty;
use App\Models\Warranty_claims;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardReportApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-09-23');
        Sanctum::actingAs(User::factory()->create());
    }

    private function createWarranty(string $code, string $endDate): Warranty
    {
        $vendor = Vendor::create(['name' => 'Vendor ' . $code]);
        $product = Product::create(['name' => 'Produk ' . $code, 'vendor_id' => $vendor->id]);
        $customer = Customer::create(['name' => 'Customer ' . $code, 'phone' => $code]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'serial_number' => 'SN-' . $code,
        ]);

        return Warranty::create([
            'warranty_code' => $code,
            'product_unit_id' => $unit->id,
            'start_date' => '2026-09-01',
            'end_date' => $endDate,
        ]);
    }

    public function test_dashboard_summary_aggregates_warranty_and_claim_statuses(): void
    {
        $active = $this->createWarranty('GAR-ACTIVE', '2027-09-01');
        $this->createWarranty('GAR-EXPIRING', '2026-10-01');
        $this->createWarranty('GAR-EXPIRED', '2026-09-01');

        Warranty_claims::create([
            'claim_code' => 'CLM-SUMMARY',
            'warranty_id' => $active->id,
            'customer_id' => $active->productUnit->customer_id,
            'product_id' => $active->productUnit->product_id,
            'vendor_id' => $active->productUnit->product->vendor_id,
            'claim_date' => '2026-09-23',
            'status' => 'received',
        ]);

        $this->getJson('/api/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('warranties.active', 1)
            ->assertJsonPath('warranties.expiring', 1)
            ->assertJsonPath('warranties.expired', 1)
            ->assertJsonPath('claims.received', 1)
            ->assertJsonPath('claims.total', 1);
    }

    public function test_reports_support_period_status_and_search_filters(): void
    {
        $warranty = $this->createWarranty('GAR-REPORT', '2027-09-01');
        Warranty_claims::create([
            'claim_code' => 'CLM-REPORT',
            'warranty_id' => $warranty->id,
            'customer_id' => $warranty->productUnit->customer_id,
            'product_id' => $warranty->productUnit->product_id,
            'vendor_id' => $warranty->productUnit->product->vendor_id,
            'serial_number' => $warranty->productUnit->serial_number,
            'claim_date' => '2026-09-20',
            'status' => 'completed',
        ]);

        $this->getJson('/api/reports/warranties?status=active&search=GAR-REPORT')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/reports/claims?from=2026-09-19&to=2026-09-21&status=completed&search=CLM-REPORT')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_report_date_range_is_validated(): void
    {
        $this->getJson('/api/reports/claims?from=2026-09-22&to=2026-09-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to']);
    }
}
