<?php

namespace App\Http\Controllers\Api\Field;

use App\Http\Controllers\Controller;
use App\Models\SalesOrder;
use App\Models\WarehouseInventory;
use App\Services\Field\FieldScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What the field app needs on launch: who is signed in, which warehouses they
 * may work on, and a summary of the work waiting for them there.
 *
 * Kept as one call so the app has everything it needs to draw its home screen
 * without a burst of requests on a phone connection.
 */
class FieldSessionController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $scope = FieldScope::for($request->user());
        $warehouseIds = $scope->warehouseIds();

        return response()->json([
            'success' => true,
            'data' => [
                'profile' => $scope->profile(),
                'warehouses' => $scope->warehouses()->map(fn ($w) => [
                    'id' => $w->id,
                    'name' => $w->name,
                    'code' => $w->code,
                    'location_type' => $w->location_type,
                    'location_type_text' => $w->location_type_text,
                    'is_home' => $w->id === $scope->homeWarehouseId(),
                ])->values(),
                'summary' => $this->summary($warehouseIds, $scope),
            ],
        ]);
    }

    /**
     * The counters the home screen shows. Scoped to the warehouses in reach, so
     * a rep sees their own workload rather than the company's.
     *
     * @param  array<int,int>  $warehouseIds
     */
    private function summary(array $warehouseIds, FieldScope $scope): array
    {
        if ($warehouseIds === []) {
            return ['open_orders' => 0, 'awaiting_confirmation' => 0, 'ready_to_ship' => 0,
                'my_open_orders' => 0, 'overdue_orders' => 0, 'low_stock_items' => 0, 'out_of_stock_items' => 0];
        }

        $base = fn () => SalesOrder::whereIn('fulfillment_warehouse_id', $warehouseIds);
        $open = [SalesOrder::STATUS_PENDING, SalesOrder::STATUS_CONFIRMED, SalesOrder::STATUS_PROCESSING, SalesOrder::STATUS_SHIPPED];

        $available = WarehouseInventory::availableSql();

        $stock = WarehouseInventory::whereIn('warehouse_id', $warehouseIds)
            ->selectRaw("SUM(CASE WHEN ({$available}) <= 0 THEN 1 ELSE 0 END) as out_of_stock")
            ->selectRaw("SUM(CASE WHEN ({$available}) > 0 AND ({$available}) <= COALESCE(reorder_point, 0) THEN 1 ELSE 0 END) as low_stock")
            ->first();

        return [
            'open_orders' => (clone $base)()->whereIn('status', $open)->count(),
            'awaiting_confirmation' => (clone $base)()->where('status', SalesOrder::STATUS_PENDING)->count(),
            'ready_to_ship' => (clone $base)()->where('status', SalesOrder::STATUS_PROCESSING)->count(),
            'my_open_orders' => $scope->employee()
                ? (clone $base)()->where('assigned_employee_id', $scope->employee()->id)->whereIn('status', $open)->count()
                : 0,
            // Past the date promised to the customer and still not delivered.
            'overdue_orders' => (clone $base)()
                ->whereIn('status', $open)
                ->whereNotNull('expected_delivery')
                ->whereDate('expected_delivery', '<', now()->toDateString())
                ->count(),
            'low_stock_items' => (int) ($stock->low_stock ?? 0),
            'out_of_stock_items' => (int) ($stock->out_of_stock ?? 0),
        ];
    }
}
