<template>
    <div class="featured-products-page">
        <section class="page-header">
            <div class="container">
                <h1>{{ pageTitle }}</h1>
                <div class="breadcrumb">
                    <router-link to="/">{{ t('nav_home') || 'الرئيسية' }}</router-link>
                    <span class="sep">›</span>
                    <span>{{ pageTitle }}</span>
                </div>
            </div>
        </section>

        <section v-if="loading" class="products-loading">
            <div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i></div>
        </section>

        <section v-else class="featured-products-section fade-up">
            <div class="container">
                <div class="section-header">
                    <div class="type-tabs">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            :class="['tab-btn', { active: currentType === tab.key }]"
                            @click="switchType(tab.key)"
                        >
                            <i :class="tab.icon"></i>
                            <span>{{ tab.label }}</span>
                        </button>
                    </div>
                    <h2>{{ sectionTitle }}</h2>
                    <p>{{ sectionSubtitle }}</p>
                </div>

                <div v-if="products.length > 0">
                    <div class="products-grid">
                        <div v-for="product in products" :key="product.id" class="product-card">
                            <div class="product-image" @click="openLightbox(product)" style="cursor: zoom-in;">
                                <img :src="product.image_main || defaultImage" :alt="product.name_ar" loading="lazy" class="main-img">
                                <div class="card-badges">
                                    <span v-if="product.sale_price" class="card-badge badge-discount"><i class="fas fa-tag"></i> {{ t('discount') || 'خصم' }}</span>
                                    <span v-if="product.is_featured" class="card-badge badge-featured"><i class="fas fa-star"></i> {{ t('featured') || 'مميز' }}</span>
                                    <span v-if="product.stock_quantity <= 0" class="card-badge badge-out"><i class="fas fa-times"></i> {{ t('out_of_stock') || 'نفد' }}</span>
                                </div>
                                <div v-if="getGalleryImages(product).length > 1" class="gallery-strip">
                                    <img v-for="(img, idx) in getGalleryImages(product).slice(0, 4)" :key="idx" :src="img" class="gallery-thumb" loading="lazy">
                                    <span v-if="getGalleryImages(product).length > 4" class="gallery-more">+{{ getGalleryImages(product).length - 4 }}</span>
                                </div>
                                <router-link :to="{ name: 'product.detail', params: { slug: product.slug } }" class="product-overlay">
                                    <div class="overlay-content">
                                        <span class="view-icon"><i class="fas fa-eye"></i></span>
                                        <span class="view-text">{{ t('view_details') || 'عرض التفاصيل' }}</span>
                                    </div>
                                </router-link>
                            </div>
                            <div class="product-info">
                                <div class="product-title-row">
                                    <h3 class="product-title">{{ product.name_ar }}</h3>
                                    <span v-if="product.name_en" class="product-subtitle">{{ product.name_en }}</span>
                                </div>
                                <div class="product-details-row">
                                    <div class="product-category">{{ product.category?.name_ar || t('construction_materials') || 'منتجات بناء' }}</div>
                                    <div v-if="product.brand || product.model" class="product-meta-info">
                                        <span v-if="product.brand">{{ product.brand }}</span>
                                        <span v-if="product.model">{{ product.model }}</span>
                                    </div>
                                </div>
                                <div v-if="product.show_price !== false" class="product-price-row">
                                    <span v-if="product.sale_price" class="price-old">{{ formatPrice(product.price) }} {{ product.currency || 'SAR' }}</span>
                                    <span class="price-current">{{ formatPrice(product.sale_price || product.price) }} {{ product.currency || 'SAR' }}</span>
                                    <span v-if="product.sale_price && product.discount_percentage" class="discount-badge">-{{ product.discount_percentage }}%</span>
                                </div>
                                <div class="product-actions-row">
                                    <a :href="`https://wa.me/${whatsappNumber}?text=${encodeURIComponent(whatsappMsg + ' ' + product.name_ar)}`" class="btn-whatsapp" target="_blank">
                                        <i class="fab fa-whatsapp"></i> <span>{{ t('whatsapp') || 'واتساب' }}</span>
                                    </a>
                                    <router-link :to="{ name: 'inquiry', query: { product_id: product.id, product_name: product.name_ar } }" class="btn-inquiry">
                                        <i class="fas fa-question-circle"></i> <span>{{ t('inquiry') || 'استفسار' }}</span>
                                    </router-link>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="pagination.total > pagination.per_page" class="pagination-tailwind">
                        <div class="mobile-pagination">
                            <span v-if="pagination.current_page === 1" class="btn-prev disabled"><i class="fas fa-chevron-right"></i> {{ t('previous') || 'السابق' }}</span>
                            <a v-else @click="pageChanged(pagination.current_page - 1)" class="btn-prev"><i class="fas fa-chevron-right"></i> {{ t('previous') || 'السابق' }}</a>
                            <span v-if="pagination.current_page >= pagination.last_page" class="btn-next disabled">{{ t('next') || 'التالي' }} <i class="fas fa-chevron-left"></i></span>
                            <a v-else @click="pageChanged(pagination.current_page + 1)" class="btn-next">{{ t('next') || 'التالي' }} <i class="fas fa-chevron-left"></i></a>
                        </div>
                        <div class="desktop-pagination">
                            <p class="pagination-info">{{ t('showing') || 'عرض' }} <span>{{ pagination.from || 0 }}</span> {{ t('to') || 'إلى' }} <span>{{ pagination.to || 0 }}</span> {{ t('of') || 'من' }} <span>{{ pagination.total }}</span> {{ t('products') || 'منتج' }}</p>
                            <div class="pagination-buttons">
                                <span v-if="pagination.current_page === 1" class="page-btn prev disabled"><i class="fas fa-chevron-right"></i></span>
                                <a v-else @click="pageChanged(pagination.current_page - 1)" class="page-btn prev"><i class="fas fa-chevron-right"></i></a>
                                <span v-for="page in totalPages" :key="page" :class="['page-btn', { active: page === pagination.current_page }]" @click="pageChanged(page)">{{ page }}</span>
                                <span v-if="pagination.current_page >= pagination.last_page" class="page-btn next disabled"><i class="fas fa-chevron-left"></i></span>
                                <a v-else @click="pageChanged(pagination.current_page + 1)" class="page-btn next"><i class="fas fa-chevron-left"></i></a>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="no-products">
                    <i :class="emptyIcon"></i>
                    <h3>{{ emptyTitle }}</h3>
                    <p>{{ t('browse_categories_instead') || 'يمكنك تصفح جميع منتجاتنا من خلال صفحة الفئات' }}</p>
                    <router-link to="/categories" class="btn"><i class="fas fa-th-large"></i> {{ t('browse_categories') || 'تصفح الفئات' }}</router-link>
                </div>
            </div>
        </section>

        <Teleport to="body">
            <Transition name="lightbox-fade">
                <div v-if="lightbox.open" class="lightbox-overlay" @click.self="closeLightbox">
                    <button class="lightbox-close" @click="closeLightbox"><i class="fas fa-times"></i></button>
                    <button v-if="lightbox.images.length > 1" class="lightbox-nav lightbox-prev" @click="prevImage"><i class="fas fa-chevron-right"></i></button>
                    <div class="lightbox-content">
                        <img :src="lightbox.images[lightbox.index]" :alt="lightbox.title" class="lightbox-img">
                        <div class="lightbox-caption">{{ lightbox.title }}</div>
                        <div v-if="lightbox.images.length > 1" class="lightbox-counter">{{ lightbox.index + 1 }} / {{ lightbox.images.length }}</div>
                    </div>
                    <button v-if="lightbox.images.length > 1" class="lightbox-nav lightbox-next" @click="nextImage"><i class="fas fa-chevron-left"></i></button>
                    <div v-if="lightbox.images.length > 1" class="lightbox-thumbs">
                        <img v-for="(img, idx) in lightbox.images" :key="idx" :src="img" :class="['lightbox-thumb', { active: idx === lightbox.index }]" @click="lightbox.index = idx">
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useProductsStore } from '@/stores/products';
import { useSettingsStore } from '@/stores/settings';
import { useI18n } from 'vue-i18n';

