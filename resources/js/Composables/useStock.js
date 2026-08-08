// resources/js/Composables/useStock.js
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

export function useStock() {
    const balances = ref([]);
    const movements = ref([]);
    const loading = ref(false);
    const error = ref(null);

    /**
     * جلب الأرصدة
     * Fetch stock balances
     */
    async function fetchBalances(params = {}) {
        loading.value = true;
        error.value = null;
        
        try {
            const response = await router.get('/api/v1/stock/balances', params, {
                preserveState: true,
            });
            balances.value = response.props.balances;
        } catch (e) {
            error.value = e.message;
        } finally {
            loading.value = false;
        }
    }

    /**
     * جلب رصيد منتج محدد في مستودع محدد
     * Fetch balance for specific product in specific warehouse
     */
    async function fetchBalance(productId, warehouseId) {
        loading.value = true;
        error.value = null;
        
        try {
            const response = await router.get(`/api/v1/stock/balances/${productId}/${warehouseId}`, {}, {
                preserveState: true,
            });
            return response.props.balance;
        } catch (e) {
            error.value = e.message;
            return null;
        } finally {
            loading.value = false;
        }
    }

    /**
     * جلب الحركات
     * Fetch movements
     */
    async function fetchMovements(params = {}) {
        loading.value = true;
        error.value = null;
        
        try {
            const response = await router.get('/api/v1/stock/movements', params, {
                preserveState: true,
            });
            movements.value = response.props.movements;
        } catch (e) {
            error.value = e.message;
        } finally {
            loading.value = false;
        }
    }

    /**
     * إضافة حركة مخزنية
     * Add stock movement
     */
    async function addMovement(data) {
        loading.value = true;
        error.value = null;
        
        try {
            await router.post('/api/v1/stock/movements', data);
            return true;
        } catch (e) {
            error.value = e.message;
            return false;
        } finally {
            loading.value = false;
        }
    }

    /**
     * تحديث حالة الرصيد
     * Update balance status
     */
    function getBalanceStatus(balance) {
        const { available_quantity, min_stock, safety_stock } = balance;
        
        if (available_quantity <= safety_stock) {
            return { color: 'red', text: 'منخفض جداً', icon: '⚠' };
        } else if (available_quantity <= min_stock) {
            return { color: 'orange', text: 'منخفض', icon: '⚠' };
        } else {
            return { color: 'green', text: 'جيد', icon: '✓' };
        }
    }

    /**
     * التحقق من إمكانية الصرف
     * Check if withdrawal is possible
     */
    function canWithdraw(balance, quantity) {
        return balance.available_quantity >= quantity;
    }

    /**
     * حساب الرصيد المتوقع بعد الحركة
     * Calculate expected balance after movement
     */
    function calculateExpectedBalance(balance, movementType, quantity) {
        if (movementType === 'in' || movementType === 'adjustment') {
            return balance.available_quantity + quantity;
        } else if (movementType === 'out') {
            return balance.available_quantity - quantity;
        }
        return balance.available_quantity;
    }

    return {
        balances,
        movements,
        loading,
        error,
        fetchBalances,
        fetchBalance,
        fetchMovements,
        addMovement,
        getBalanceStatus,
        canWithdraw,
        calculateExpectedBalance,
    };
}
