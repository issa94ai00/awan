<template>
    <div class="products-index">
        <AdminPageHeader
            icon="fas fa-boxes-stacked"
            :title="$t('product_management')"
            :subtitle="$t('prod_admin_subtitle')"
        >
            <template #actions>
                <el-dropdown trigger="click" @command="onToolsCommand">
                    <el-button :loading="exporting || importing">
                        <el-icon><Files /></el-icon>
                        <span>{{ $t('prod_admin_excel') }}</span>
                        <el-icon class="el-icon--right"><ArrowDown /></el-icon>
                    </el-button>
                    <template #dropdown>
                        <el-dropdown-menu>
                            <el-dropdown-item command="export" :icon="Download">
                                {{ activeFilterCount ? $t('prod_admin_export_filtered') : $t('export_to_excel') }}
                            </el-dropdown-item>
                            <el-dropdown-item command="import" :icon="Upload">
                                {{ $t('import_from_excel') }}
                            </el-dropdown-item>
                        </el-dropdown-menu>
                    </template>
                </el-dropdown>
                <el-button type="primary" :icon="Plus" @click="goToCreate">
                    {{ $t('add_a_product') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <input ref="fileInput" type="file" accept=".xlsx" class="visually-hidden" @change="onFileSelected" />

        <AdminStatGrid :min="165">
            <el-card
                v-for="card in statCards"
                :key="card.key"
                shadow="hover"
                class="stat-card is-clickable"
                :class="{ 'is-selected': card.isSelected() }"
                @click="card.apply()"
            >
                <div class="stat-card-inner">
                    <div class="stat-icon-box" :class="card.key">
                        <el-icon><component :is="card.icon" /></el-icon>
                    </div>
                    <div class="stat-details">
                        <h3>{{ summary ? formatCount(card.value) : '—' }}</h3>
                        <p>{{ card.title }}</p>
                    </div>
                </div>
            </el-card>
        </AdminStatGrid>

        <div class="panel-card">
            <div class="filters">
                <el-input
                    v-model="filters.search"
                    class="filter-search"
                    :placeholder="$t('prod_admin_search_placeholder')"
                    :prefix-icon="Search"
                    clearable
                    @input="onSearchInput"
                />

                <el-select
                    v-model="filters.category_id"
                    class="filter-select filter-category"
                    :placeholder="$t('all_categories')"
                    clearable
                    filterable
                    @change="applyFilters"
                >
                    <el-option
                        v-for="cat in categoryOptions"
                        :key="cat.id"
                        :label="cat.label"
                        :value="cat.id"
                    >
                        <span :class="{ 'option-child': cat.isChild, 'option-section': !cat.isChild }">
                            {{ cat.label }}
                        </span>
                        <span v-if="!cat.is_active" class="option-muted">· {{ $t('inactive') }}</span>
                    </el-option>
                </el-select>

                <el-select
                    v-model="filters.stock_level"
                    class="filter-select"
                    :placeholder="$t('prod_admin_any_stock')"
                    clearable
                    @change="applyFilters"
                >
                    <el-option :label="$t('prod_admin_stock_available')" value="available" />
                    <el-option :label="$t('prod_admin_stock_low')" value="low" />
                    <el-option :label="$t('prod_admin_stock_out')" value="out" />
                </el-select>

                <el-select
                    v-model="filters.featured"
                    class="filter-select"
                    :placeholder="$t('prod_admin_any_featured')"
                    clearable
                    @change="applyFilters"
                >
                    <el-option :label="$t('prod_admin_featured_only')" :value="1" />
                    <el-option :label="$t('prod_admin_not_featured')" :value="0" />
                </el-select>

                <el-radio-group v-model="filters.status" class="filter-status" @change="applyFilters">
                    <el-radio-button value="">{{ $t('cat_admin_all') }}</el-radio-button>
                    <el-radio-button value="1">{{ $t('active') }}</el-radio-button>
                    <el-radio-button value="0">{{ $t('inactive') }}</el-radio-button>
                </el-radio-group>

                <el-button v-if="activeFilterCount" :icon="RefreshLeft" @click="resetFilters">
                    {{ $t('prod_admin_clear_filters', { count: activeFilterCount }) }}
                </el-button>
            </div>

            <transition name="bulk">
                <div v-if="selectedIds.length" class="bulk-bar">
                    <span class="bulk-count">
                        <el-icon><Select /></el-icon>
                        {{ $t('prod_admin_selected', { count: selectedIds.length }) }}
                    </span>
                    <span class="bulk-spacer" />
                    <el-button size="small" :icon="Check" :loading="bulkBusy === 'activate'" @click="bulkSetActive(true)">
                        {{ $t('activation') }}
                    </el-button>
                    <el-button size="small" :icon="Close" :loading="bulkBusy === 'deactivate'" @click="bulkSetActive(false)">
                        {{ $t('disable') }}
                    </el-button>
                    <el-button size="small" type="danger" plain :icon="Delete" :loading="bulkBusy === 'delete'" @click="bulkDelete">
                        {{ $t('delete') }}
                    </el-button>
                    <el-button size="small" text @click="clearSelection">{{ $t('deselect') }}</el-button>
                </div>
            </transition>

            <el-result v-if="loadError && !products.length" icon="error" :title="$t('failed_to_bring_products')">
                <template #extra>
                    <el-button type="primary" :icon="Refresh" @click="fetchProducts">{{ $t('cat_admin_retry') }}</el-button>
                </template>
            </el-result>

            <div v-else class="table-wrapper">
                <el-table
                    ref="tableRef"
                    v-loading="loading"
                    :data="products"
                    row-key="id"
                    style="width: 100%"
                    class="products-table"
                    :row-class-name="rowClassName"
                    :default-sort="defaultSort"
                    @selection-change="onSelectionChange"
                    @sort-change="onSortChange"
                >
                    <template #empty>
                        <el-empty v-if="!loading && activeFilterCount" :description="$t('prod_admin_no_matches')" :image-size="90">
                            <el-button @click="resetFilters">{{ $t('cat_admin_clear_filters') }}</el-button>
                        </el-empty>
                        <el-empty v-else-if="!loading" :description="$t('there_are_no_products')" :image-size="90">
                            <el-button type="primary" :icon="Plus" @click="goToCreate">{{ $t('add_a_product') }}</el-button>
                        </el-empty>
                        <span v-else />
                    </template>

                    <el-table-column type="selection" width="44" reserve-selection />

                    <el-table-column :label="$t('product')" min-width="210" prop="name_ar" sortable="custom">
                        <template #default="{ row }">
                            <div class="product-cell">
                                <div class="product-img-cell">
                                    <EntityImage
                                        :src="rowImages(row)[0] || ''"
                                        type="product"
                                        :size="56"
                                        :preview-src-list="rowImages(row)"
                                    />
                                    <span v-if="rowImages(row).length > 1" class="gallery-count">
                                        <el-icon :size="10"><Picture /></el-icon>{{ rowImages(row).length }}
                                    </span>
                                </div>
                                <div class="cell-stack">
                                    <router-link :to="showRoute(row)" class="product-name">
                                        {{ row.name_ar || row.name_en }}
                                    </router-link>
                                    <span v-if="row.name_en && row.name_en !== row.name_ar" class="cell-secondary" dir="ltr">
                                        {{ row.name_en }}
                                    </span>
                                    <span class="cell-meta">
                                        <el-tooltip v-if="row.sku" :content="$t('prod_admin_copy_sku')" placement="top" :enterable="false">
                                            <button type="button" class="sku-chip" dir="ltr" @click="copySku(row.sku)">
                                                {{ row.sku }}
                                                <el-icon :size="11"><DocumentCopy /></el-icon>
                                            </button>
                                        </el-tooltip>
                                        <span v-if="row.variants_count || row.variants?.length" class="variant-chip">
                                            {{ $t('prod_admin_variants', { count: row.variants_count || row.variants.length }) }}
                                        </span>
                                    </span>
                                </div>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('category')" min-width="110">
                        <template #default="{ row }">
                            <button
                                v-if="row.category"
                                type="button"
                                class="category-chip"
                                :title="$t('prod_admin_filter_by_category')"
                                @click="filterByCategory(row.category.id)"
                            >
                                {{ row.category.name_ar || row.category.name_en }}
                            </button>
                            <span v-else class="cell-empty">{{ $t('without_category') }}</span>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('the_price')" width="100" prop="price" sortable="custom" align="center">
                        <template #default="{ row }">
                            <div class="price-cell">
                                <span class="current-price">{{ formatPrice(row.price) }}</span>
                                <span class="currency">{{ row.currency || baseCurrencyCode() }}</span>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('cost')" width="105" prop="cost_price" sortable="custom" align="center">
                        <template #default="{ row }">
                            <div v-if="Number(row.cost_price) > 0" class="price-cell">
                                <span class="cost-value">{{ formatPrice(row.cost_price) }}</span>
                                <span
                                    class="margin-chip"
                                    :class="marginTone(row)"
                                    :title="$t('prod_admin_margin', { value: margin(row) })"
                                    dir="ltr"
                                >
                                    {{ margin(row) }}%
                                </span>
                            </div>
                            <span v-else class="cell-empty">—</span>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('inventory')" width="100" prop="stock_quantity" sortable="custom" align="center">
                        <template #default="{ row }">
                            <el-tooltip :content="stockHint(row)" placement="top" :enterable="false">
                                <span class="stock-pill" :class="stockLevel(row)">
                                    <span class="stock-dot" />
                                    {{ formatCount(row.stock_quantity ?? 0) }}
                                </span>
                            </el-tooltip>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('status')" width="100" align="center">
                        <template #default="{ row }">
                            <div class="status-cell">
                                <el-switch
                                    :model-value="row.is_active"
                                    :loading="savingId === row.id"
                                    class="status-switch"
                                    :aria-label="$t('status')"
                                    @change="(val) => toggleField(row, 'is_active', val)"
                                />
                                <span class="status-label" :class="{ 'is-on': row.is_active }">
                                    {{ row.is_active ? $t('active') : $t('inactive') }}
                                </span>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('featured')" width="64" align="center">
                        <template #default="{ row }">
                            <el-tooltip
                                :content="row.is_featured ? $t('prod_admin_unfeature') : $t('prod_admin_feature')"
                                placement="top"
                                :enterable="false"
                            >
                                <button
                                    type="button"
                                    class="star-toggle"
                                    :class="{ 'is-on': row.is_featured }"
                                    :disabled="savingId === row.id"
                                    :aria-pressed="!!row.is_featured"
                                    @click="toggleField(row, 'is_featured', !row.is_featured)"
                                >
                                    <el-icon :size="20"><StarFilled v-if="row.is_featured" /><Star v-else /></el-icon>
                                </button>
                            </el-tooltip>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('procedures')" width="200" align="center">
                        <template #default="{ row }">
                            <div class="row-actions">
                                <el-tooltip :content="$t('quick_edit')" placement="top" :enterable="false">
                                    <el-button size="small" circle :icon="Lightning" @click="quickEdit(row)" />
                                </el-tooltip>
                                <el-tooltip :content="$t('full_edit')" placement="top" :enterable="false">
                                    <el-button size="small" circle :icon="Edit" @click="editProduct(row)" />
                                </el-tooltip>
                                <el-tooltip :content="$t('view')" placement="top" :enterable="false">
                                    <el-button size="small" circle :icon="View" @click="viewProduct(row)" />
                                </el-tooltip>
                                <el-tooltip :content="$t('delete')" placement="top" :enterable="false">
                                    <el-button size="small" circle plain type="danger" :icon="Delete" @click="deleteProduct(row)" />
                                </el-tooltip>
                            </div>
                        </template>
                    </el-table-column>
                </el-table>
            </div>

            <div v-if="total > 0" class="pagination-row">
                <span class="pagination-summary">
                    {{ $t('prod_admin_range', { from: rangeFrom, to: rangeTo, total: formatCount(total) }) }}
                </span>
                <el-pagination
                    v-model:current-page="currentPage"
                    v-model:page-size="pageSize"
                    :total="total"
                    :page-sizes="[10, 20, 50, 100]"
                    :layout="isNarrow ? 'prev, pager, next' : 'sizes, prev, pager, next, jumper'"
                    :pager-count="isNarrow ? 5 : 7"
                    background
                    @size-change="onSizeChange"
                    @current-change="onPageChange"
                />
            </div>
        </div>

        <!-- Quick Edit Dialog -->
        <el-dialog
            v-model="quickEditDialogVisible"
            width="640px"
            :show-close="false"
            :close-on-click-modal="false"
            :close-on-press-escape="false"
            class="quick-edit-dialog"
        >
            <template #header>
                <div class="qe-header">
                    <EntityImage :src="quickEditProduct?.image_main" type="product" :size="46" />
                    <div class="qe-header-info">
                        <span class="qe-header-name">{{ quickEditForm.name_ar || quickEditForm.name_en || $t('quick_edit') }}</span>
                        <span v-if="quickEditProduct?.sku" class="qe-header-sku">SKU: {{ quickEditProduct.sku }}</span>
                    </div>
                    <el-button :icon="Close" circle text size="small" @click="closeQuickEdit" />
                </div>
            </template>

            <el-form :model="quickEditForm" label-position="top" class="qe-form" @submit.prevent="submitQuickEdit">
                <div class="qe-section">
                    <h4 class="qe-section-title">{{ $t('product') }}</h4>
                    <el-row :gutter="16">
                        <el-col :xs="24" :sm="12">
                            <el-form-item :label="$t('name_arabic')" required>
                                <el-input v-model="quickEditForm.name_ar" :class="{ 'qe-input-error': nameError }" />
                                <span v-if="nameError" class="qe-field-error">{{ nameError }}</span>
                            </el-form-item>
                        </el-col>
                        <el-col :xs="24" :sm="12">
                            <el-form-item :label="$t('name_english')">
                                <el-input v-model="quickEditForm.name_en" dir="ltr" />
                            </el-form-item>
                        </el-col>
                    </el-row>
                </div>

                <div class="qe-section">
                    <div class="qe-section-header">
                        <h4 class="qe-section-title">{{ $t('the_price') }}</h4>
                        <span v-if="marginPercent !== null" class="qe-margin" :class="marginClass">
                            <el-icon :size="13"><Coin /></el-icon>
                            {{ $t('prod_admin_margin', { value: marginPercent }) }}
                        </span>
                    </div>
                    <el-row :gutter="16">
                        <el-col :xs="24" :sm="12">
                            <el-form-item :label="$t('price')">
                                <el-input-number v-model="quickEditForm.price" :min="0" :step="0.5" controls-position="right" style="width: 100%" />
                            </el-form-item>
                        </el-col>
                        <el-col :xs="24" :sm="12">
                            <el-form-item :label="$t('cost_price')">
                                <el-input-number v-model="quickEditForm.cost_price" :min="0" :step="0.5" controls-position="right" style="width: 100%" />
                            </el-form-item>
                        </el-col>
                    </el-row>
                    <el-alert
                        v-if="priceWarning"
                        :title="priceWarning"
                        type="warning"
                        :closable="false"
                        show-icon
                        class="qe-alert"
                    />
                </div>

                <div class="qe-section">
                    <h4 class="qe-section-title">{{ $t('inventory') }}</h4>
                    <el-row :gutter="16">
                        <el-col :xs="24" :sm="12">
                            <el-form-item :label="$t('prod_admin_warehouse')">
                                <el-select
                                    v-model="quickEditForm.warehouse_id"
                                    style="width: 100%"
                                    :loading="warehousesLoading"
                                    :disabled="stockLoading"
                                    @change="onWarehouseChange"
                                >
                                    <el-option
                                        v-for="wh in warehouses"
                                        :key="wh.id"
                                        :label="wh.is_primary ? `${wh.name} (${$t('prod_admin_primary')})` : wh.name"
                                        :value="wh.id"
                                    />
                                </el-select>
                            </el-form-item>
                        </el-col>
                        <el-col :xs="24" :sm="12">
                            <el-form-item :label="$t('quantity')">
                                <el-input-number
                                    v-model="quickEditForm.stock_quantity"
                                    :min="0"
                                    controls-position="right"
                                    style="width: 100%"
                                    :disabled="stockLoading"
                                />
                            </el-form-item>
                        </el-col>
                    </el-row>
                    <div class="qe-stock-hint">
                        <span v-if="stockLoading" class="qe-stock-loading">
                            <el-icon class="is-loading"><Loading /></el-icon> {{ $t('prod_admin_loading_stock') }}
                        </span>
                        <template v-else>
                            <span>{{ $t('prod_admin_current_stock') }} <strong>{{ currentWarehouseQty }}</strong></span>
                            <span v-if="stockDelta !== 0" class="qe-stock-delta" :class="stockDelta > 0 ? 'positive' : 'negative'">
                                {{ stockDelta > 0 ? `+${stockDelta}` : stockDelta }}
                            </span>
                        </template>
                    </div>
                </div>

                <div class="qe-section">
                    <el-row :gutter="16" align="middle">
                        <el-col :xs="24" :sm="12">
                            <el-form-item :label="$t('the_category')">
                                <el-select v-model="quickEditForm.category_id" :placeholder="$t('select_category')" style="width: 100%" filterable>
                                    <el-option
                                        v-for="cat in categoryOptions"
                                        :key="cat.id"
                                        :label="cat.label"
                                        :value="cat.id"
                                    >
                                        <span :class="{ 'option-child': cat.isChild, 'option-section': !cat.isChild }">{{ cat.label }}</span>
                                    </el-option>
                                </el-select>
                            </el-form-item>
                        </el-col>
                        <el-col :xs="24" :sm="12">
                            <div class="qe-switches">
                                <div class="qe-switch-item">
                                    <span>{{ $t('status') }}</span>
                                    <el-switch v-model="quickEditForm.is_active" />
                                </div>
                                <div class="qe-switch-item">
                                    <span>{{ $t('featured') }}</span>
                                    <el-switch v-model="quickEditForm.is_featured" />
                                </div>
                            </div>
                        </el-col>
                    </el-row>
                </div>
            </el-form>

            <template #footer>
                <el-button @click="closeQuickEdit">{{ $t('cancel') }}</el-button>
                <el-button
                    type="primary"
                    :loading="quickEditSubmitting"
                    :disabled="!isDirty || !!nameError"
                    @click="submitQuickEdit"
                >
                    {{ $t('save') }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';
import EntityImage from '@/components/admin/EntityImage.vue';
import { baseCurrencyCode } from '@/utils/currency';
import { productImages } from '@/utils/productImages';
import { useI18n } from 'vue-i18n';
import { ref, reactive, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useProductsStore } from '@/stores/products';
import { productsApi } from '@/api/products';
import { inventoryApi } from '@/api/inventory';
import {
    Plus, Search, Refresh, RefreshLeft, View, Edit, Delete, Lightning, Select,
    Star, StarFilled, Check, Close, Picture, Download, Upload, Coin, Loading,
    Files, ArrowDown, DocumentCopy, Goods, CircleCheck, CircleClose, Warning, RemoveFilled,
} from '@element-plus/icons-vue';

const { t } = useI18n();
const router = useRouter();
const route = useRoute();
const store = useProductsStore();

// ── Filters, sort and paging ─────────────────────────────────────────────
// All of it lives in the URL, so a filtered list survives a reload, the back
// button returns to the same page, and a view can be shared as a link.
const filters = reactive({
    search: '',
    category_id: null,
    status: '',
    stock_level: '',
    featured: null,
});
const sort = reactive({ by: 'created_at', order: 'desc' });
const currentPage = ref(1);
const pageSize = ref(20);

const SORT_PROPS = { name_ar: 'name_ar', price: 'price', cost_price: 'cost_price', stock_quantity: 'stock_quantity' };

const readQuery = () => {
    const q = route.query;
    filters.search = q.search ? String(q.search) : '';
    filters.category_id = q.category_id ? Number(q.category_id) || null : null;
    filters.status = q.status === '1' || q.status === '0' ? q.status : '';
    filters.stock_level = ['available', 'low', 'out'].includes(q.stock) ? q.stock : '';
    filters.featured = q.featured === '1' ? 1 : q.featured === '0' ? 0 : null;
    sort.by = SORT_PROPS[q.sort] ? q.sort : 'created_at';
    sort.order = q.order === 'asc' ? 'asc' : 'desc';
    currentPage.value = Math.max(1, Number(q.page) || 1);
    pageSize.value = [10, 20, 50, 100].includes(Number(q.per_page)) ? Number(q.per_page) : 20;
};

const writeQuery = () => {
    const query = {
        search: filters.search || undefined,
        category_id: filters.category_id || undefined,
        status: filters.status || undefined,
        stock: filters.stock_level || undefined,
        featured: isFeaturedSet() ? filters.featured : undefined,
        sort: sort.by !== 'created_at' ? sort.by : undefined,
        order: sort.by !== 'created_at' ? sort.order : undefined,
        page: currentPage.value > 1 ? currentPage.value : undefined,
        per_page: pageSize.value !== 20 ? pageSize.value : undefined,
    };
    Object.keys(query).forEach((k) => query[k] === undefined && delete query[k]);
    lastQueryKey = queryKey(query);
    // The edit form and product page return here, to this same view.
    store.adminListQuery = query;
    router.replace({ query });
};

// Query values come back from the router as strings, so compare them as such.
const queryKey = (query) => Object.entries(query)
    .map(([k, v]) => `${k}=${v}`)
    .sort()
    .join('&');
let lastQueryKey = null;

const defaultSort = computed(() => (sort.by !== 'created_at'
    ? { prop: sort.by, order: sort.order === 'asc' ? 'ascending' : 'descending' }
    : {}));

// A cleared el-select sets its model to undefined, so only 0 and 1 count.
const isFeaturedSet = () => filters.featured === 0 || filters.featured === 1;

const activeFilterCount = computed(() => [
    filters.search, filters.category_id, filters.status, filters.stock_level, isFeaturedSet() ? '1' : '',
].filter(Boolean).length);

const requestParams = () => ({
    page: currentPage.value,
    per_page: pageSize.value,
    search: filters.search || undefined,
    category_id: filters.category_id || undefined,
    is_active: filters.status === '' ? undefined : filters.status,
    stock_level: filters.stock_level || undefined,
    featured: isFeaturedSet() ? filters.featured : undefined,
    stock: undefined,
    sort_by: sort.by,
    sort_order: sort.order,
});

// ── Data ─────────────────────────────────────────────────────────────────
const products = computed(() => store.products);
const loading = computed(() => store.loading);
const total = computed(() => store.pagination.total);
const summary = computed(() => store.summary);
const loadError = ref(false);

const fetchProducts = async ({ withSummary = false } = {}) => {
    loadError.value = false;
    try {
        await store.fetchProducts({ ...requestParams(), with_summary: withSummary });
    } catch {
        loadError.value = true;
        ElMessage.error(t('failed_to_bring_products'));
    }
};

const applyFilters = () => {
    clearTimeout(searchTimeout);
    currentPage.value = 1;
    writeQuery();
    fetchProducts();
};

let searchTimeout = null;
// An emptied box (the clear button or backspacing to nothing) applies at once;
// the clear button fires `input` too, so it has no handler of its own.
const onSearchInput = (value) => {
    clearTimeout(searchTimeout);
    if (!value) applyFilters();
    else searchTimeout = setTimeout(applyFilters, 400);
};

const resetFilters = () => {
    Object.assign(filters, { search: '', category_id: null, status: '', stock_level: '', featured: null });
    applyFilters();
};

const filterByCategory = (id) => {
    filters.category_id = id;
    applyFilters();
};

const onSortChange = ({ prop, order }) => {
    if (!order || !SORT_PROPS[prop]) {
        sort.by = 'created_at';
        sort.order = 'desc';
    } else {
        sort.by = SORT_PROPS[prop];
        sort.order = order === 'ascending' ? 'asc' : 'desc';
    }
    currentPage.value = 1;
    writeQuery();
    fetchProducts();
};

const onSizeChange = () => {
    currentPage.value = 1;
    writeQuery();
    fetchProducts();
};

const onPageChange = () => {
    writeQuery();
    fetchProducts();
};

const rangeFrom = computed(() => (total.value ? (currentPage.value - 1) * pageSize.value + 1 : 0));
const rangeTo = computed(() => Math.min(currentPage.value * pageSize.value, total.value));

// ── Categories: sections first, each followed by its subcategories ─────────
const categoryOptions = computed(() => {
    const all = store.categories || [];
    const ids = new Set(all.map((c) => c.id));
    const byOrder = (a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0) || a.id - b.id;
    const name = (c) => c.name_ar || c.name_en || c.name;
    const tops = all.filter((c) => !c.parent_id || !ids.has(c.parent_id)).sort(byOrder);

    return tops.flatMap((top) => [
        { id: top.id, label: name(top), isChild: false, is_active: top.is_active !== false },
        ...all.filter((c) => c.parent_id === top.id).sort(byOrder)
            .map((c) => ({ id: c.id, label: name(c), isChild: true, is_active: c.is_active !== false })),
    ]);
});

// ── Stat cards (also shortcuts to the matching filter) ─────────────────────
const statCards = computed(() => {
    const s = summary.value || {};
    const onlyFilter = (patch) => () => {
        const isSame = Object.entries(patch).every(([k, v]) => filters[k] === v);
        Object.assign(filters, { status: '', stock_level: '' }, isSame ? {} : patch);
        applyFilters();
    };
    return [
        {
            key: 'total', icon: Goods, title: t('prod_admin_total'), value: s.total,
            apply: resetFilters, isSelected: () => false,
        },
        {
            key: 'active', icon: CircleCheck, title: t('active'), value: s.active,
            apply: onlyFilter({ status: '1' }), isSelected: () => filters.status === '1',
        },
        {
            key: 'inactive', icon: CircleClose, title: t('inactive'), value: s.inactive,
            apply: onlyFilter({ status: '0' }), isSelected: () => filters.status === '0',
        },
        {
            key: 'low', icon: Warning, title: t('prod_admin_stock_low'), value: s.low_stock,
            apply: onlyFilter({ stock_level: 'low' }), isSelected: () => filters.stock_level === 'low',
        },
        {
            key: 'out', icon: RemoveFilled, title: t('prod_admin_stock_out'), value: s.out_of_stock,
            apply: onlyFilter({ stock_level: 'out' }), isSelected: () => filters.stock_level === 'out',
        },
    ];
});

// ── Row presentation ─────────────────────────────────────────────────────
const rowImages = (row) => productImages(row);

const formatPrice = (price) => {
    if (price === null || price === undefined) return '0.00';
    return Number(price).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatCount = (value) => Number(value ?? 0).toLocaleString('en-US');

const lowThreshold = (row) => (Number(row.min_stock) > 0 ? Number(row.min_stock) : 10);

const stockLevel = (row) => {
    const qty = Number(row.stock_quantity) || 0;
    if (qty <= 0) return 'out';
    if (qty <= lowThreshold(row)) return 'low';
    return 'ok';
};

const stockHint = (row) => ({
    out: t('prod_admin_stock_out'),
    low: t('prod_admin_stock_low_hint', { min: lowThreshold(row) }),
    ok: t('prod_admin_stock_available'),
}[stockLevel(row)]);

const margin = (row) => {
    const price = Number(row.price) || 0;
    const cost = Number(row.cost_price) || 0;
    if (!price) return 0;
    return Math.round(((price - cost) / price) * 1000) / 10;
};

const marginTone = (row) => {
    const value = margin(row);
    if (value < 0) return 'bad';
    if (value < 15) return 'low';
    return 'good';
};

const rowClassName = ({ row }) => (row.is_active ? '' : 'row-inactive');

const copySku = async (sku) => {
    try {
        await navigator.clipboard.writeText(sku);
        ElMessage.success(t('prod_admin_sku_copied'));
    } catch {
        ElMessage.info(sku);
    }
};

const isNarrow = ref(false);
const onResize = () => { isNarrow.value = window.innerWidth < 768; };

// ── Navigation ───────────────────────────────────────────────────────────
const showRoute = (product) => ({ name: 'admin.products.show', params: { id: product.id } });
const goToCreate = () => router.push('/admin/products/create');
const viewProduct = (product) => router.push(showRoute(product));
const editProduct = (product) => router.push({ name: 'admin.products.edit', params: { id: product.id } });

// ── Inline toggles ───────────────────────────────────────────────────────
const savingId = ref(null);

/** Writes one field and patches the row in place, so the table never blinks. */
const toggleField = async (product, field, value) => {
    savingId.value = product.id;
    try {
        await productsApi.update(product.id, { [field]: value });
        product[field] = value;
        if (field === 'is_active') {
            ElMessage.success(value ? t('activated') : t('disabled'));
        } else {
            ElMessage.success(value ? t('prod_admin_now_featured') : t('prod_admin_now_unfeatured'));
        }
        refreshSummary();
    } catch (error) {
        ElMessage.error(error.response?.data?.message || t('failed_to_change_status'));
    } finally {
        savingId.value = null;
    }
};

// Keeps the cards honest after an edit without reloading the visible page.
const refreshSummary = async () => {
    try {
        const response = await productsApi.getAll({ per_page: 1, with_summary: 1 });
        if (response.data?.summary) store.summary = response.data.summary;
    } catch { /* the cards just stay as they were */ }
};

// ── Selection and bulk actions ───────────────────────────────────────────
const tableRef = ref(null);
const selectedIds = ref([]);
const bulkBusy = ref(null);

const onSelectionChange = (selection) => {
    selectedIds.value = selection.map((p) => p.id);
};

const clearSelection = () => {
    tableRef.value?.clearSelection();
    selectedIds.value = [];
};

const bulkSetActive = async (value) => {
    bulkBusy.value = value ? 'activate' : 'deactivate';
    try {
        const result = await store.bulkUpdateStatus(selectedIds.value, value);
        const count = result.succeeded.length;
        ElMessage.success(value
            ? t('prod_admin_bulk_activated', { count })
            : t('bulk_disabled_products', { count }));
        if (result.failed) ElMessage.warning(t('prod_admin_bulk_failed', { count: result.failed }));
        clearSelection();
        refreshSummary();
    } catch {
        ElMessage.error(value ? t('mass_activation_failed') : t('failed_to_mass_disrupt'));
    } finally {
        bulkBusy.value = null;
    }
};

const bulkDelete = async () => {
    const count = selectedIds.value.length;
    try {
        await ElMessageBox.confirm(
            t('prod_admin_bulk_delete_confirm', { count }),
            t('confirm_deletion'),
            {
                confirmButtonText: t('delete'),
                cancelButtonText: t('cancel'),
                confirmButtonClass: 'el-button--danger',
                type: 'warning',
            }
        );
    } catch {
        return;
    }

    bulkBusy.value = 'delete';
    try {
        const result = await store.bulkDelete(selectedIds.value);
        ElMessage.success(t('bulk_deleted_products', { count: result.succeeded.length }));
        if (result.failed) ElMessage.warning(t('prod_admin_bulk_failed', { count: result.failed }));
        clearSelection();
        await afterRemoval();
    } catch {
        ElMessage.error(t('failed_to_mass_delete'));
    } finally {
        bulkBusy.value = null;
    }
};

const deleteProduct = async (product) => {
    try {
        await ElMessageBox.confirm(
            t('prod_admin_delete_confirm', { name: product.name_ar || product.name_en }),
            t('confirm_deletion'),
            {
                confirmButtonText: t('delete'),
                cancelButtonText: t('cancel'),
                confirmButtonClass: 'el-button--danger',
                type: 'warning',
            }
        );
    } catch {
        return;
    }

    try {
        await store.deleteProduct(product.id);
        ElMessage.success(t('var_has_been_successfully_deleted'));
        await afterRemoval();
    } catch (error) {
        ElMessage.error(error.response?.data?.message || t('failed_to_delete_product'));
    }
};

// Refills the page after rows go, stepping back one page if this one emptied.
const afterRemoval = async () => {
    if (products.value.length === 0 && currentPage.value > 1) currentPage.value -= 1;
    writeQuery();
    await fetchProducts({ withSummary: true });
};

// ── Excel ────────────────────────────────────────────────────────────────
const exporting = ref(false);
const importing = ref(false);
const fileInput = ref(null);

const onToolsCommand = (command) => {
    if (command === 'export') exportProducts();
    if (command === 'import') fileInput.value?.click();
};

const exportProducts = async () => {
    exporting.value = true;
    try {
        const res = await store.exportExcel({
            search: filters.search || undefined,
            category_id: filters.category_id || undefined,
            featured: isFeaturedSet() ? filters.featured : undefined,
            is_active: filters.status === '' ? undefined : filters.status,
            stock_level: filters.stock_level || undefined,
        });
        const blob = new Blob([res.data], {
            type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `products-${new Date().toISOString().slice(0, 10)}.xlsx`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
        ElMessage.success(t('var_exported_successfully'));
    } catch {
        ElMessage.error(t('failed_to_export_products'));
    } finally {
        exporting.value = false;
    }
};

const onFileSelected = async (event) => {
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
        const res = await store.importExcel(formData);
        const data = res.data?.data || {};
        const summaryText = [
            t('imported_new_products', { count: data.created }),
            t('imported_updated_products', { count: data.updated }),
            t('imported_skipped_products', { count: data.skipped })
        ];
        ElMessage.success(summaryText.join('، '));
        if (data.errors?.length) {
            ElMessage.warning(t('import_failed_rows', { count: data.errors.length }));
        }
        fetchProducts({ withSummary: true });
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('failed_to_import_products'));
    } finally {
        importing.value = false;
    }
};

// ── Quick edit ───────────────────────────────────────────────────────────
const quickEditDialogVisible = ref(false);
const quickEditSubmitting = ref(false);
const quickEditProduct = ref(null);
const warehouses = ref([]);
const warehousesLoading = ref(false);
const stockLoading = ref(false);
const stockByWarehouse = ref({});
const quickEditForm = ref({
    id: null,
    name_ar: '',
    name_en: '',
    price: 0,
    cost_price: null,
    stock_quantity: 0,
    warehouse_id: null,
    category_id: null,
    is_active: true,
    is_featured: false,
});
let quickEditSnapshot = null;

const currentWarehouseQty = computed(() => stockByWarehouse.value[quickEditForm.value.warehouse_id] || 0);
const stockDelta = computed(() => (Number(quickEditForm.value.stock_quantity) || 0) - currentWarehouseQty.value);

const nameError = computed(() => (
    quickEditForm.value.name_ar.trim() === '' ? t('prod_admin_name_required') : ''
));

const marginPercent = computed(() => {
    const price = Number(quickEditForm.value.price) || 0;
    const cost = quickEditForm.value.cost_price;
    if (!price || cost === null || cost === undefined || cost === '') return null;
    return Math.round(((price - Number(cost)) / price) * 1000) / 10;
});

const marginClass = computed(() => {
    if (marginPercent.value === null) return '';
    if (marginPercent.value < 0) return 'qe-margin-bad';
    if (marginPercent.value < 15) return 'qe-margin-low';
    return 'qe-margin-good';
});

const priceWarning = computed(() => {
    const price = Number(quickEditForm.value.price) || 0;
    const cost = quickEditForm.value.cost_price;
    if (cost !== null && cost !== undefined && cost !== '' && Number(cost) > price) {
        return t('prod_admin_cost_above_price');
    }
    return null;
});

const isDirty = computed(() => {
    if (!quickEditSnapshot) return false;
    return JSON.stringify(quickEditForm.value) !== quickEditSnapshot;
});

const ensureWarehousesLoaded = async () => {
    if (warehouses.value.length) return;
    warehousesLoading.value = true;
    try {
        const res = await inventoryApi.getWarehouses({ per_page: 50, is_active: true });
        const list = res.data?.data || [];
        warehouses.value = list.slice().sort((a, b) => {
            if (!!a.is_primary !== !!b.is_primary) return a.is_primary ? -1 : 1;
            return (a.name || '').localeCompare(b.name || '', 'ar');
        });
    } catch {
        ElMessage.error(t('prod_admin_warehouses_failed'));
    } finally {
        warehousesLoading.value = false;
    }
};

const loadStockBreakdown = async (productId) => {
    stockLoading.value = true;
    try {
        const res = await inventoryApi.getStock({ product_id: productId, per_page: 100 });
        const rows = res.data?.data?.stock || [];
        const map = {};
        rows.forEach((row) => { map[row.warehouse_id] = Number(row.quantity) || 0; });
        stockByWarehouse.value = map;
    } catch {
        stockByWarehouse.value = {};
    } finally {
        stockLoading.value = false;
    }
};

const onWarehouseChange = (warehouseId) => {
    // A different warehouse means a different physical count, so the field is
    // reset to what that warehouse actually holds rather than carrying over a
    // number that was meant to correct the previous one.
    quickEditForm.value.stock_quantity = stockByWarehouse.value[warehouseId] || 0;
};

const quickEdit = async (product) => {
    quickEditProduct.value = product;
    stockByWarehouse.value = {};
    // No sale price here: `products` has no such column, so whatever was typed
    // was dropped on save while the dialog reported success.
    quickEditForm.value = {
        id: product.id,
        name_ar: product.name_ar || '',
        name_en: product.name_en || '',
        price: Number(product.price) || 0,
        cost_price: product.cost_price !== null && product.cost_price !== undefined ? Number(product.cost_price) : null,
        stock_quantity: 0,
        warehouse_id: null,
        category_id: product.category_id || product.category?.id || null,
        is_active: product.is_active ?? true,
        is_featured: product.is_featured ?? false,
    };
    quickEditSnapshot = null;
    quickEditDialogVisible.value = true;

    await ensureWarehousesLoaded();
    await loadStockBreakdown(product.id);

    // Default to whichever warehouse already carries the most stock for this
    // product — usually the one an operator meant to recount — falling back
    // to the primary warehouse for a product with no stock anywhere yet.
    let defaultWarehouseId = warehouses.value.find(w => w.is_primary)?.id ?? warehouses.value[0]?.id ?? null;
    let bestQty = 0;
    for (const wh of warehouses.value) {
        const qty = stockByWarehouse.value[wh.id] || 0;
        if (qty > bestQty) {
            bestQty = qty;
            defaultWarehouseId = wh.id;
        }
    }

    quickEditForm.value.warehouse_id = defaultWarehouseId;
    quickEditForm.value.stock_quantity = stockByWarehouse.value[defaultWarehouseId] || 0;

    quickEditSnapshot = JSON.stringify(quickEditForm.value);
};

const closeQuickEdit = () => {
    if (isDirty.value) {
        ElMessageBox.confirm(t('prod_admin_discard_changes'), t('confirm'), {
            confirmButtonText: t('prod_admin_discard'),
            cancelButtonText: t('prod_admin_keep_editing'),
            type: 'warning',
        }).then(() => {
            quickEditDialogVisible.value = false;
        }).catch(() => {});
        return;
    }
    quickEditDialogVisible.value = false;
};

const submitQuickEdit = async () => {
    if (nameError.value || !isDirty.value) return;

    quickEditSubmitting.value = true;
    try {
        const original = JSON.parse(quickEditSnapshot);
        const payload = {};

        ['name_ar', 'name_en', 'price', 'cost_price', 'category_id', 'is_active', 'is_featured'].forEach((field) => {
            if (quickEditForm.value[field] !== original[field]) {
                payload[field] = quickEditForm.value[field];
            }
        });

        if (stockDelta.value !== 0) {
            payload.stock_quantity = quickEditForm.value.stock_quantity;
            payload.warehouse_id = quickEditForm.value.warehouse_id;
        }

        // Patch the row from the response directly instead of refetching the
        // whole list: a quick edit should feel instant, not blink the table.
        const response = await productsApi.update(quickEditForm.value.id, payload);
        const updated = response.data.data;
        const index = store.products.findIndex(p => p.id === updated.id);
        if (index !== -1) store.products[index] = updated;

        ElMessage.success(t('the_product_has_been_updated'));
        quickEditDialogVisible.value = false;
        refreshSummary();
    } catch (error) {
        ElMessage.error(error.response?.data?.message || t('failed_to_update_product'));
    } finally {
        quickEditSubmitting.value = false;
    }
};

// ── Lifecycle ────────────────────────────────────────────────────────────
// Navigating to this list while already on it (the sidebar link, a link with
// ?category_id=) reuses the component, so the new URL has to be read here.
watch(() => route.query, (query) => {
    if (route.name !== 'admin.products.index' || queryKey(query) === lastQueryKey) return;
    readQuery();
    lastQueryKey = queryKey(query);
    store.adminListQuery = { ...query };
    clearSelection();
    fetchProducts();
});

onMounted(async () => {
    onResize();
    window.addEventListener('resize', onResize);
    readQuery();
    lastQueryKey = queryKey(route.query);
    store.adminListQuery = { ...route.query };
    store.fetchCategories();
    await fetchProducts({ withSummary: true });
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', onResize);
    clearTimeout(searchTimeout);
});
</script>

<style scoped>
.products-index {
    --surface: #ffffff;
    --line: #e2e8f0;
    --ink: #0f172a;
    --ink-mute: #64748b;
    --primary: #2563eb;
    --primary-soft: #eff6ff;
    --ok: #16a34a;
    --ok-soft: #f0fdf4;
    --warn: #d97706;
    --warn-soft: #fffbeb;
    --danger: #dc2626;
    --danger-soft: #fef2f2;
    --purple: #7c3aed;
    --purple-soft: #f5f3ff;

    padding: 0;
    color: var(--ink);
}

.visually-hidden { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }

/* ── Stat cards ─────────────────────────────────────────────────────── */
.stat-card {
    border-radius: 14px;
    border: 1px solid transparent;
    transition: border-color 0.15s ease, transform 0.15s ease;
}
.stat-card.is-clickable { cursor: pointer; }
.stat-card.is-clickable:hover { transform: translateY(-1px); }
.stat-card.is-selected { border-color: var(--primary); }

.stat-card-inner { display: flex; align-items: center; gap: 1rem; }

.stat-icon-box {
    width: 48px;
    height: 48px;
    flex-shrink: 0;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
}
.stat-icon-box.total { background: var(--purple-soft); color: var(--purple); }
.stat-icon-box.active { background: var(--ok-soft); color: var(--ok); }
.stat-icon-box.inactive { background: #f1f5f9; color: var(--ink-mute); }
.stat-icon-box.low { background: var(--warn-soft); color: var(--warn); }
.stat-icon-box.out { background: var(--danger-soft); color: var(--danger); }

.stat-details { min-width: 0; }
.stat-details h3 { margin: 0; font-size: 1.4rem; font-weight: 800; line-height: 1.2; }
.stat-details p { margin: 0.2rem 0 0; color: var(--ink-mute); font-size: 0.82rem; font-weight: 600; }

/* ── Panel / filters ────────────────────────────────────────────────── */
.panel-card {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 14px;
    padding: 1.1rem 1.25rem;
    box-shadow: 0 1px 2px rgba(18, 28, 44, 0.04);
}

.filters {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
}
.filter-search { flex: 1 1 260px; max-width: 360px; }
.filter-select { width: 170px; }
.filter-category { width: 220px; }

.option-section { font-weight: 700; }
.option-child { padding-inline-start: 1rem; color: #475569; }
.option-muted { color: #94a3b8; font-size: 0.8rem; margin-inline-start: 0.25rem; }

/* ── Bulk bar ───────────────────────────────────────────────────────── */
.bulk-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
    padding: 0.6rem 0.9rem;
    margin-bottom: 0.9rem;
    border-radius: 10px;
    background: var(--primary-soft);
    border: 1px solid #bfdbfe;
}
.bulk-count { display: inline-flex; align-items: center; gap: 0.4rem; font-weight: 700; color: #1e3a8a; }
.bulk-spacer { flex: 1 1 auto; }
.bulk-bar .el-button + .el-button { margin-inline-start: 0; }

.bulk-enter-active, .bulk-leave-active { transition: opacity 0.15s ease, transform 0.15s ease; }
.bulk-enter-from, .bulk-leave-to { opacity: 0; transform: translateY(-4px); }

/* ── Table ──────────────────────────────────────────────────────────── */
.table-wrapper { border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }

.product-cell { display: flex; align-items: center; gap: 0.75rem; min-width: 0; }

.product-img-cell { position: relative; flex-shrink: 0; }
.product-img-cell :deep(.entity-image) { cursor: zoom-in; }
.product-img-cell :deep(.entity-image--empty) { cursor: default; }

.gallery-count {
    position: absolute;
    bottom: 3px;
    inset-inline-end: 3px;
    display: flex;
    align-items: center;
    gap: 2px;
    padding: 0 4px;
    border-radius: 4px;
    background: rgba(15, 23, 42, 0.7);
    color: #fff;
    font-size: 9px;
    font-weight: 700;
    line-height: 1.5;
}

.cell-stack { display: flex; flex-direction: column; gap: 0.15rem; min-width: 0; }
.product-name {
    font-weight: 700;
    color: var(--ink);
    text-decoration: none;
    line-height: 1.35;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.product-name:hover { color: var(--primary); text-decoration: underline; }
.cell-secondary { font-size: 0.76rem; color: var(--ink-mute); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cell-meta { display: flex; flex-wrap: wrap; gap: 0.35rem; margin-top: 0.1rem; }
.cell-empty { color: #94a3b8; font-size: 0.8rem; }

.sku-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0 0.4rem;
    border: 1px solid var(--line);
    border-radius: 6px;
    background: #f8fafc;
    color: #475569;
    font: 600 0.7rem/1.6 ui-monospace, SFMono-Regular, Menlo, monospace;
    cursor: pointer;
}
.sku-chip:hover { border-color: var(--primary); color: var(--primary); }

.variant-chip {
    padding: 0 0.45rem;
    border-radius: 6px;
    background: var(--purple-soft);
    color: var(--purple);
    font-size: 0.7rem;
    font-weight: 600;
    line-height: 1.6;
}

.category-chip {
    max-width: 100%;
    padding: 0.1rem 0.55rem;
    border: 1px solid var(--line);
    border-radius: 8px;
    background: #fff;
    color: #334155;
    font-size: 0.76rem;
    line-height: 1.5;
    text-align: start;
    cursor: pointer;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.category-chip:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-soft); }

.price-cell { display: flex; flex-direction: column; align-items: center; gap: 0.1rem; }
.current-price { font-weight: 700; font-size: 0.95rem; }
.currency { font-size: 0.68rem; color: var(--ink-mute); }
.cost-value { font-weight: 600; color: #334155; }

.margin-chip { font-size: 0.68rem; font-weight: 700; padding: 0 0.4rem; border-radius: 999px; white-space: nowrap; cursor: help; }
.margin-chip.good { color: #15803d; background: var(--ok-soft); }
.margin-chip.low { color: #b45309; background: var(--warn-soft); }
.margin-chip.bad { color: #b91c1c; background: var(--danger-soft); }

.stock-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    min-width: 56px;
    justify-content: center;
    padding: 0.15rem 0.6rem;
    border-radius: 999px;
    font-weight: 700;
    font-size: 0.85rem;
    cursor: default;
}
.stock-dot { width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
.stock-pill.ok { color: #15803d; background: var(--ok-soft); }
.stock-pill.low { color: #b45309; background: var(--warn-soft); }
.stock-pill.out { color: #b91c1c; background: var(--danger-soft); }

.status-cell { display: inline-flex; align-items: center; gap: 0.3rem; }
.status-switch { --el-switch-on-color: var(--ok); }
.status-label { font-size: 0.74rem; font-weight: 600; color: var(--ink-mute); text-align: start; white-space: nowrap; }
.status-label.is-on { color: var(--ok); }

.star-toggle {
    display: inline-flex;
    padding: 0.25rem;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: #cbd5e1;
    cursor: pointer;
    transition: color 0.15s ease, background 0.15s ease;
}
.star-toggle:hover { background: var(--warn-soft); color: #f59e0b; }
.star-toggle.is-on { color: #f59e0b; }
.star-toggle:disabled { opacity: 0.5; cursor: wait; }

.row-actions { display: flex; flex-wrap: nowrap; gap: 0.3rem; justify-content: center; }
.row-actions .el-button + .el-button { margin-inline-start: 0; }

:deep(.row-inactive .product-cell),
:deep(.row-inactive .price-cell) { opacity: 0.55; }

/* ── Pagination ─────────────────────────────────────────────────────── */
.pagination-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-top: 1rem;
}
.pagination-summary { font-size: 0.8rem; color: var(--ink-mute); }

/* ── Quick Edit dialog ──────────────────────────────────────────────── */
.qe-header { display: flex; align-items: center; gap: 0.75rem; width: 100%; }
.qe-header-info { display: flex; flex-direction: column; gap: 1px; flex: 1; min-width: 0; }
.qe-header-name {
    font-weight: 700;
    font-size: 1.05rem;
    color: #1a1a2e;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.qe-header-sku { font-size: 0.75rem; color: #909399; }

.qe-section { padding: 0.85rem 0; border-bottom: 1px solid #f0f2f5; }
.qe-section:last-child { border-bottom: none; padding-bottom: 0; }
.qe-section-header { display: flex; align-items: center; justify-content: space-between; }
.qe-section-title { margin: 0 0 0.5rem; font-size: 0.85rem; font-weight: 700; color: #606266; }

.qe-margin {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 0.8rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 999px;
}
.qe-margin-good { color: #3d8b40; background: #e8f6e9; }
.qe-margin-low { color: #a06400; background: #fdf3e0; }
.qe-margin-bad { color: #c23934; background: #fce8e6; }

.qe-alert { margin-top: 0.5rem; }
.qe-field-error { display: block; color: #f56c6c; font-size: 0.75rem; margin-top: 2px; }
.qe-input-error :deep(.el-input__wrapper) { box-shadow: 0 0 0 1px #f56c6c inset; }

.qe-stock-hint { display: flex; align-items: center; gap: 0.5rem; font-size: 0.82rem; color: #606266; margin-top: 0.25rem; }
.qe-stock-loading { display: flex; align-items: center; gap: 4px; color: #909399; }
.qe-stock-delta { font-weight: 700; padding: 1px 7px; border-radius: 999px; }
.qe-stock-delta.positive { color: #3d8b40; background: #e8f6e9; }
.qe-stock-delta.negative { color: #c23934; background: #fce8e6; }

.qe-switches { display: flex; align-items: center; gap: 1.5rem; height: 100%; padding-top: 1.6rem; }
.qe-switch-item { display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: #606266; }

@media (max-width: 768px) {
    :deep(.admin-stat-grid) { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: 0.6rem; }
    .stat-card :deep(.el-card__body) { padding: 0.75rem; }
    .stat-card-inner { gap: 0.6rem; }
    .stat-icon-box { width: 38px; height: 38px; font-size: 1.1rem; }
    .stat-details h3 { font-size: 1.15rem; }

    .panel-card { padding: 0.9rem; }
    .filter-search { max-width: none; flex-basis: 100%; }
    .filter-select, .filter-category { width: calc(50% - 0.375rem); }
    .filter-status { width: 100%; display: flex; }
    .filter-status :deep(.el-radio-button) { flex: 1; }
    .filter-status :deep(.el-radio-button__inner) { width: 100%; }
    .pagination-row { justify-content: center; }
    .qe-switches { padding-top: 0; }
}
</style>

<style>
/* Teleported dialog: full width on phones rather than a fixed 640px box. */
@media (max-width: 700px) {
    .quick-edit-dialog { width: calc(100% - 24px) !important; }
}
</style>
