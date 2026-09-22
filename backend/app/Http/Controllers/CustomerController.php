<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search'));

        $customers = Customer::query()
            ->withCount('productUnits')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($customerQuery) use ($search): void {
                    $customerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return response()->json($customers);
    }

    public function store(Request $request): JsonResponse
    {
        $customer = Customer::create($this->validatedData($request));

        return response()->json($customer->loadCount('productUnits'), 201);
    }

    public function show(Customer $customer): JsonResponse
    {
        return response()->json(
            $customer->load([
                'productUnits.product',
                'productUnits.warranties',
            ])->loadCount('productUnits'),
        );
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $customer->update($this->validatedData($request));

        return response()->json($customer->fresh()->loadCount('productUnits'));
    }

    public function destroy(Customer $customer): JsonResponse
    {
        if ($customer->productUnits()->exists() || $customer->claims()->exists()) {
            throw ValidationException::withMessages([
                'customer' => ['Customer tidak dapat dihapus karena masih memiliki data garansi atau klaim.'],
            ]);
        }

        $customer->delete();

        return response()->json([
            'message' => 'Customer berhasil dihapus.',
        ]);
    }

    /**
     * @return array{name: string, phone: string, address: ?string}
     */
    private function validatedData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
        ]);
    }
}
