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

    public function test_paid_service_requests_require_an_expired_warranty(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $expiredUnit = $this->createUnitWithWarranty(now()->subDay()->toDateString());
        $activeUnit = $this->createUnitWithWarranty(now()->addMonth()->toDateString());

        $this->getJson('/api/paid-service-requests/eligible-units')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $expiredUnit->id);

        $this->postJson('/api/paid-service-requests', [
            'product_unit_id' => $activeUnit->id,
            'issue_description' => 'Pemeriksaan umum.',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['product_unit_id']);
    }
}
