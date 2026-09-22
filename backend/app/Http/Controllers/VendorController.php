<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VendorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));

        $vendors = Vendor::query()
            ->withCount('products')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($vendorQuery) use ($search): void {
                    $vendorQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('contact_person', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return response()->json($vendors);
    }

    public function store(Request $request): JsonResponse
    {
        $vendor = Vendor::create($this->validatedData($request));

        return response()->json($vendor->loadCount('products'), 201);
    }

    public function show(Vendor $vendor): JsonResponse
    {
        return response()->json(
            $vendor->load(['products'])->loadCount('products'),
        );
    }

    public function update(Request $request, Vendor $vendor): JsonResponse
    {
        $vendor->update($this->validatedData($request));

        return response()->json($vendor->fresh()->loadCount('products'));
    }

    public function destroy(Vendor $vendor): JsonResponse
    {
        if ($vendor->products()->exists()) {
            throw ValidationException::withMessages([
                'vendor' => ['Vendor tidak dapat dihapus karena masih memiliki produk.'],
            ]);
        }

        $vendor->delete();

        return response()->json([
            'message' => 'Vendor berhasil dihapus.',
        ]);
    }

    /**
     * @return array{name: string, contact_person: ?string, phone: ?string, email: ?string}
     */
    private function validatedData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => [
                'nullable',
                'string',
                'email',
                'max:255',
                Rule::unique('vendors', 'email')->ignore($request->route('vendor')?->id),
            ],
        ]);
    }
}
