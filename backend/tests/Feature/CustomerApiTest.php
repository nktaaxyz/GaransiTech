<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ProductUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use RefreshDatabase;

    private function authenticate(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_admin_can_create_and_view_a_customer(): void
    {
        $this->authenticate();

        $createResponse = $this->postJson('/api/customers', [
            'name' => 'Budi',
            'phone' => '08123456789',
            'address' => 'Bandung',
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('name', 'Budi')
            ->assertJsonPath('phone', '08123456789')
            ->assertJsonPath('product_units_count', 0);

        $customerId = $createResponse->json('id');

        $this->getJson("/api/customers/{$customerId}")
            ->assertOk()
            ->assertJsonPath('product_units_count', 0)
            ->assertJsonPath('product_units', []);
    }

    public function test_customer_list_supports_search(): void
    {
        $this->authenticate();

        Customer::create(['name' => 'Budi', 'phone' => '08123']);
        Customer::create(['name' => 'Sari', 'phone' => '08999']);

        $this->getJson('/api/customers?search=budi')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Budi');
    }

    public function test_customer_requires_name_and_phone(): void
    {
        $this->authenticate();

        $this->postJson('/api/customers', ['address' => 'Bandung'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'phone']);
    }

    public function test_customer_with_product_units_cannot_be_deleted(): void
    {
        $this->authenticate();

        $customer = Customer::create([
            'name' => 'Budi',
            'phone' => '08123',
        ]);

        ProductUnit::create([
            'customer_id' => $customer->id,
            'serial_number' => 'SERIAL-001',
        ]);

        $this->deleteJson("/api/customers/{$customer->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer']);

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    public function test_customer_endpoints_require_authentication(): void
    {
        $this->getJson('/api/customers')->assertUnauthorized();
    }
}