import axios from 'axios';

const route = useRoute();
const router = useRouter();
const productsStore = useProductsStore();
const settingsStore = useSettingsStore();
const { t, locale } = useI18n();

const loading = ref(false);
const products = ref([]);
const pagination = ref({});

const settings = computed(() => settingsStore.data);
const defaultImage = '/assets/images/products/default-product.jpg';
const whatsappNumber = computed(() => settings.value.contact_whatsapp || settings.value.contact_phone || '00963962889577');
const whatsappMsg = computed(() => {
    if (locale.value === 'en') return 'Hello, I am interested in the product:';
    return 'مرحباً، أنا مهتم بمنتج:';
});

const currentType = computed(() => {
    const q = route.query;
    if (q.new !== undefined) return 'new';
    if (q.best !== undefined) return 'best';
    return 'featured';
});

const tabs = [
    { key: 'featured', label: t('featured_tab') || 'مميزة', icon: 'fas fa-star' },
    { key: 'new', label: t('new_arrivals_tab') || 'وصل حديثاً', icon: 'fas fa-sparkles' },
    { key: 'best', label: t('best_sellers_tab') || 'الأكثر مبيعاً', icon: 'fas fa-trophy' },
];

const pageTitle = computed(() => {
    switch (currentType.value) {
        case 'new': return t('new_arrivals_title') || 'وصل حديثاً';
        case 'best': return t('best_sellers_title') || 'الأكثر مبيعاً';
        default: return t('featured_products') || 'المنتجات المميزة';
    }
});

