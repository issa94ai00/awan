<template>
    <div class="category-detail-page-view">
        <!-- Page Header -->
        <section class="page-header" v-if="category">
            <div class="container">
                <h1>{{ $p(category, 'name') }}</h1>
                <div class="breadcrumb">
                    <router-link to="/">{{ t('nav_home') || 'الرئيسية' }}</router-link>
                    <span class="sep">›</span>
                    <router-link to="/categories">{{ t('nav_categories') || 'الفئات' }}</router-link>
                    <span class="sep">›</span>
                    <span>{{ $p(category, 'name') }}</span>
                </div>
            </div>
        </section>

        <!-- Products List Section -->
        <section class="products-section category-products-section fade-up">
            <div class="container">
                <div class="products-header" v-if="category">
                    <div>
                        <h2 class="section-title" style="margin-bottom: 0.5rem;">{{ t('nav_products') || 'منتجات' }} {{ $p(category, 'name') }}</h2>
                        <p style="color:#556;">{{ $p(category, 'description') || t('browse_category_products') || 'تصفح المنتجات ضمن هذه الفئة' }}</p>
                    </div>
                </div>

                <div v-if="loading" style="text-align: center; padding: 3rem;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--mobile-primary);"></i>
                </div>

                <div v-else>
                    <div v-if="products.length" class="products-grid">
                        <div v-for="product in products" :key="product.id" class="product-card">
                            <div class="product-image">
                                <div class="badges-container">
                                    <span v-if="!product.in_stock" class="badge badge-out">{{ t('out_of_stock') || 'غير متوفر' }}</span>
                                    <span v-else class="badge badge-in">{{ t('in_stock') || 'متوفر' }}</span>
                                </div>
                                <img :src="getImageUrl(product.image_main)" :alt="product.name_ar" loading="lazy">
                                <router-link :to="'/product/' + product.slug" class="product-overlay">
                                    <span class="view-btn"><i class="fas fa-eye"></i></span>
                                </router-link>
                            </div>
                            <div class="product-info">
                                <!-- Row 1: Title -->
                                <div class="product-title-row">
                                    <h3 class="product-title">{{ $p(product, 'name') }}</h3>
                                </div>
                                <!-- Row 2: Details -->
                                <div class="product-details-row">
                                    <div class="product-category">{{ $p(category, 'name') || t('category') || 'منتجات' }}</div>
                                    <div v-if="product.brand || product.model" class="product-meta-info">
                                        <span v-if="product.brand">{{ product.brand }}</span>
                                        <span v-if="product.model">{{ product.model }}</span>
                                    </div>
                                    <div v-if="settings.show_product_price === '1' && product.show_price && parseFloat(product.price) > 0" class="product-price">
                                        <span>${{ parseFloat(product.price).toFixed(2) }}</span>
                                    </div>
                                </div>
                                <!-- Row 3: Action Buttons -->
                                <div class="product-actions-row">
                                    <button class="btn-add-to-cart" @click="handleAddToCart(product)">
                                        <i class="fas fa-cart-plus"></i>
                                        <span>{{ t('add_to_cart') || 'أضف للسلة' }}</span>
                                    </button>
                                    <a :href="'https://wa.me/' + (settings.contact_whatsapp || '963900000000') + '?text=' + encodeURIComponent('مرحباً، أنا مهتم بمنتج: ' + $p(product, 'name'))" class="btn-whatsapp" target="_blank">
                                        <i class="fab fa-whatsapp"></i>
                                        <span>WhatsApp</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else style="text-align:center; padding: 4rem 2rem; color:#666;">
                        <i class="fas fa-box-open" style="font-size: 2.5rem; margin-bottom: 15px; display: block; color: #909399;"></i>
                        {{ t('no_products_found') || 'لا توجد منتجات حالياً ضمن هذه الفئة' }}
                    </div>

                    <!-- Tailwind Pagination -->
                    <nav v-if="pagination.last_page > 1" class="pagination-tailwind" aria-label="Pagination">
                        <!-- Mobile view -->
                        <div class="mobile-pagination">
                            <span v-if="pagination.current_page === 1" class="btn-prev disabled">
                                <i class="fas fa-chevron-right"></i>
                                {{ t('previous') || 'السابق' }}
                            </span>
                            <a v-else href="#" @click.prevent="goToPage(pagination.current_page - 1)" class="btn-prev">
                                <i class="fas fa-chevron-right"></i>
                                {{ t('previous') || 'السابق' }}
                            </a>

                            <a v-if="pagination.has_more_pages" href="#" @click.prevent="goToPage(pagination.current_page + 1)" class="btn-next">
                                {{ t('next') || 'التالي' }}
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            <span v-else class="btn-next disabled">
                                {{ t('next') || 'التالي' }}
                                <i class="fas fa-chevron-left"></i>
                            </span>
                        </div>

                        <!-- Desktop view -->
                        <div class="desktop-pagination">
                            <p class="pagination-info">
                                {{ t('showing_page') || 'عرض الصفحة' }} <span>{{ pagination.current_page }}</span> {{ t('of') || 'من أصل' }} <span>{{ pagination.last_page }}</span> {{ t('pages') || 'صفحات' }}
                            </p>

                            <div class="pagination-buttons">
                                <!-- Previous -->
                                <span v-if="pagination.current_page === 1" class="page-btn prev disabled">
                                    <i class="fas fa-chevron-right"></i>
                                </span>
                                <a v-else href="#" @click.prevent="goToPage(pagination.current_page - 1)" class="page-btn prev">
                                    <i class="fas fa-chevron-right"></i>
                                </a>

                                <!-- Page Numbers -->
                                <template v-for="page in pagination.last_page" :key="page">
                                    <span v-if="page === pagination.current_page" class="page-btn active">{{ page }}</span>
                                    <a v-else href="#" @click.prevent="goToPage(page)" class="page-btn">{{ page }}</a>
                                </template>

                                <!-- Next -->
                                <a v-if="pagination.current_page < pagination.last_page" href="#" @click.prevent="goToPage(pagination.current_page + 1)" class="page-btn next">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                                <span v-else class="page-btn next disabled">
                                    <i class="fas fa-chevron-left"></i>
                                </span>
                            </div>
                        </div>
                    </nav>
                </div>
            </div>
        </section>

        <!-- Notification Toast -->
        <div v-if="toast.show" class="cart-notification success show" style="top: 100px;">
            <i class="fas fa-check-circle"></i>
            <span>{{ toast.message }}</span>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed, watch, reactive } from 'vue';
