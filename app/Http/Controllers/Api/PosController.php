<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\ProductResource;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosController extends Controller
{
    /**
     * Get POS configuration options for Flutter POS clients.
     */
    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'POS options retrieved successfully',
            'data' => [
                'payment_methods' => Invoice::getPaymentOptions(),
                'invoice_statuses' => Invoice::getStatusOptions(),
                'customer_statuses' => [
                    'active' => 'نشط',
                    'inactive' => 'غير نشط',
                ],
            ],
        ]);
    }

    /**
     * Lookup products by SKU, barcode, or name for POS quick search.
     */
    public function productLookup(Request $request): JsonResponse
    {
        $sku = $request->get('sku');
        $query = $request->get('q');

        $products = Product::query()
            ->where('is_active', 1)
            ->with('category');

        if ($sku) {
            $products->where('sku', $sku);
        }

        if ($query) {
            $searchTerm = '%' . $query . '%';
            $products->where(function ($q) use ($searchTerm) {
                $q->where('name_ar', 'like', $searchTerm)
                    ->orWhere('name_en', 'like', $searchTerm)
                    ->orWhere('brand', 'like', $searchTerm)
                    ->orWhere('model', 'like', $searchTerm)
                    ->orWhere('sku', 'like', $searchTerm);
            });
        }

        $products = $products->limit(50)->get();

        return response()->json([
            'success' => true,
            'message' => 'POS product lookup completed successfully',
            'data' => ProductResource::collection($products),
        ]);
    }

    /**
     * List POS customers with optional search.
     */
    public function customers(Request $request): JsonResponse
    {
        $query = Customer::query();

        if ($request->filled('search')) {
            $searchTerm = '%' . $request->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)
                    ->orWhere('phone', 'like', $searchTerm)
                    ->orWhere('email', 'like', $searchTerm)
                    ->orWhere('company', 'like', $searchTerm);
            });
        }

        $perPage = min(max((int) $request->get('per_page', 20), 1), 100);
        $customers = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'POS customers retrieved successfully',
            'data' => [
                'customers' => CustomerResource::collection($customers->items()),
                'pagination' => [
                    'current_page' => $customers->currentPage(),
                    'last_page' => $customers->lastPage(),
                    'per_page' => $customers->perPage(),
                    'total' => $customers->total(),
                    'has_more_pages' => $customers->hasMorePages(),
                ],
            ],
        ]);
    }

    /**
     * Create or update a customer from POS.
     */
    public function customerStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:1000',
            'source' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:active,inactive',
            'notes' => 'nullable|string|max:2000',
        ], [
            'name.required' => 'الاسم مطلوب',
            'email.email' => 'البريد الإلكتروني غير صحيح',
            'status.in' => 'الحالة غير صالحة',
        ]);

        $customer = null;
        if (!empty($validated['phone'])) {
            $customer = Customer::where('phone', $validated['phone'])->first();
        }

        if (!$customer && !empty($validated['email'])) {
            $customer = Customer::where('email', $validated['email'])->first();
        }

        if ($customer) {
            $customer->update($validated);
        } else {
            $customer = Customer::create($validated);
        }

        return response()->json([
            'success' => true,
            'message' => 'Customer saved successfully',
            'data' => new CustomerResource($customer),
        ], $customer->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Show customer details.
     */
    public function customerUpdate(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:1000',
            'source' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:active,inactive',
            'notes' => 'nullable|string|max:2000',
        ], [
            'name.required' => 'الاسم مطلوب',
            'email.email' => 'البريد الإلكتروني غير صحيح',
            'status.in' => 'الحالة غير صالحة',
        ]);

        $customer->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Customer updated successfully',
            'data' => new CustomerResource($customer),
        ]);
    }

    public function customerDestroy(Customer $customer): JsonResponse
    {
        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Customer deleted successfully',
            'data' => null,
        ]);
    }

    public function customerShow(Customer $customer): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Customer retrieved successfully',
            'data' => new CustomerResource($customer),
        ]);
    }
}
