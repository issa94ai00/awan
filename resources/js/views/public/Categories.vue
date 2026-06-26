<template>
    <div class="categories-page-view">
        <!-- Page Header -->
        <section class="page-header">
            <div class="container">
                <h1>{{ isSearch ? t('search') || 'نتائج البحث' : t('nav_categories') || 'الفئات' }}</h1>
                <div class="breadcrumb">
                    <router-link to="/">{{ t('nav_home') || 'الرئيسية' }}</router-link>
                    <span class="sep">›</span>
                    <span>{{ isSearch ? (t('search') || 'البحث') + ': ' + searchQuery : t('nav_categories') || 'الفئات' }}</span>
                </div>
            </div>
        </section>

        <!-- Categories List / Search Results Section -->
        <section class="categories fade-up">
            <div class="container">
                <div class="section-header">
                    <h2>{{ isSearch ? t('search') || 'نتائج البحث' : t('main_categories') || 'جميع الفئات' }}</h2>
                    <p v-if="isSearch">{{ products.length }} {{ t('items') || 'منتجات' }}</p>
                    <p v-else>{{ t('categories_subtitle') || 'تصفح جميع فئات المنتجات المتاحة في المتجر' }}</p>
                </div>

                <div v-if="loading" style="text-align: center; padding: 3rem;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--mobile-primary);"></i>
                </div>

                <div v-else>
                    <!-- Search Results Grid -->
                    <div v-if="isSearch" class="products-grid" style="margin-top: 30px;">
                        <div v-for="product in products" :key="product.id" class="product-card">
                            <div class="product-image">
                                <img :src="getImageUrl(product.image_main)" :alt="product.name_ar">
                                <router-link :to="'/product/' + product.slug" class="product-overlay">
                                    <span class="view-btn"><i class="fas fa-eye"></i></span>
                                </router-link>
                            </div>
                            <div class="product-info">
                                <div class="product-name-container">
                                    <h3 class="product-title">{{ $p(product, 'name') }}</h3>
                                </div>
                                <div class="product-category">{{ $p(product.category, 'name') || 'قطع غيار' }}</div>
                                <div v-if="settings.show_product_price === '1' && product.show_price && parseFloat(product.price) > 0" class="product-price">
                                    ${{ parseFloat(product.price).toFixed(2) }}
                                </div>
                                <button class="btn-add-to-cart" @click="handleAddToCart(product)">
                                    <i class="fas fa-cart-plus"></i>
                                    {{ t('add_to_cart') || 'أضف للسلة' }}
                                </button>
                            </div>
                        </div>
                        
                        <div v-if="!products.length" style="grid-column: 1/-1; text-align: center; padding: 40px; color:#666;">
                            <i class="fas fa-search" style="font-size: 2rem; display: block; margin-bottom: 10px;"></i>
                            {{ t('no_products_found') || 'لا توجد منتجات مطابقة للبحث حالياً' }}
                        </div>
                    </div>

                    <!-- Standard Categories Grid -->
                    <div v-else class="categories-grid">
                        <div v-for="category in categories" 
                             :key="category.id" 
                             class="category-card" 
                             @click="goToCategory(category.slug)">
                            <div v-if="category.image" class="category-image">
                                <img :src="getImageUrl(category.image)" :alt="category.name_ar">
                            </div>
                            <div v-else class="category-icon">
                                <i class="fas" :class="category.icon || 'fa-cube'"></i>
                            </div>
                            <h3>{{ $p(category, 'name') }}</h3>
                            <p>{{ truncateText($p(category, 'description') || t('high_quality_spare_parts'), 50) }}</p>
                            <span class="category-count">{{ category.product_count || 0 }} {{ t('products_count') || 'منتج' }}</span>
                        </div>
                        
                        <div v-if="!categories.length" style="text-align:center; padding: 2rem; color:#666;">
                            {{ t('no_categories') || 'لا توجد فئات متاحة حالياً' }}
                        </div>
                    </div>
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
import { useRoute, useRouter } from 'vue-router';
import { useSettingsStore } from '@/stores/settings';
import { useCartStore } from '@/stores/cart';
import { getImageUrl } from '@/utils/imageUrl';
import { useI18n } from 'vue-i18n';
import axios from 'axios';

// Stores
const settingsStore = useSettingsStore();
const cartStore = useCartStore();
const { t, locale } = useI18n();

// Router
const route = useRoute();
const router = useRouter();

// State
const categories = ref([]);
const products = ref([]);
const loading = ref(true);
const toast = reactive({ show: false, message: '' });

// Computed
const settings = computed(() => settingsStore.data);
const isSearch = computed(() => !!route.query.q);
const searchQuery = computed(() => route.query.q || '');