import { useRoute } from 'vue-router';
import { useSettingsStore } from '@/stores/settings';
import { useCartStore } from '@/stores/cart';
import { getImageUrl } from '@/utils/imageUrl';
import { triggerFadeUp } from '@/utils/fadeUp';
import { useI18n } from 'vue-i18n';
import axios from 'axios';

// Stores
const settingsStore = useSettingsStore();
const cartStore = useCartStore();
const { t, locale } = useI18n();

// Router
const route = useRoute();

// State
const category = ref(null);
const products = ref([]);
const pagination = ref({});
const loading = ref(true);
const toast = reactive({ show: false, message: '' });

// Computed
const settings = computed(() => settingsStore.data);
const categorySlug = computed(() => route.params.slug);

// SEO Meta Tags
const dispatchSeoEvent = () => {
    if (!category.value) return;
    const currentLocale = locale.value;
    const categoryName = currentLocale === 'en' ? (category.value.name_en || category.value.name_ar) : category.value.name_ar;
    const categoryDesc = currentLocale === 'en' ? (category.value.description_en || category.value.description_ar || category.value.description) : (category.value.description_ar || category.value.description);
    const seoTitleVal = category.value.meta_title || categoryName;
    const seoDescVal = category.value.meta_description || categoryDesc;

    window.dispatchEvent(new CustomEvent('set-dynamic-seo', {
        detail: {
            title: seoTitleVal,
            description: seoDescVal,
            keywords: '',
            image: category.value.image || ''
        }
    }));
};

watch(locale, () => {
    if (category.value) {
        dispatchSeoEvent();
    }
});

// Helpers

const handleAddToCart = async (product) => {
    try {
        await cartStore.addToCart(product.id, 1);
        showToast(`تم إضافة "${product.name_ar}" إلى السلة`);
    } catch (e) {
        showToast('حدث خطأ أثناء إضافة المنتج');
    }
};

const showToast = (msg) => {
    toast.message = msg;
    toast.show = true;
    setTimeout(() => {
        toast.show = false;
    }, 3000);
};

