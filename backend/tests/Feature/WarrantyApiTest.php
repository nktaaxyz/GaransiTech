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

class WarrantyApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-09-23');
    }

    private function authenticate(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    private function createProductUnit(): ProductUnit
    {
        $vendor = Vendor::create(['name' => 'PT Maju Jaya', 'email' => 'maju@example.com']);
        $product = Product::create([
            'vendor_id' => $vendor->id,
            'name' => 'Laptop Pro 14',
            'category' => 'Elektronik',
        ]);
        $customer = Customer::create([
            'name' => 'Budi',
            'phone' => '08123456789',
        ]);

        return ProductUnit::create([
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'serial_number' => 'SN-WARRANTY-001',
        ]);
    }

    public function test_admin_can_create_and_view_a_warranty(): void
    {
        $this->authenticate();
        $unit = $this->createProductUnit();

        $response = $this->postJson('/api/warranties', [
            'product_unit_id' => $unit->id,
            'start_date' => '2026-09-01',
            'end_date' => '2027-09-01',
            'notes' => 'Garansi toko',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('product_unit.id', $unit->id)
            ->assertJsonPath('status', 'active');

        $this->assertMatchesRegularExpression(
            '/^GAR-20260923-[A-Z0-9]{6}$/',
            $response->json('warranty_code'),
        );

        $this->getJson('/api/warranties/' . $response->json('id'))
            ->assertOk()
            ->assertJsonPath('claims', []);
    }

    public function test_warranty_validates_dates_and_generates_unique_code(): void
    {
        $this->authenticate();
        $unit = $this->createProductUnit();

        $this->postJson('/api/warranties', [
            'product_unit_id' => $unit->id,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-01',
        ])->assertUnprocessable()->assertJsonValidationErrors(['end_date']);

        $first = $this->postJson('/api/warranties', [
            'product_unit_id' => $unit->id,
            'start_date' => '2026-09-01',
            'end_date' => '2027-09-01',
        ])->assertCreated();

        $second = $this->postJson('/api/warranties', [
            'product_unit_id' => $unit->id,
            'start_date' => '2026-09-01',
            'end_date' => '2027-09-01',
        ])->assertCreated();

        $this->assertNotSame(
            $first->json('warranty_code'),
            $second->json('warranty_code'),
        );
    }

    public function test_warranty_status_is_active_expiring_or_expired(): void
    {
        $this->authenticate();
        $unit = $this->createProductUnit();

        Warranty::create([
            'warranty_code' => 'GAR-ACTIVE',
            'product_unit_id' => $unit->id,
            'start_date' => '2026-09-01',
            'end_date' => '2027-09-01',
        ]);
        Warranty::create([
            'warranty_code' => 'GAR-EXPIRING',
            'product_unit_id' => $unit->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-10-01',
        ]);
        Warranty::create([
            'warranty_code' => 'GAR-EXPIRED',
            'product_unit_id' => $unit->id,
            'start_date' => '2025-09-01',
            'end_date' => '2026-09-01',
        ]);

        $this->getJson('/api/warranties')
            ->assertOk()
            ->assertJsonFragment(['status' => 'expiring'])
            ->assertJsonFragment(['status' => 'active']);

        $this->getJson('/api/warranties?status=expired')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.warranty_code', 'GAR-EXPIRED');

        $this->getJson('/api/warranties?status=invalid')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_warranty_with_claim_cannot_be_deleted(): void
    {
        $this->authenticate();
        $unit = $this->createProductUnit();
        $warranty = Warranty::create([
            'warranty_code' => 'GAR-CLAIMED',
            'product_unit_id' => $unit->id,
            'start_date' => '2026-09-01',
            'end_date' => '2027-09-01',
        ]);

        $warranty->claims()->create([
            'claim_code' => 'CLM-0001',
            'customer_id' => $unit->customer_id,
            'product_id' => $unit->product_id,
            'vendor_id' => $unit->product->vendor_id,
            'serial_number' => $unit->serial_number,
            'claim_date' => '2026-09-23',
            'status' => 'received',
        ]);

        $this->deleteJson('/api/warranties/' . $warranty->id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['warranty']);
    }

    public function test_warranty_endpoints_require_authentication(): void
    {
        $this->getJson('/api/warranties')->assertUnauthorized();
    }
}
