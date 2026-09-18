<?php

namespace App\Http\Controllers;

use App\Models\ClaimStatusLog;
use App\Models\Warranty_claims;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WarrantyClaimController extends Controller
{
    public function index(): JsonResponse
    {
        $claims = Warranty_claims::query()
            ->with(['customer', 'product', 'vendor', 'statusLogs.changedBy'])
            ->latest()
            ->paginate(15);

        return response()->json($claims);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'claim_code' => ['required', 'string', 'max:255', 'unique:warranty_claims,claim_code'],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'vendor_id' => ['required', 'integer', 'exists:vendors,id'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'claim_date' => ['required', 'date'],
            'note' => ['nullable', 'string'],
        ]);

        $claim = DB::transaction(function () use ($validated, $request): Warranty_claims {
            $claim = Warranty_claims::create([
                'claim_code' => $validated['claim_code'],
                'customer_id' => $validated['customer_id'],
                'product_id' => $validated['product_id'],
                'vendor_id' => $validated['vendor_id'],
                'serial_number' => $validated['serial_number'] ?? null,
                'claim_date' => $validated['claim_date'],
                'status' => 'received',
            ]);

            $claim->statusLogs()->create([
                'new_status' => $claim->status,
                'note' => $validated['note'] ?? 'Claim diterima.',
                'changed_by' => $request->user()?->id,
            ]);

            return $claim;
        });

        return response()->json($claim->load(['customer', 'product', 'vendor', 'statusLogs']), 201);
    }

    public function show(Warranty_claims $claim): JsonResponse
    {
        return response()->json(
            $claim->load(['customer', 'product', 'vendor', 'statusLogs.changedBy'])
        );
    }

    public function updateStatus(Request $request, Warranty_claims $claim): JsonResponse
    {
        $validated = $request->validate([
            'status' => [
                'required',
                'string',
                Rule::in(['received', 'forwaded_to_vendor', 'processing_by_vendor', 'completed', 'rejected']),
            ],
            'note' => ['required', 'string'],
            'forwarded_date' => ['nullable', 'date'],
            'vendor_reference_number' => ['nullable', 'string', 'max:255'],
            'resolution_date' => ['nullable', 'date'],
            'resolution_note' => ['nullable', 'string'],
        ]);

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

        return response()->json($claim->load(['customer', 'product', 'vendor', 'statusLogs']));
    }
}