const sectionTitle = computed(() => {
    switch (currentType.value) {
        case 'new': return t('new_arrivals_title') || 'وصل حديثاً';
        case 'best': return t('best_sellers_title') || 'الأكثر مبيعاً';
        default: return t('our_featured_products') || 'منتجاتنا المميزة';
    }
});

const sectionSubtitle = computed(() => {
    switch (currentType.value) {
        case 'new': return t('new_arrivals_subtitle') || 'اكتشف أحدث منتجاتنا وإضافاتنا الجديدة';
        case 'best': return t('best_sellers_subtitle') || 'منتجاتنا الأكثر شهرة ومبيعاً';
        default: return t('featured_products_subtitle') || 'اختر من مجموعتنا المتميزة من منتجات البناء عالية الجودة';
    }
});

const emptyTitle = computed(() => {
    switch (currentType.value) {
        case 'new': return t('no_new_arrivals') || 'لا توجد منتجات وصلت حديثاً حالياً';
        case 'best': return t('no_best_sellers') || 'لا توجد منتجات الأكثر مبيعاً حالياً';
        default: return t('no_featured_products') || 'لا توجد منتجات مميزة حالياً';
    }
});

const emptyIcon = computed(() => {
    switch (currentType.value) {
        case 'new': return 'fas fa-clock';
        case 'best': return 'fas fa-crown';
        default: return 'fas fa-box-open';
    }
});

const lightbox = reactive({
    open: false, images: [], index: 0, title: ''
});

function openLightbox(product) {
    const images = getGalleryImages(product);
    if (!images.length) return;
    lightbox.images = images;
    lightbox.index = 0;
    lightbox.title = product.name_ar;
    lightbox.open = true;
    document.body.style.overflow = 'hidden';
}

function closeLightbox() {
    lightbox.open = false;
    document.body.style.overflow = '';
}

function nextImage() { lightbox.index = (lightbox.index + 1) % lightbox.images.length; }
function prevImage() { lightbox.index = (lightbox.index - 1 + lightbox.images.length) % lightbox.images.length; }

function formatPrice(price) {
    if (!price) return '0';
    return Number(price).toLocaleString(locale.value === 'en' ? 'en-US' : 'ar-SA');
}

function getGalleryImages(product) {
    const images = [];
    if (product.image_main) images.push(product.image_main);
    if (product.image_gallery) {
        try {
            const gallery = typeof product.image_gallery === 'string' ? JSON.parse(product.image_gallery) : product.image_gallery;
            if (Array.isArray(gallery)) images.push(...gallery);
        } catch {}
    }
    return images;
}

function switchType(type) {
    const query = { ...route.query };
    delete query.new;
    delete query.best;
    if (type === 'new') query.new = '';
    else if (type === 'best') query.best = '';
    router.push({ query });
}