// Fetch products for category
const fetchCategoryProducts = async (page = 1) => {
    loading.value = true;
    try {
        const res = await axios.get(`/api/v1/categories/${categorySlug.value}/products?page=${page}`);
        if (res.data?.success) {
            category.value = res.data.data.category;
            dispatchSeoEvent();
            products.value = res.data.data.products || [];
            pagination.value = res.data.data.pagination || {};
        }
    } catch (e) {
        console.error('Failed to load category products', e);
    } finally {
        loading.value = false;
        triggerFadeUp();
    }
};

const goToPage = (page) => {
    fetchCategoryProducts(page);
    // Smooth scroll to products section
    const sec = document.querySelector('.category-products-section');
    if (sec) {
        sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
};

onMounted(() => {
    fetchCategoryProducts();
});

watch(categorySlug, () => {
    fetchCategoryProducts();
});
</script>

<style scoped>
.category-detail-page-view {
    padding-bottom: 3rem;
}

.products-section {
    padding: 2rem 0 4rem;
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 24px;
    margin-top: 30px;
}

.product-card {
    background: rgba(255, 255, 255, 0.7) !important;
    backdrop-filter: blur(20px) saturate(160%);
    -webkit-backdrop-filter: blur(20px) saturate(160%);
    border: 1px solid rgba(255, 255, 255, 0.5) !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.03) !important;
    border-radius: 24px !important;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    height: 100%;
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1) !important;
}

.product-card:hover {
    transform: translateY(-8px) scale(1.01) !important;
    background: rgba(255, 255, 255, 0.85) !important;
    box-shadow: 0 20px 40px color-mix(in srgb, var(--mobile-primary) 8%, transparent), 0 15px 30px rgba(0, 0, 0, 0.04) !important;
    border-color: color-mix(in srgb, var(--mobile-primary) 25%, transparent) !important;
}

[data-theme="dark"] .product-card {
    background: rgba(30, 41, 59, 0.45) !important;
    border-color: rgba(255, 255, 255, 0.08) !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2) !important;
}

[data-theme="dark"] .product-card:hover {
    background: rgba(30, 41, 59, 0.6) !important;
    border-color: color-mix(in srgb, var(--mobile-primary) 25%, transparent) !important;
    box-shadow: 0 20px 40px color-mix(in srgb, var(--mobile-primary) 10%, transparent), 0 15px 30px rgba(0, 0, 0, 0.3) !important;
}

.product-image {
    position: relative;
    height: 250px;
    background: #f8fafc;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}

[data-theme="dark"] .product-image {
    background: #1e293b;
}

.product-image img {
    max-height: 100%;
    max-width: 100%;
    object-fit: contain;
    transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1) !important;
}

.product-card:hover .product-image img {
    transform: scale(1.06);
}

.badges-container {
    position: absolute;
    top: 12px;
    right: 12px;
    z-index: 10;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.badge {
    padding: 6px 12px;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.3px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
}

.badge-in {
    background: #e6f4ea;
    color: #137333;
}

[data-theme="dark"] .badge-in {
    background: rgba(19, 115, 51, 0.2);
    color: #81c995;
    border: 1px solid rgba(129, 201, 149, 0.2);
}

.badge-out {
    background: #fce8e6;
    color: #c5221f;
}

[data-theme="dark"] .badge-out {
    background: rgba(197, 34, 31, 0.2);
    color: #f28b82;
    border: 1px solid rgba(242, 139, 130, 0.2);
}

.product-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: color-mix(in srgb, var(--mobile-primary) 20%, transparent);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: all 0.4s ease;
    z-index: 5;
}

.product-card:hover .product-overlay {
    opacity: 1;
}

.view-btn {
    width: 50px;
    height: 50px;
    background: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--el-color-primary);
    font-size: 1.2rem;
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    transform: translateY(20px);
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

.product-card:hover .view-btn {
    transform: translateY(0);
}

.product-info {
    padding: 20px;
    display: flex;
    flex-direction: column;
    flex-grow: 1;
    gap: 12px;
}

.product-title-row {
    margin-bottom: 4px;
}

.product-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 4px 0;
    line-height: 1.4;
    transition: color 0.3s;
}

[data-theme="dark"] .product-title {
    color: #f1f5f9;
}

.product-subtitle {
    font-size: 0.85rem;
    color: #64748b;
    display: block;
}

