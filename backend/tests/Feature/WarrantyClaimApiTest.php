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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_claim_transcript_summary_reports_ai_service_as_unavailable_until_configured(): void
    {
        $this->postJson('/api/claims/summarize', [
            'transcript' => 'Pelanggan mengatakan layar laptop berkedip dan mati.',
        ])->assertStatus(503)
            ->assertJsonPath('message', 'Layanan AI belum dikonfigurasi.');
    }

    public function test_claim_transcript_summary_requires_a_transcript(): void
    {
        $this->postJson('/api/claims/summarize', [
            'transcript' => '',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['transcript']);
    }

    public function test_claim_is_created_from_an_active_warranty(): void
    {
        $warranty = $this->createWarranty();

        $response = $this->postJson('/api/claims', [
            'warranty_id' => $warranty->id,
            'claim_date' => '2026-09-23',
            'damage_description' => 'Layar tidak menyala.',
            'client_statement' => 'Laptop saya tiba-tiba mati dan tidak bisa dinyalakan.',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('warranty_id', $warranty->id)
            ->assertJsonPath('customer_id', $warranty->productUnit->customer_id)
            ->assertJsonPath('product_id', $warranty->productUnit->product_id)
            ->assertJsonPath('serial_number', 'SN-CLAIM-001')
            ->assertJsonPath('status', 'received')
            ->assertJsonPath('damage_description', 'Layar tidak menyala.')
            ->assertJsonPath('client_statement', 'Laptop saya tiba-tiba mati dan tidak bisa dinyalakan.')
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

    public function test_receipt_must_be_recorded_before_pdf_can_be_downloaded(): void
    {
        $warranty = $this->createWarranty();
        $claim = $this->postJson('/api/claims', [
            'warranty_id' => $warranty->id,
            'claim_date' => '2026-09-23',
            'damage_description' => 'Layar tidak menyala.',
        ])->assertCreated()->json();

        $this->get('/api/claims/'.$claim['id'].'/receipt')
            ->assertNotFound();
    }

    public function test_claim_receipt_can_be_recorded_and_downloaded_as_a_pdf(): void
    {
        $warranty = $this->createWarranty();
        $claim = $this->postJson('/api/claims', [
            'warranty_id' => $warranty->id,
            'claim_date' => '2026-09-23',
            'damage_description' => 'Layar tidak menyala.',
        ])->assertCreated()->json();

        $this->patchJson('/api/claims/'.$claim['id'].'/receipt', [
            'received_at' => '2026-09-23',
            'received_condition' => 'Layar retak, unit menyala.',
            'receipt_note' => 'Adaptor disertakan.',
        ])->assertOk()
            ->assertJsonPath('received_at', '2026-09-23T00:00:00.000000Z')
            ->assertJsonPath('received_condition', 'Layar retak, unit menyala.')
            ->assertJsonPath('receipt_note', 'Adaptor disertakan.');

        $response = $this->get('/api/claims/'.$claim['id'].'/receipt');

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename=bukti-penerimaan-'.$claim['claim_code'].'.pdf');

        $pdf = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $pdf);
        preg_match_all('/\/Type\s*\/Page\b/', $pdf, $pages);
        $this->assertCount(1, $pages[0]);
    }

    public function test_vendor_dispatch_pdf_is_available_after_claim_is_forwarded(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('Enable PHP GD to verify image embedding in the vendor dispatch PDF.');
        }

        Storage::fake('local');
        $warranty = $this->createWarranty();
        $claim = $this->postJson('/api/claims', [
            'warranty_id' => $warranty->id,
            'claim_date' => '2026-09-23',
            'damage_description' => 'Layar tidak menyala.',
        ])->assertCreated()->json();

        $this->get('/api/claims/'.$claim['id'].'/vendor-dispatch')
            ->assertNotFound();

        $this->patchJson('/api/claims/'.$claim['id'].'/status', [
            'status' => 'forwarded_to_vendor',
            'note' => 'Barang dikirim ke vendor.',
            'forwarded_date' => '2026-09-24',
            'vendor_reference_number' => 'RESI-12345',
            'shipping_carrier' => 'JNE',
        ])->assertOk()
            ->assertJsonPath('shipping_carrier', 'JNE');

        $this->post('/api/claims/'.$claim['id'].'/evidence-photo', [
            'photo' => UploadedFile::fake()->createWithContent(
                'damage.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAFklEQVQImWO8IyfHwMDAxMDAwMDAAAAOCAEchcSHIgAAAABJRU5ErkJggg=='),
            ),
        ])->assertOk();

        $response = $this->get('/api/claims/'.$claim['id'].'/vendor-dispatch');

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename=bukti-pengiriman-vendor-'.$claim['claim_code'].'.pdf');

        $pdf = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('/Subtype /Image', $pdf);
        preg_match_all('/\/Type\s*\/Page\b/', $pdf, $pages);
        $this->assertCount(2, $pages[0]);
    }

    public function test_vendor_dispatch_pdf_requires_an_evidence_photo(): void
    {
        $warranty = $this->createWarranty();
        $claim = $this->postJson('/api/claims', [
            'warranty_id' => $warranty->id,
            'claim_date' => '2026-09-23',
            'damage_description' => 'Layar tidak menyala.',
        ])->assertCreated()->json();

        $this->patchJson('/api/claims/'.$claim['id'].'/status', [
            'status' => 'forwarded_to_vendor',
            'note' => 'Barang dikirim ke vendor.',
            'forwarded_date' => '2026-09-24',
            'vendor_reference_number' => 'RESI-12345',
            'shipping_carrier' => 'JNE',
        ])->assertOk();

        $this->getJson('/api/claims/'.$claim['id'].'/vendor-dispatch')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Foto bukti kerusakan belum tersedia. Unggah foto bukti klaim sebelum mencetak PDF.');
    }

    public function test_vendor_dispatch_pdf_reports_when_gd_is_unavailable(): void
    {
        if (extension_loaded('gd')) {
            $this->markTestSkipped('This runtime has PHP GD enabled.');
        }

        $warranty = $this->createWarranty();
        $claim = $this->postJson('/api/claims', [
            'warranty_id' => $warranty->id,
            'claim_date' => '2026-09-23',
            'damage_description' => 'Layar tidak menyala.',
        ])->assertCreated()->json();

        $this->patchJson('/api/claims/'.$claim['id'].'/status', [
            'status' => 'forwarded_to_vendor',
            'note' => 'Barang dikirim ke vendor.',
            'forwarded_date' => '2026-09-24',
            'vendor_reference_number' => 'RESI-12345',
            'shipping_carrier' => 'JNE',
        ])->assertOk();

        Storage::fake('local');
        $this->post('/api/claims/'.$claim['id'].'/evidence-photo', [
            'photo' => UploadedFile::fake()->createWithContent(
                'damage.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAFklEQVQImWO8IyfHwMDAxMDAwMDAAAAOCAEchcSHIgAAAABJRU5ErkJggg=='),
            ),
        ])->assertOk();

        $this->getJson('/api/claims/'.$claim['id'].'/vendor-dispatch')
            ->assertStatus(503)
            ->assertJsonPath('message', 'PDF dengan foto memerlukan ekstensi PHP GD. Aktifkan extension=gd di php.ini lalu restart server PHP.');
    }

    public function test_evidence_photo_upload_validates_image_type(): void
    {
        $warranty = $this->createWarranty();
        $claim = $this->postJson('/api/claims', [
            'warranty_id' => $warranty->id,
            'claim_date' => '2026-09-23',
            'damage_description' => 'Layar tidak menyala.',
        ])->assertCreated()->json();

        $this->post('/api/claims/'.$claim['id'].'/evidence-photo', [
            'photo' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_receipt_requires_a_date_and_item_condition(): void
    {
        $warranty = $this->createWarranty();
        $claim = $this->postJson('/api/claims', [
            'warranty_id' => $warranty->id,
            'claim_date' => '2026-09-23',
            'damage_description' => 'Layar tidak menyala.',
        ])->assertCreated()->json();

        $this->patchJson('/api/claims/'.$claim['id'].'/receipt', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['received_at', 'received_condition']);
    }

    public function test_claim_status_transition_is_recorded_and_final_status_is_locked(): void
    {
        $warranty = $this->createWarranty();
        $claim = $this->postJson('/api/claims', [
            'warranty_id' => $warranty->id,
            'claim_date' => '2026-09-23',
            'damage_description' => 'Tidak menyala.',
        ])->json();

        $this->patchJson('/api/claims/'.$claim['id'].'/status', [
            'status' => 'forwarded_to_vendor',
            'note' => 'Diteruskan ke vendor.',
            'forwarded_date' => '2026-09-23',
            'vendor_reference_number' => 'VENDOR-001',
            'shipping_carrier' => 'JNE',
        ])->assertOk()->assertJsonPath('status', 'forwarded_to_vendor');

        $this->patchJson('/api/claims/'.$claim['id'].'/status', [
            'status' => 'completed',
            'note' => 'Langsung selesai.',
            'resolution_date' => '2026-09-23',
            'resolution_note' => 'Perbaikan selesai.',
        ])->assertUnprocessable()->assertJsonValidationErrors(['status']);

        $this->patchJson('/api/claims/'.$claim['id'].'/status', [
            'status' => 'processing_by_vendor',
            'note' => 'Vendor mulai memproses.',
        ])->assertOk();
    }

    public function test_forwarding_requires_vendor_tracking_data(): void
    {
        $warranty = $this->createWarranty();
        $claim = $this->postJson('/api/claims', [
            'warranty_id' => $warranty->id,
            'claim_date' => '2026-09-23',
            'damage_description' => 'Tidak menyala.',
        ])->json();

        $this->patchJson('/api/claims/'.$claim['id'].'/status', [
            'status' => 'forwarded_to_vendor',
            'note' => 'Diteruskan ke vendor.',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['forwarded_date', 'vendor_reference_number', 'shipping_carrier']);
    }

    public function test_completion_requires_resolution_data(): void
    {
        $warranty = $this->createWarranty();
        $claim = $this->postJson('/api/claims', [
            'warranty_id' => $warranty->id,
            'claim_date' => '2026-09-23',
            'damage_description' => 'Tidak menyala.',
        ])->json();

        $this->patchJson('/api/claims/'.$claim['id'].'/status', [
            'status' => 'forwarded_to_vendor',
            'note' => 'Diteruskan ke vendor.',
            'forwarded_date' => '2026-09-23',
            'vendor_reference_number' => 'VENDOR-002',
            'shipping_carrier' => 'JNE',
        ])->assertOk();

        $this->patchJson('/api/claims/'.$claim['id'].'/status', [
            'status' => 'processing_by_vendor',
            'note' => 'Sedang diproses vendor.',
        ])->assertOk();

        $this->patchJson('/api/claims/'.$claim['id'].'/status', [
            'status' => 'completed',
            'note' => 'Perbaikan selesai.',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['resolution_date', 'resolution_note']);
    }

    public function test_completed_claim_ends_warranty_and_prevents_another_claim(): void
    {
        $warranty = $this->createWarranty();
        $claim = $this->postJson('/api/claims', [
            'warranty_id' => $warranty->id,
            'claim_date' => '2026-09-23',
            'damage_description' => 'Tidak menyala.',
        ])->assertCreated()->json();

        $this->patchJson('/api/claims/'.$claim['id'].'/status', [
            'status' => 'forwarded_to_vendor',
            'note' => 'Diteruskan ke vendor.',
            'forwarded_date' => '2026-09-23',
            'vendor_reference_number' => 'VENDOR-003',
            'shipping_carrier' => 'JNE',
        ])->assertOk();

        $this->patchJson('/api/claims/'.$claim['id'].'/status', [
            'status' => 'processing_by_vendor',
            'note' => 'Sedang diproses vendor.',
        ])->assertOk();

        $this->patchJson('/api/claims/'.$claim['id'].'/status', [
            'status' => 'completed',
            'note' => 'Unit pengganti diberikan.',
            'resolution_date' => '2026-09-23',
            'resolution_note' => 'Unit diganti.',
        ])->assertOk();

        $this->getJson('/api/warranties/'.$warranty->id)
            ->assertOk()
            ->assertJsonPath('status', 'expired');

        $this->getJson('/api/warranties?status=active')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/warranties?status=expired')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.warranty_code', $warranty->warranty_code);

        $this->postJson('/api/claims', [
            'warranty_id' => $warranty->id,
            'claim_date' => '2026-09-23',
            'damage_description' => 'Klaim kedua.',
        ])->assertUnprocessable()->assertJsonValidationErrors(['warranty_id']);
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