const totalPages = computed(() => {
    if (!pagination.value.last_page) return [];
    return Array.from({ length: pagination.value.last_page }, (_, i) => i + 1);
});

async function loadProducts(page = 1) {
    loading.value = true;
    try {
        const params = { page };
        const type = currentType.value;
        if (type !== 'featured') params.type = type;
        const res = await axios.get('/api/v1/featured-products', { params });
        products.value = res.data.data.products || [];
        pagination.value = res.data.data.pagination || {};
    } catch (error) {
        console.error('Failed to load products:', error);
        products.value = [];
        pagination.value = {};
    } finally {
        loading.value = false;
    }
}

function pageChanged(page) {
    loadProducts(page);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function handleKeydown(e) {
    if (!lightbox.open) return;
    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowLeft') nextImage();
    if (e.key === 'ArrowRight') prevImage();
}

function updateSEOMetaTags() {
    const siteName = settings.value[`site_name_${locale.value}`] || settings.value.site_name || 'أوان التقدم';
    const title = `${pageTitle.value} - ${siteName}`;
    document.title = title;
    const desc = document.querySelector('meta[name="description"]');
    if (desc) desc.setAttribute('content', sectionSubtitle.value);
    const ogTitle = document.querySelector('meta[property="og:title"]');
    if (ogTitle) ogTitle.setAttribute('content', title);
}

watch(currentType, () => {
    loadProducts();
    updateSEOMetaTags();
});

onMounted(() => {
    loadProducts();
    settingsStore.fetch().catch(() => {});
    updateSEOMetaTags();
    document.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown);
});
</script>

<style scoped>
.featured-products-page {
    min-height: 100vh;
}

/* Page Header */
.page-header {
    padding-top: 120px;
    background: linear-gradient(135deg, var(--mobile-primary-dark), #5a6b7a);
    color: white;
    padding-bottom: 3rem;
    position: relative;
    overflow: hidden;
}

.page-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle at 80% 20%, rgba(255,255,255,0.06) 0%, transparent 50%),
                radial-gradient(circle at 20% 80%, rgba(255,255,255,0.04) 0%, transparent 50%);
    pointer-events: none;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 2rem;
}

.page-header h1 {
    font-size: 2.2rem;
    font-weight: 700;
    margin-bottom: 1rem;
}

.breadcrumb {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1rem;
    opacity: 0.9;
}

.breadcrumb a {
    color: white;
    text-decoration: none;
    transition: opacity 0.3s;
}

.breadcrumb a:hover {
    opacity: 0.8;
}

/* Featured Products Section */
.featured-products-section {
    padding: 4rem 0;
}

.section-header {
    text-align: center;
    margin-bottom: 3rem;
}

.type-tabs {
    display: flex;
    justify-content: center;
    gap: 0.75rem;
    margin-bottom: 2rem;
    flex-wrap: wrap;
}

.tab-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.65rem 1.5rem;
    border-radius: 50px;
    border: 2px solid #e5e7eb;
    background: white;
    color: #6b7280;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.tab-btn:hover {
    border-color: #c9a959;
    color: #c9a959;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(201, 169, 89, 0.15);
}

.tab-btn.active {
    background: linear-gradient(135deg, #c9a959, #d4af37);
    border-color: transparent;
    color: white;
    box-shadow: 0 4px 16px rgba(201, 169, 89, 0.35);
}

.tab-btn i {
    font-size: 0.85rem;
}

.section-header h2 {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: var(--mobile-primary-dark);
}

.section-header p {
    color: #6b7280;
    font-size: 1.125rem;
}

/* Products Grid */
.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 2rem;
    margin-bottom: 3rem;
}

.product-card {
    background: white;
    border-radius: 24px;
    overflow: hidden;
    transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 12px rgba(93, 64, 55, 0.06), 0 0 0 1px rgba(93, 64, 55, 0.04);
    position: relative;
    display: flex;
    flex-direction: column;
    min-height: 280px;
}

.product-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #c9a959 0%, #d4af37 50%, #c9a959 100%);
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 2;
}

.product-card:hover::before {
    transform: scaleX(1);
}

.product-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 50px rgba(93, 64, 55, 0.14), 0 0 0 1px rgba(201, 169, 89, 0.12);
}

