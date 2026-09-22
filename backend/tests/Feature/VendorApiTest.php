<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VendorApiTest extends TestCase
{
    use RefreshDatabase;

    private function authenticate(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_admin_can_create_and_view_a_vendor(): void
    {
        $this->authenticate();

        $createResponse = $this->postJson('/api/vendors', [
            'name' => 'PT Maju Jaya',
            'contact_person' => 'Andi',
            'phone' => '08123456789',
            'email' => 'andi@majujaya.com',
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('name', 'PT Maju Jaya')
            ->assertJsonPath('contact_person', 'Andi')
            ->assertJsonPath('products_count', 0);

        $vendorId = $createResponse->json('id');

        $this->getJson("/api/vendors/{$vendorId}")
            ->assertOk()
            ->assertJsonPath('products_count', 0)
            ->assertJsonPath('products', []);
    }

    public function test_vendor_list_supports_search(): void
    {
        $this->authenticate();

        Vendor::create([
            'name' => 'PT Maju Jaya',
            'contact_person' => 'Andi',
            'email' => 'andi@majujaya.com',
        ]);

        Vendor::create([
            'name' => 'PT Sejahtera',
            'contact_person' => 'Budi',
            'email' => 'budi@sejahtera.com',
        ]);

        $this->getJson('/api/vendors?search=andi')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'PT Maju Jaya');
    }

    public function test_vendor_requires_name_and_valid_email(): void
    {
        $this->authenticate();

        $this->postJson('/api/vendors', [
            'email' => 'not-an-email',
        ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email']);
    }

    public function test_vendor_can_be_updated(): void
    {
        $this->authenticate();

        $vendor = Vendor::create([
            'name' => 'PT Lama',
            'email' => 'lama@example.com',
        ]);

        $this->putJson("/api/vendors/{$vendor->id}", [
            'name' => 'PT Baru',
            'contact_person' => 'Sari',
            'email' => 'baru@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('name', 'PT Baru')
            ->assertJsonPath('contact_person', 'Sari');
    }

    public function test_vendor_with_products_cannot_be_deleted(): void
    {
        $this->authenticate();

        $vendor = Vendor::create([
            'name' => 'PT Maju Jaya',
            'email' => 'maju@jaya.com',
        ]);

        Product::create([
            'vendor_id' => $vendor->id,
            'name' => 'Laptop 14',
            'category' => 'Elektronik',
        ]);

        $this->deleteJson("/api/vendors/{$vendor->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['vendor']);

        $this->assertDatabaseHas('vendors', ['id' => $vendor->id]);
    }

    public function test_vendor_endpoints_require_authentication(): void
    {
        $this->getJson('/api/vendors')->assertUnauthorized();
    }
}