// SEO Meta Tags
const updateSEOMetaTags = () => {
    const siteName = settings.value.site_name || 'أوان التكادوم';
    const categoriesTitle = locale.value === 'en' ? 'Categories' : 'فئات المنتجات';
    const categoriesDescription = locale.value === 'en' ? 'Browse our wide range of mobile phone accessories' : 'تصفح مجموعتنا الواسعة من مستلزمات الأجهزة المحمولة';
    const ogImage = settings.value.og_image ? getImageUrl(settings.value.og_image) : '/assets/images/logo.png';
    
    // Update document title
    document.title = `${categoriesTitle} - ${siteName}`;
    
    // Update meta description
    const metaDescription = document.querySelector('meta[name="description"]');
    if (metaDescription) {
        metaDescription.setAttribute('content', categoriesDescription);
    }
    
    // Update og:title
    const ogTitle = document.querySelector('meta[property="og:title"]');
    if (ogTitle) {
        ogTitle.setAttribute('content', `${categoriesTitle} - ${siteName}`);
    }
    
    // Update og:description
    const ogDescription = document.querySelector('meta[property="og:description"]');
    if (ogDescription) {
        ogDescription.setAttribute('content', categoriesDescription);
    }
    
    // Update og:image
    const ogImageMeta = document.querySelector('meta[property="og:image"]');
    if (ogImageMeta) {
        ogImageMeta.setAttribute('content', ogImage);
    }
    
    // Update twitter:title
    const twitterTitle = document.querySelector('meta[property="twitter:title"]');
    if (twitterTitle) {
        twitterTitle.setAttribute('content', `${categoriesTitle} - ${siteName}`);
    }
    
    // Update twitter:description
    const twitterDescription = document.querySelector('meta[property="twitter:description"]');
    if (twitterDescription) {
        twitterDescription.setAttribute('content', categoriesDescription);
    }
    
    // Update twitter:image
    const twitterImage = document.querySelector('meta[property="twitter:image"]');
    if (twitterImage) {
        twitterImage.setAttribute('content', ogImage);
    }
};

// Helpers

const truncateText = (text, len) => {
    if (!text) return '';
    return text.length > len ? text.substring(0, len) + '...' : text;
};

const goToCategory = (slug) => {
    router.push(`/category/${slug}`);
};

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

// Fetch data depending on route query parameter
const loadData = async () => {
    loading.value = true;
    try {
        if (isSearch.value) {
            const res = await axios.get(`/api/v1/search?q=${encodeURIComponent(searchQuery.value)}`);
            if (res.data?.success) {
                products.value = res.data.data?.products || res.data.data || [];
            }
        } else {
            const res = await axios.get('/api/v1/categories');
            if (res.data?.success) {
                categories.value = res.data.data || [];
            }
        }
    } catch (e) {
        console.error('Failed to load categories/search data', e);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    loadData();
    // Update SEO meta tags
    updateSEOMetaTags();
});

// Watch query search changes
watch(() => route.query.q, () => {
    loadData();
});
</script>

<style scoped>
.category-card, .product-card {
    background: rgba(255, 255, 255, 0.7) !important;
    backdrop-filter: blur(20px) saturate(160%);
    -webkit-backdrop-filter: blur(20px) saturate(160%);
    border: 1px solid rgba(255, 255, 255, 0.5) !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.03) !important;
    border-radius: 24px !important;
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1) !important;
}

.category-card:hover, .product-card:hover {
    transform: translateY(-8px) scale(1.01) !important;
    background: rgba(255, 255, 255, 0.85) !important;
    box-shadow: 0 20px 40px color-mix(in srgb, var(--mobile-primary) 8%, transparent), 0 15px 30px rgba(0, 0, 0, 0.04) !important;
    border-color: color-mix(in srgb, var(--mobile-primary) 25%, transparent) !important;
}

[data-theme="dark"] .category-card, 
[data-theme="dark"] .product-card {
    background: rgba(30, 41, 59, 0.45) !important;
    border-color: rgba(255, 255, 255, 0.08) !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2) !important;
}

[data-theme="dark"] .category-card:hover, 
[data-theme="dark"] .product-card:hover {
    background: rgba(30, 41, 59, 0.6) !important;
    border-color: color-mix(in srgb, var(--mobile-primary) 25%, transparent) !important;
    box-shadow: 0 20px 40px color-mix(in srgb, var(--mobile-primary) 10%, transparent), 0 15px 30px rgba(0, 0, 0, 0.3) !important;
}

.product-image {
    overflow: hidden;
    position: relative;
    border-radius: 16px;
}

.product-image img {
    transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1) !important;
}

.product-card:hover .product-image img {
    transform: scale(1.06);
}
</style>
