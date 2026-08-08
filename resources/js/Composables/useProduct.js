// resources/js/Composables/useProduct.js
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

export function useProduct() {
    const products = ref([]);
    const loading = ref(false);
    const error = ref(null);

    /**
     * جلب جميع المنتجات
     * Fetch all products
     */
    async function fetchProducts(params = {}) {
        loading.value = true;
        error.value = null;
        
        try {
            const response = await router.get('/api/v1/products', params, {
                preserveState: true,
            });
            products.value = response.props.products.data;
        } catch (e) {
            error.value = e.message;
        } finally {
            loading.value = false;
        }
    }

    /**
     * جلب منتج محدد
     * Fetch specific product
     */
    async function fetchProduct(id) {
        loading.value = true;
        error.value = null;
        
        try {
            const response = await router.get(`/api/v1/products/${id}`, {}, {
                preserveState: true,
            });
            return response.props.product;
        } catch (e) {
            error.value = e.message;
            return null;
        } finally {
            loading.value = false;
        }
    }

    /**
     * إنشاء منتج جديد
     * Create new product
     */
    async function createProduct(data) {
        loading.value = true;
        error.value = null;
        
        try {
            await router.post('/api/v1/products', data);
            return true;
        } catch (e) {
            error.value = e.message;
            return false;
        } finally {
            loading.value = false;
        }
    }

    /**
     * تحديث منتج
     * Update product
     */
    async function updateProduct(id, data) {
        loading.value = true;
        error.value = null;
        
        try {
            await router.put(`/api/v1/products/${id}`, data);
            return true;
        } catch (e) {
            error.value = e.message;
            return false;
        } finally {
            loading.value = false;
        }
    }

    /**
     * حذف منتج
     * Delete product
     */
    async function deleteProduct(id) {
        loading.value = true;
        error.value = null;
        
        try {
            await router.delete(`/api/v1/products/${id}`);
            return true;
        } catch (e) {
            error.value = e.message;
            return false;
        } finally {
            loading.value = false;
        }
    }

    /**
     * البحث عن منتجات
     * Search products
     */
    async function searchProducts(query) {
        loading.value = true;
        error.value = null;
        
        try {
            const response = await router.get('/api/v1/products/search', { q: query }, {
                preserveState: true,
            });
            return response.props.products;
        } catch (e) {
            error.value = e.message;
            return [];
        } finally {
            loading.value = false;
        }
    }

    /**
     * الحصول على إحصائيات الاستهلاك
     * Get consumption statistics
     */
    async function getConsumptionStats(productId) {
        loading.value = true;
        error.value = null;
        
        try {
            const response = await router.get(`/api/v1/products/${productId}/consumption-stats`, {}, {
                preserveState: true,
            });
            return response.props.stats;
        } catch (e) {
            error.value = e.message;
            return null;
        } finally {
            loading.value = false;
        }
    }

    /**
     * التحقق من حالة المنتج
     * Check product status
     */
    function getProductStatus(product) {
        if (!product) return { status: 'unknown', color: 'gray' };
        
        if (product.warehouses_count === 0) {
            return { status: 'unlinked', color: 'gray' };
        }
        
        if (product.total_balance <= product.min_stock) {
            return { status: 'low_stock', color: 'red' };
        }
        
        if (product.total_balance <= product.max_stock) {
            return { status: 'normal', color: 'green' };
        }
        
        return { status: 'overstock', color: 'orange' };
    }

    /**
     * تصفية المنتجات حسب الفئة
     * Filter products by category
     */
    function filterByCategory(categoryId) {
        return products.value.filter(p => p.category_id === categoryId);
    }

    /**
     * الحصول على المنتجات المرتبطة بمستودع
     * Get products linked to warehouse
     */
    function getProductsByWarehouse(warehouseId) {
        return products.value.filter(p => 
            p.warehouses?.some(w => w.id === warehouseId)
        );
    }

    return {
        products,
        loading,
        error,
        fetchProducts,
        fetchProduct,
        createProduct,
        updateProduct,
        deleteProduct,
        searchProducts,
        getConsumptionStats,
        getProductStatus,
        filterByCategory,
        getProductsByWarehouse,
    };
}
