<?php

namespace App\Http\Controllers;

use App\Models\PaidServiceRequest;
use App\Models\ProductUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaidServiceRequestController extends Controller
{
    private const STATUSES = [
        'requested',
        'quoted',
        'approved',
        'in_progress',
        'completed',
        'cancelled',
    ];

    public function index(): JsonResponse
    {
        return response()->json(
            PaidServiceRequest::query()
                ->with(['productUnit.product', 'productUnit.customer', 'vendor'])
                ->latest()
                ->paginate(15),
        );
    }

    public function eligibleUnits(): JsonResponse
    {
        $today = now()->toDateString();

        return response()->json(
            ProductUnit::query()
                ->whereHas('warranties', fn ($query) => $query->whereDate('end_date', '<', $today))
                ->whereDoesntHave('warranties', fn ($query) => $query->whereDate('end_date', '>=', $today))
                ->with(['product', 'customer', 'warranties'])
                ->latest()
                ->get(),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_unit_id' => ['required', 'integer', 'exists:product_units,id'],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'issue_description' => ['required', 'string', 'max:10000'],
            'quoted_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $this->ensureWarrantyExpired((int) $validated['product_unit_id']);

        $serviceRequest = PaidServiceRequest::create([
            ...$validated,
            'status' => 'requested',
        ]);

        return response()->json(
            $serviceRequest->load(['productUnit.product', 'productUnit.customer', 'vendor']),
            201,
        );
    }

    public function update(Request $request, PaidServiceRequest $paidServiceRequest): JsonResponse
    {
        $validated = $request->validate([
            'vendor_id' => ['sometimes', 'nullable', 'integer', 'exists:vendors,id'],
            'issue_description' => ['sometimes', 'required', 'string', 'max:10000'],
            'quoted_amount' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'status' => ['sometimes', 'required', Rule::in(self::STATUSES)],
        ]);

        $paidServiceRequest->update($validated);

        return response()->json(
            $paidServiceRequest->fresh()->load(['productUnit.product', 'productUnit.customer', 'vendor']),
        );
    }

    private function ensureWarrantyExpired(int $productUnitId): void
    {
        $unit = ProductUnit::query()->findOrFail($productUnitId);
        $today = now()->toDateString();

        if (
            ! $unit->warranties()->whereDate('end_date', '<', $today)->exists()
            || $unit->warranties()->whereDate('end_date', '>=', $today)->exists()
        ) {
            throw ValidationException::withMessages([
                'product_unit_id' => ['Servis berbayar hanya dapat dibuat untuk unit dengan garansi yang sudah berakhir.'],
            ]);
        }
    }
}
