// resources/js/Composables/useWarehouse.js
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

export function useWarehouse() {
    const warehouses = ref([]);
    const loading = ref(false);
    const error = ref(null);

    /**
     * جلب جميع المستودعات
     * Fetch all warehouses
     */
    async function fetchWarehouses(params = {}) {
        loading.value = true;
        error.value = null;
        
        try {
            const response = await router.get('/api/v1/admin/wms/warehouses', params, {
                preserveState: true,
            });
            warehouses.value = response.props.warehouses.data;
        } catch (e) {
            error.value = e.message;
        } finally {
            loading.value = false;
        }
    }

    /**
     * جلب مستودع محدد
     * Fetch specific warehouse
     */
    async function fetchWarehouse(id) {
        loading.value = true;
        error.value = null;
        
        try {
            const response = await router.get(`/api/v1/admin/wms/warehouses/${id}`, {}, {
                preserveState: true,
            });
            return response.props.warehouse;
        } catch (e) {
            error.value = e.message;
            return null;
        } finally {
            loading.value = false;
        }
    }

    /**
     * إنشاء مستودع جديد
     * Create new warehouse
     */
    async function createWarehouse(data) {
        loading.value = true;
        error.value = null;
        
        try {
            await router.post('/api/v1/admin/wms/warehouses', data);
            return true;
        } catch (e) {
            error.value = e.message;
            return false;
        } finally {
            loading.value = false;
        }
    }

    /**
     * تحديث مستودع
     * Update warehouse
     */
    async function updateWarehouse(id, data) {
        loading.value = true;
        error.value = null;
        
        try {
            await router.put(`/api/v1/admin/wms/warehouses/${id}`, data);
            return true;
        } catch (e) {
            error.value = e.message;
            return false;
        } finally {
            loading.value = false;
        }
    }

    /**
     * حذف مستودع
     * Delete warehouse
     */
    async function deleteWarehouse(id) {
        loading.value = true;
        error.value = null;
        
        try {
            await router.delete(`/api/v1/admin/wms/warehouses/${id}`);
            return true;
        } catch (e) {
            error.value = e.message;
            return false;
        } finally {
            loading.value = false;
        }
    }

    /**
     * حساب نسبة الاستخدام
     * Calculate utilization percentage
     */
    function getUtilizationPercentage(warehouse) {
        if (!warehouse.capacity || warehouse.capacity <= 0) {
            return 0;
        }
        
        const totalStock = warehouse.inventory?.reduce((sum, inv) => sum + inv.quantity, 0) || 0;
        return (totalStock / warehouse.capacity) * 100;
    }

    /**
     * الحصول على نص نوع الموقع
     * Get location type text
     */
    function getLocationTypeText(type) {
        const types = {
            warehouse: 'مستودع',
            branch: 'فرع',
            distribution_center: 'مركز توزيع',
            '3pl': 'طرف ثالث',
        };
        return types[type] || type;
    }

    /**
     * تصفية المستودعات النشطة
     * Filter active warehouses
     */
    function getActiveWarehouses() {
        return warehouses.value.filter(w => w.is_active);
    }

    /**
     * الحصول على المستودع الرئيسي
     * Get primary warehouse
     */
    function getPrimaryWarehouse() {
        return warehouses.value.find(w => w.is_primary);
    }

    return {
        warehouses,
        loading,
        error,
        fetchWarehouses,
        fetchWarehouse,
        createWarehouse,
        updateWarehouse,
        deleteWarehouse,
        getUtilizationPercentage,
        getLocationTypeText,
        getActiveWarehouses,
        getPrimaryWarehouse,
    };
}
