<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Inventory\CompositeProductService;
use Illuminate\Http\Request;

class CompositeProductController extends Controller
{
    private CompositeProductService $compositeService;

    public function __construct(CompositeProductService $compositeService)
    {
        $this->compositeService = $compositeService;
    }

    /**
     * Check if a product is composite
     */
    public function isComposite($productId)
    {
        $isComposite = $this->compositeService->isCompositeProduct($productId);

        return response()->json([
            'success' => true,
            'data' => [
                'product_id' => $productId,
                'is_composite' => $isComposite,
            ],
        ]);
    }

    /**
     * Get product components
     */
    public function getComponents($productId)
    {
        $components = $this->compositeService->getProductComponents($productId);

        return response()->json([
            'success' => true,
            'data' => $components,
        ]);
    }

    /**
     * Check if product can be assembled
     */
    public function canAssemble(Request $request, $productId)
    {
        $quantity = $request->get('quantity', 1);
        $warehouseId = $request->get('warehouse_id');

        $availability = $this->compositeService->canAssembleProduct($productId, $quantity, $warehouseId);

        return response()->json([
            'success' => true,
            'data' => $availability,
        ]);
    }

    /**
     * Get best warehouse for assembly
     */
    public function getBestWarehouseForAssembly(Request $request, $productId)
    {
        $quantity = $request->get('quantity', 1);
        $customerLatitude = $request->get('customer_latitude');
        $customerLongitude = $request->get('customer_longitude');

        $bestWarehouse = $this->compositeService->getBestWarehouseForAssembly(
            $productId,
            $quantity,
            $customerLatitude,
            $customerLongitude
        );

        if (!$bestWarehouse) {
            return response()->json([
                'success' => false,
                'message' => 'لا يوجد مستودع متاح للتجميع',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $bestWarehouse,
        ]);
    }

    /**
     * Create assembly order
     */
    public function createAssemblyOrder(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'warehouse_id' => 'required|exists:warehouses,id',
            'assembly_area_id' => 'nullable|exists:warehouse_bins,id',
        ]);

        $result = $this->compositeService->createAssemblyOrder(
            $validated['product_id'],
            $validated['quantity'],
            $validated['warehouse_id'],
            $validated['assembly_area_id'] ?? null
        );

        return response()->json($result);
    }

    /**
     * Complete assembly order
     */
    public function completeAssemblyOrder($assemblyOrderId)
    {
        $result = $this->compositeService->completeAssemblyOrder($assemblyOrderId);

        return response()->json($result);
    }

    /**
     * Disassemble product
     */
    public function disassemble(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'warehouse_id' => 'required|exists:warehouses,id',
        ]);

        $result = $this->compositeService->disassembleProduct(
            $validated['product_id'],
            $validated['quantity'],
            $validated['warehouse_id']
        );

        return response()->json($result);
    }

    /**
     * Get bill of materials (BOM)
     */
    public function getBillOfMaterials($productId)
    {
        $bom = $this->compositeService->getBillOfMaterials($productId);

        return response()->json([
            'success' => true,
            'data' => $bom,
        ]);
    }

    /**
     * Calculate composite product cost
     */
    public function calculateCost($productId)
    {
        $cost = $this->compositeService->calculateCompositeProductCost($productId);

        return response()->json([
            'success' => true,
            'data' => $cost,
        ]);
    }

    /**
     * Update component quantities
     */
    public function updateComponents(Request $request, $productId)
    {
        $validated = $request->validate([
            'components' => 'required|array',
            'components.*.component_id' => 'required|exists:product_components,id',
            'components.*.quantity' => 'required|integer|min:1',
            'components.*.is_optional' => 'boolean',
            'components.*.notes' => 'nullable|string',
        ]);

        $result = $this->compositeService->updateComponentQuantities($productId, $validated['components']);

        return response()->json($result);
    }

    /**
     * Get assembly orders
     */
    public function getAssemblyOrders(Request $request)
    {
        $productId = $request->get('product_id');
        $warehouseId = $request->get('warehouse_id');
        $status = $request->get('status');

        $query = \DB::table('product_assembly_orders')
            ->join('products', 'product_assembly_orders.parent_product_id', '=', 'products.id')
            ->join('warehouses', 'product_assembly_orders.warehouse_id', '=', 'warehouses.id')
            ->select([
                'product_assembly_orders.*',
                'products.name as product_name',
                'warehouses.name as warehouse_name',
            ]);

        if ($productId) {
            $query->where('product_assembly_orders.parent_product_id', $productId);
        }

        if ($warehouseId) {
            $query->where('product_assembly_orders.warehouse_id', $warehouseId);
        }

        if ($status) {
            $query->where('product_assembly_orders.status', $status);
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Get assembly order details
     */
    public function getAssemblyOrder($id)
    {
        $order = \DB::table('product_assembly_orders')
            ->where('product_assembly_orders.id', $id)
            ->join('products', 'product_assembly_orders.parent_product_id', '=', 'products.id')
            ->join('warehouses', 'product_assembly_orders.warehouse_id', '=', 'warehouses.id')
            ->select([
                'product_assembly_orders.*',
                'products.name as product_name',
                'warehouses.name as warehouse_name',
            ])
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'أمر التجميع غير موجود',
            ], 404);
        }

        $items = \DB::table('product_assembly_order_items')
            ->where('assembly_order_id', $id)
            ->join('products', 'product_assembly_order_items.component_product_id', '=', 'products.id')
            ->select([
                'product_assembly_order_items.*',
                'products.name as component_name',
                'products.sku as component_sku',
            ])
            ->get();

        $order->items = $items;

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }
}
