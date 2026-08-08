// resources/js/Stores/warehouseStore.js
import { defineStore } from 'pinia';
import { ref } from 'vue';

export const useWarehouseStore = defineStore('warehouse', () => {
    const warehouses = ref([]);
    const activeWarehouses = ref([]);
    const primaryWarehouse = ref(null);
    const loading = ref(false);

    /**
     * تعيين قائمة المستودعات
     * Set warehouses list
     */
    function setWarehouses(warehouseList) {
        warehouses.value = warehouseList;
        activeWarehouses.value = warehouseList.filter(w => w.is_active);
        primaryWarehouse.value = warehouseList.find(w => w.is_primary);
    }

    /**
     * إضافة مستودع
     * Add warehouse
     */
    function addWarehouse(warehouse) {
        warehouses.value.push(warehouse);
        if (warehouse.is_active) {
            activeWarehouses.value.push(warehouse);
        }
        if (warehouse.is_primary) {
            primaryWarehouse.value = warehouse;
        }
    }

    /**
     * تحديث مستودع
     * Update warehouse
     */
    function updateWarehouse(warehouse) {
        const index = warehouses.value.findIndex(w => w.id === warehouse.id);
        if (index !== -1) {
            warehouses.value[index] = warehouse;
            
            // تحديث القوائم الفرعية
            // Update sub-lists
            if (warehouse.is_active) {
                const activeIndex = activeWarehouses.value.findIndex(w => w.id === warehouse.id);
                if (activeIndex !== -1) {
                    activeWarehouses.value[activeIndex] = warehouse;
                } else {
                    activeWarehouses.value.push(warehouse);
                }
            } else {
                activeWarehouses.value = activeWarehouses.value.filter(w => w.id !== warehouse.id);
            }
            
            if (warehouse.is_primary) {
                primaryWarehouse.value = warehouse;
            } else if (primaryWarehouse.value?.id === warehouse.id) {
                primaryWarehouse.value = null;
            }
        }
    }

    /**
     * حذف مستودع
     * Delete warehouse
     */
    function removeWarehouse(id) {
        warehouses.value = warehouses.value.filter(w => w.id !== id);
        activeWarehouses.value = activeWarehouses.value.filter(w => w.id !== id);
        if (primaryWarehouse.value?.id === id) {
            primaryWarehouse.value = null;
        }
    }

    /**
     * الحصول على مستودع بالمعرف
     * Get warehouse by ID
     */
    function getWarehouseById(id) {
        return warehouses.value.find(w => w.id === id);
    }

    /**
     * الحصول على المستودعات حسب النوع
     * Get warehouses by type
     */
    function getWarehousesByType(type) {
        return warehouses.value.filter(w => w.location_type === type);
    }

    /**
     * الحصول على المستودعات النشطة
     * Get active warehouses
     */
    function getActiveWarehouses() {
        return activeWarehouses.value;
    }

    /**
     * الحصول على المستودع الرئيسي
     * Get primary warehouse
     */
    function getPrimaryWarehouse() {
        return primaryWarehouse.value;
    }

    /**
     * تعيين المستودع الرئيسي
     * Set primary warehouse
     */
    function setPrimaryWarehouse(id) {
        warehouses.value.forEach(w => {
            w.is_primary = w.id === id;
        });
        primaryWarehouse.value = getWarehouseById(id);
    }

    /**
     * حساب إجمالي السعة
     * Calculate total capacity
     */
    function getTotalCapacity() {
        return warehouses.value.reduce((sum, w) => sum + (w.capacity || 0), 0);
    }

    /**
     * حساب إجمالي الاستخدام
     * Calculate total utilization
     */
    function getTotalUtilization() {
        return warehouses.value.reduce((sum, w) => {
            const utilization = getUtilizationPercentage(w);
            return sum + utilization;
        }, 0) / (warehouses.value.length || 1);
    }

    /**
     * حساب نسبة استخدام مستودع
     * Calculate warehouse utilization percentage
     */
    function getUtilizationPercentage(warehouse) {
        if (!warehouse.capacity || warehouse.capacity <= 0) {
            return 0;
        }
        
        const totalStock = warehouse.inventory?.reduce((sum, inv) => sum + inv.quantity, 0) || 0;
        return (totalStock / warehouse.capacity) * 100;
    }

    /**
     * مسح البيانات
     * Clear data
     */
    function clear() {
        warehouses.value = [];
        activeWarehouses.value = [];
        primaryWarehouse.value = null;
    }

    return {
        warehouses,
        activeWarehouses,
        primaryWarehouse,
        loading,
        setWarehouses,
        addWarehouse,
        updateWarehouse,
        removeWarehouse,
        getWarehouseById,
        getWarehousesByType,
        getActiveWarehouses,
        getPrimaryWarehouse,
        setPrimaryWarehouse,
        getTotalCapacity,
        getTotalUtilization,
        getUtilizationPercentage,
        clear,
    };
});
