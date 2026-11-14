<?php

namespace App\Http\Controllers;

use App\Models\PaidServiceRequest;
use App\Models\Warranty;
use App\Models\Warranty_claims;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $today = CarbonImmutable::today();
        $threshold = $today->addDays((int) config('warranty.expiring_days', 30));

        $warrantyStats = DB::table('warranties')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN end_date > ? AND NOT EXISTS (
                    SELECT 1 FROM warranty_claims
                    WHERE warranty_claims.warranty_id = warranties.id
                    AND warranty_claims.status = 'completed'
                ) THEN 1 ELSE 0 END), 0) as active",
                [$threshold->toDateString()],
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN end_date >= ? AND end_date <= ? AND NOT EXISTS (
                    SELECT 1 FROM warranty_claims
                    WHERE warranty_claims.warranty_id = warranties.id
                    AND warranty_claims.status = 'completed'
                ) THEN 1 ELSE 0 END), 0) as expiring",
                [$today->toDateString(), $threshold->toDateString()],
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN end_date < ? OR EXISTS (
                    SELECT 1 FROM warranty_claims
                    WHERE warranty_claims.warranty_id = warranties.id
                    AND warranty_claims.status = 'completed'
                ) THEN 1 ELSE 0 END), 0) as expired",
                [$today->toDateString()],
            )
            ->first();

        $claimStats = DB::table('warranty_claims')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'received' THEN 1 ELSE 0 END), 0) as received")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'forwarded_to_vendor' THEN 1 ELSE 0 END), 0) as forwarded_to_vendor")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'processing_by_vendor' THEN 1 ELSE 0 END), 0) as processing_by_vendor")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END), 0) as completed")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END), 0) as rejected")
            ->first();

        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'warranties' => [
                'active' => (int) $warrantyStats->active,
                'expiring' => (int) $warrantyStats->expiring,
                'expired' => (int) $warrantyStats->expired,
                'total' => (int) $warrantyStats->total,
            ],
            'claims' => [
                'received' => (int) $claimStats->received,
                'forwarded_to_vendor' => (int) $claimStats->forwarded_to_vendor,
                'processing_by_vendor' => (int) $claimStats->processing_by_vendor,
                'completed' => (int) $claimStats->completed,
                'rejected' => (int) $claimStats->rejected,
                'total' => (int) $claimStats->total,
            ],
            'recent_claims' => Warranty_claims::query()
                ->with(['customer:id,name', 'product:id,name'])
                ->latest('claim_date')
                ->limit(5)
                ->get(['id', 'claim_code', 'serial_number', 'claim_date', 'status', 'customer_id', 'product_id']),
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
        $threshold = $today->addDays((int) config('warranty.expiring_days', 30));
        $warranties = Warranty::query()
            ->with(['productUnit.product', 'productUnit.customer'])
            ->withExists([
                'claims as has_completed_claim' => fn ($query) => $query->where('status', 'completed'),
            ])
            ->when($validated['from'] ?? null, fn ($query, $from) => $query->whereDate('start_date', '>=', $from))
            ->when($validated['to'] ?? null, fn ($query, $to) => $query->whereDate('start_date', '<=', $to))
            ->when($validated['status'] ?? null, function ($query, $status) use ($today, $threshold): void {
                if ($status === 'expired') {
                    $query->where(function ($warrantyQuery) use ($today): void {
                        $warrantyQuery
                            ->whereDate('end_date', '<', $today)
                            ->orWhereHas('claims', fn ($claimQuery) => $claimQuery->where('status', 'completed'));
                    });
                } elseif ($status === 'expiring') {
                    $query->whereDate('end_date', '>=', $today)
                        ->whereDate('end_date', '<=', $threshold)
                        ->whereDoesntHave('claims', fn ($claimQuery) => $claimQuery->where('status', 'completed'));
                } else {
                    $query->whereDate('end_date', '>', $threshold)
                        ->whereDoesntHave('claims', fn ($claimQuery) => $claimQuery->where('status', 'completed'));
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
                $warranty->getAttribute('has_completed_claim')
                    ? 'expired'
                    : ($warranty->end_date->isBefore($today)
                    ? 'expired'
                    : ($warranty->end_date->lessThanOrEqualTo($threshold) ? 'expiring' : 'active')),
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

    public function paidServices(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'status' => ['nullable', 'in:requested,quoted,approved,in_progress,completed,cancelled'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $serviceRequests = PaidServiceRequest::query()
            ->with(['productUnit.product', 'productUnit.customer', 'vendor'])
            ->when($validated['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($validated['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['search'] ?? null, function ($query, $search): void {
                $query->where(function ($serviceQuery) use ($search): void {
                    $serviceQuery->where('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_phone', 'like', "%{$search}%")
                        ->orWhere('product_name', 'like', "%{$search}%")
                        ->orWhere('serial_number', 'like', "%{$search}%")
                        ->orWhere('issue_description', 'like', "%{$search}%")
                        ->orWhereHas('vendor', fn ($vendorQuery) => $vendorQuery->where('name', 'like', "%{$search}%"))
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

        return response()->json($serviceRequests);
    }
}
