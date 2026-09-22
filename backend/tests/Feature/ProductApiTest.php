<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    private function authenticate(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_admin_can_create_and_view_a_product(): void
    {
        $this->authenticate();

        $vendor = Vendor::create([
            'name' => 'PT Maju Jaya',
            'email' => 'maju@example.com',
        ]);

        $createResponse = $this->postJson('/api/products', [
            'name' => 'Laptop Pro 14',
            'category' => 'Elektronik',
            'vendor_id' => $vendor->id,
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('name', 'Laptop Pro 14')
            ->assertJsonPath('category', 'Elektronik')
            ->assertJsonPath('vendor.id', $vendor->id)
            ->assertJsonPath('units_count', 0);

        $productId = $createResponse->json('id');

        $this->getJson("/api/products/{$productId}")
            ->assertOk()
            ->assertJsonPath('units_count', 0)
            ->assertJsonPath('units', []);
    }

    public function test_product_list_supports_search(): void
    {
        $this->authenticate();

        $vendor = Vendor::create(['name' => 'PT Maju Jaya', 'email' => 'maju@example.com']);

        Product::create([
            'vendor_id' => $vendor->id,
            'name' => 'Laptop Pro 14',
            'category' => 'Elektronik',
        ]);

        Product::create([
            'vendor_id' => $vendor->id,
            'name' => 'Smartphone X',
            'category' => 'Gadget',
        ]);

        $this->getJson('/api/products?search=laptop')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Laptop Pro 14');
    }

    public function test_product_requires_name_and_existing_vendor(): void
    {
        $this->authenticate();

        $this->postJson('/api/products', [
            'name' => 'Laptop Pro 14',
            'vendor_id' => 999,
        ])->assertUnprocessable()->assertJsonValidationErrors(['vendor_id']);

        $this->postJson('/api/products', [
            'vendor_id' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors(['name']);
    }

    public function test_product_with_units_cannot_be_deleted(): void
    {
        $this->authenticate();

        $vendor = Vendor::create(['name' => 'PT Maju Jaya', 'email' => 'maju@example.com']);
        $product = Product::create([
            'vendor_id' => $vendor->id,
            'name' => 'Laptop Pro 14',
            'category' => 'Elektronik',
        ]);

        ProductUnit::create([
            'product_id' => $product->id,
            'customer_id' => null,
            'serial_number' => 'SERIAL-001',
        ]);

        $this->deleteJson("/api/products/{$product->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product']);

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_product_endpoints_require_authentication(): void
    {
        $this->getJson('/api/products')->assertUnauthorized();
    }
}
