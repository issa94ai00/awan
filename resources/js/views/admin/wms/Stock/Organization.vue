<!-- resources/js/views/admin/wms/Stock/Organization.vue -->
<template>
    <div class="organization-page">
        <AdminPageHeader
            icon="fas fa-diagram-project"
            :title="$t('stock_organization')"
            :subtitle="$t('stock_organization_subtitle')"
        >
            <template #actions>
                <el-input v-model="searchQuery" :placeholder="$t('search_by_name_or_code')" clearable style="width: 240px">
                    <template #prefix><i class="fas fa-search"></i></template>
                </el-input>
                <el-button plain :loading="refreshing" @click="refreshData">
                    <i class="fas fa-rotate"></i> {{ $t('update') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <el-alert v-if="error" type="error" :title="error" show-icon :closable="false" class="mb-3" />

        <div class="stat-grid mb-3">
            <el-card shadow="never" class="stat-card stat-blue">
                <span class="stat-label">{{ $t('total_products') }}</span>
                <strong class="stat-value">{{ formatNumber(stats.total) }}</strong>
            </el-card>
            <el-card shadow="never" class="stat-card stat-green">
                <span class="stat-label">{{ $t('linked_products') }}</span>
                <strong class="stat-value">{{ formatNumber(stats.assigned) }}</strong>
            </el-card>
            <el-card shadow="never" class="stat-card stat-yellow">
                <span class="stat-label">{{ $t('unlinked_products') }}</span>
                <strong class="stat-value">{{ formatNumber(stats.unassigned) }}</strong>
            </el-card>
        </div>

        <div v-if="loading" class="loading-state"><el-skeleton :rows="8" animated /></div>

        <div v-else class="columns-grid">
            <el-card shadow="never" class="column-card">
                <template #header>
                    <span class="column-header column-header-warning">
                        {{ $t('unlinked_products') }}
                        <el-tag type="warning" size="small">{{ formatNumber(filteredUnassigned.length) }}</el-tag>
                    </span>
                </template>
                <div class="column-body">
                    <el-empty v-if="!filteredUnassigned.length" :description="$t('no_unlinked_products')" />
                    <div v-for="product in filteredUnassigned" :key="product.id" class="product-row">
                        <div class="product-info">
                            <strong>{{ product.name }}</strong>
                            <p class="product-sub">{{ $t('code_label') }} {{ product.code }} · {{ product.category?.name || '—' }}</p>
                        </div>
                        <div class="product-actions">
                            <el-button size="small" type="primary" @click="openAssignModal(product)">{{ $t('link_action') }}</el-button>
                            <el-button size="small" plain @click="goToAssignment(product.id)">{{ $t('details') }}</el-button>
                        </div>
                    </div>
                </div>
            </el-card>

            <el-card shadow="never" class="column-card">
                <template #header>
                    <span class="column-header column-header-success">
                        {{ $t('linked_products') }}
                        <el-tag type="success" size="small">{{ formatNumber(filteredAssigned.length) }}</el-tag>
                    </span>
                </template>
                <div class="column-body">
                    <el-empty v-if="!filteredAssigned.length" :description="$t('no_linked_products')" />
                    <div v-for="product in filteredAssigned" :key="product.id" class="product-row clickable" @click="goToProductDetails(product.id)">
                        <div class="product-info">
                            <strong>{{ product.name }}</strong>
                            <p class="product-sub">
                                {{ $t('code_label') }} {{ product.code }} ·
                                {{ $t('warehouses') }}: {{ formatNumber(product.warehouses_count) }} ·
                                {{ $t('balance') }}: {{ formatNumber(product.total_balance) }}
                            </p>
                        </div>
                        <div class="product-actions">
                            <el-button size="small" plain @click.stop="goToAssignment(product.id)">{{ $t('edit_action') }}</el-button>
                        </div>
                    </div>
                </div>
            </el-card>
        </div>

        <!-- Assign Dialog -->
        <el-dialog v-model="showAssignModal" :title="$t('link_product_to_warehouse')" width="440px">
            <div v-if="selectedProduct" class="assign-summary">
                <p><strong>{{ $t('product_label') }}</strong> {{ selectedProduct.name }}</p>
                <p><strong>{{ $t('code_label') }}</strong> {{ selectedProduct.code }}</p>
            </div>
            <el-form-item :label="$t('choose_warehouse')">
                <el-select v-model="selectedWarehouseId" :placeholder="$t('choose_warehouse_placeholder')" style="width: 100%">
                    <el-option v-for="wh in warehouses" :key="wh.id" :value="wh.id" :label="`${wh.name} (${wh.code})`" />
                </el-select>
            </el-form-item>
            <template #footer>
                <el-button :disabled="assigning" @click="closeAssignModal">{{ $t('cancel') }}</el-button>
                <el-button type="primary" :loading="assigning" :disabled="!selectedWarehouseId" @click="assignToWarehouse">
                    {{ $t('link_action') }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { formatNumber as formatCount } from '@/utils/currency';
import { useI18n } from 'vue-i18n';
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import { wmsService } from '@/services/wms';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';

const { t } = useI18n();
const router = useRouter();

const loading = ref(false);
const products = ref([]);
const warehouses = ref([]);
const unassignedProducts = ref([]);
const assignedProducts = ref([]);
const error = ref(null);
const refreshing = ref(false);

const selectedWarehouseId = ref(null);
const searchQuery = ref('');
const showAssignModal = ref(false);
const selectedProduct = ref(null);
const assigning = ref(false);

const stats = computed(() => ({
    total: products.value.length,
    assigned: assignedProducts.value.length,
    unassigned: unassignedProducts.value.length,
}));

async function fetchData() {
    loading.value = true;
    error.value = null;

    try {
        const [productsRes, warehousesRes] = await Promise.all([
            // This screen's whole job is sorting the catalog into assigned vs
            // unassigned, so it genuinely needs close to everything rather
            // than one page of it.
            wmsService.getProducts({ per_page: 500 }),
            wmsService.getWarehouses(),
        ]);

        products.value = productsRes.data?.data || [];
        warehouses.value = warehousesRes.data?.data || warehousesRes.data || [];

        categorizeProducts();
    } catch (err) {
        error.value = err.response?.data?.message || err.message || t('failed_to_fetch_data_short');
        ElMessage.error(error.value);
    } finally {
        loading.value = false;
    }
}

function categorizeProducts() {
    unassignedProducts.value = products.value.filter((p) => p.warehouses_count === 0);
    assignedProducts.value = products.value.filter((p) => p.warehouses_count > 0);
}

const filteredUnassigned = computed(() => filterProducts(unassignedProducts.value));
const filteredAssigned = computed(() => filterProducts(assignedProducts.value));

function filterProducts(list) {
    if (!searchQuery.value) return list;
    const query = searchQuery.value.toLowerCase();
    return list.filter((p) => (p.name && p.name.toLowerCase().includes(query)) || (p.code && p.code.toLowerCase().includes(query)));
}

async function refreshData() {
    refreshing.value = true;
    await fetchData();
    refreshing.value = false;
    ElMessage.success(t('data_updated_successfully'));
}

function openAssignModal(product) {
    selectedProduct.value = product;
    selectedWarehouseId.value = null;
    showAssignModal.value = true;
}

function closeAssignModal() {
    showAssignModal.value = false;
    selectedProduct.value = null;
    selectedWarehouseId.value = null;
}

async function assignToWarehouse() {
    if (!selectedProduct.value || !selectedWarehouseId.value) {
        ElMessage.warning(t('please_choose_warehouse'));
        return;
    }

    assigning.value = true;
    try {
        // Quick-link defaults — refine exact levels from the assignment
        // screen ("edit" on the linked side) once real usage data exists.
        await wmsService.createAssignment({
            product_id: selectedProduct.value.id,
            warehouse_id: selectedWarehouseId.value,
            replenishment_method: 'purchase',
            planning_method: 'rop',
            min_stock_level: 10,
            max_stock_level: 1000,
            safety_stock: 5,
            lead_time_days: 7,
        });

        ElMessage.success(t('product_linked_to_warehouse'));
        closeAssignModal();
        await fetchData();
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('failed_to_link_product'));
    } finally {
        assigning.value = false;
    }
}

function goToProductDetails(productId) {
    // There is no WMS-specific product detail screen — the main product
    // record (edited in full elsewhere) is the real destination.
    router.push(`/admin/products/${productId}`);
}

function goToAssignment(productId) {
    router.push(`/admin/wms/products/${productId}/assign`);
}

function formatNumber(num) {
    if (num === null || num === undefined) return '—';
    return formatCount(num);
}

onMounted(fetchData);
</script>

<style scoped>
.organization-page { font-family: 'Cairo', sans-serif; }

.stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; }
.stat-card { border-radius: 12px; border-inline-start: 4px solid var(--el-color-info); }
.stat-card :deep(.el-card__body) { display: flex; flex-direction: column; gap: 0.3rem; }
.stat-blue { border-inline-start-color: var(--el-color-primary); }
.stat-green { border-inline-start-color: var(--el-color-success); }
.stat-yellow { border-inline-start-color: var(--el-color-warning); }
.stat-label { font-size: 0.8rem; color: var(--text-muted); }
.stat-value { font-size: 1.5rem; font-weight: 800; }

.columns-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1rem; align-items: start; }
.column-card :deep(.el-card__body) { padding: 0; }
.column-header { display: flex; align-items: center; justify-content: space-between; font-weight: 700; }

.column-body { max-height: 480px; overflow-y: auto; }
.product-row { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 0.9rem 1.1rem; border-bottom: 1px solid var(--border-color); }
.product-row:last-child { border-bottom: none; }
.product-row.clickable { cursor: pointer; }
.product-row.clickable:hover { background: var(--el-fill-color-light); }
.product-info { min-width: 0; }
.product-sub { margin: 0.15rem 0 0; font-size: 0.78rem; color: var(--text-muted); }
.product-actions { display: flex; gap: 0.4rem; flex: 0 0 auto; }

.assign-summary { margin-bottom: 1rem; padding: 0.75rem 1rem; background: var(--el-fill-color-light); border-radius: 8px; font-size: 0.85rem; }
.assign-summary p { margin: 0.2rem 0; }

.loading-state { padding: 2rem; }
.mb-3 { margin-bottom: 0.75rem; }
</style>
