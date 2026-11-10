<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warranty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaidServiceRequestApiTest extends TestCase
{
    use RefreshDatabase;

    private function createUnitWithWarranty(string $endDate): ProductUnit
    {
        $vendor = Vendor::create(['name' => 'Mitra Servis']);
        $product = Product::create([
            'vendor_id' => $vendor->id,
            'name' => 'Laptop Pro',
            'category' => 'Elektronik',
        ]);
        $customer = Customer::create([
            'name' => 'Budi',
            'phone' => '08123456789',
            'address' => 'Bandung',
        ]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'serial_number' => 'SN-'.uniqid(),
        ]);
        Warranty::create([
            'warranty_code' => 'GAR-'.uniqid(),
            'product_unit_id' => $unit->id,
            'start_date' => now()->subYear()->toDateString(),
            'end_date' => $endDate,
        ]);

        return $unit;
    }

    public function test_admin_can_create_and_update_a_paid_service_request_for_an_expired_unit(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $unit = $this->createUnitWithWarranty(now()->subDay()->toDateString());
        $vendor = Vendor::create(['name' => 'Service Center']);

        $createResponse = $this->postJson('/api/paid-service-requests', [
            'product_unit_id' => $unit->id,
            'vendor_id' => $vendor->id,
            'issue_description' => 'Layar berkedip.',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('product_unit.id', $unit->id)
            ->assertJsonPath('vendor.id', $vendor->id)
            ->assertJsonPath('status', 'requested');

        $serviceRequestId = $createResponse->json('id');

        $this->patchJson("/api/paid-service-requests/{$serviceRequestId}", [
            'quoted_amount' => 125000,
            'status' => 'quoted',
        ])->assertOk()
            ->assertJsonPath('quoted_amount', '125000.00')
            ->assertJsonPath('status', 'quoted');
    }

    public function test_paid_service_requests_allow_expired_or_unregistered_warranty_units_but_not_active_warranties(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $expiredUnit = $this->createUnitWithWarranty(now()->subDay()->toDateString());
        $activeUnit = $this->createUnitWithWarranty(now()->addMonth()->toDateString());
        $unwarrantedUnit = $this->createUnitWithoutWarranty();

        $this->getJson('/api/paid-service-requests/eligible-units')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['id' => $expiredUnit->id])
            ->assertJsonFragment(['id' => $unwarrantedUnit->id]);

        $this->postJson('/api/paid-service-requests', [
            'product_unit_id' => $activeUnit->id,
            'issue_description' => 'Pemeriksaan umum.',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['product_unit_id']);

        $this->postJson('/api/paid-service-requests', [
            'product_unit_id' => $unwarrantedUnit->id,
            'issue_description' => 'Perangkat belum terdaftar garansinya.',
        ])->assertCreated()
            ->assertJsonPath('product_unit.id', $unwarrantedUnit->id);
    }

    public function test_admin_can_create_a_standalone_service_request_without_registered_warranty_or_unit(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/paid-service-requests', [
            'customer_name' => 'Siti',
            'customer_phone' => '081234567890',
            'product_name' => 'MikroTik CCR',
            'serial_number' => 'CCR-001',
            'issue_description' => 'Port ethernet tidak terdeteksi.',
        ])->assertCreated()
            ->assertJsonPath('product_unit', null)
            ->assertJsonPath('customer_name', 'Siti')
            ->assertJsonPath('customer_phone', '081234567890')
            ->assertJsonPath('product_name', 'MikroTik CCR')
            ->assertJsonPath('serial_number', 'CCR-001')
            ->assertJsonPath('status', 'requested');

        $this->assertDatabaseCount('product_units', 0);
        $this->assertDatabaseCount('warranties', 0);
    }

    public function test_standalone_service_requests_require_customer_and_product_details(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/paid-service-requests', [
            'issue_description' => 'Perlu diperiksa.',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_name', 'customer_phone', 'product_name']);
    }

    public function test_paid_service_report_filters_by_period_status_and_service_details(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $vendor = Vendor::create(['name' => 'Service Center Report']);
        $service = $this->postJson('/api/paid-service-requests', [
            'customer_name' => 'Siti Laporan',
            'customer_phone' => '081234567890',
            'product_name' => 'Router Laporan',
            'serial_number' => 'SN-REPORT',
            'vendor_id' => $vendor->id,
            'issue_description' => 'Port ethernet tidak terdeteksi.',
        ])->assertCreated()
            ->json();

        $this->patchJson("/api/paid-service-requests/{$service['id']}", [
            'status' => 'quoted',
            'quoted_amount' => 250000,
        ])->assertOk();

        $today = now()->toDateString();
        $this->getJson("/api/reports/paid-services?from={$today}&to={$today}&status=quoted&search=SN-REPORT")
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.customer_name', 'Siti Laporan')
            ->assertJsonPath('data.0.product_name', 'Router Laporan')
            ->assertJsonPath('data.0.vendor.name', 'Service Center Report')
            ->assertJsonPath('data.0.status', 'quoted')
            ->assertJsonPath('data.0.quoted_amount', '250000.00');
    }

    private function createUnitWithoutWarranty(): ProductUnit
    {
        $vendor = Vendor::create(['name' => 'Mitra Tanpa Garansi']);
        $product = Product::create([
            'vendor_id' => $vendor->id,
            'name' => 'Router tanpa garansi',
        ]);
        $customer = Customer::create([
            'name' => 'Pelanggan Tanpa Garansi',
            'phone' => '081200000000',
        ]);

        return ProductUnit::create([
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'serial_number' => 'SN-'.uniqid(),
        ]);
    }
}
