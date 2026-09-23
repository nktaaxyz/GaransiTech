<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warranty;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WarrantyClaimApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-09-23');
        Sanctum::actingAs(User::factory()->create());
    }

    private function createWarranty(string $endDate = '2027-09-23'): Warranty
    {
        $vendor = Vendor::create(['name' => 'PT Maju Jaya']);
        $product = Product::create([
            'name' => 'Laptop Pro 14',
            'vendor_id' => $vendor->id,
        ]);
        $customer = Customer::create([
            'name' => 'Budi',
            'phone' => '08123456789',
        ]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'serial_number' => 'SN-CLAIM-001',
        ]);

        return Warranty::create([
            'warranty_code' => 'GAR-CLAIM-001',
            'product_unit_id' => $unit->id,
            'start_date' => '2026-09-01',
            'end_date' => $endDate,
        ]);
    }

    public function test_claim_is_created_from_an_active_warranty(): void
    {
        $warranty = $this->createWarranty();

        $response = $this->postJson('/api/claims', [
            'warranty_id' => $warranty->id,
            'claim_date' => '2026-09-23',
            'damage_description' => 'Layar tidak menyala.',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('warranty_id', $warranty->id)
            ->assertJsonPath('customer_id', $warranty->productUnit->customer_id)
            ->assertJsonPath('product_id', $warranty->productUnit->product_id)
            ->assertJsonPath('serial_number', 'SN-CLAIM-001')
            ->assertJsonPath('status', 'received')
            ->assertJsonPath('damage_description', 'Layar tidak menyala.')
            ->assertJsonCount(1, 'status_logs');

        $this->assertMatchesRegularExpression('/^CLM-20260923-[A-Z0-9]{6}$/', $response->json('claim_code'));
    }

    public function test_claim_cannot_be_created_outside_warranty_period(): void
    {
        $warranty = $this->createWarranty('2026-09-22');

        $this->postJson('/api/claims', [
            'warranty_id' => $warranty->id,
            'claim_date' => '2026-09-23',
            'damage_description' => 'Tidak menyala.',
        ])->assertUnprocessable()->assertJsonValidationErrors(['warranty_id']);
    }

    public function test_claim_status_transition_is_recorded_and_final_status_is_locked(): void
    {
        $warranty = $this->createWarranty();
        $claim = $this->postJson('/api/claims', [
            'warranty_id' => $warranty->id,
            'claim_date' => '2026-09-23',
            'damage_description' => 'Tidak menyala.',
        ])->json();

        $this->patchJson('/api/claims/' . $claim['id'] . '/status', [
            'status' => 'forwarded_to_vendor',
            'note' => 'Diteruskan ke vendor.',
        ])->assertOk()->assertJsonPath('status', 'forwarded_to_vendor');

        $this->patchJson('/api/claims/' . $claim['id'] . '/status', [
            'status' => 'completed',
            'note' => 'Langsung selesai.',
        ])->assertUnprocessable()->assertJsonValidationErrors(['status']);

        $this->patchJson('/api/claims/' . $claim['id'] . '/status', [
            'status' => 'processing_by_vendor',
            'note' => 'Vendor mulai memproses.',
        ])->assertOk();
    }

    public function test_claim_list_supports_status_and_search_filters(): void
    {
        $warranty = $this->createWarranty();
        $this->postJson('/api/claims', [
            'warranty_id' => $warranty->id,
            'claim_date' => '2026-09-23',
            'damage_description' => 'Tidak menyala.',
        ]);

        $this->getJson('/api/claims?status=received&search=SN-CLAIM-001')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
