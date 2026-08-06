<template>
    <div class="products-index">
        <el-card shadow="never" class="mb-4">
            <template #header>
                <div class="card-header">
                    <div class="header-left">
                        <h2 class="page-title">{{ $t('product_management') }}</h2>
                        <el-tag v-if="total > 0" type="info" effect="plain">
                            {{ total }} {{ $t('project') }}
                        </el-tag>
                    </div>
                    <el-button type="primary" :icon="Plus" size="large" @click="goToCreate">
                        {{ $t('add_a_product') }}
                    </el-button>
                </div>
            </template>

            <div class="filters-bar">
                <el-row :gutter="16" align="middle">
                    <el-col :xs="24" :sm="12" :md="5">
                        <el-input
                            v-model="searchQuery"
                            :placeholder="$t('search_for_a_product')"
                            :prefix-icon="Search"
                            clearable
                            size="large"
                            @input="onSearchInput"
                        />
                    </el-col>
                    <el-col :xs="12" :sm="6" :md="4">
                        <el-select
                            v-model="filterCategory"
                            :placeholder="$t('all_categories')"
                            clearable
                            size="large"
                            style="width:100%"
                            @change="fetchProducts"
                        >
                            <el-option
                                v-for="cat in categories"
                                :key="cat.id"
                                :label="cat.name_ar || cat.name"
                                :value="cat.id"
                            />
                        </el-select>
                    </el-col>
                    <el-col :xs="12" :sm="6" :md="3">
                        <el-select
                            v-model="filterStatus"
                            :placeholder="$t('status')"
                            clearable
                            size="large"
                            style="width:100%"
                            @change="fetchProducts"
                        >
                            <el-option :label="$t('active')" :value="true" />
                            <el-option :label="$t('inactive')" :value="false" />
                        </el-select>
                    </el-col>
                    <el-col :xs="12" :sm="6" :md="3">
                        <el-select
                            v-model="filterFeatured"
                            :placeholder="$t('distinguished')"
                            clearable
                            size="large"
                            style="width:100%"
                            @change="fetchProducts"
                        >
                            <el-option :label="$t('distinct')" :value="true" />
                            <el-option :label="$t('indiscriminate')" :value="false" />
                        </el-select>
                    </el-col>
                    <el-col :xs="12" :sm="6" :md="3">
                        <el-select
                            v-model="filterStock"
                            :placeholder="$t('inventory')"
                            clearable
                            size="large"
                            style="width:100%"
                            @change="fetchProducts"
                        >
                            <el-option :label="$t('available')" :value="true" />
                            <el-option :label="$t('run_out')" :value="false" />
                        </el-select>
                    </el-col>
                    <el-col :xs="24" :sm="12" :md="3">
                        <el-button :icon="Refresh" size="large" @click="resetFilters">
                            {{ $t('reset') }}
                        </el-button>
                    </el-col>
                </el-row>
            </div>

            <div v-if="hasSelected && products.length" class="bulk-actions-bar">
                <el-alert :title="`${selectedProducts.length} منتج(ة) محدد(ة)`" type="info" :closable="false" show-icon>
                    <template #default>
                        <div class="bulk-actions-inner">
                            <span class="selected-count">{{ selectedProducts.length }} {{ $t('specific_product_s') }}</span>
                            <el-button-group>
                                <el-button size="small" type="success" :icon="Check" @click="bulkActivate">
                                    {{ $t('activation') }}
                                </el-button>
                                <el-button size="small" type="warning" :icon="Close" @click="bulkDeactivate">
                                    {{ $t('disable') }}
                                </el-button>
                                <el-button size="small" type="danger" :icon="Delete" @click="bulkDelete">
                                    {{ $t('delete') }}
                                </el-button>
                            </el-button-group>
                            <el-button size="small" @click="clearSelection">{{ $t('deselect') }}</el-button>
                        </div>
                    </template>
                </el-alert>
            </div>

            <el-table
                v-loading="loading"
                :data="products"
                style="width: 100%"
                stripe
                @selection-change="onSelectionChange"
                class="products-table"
                empty-text="لا توجد منتجات"
            >
                <el-table-column type="selection" width="45" />
                <el-table-column :label="$t('image')" width="100">
                    <template #default="{ row }">
                        <div class="product-img-cell">
                            <el-image
                                :src="row.image_main || '/placeholder.jpg'"
                                fit="cover"
                                class="product-thumb"
                                :preview-src-list="getPreviewList(row)"
                                :preview-teleported="true"
                                lazy
                            >
                                <template #error>
                                    <div class="image-placeholder">
                                        <el-icon :size="22"><Picture /></el-icon>
                                    </div>
                                </template>
                            </el-image>
                            <div class="img-badges">
                                <span v-if="row.sale_price" class="badge badge-sale">Sale</span>
                                <span v-if="row.is_featured" class="badge badge-star">
                                    <el-icon :size="10"><StarFilled /></el-icon>
                                </span>
                            </div>
                            <div v-if="getGalleryCount(row) > 0" class="gallery-count">
                                <el-icon :size="10"><Picture /></el-icon>
                                {{ getGalleryCount(row) + 1 }}
                            </div>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('product')" min-width="180">
                    <template #default="{ row }">
                        <div class="product-cell">
                            <span class="product-name">{{ row.name_ar || row.name_en }}</span>
                            <span v-if="row.sku" class="product-sku">SKU: {{ row.sku }}</span>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('category')" width="130">
                    <template #default="{ row }">
                        <el-tag type="info" effect="plain" size="small">
                            {{ row.category?.name_ar || row.category?.name_en || $t('without_category') }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('the_price')" width="120" sortable="custom" prop="price">
                    <template #default="{ row }">
                        <div class="price-cell">
                            <span class="current-price">{{ formatPrice(row.price) }}</span>
                            <span v-if="row.sale_price" class="sale-price">{{ formatPrice(row.sale_price) }}</span>
                            <span class="currency">{{ row.currency || 'SAR' }}</span>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('cost')" width="100">
                    <template #default="{ row }">
                        <span class="cost-value">{{ row.cost_price ? formatPrice(row.cost_price) : '—' }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('inventory')" width="110" sortable="custom" prop="stock_quantity">
                    <template #default="{ row }">
                        <el-tag :type="getStockType(row.stock_quantity)" size="large" effect="dark">
                            <el-icon style="vertical-align:middle;margin-left:4px">
                                <Box v-if="row.stock_quantity > 0" />
                                <WarningFilled v-else />
                            </el-icon>
                            {{ row.stock_quantity }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('status')" width="100">
                    <template #default="{ row }">
                        <el-switch
                            :model-value="row.is_active"
                            :loading="togglingId === row.id"
                            active-color="#67c23a"
                            inactive-color="#f56c6c"
                            @click.stop
                            @change="(val) => toggleActive(row, val)"
                        />
                    </template>
                </el-table-column>
                <el-table-column :label="$t('distinct')" width="80" align="center">
                    <template #default="{ row }">
                        <el-icon v-if="row.is_featured" color="#e6a23c" :size="22">
                            <StarFilled />
                        </el-icon>
                        <el-icon v-else color="#c0c4cc" :size="22">
                            <Star />
                        </el-icon>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('procedures')" width="140" fixed="right">
                    <template #default="{ row }">
                        <div class="actions-cell">
                            <el-tooltip content="عرض" placement="top">
                                <el-button :icon="View" size="small" text @click="viewProduct(row)" />
                            </el-tooltip>
                            <el-tooltip content="تعديل" placement="top">
                                <el-button :icon="Edit" size="small" text type="primary" @click="editProduct(row)" />
                            </el-tooltip>
                            <el-tooltip content="حذف" placement="top">
                                <el-popconfirm
                                    :title="$t('confirm_deletion')"
                                    :confirm-button-text="$t('yes')"
                                    :cancel-button-text="$t('no')"
                                    @confirm="deleteProduct(row)"
                                >
                                    <template #reference>
                                        <el-button :icon="Delete" size="small" text type="danger" />
                                    </template>
                                </el-popconfirm>
                            </el-tooltip>
                        </div>
                    </template>
                </el-table-column>
            </el-table>

            <div v-if="total > 0" class="pagination-wrapper">
                <el-pagination
                    v-model:current-page="currentPage"
                    v-model:page-size="pageSize"
                    :total="total"
                    :page-sizes="[10, 20, 50, 100]"
                    layout="total, sizes, prev, pager, next, jumper"
                    background
                    @size-change="onSizeChange"
                    @current-change="onPageChange"
                />
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import { useProductsStore } from '@/stores/products';
import {
    Plus, Search, Refresh, View, Edit, Delete,
    Star, StarFilled, Check, Close, Picture, Box, WarningFilled
} from '@element-plus/icons-vue';

const router = useRouter();
const store = useProductsStore();

const searchQuery = ref('');
const filterCategory = ref(null);
const filterStatus = ref(null);
const filterFeatured = ref(null);
const filterStock = ref(null);
const currentPage = ref(1);
const pageSize = ref(10);
const togglingId = ref(null);

const products = computed(() => store.products);
const categories = computed(() => store.categories);
const loading = computed(() => store.loading);
const total = computed(() => store.pagination.total);
const selectedProducts = computed(() => store.selectedProducts);
const hasSelected = computed(() => store.hasSelected);

let searchTimeout = null;
const onSearchInput = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        currentPage.value = 1;
        fetchProducts();
    }, 400);
};

