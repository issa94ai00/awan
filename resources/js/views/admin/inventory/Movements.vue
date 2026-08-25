<template>
    <div class="inventory-page inventory-movements">
        <!-- Page Header -->
        <AdminPageHeader
            icon="fas fa-history text-primary"
            :title="$t('stock_movement_log')"
            :subtitle="$t('movement_log_subtitle')"
        >
            <template #actions>
                <router-link to="/admin/inventory">
                    <el-button><i class="fas fa-warehouse mr-1"></i> {{ $t('inventory_board') }}</el-button>
                </router-link>
                <el-button type="primary" class="create-btn" @click="openAdjustmentDrawer">
                    <i class="fas fa-plus"></i> {{ $t('new_stock_adjustment') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <!-- Filter Panel -->
        <el-card shadow="never" class="filters-panel mb-4">
            <div class="filters-row">
                <div class="filter-item filter-item-product">
                    <label>{{ $t('product_item') }}</label>
                    <!-- Searches the whole catalog on the server instead of filtering
                         only the first page already in memory, so a product outside
                         that page is still found. -->
                    <el-select
                        v-model="filters.product_id"
                        :placeholder="$t('search_product_placeholder')"
                        clearable
                        filterable
                        remote
                        reserve-keyword
                        :remote-method="searchProducts"
                        :loading="productSearchLoading"
                        style="width: 100%;"
                        @change="applyFilters"
                    >
                        <el-option v-for="p in productOptions" :key="p.id" :value="p.id" :label="p.name_ar || p.name_en || p.name">
                            <div class="product-option">
                                <span class="product-option-name">{{ p.name_ar || p.name_en || p.name }}</span>
                                <span class="product-option-meta">
                                    <span class="product-option-sku">SKU: {{ p.sku || '-' }}</span>
                                    <span class="product-option-stock" :class="stockTone(p.stock_quantity)">
                                        {{ $t('available') }} {{ p.stock_quantity ?? 0 }}
                                    </span>
                                </span>
                            </div>
                        </el-option>
                    </el-select>
                </div>
                <div class="filter-item filter-item-warehouse">
                    <label>{{ $t('warehouse') }}</label>
                    <el-select v-model="filters.warehouse_id" :placeholder="$t('all')" clearable style="width: 100%;" @change="applyFilters">
                        <el-option v-for="w in inventoryStore.warehouses" :key="w.id" :label="w.name" :value="w.id" />
                    </el-select>
                </div>
                <div class="filter-item filter-item-type">
                    <label>{{ $t('movement_type') }}</label>
                    <el-select v-model="filters.movement_type" :placeholder="$t('all')" clearable style="width: 100%;" @change="applyFilters">
                        <el-option :label="$t('movement_in')" value="in" />
                        <el-option :label="$t('movement_out_label')" value="out" />
                        <el-option :label="$t('movement_adjustment')" value="adjustment" />
                    </el-select>
                </div>
                <div class="filter-item filter-item-date">
                    <label>{{ $t('date_from') }}</label>
                    <el-date-picker v-model="filters.from_date" type="date" :placeholder="$t('choose_the_date')" value-format="YYYY-MM-DD" style="width: 100%;" @change="applyFilters" />
                </div>
                <div class="filter-item filter-item-date">
                    <label>{{ $t('date_to') }}</label>
                    <el-date-picker v-model="filters.to_date" type="date" :placeholder="$t('choose_the_date')" value-format="YYYY-MM-DD" style="width: 100%;" @change="applyFilters" />
                </div>
                <el-button type="info" plain class="filter-reset" @click="resetFilters">{{ $t('reset') }}</el-button>
            </div>
        </el-card>

        <!-- Main Card & Movements Table -->
        <el-card shadow="never" class="table-panel">
            <template #header>
                <div class="card-header">
                    <span><i class="fas fa-exchange-alt text-muted"></i> {{ $t('stock_transactions_statement') }}</span>
                </div>
            </template>

            <div v-if="store.loading" class="loading-state">
                <el-skeleton :rows="6" animated />
            </div>
            <div v-else>
                <el-table
                    v-if="store.movements.length"
                    :data="store.movements"
                    style="width: 100%"
                    stripe
                    class="custom-table"
                >
                    <el-table-column prop="created_at" :label="$t('date_and_time')" width="180" align="center">
                        <template #default="{ row }">
                            <span>{{ row.created_at ? row.created_at.replace('T', ' ').substring(0, 19) : '-' }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('item_product')" min-width="180">
                        <template #default="{ row }">
                            <strong style="color: var(--text-dark);">{{ row.product?.name_ar || row.product?.name || '-' }}</strong>
                            <p style="margin: 0.15rem 0 0 0; font-size: 0.8rem; color: var(--text-muted);">SKU: {{ row.product?.sku || '-' }}</p>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('warehouse')" min-width="130">
                        <template #default="{ row }">
                            {{ row.warehouse?.name || '-' }}
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('movement_type')" width="140" align="center">
                        <template #default="{ row }">
                            <el-tag :type="typeTagType(row.movement_type)" effect="light" class="status-tag">
                                <i class="fas status-dot-icon" :class="statusIconClass(row.movement_type)"></i>
                                {{ getArabicType(row.movement_type) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('quantity')" width="120" align="center">
                        <template #default="{ row }">
                            <strong :class="quantityColorClass(row.movement_type)" style="font-size: 1rem;">
                                {{ row.movement_type === 'in' ? '+' : '-' }}{{ row.quantity }}
                            </strong>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('reference_code')" width="150" show-overflow-tooltip>
                        <template #default="{ row }">
                            <span v-if="row.reference">{{ row.reference }}</span>
                            <span v-else-if="row.movement_key" class="text-muted">{{ row.movement_key }}</span>
                            <span v-else>-</span>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('responsible')" width="140" show-overflow-tooltip>
                        <template #default="{ row }">{{ row.creator?.name || '-' }}</template>
                    </el-table-column>
                    <el-table-column :label="$t('notes')" min-width="180" show-overflow-tooltip>
                        <template #default="{ row }">{{ row.notes || '-' }}</template>
                    </el-table-column>
                </el-table>

                <!-- Empty State -->
                <div v-if="!store.movements.length" class="empty-state-box">
                    <i class="fas fa-exchange-alt empty-icon"></i>
                    <p>{{ $t('no_matching_movements') }}</p>
                    <el-button type="primary" size="medium" @click="openAdjustmentDrawer">
                        <i class="fas fa-plus"></i> {{ $t('record_stock_movement') }}
                    </el-button>
                </div>

                <!-- Pagination -->
                <div v-if="store.movements.length" class="pagination-row">
                    <el-pagination
                        layout="prev, pager, next, total"
                        :total="store.pagination.total"
                        :current-page="store.pagination.current_page"
                        :page-size="store.pagination.per_page"
                        background
                        @current-change="onPageChange"
                    />
                </div>
            </div>
        </el-card>

        <!-- Quick Adjustment Form Drawer -->
        <el-drawer
            v-model="adjustmentDrawerVisible"
            :title="$t('record_new_stock_movement')"
            size="40%"
            direction="rtl"
            destroy-on-close
            class="form-drawer"
        >
            <el-form :model="form" label-position="top">
                <el-form-item :label="$t('product_to_adjust')" required>
                    <el-select
                        v-model="form.product_id"
                        :placeholder="$t('search_product_placeholder')"
                        style="width: 100%"
                        clearable
                        filterable
                        remote
                        reserve-keyword
                        :remote-method="searchProducts"
                        :loading="productSearchLoading"
                    >
                        <el-option v-for="p in productOptions" :key="p.id" :value="p.id" :label="p.name_ar || p.name_en || p.name">
                            <div class="product-option">
                                <span class="product-option-name">{{ p.name_ar || p.name_en || p.name }}</span>
                                <span class="product-option-meta">
                                    <span class="product-option-sku">SKU: {{ p.sku || '-' }}</span>
                                    <span class="product-option-stock" :class="stockTone(p.stock_quantity)">
                                        {{ $t('available') }} {{ p.stock_quantity ?? 0 }}
                                    </span>
                                </span>
                            </div>
                        </el-option>
                    </el-select>
                </el-form-item>

                <el-form-item :label="$t('warehouse')">
                    <el-select v-model="form.warehouse_id" :placeholder="$t('default_warehouse')" clearable style="width: 100%">
                        <el-option v-for="w in inventoryStore.warehouses" :key="w.id" :label="w.name" :value="w.id" />
                    </el-select>
                </el-form-item>

                <el-form-item :label="$t('stock_movement_type')" required>
                    <el-radio-group v-model="form.movement_type" class="w-100">
                        <el-radio-button value="in">{{ $t('movement_in') }}</el-radio-button>
                        <el-radio-button value="out">{{ $t('movement_out_label') }}</el-radio-button>
                        <el-radio-button value="adjustment">{{ $t('movement_adjustment') }}</el-radio-button>
                    </el-radio-group>
                </el-form-item>

                <el-form-item :label="$t('quantity')" required>
                    <el-input-number v-model="form.quantity" :min="1" style="width: 100%" />
                </el-form-item>

                <el-form-item :label="$t('reference_code_hint')">
                    <el-input v-model="form.reference" :placeholder="$t('reference_example')" />
                </el-form-item>

                <el-form-item :label="$t('source')">
                    <el-input v-model="form.source" :placeholder="$t('source_example')" />
                </el-form-item>

                <el-form-item :label="$t('adjustment_notes')">
                    <el-input v-model="form.notes" type="textarea" :rows="3" :placeholder="$t('adjustment_notes_placeholder')" />
                </el-form-item>

                <div class="drawer-footer">
                    <el-button @click="adjustmentDrawerVisible = false">{{ $t('cancel') }}</el-button>
                    <el-button type="primary" :loading="submittingForm" @click="saveAdjustment">{{ $t('confirm_stock_movement') }}</el-button>
                </div>
            </el-form>
        </el-drawer>
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import { ref, onMounted, reactive } from 'vue';
import { useStockMovementsStore } from '@/stores/stockMovements';
import { useProductsStore } from '@/stores/products';
import { useInventoryStore } from '@/stores/inventory';
import { stockMovementsApi } from '@/api/stockMovements';
import { productsApi } from '@/api/products';
import { ElMessage } from 'element-plus';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';

const { t } = useI18n();

const store = useStockMovementsStore();
const productsStore = useProductsStore();
const inventoryStore = useInventoryStore();

// Product search: starts as whatever page loaded on mount, then becomes the
// live server search results once the operator types — shared between the
// filter panel and the adjustment drawer since both search the same catalog.
const productOptions = ref([]);
const productSearchLoading = ref(false);
let productSearchTimer = null;

const searchProducts = (query) => {
    clearTimeout(productSearchTimer);

    if (!query) {
        productOptions.value = productsStore.products;
        return;
    }

    productSearchLoading.value = true;
    productSearchTimer = setTimeout(async () => {
        try {
            const res = await productsApi.getAll({ search: query, per_page: 100 });
            productOptions.value = res.data.data || [];
        } catch (e) {
            // Keep whatever was showing rather than blanking the list on a
            // transient failure.
        } finally {
            productSearchLoading.value = false;
        }
    }, 300);
};

const stockTone = (quantity) => {
    const value = Number(quantity) || 0;
    if (value <= 0) return 'out';
    return value <= 5 ? 'low' : 'ok';
};

// Filters state
const filters = reactive({
    product_id: '',
    warehouse_id: '',
    movement_type: '',
    from_date: '',
    to_date: '',
});

const buildParams = () => {
    const params = { per_page: store.pagination.per_page, page: store.pagination.current_page };
    if (filters.product_id) params.product_id = filters.product_id;
    if (filters.warehouse_id) params.warehouse_id = filters.warehouse_id;
    if (filters.movement_type) params.movement_type = filters.movement_type;
    if (filters.from_date) params.from_date = filters.from_date;
    if (filters.to_date) params.to_date = filters.to_date;
    return params;
};

const applyFilters = async () => {
    store.pagination.current_page = 1;
    try {
        await store.fetchMovements(buildParams());
    } catch (e) {
        /* handled by store */
    }
};

const resetFilters = () => {
    filters.product_id = '';
    filters.warehouse_id = '';
    filters.movement_type = '';
    filters.from_date = '';
    filters.to_date = '';
    store.pagination.current_page = 1;
    store.fetchMovements(buildParams()).catch(() => {});
};

const onPageChange = (page) => {
    store.pagination.current_page = page;
    store.fetchMovements(buildParams()).catch(() => {});
};

// Adjustment Drawer state
const adjustmentDrawerVisible = ref(false);
const submittingForm = ref(false);
const form = reactive({
    product_id: '',
    warehouse_id: '',
    movement_type: 'adjustment',
    quantity: 1,
    reference: '',
    source: '',
    notes: '',
});

const resetForm = () => {
    form.product_id = '';
    form.warehouse_id = '';
    form.movement_type = 'adjustment';
    form.quantity = 1;
    form.reference = '';
    form.source = '';
    form.notes = '';
};

const openAdjustmentDrawer = () => {
    resetForm();
    adjustmentDrawerVisible.value = true;
};

const saveAdjustment = async () => {
    if (!form.product_id) {
        ElMessage.warning(t('please_select_item_first'));
        return;
    }
    if (!form.quantity) {
        ElMessage.warning(t('please_set_quantity'));
        return;
    }

    submittingForm.value = true;
    try {
        await stockMovementsApi.create({
            ...form,
            product_id: form.product_id,
            warehouse_id: form.warehouse_id || null,
            quantity: Number(form.quantity),
        });
        ElMessage.success(t('stock_movement_recorded_and_posted'));
        adjustmentDrawerVisible.value = false;
        await store.fetchMovements(buildParams());
        await productsStore.fetchProducts({ per_page: 200 });
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('failed_to_save_stock_movement'));
    } finally {
        submittingForm.value = false;
    }
};

const typeTagType = (type) => {
    const val = String(type || '').toLowerCase();
    if (val === 'in') return 'success';
    if (val === 'out') return 'danger';
    return 'warning';
};

const statusIconClass = (type) => {
    const val = String(type || '').toLowerCase();
    if (val === 'in') return 'fa-arrow-alt-circle-down';
    if (val === 'out') return 'fa-arrow-alt-circle-up';
    return 'fa-sliders-h';
};

const getArabicType = (type) => {
    const val = String(type || '').toLowerCase();
    if (val === 'in') return t('movement_in');
    if (val === 'out') return t('movement_out_label');
    return t('movement_adjustment');
};

const quantityColorClass = (type) => {
    const val = String(type || '').toLowerCase();
    if (val === 'in') return 'text-success';
    if (val === 'out') return 'text-danger';
    return 'text-warning';
};

onMounted(async () => {
    store.fetchMovements(buildParams()).catch(() => {});
    inventoryStore.fetchWarehouses().catch(() => {});

    await productsStore.fetchProducts({ per_page: 200 }).catch(() => {});
    productOptions.value = productsStore.products;
});
</script>

<style scoped>
.inventory-page {
    font-family: 'Cairo', sans-serif;
}

.page-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1.25rem;
    margin-bottom: 2rem;
    padding-bottom: 1.25rem;
    border-bottom: 2px solid var(--border-color);
}

.page-title h1 {
    margin: 0;
    font-size: 1.6rem;
    font-weight: 700;
    color: var(--text-dark);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.page-title p {
    margin: 0.5rem 0 0;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.header-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
}

.create-btn {
    font-weight: 600;
    border-radius: var(--radius-md);
    padding: 0.625rem 1.25rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.mr-1 {
    margin-inline-end: 0.35rem;
}

.filters-panel {
    border-radius: var(--radius-md);
}

.filters-row {
    display: flex;
    flex-wrap: wrap;
    gap: 1.25rem;
    align-items: flex-end;
}

.filter-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    width: 180px;
}

.filter-item-product { width: 280px; }
.filter-item-warehouse { width: 180px; }
.filter-item-type { width: 160px; }
.filter-item-date { width: 160px; }

.filter-item label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-muted);
}

.filter-reset { align-self: flex-end; }

/* Product search dropdown: name first, SKU and live stock secondary so a
   pick can be made without opening the product itself to check either. */
.product-option {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    line-height: 1.3;
    padding: 0.15rem 0;
}

.product-option-name {
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.product-option-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    font-size: 0.78rem;
    color: var(--text-muted);
}

.product-option-sku {
    font-family: 'Cascadia Mono', Consolas, monospace;
}

.product-option-stock { font-variant-numeric: tabular-nums; }
.product-option-stock.ok { color: var(--success-color, #10b981); }
.product-option-stock.low { color: var(--warning-color, #f59e0b); }
.product-option-stock.out { color: var(--danger-color, #ef4444); }

.table-panel {
    border-radius: 1rem;
    overflow: hidden;
}

.card-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 700;
    color: var(--text-dark);
}

.status-tag {
    font-size: 0.8rem;
    font-weight: 600;
    border-radius: 20px;
    padding: 0.25rem 0.75rem;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.status-dot-icon {
    font-size: 0.8rem;
}

.loading-state {
    padding: 2rem;
}

.empty-state-box {
    padding: 4rem 2rem;
    text-align: center;
    color: var(--text-muted);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.empty-icon {
    font-size: 3.5rem;
    color: var(--text-light);
    margin-bottom: 1.25rem;
    opacity: 0.5;
}

.empty-state-box p {
    font-weight: 500;
    font-size: 1.05rem;
    margin-bottom: 1.5rem;
}

.pagination-row {
    display: flex;
    justify-content: flex-end;
    padding-top: 1.1rem;
}

.drawer-footer {
    border-top: 1px solid var(--border-color);
    margin-top: 2rem;
    padding-top: 1.5rem;
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}

.w-100 {
    width: 100%;
}

.text-muted {
    color: var(--text-muted);
}

/* ── Responsive ─────────────────────────────────────────────────────── */
@media (max-width: 768px) {
    .filters-row {
        flex-direction: column;
        align-items: stretch;
    }

    .filter-item,
    .filter-item-product,
    .filter-item-warehouse,
    .filter-item-type,
    .filter-item-date {
        width: 100%;
    }

    .filter-reset {
        align-self: stretch;
        width: 100%;
    }
}

@media (max-width: 640px) {
    /* The drawer's percentage width was sized for desktop; on a phone it
       left barely room for the fields it holds. */
    .form-drawer {
        width: 92vw !important;
    }
}
</style>
