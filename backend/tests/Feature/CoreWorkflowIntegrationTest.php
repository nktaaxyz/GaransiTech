<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoreWorkflowIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_complete_the_core_warranty_workflow(): void
    {
        CarbonImmutable::setTestNow('2026-09-23');
        Sanctum::actingAs(User::factory()->create());

        $customer = $this->postJson('/api/customers', [
            'name' => 'Budi',
            'phone' => '08123456789',
        ])->assertCreated()->json();

        $vendor = $this->postJson('/api/vendors', [
            'name' => 'PT Maju Jaya',
        ])->assertCreated()->json();

        $product = $this->postJson('/api/products', [
            'name' => 'Laptop Pro 14',
            'category' => 'Elektronik',
            'vendor_id' => $vendor['id'],
        ])->assertCreated()->json();

        $unit = $this->postJson('/api/product-units', [
            'product_id' => $product['id'],
            'customer_id' => $customer['id'],
            'serial_number' => 'SN-INTEGRATION-001',
            'purchase_date' => '2026-09-01',
        ])->assertCreated()->json();

        $warranty = $this->postJson('/api/warranties', [
            'product_unit_id' => $unit['id'],
            'start_date' => '2026-09-01',
            'end_date' => '2027-09-01',
        ])->assertCreated()->json();

        $claim = $this->postJson('/api/claims', [
            'warranty_id' => $warranty['id'],
            'claim_date' => '2026-09-23',
            'damage_description' => 'Laptop tidak menyala.',
        ])->assertCreated()->json();

        $this->patchJson("/api/claims/{$claim['id']}/status", [
            'status' => 'forwarded_to_vendor',
            'note' => 'Diteruskan ke vendor.',
        ])->assertOk();

        $this->patchJson("/api/claims/{$claim['id']}/status", [
            'status' => 'processing_by_vendor',
            'note' => 'Sedang diproses vendor.',
        ])->assertOk();

        $this->patchJson("/api/claims/{$claim['id']}/status", [
            'status' => 'completed',
            'note' => 'Perbaikan selesai.',
            'resolution_date' => '2026-09-23',
            'resolution_note' => 'Komponen diganti.',
        ])->assertOk()->assertJsonPath('status', 'completed');

        $this->getJson('/api/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('warranties.total', 1)
            ->assertJsonPath('claims.completed', 1);

        $this->getJson('/api/reports/claims?status=completed')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
