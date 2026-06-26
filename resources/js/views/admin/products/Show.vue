<template>
    <div class="product-show">
        <el-card shadow="never">
            <template #header>
                <div class="card-header">
                    <div class="header-left">
                        <h2 class="page-title">{{ $t('product_details') }}</h2>
                        <el-tag type="info" effect="plain">ID: {{ product.id }}</el-tag>
                        <el-tag v-if="product.sku" type="warning" effect="plain">SKU: {{ product.sku }}</el-tag>
                    </div>
                    <div class="header-actions">
                        <el-button :icon="Edit" type="primary" @click="editProduct">
                            {{ $t('edit') }}
                        </el-button>
                        <el-button :icon="ArrowRight" @click="goBack">{{ $t('back') }}</el-button>
                    </div>
                </div>
            </template>

            <el-skeleton :loading="loading" animated :rows="10">
                <el-row :gutter="24">
                    <el-col :xs="24" :md="7">
                        <el-card shadow="never" class="image-card">
                            <el-image
                                style="width: 100%; height: 300px; object-fit: cover; border-radius: 8px;"
                                :src="product.image_main || '/placeholder.jpg'"
                                fit="cover"
                                lazy
                            >
                                <template #error>
                                    <div class="no-image">
                                        <el-icon :size="48"><Picture /></el-icon>
                                        <span>{{ $t('there_is_no_photo') }}</span>
                                    </div>
                                </template>
                            </el-image>

                            <div v-if="galleryImages.length" class="gallery-thumbs">
                                <el-image
                                    v-for="(img, idx) in galleryImages"
                                    :key="idx"
                                    :src="img"
                                    fit="cover"
                                    class="thumb-img"
                                    :preview-src-list="galleryImages"
                                    preview-teleported
                                />
                            </div>
                        </el-card>

                        <el-card shadow="never" class="info-card">
                            <template #header>
                                <span class="info-card-title">{{ $t('quick_information') }}</span>
                            </template>
                            <div class="quick-info">
                                <div class="info-row">
                                    <span class="info-label">{{ $t('status') }}</span>
                                    <el-tag :type="product.is_active ? 'success' : 'danger'" effect="dark" size="small">
                                        {{ product.is_active ? $t('active') : $t('inactive') }}
                                    </el-tag>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">{{ $t('distinct') }}</span>
                                    <el-tag :type="product.is_featured ? 'warning' : 'info'" effect="plain" size="small">
                                        {{ product.is_featured ? $t('yes') : $t('no') }}
                                    </el-tag>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">{{ $t('available') }}</span>
                                    <el-tag :type="product.in_stock ? 'success' : 'danger'" effect="plain" size="small">
                                        {{ product.in_stock ? $t('available') : $t('run_out') }}
                                    </el-tag>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">{{ $t('view_price') }}</span>
                                    <span>{{ product.show_price ? $t('yes') : $t('no') }}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">{{ $t('sort_order') }}</span>
                                    <span>{{ product.sort_order ?? 0 }}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">{{ $t('date_added') }}</span>
                                    <span class="date-value">{{ formatDate(product.created_at) }}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">{{ $t('latest_update') }}</span>
                                    <span class="date-value">{{ formatDate(product.updated_at) }}</span>
                                </div>
                            </div>
                        </el-card>
                    </el-col>

                    <el-col :xs="24" :md="17">
                        <el-card shadow="never" class="main-info-card">
                            <template #header>
                                <span class="info-card-title">{{ $t('basic_data') }}</span>
                            </template>

                            <h1 class="product-title">{{ product.name_ar || product.name_en }}</h1>
                            <p v-if="product.name_en && product.name_ar" class="product-title-en">{{ product.name_en }}</p>

                            <el-descriptions :column="2" border class="mt-4">
                                <el-descriptions-item label="Slug" :span="2">
                                    <code>{{ product.slug }}</code>
                                </el-descriptions-item>
                                <el-descriptions-item :label="$t('category')">
                                    <el-tag type="info" effect="plain">
                                        {{ product.category?.name_ar || product.category?.name_en || $t('without_category') }}
                                    </el-tag>
                                </el-descriptions-item>
                                <el-descriptions-item label="SKU">
                                    <span>{{ product.sku || '—' }}</span>
                                </el-descriptions-item>
                                <el-descriptions-item :label="$t('barcode')">
                                    <span>{{ product.barcode || '—' }}</span>
                                </el-descriptions-item>
                                <el-descriptions-item :label="$t('unity')">
                                    <span>{{ product.unit || 'piece' }}</span>
                                </el-descriptions-item>
                                <el-descriptions-item :label="$t('the_price')">
                                    <span class="price-value">{{ formatPrice(product.price) }}</span>
                                    <small class="currency-label">{{ product.currency || 'SAR' }}</small>
                                </el-descriptions-item>
                                <el-descriptions-item :label="$t('cost_price')">
                                    <span>{{ product.cost_price ? formatPrice(product.cost_price) : '—' }}</span>
                                </el-descriptions-item>
                                <el-descriptions-item :label="$t('discounted_price')">
                                    <span v-if="product.sale_price" class="sale-value">{{ formatPrice(product.sale_price) }}</span>
                                    <span v-else>—</span>
                                </el-descriptions-item>
                                <el-descriptions-item :label="$t('tax_rate')">
                                    <span>{{ product.tax_rate ? product.tax_rate + '%' : '—' }}</span>
                                </el-descriptions-item>
                                <el-descriptions-item :label="$t('taxable')">
                                    <el-tag :type="product.taxable ? 'success' : 'info'" effect="plain" size="small">
                                        {{ product.taxable ? $t('yes') : $t('no') }}
                                    </el-tag>
                                </el-descriptions-item>
                                <el-descriptions-item :label="$t('brand')">
                                    <span>{{ product.brand || '—' }}</span>
                                </el-descriptions-item>
                                <el-descriptions-item :label="$t('model')">
                                    <span>{{ product.model || '—' }}</span>
                                </el-descriptions-item>
                                <el-descriptions-item :label="$t('the_color')">
                                    <span>{{ product.color || '—' }}</span>
                                </el-descriptions-item>
                                <el-descriptions-item :label="$t('size')">
                                    <span>{{ product.size || '—' }}</span>
                                </el-descriptions-item>
                                <el-descriptions-item :label="$t('inventory')">
                                    <el-tag :type="getStockTag(product.stock_quantity)" effect="dark">
                                        {{ product.stock_quantity ?? 0 }} {{ $t('lonliness') }}
                                    </el-tag>
                                </el-descriptions-item>
                                <el-descriptions-item :label="$t('minimum')">
                                    <span>{{ product.min_stock ?? 0 }}</span>
                                </el-descriptions-item>
                                <el-descriptions-item :label="$t('maximum')">
                                    <span>{{ product.max_stock ?? '—' }}</span>
                                </el-descriptions-item>
                                <el-descriptions-item :label="$t('reorder_point')">
                                    <span>{{ product.reorder_point ?? '—' }}</span>
                                </el-descriptions-item>
                                <el-descriptions-item :label="$t('the_weight')">
                                    <span>{{ product.weight ? product.weight + ' كجم' : '—' }}</span>
                                </el-descriptions-item>
                                <el-descriptions-item :label="$t('dimensions')">
                                    <span v-if="product.length || product.width || product.height">
                                        {{ product.length || '—' }} × {{ product.width || '—' }} × {{ product.height || '—' }} {{ $t('poison') }}
                                    </span>
                                    <span v-else>—</span>
                                </el-descriptions-item>
                            </el-descriptions>

                            <el-divider />

                            <div v-if="product.short_description_ar || product.short_description_en" class="description-section">
                                <h3 class="section-title">{{ $t('brief_description') }}</h3>
                                <p class="desc-text">{{ product.short_description_ar || product.short_description_en }}</p>
                            </div>

                            <div class="description-section">
                                <h3 class="section-title">{{ $t('description') }}</h3>
                                <div v-if="product.description_ar || product.description_en" class="desc-text">
                                    <p>{{ product.description_ar || product.description_en }}</p>
                                </div>
                                <p v-else class="text-muted">{{ $t('there_is_no_description_available') }}</p>
                            </div>
                        </el-card>

                        <el-card shadow="never" class="seo-card">
                            <template #header>
                                <span class="info-card-title">{{ $t('search_engine_optimization_seo') }}</span>
                            </template>

                            <el-descriptions :column="1" border>
                                <el-descriptions-item label="Meta Title">
                                    <span>{{ product.seo?.title || product.seo_title || '—' }}</span>
                                </el-descriptions-item>
                                <el-descriptions-item label="Meta Description">
                                    <span>{{ product.seo?.description || product.seo_description || '—' }}</span>
                                </el-descriptions-item>
                                <el-descriptions-item label="Meta Keywords">
                                    <span>{{ product.seo?.keywords || product.seo_keywords || '—' }}</span>
                                </el-descriptions-item>
                            </el-descriptions>
                        </el-card>

                        <el-card v-if="product.discount_percentage && product.discount_percentage > 0" shadow="never" class="deal-card">
                            <template #header>
                                <span class="info-card-title">{{ $t('special_offer') }}</span>
                            </template>
                            <el-alert
                                :title="`خصم ${product.discount_percentage}% على هذا المنتج!`"
                                type="success"
                                :description="`السعر الأصلي: ${formatPrice(product.price)} → سعر العرض: ${formatPrice(product.sale_price)} ${product.currency || 'SAR'}`"
                                show-icon
                                :closable="false"
                            />
                        </el-card>
                    </el-col>
                </el-row>
            </el-skeleton>
        </el-card>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import { useProductsStore } from '@/stores/products';
