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
                ->whereDoesntHave('warranties', fn ($query) => $query
                    ->whereDate('start_date', '<=', $today)
                    ->whereDate('end_date', '>=', $today))
                ->with(['product', 'customer', 'warranties'])
                ->latest()
                ->get(),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_unit_id' => ['nullable', 'integer', 'exists:product_units,id'],
            'customer_name' => ['required_without:product_unit_id', 'nullable', 'string', 'max:255'],
            'customer_phone' => ['required_without:product_unit_id', 'nullable', 'string', 'max:50'],
            'product_name' => ['required_without:product_unit_id', 'nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'issue_description' => ['required', 'string', 'max:10000'],
            'quoted_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $unit = isset($validated['product_unit_id'])
            ? ProductUnit::with(['customer', 'product'])->findOrFail($validated['product_unit_id'])
            : null;

        if ($unit !== null) {
            $this->ensureNoActiveWarranty($unit);
        }

        $serviceRequest = PaidServiceRequest::create([
            ...$validated,
            'customer_name' => $unit?->customer?->name ?? ($validated['customer_name'] ?? null),
            'customer_phone' => $unit?->customer?->phone ?? ($validated['customer_phone'] ?? null),
            'product_name' => $unit?->product?->name ?? ($validated['product_name'] ?? null),
            'serial_number' => $unit?->serial_number ?? ($validated['serial_number'] ?? null),
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

    private function ensureNoActiveWarranty(ProductUnit $unit): void
    {
        $today = now()->toDateString();

        if ($unit->warranties()
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->exists()) {
            throw ValidationException::withMessages([
                'product_unit_id' => ['Unit dengan garansi aktif harus diproses melalui klaim garansi.'],
            ]);
        }
    }
}
