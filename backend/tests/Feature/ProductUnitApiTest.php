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

class ProductUnitApiTest extends TestCase
{
    use RefreshDatabase;

    private function authenticate(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_admin_can_create_and_view_a_product_unit(): void
    {
        $this->authenticate();

        $vendor = Vendor::create(['name' => 'PT Maju Jaya', 'email' => 'maju@example.com']);
        $product = Product::create([
            'vendor_id' => $vendor->id,
            'name' => 'Laptop Pro 14',
            'category' => 'Elektronik',
        ]);
        $customer = Customer::create([
            'name' => 'Budi',
            'phone' => '08123456789',
            'address' => 'Bandung',
        ]);

        $createResponse = $this->postJson('/api/product-units', [
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'serial_number' => 'SN-001',
            'purchase_date' => '2026-09-01',
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('serial_number', 'SN-001')
            ->assertJsonPath('product.id', $product->id)
            ->assertJsonPath('customer.id', $customer->id);

        $unitId = $createResponse->json('id');

        $this->getJson("/api/product-units/{$unitId}")
            ->assertOk()
            ->assertJsonPath('serial_number', 'SN-001');
    }

    public function test_product_unit_list_supports_search_by_serial_number_and_customer(): void
    {
        $this->authenticate();

        $vendor = Vendor::create(['name' => 'PT Maju Jaya', 'email' => 'maju@example.com']);
        $product = Product::create([
            'vendor_id' => $vendor->id,
            'name' => 'Laptop Pro 14',
            'category' => 'Elektronik',
        ]);
        $customer = Customer::create([
            'name' => 'Budi',
            'phone' => '08123456789',
            'address' => 'Bandung',
        ]);

        ProductUnit::create([
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'serial_number' => 'SN-ALPHA',
            'purchase_date' => '2026-09-01',
        ]);

        ProductUnit::create([
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'serial_number' => 'SN-BETA',
            'purchase_date' => '2026-09-02',
        ]);

        $this->getJson('/api/product-units?search=budi')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/product-units?search=alpha')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.serial_number', 'SN-ALPHA');
    }

    public function test_product_unit_requires_valid_relations_and_unique_serial_number(): void
    {
        $this->authenticate();

        $this->postJson('/api/product-units', [
            'product_id' => 999,
            'customer_id' => 999,
            'serial_number' => 'SN-001',
        ])->assertUnprocessable()->assertJsonValidationErrors(['product_id', 'customer_id']);

        $vendor = Vendor::create(['name' => 'PT Maju Jaya', 'email' => 'maju@example.com']);
        $product = Product::create([
            'vendor_id' => $vendor->id,
            'name' => 'Laptop Pro 14',
            'category' => 'Elektronik',
        ]);
        $customer = Customer::create([
            'name' => 'Budi',
            'phone' => '08123456789',
            'address' => 'Bandung',
        ]);

        ProductUnit::create([
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'serial_number' => 'SN-DUPLICATE',
        ]);

        $this->postJson('/api/product-units', [
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'serial_number' => 'SN-DUPLICATE',
        ])->assertUnprocessable()->assertJsonValidationErrors(['serial_number']);
    }

    public function test_product_unit_with_warranty_cannot_be_deleted(): void
    {
        $this->authenticate();

        $vendor = Vendor::create(['name' => 'PT Maju Jaya', 'email' => 'maju@example.com']);
        $product = Product::create([
            'vendor_id' => $vendor->id,
            'name' => 'Laptop Pro 14',
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
            'serial_number' => 'SN-WARRANTY',
            'purchase_date' => '2026-09-01',
        ]);

        Warranty::create([
            'product_unit_id' => $unit->id,
            'warranty_code' => 'W-1001',
            'start_date' => '2026-09-01',
            'end_date' => '2027-09-01',
        ]);

        $this->deleteJson("/api/product-units/{$unit->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_unit']);

        $this->assertDatabaseHas('product_units', ['id' => $unit->id]);
    }

    public function test_product_unit_endpoints_require_authentication(): void
    {
        $this->getJson('/api/product-units')->assertUnauthorized();
    }
}
