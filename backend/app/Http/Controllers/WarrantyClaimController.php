<?php

namespace App\Http\Controllers;

use App\Models\ClaimStatusLog;
use App\Models\Warranty;
use App\Models\Warranty_claims;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WarrantyClaimController extends Controller
{
    private const STATUSES = [
        'received',
        'forwarded_to_vendor',
        'processing_by_vendor',
        'completed',
        'rejected',
    ];

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
            'note' => ['nullable', 'string'],
        ]);

        $warranty = Warranty::with('productUnit.product.vendor', 'productUnit.customer')
            ->findOrFail($validated['warranty_id']);
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
            $code = 'CLM-' . CarbonImmutable::today()->format('Ymd') . '-' . Str::upper(Str::random(6));
        } while (Warranty_claims::query()->where('claim_code', $code)->exists());

        return $code;
    }
}
