<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ErpUpgradeService;
use App\Models\RmaRequest;
use App\Models\IntegrationSetting;
use App\Models\WarehouseBin;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ErpUpgradeController extends Controller
{
    protected $service;

    public function __construct(ErpUpgradeService $service)
    {
        $this->service = $service;
    }

    public function allocateLandedCost(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'shipping_charges' => 'required|numeric|min:0',
            'customs_duties' => 'required|numeric|min:0',
            'insurance_cost' => 'required|numeric|min:0',
            'other_charges' => 'required|numeric|min:0',
            'allocation_method' => 'required|in:value,quantity',
        ]);

        try {
            $landedCost = $this->service->allocateLandedCost(
                $id,
                (float) $validated['shipping_charges'],
                (float) $validated['customs_duties'],
                (float) $validated['insurance_cost'],
                (float) $validated['other_charges'],
                $validated['allocation_method']
            );

            return response()->json([
                'success' => true,
                'message' => 'تم توزيع التكاليف الإضافية بنجاح على البنود المستلمة.',
                'data' => $landedCost
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل تخصيص التكاليف الإضافية.',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function reserveInventory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $success = $this->service->reserveInventory(
            $validated['warehouse_id'],
            $validated['product_id'],
            $validated['product_variant_id'],
            $validated['quantity']
        );

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'فشل حجز المخزون: الكمية المتاحة في المستودع غير كافية.'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم حجز الكمية بنجاح.'
        ]);
    }

    public function releaseInventory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'quantity' => 'required|integer|min:1',
            'is_shipped' => 'boolean',
        ]);

        $success = $this->service->releaseInventory(
            $validated['warehouse_id'],
            $validated['product_id'],
            $validated['product_variant_id'],
            $validated['quantity'],
            $validated['is_shipped'] ?? false
        );

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'فشل فك الحجز: كمية الحجز المسجلة أقل من الكمية المطلوبة.'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم فك حجز الكمية بنجاح.'
        ]);
    }

    public function rmaIndex(): JsonResponse
    {
        $rmas = RmaRequest::with(['salesOrder', 'customer'])->get();
        return response()->json([
            'success' => true,
            'data' => $rmas
        ]);
    }

    public function rmaStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sales_order_id' => 'required|exists:sales_orders,id',
            'customer_id' => 'required|exists:customers,id',
            'reason' => 'nullable|string',
            'resolution_type' => 'required|string',
        ]);

        $rma = RmaRequest::create([
            'rma_number' => 'RMA-' . time() . '-' . rand(100, 999),
            'sales_order_id' => $validated['sales_order_id'],
            'customer_id' => $validated['customer_id'],
            'status' => 'requested',
            'reason' => $validated['reason'],
            'resolution_type' => $validated['resolution_type'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل طلب الإرجاع (RMA) بنجاح.',
            'data' => $rma
        ], 201);
    }

    public function rmaUpdateStatus(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string',
        ]);

        $rma = RmaRequest::findOrFail($id);
        $rma->update(['status' => $validated['status']]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث حالة طلب الإرجاع بنجاح.',
            'data' => $rma
        ]);
    }

    public function integrationIndex(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => IntegrationSetting::all()
        ]);
    }

    public function integrationStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel_name' => 'required|string',
            'api_domain' => 'required|string',
            'access_token' => 'required|string',
            'sync_stock' => 'boolean',
            'sync_orders' => 'boolean',
        ]);

        $integration = IntegrationSetting::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'تم حفظ إعدادات التكامل بنجاح.',
            'data' => $integration
        ], 201);
    }

    public function binIndex(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => WarehouseBin::with('warehouse')->get()
        ]);
    }

    public function binStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'bin_code' => 'required|string|unique:warehouse_bins,bin_code',
            'zone' => 'nullable|string',
            'rack' => 'nullable|string',
            'shelf' => 'nullable|string',
            'max_weight' => 'nullable|integer',
        ]);

        $bin = WarehouseBin::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء موقع الرف بنجاح.',
            'data' => $bin
        ], 201);
    }
}