.product-details-row {
    display: flex;
    flex-direction: column;
    gap: 6px;
    border-top: 1px dashed rgba(0, 0, 0, 0.08);
    padding-top: 12px;
    margin-bottom: 6px;
}

[data-theme="dark"] .product-details-row {
    border-color: rgba(255, 255, 255, 0.08);
}

.product-category {
    font-size: 0.8rem;
    color: var(--mobile-primary);
    font-weight: 600;
}

.product-meta-info {
    font-size: 0.8rem;
    color: #64748b;
    display: flex;
    gap: 8px;
}

.product-meta-info span {
    background: rgba(0, 0, 0, 0.04);
    padding: 2px 8px;
    border-radius: 4px;
}

[data-theme="dark"] .product-meta-info span {
    background: rgba(255, 255, 255, 0.05);
    color: #94a3b8;
}

.product-price {
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--mobile-primary);
    margin-top: 4px;
}

[data-theme="dark"] .product-price {
    color: var(--mobile-primary);
}

.product-actions-row {
    display: flex;
    gap: 10px;
    margin-top: auto;
}

.btn-add-to-cart, .btn-whatsapp {
    padding: 10px 14px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 0.88rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.3s ease;
    cursor: pointer;
    flex: 1;
    border: none;
}

.btn-add-to-cart {
    background: var(--mobile-primary);
    color: white;
}

.btn-add-to-cart:hover {
    background: var(--el-color-primary-light-3);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px color-mix(in srgb, var(--mobile-primary) 20%, transparent);
}

.btn-whatsapp {
    background: #25d366;
    color: white;
    text-decoration: none;
}

.btn-whatsapp:hover {
    background: #20ba5a;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(37, 211, 102, 0.2);
}

/* Pagination modern glass style */
.pagination-tailwind {
    margin-top: 40px;
}

.mobile-pagination {
    display: none;
}

.desktop-pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: rgba(255, 255, 255, 0.4);
    backdrop-filter: blur(10px);
    padding: 12px 24px;
    border-radius: 20px;
    border: 1px solid rgba(255, 255, 255, 0.4);
}

[data-theme="dark"] .desktop-pagination {
    background: rgba(30, 41, 59, 0.3);
    border-color: rgba(255, 255, 255, 0.05);
}

.pagination-info {
    font-size: 0.9rem;
    color: #475569;
    margin: 0;
}

[data-theme="dark"] .pagination-info {
    color: #94a3b8;
}

.pagination-buttons {
    display: flex;
    gap: 8px;
}

.page-btn {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    color: #475569;
    text-decoration: none;
    background: rgba(255, 255, 255, 0.6);
    border: 1px solid rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

[data-theme="dark"] .page-btn {
    background: rgba(30, 41, 59, 0.5);
    color: #f1f5f9;
    border-color: rgba(255, 255, 255, 0.05);
}

.page-btn:hover:not(.disabled) {
    background: var(--mobile-primary);
    color: white !important;
    border-color: var(--mobile-primary);
    transform: translateY(-2px);
}

.page-btn.active {
    background: var(--mobile-primary);
    color: white !important;
    border-color: var(--mobile-primary);
}

[data-theme="dark"] .page-btn.active {
    background: var(--mobile-primary);
    color: #0f172a !important;
    border-color: var(--mobile-primary);
}

.page-btn.disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

@media (max-width: 640px) {
    .desktop-pagination {
        display: none;
    }
    
    .mobile-pagination {
        display: flex;
        justify-content: space-between;
        gap: 15px;
    }
    
    .btn-prev, .btn-next {
        flex: 1;
        padding: 12px;
        background: rgba(255, 255, 255, 0.6);
        border: 1px solid rgba(0,0,0,0.05);
        border-radius: 12px;
        color: #475569;
        text-align: center;
        text-decoration: none;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    
    [data-theme="dark"] .btn-prev, 
    [data-theme="dark"] .btn-next {
        background: rgba(30, 41, 59, 0.5);
        color: #f1f5f9;
        border-color: rgba(255, 255, 255, 0.05);
    }
    
    .btn-prev:hover:not(.disabled), 
    .btn-next:hover:not(.disabled) {
        background: var(--mobile-primary);
        color: white;
    }
    
    .btn-prev.disabled, .btn-next.disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }
}
</style>