import { Edit, ArrowRight, Picture } from '@element-plus/icons-vue';

const route = useRoute();
const router = useRouter();
const store = useProductsStore();

const loading = ref(true);
const product = ref({});

const galleryImages = computed(() => {
    const p = product.value;
    if (!p.image_gallery) return [];
    if (Array.isArray(p.image_gallery)) return p.image_gallery;
    try {
        return JSON.parse(p.image_gallery);
    } catch {
        return [];
    }
});

const getStockTag = (stock) => {
    if (!stock && stock !== 0) return 'danger';
    if (stock === 0) return 'danger';
    if (stock <= 10) return 'warning';
    return 'success';
};

const formatPrice = (price) => {
    if (price === null || price === undefined) return '0.00';
    return Number(price).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
};

const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('ar-SA', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const loadProduct = async () => {
    loading.value = true;
    try {
        const response = await store.fetchProduct(route.params.id);
        product.value = response || store.currentProduct || {};
    } catch {
        ElMessage.error(window.t('failed_to_fetch_product_details'));
        router.push({ name: 'admin.products.index' });
    } finally {
        loading.value = false;
    }
};

const editProduct = () => {
    router.push({ name: 'admin.products.edit', params: { id: product.value.id } });
};

const goBack = () => {
    router.push({ name: 'admin.products.index' });
};

onMounted(loadProduct);
</script>

<style scoped>
.product-show {
    padding: 0;
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
    flex-wrap: wrap;
}

.header-actions {
    display: flex;
    gap: 0.5rem;
}

.page-title {
    font-size: 1.35rem;
    font-weight: 700;
    margin: 0;
    color: #1a1a2e;
}

.image-card {
    margin-bottom: 1rem;
}

.no-image {
    width: 100%;
    height: 300px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: #f5f7fa;
    border-radius: 8px;
    color: #c0c4cc;
    gap: 12px;
}

.gallery-thumbs {
    display: flex;
    gap: 8px;
    margin-top: 12px;
    flex-wrap: wrap;
}

.thumb-img {
    width: 70px;
    height: 70px;
    border-radius: 8px;
    cursor: pointer;
    border: 2px solid #ebeef5;
    transition: border-color 0.2s;
}

.thumb-img:hover {
    border-color: #409eff;
}

.info-card {
    margin-bottom: 1rem;
}

.info-card-title {
    font-weight: 700;
    font-size: 1rem;
}

.quick-info {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 0;
    border-bottom: 1px solid #f5f7fa;
}

.info-row:last-child {
    border-bottom: none;
}

.info-label {
    color: #909399;
    font-size: 0.85rem;
}

.date-value {
    font-size: 0.8rem;
    color: #606266;
}

.main-info-card,
.seo-card,
.deal-card {
    margin-bottom: 1rem;
}

.product-title {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0 0 4px;
    color: #1a1a2e;
}

.product-title-en {
    font-size: 1rem;
    color: #909399;
    margin: 0 0 1rem;
}

.mt-4 {
    margin-top: 1rem;
}

.price-value {
    font-size: 1.1rem;
    font-weight: 700;
    color: #409eff;
}

.sale-value {
    font-size: 1.1rem;
    font-weight: 700;
    color: #e6a23c;
}

.currency-label {
    margin-right: 4px;
    color: #909399;
}

.section-title {
    font-size: 1.1rem;
    font-weight: 600;
    margin: 0 0 8px;
    color: #303133;
}

.desc-text {
    color: #606266;
    line-height: 1.7;
    white-space: pre-wrap;
}

.text-muted {
    color: #c0c4cc;
}

.description-section {
    margin-bottom: 1rem;
}
</style>