const getStockType = (stock) => {
    if (stock === 0 || stock === null || stock === undefined) return 'danger';
    if (stock <= 10) return 'warning';
    return 'success';
};

const getGalleryCount = (row) => {
    if (!row.image_gallery) return 0;
    if (Array.isArray(row.image_gallery)) return row.image_gallery.length;
    try { return JSON.parse(row.image_gallery).length; } catch { return 0; }
};

const getPreviewList = (row) => {
    const list = [];
    if (row.image_main) list.push(row.image_main);
    if (row.image_gallery) {
        const gallery = Array.isArray(row.image_gallery) ? row.image_gallery : JSON.parse(row.image_gallery || '[]');
        list.push(...gallery);
    }
    return list;
};

const formatPrice = (price) => {
    if (price === null || price === undefined) return '0.00';
    return Number(price).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const fetchProducts = async () => {
    try {
        await store.fetchProducts({
            page: currentPage.value,
            per_page: pageSize.value,
            search: searchQuery.value || undefined,
            category_id: filterCategory.value || undefined,
            featured: filterFeatured.value !== null ? filterFeatured.value : undefined,
            stock: filterStock.value !== null ? filterStock.value : undefined
        });
    } catch {
        ElMessage.error(window.t('failed_to_bring_products'));
    }
};

const resetFilters = () => {
    searchQuery.value = '';
    filterCategory.value = null;
    filterStatus.value = null;
    filterFeatured.value = null;
    filterStock.value = null;
    currentPage.value = 1;
    store.clearFilters();
    fetchProducts();
};

const onSelectionChange = (selection) => {
    store.selectedProducts = selection.map(p => p.id);
};

const clearSelection = () => {
    store.selectedProducts = [];
};

const onSizeChange = () => {
    currentPage.value = 1;
    fetchProducts();
};

const onPageChange = () => {
    fetchProducts();
};

const goToCreate = () => {
    router.push('/admin/products/create');
};

const viewProduct = (product) => {
    router.push({ name: 'admin.products.show', params: { id: product.id } });
};

const editProduct = (product) => {
    router.push({ name: 'admin.products.edit', params: { id: product.id } });
};

const deleteProduct = async (product) => {
    try {
        await store.deleteProduct(product.id);
        ElMessage.success(window.t('var_has_been_successfully_deleted'));
        if (products.value.length === 0 && currentPage.value > 1) {
            currentPage.value--;
            fetchProducts();
        }
    } catch {
        ElMessage.error(window.t('failed_to_delete_product'));
    }
};

const toggleActive = async (product, value) => {
    togglingId.value = product.id;
    try {
        await store.updateProduct(product.id, { is_active: value });
        ElMessage.success(value ? window.t('activated') : window.t('disabled'));
    } catch {
        ElMessage.error(window.t('failed_to_change_status'));
    } finally {
        togglingId.value = null;
    }
};

const bulkActivate = async () => {
    try {
        const result = await store.bulkUpdateStatus(selectedProducts.value, true);
        ElMessage.success(window.t('var_product_has_been_activated'));
    } catch {
        ElMessage.error(window.t('mass_activation_failed'));
    }
};

const bulkDeactivate = async () => {
    try {
        const result = await store.bulkUpdateStatus(selectedProducts.value, false);
        ElMessage.success(window.t('bulk_disabled_products', { count: result.succeeded.length }));
    } catch {
        ElMessage.error(window.t('failed_to_mass_disrupt'));
    }
};

const bulkDelete = async () => {
    try {
        const result = await store.bulkDelete(selectedProducts.value);
        ElMessage.success(window.t('bulk_deleted_products', { count: result.succeeded }));
        if (products.value.length === 0 && currentPage.value > 1) {
            currentPage.value--;
            fetchProducts();
        }
    } catch {
        ElMessage.error(window.t('failed_to_mass_delete'));
    }
};

const init = async () => {
    await store.fetchCategories();
    await fetchProducts();
};

onMounted(init);
</script>

<style scoped>
.products-index {
    padding: 0;
}

.mb-4 {
    margin-bottom: 1.5rem;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.page-title {
    font-size: 1.35rem;
    font-weight: 700;
    margin: 0;
    color: #1a1a2e;
}

.filters-bar {
    margin-bottom: 1rem;
    padding: 0.5rem 0;
}

.bulk-actions-bar {
    margin-bottom: 1rem;
}

.bulk-actions-inner {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.selected-count {
    font-weight: 600;
    margin-left: 0.5rem;
}

.products-table {
    border-radius: 8px;
}

.product-cell {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.product-name {
    font-weight: 600;
    color: #1a1a2e;
    line-height: 1.3;
}

.product-sku {
    font-size: 0.75rem;
    color: #909399;
}

.price-cell {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.current-price {
    font-weight: 700;
    color: #409eff;
    font-size: 1rem;
}

.sale-price {
    text-decoration: line-through;
    color: #c0c4cc;
    font-size: 0.8rem;
}

.currency {
    font-size: 0.7rem;
    color: #909399;
}

.cost-value {
    font-weight: 500;
    color: #67c23a;
    font-size: 0.9rem;
}

.image-placeholder {
    width: 70px;
    height: 70px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f5f7fa;
    border-radius: 10px;
    color: #c0c4cc;
}

.product-img-cell {
    position: relative;
    width: 70px;
    height: 70px;
}

.product-thumb {
    width: 70px;
    height: 70px;
    border-radius: 10px;
    overflow: hidden;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
}

.product-thumb:hover {
    transform: scale(1.08);
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
}

.img-badges {
    position: absolute;
    top: 3px;
    left: 3px;
    display: flex;
    gap: 3px;
    z-index: 2;
}

.badge {
    font-size: 9px;
    font-weight: 700;
    padding: 1px 5px;
    border-radius: 4px;
    line-height: 1.3;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.badge-sale {
    background: linear-gradient(135deg, #f56c6c, #e64242);
    color: white;
}

.badge-star {
    background: linear-gradient(135deg, #e6a23c, #d4910a);
    color: white;
    display: flex;
    align-items: center;
    padding: 2px 4px;
}

.gallery-count {
    position: absolute;
    bottom: 3px;
    right: 3px;
    background: rgba(0,0,0,0.65);
    color: white;
    font-size: 9px;
    font-weight: 600;
    padding: 1px 5px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    gap: 2px;
    backdrop-filter: blur(4px);
    z-index: 2;
}

.actions-cell {
    display: flex;
    gap: 4px;
}

.pagination-wrapper {
    margin-top: 1.5rem;
    display: flex;
    justify-content: center;
}
</style>
