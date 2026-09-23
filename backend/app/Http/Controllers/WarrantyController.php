<?php

namespace App\Http\Controllers;

use App\Models\Warranty;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class WarrantyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));
        $statusFilter = $request->query('status');
        $request->validate([
            'status' => ['nullable', 'string', 'in:active,expiring,expired'],
        ]);

        $warranties = Warranty::query()
            ->with(['productUnit.product', 'productUnit.customer'])
            ->when($statusFilter !== null, function ($query) use ($statusFilter): void {
                $today = CarbonImmutable::today()->toDateString();
                $threshold = CarbonImmutable::today()->addDays(30)->toDateString();

                if ($statusFilter === 'expired') {
                    $query->whereDate('end_date', '<', $today);
                } elseif ($statusFilter === 'expiring') {
                    $query->whereDate('end_date', '>=', $today)
                        ->whereDate('end_date', '<=', $threshold);
                } else {
                    $query->whereDate('end_date', '>', $threshold);
                }
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($warrantyQuery) use ($search): void {
                    $warrantyQuery
                        ->where('warranty_code', 'like', "%{$search}%")
                        ->orWhereHas('productUnit', function ($unitQuery) use ($search): void {
                            $unitQuery
                                ->where('serial_number', 'like', "%{$search}%")
                                ->orWhereHas('customer', function ($customerQuery) use ($search): void {
                                    $customerQuery->where('name', 'like', "%{$search}%");
                                })
                                ->orWhereHas('product', function ($productQuery) use ($search): void {
                                    $productQuery->where('name', 'like', "%{$search}%");
                                });
                        });
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $warranties->getCollection()->transform(
            fn (Warranty $warranty): Warranty => $this->withStatus($warranty),
        );

        return response()->json($warranties);
    }

    public function store(Request $request): JsonResponse
    {
        $warranty = Warranty::create([
            ...$this->validatedData($request),
            'warranty_code' => $this->generateWarrantyCode(),
        ]);

        return response()->json(
            $this->withStatus($warranty->load(['productUnit.product', 'productUnit.customer'])),
            201,
        );
    }

    public function show(Warranty $warranty): JsonResponse
    {
        return response()->json(
            $this->withStatus($warranty->load(['productUnit.product', 'productUnit.customer', 'claims'])),
        );
    }

    public function update(Request $request, Warranty $warranty): JsonResponse
    {
        $warranty->update($this->validatedData($request, $warranty));

        return response()->json(
            $this->withStatus($warranty->fresh()->load(['productUnit.product', 'productUnit.customer'])),
        );
    }

    public function destroy(Warranty $warranty): JsonResponse
    {
        if ($warranty->claims()->exists()) {
            throw ValidationException::withMessages([
                'warranty' => ['Garansi tidak dapat dihapus karena sudah memiliki klaim.'],
            ]);
        }

        $warranty->delete();

        return response()->json([
            'message' => 'Garansi berhasil dihapus.',
        ]);
    }

    private function withStatus(Warranty $warranty): Warranty
    {
        $today = CarbonImmutable::today();
        $endDate = CarbonImmutable::parse($warranty->end_date);
        $expiringThreshold = $today->addDays(30);

        $status = $endDate->isBefore($today)
            ? 'expired'
            : ($endDate->lessThanOrEqualTo($expiringThreshold) ? 'expiring' : 'active');

        $warranty->setAttribute('status', $status);

        return $warranty;
    }

    /**
     * @return array{warranty_code: string, product_unit_id: int, start_date: string, end_date: string, notes: ?string}
     */
    private function validatedData(Request $request, ?Warranty $warranty = null): array
    {
        return $request->validate([
            'product_unit_id' => ['required', 'integer', 'exists:product_units,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function generateWarrantyCode(): string
    {
        do {
            $code = 'GAR-' . CarbonImmutable::today()->format('Ymd') . '-' . Str::upper(Str::random(6));
        } while (Warranty::query()->where('warranty_code', $code)->exists());

        return $code;
    }
}
