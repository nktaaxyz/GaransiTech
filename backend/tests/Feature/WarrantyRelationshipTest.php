<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Vendor;
use App\Models\Warranty;
use App\Models\Warranty_claims;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarrantyRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_claim_can_be_resolved_through_its_warranty_and_product_unit(): void
    {
        $customer = Customer::create(['name' => 'Budi']);
        $vendor = Vendor::create(['name' => 'Vendor A']);
        $product = Product::create([
            'name' => 'Router ABC',
            'category' => 'Networking',
            'vendor_id' => $vendor->id,
        ]);
        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'serial_number' => 'ABC-001',
            'purchase_date' => '2026-09-22',
        ]);
        $warranty = Warranty::create([
            'warranty_code' => 'GAR-0001',
            'product_unit_id' => $unit->id,
            'start_date' => '2026-09-22',
            'end_date' => '2027-09-22',
        ]);
        $claim = Warranty_claims::create([
            'claim_code' => 'CLM-0001',
            'warranty_id' => $warranty->id,
            'customer_id' => $customer->id,
            'product_id' => $product->id,
            'vendor_id' => $vendor->id,
            'serial_number' => $unit->serial_number,
            'claim_date' => '2026-09-23',
            'status' => 'received',
        ]);

        $claim->load('warranty.productUnit.product', 'warranty.productUnit.customer');

        $this->assertSame($warranty->id, $claim->warranty->id);
        $this->assertSame($unit->id, $claim->warranty->productUnit->id);
        $this->assertSame($product->id, $claim->warranty->productUnit->product->id);
        $this->assertSame($customer->id, $claim->warranty->productUnit->customer->id);
    }
}
