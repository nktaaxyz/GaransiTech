<?php

namespace App\Http\Controllers;

use App\Models\ClaimStatusLog;
use App\Models\Warranty;
use App\Models\Warranty_claims;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class WarrantyClaimController extends Controller
{
    private const STATUSES = [
        'received',
        'forwarded_to_vendor',
        'processing_by_vendor',
        'completed',
        'rejected',
    ];

    public function summarize(Request $request): JsonResponse
    {
        $request->validate([
            'transcript' => ['required', 'string', 'max:10000'],
        ]);

        return response()->json([
            'message' => 'Layanan AI belum dikonfigurasi.',
        ], 503);
    }

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));

        $claims = Warranty_claims::query()
            ->with(['warranty', 'customer', 'product', 'vendor', 'statusLogs.changedBy'])
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($claimQuery) use ($search): void {
                    $claimQuery
                        ->where('claim_code', 'like', "%{$search}%")
                        ->orWhere('serial_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($customerQuery) => $customerQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('product', fn ($productQuery) => $productQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return response()->json($claims);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warranty_id' => ['required', 'integer', 'exists:warranties,id'],
            'claim_date' => ['required', 'date'],
            'damage_description' => ['required', 'string'],
            'client_statement' => ['nullable', 'string', 'max:10000'],
            'note' => ['nullable', 'string'],
        ]);

        $warranty = Warranty::with('productUnit.product.vendor', 'productUnit.customer')
            ->findOrFail($validated['warranty_id']);

        if ($warranty->claims()->where('status', 'completed')->exists()) {
            throw ValidationException::withMessages([
                'warranty_id' => ['Garansi ini sudah digunakan untuk klaim yang selesai.'],
            ]);
        }

        $claimDate = CarbonImmutable::parse($validated['claim_date']);

        if ($claimDate->lt($warranty->start_date) || $claimDate->gt($warranty->end_date)) {
            throw ValidationException::withMessages([
                'warranty_id' => ['Garansi tidak aktif pada tanggal klaim.'],
            ]);
        }

        $claim = DB::transaction(function () use ($validated, $warranty, $request): Warranty_claims {
            $unit = $warranty->productUnit;
            $claim = Warranty_claims::create([
                'claim_code' => $this->generateClaimCode(),
                'warranty_id' => $warranty->id,
                'customer_id' => $unit->customer_id,
                'product_id' => $unit->product_id,
                'vendor_id' => $unit->product->vendor_id,
                'serial_number' => $unit->serial_number,
                'damage_description' => $validated['damage_description'],
                'client_statement' => $validated['client_statement'] ?? null,
                'claim_date' => $validated['claim_date'],
                'status' => 'received',
            ]);

            $claim->statusLogs()->create([
                'new_status' => 'received',
                'note' => $validated['note'] ?? 'Klaim diterima.',
                'changed_by' => $request->user()?->id,
            ]);

            return $claim;
        });

        return response()->json(
            $claim->load(['warranty', 'customer', 'product', 'vendor', 'statusLogs.changedBy']),
            201,
        );
    }

    public function show(Warranty_claims $claim): JsonResponse
    {
        return response()->json(
            $claim->load(['warranty', 'customer', 'product', 'vendor', 'statusLogs.changedBy']),
        );
    }

    public function recordReceipt(Request $request, Warranty_claims $claim): JsonResponse
    {
        $validated = $request->validate([
            'received_at' => ['required', 'date'],
            'received_condition' => ['required', 'string', 'max:10000'],
            'receipt_note' => ['nullable', 'string', 'max:10000'],
        ]);

        $claim->update($validated);

        return response()->json(
            $claim->fresh()->load(['warranty', 'customer', 'product', 'vendor', 'statusLogs.changedBy']),
        );
    }

    public function downloadReceipt(Warranty_claims $claim): Response
    {
        if ($claim->received_at === null) {
            abort(404, 'Data penerimaan barang untuk klaim ini belum dicatat.');
        }

        $claim->load(['customer', 'product', 'vendor']);

        return Pdf::loadView('pdf.claim-receipt', [
            'claim' => $claim,
        ])
            ->setPaper('a4')
            ->download("bukti-penerimaan-{$claim->claim_code}.pdf");
    }

    public function downloadVendorDispatch(Warranty_claims $claim): Response
    {
        if ($claim->forwarded_date === null || $claim->vendor_reference_number === null) {
            abort(404, 'Data pengiriman barang ke vendor untuk klaim ini belum dicatat.');
        }

        if ($claim->evidence_photo_path === null || ! Storage::disk('local')->exists($claim->evidence_photo_path)) {
            abort(422, 'Foto bukti kerusakan belum tersedia. Unggah foto bukti klaim sebelum mencetak PDF.');
        }

        if (! extension_loaded('gd')) {
            abort(503, 'PDF dengan foto memerlukan ekstensi PHP GD. Aktifkan extension=gd di php.ini lalu restart server PHP.');
        }

        $claim->load(['customer', 'product', 'vendor']);
        $photoDataUri = $this->createDispatchPhotoDataUri($claim);

        return Pdf::loadView('pdf.claim-vendor-dispatch', [
            'claim' => $claim,
            'copies' => ['Vendor', 'Arsip Toko'],
            'photoDataUri' => $photoDataUri,
        ])
            ->setPaper('a4')
            ->download("bukti-pengiriman-vendor-{$claim->claim_code}.pdf");
    }

    private function createDispatchPhotoDataUri(Warranty_claims $claim): string
    {
        $source = imagecreatefromstring(Storage::disk('local')->get($claim->evidence_photo_path));
        if ($source === false) {
            throw new \RuntimeException('Foto bukti klaim tidak dapat dibaca untuk PDF.');
        }

        $canvas = imagecreatetruecolor(1450, 600);
        if ($canvas === false) {
            imagedestroy($source);
            throw new \RuntimeException('Area foto PDF tidak dapat dibuat.');
        }

        $background = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $background);

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = min(1450 / $sourceWidth, 600 / $sourceHeight);
        $width = (int) round($sourceWidth * $scale);
        $height = (int) round($sourceHeight * $scale);
        imagecopyresampled(
            $canvas,
            $source,
            (int) floor((1450 - $width) / 2),
            (int) floor((600 - $height) / 2),
            0,
            0,
            $width,
            $height,
            $sourceWidth,
            $sourceHeight,
        );

        ob_start();
        $encoded = imagejpeg($canvas, null, 85);
        $image = ob_get_clean();
        imagedestroy($source);
        imagedestroy($canvas);

        if (! $encoded || $image === false) {
            throw new \RuntimeException('Foto bukti klaim gagal disiapkan untuk PDF.');
        }

        return 'data:image/jpeg;base64,'.base64_encode($image);
    }

    public function uploadEvidencePhoto(Request $request, Warranty_claims $claim): JsonResponse
    {
        $validated = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,png', 'max:5120'],
        ]);

        $photo = $validated['photo'];
        $path = $photo->store('claim-evidence', 'local');
        if ($path === false) {
            throw new \RuntimeException('Foto bukti klaim gagal disimpan.');
        }

        $oldPath = $claim->evidence_photo_path;
        $claim->update([
            'evidence_photo_path' => $path,
            'evidence_photo_mime_type' => $photo->getMimeType(),
        ]);

        if ($oldPath && $oldPath !== $path) {
            Storage::disk('local')->delete($oldPath);
        }

        return response()->json([
            'message' => 'Foto bukti klaim berhasil diunggah.',
        ]);
    }

    public function update(Request $request, Warranty_claims $claim): JsonResponse
    {
        $validated = $request->validate([
            'damage_description' => ['sometimes', 'required', 'string'],
            'claim_date' => ['sometimes', 'required', 'date'],
        ]);

        $claim->update($validated);

        return response()->json(
            $claim->fresh()->load(['warranty', 'customer', 'product', 'vendor', 'statusLogs.changedBy']),
        );
    }

    public function updateStatus(Request $request, Warranty_claims $claim): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(self::STATUSES)],
            'note' => ['required', 'string'],
            'forwarded_date' => [
                Rule::requiredIf($request->input('status') === 'forwarded_to_vendor'),
                'nullable',
                'date',
            ],
            'vendor_reference_number' => [
                Rule::requiredIf($request->input('status') === 'forwarded_to_vendor'),
                'nullable',
                'string',
                'max:255',
            ],
            'shipping_carrier' => [
                Rule::requiredIf($request->input('status') === 'forwarded_to_vendor'),
                'nullable',
                'string',
                'max:255',
            ],
            'resolution_date' => [
                Rule::requiredIf($request->input('status') === 'completed'),
                'nullable',
                'date',
            ],
            'resolution_note' => [
                Rule::requiredIf($request->input('status') === 'completed'),
                'nullable',
                'string',
            ],
        ]);

        $this->ensureValidTransition($claim->status, $validated['status']);

        DB::transaction(function () use ($claim, $validated, $request): void {
            $oldStatus = $claim->status;
            $claim->update([
                'status' => $validated['status'],
                'forwarded_date' => $validated['forwarded_date'] ?? $claim->forwarded_date,
                'shipping_carrier' => $validated['shipping_carrier'] ?? $claim->shipping_carrier,
                'vendor_reference_number' => $validated['vendor_reference_number'] ?? $claim->vendor_reference_number,
                'resolution_date' => $validated['resolution_date'] ?? $claim->resolution_date,
                'resolution_note' => $validated['resolution_note'] ?? $claim->resolution_note,
            ]);

            ClaimStatusLog::create([
                'claim_id' => $claim->id,
                'old_status' => $oldStatus,
                'new_status' => $claim->status,
                'note' => $validated['note'],
                'changed_by' => $request->user()?->id,
            ]);
        });

        return response()->json(
            $claim->fresh()->load(['warranty', 'customer', 'product', 'vendor', 'statusLogs.changedBy']),
        );
    }

    private function ensureValidTransition(string $current, string $next): void
    {
        $allowed = [
            'received' => ['forwarded_to_vendor', 'rejected'],
            'forwarded_to_vendor' => ['processing_by_vendor', 'rejected'],
            'processing_by_vendor' => ['completed', 'rejected'],
            'completed' => [],
            'rejected' => [],
        ];

        if (! in_array($next, $allowed[$current] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => ["Perubahan status dari {$current} ke {$next} tidak diizinkan."],
            ]);
        }
    }

    private function generateClaimCode(): string
    {
        do {
            $code = 'CLM-'.CarbonImmutable::today()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (Warranty_claims::query()->where('claim_code', $code)->exists());

        return $code;
    }
}
