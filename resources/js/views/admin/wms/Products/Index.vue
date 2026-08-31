<!-- resources/js/views/admin/wms/Products/Index.vue -->
<template>
    <div class="wms-products-page">
        <AdminPageHeader
            icon="fas fa-boxes-packing"
            :title="$t('product_management')"
            :subtitle="$t('wms_products_subtitle')"
        >
            <template #actions>
                <el-button plain :loading="refreshing" @click="refreshData">
                    <i class="fas fa-rotate"></i> {{ $t('update') }}
                </el-button>
                <el-button type="primary" @click="openAddModal">
                    <i class="fas fa-plus"></i> {{ $t('add_a_new_product') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <div class="stat-grid mb-3">
            <el-card shadow="never" class="stat-card stat-blue">
                <span class="stat-label">{{ $t('total_products') }}</span>
                <strong class="stat-value">{{ formatNumber(pagination.total) }}</strong>
            </el-card>
            <el-card shadow="never" class="stat-card stat-green">
                <span class="stat-label">{{ $t('linked_to_warehouse') }}</span>
                <strong class="stat-value">{{ formatNumber(stats.linked) }}</strong>
            </el-card>
            <el-card shadow="never" class="stat-card stat-gray">
                <span class="stat-label">{{ $t('unlinked_products') }}</span>
                <strong class="stat-value">{{ formatNumber(stats.unlinked) }}</strong>
            </el-card>
            <el-card shadow="never" class="stat-card stat-red">
                <span class="stat-label">{{ $t('low_stock') }}</span>
                <strong class="stat-value">{{ formatNumber(stats.lowStock) }}</strong>
            </el-card>
        </div>

        <el-card shadow="never" class="mb-3">
            <div class="filters-row">
                <el-input v-model="searchQuery" :placeholder="$t('search_by_code_or_name')" clearable style="max-width: 320px" @input="handleSearch">
                    <template #prefix><i class="fas fa-search"></i></template>
                </el-input>
                <el-select v-model="categoryFilter" :placeholder="$t('all_categories')" clearable style="width: 200px" @change="handleFilterChange">
                    <el-option v-for="cat in categories" :key="cat.id" :value="cat.id" :label="cat.name" />
                </el-select>
                <el-select v-model="statusFilter" :placeholder="$t('all_statuses')" clearable style="width: 200px" @change="handleFilterChange">
                    <el-option value="linked" :label="$t('linked_to_warehouse')" />
                    <el-option value="unlinked" :label="$t('not_linked')" />
                    <el-option value="low_stock" :label="$t('low_stock')" />
                </el-select>
            </div>
        </el-card>

        <el-card shadow="never">
            <div v-if="loading" class="loading-state"><el-skeleton :rows="6" animated /></div>
            <el-alert v-else-if="error" type="error" :title="error" show-icon :closable="false">
                <el-button size="small" @click="fetchProducts">{{ $t('retry') }}</el-button>
            </el-alert>
            <template v-else>
                <el-table v-if="filteredProducts.length" :data="filteredProducts" stripe>
                    <el-table-column :label="$t('status')" width="110" align="center">
                        <template #default="{ row }">
                            <el-tag :type="statusTagType(getProductStatus(row))" size="small">
                                {{ getProductStatus(row) === 'ok' ? $t('good') : getProductStatus(row) === 'low_stock' ? $t('low') : $t('not_linked') }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('code')" width="110">
                        <template #default="{ row }">{{ row.code }}</template>
                    </el-table-column>
                    <el-table-column :label="$t('name')" min-width="180">
                        <template #default="{ row }">
                            <strong>{{ row.name }}</strong>
                            <p class="row-sub">{{ row.sku }}</p>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('category')" width="130">
                        <template #default="{ row }">{{ row.category?.name || '—' }}</template>
                    </el-table-column>
                    <el-table-column :label="$t('unit')" width="90">
                        <template #default="{ row }">{{ row.unit || '—' }}</template>
                    </el-table-column>
                    <el-table-column :label="$t('warehouses')" width="110" align="center">
                        <template #default="{ row }">
                            {{ formatNumber(row.warehouses_count || 0) }}
                            <i v-if="row.warehouses_count > 0" class="fas fa-check text-success"></i>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('balance')" width="100" align="center">
                        <template #default="{ row }">
                            <strong :class="{ 'text-danger': row.total_balance <= row.min_stock, 'text-success': row.total_balance > row.min_stock }">
                                {{ formatNumber(row.total_balance || 0) }}
                            </strong>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('price')" width="100" align="center">
                        <template #default="{ row }">{{ formatPrice(row.price) }}</template>
                    </el-table-column>
                    <el-table-column :label="$t('actions')" width="220" align="center">
                        <template #default="{ row }">
                            <el-button-group>
                                <el-button size="small" plain :title="$t('view_details')" @click="goToProduct(row.id)">
                                    <i class="fas fa-eye"></i>
                                </el-button>
                                <el-button size="small" plain :title="$t('link_to_warehouse')" @click="goToAssignment(row.id)">
                                    <i class="fas fa-warehouse"></i>
                                </el-button>
                                <el-button size="small" type="danger" plain :title="$t('delete')" @click="deleteProduct(row.id)">
                                    <i class="fas fa-trash"></i>
                                </el-button>
                            </el-button-group>
                        </template>
                    </el-table-column>
                </el-table>

                <el-empty v-else :description="$t('no_products')" />

                <div v-if="pagination.total > pagination.per_page" class="pagination-row">
                    <el-pagination
                        layout="prev, pager, next, total"
                        :total="pagination.total"
                        :current-page="pagination.current_page"
                        :page-size="pagination.per_page"
                        background
                        @current-change="fetchProducts"
                    />
                </div>
            </template>
        </el-card>

        <!-- Add/Edit hint dialog — full product CRUD lives on the main product form -->
        <el-dialog v-model="showAddModal" :title="$t('add_a_new_product')" width="440px">
            <p>{{ $t('use_main_product_form_hint') }}</p>
            <template #footer>
                <el-button type="primary" @click="showAddModal = false">{{ $t('close') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { formatNumber as formatCount } from '@/utils/currency';
import { useI18n } from 'vue-i18n';
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import api from '@/api';
import { wmsService } from '@/services/wms';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';

const { t } = useI18n();
const router = useRouter();

const loading = ref(true);
const refreshing = ref(false);
const products = ref([]);
const categories = ref([]);
const searchQuery = ref('');
const categoryFilter = ref('');
const statusFilter = ref('');
const showAddModal = ref(false);
const error = ref(null);
const pagination = ref({ current_page: 1, per_page: 20, total: 0 });

// Counted across the whole catalog server-side would need its own endpoint;
// this is a fair approximation from the page in hand until that exists.
const stats = computed(() => {
    const linked = products.value.filter((p) => p.warehouses_count > 0).length;
    return {
        linked,
        unlinked: products.value.length - linked,
        lowStock: products.value.filter((p) => p.total_balance <= p.min_stock).length,
    };
});

async function fetchProducts(page = 1) {
    loading.value = true;
    error.value = null;

    try {
        const params = { page, per_page: pagination.value.per_page };
        if (searchQuery.value) params.search = searchQuery.value;
        if (categoryFilter.value) params.category = categoryFilter.value;

        const response = await wmsService.getProducts(params);
        products.value = response.data?.data || [];
        pagination.value = {
            current_page: response.data?.current_page || 1,
            per_page: response.data?.per_page || pagination.value.per_page,
            total: response.data?.total || 0,
        };
    } catch (err) {
        error.value = err.response?.data?.message || err.message || t('failed_to_fetch_data_short');
        ElMessage.error(error.value);
    } finally {
        loading.value = false;
    }
}

async function fetchCategories() {
    try {
        const response = await api.get('/categories');
        categories.value = response.data?.data || [];
    } catch {
        ElMessage.warning(t('failed_to_fetch_categories_short'));
    }
}

async function refreshData() {
    refreshing.value = true;
    await Promise.all([fetchProducts(pagination.value.current_page), fetchCategories()]);
    refreshing.value = false;
    ElMessage.success(t('data_updated_successfully'));
}

onMounted(() => {
    fetchProducts();
    fetchCategories();
});

let searchTimeout;
function handleSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => fetchProducts(1), 300);
}

function handleFilterChange() {
    fetchProducts(1);
}

function getProductStatus(product) {
    if (!product) return 'error';
    const warehousesCount = Number(product.warehouses_count) || 0;
    const totalBalance = Number(product.total_balance) || 0;
    const minStock = Number(product.min_stock) || 0;
    if (warehousesCount === 0) return 'unlinked';
    if (totalBalance <= minStock) return 'low_stock';
    return 'ok';
}

function statusTagType(status) {
    return { ok: 'success', low_stock: 'danger', unlinked: 'info' }[status] || 'info';
}

// Client-side status filter — the count above the table (and the paginator)
// still reflect the server-side search/category filters, since status is
// derived per row rather than stored.
const filteredProducts = computed(() => {
    if (!statusFilter.value) return products.value;
    if (statusFilter.value === 'linked') return products.value.filter((p) => p.warehouses_count > 0);
    if (statusFilter.value === 'unlinked') return products.value.filter((p) => p.warehouses_count === 0);
    if (statusFilter.value === 'low_stock') return products.value.filter((p) => p.total_balance <= p.min_stock);
    return products.value;
});

function openAddModal() {
    showAddModal.value = true;
}

function goToAssignment(productId) {
    router.push(`/admin/wms/products/${productId}/assign`);
}

function goToProduct(productId) {
    // There is no WMS-specific product detail screen — the main product
    // record (edited in full elsewhere) is the real destination.
    router.push(`/admin/products/${productId}`);
}

async function deleteProduct(productId) {
    try {
        await ElMessageBox.confirm(t('confirm_delete_product'), t('confirm_deletion'), {
            confirmButtonText: t('yes_delete'),
            cancelButtonText: t('cancel'),
            type: 'warning',
            confirmButtonClass: 'el-button--danger',
        });
    } catch {
        return;
    }

    try {
        // No WMS-specific delete endpoint exists — this removes the product
        // record itself, same as the main product list's delete action.
        await api.delete(`/admin/products/${productId}`);
        ElMessage.success(t('product_deleted'));
        await fetchProducts(pagination.value.current_page);
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('failed_to_delete_product'));
    }
}

function formatNumber(num) {
    if (num === null || num === undefined) return '—';
    return formatCount(num);
}

function formatPrice(price) {
    if (price === null || price === undefined) return '—';
    return Number(price).toFixed(2);
}
</script>

<style scoped>
.wms-products-page { font-family: 'Cairo', sans-serif; }

.stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; }
.stat-card { border-radius: 12px; border-inline-start: 4px solid var(--el-color-info); }
.stat-card :deep(.el-card__body) { display: flex; flex-direction: column; gap: 0.3rem; }
.stat-blue { border-inline-start-color: var(--el-color-primary); }
.stat-green { border-inline-start-color: var(--el-color-success); }
.stat-gray { border-inline-start-color: var(--el-color-info); }
.stat-red { border-inline-start-color: var(--el-color-danger); }
.stat-label { font-size: 0.8rem; color: var(--text-muted); }
.stat-value { font-size: 1.5rem; font-weight: 800; }

.filters-row { display: flex; gap: 0.75rem; flex-wrap: wrap; }

.row-sub { margin: 0.15rem 0 0; font-size: 0.76rem; color: var(--text-muted); }
.text-success { color: var(--el-color-success); }
.text-danger { color: var(--el-color-danger); }

.pagination-row { display: flex; justify-content: flex-end; padding-top: 1rem; }
.loading-state { padding: 2rem; }
.mb-3 { margin-bottom: 0.75rem; }
</style>
