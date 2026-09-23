<?php

namespace App\Http\Controllers;

use App\Models\Warranty;
use App\Models\Warranty_claims;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function summary(): JsonResponse
    {
        $today = CarbonImmutable::today();
        $threshold = $today->addDays(30);

        return response()->json([
            'warranties' => [
                'active' => Warranty::query()->whereDate('end_date', '>', $threshold)->count(),
                'expiring' => Warranty::query()
                    ->whereDate('end_date', '>=', $today)
                    ->whereDate('end_date', '<=', $threshold)
                    ->count(),
                'expired' => Warranty::query()->whereDate('end_date', '<', $today)->count(),
                'total' => Warranty::query()->count(),
            ],
            'claims' => [
                'received' => Warranty_claims::query()->where('status', 'received')->count(),
                'forwarded_to_vendor' => Warranty_claims::query()->where('status', 'forwarded_to_vendor')->count(),
                'processing_by_vendor' => Warranty_claims::query()->where('status', 'processing_by_vendor')->count(),
                'completed' => Warranty_claims::query()->where('status', 'completed')->count(),
                'rejected' => Warranty_claims::query()->where('status', 'rejected')->count(),
                'total' => Warranty_claims::query()->count(),
            ],
        ]);
    }

    public function warranties(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'status' => ['nullable', 'in:active,expiring,expired'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $today = CarbonImmutable::today();
        $threshold = $today->addDays(30);
        $warranties = Warranty::query()
            ->with(['productUnit.product', 'productUnit.customer'])
            ->when($validated['from'] ?? null, fn ($query, $from) => $query->whereDate('start_date', '>=', $from))
            ->when($validated['to'] ?? null, fn ($query, $to) => $query->whereDate('start_date', '<=', $to))
            ->when($validated['status'] ?? null, function ($query, $status) use ($today, $threshold): void {
                if ($status === 'expired') {
                    $query->whereDate('end_date', '<', $today);
                } elseif ($status === 'expiring') {
                    $query->whereDate('end_date', '>=', $today)->whereDate('end_date', '<=', $threshold);
                } else {
                    $query->whereDate('end_date', '>', $threshold);
                }
            })
            ->when($validated['search'] ?? null, function ($query, $search): void {
                $query->where(function ($warrantyQuery) use ($search): void {
                    $warrantyQuery
                        ->where('warranty_code', 'like', "%{$search}%")
                        ->orWhereHas('productUnit', function ($unitQuery) use ($search): void {
                            $unitQuery->where('serial_number', 'like', "%{$search}%")
                                ->orWhereHas('customer', fn ($customerQuery) => $customerQuery->where('name', 'like', "%{$search}%"))
                                ->orWhereHas('product', fn ($productQuery) => $productQuery->where('name', 'like', "%{$search}%"));
                        });
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $warranties->getCollection()->transform(function (Warranty $warranty) use ($today, $threshold): Warranty {
            $warranty->setAttribute(
                'status',
                $warranty->end_date->isBefore($today)
                    ? 'expired'
                    : ($warranty->end_date->lessThanOrEqualTo($threshold) ? 'expiring' : 'active'),
            );

            return $warranty;
        });

        return response()->json($warranties);
    }

    public function claims(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'status' => ['nullable', 'in:received,forwarded_to_vendor,processing_by_vendor,completed,rejected'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $claims = Warranty_claims::query()
            ->with(['warranty', 'customer', 'product', 'vendor'])
            ->when($validated['from'] ?? null, fn ($query, $from) => $query->whereDate('claim_date', '>=', $from))
            ->when($validated['to'] ?? null, fn ($query, $to) => $query->whereDate('claim_date', '<=', $to))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['search'] ?? null, function ($query, $search): void {
                $query->where(function ($claimQuery) use ($search): void {
                    $claimQuery->where('claim_code', 'like', "%{$search}%")
                        ->orWhere('serial_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($customerQuery) => $customerQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('product', fn ($productQuery) => $productQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest('claim_date')
            ->paginate(15)
            ->withQueryString();

        return response()->json($claims);
    }
}
