<?php

namespace App\Http\Controllers;

use App\Models\ProductUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProductUnitController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));

        $productUnits = ProductUnit::query()
            ->with(['product.vendor', 'customer', 'warranties'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($unitQuery) use ($search): void {
                    $unitQuery
                        ->where('serial_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($customerQuery) use ($search): void {
                            $customerQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('product', function ($productQuery) use ($search): void {
                            $productQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return response()->json($productUnits);
    }

    public function store(Request $request): JsonResponse
    {
        $productUnit = ProductUnit::create($this->validatedData($request));

        return response()->json($productUnit->load(['product.vendor', 'customer', 'warranties']), 201);
    }

    public function show(ProductUnit $productUnit): JsonResponse
    {
        return response()->json(
            $productUnit->load(['product.vendor', 'customer', 'warranties']),
        );
    }

    public function update(Request $request, ProductUnit $productUnit): JsonResponse
    {
        $productUnit->update($this->validatedData($request));

        return response()->json($productUnit->fresh()->load(['product.vendor', 'customer', 'warranties']));
    }

    public function destroy(ProductUnit $productUnit): JsonResponse
    {
        if ($productUnit->warranties()->exists()) {
            throw ValidationException::withMessages([
                'product_unit' => ['Unit produk tidak dapat dihapus karena masih memiliki garansi.'],
            ]);
        }

        $productUnit->delete();

        return response()->json([
            'message' => 'Unit produk berhasil dihapus.',
        ]);
    }

    /**
     * @return array{product_id: int, customer_id: int, serial_number: string, purchase_date: ?string}
     */
    private function validatedData(Request $request): array
    {
        $productUnitId = $request->route('product_unit')?->id;

        return $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'serial_number' => ['required', 'string', 'max:255', 'unique:product_units,serial_number,' . $productUnitId],
            'purchase_date' => ['nullable', 'date'],
        ]);
    }
}