.product-image {
    position: relative;
    height: 250px;
    overflow: hidden;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 24px 24px 0 0;
    flex-shrink: 0;
}

.product-image::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(201, 169, 89, 0.1) 0%, rgba(93, 64, 55, 0.05) 100%);
    opacity: 0;
    transition: opacity 0.5s ease;
    z-index: 1;
}

.product-card:hover .product-image::before {
    opacity: 1;
}

.product-image img,
.product-image .main-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

.product-card:hover .product-image img,
.product-card:hover .main-img {
    transform: scale(1.08);
}

/* Badges */
.card-badges {
    position: absolute;
    top: 12px;
    right: 12px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    z-index: 10;
}

.card-badge {
    font-size: 0.7rem;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.badge-discount {
    background: linear-gradient(135deg, rgba(220, 53, 69, 0.92), rgba(200, 30, 50, 0.95));
    color: white;
    border: 1px solid rgba(255,255,255,0.2);
}

.badge-featured {
    background: linear-gradient(135deg, rgba(201, 169, 89, 0.92), rgba(180, 150, 70, 0.95));
    color: white;
    border: 1px solid rgba(255,255,255,0.2);
}

.badge-out {
    background: linear-gradient(135deg, rgba(108, 117, 125, 0.92), rgba(90, 100, 110, 0.95));
    color: white;
    border: 1px solid rgba(255,255,255,0.2);
}

/* Gallery Strip */
.gallery-strip {
    position: absolute;
    bottom: 10px;
    left: 10px;
    right: 10px;
    display: flex;
    gap: 4px;
    z-index: 10;
    opacity: 0;
    transform: translateY(8px);
    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    pointer-events: none;
}

.product-card:hover .gallery-strip {
    opacity: 1;
    transform: translateY(0);
    pointer-events: auto;
}

.gallery-thumb {
    width: 36px;
    height: 36px;
    border-radius: 6px;
    object-fit: cover;
    border: 2px solid rgba(255,255,255,0.85);
    box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    cursor: pointer;
    transition: transform 0.2s;
}

.gallery-thumb:hover {
    transform: scale(1.15);
}

.gallery-more {
    width: 36px;
    height: 36px;
    border-radius: 6px;
    background: rgba(0,0,0,0.6);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.65rem;
    font-weight: 700;
    border: 2px solid rgba(255,255,255,0.85);
    backdrop-filter: blur(4px);
}

.product-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(45, 80, 22, 0.88) 0%, rgba(26, 38, 52, 0.92) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    opacity: 0;
    transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    z-index: 5;
}

.product-card:hover .product-overlay {
    opacity: 1;
}

.overlay-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    transform: translateY(20px) scale(0.85);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.product-card:hover .overlay-content {
    transform: translateY(0) scale(1);
}

.view-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.95);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--mobile-primary-dark);
    font-size: 1.1rem;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
    transition: all 0.3s;
}

