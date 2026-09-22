<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));

        $products = Product::query()
            ->with(['vendor'])
            ->withCount('units')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($productQuery) use ($search): void {
                    $productQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return response()->json($products);
    }

    public function store(Request $request): JsonResponse
    {
        $product = Product::create($this->validatedData($request));

        return response()->json($product->load(['vendor'])->loadCount('units'), 201);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json(
            $product->load(['vendor', 'units.customer'])->loadCount('units'),
        );
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $product->update($this->validatedData($request));

        return response()->json($product->fresh()->load(['vendor'])->loadCount('units'));
    }

    public function destroy(Product $product): JsonResponse
    {
        if ($product->units()->exists()) {
            throw ValidationException::withMessages([
                'product' => ['Produk tidak dapat dihapus karena masih memiliki unit produk.'],
            ]);
        }

        $product->delete();

        return response()->json([
            'message' => 'Produk berhasil dihapus.',
        ]);
    }

    /**
     * @return array{name: string, category: ?string, vendor_id: int}
     */
    private function validatedData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'vendor_id' => ['required', 'integer', 'exists:vendors,id'],
        ]);
    }
}
