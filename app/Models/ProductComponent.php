<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_product_id',
        'component_product_id',
        'quantity_required',
        'is_optional',
        'notes',
    ];

    protected $casts = [
        'quantity_required' => 'integer',
        'is_optional' => 'boolean',
    ];

    public function parentProduct()
    {
        return $this->belongsTo(Product::class, 'parent_product_id');
    }

    public function componentProduct()
    {
        return $this->belongsTo(Product::class, 'component_product_id');
    }

    public function scopeRequired($query)
    {
        return $query->where('is_optional', false);
    }

    public function scopeOptional($query)
    {
        return $query->where('is_optional', true);
    }

    public function scopeForParent($query, $productId)
    {
        return $query->where('parent_product_id', $productId);
    }

    public function scopeForComponent($query, $productId)
    {
        return $query->where('component_product_id', $productId);
    }

    /**
     * Get all warehouse assignments for this component
     */
    public function getComponentWarehouseAssignments()
    {
        return $this->componentProduct->warehouseAssignments()->active();
    }

    /**
     * Check if all required components are available in a specific warehouse
     */
    public function isAvailableInWarehouse($warehouseId, $requiredQuantity = 1): bool
    {
        $componentAssignments = $this->componentProduct->warehouseAssignments()
            ->active()
            ->forWarehouse($warehouseId)
            ->get();

        if ($componentAssignments->isEmpty()) {
            return false;
        }

        $totalNeeded = $this->quantity_required * $requiredQuantity;
        $totalAvailable = $componentAssignments->sum('available_stock');

        return $totalAvailable >= $totalNeeded;
    }

    /**
     * Get the best warehouse to fulfill this component requirement
     * based on stock availability and geographic proximity to customer
     */
    public function getBestWarehouseForComponent($customerLatitude = null, $customerLongitude = null): ?ProductWarehouseAssignment
    {
        $assignments = $this->componentProduct->warehouseAssignments()
            ->active()
            ->where('available_stock', '>=', $this->quantity_required)
            ->get();

        if ($assignments->isEmpty()) {
            return null;
        }

        // If no customer location provided, return warehouse with most stock
        if (!$customerLatitude || !$customerLongitude) {
            return $assignments->sortByDesc('available_stock')->first();
        }

        // Calculate distance to customer for each warehouse
        return $assignments->map(function ($assignment) use ($customerLatitude, $customerLongitude) {
            $distance = $this->calculateDistanceToCustomer(
                $assignment->warehouse,
                $customerLatitude,
                $customerLongitude
            );
            $assignment->distance_to_customer = $distance;
            return $assignment;
        })->sortBy('distance_to_customer')->first();
    }

    private function calculateDistanceToCustomer($warehouse, $customerLat, $customerLng): float
    {
        if (!$warehouse->latitude || !$warehouse->longitude) {
            return PHP_FLOAT_MAX;
        }

        $earthRadius = 6371; // km
        $latFrom = deg2rad($warehouse->latitude);
        $lonFrom = deg2rad($warehouse->longitude);
        $latTo = deg2rad($customerLat);
        $lonTo = deg2rad($customerLng);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos($latFrom) * cos($latTo) *
             sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