.product-card:hover .view-icon {
    background: linear-gradient(135deg, #c9a959 0%, #c9a040 100%);
    color: white;
    box-shadow: 0 8px 24px rgba(201, 169, 89, 0.4);
}

.view-text {
    color: white;
    font-size: 0.8rem;
    font-weight: 600;
    text-shadow: 0 1px 4px rgba(0,0,0,0.3);
}

.product-info {
    padding: 1.25rem 1.5rem;
    background: white;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    border-radius: 0 0 24px 24px;
    justify-content: space-between;
    position: relative;
    z-index: 1;
    flex: 1;
    border-top: 1px solid rgba(93, 64, 55, 0.04);
}

.product-title-row {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.product-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #1a1a1a;
    margin: 0;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    transition: color 0.3s ease;
}

.product-card:hover .product-title {
    color: #5a4a2e;
}

.product-subtitle {
    font-size: 0.8rem;
    color: #888;
    font-weight: 400;
    margin: 0;
}

.product-details-row {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.product-category {
    font-size: 0.8rem;
    color: #6b7280;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    line-height: 1.3;
}

.product-category::before {
    content: '';
    display: inline-block;
    width: 5px;
    height: 5px;
    background: #c9a959;
    border-radius: 50%;
}

.product-meta-info {
    display: flex;
    flex-wrap: wrap;
    gap: 0.6rem;
    font-size: 0.75rem;
    color: #777;
}

.product-meta-info span {
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.product-meta-info span::before {
    content: '\2022';
    color: #555;
    font-weight: 700;
}

.product-meta-info span:first-child::before {
    content: none;
}

/* Price Row */
.product-price-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.price-old {
    font-size: 0.78rem;
    color: #999;
    text-decoration: line-through;
    font-weight: 500;
}

.price-current {
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--mobile-primary-dark);
    direction: ltr;
}

.discount-badge {
    font-size: 0.65rem;
    font-weight: 700;
    background: linear-gradient(135deg, #dc3545, #c82333);
    color: white;
    padding: 2px 7px;
    border-radius: 5px;
    letter-spacing: 0.3px;
}

.product-actions-row {
    display: flex;
    gap: 0.75rem;
    margin-top: auto;
    padding-top: 0.75rem;
    border-top: 1px solid rgba(93, 64, 55, 0.06);
}

.btn-whatsapp,
.btn-inquiry {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.7rem 0.85rem;
    border-radius: 14px;
    text-decoration: none;
    font-weight: 700;
    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    font-size: 0.8rem;
    position: relative;
    overflow: hidden;
    border: none;
    cursor: pointer;
    letter-spacing: 0.2px;
}

.btn-whatsapp::before,
.btn-inquiry::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.18), transparent);
    transition: left 0.6s ease;
}

.btn-whatsapp:hover::before,
.btn-inquiry:hover::before {
    left: 100%;
}

.btn-whatsapp {
    background: linear-gradient(135deg, #25d366 0%, #20ba58 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(37, 211, 102, 0.2);
}

.btn-whatsapp:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(37, 211, 102, 0.35);
}

.btn-whatsapp:active {
    transform: translateY(0) scale(0.96);
}

.btn-inquiry {
    background: linear-gradient(135deg, var(--mobile-primary) 0%, var(--mobile-primary-dark) 100%);
    color: white;
    box-shadow: 0 2px 8px color-mix(in srgb, var(--mobile-primary) 20%, transparent);
}

.btn-inquiry:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px color-mix(in srgb, var(--mobile-primary) 35%, transparent);
}

.btn-inquiry:active {
    transform: translateY(0) scale(0.96);
}

.btn-whatsapp i,
.btn-inquiry i {
    font-size: 1rem;
    transition: transform 0.35s ease;
}

.btn-whatsapp:hover i,
.btn-inquiry:hover i {
    transform: scale(1.15);
}

/* No Products */
.no-products {
    text-align: center;
    padding: 4rem 2rem;
}

.no-products i {
    font-size: 4rem;
    color: #5a6b7a;
    margin-bottom: 1.25rem;
}

.no-products h3 {
    color: var(--mobile-primary-dark);
    margin-bottom: 0.75rem;
    font-size: 1.5rem;
}

.no-products p {
    color: #6b7280;
    margin-bottom: 1.25rem;
}

.no-products .btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 2rem;
    background: linear-gradient(135deg, var(--mobile-primary) 0%, var(--mobile-primary-dark) 100%);
    color: white;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
}

.no-products .btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px color-mix(in srgb, var(--mobile-primary) 35%, transparent);
}

/* Pagination */
.pagination-tailwind {
    margin-top: 2rem;
}

.mobile-pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(93, 64, 55, 0.06);
}

.desktop-pagination {
    display: none;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(93, 64, 55, 0.06);
}

.btn-prev,
.btn-next {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s;
}

.btn-prev {
    background: linear-gradient(135deg, var(--mobile-primary) 0%, var(--mobile-primary-dark) 100%);
    color: white;
}

.btn-prev:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px color-mix(in srgb, var(--mobile-primary) 30%, transparent);
}

.btn-prev.disabled {
    background: #e5e7eb;
    color: #9ca3af;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.btn-next {
    background: linear-gradient(135deg, var(--mobile-primary) 0%, var(--mobile-primary-dark) 100%);
    color: white;
}

.btn-next:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px color-mix(in srgb, var(--mobile-primary) 30%, transparent);
}

.btn-next.disabled {
    background: #e5e7eb;
    color: #9ca3af;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.pagination-info {
    color: #6b7280;
    font-size: 0.875rem;
}

