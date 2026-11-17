<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        // Order forms pick a line per variant ("floor drain - 4\""); the till
        // and older callers keep one row per product.
        $expand = $request->boolean('expand_variants');

        $products = Product::query()
            ->when($expand, fn ($q) => $q->withVariantRows())
            ->where('products.is_active', 1)
            ->with('category');

        if ($sku) {
            $products->where(function ($q) use ($sku, $expand) {
                $q->where('products.sku', $sku);
                if ($expand) {
                    $q->orWhere('pv.sku', $sku);
                }
            });
        }

        if ($query) {
            $columns = [
                'products.name_ar', 'products.name_en', 'products.brand',
                'products.model', 'products.sku', 'products.barcode',
            ];
            if ($expand) {
                $columns = [...$columns, 'pv.sku', 'pv.barcode', 'pv.size', 'pv.color'];
            }
            $products->whereSearch($columns, $query);
        }

        // An order form narrows its search to one category, or browses it. A
        // section covers the products filed under its subcategories too.
        if ($request->filled('category_id')) {
            $category = Category::find($request->integer('category_id'));
            $products->whereIn('products.category_id', $category ? $category->descendantIds() : [0]);
        }

        if ($expand) {
            $products->orderBy('products.name_ar')->orderBy('products.id')->orderBy('pv.id');
        }

        $products = $products->limit(50)->get();

        $data = ProductResource::collection($products);

        // Order forms ask where each result can ship from. Opt-in, so the till
        // and other callers keep their payload (and query count) unchanged.
        if ($request->boolean('with_warehouses') && $products->isNotEmpty()) {
            $rows = $data->resolve($request);
            $stock = $this->warehouseStockFor($products->pluck('id')->unique()->all());

            foreach ($rows as &$row) {
                $byVariant = $stock[$row['id']] ?? [];
                // Variant stock is kept per variant only where the warehouse
                // tracks it; otherwise the product-level rows are the truth.
                $row['warehouse_stock'] = array_values(
                    ($row['variant_id'] ?? null) && isset($byVariant[$row['variant_id']])
                        ? $byVariant[$row['variant_id']]
                        : ($byVariant[0] ?? [])
                );
            }
            unset($row);
            $data = $rows;
        }

        return response()->json([
            'success' => true,
            'message' => 'POS product lookup completed successfully',
            'data' => $data,
        ]);
    }

    /**
     * Sellable quantity per active warehouse, for a set of products.
     *
     * Sellable = available bucket minus what confirmed orders hold — the same
     * figure InventoryService checks before it lets stock leave. Bins of one
     * warehouse are summed. Shape: [product_id][variant_id|0] => list of
     * {warehouse_id, name, available}, largest first; empty warehouses omitted.
     *
     * @param  array<int,int>  $productIds
     * @return array<int,array<int,array<int,array{warehouse_id:int,name:string,available:int}>>>
     */
    private function warehouseStockFor(array $productIds): array
    {
        $rows = DB::table('warehouse_inventory as wi')
            ->join('warehouses as w', 'w.id', '=', 'wi.warehouse_id')
            ->whereIn('wi.product_id', $productIds)
            ->where('w.is_active', 1)
            ->groupBy('wi.product_id', 'wi.product_variant_id', 'wi.warehouse_id', 'w.name')
            ->selectRaw('wi.product_id, wi.product_variant_id, wi.warehouse_id, w.name,
                SUM(wi.available_quantity - wi.reserved_quantity) as available')
            ->havingRaw('SUM(wi.available_quantity - wi.reserved_quantity) > 0')
            ->orderByDesc('available')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->product_id][(int) ($r->product_variant_id ?? 0)][] = [
                'warehouse_id' => (int) $r->warehouse_id,
                'name' => $r->name,
                'available' => (int) $r->available,
            ];
        }

        return $out;
    }

    /**
     * List POS customers with optional search.
     */
    public function customers(Request $request): JsonResponse
    {
        $query = Customer::query();

        // A sales rep reaches only the customers they serve, on every screen
        // that lists clients (the `mine` flag the quote form sends is now moot).
        $employeeId = $this->repEmployeeId($request);
        if ($employeeId !== null) {
            $query->servedBy($employeeId);
        }

        if ($request->filled('search')) {
            $query->whereSearch(['name', 'phone', 'email', 'company'], $request->search);
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
            'credit_limit' => 'nullable|numeric|min:0',
            'tax_number' => 'nullable|string|max:50',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'currency' => 'nullable|string|max:10',
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

        $employeeId = $this->repEmployeeId($request);

        if ($customer) {
            // Saving matches by phone/email; a rep must not overwrite another
            // rep's client that happens to share them.
            $this->ensureServes($employeeId, $customer);
            $customer->update($validated);
        } else {
            $customer = Customer::create($validated);

            // A customer a rep brings in is theirs to serve and quote.
            if ($employeeId) {
                $customer->assignEmployee($employeeId);
            }
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
            'credit_limit' => 'nullable|numeric|min:0',
            'tax_number' => 'nullable|string|max:50',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'currency' => 'nullable|string|max:10',
        ], [
            'name.required' => 'الاسم مطلوب',
            'email.email' => 'البريد الإلكتروني غير صحيح',
            'status.in' => 'الحالة غير صالحة',
        ]);

        $this->ensureServes($this->repEmployeeId($request), $customer);
        $customer->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Customer updated successfully',
            'data' => new CustomerResource($customer),
        ]);
    }

    public function customerDestroy(Request $request, Customer $customer): JsonResponse
    {
        $this->ensureServes($this->repEmployeeId($request), $customer);
        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Customer deleted successfully',
            'data' => null,
        ]);
    }

    public function customerShow(Request $request, Customer $customer): JsonResponse
    {
        $this->ensureServes($this->repEmployeeId($request), $customer);

        return response()->json([
            'success' => true,
            'message' => 'Customer retrieved successfully',
            'data' => new CustomerResource($customer),
        ]);
    }

    /**
     * The employee a sales-scoped user acts as (0 when they have no employee
     * record, which matches no customer); null for admins and managers.
     */
    private function repEmployeeId(Request $request): ?int
    {
        $user = $request->user();
        if (! $user?->isSalesScoped()) {
            return null;
        }

        return (int) ($user->employee?->id
            ?? \App\Models\Employee::where('user_id', $user->id)->value('id')
            ?? 0);
    }

    private function ensureServes(?int $employeeId, Customer $customer): void
    {
        if ($employeeId !== null && ! Customer::servedBy($employeeId)->whereKey($customer->id)->exists()) {
            abort(403, 'هذا العميل غير مسند إليك');
        }
    }
}
