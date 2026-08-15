<template>
    <div class="inventory-page inventory-index">
        <!-- Page Header -->
        <AdminPageHeader
            icon="fas fa-warehouse"
            :title="$t('inventory_dashboard')"
            :subtitle="$t('inventory_dashboard_subtitle')"
        >
            <template #actions>
                <el-button type="info" plain @click="openAdjustmentDrawer">
                    <i class="fas fa-sliders-h mr-1"></i> {{ $t('stock_adjustment') }}
                </el-button>
                <router-link to="/admin/inventory/movements">
                    <el-button>
                        <i class="fas fa-history mr-1"></i> {{ $t('movement_log') }}
                    </el-button>
                </router-link>
                <el-button type="success" @click="exportStock" :loading="exporting" plain>
                    {{ $t('export_balances') }}
                </el-button>
                <el-button type="warning" @click="triggerInventoryImport" :loading="importing" plain>
                    {{ $t('import_balances') }}
                </el-button>
                <el-button type="primary" @click="refreshAll">
                    <i class="fas fa-sync-alt mr-1" :class="{ 'fa-spin': loading }"></i> {{ $t('update') }}
                </el-button>
            </template>
        </AdminPageHeader>
        <input ref="inventoryFileInput" type="file" accept=".xlsx" style="display:none" @change="onInventoryFileSelected" />

        <!-- Metric Cards -->
        <AdminStatGrid>
            <el-card shadow="never" class="stat-card-wrapper">
                <div class="stat-card-inner">
                    <div class="stat-icon-box blue-grad"><i class="fas fa-boxes"></i></div>
                    <div class="stat-details">
                        <h3>{{ formatNumber(summary?.products_with_stock) }}</h3>
                        <p>{{ $t('items_with_stock') }}</p>
                    </div>
                </div>
            </el-card>
            <el-card shadow="never" class="stat-card-wrapper">
                <div class="stat-card-inner">
                    <div class="stat-icon-box green-grad"><i class="fas fa-cubes"></i></div>
                    <div class="stat-details">
                        <h3>{{ formatNumber(summary?.total_available) }}</h3>
                        <p>{{ $t('units_available_for_sale') }}</p>
                    </div>
                </div>
            </el-card>
            <el-card shadow="never" class="stat-card-wrapper">
                <div class="stat-card-inner">
                    <div class="stat-icon-box gold-grad"><i class="fas fa-coins"></i></div>
                    <div class="stat-details">
                        <h3>{{ formatMoney(summary?.total_value) }}</h3>
                        <p>{{ $t('total_stock_value') }}</p>
                    </div>
                </div>
            </el-card>
            <el-card shadow="never" class="stat-card-wrapper">
                <div class="stat-card-inner">
                    <div class="stat-icon-box" :class="(summary?.low_stock_rows || 0) > 0 ? 'red-grad' : 'green-grad'">
                        <i class="fas" :class="(summary?.low_stock_rows || 0) > 0 ? 'fa-exclamation-triangle' : 'fa-check'"></i>
                    </div>
                    <div class="stat-details">
                        <h3>{{ formatNumber(summary?.low_stock_rows) }}</h3>
                        <p>{{ $t('items_at_reorder_point') }}</p>
                    </div>
                </div>
            </el-card>
        </AdminStatGrid>

        <!-- Warehouse Distribution + Today Activity -->
        <el-row :gutter="16" class="mt-4">
            <el-col :xs="24" :lg="14">
                <el-card shadow="never" class="panel">
                    <template #header>
                        <div class="card-header">
                            <span><i class="fas fa-warehouse"></i> {{ $t('stock_across_warehouses') }}</span>
                        </div>
                    </template>

                    <div v-loading="loading" class="warehouse-list">
                        <div v-for="w in warehouses" :key="w.id" class="warehouse-row">
                            <div class="warehouse-info">
                                <div>
                                    <strong>{{ w.name }}</strong>
                                    <span class="wh-code">{{ w.code }}</span>
                                    <el-tag v-if="!w.is_active" type="info" size="small">{{ $t('inactive') }}</el-tag>
                                </div>
                                <span class="wh-qty">
                                    {{ formatNumber(w.total_available) }} متاح
                                    <span class="wh-gross">من {{ formatNumber(w.total_quantity) }}</span>
                                </span>
                            </div>
                            <el-progress
                                :percentage="warehousePercentage(w.total_available)"
                                :stroke-width="10"
                                :color="warehouseProgressColor(w.total_available)"
                                :show-text="false"
                            />
                        </div>
                        <el-empty v-if="!warehouses.length && !loading" :description="$t('no_warehouses')" :image-size="70" />
                    </div>
                </el-card>
            </el-col>

            <el-col :xs="24" :lg="10">
                <el-card shadow="never" class="panel">
                    <template #header>
                        <div class="card-header">
                            <span><i class="fas fa-chart-line"></i> {{ $t('today_s_activity') }}</span>
                        </div>
                    </template>
                    <div class="today-stats">
                        <div class="today-item">
                            <div class="today-icon blue-grad"><i class="fas fa-exchange-alt"></i></div>
                            <div>
                                <h3>{{ formatNumber(today?.movements) }}</h3>
                                <p>{{ $t('total_movements') }}</p>
                            </div>
                        </div>
                        <div class="today-item">
                            <div class="today-icon green-grad"><i class="fas fa-arrow-down"></i></div>
                            <div>
                                <h3>{{ formatNumber(today?.received) }}</h3>
                                <p>{{ $t('inbound_receipt') }}</p>
                            </div>
                        </div>
                        <div class="today-item">
                            <div class="today-icon red-grad"><i class="fas fa-arrow-up"></i></div>
                            <div>
                                <h3>{{ formatNumber(today?.issued) }}</h3>
                                <p>{{ $t('outbound_issue') }}</p>
                            </div>
                        </div>
                    </div>

                    <el-divider content-position="right">{{ $t('shortage_alerts') }}</el-divider>
                    <div v-if="lowStockRows.length" class="alert-list">
                        <div v-for="row in lowStockRows" :key="row.id" class="alert-item">
                            <i class="fas fa-exclamation-triangle alert-icon"></i>
                            <div class="alert-text">
                                <strong>{{ row.product?.name_ar || row.product?.name || row.product?.sku }}</strong>
                                <p>{{ row.warehouse?.name }} — متاح {{ formatNumber(row.available) }} من {{ formatNumber(row.quantity) }}</p>
                            </div>
                        </div>
                    </div>
                    <el-empty v-else :description="$t('no_low_stock_items')" :image-size="70" />
                </el-card>
            </el-col>
        </el-row>

        <!-- Stock Table -->
        <el-card shadow="never" class="panel mt-4">
            <template #header>
                <div class="card-header card-header-split">
                    <span><i class="fas fa-table"></i> {{ $t('item_balances_by_warehouse') }}</span>
                    <div class="table-actions">
                        <el-select v-model="filters.warehouse_id" :placeholder="$t('all_warehouses')" clearable style="width: 180px;" @change="() => loadStock(true)">
                            <el-option v-for="w in warehouses" :key="w.id" :label="w.name" :value="w.id" />
                        </el-select>
                        <el-select v-model="filters.status" :placeholder="$t('all_statuses')" clearable style="width: 150px;" @change="() => loadStock(true)">
                            <el-option :label="$t('available')" value="ok" />
                            <el-option :label="$t('low')" value="low" />
                            <el-option :label="$t('out_of_stock_short')" value="out" />
                        </el-select>
                        <el-input v-model="filters.search" :placeholder="$t('search_by_name_or_sku')" clearable style="width: 220px;" @keyup.enter="() => loadStock(true)" @clear="() => loadStock(true)">
                            <template #prefix><i class="fas fa-search"></i></template>
                        </el-input>
                    </div>
                </div>
            </template>

            <el-table :data="store.stock" v-loading="loading" stripe size="default" :empty-text="$t('no_stock_data')">
                <el-table-column :label="$t('product')" min-width="200">
                    <template #default="{ row }">
                        <div class="product-name-cell">
                            <strong>{{ row.product?.name_ar || row.product?.name || '-' }}</strong>
                            <el-button
                                type="text"
                                size="small"
                                class="quick-edit-button"
                                @click="openQuickEdit(row)"
                                v-if="row.product?.id"
                            >
                                <i class="fas fa-edit"></i> {{ $t('quick_edit') }}
                            </el-button>
                        </div>
                        <p class="table-sub">{{ row.product?.sku || '-' }}</p>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('warehouse')" min-width="140">
                    <template #default="{ row }">
                        {{ row.warehouse?.name || '-' }}
                        <p class="table-sub">{{ row.bin?.code || '' }}</p>
                    </template>
                </el-table-column>
                <!-- The three figures side by side: without them a balance that
                     dropped because another order reserved it looks like stock
                     that went missing. -->
                <el-table-column :label="$t('grand_total')" width="100" align="center">
                    <template #default="{ row }">
                        <span class="table-sub">{{ formatNumber(row.quantity) }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('reserved')" width="100" align="center">
                    <template #default="{ row }">
                        <span v-if="Number(row.reserved_quantity) > 0" class="text-warning">
                            {{ formatNumber(row.reserved_quantity) }}
                        </span>
                        <span v-else class="table-sub">—</span>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('available_amount')" width="110" align="center">
                    <template #default="{ row }">
                        <strong :class="availableClass(row)">{{ formatNumber(row.available) }}</strong>
                        <p v-if="unsellable(row) > 0" class="table-sub">
                            {{ formatNumber(unsellable(row)) }} تالف/محتجز
                        </p>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('cost')" width="120" align="center">
                    <template #default="{ row }">
                        <span v-if="row.product?.cost_price > 0">{{ formatMoney(row.product.cost_price) }}</span>
                        <span v-else class="table-sub">—</span>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('selling_price')" width="120" align="center">
                    <template #default="{ row }">
                        <span v-if="row.product?.price > 0">{{ formatMoney(row.product.price) }}</span>
                        <span v-else class="table-sub">—</span>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('value')" width="130" align="center">
                    <template #default="{ row }">{{ formatMoney(row.available * (row.product?.cost_price || 0)) }}</template>
                </el-table-column>
                <el-table-column :label="$t('status')" width="110" align="center">
                    <template #default="{ row }">
                        <el-tag :type="stockStatus(row).type" effect="light" size="small">{{ stockStatus(row).label }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('latest_update')" width="140" align="center">
                    <template #default="{ row }">{{ formatDate(row.updated_at) }}</template>
                </el-table-column>
            </el-table>

            <div class="pagination-row">
                <el-pagination
                    layout="prev, pager, next, total"
                    :total="store.pagination.total"
                    :current-page="store.pagination.current_page"
                    :page-size="store.pagination.per_page"
                    background
                    @current-change="onPageChange"
                />
            </div>
        </el-card>

        <!-- Adjustment Drawer -->
        <el-drawer
            v-model="adjustmentDrawerVisible"
            :title="$t('record_movement_and_adjustment')"
            size="42%"
            direction="rtl"
            destroy-on-close
            class="form-drawer"
        >
            <el-form :model="form" label-position="top">
                <el-form-item :label="$t('product')" required>
                    <el-select v-model="form.product_id" :placeholder="$t('select_product')" style="width: 100%" filterable>
                        <el-option
                            v-for="p in products"
                            :key="p.id"
                            :label="`${p.name_ar || p.name_en || p.name} (SKU: ${p.sku})`"
                            :value="p.id"
                        />
                    </el-select>
                </el-form-item>

                <el-form-item :label="$t('warehouse')">
                    <el-select v-model="form.warehouse_id" :placeholder="$t('default_warehouse')" clearable style="width: 100%">
                        <el-option v-for="w in warehouses" :key="w.id" :label="w.name" :value="w.id" />
                    </el-select>
                </el-form-item>

                <el-form-item :label="$t('movement_type')" required>
                    <el-radio-group v-model="form.movement_type" class="w-100">
                        <el-radio-button value="in">{{ $t('inbound') }}</el-radio-button>
                        <el-radio-button value="out">{{ $t('outbound') }}</el-radio-button>
                        <el-radio-button value="adjustment">{{ $t('adjustment') }}</el-radio-button>
                    </el-radio-group>
                </el-form-item>

                <el-form-item :label="$t('quantity')" required>
                    <el-input-number v-model="form.quantity" :min="1" style="width: 100%" />
                </el-form-item>

                <el-form-item :label="$t('reference_code')">
                    <el-input v-model="form.reference" :placeholder="$t('reference_example')" />
                </el-form-item>

                <el-form-item :label="$t('notes')">
                    <el-input v-model="form.notes" type="textarea" :rows="3" :placeholder="$t('movement_reason_placeholder')" />
                </el-form-item>

                <div class="drawer-footer">
                    <el-button @click="adjustmentDrawerVisible = false">{{ $t('cancel') }}</el-button>
                    <el-button type="primary" :loading="submittingForm" @click="saveAdjustment">{{ $t('confirm_movement') }}</el-button>
                </div>
            </el-form>
        </el-drawer>

        <!-- Quick Edit Dialog -->
        <el-dialog
            v-model="quickEditDialogVisible"
            :title="$t('quick_edit_product')"
            width="600px"
            :close-on-click-modal="false"
        >
            <el-form :model="quickEditForm" label-width="120px" label-position="right">
                <el-form-item :label="$t('name_arabic')">
                    <el-input v-model="quickEditForm.name_ar" />
                </el-form-item>
                <el-form-item :label="$t('name_english')">
                    <el-input v-model="quickEditForm.name_en" />
                </el-form-item>
                <el-form-item :label="$t('price')">
                    <el-input-number v-model="quickEditForm.price" :min="0" :step="0.01" style="width: 100%" />
                </el-form-item>
                <el-form-item :label="$t('discounted_price')">
                    <el-input-number v-model="quickEditForm.sale_price" :min="0" :step="0.01" style="width: 100%" />
                </el-form-item>
                <el-form-item :label="$t('cost_price')">
                    <el-input-number v-model="quickEditForm.cost_price" :min="0" :step="0.01" style="width: 100%" />
                </el-form-item>
                <el-form-item :label="$t('quantity')">
                    <el-input-number v-model="quickEditForm.stock_quantity" :min="0" style="width: 100%" />
                </el-form-item>
                <el-form-item :label="$t('the_category')">
                    <el-select v-model="quickEditForm.category_id" :placeholder="$t('select_category')" style="width: 100%">
                        <el-option
                            v-for="cat in categories"
                            :key="cat.id"
                            :label="cat.name_ar || cat.name"
                            :value="cat.id"
                        />
                    </el-select>
                </el-form-item>
                <el-form-item :label="$t('status')">
                    <el-switch v-model="quickEditForm.is_active" />
                </el-form-item>
                <el-form-item :label="$t('featured')">
                    <el-switch v-model="quickEditForm.is_featured" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="quickEditDialogVisible = false">{{ $t('cancel') }}</el-button>
                <el-button type="primary" :loading="quickEditSubmitting" @click="submitQuickEdit">{{ $t('save') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import { ref, computed, reactive, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useInventoryStore } from '@/stores/inventory';
import { useProductsStore } from '@/stores/products';
import { inventoryApi } from '@/api/inventory';
import { ElMessage, ElMessageBox } from 'element-plus';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';

const { t } = useI18n();

const router = useRouter();
const store = useInventoryStore();
const productsStore = useProductsStore();

const loading = computed(() => store.loading);
const summary = computed(() => store.summary);
const today = computed(() => store.today);
const warehouses = computed(() => store.warehouses);
const products = computed(() => productsStore.products);
const categories = computed(() => productsStore.categories);

const filters = reactive({
    warehouse_id: null,
    status: null,
    search: '',
});

const lowStockRows = computed(() => store.stock.filter((r) => stockStatus(r).type !== 'success').slice(0, 5));

const stockStatus = (row) => {
    const available = Number(row.available ?? 0);
    const reorder = Number(row.reorder_point ?? 0);
    if (available <= 0) return { label: t('out_of_stock_short'), type: 'danger' };
    if (available <= reorder) return { label: t('low'), type: 'warning' };
    return { label: t('available'), type: 'success' };
};

const warehousePercentage = (qty) => {
    const max = Math.max(...warehouses.value.map((w) => Number(w.total_available) || 0), 1);
    return Math.round(((Number(qty) || 0) / max) * 100);
};

const warehouseProgressColor = (qty) => (Number(qty) > 0 ? '#667eea' : '#9ca3af');

/** Units on the shelf that cannot be sold at all — not merely spoken for. */
const unsellable = (row) => (Number(row.damaged_quantity) || 0) + (Number(row.quarantined_quantity) || 0);

const availableClass = (row) => {
    const available = Number(row.available ?? 0);
    if (available <= 0) return 'text-danger';
    if (available <= Number(row.reorder_point ?? 0)) return 'text-warning';
    return 'text-success';
};

const formatNumber = (n) => (n === null || n === undefined ? '0' : Number(n).toLocaleString('en-US'));
const formatMoney = (n) =>
    (n === null || n === undefined ? '0' : Number(n)).toLocaleString('en-US', { style: 'currency', currency: 'SAR', maximumFractionDigits: 2 });

const formatDate = (d) => (d ? String(d).replace('T', ' ').substring(0, 16) : '-');

const exporting = ref(false);
const importing = ref(false);
const inventoryFileInput = ref(null);

const triggerInventoryImport = () => {
    inventoryFileInput.value?.click();
};

const onInventoryFileSelected = async (event) => {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;

    if (!/\.xlsx$/i.test(file.name)) {
        ElMessage.error(t('please_choose_xlsx_file'));
        return;
    }

    importing.value = true;
    try {
        const formData = new FormData();
        formData.append('file', file);
        const res = await inventoryApi.importStock(formData);
        const data = res.data?.data || {};
        // Split new vs updated stock rows so it is obvious when a sheet added a
        // product to another warehouse rather than editing its existing balance.
        const messages = [
            `منتجات جديدة: ${data.products_created ?? 0}`,
            `منتجات مطابقة: ${data.products_matched ?? 0}`,
            `أسعار محدَّثة: ${data.prices_updated ?? 0}`,
            `أرصدة جديدة: ${data.inventory_created ?? 0}`,
            `أرصدة محدَّثة: ${data.inventory_updated ?? 0}`,
        ];

        const errors = data.errors || [];
        if (errors.length) {
            messages.push(`أخطاء: ${errors.length}`);
        }

        ElMessage[errors.length ? 'warning' : 'success']({
            message: messages.join(t('list_separator')),
            duration: 6000,
        });

        // Computed columns the sheet carried but the importer does not apply.
        // Said out loud, because an operator who edited t('reserved') and saw a
        // success message would otherwise assume it took.
        const ignored = data.ignored_columns || [];
        if (ignored.length) {
            ElMessage({
                type: 'info',
                message: t('computed_columns_not_imported', { columns: ignored.join(t('list_separator')) }),
                duration: 9000,
                showClose: true,
            });
        }

        // Rows are listed rather than counted: "12 أخطاء" tells the operator
        // nothing they can act on, and the row numbers match the spreadsheet's
        // own gutter so each one can be found and fixed.
        if (errors.length) {
            ElMessageBox.alert(
                errors
                    .slice(0, 20)
                    .map((e) => `صف ${e.row}: ${e.message}`)
                    .join('<br>') +
                    (errors.length > 20 ? `<br>… و${errors.length - 20} صفاً آخر` : ''),
                t('rows_not_imported'),
                { dangerouslyUseHTMLString: true, confirmButtonText: t('ok_button') },
            );
        }

        await loadStock(true);
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('failed_to_import_balances'));
    } finally {
        importing.value = false;
    }
};

const exportStock = async () => {
    exporting.value = true;
    try {
        const params = {
            warehouse_id: filters.warehouse_id || undefined,
            status: filters.status && filters.status !== 'ok' ? filters.status : undefined,
            search: filters.search || undefined,
        };
        const res = await inventoryApi.exportStock(params);
        const blob = new Blob([res.data], {
            type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `warehouse-stock-${new Date().toISOString().slice(0, 10)}.xlsx`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
        ElMessage.success(t('balances_exported'));
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('failed_to_export_balances'));
    } finally {
        exporting.value = false;
    }
};

const navigateToProduct = (productId) => {
    if (!productId) return;
    router.push({ name: 'admin.products.edit', params: { id: productId } });
};

const openQuickEdit = (row) => {
    if (!row.product?.id) return;
    quickEditForm.value = {
        id: row.product.id,
        name_ar: row.product.name_ar || '',
        name_en: row.product.name_en || '',
        price: row.product.price || 0,
        sale_price: row.product.sale_price || null,
        cost_price: row.product.cost_price || null,
        stock_quantity: row.product.stock_quantity || 0,
        category_id: row.product.category_id || null,
        is_active: row.product.is_active ?? true,
        is_featured: row.product.is_featured ?? false,
    };
    quickEditDialogVisible.value = true;
};

const submitQuickEdit = async () => {
    quickEditSubmitting.value = true;
    try {
        await productsStore.updateProduct(quickEditForm.value.id, {
            name_ar: quickEditForm.value.name_ar,
            name_en: quickEditForm.value.name_en,
            price: quickEditForm.value.price,
            sale_price: quickEditForm.value.sale_price,
            cost_price: quickEditForm.value.cost_price,
            stock_quantity: quickEditForm.value.stock_quantity,
            category_id: quickEditForm.value.category_id,
            is_active: quickEditForm.value.is_active,
            is_featured: quickEditForm.value.is_featured,
        });
        ElMessage.success(t('the_product_has_been_updated'));
        quickEditDialogVisible.value = false;
        await refreshAll();
    } catch (error) {
        ElMessage.error(error.response?.data?.message || t('failed_to_update_product'));
    } finally {
        quickEditSubmitting.value = false;
    }
};

const loadStock = async (resetPage = false) => {
    if (resetPage) {
        store.pagination.current_page = 1;
    }

    const params = {
        per_page: 20,
        page: store.pagination.current_page,
    };
    if (filters.warehouse_id) params.warehouse_id = filters.warehouse_id;
    if (filters.status && filters.status !== 'ok') params.status = filters.status;
    if (filters.search) params.search = filters.search;
    try {
        await store.fetchStock(params);
    } catch (e) {
        ElMessage.error(t('failed_to_load_stock_data'));
    }
};

const onPageChange = (page) => {
    store.pagination.current_page = page;
    loadStock();
};

const refreshAll = async () => {
    try {
        await Promise.all([store.fetchSummary(), loadStock()]);
        ElMessage.success(t('data_updated_successfully'));
    } catch (e) {
        ElMessage.error(t('failed_to_update_data'));
    }
};

// Adjustment drawer
const adjustmentDrawerVisible = ref(false);
const submittingForm = ref(false);
const form = reactive({
    product_id: null,
    warehouse_id: null,
    movement_type: 'adjustment',
    quantity: 1,
    reference: '',
    notes: '',
});

// Quick edit dialog
const quickEditDialogVisible = ref(false);
const quickEditSubmitting = ref(false);
const quickEditForm = ref({
    id: null,
    name_ar: '',
    name_en: '',
    price: 0,
    sale_price: null,
    cost_price: null,
    stock_quantity: 0,
    category_id: null,
    is_active: true,
    is_featured: false,
});

const resetForm = () => {
    form.product_id = null;
    form.warehouse_id = null;
    form.movement_type = 'adjustment';
    form.quantity = 1;
    form.reference = '';
    form.notes = '';
};

const openAdjustmentDrawer = () => {
    resetForm();
    adjustmentDrawerVisible.value = true;
};

const saveAdjustment = async () => {
    if (!form.product_id) {
        ElMessage.warning(t('please_select_product_first'));
        return;
    }
    if (!form.quantity || form.quantity < 1) {
        ElMessage.warning(t('please_set_quantity'));
        return;
    }

    submittingForm.value = true;
    try {
        await store.createMovement({
            product_id: form.product_id,
            warehouse_id: form.warehouse_id || null,
            movement_type: form.movement_type,
            quantity: form.quantity,
            reference: form.reference,
            notes: form.notes,
            movement_key: form.reference ? `manual:${form.reference}` : null,
        });
        ElMessage.success(t('stock_movement_recorded'));
        adjustmentDrawerVisible.value = false;
        await refreshAll();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('failed_to_save_stock_movement'));
    } finally {
        submittingForm.value = false;
    }
};

onMounted(() => {
    Promise.all([
        store.fetchSummary(),
        productsStore.fetchProducts({ per_page: 500 }).catch(() => {}),
        productsStore.fetchCategories().catch(() => {}),
    ]).then(() => loadStock());
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

.page-title h1 i {
    color: var(--el-color-primary);
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

.overview-cards {
    margin-bottom: 1rem;
}

.stat-card-wrapper {
    border-radius: 1rem;
    border: 1px solid var(--border-color);
    margin-bottom: 1rem;
    transition: box-shadow 0.25s ease, transform 0.25s ease;
}

.stat-card-wrapper:hover {
    transform: translateY(-3px);
    box-shadow: var(--card-shadow-hover, 0 10px 25px rgba(0, 0, 0, 0.08));
}

.stat-card-inner {
    display: flex;
    align-items: center;
    gap: 1.1rem;
}

.stat-icon-box {
    width: 52px;
    height: 52px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 1.35rem;
    flex-shrink: 0;
}

.blue-grad { background: linear-gradient(135deg, var(--accent-blue, #005a9c) 0%, #3b82f6 100%); }
.green-grad { background: linear-gradient(135deg, var(--success, #10b981) 0%, #059669 100%); }
.red-grad { background: linear-gradient(135deg, var(--danger, #ef4444) 0%, #dc2626 100%); }
.gold-grad { background: linear-gradient(135deg, var(--accent-gold, #c9a959) 0%, #d4af37 100%); }

.stat-details h3 {
    margin: 0;
    font-size: 1.55rem;
    font-weight: 700;
    color: var(--text-dark);
    line-height: 1.2;
}

.stat-details p {
    margin: 0.25rem 0 0;
    color: var(--text-muted);
    font-size: 0.82rem;
    font-weight: 500;
}

.panel {
    border-radius: 1rem;
    border: 1px solid var(--border-color);
    margin-bottom: 1rem;
}

.card-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 700;
    color: var(--text-dark);
}

.card-header i {
    color: var(--el-color-primary);
}

.card-header-split {
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.table-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.warehouse-list {
    display: flex;
    flex-direction: column;
    gap: 1.1rem;
    min-height: 120px;
}

.warehouse-row .warehouse-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.4rem;
}

.wh-code {
    color: var(--text-muted);
    font-size: 0.8rem;
    margin-inline-start: 0.4rem;
}

.wh-qty {
    font-weight: 700;
    color: var(--text-dark);
    white-space: nowrap;
}

.wh-gross {
    font-weight: 500;
    font-size: 0.8rem;
    color: #94a3b8;
}

.today-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 0.9rem;
}

.today-item {
    display: flex;
    align-items: center;
    gap: 0.8rem;
}

.today-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 1.1rem;
}

.today-item h3 {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--text-dark);
}

.today-item p {
    margin: 0;
    font-size: 0.8rem;
    color: var(--text-muted);
}

.alert-list {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    max-height: 260px;
    overflow-y: auto;
}

.alert-item {
    display: flex;
    gap: 0.7rem;
    align-items: flex-start;
    padding: 0.6rem 0.8rem;
    background: var(--danger-light, #fee2e2);
    border: 1px solid color-mix(in srgb, var(--danger, #ef4444) 25%, white);
    border-radius: 10px;
}

.alert-icon {
    color: var(--danger, #ef4444);
    margin-top: 0.2rem;
}

.alert-text strong {
    font-size: 0.9rem;
    color: var(--text-dark);
}

.alert-text p {
    margin: 0.15rem 0 0;
    font-size: 0.78rem;
    color: var(--text-muted);
}

.table-sub {
    margin: 0.1rem 0 0;
    font-size: 0.78rem;
    color: var(--text-muted);
    font-weight: 400;
}

.text-success { color: var(--success, #059669); }
.text-danger { color: var(--danger, #dc2626); }
.text-warning { color: var(--warning, #d97706); }

.pagination-row {
    display: flex;
    justify-content: flex-end;
    padding-top: 1rem;
}

.drawer-footer {
    border-top: 1px solid var(--border-color);
    margin-top: 2rem;
    padding-top: 1.25rem;
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}

.w-100 {
    width: 100%;
}

.product-name-cell {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.quick-edit-button {
    font-size: 0.75rem;
    padding: 2px 8px;
    color: #e6a23c;
}
</style>