.pagination-info span {
    font-weight: 600;
    color: var(--mobile-primary-dark);
}

.pagination-buttons {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.page-btn {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s;
    background: white;
    color: var(--mobile-primary-dark);
    border: 1px solid #e5e7eb;
    cursor: pointer;
}

.page-btn:hover:not(.disabled) {
    background: linear-gradient(135deg, var(--mobile-primary) 0%, var(--mobile-primary-dark) 100%);
    color: white;
    border-color: transparent;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px color-mix(in srgb, var(--mobile-primary) 20%, transparent);
}

.page-btn.active {
    background: linear-gradient(135deg, var(--mobile-primary) 0%, var(--mobile-primary-dark) 100%);
    color: white;
    border-color: transparent;
}

.page-btn.disabled {
    background: #e5e7eb;
    color: #9ca3af;
    cursor: not-allowed;
}

/* Fade Up Animation */
.fade-up {
    opacity: 0;
    transform: translateY(30px);
    animation: fadeUp 0.6s ease-out forwards;
}

@keyframes fadeUp {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (min-width: 768px) {
    .mobile-pagination {
        display: none;
    }

    .desktop-pagination {
        display: flex;
    }
}

@media (max-width: 768px) {
    .page-header h1 {
        font-size: 1.75rem;
    }

    .products-grid {
        grid-template-columns: 1fr;
    }

    .product-actions-row {
        flex-direction: column;
    }

    .type-tabs {
        gap: 0.5rem;
    }

    .tab-btn {
        padding: 0.5rem 1rem;
        font-size: 0.8rem;
    }
}

/* Lightbox */
.lightbox-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.92);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(12px);
}

.lightbox-close {
    position: absolute;
    top: 20px;
    left: 20px;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255,255,255,0.2);
    color: white;
    font-size: 1.1rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
    z-index: 10;
}

.lightbox-close:hover {
    background: rgba(255, 255, 255, 0.25);
    transform: scale(1.1);
}

.lightbox-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255,255,255,0.2);
    color: white;
    font-size: 1.2rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
    z-index: 10;
}

.lightbox-nav:hover {
    background: rgba(255, 255, 255, 0.25);
    transform: translateY(-50%) scale(1.1);
}

.lightbox-prev {
    right: 20px;
}

.lightbox-next {
    left: 20px;
}

.lightbox-content {
    max-width: 85vw;
    max-height: 80vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
}

.lightbox-img {
    max-width: 100%;
    max-height: 72vh;
    border-radius: 12px;
    object-fit: contain;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
}

.lightbox-caption {
    color: white;
    text-align: center;
    margin-top: 16px;
    font-size: 1rem;
    font-weight: 600;
    text-shadow: 0 2px 8px rgba(0,0,0,0.4);
}

.lightbox-counter {
    color: rgba(255,255,255,0.6);
    font-size: 0.8rem;
    margin-top: 6px;
}

.lightbox-thumbs {
    position: absolute;
    bottom: -60px;
    display: flex;
    gap: 8px;
    justify-content: center;
    max-width: 80vw;
    overflow-x: auto;
    padding: 8px 0;
}

.lightbox-thumb {
    width: 48px;
    height: 48px;
    border-radius: 8px;
    object-fit: cover;
    cursor: pointer;
    opacity: 0.5;
    border: 2px solid transparent;
    transition: all 0.3s;
}

.lightbox-thumb:hover {
    opacity: 0.8;
}

.lightbox-thumb.active {
    opacity: 1;
    border-color: #c9a959;
    box-shadow: 0 0 12px rgba(201, 169, 89, 0.5);
}

.lightbox-fade-enter-active,
.lightbox-fade-leave-active {
    transition: opacity 0.3s ease;
}

.lightbox-fade-enter-from,
.lightbox-fade-leave-to {
    opacity: 0;
}

@media (max-width: 768px) {
    .lightbox-nav {
        width: 40px;
        height: 40px;
        font-size: 1rem;
    }

    .lightbox-prev {
        right: 10px;
    }

    .lightbox-next {
        left: 10px;
    }

    .lightbox-thumb {
        width: 36px;
        height: 36px;
    }
}
</style>
