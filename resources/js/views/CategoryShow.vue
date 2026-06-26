<template>
    <div class="category-show-page">
        <!-- Page Header -->
        <section class="page-header">
            <div class="container">
                <h1>{{ category?.name_ar }}</h1>
                <div class="breadcrumb">
                    <router-link to="/">الرئيسية</router-link>
                    <span class="sep">›</span>
                    <span>{{ category?.name_ar }}</span>
                </div>
            </div>
        </section>

        <!-- Products Section -->
        <section class="products-section category-products-section fade-up">
            <div class="container">
                <div class="products-header">
                    <div>
                        <h2 class="section-title">منتجات {{ category?.name_ar }}</h2>
                        <p>{{ category?.description || 'تصفح المنتجات ضمن هذه الفئة' }}</p>
                    </div>
                    <div class="products-count">
                        <span class="count-badge">{{ pagination.total }} منتج</span>
                    </div>
                </div>

                <div v-if="loading" class="loading-state">
                    <div class="spinner"></div>
                    <p>جاري تحميل المنتجات...</p>
                </div>

                <div class="products-grid" v-else-if="products.length > 0">
                    <div 
                        v-for="(product, index) in products" 
                        :key="product.id" 
                        class="product-card"
                        :style="{ animationDelay: `${index * 0.05}s` }"
                    >
                        <div class="product-image">
                            <div class="badges-container">
                                <span v-if="product.is_featured" class="badge badge-featured">مميز</span>
                                <span v-if="!product.in_stock" class="badge badge-out">غير متوفر</span>
                                <span v-else class="badge badge-in">متوفر</span>
                            </div>
                            <img 
                                :src="product.image_main ? product.image_main : '/assets/images/products/default-product.jpg'" 
                                :alt="product.name_ar"
                                loading="lazy"
                            >
                            <router-link :to="{ name: 'product.show', params: { slug: product.slug } }" class="product-overlay">
                                <span class="view-btn"><i class="fas fa-eye"></i></span>
                            </router-link>
                        </div>
                        <div class="product-info">
                            <div class="product-title-row">
                                <h3 class="product-title">{{ product.name_ar }}</h3>
                                <span v-if="product.name_en" class="product-subtitle">{{ product.name_en }}</span>
                            </div>
                            <div class="product-details-row">
                                <div class="product-category">{{ product.category?.name_ar || 'منتجات بناء' }}</div>
                                <div v-if="product.brand || product.model" class="product-meta-info">
                                    <span v-if="product.brand">{{ product.brand }}</span>
                                    <span v-if="product.model">{{ product.model }}</span>
                                </div>
                                <div v-if="product.price" class="product-price">
                                    <span>${{ formatPrice(product.price) }}</span>
                                </div>
                            </div>
                            <div class="product-actions-row">
                                <a :href="`https://wa.me/${whatsappNumber}?text=${encodeURIComponent('مرحباً، أنا مهتم بمنتج: ' + product.name_ar)}`" class="btn-whatsapp" target="_blank">
                                    <i class="fab fa-whatsapp"></i>
                                    <span>واتساب</span>
                                </a>
                                <router-link :to="{ name: 'inquiry', query: { product_id: product.id, product_name: product.name_ar } }" class="btn-inquiry">
                                    <i class="fas fa-question-circle"></i>
                                    <span>استفسار</span>
                                </router-link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <nav v-if="pagination.total > pagination.per_page" class="pagination-tailwind">
                    <!-- Mobile view -->
                    <div class="mobile-pagination">
                        <span v-if="pagination.current_page === 1" class="btn-prev disabled">
                            <i class="fas fa-chevron-right"></i>
                            السابق
                        </span>
                        <a v-else @click="goToPage(pagination.current_page - 1)" class="btn-prev">
                            <i class="fas fa-chevron-right"></i>
                            السابق
                        </a>

                        <span v-if="pagination.current_page >= pagination.last_page" class="btn-next disabled">
                            التالي
                            <i class="fas fa-chevron-left"></i>
                        </span>
                        <a v-else @click="goToPage(pagination.current_page + 1)" class="btn-next">
                            التالي
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </div>

                    <!-- Desktop view -->
                    <div class="desktop-pagination">
                        <p class="pagination-info">
                            عرض <span>{{ pagination.from || 0 }}</span> إلى <span>{{ pagination.to || 0 }}</span> من <span>{{ pagination.total }}</span> منتج
                        </p>

                        <div class="pagination-buttons">
                            <span v-if="pagination.current_page === 1" class="page-btn prev disabled">
                                <i class="fas fa-chevron-right"></i>
                            </span>
                            <a v-else @click="goToPage(pagination.current_page - 1)" class="page-btn prev">
                                <i class="fas fa-chevron-right"></i>
                            </a>

                            <span 
                                v-for="page in getPageNumbers()" 
                                :key="page"
                                :class="['page-btn', { active: page === pagination.current_page }]"
                                @click="page !== '...' && goToPage(page)"
                            >
                                {{ page }}
                            </span>

                            <span v-if="pagination.current_page >= pagination.last_page" class="page-btn next disabled">
                                <i class="fas fa-chevron-left"></i>
                            </span>
                            <a v-else @click="goToPage(pagination.current_page + 1)" class="page-btn next">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </div>
                    </div>
                </nav>

                <div v-else class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <p>لا توجد منتجات حالياً ضمن هذه الفئة</p>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useCategoriesStore } from '@/stores/categories';
import { useProductsStore } from '@/stores/products';

const route = useRoute();
const router = useRouter();
const categoriesStore = useCategoriesStore();
const productsStore = useProductsStore();

const loading = ref(false);
const category = ref(null);
const products = ref([]);
const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 12,
    total: 0,
    from: 0,
    to: 0
});
const whatsappNumber = ref('963900000000');

const categoryId = computed(() => route.params.id || route.params.slug);

async function loadCategory() {
    loading.value = true;
    try {
        await categoriesStore.fetchCategory(categoryId.value);
        category.value = categoriesStore.currentCategory;
        await loadProducts();
    } catch (error) {
        console.error('Failed to load category:', error);
    } finally {
        loading.value = false;
    }
}

async function loadProducts(page = 1) {
    loading.value = true;
    try {
        await productsStore.fetchProducts({ 
            category_slug: categoryId.value, 
            page,
            per_page: 12
        });
        products.value = productsStore.products;
        pagination.value = productsStore.pagination;
    } catch (error) {
        console.error('Failed to load products:', error);
    } finally {
        loading.value = false;
    }
}

function goToPage(page) {
    if (page === pagination.value.current_page) return;
    loadProducts(page);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function getPageNumbers() {
    const pages = [];
    const current = pagination.value.current_page;
    const last = pagination.value.last_page;
    
    if (last <= 7) {
        for (let i = 1; i <= last; i++) {
            pages.push(i);
        }
    } else {
        if (current <= 4) {
            for (let i = 1; i <= 5; i++) pages.push(i);
            pages.push('...');
            pages.push(last);
        } else if (current >= last - 3) {
            pages.push(1);
            pages.push('...');
            for (let i = last - 4; i <= last; i++) pages.push(i);
        } else {
            pages.push(1);
            pages.push('...');
            for (let i = current - 1; i <= current + 1; i++) pages.push(i);
            pages.push('...');
            pages.push(last);
        }
    }
    return pages;
}

function formatPrice(price) {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(price);
}

onMounted(() => loadCategory());
</script>

<style scoped>
.category-show-page {
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
    font-size: 2.5rem;
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

/* Products Section */
.products-section {
    padding: 4rem 0;
}

.products-header {
    margin-bottom: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.section-title {
    font-size: 1.75rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: #1e3a8a;
}

.products-header p {
    color: #6b7280;
}

.products-count {
    display: flex;
    align-items: center;
}

.count-badge {
    background: #f0f9ff;
    color: #3b82f6;
    padding: 0.5rem 1.5rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.875rem;
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 2rem;
    margin-bottom: 3rem;
}

.product-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
    opacity: 0;
    animation: fadeUp 0.6s ease-out forwards;
}

.product-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

.product-image {
    position: relative;
    height: 250px;
    overflow: hidden;
}

.badges-container {
    position: absolute;
    top: 1rem;
    right: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    z-index: 5;
}

.badge {
    padding: 0.25rem 0.75rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge-in {
    background: #10b981;
    color: white;
}

.badge-out {
    background: #ef4444;
    color: white;
}

.badge-featured {
    background: #f59e0b;
    color: white;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s;
}

.product-card:hover .product-image img {
    transform: scale(1.1);
}

.product-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s;
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
    color: #1e3a8a;
    text-decoration: none;
    transition: all 0.3s;
}

.view-btn:hover {
    background: #3b82f6;
    color: white;
    transform: scale(1.1);
}

.product-info {
    padding: 1.5rem;
}

.product-title-row {
    margin-bottom: 1rem;
}

.product-title {
    font-size: 1.125rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
    color: #1a1a1a;
}

.product-subtitle {
    font-size: 0.875rem;
    color: #6b7280;
}

.product-details-row {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.product-category {
    color: #6b7280;
    font-size: 0.875rem;
}

.product-meta-info {
    display: flex;
    gap: 0.5rem;
    font-size: 0.875rem;
    color: #6b7280;
}

.product-price {
    font-weight: 700;
    color: #3b82f6;
    font-size: 1.125rem;
}

.product-actions-row {
    display: flex;
    gap: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px solid rgba(0, 0, 0, 0.06);
}

.btn-whatsapp,
.btn-inquiry {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.7rem 0.85rem;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 700;
    font-size: 0.85rem;
    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
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
    background: linear-gradient(135deg, #20ba58 0%, #128c7e 100%);
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
    background: linear-gradient(135deg, var(--mobile-primary-dark) 0%, color-mix(in srgb, var(--mobile-primary-dark) 80%, black) 100%);
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
    background: #2563eb;
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
    border-radius: 8px;
    margin-bottom: 1rem;
}

.desktop-pagination {
    display: none;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: white;
    border-radius: 8px;
}

.btn-prev,
.btn-next,
.page-btn {
    padding: 0.5rem 1rem;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
}

.btn-prev,
.btn-next {
    background: #3b82f6;
    color: white;
    border: none;
}

.btn-prev:hover,
.btn-next:hover {
    background: #2563eb;
}

.btn-prev.disabled,
.btn-next.disabled {
    background: #d1d5db;
    cursor: not-allowed;
}

.page-btn {
    background: white;
    color: #374151;
    border: 1px solid #e5e7eb;
}

.page-btn:hover {
    background: #f9fafb;
}

.page-btn.active {
    background: #3b82f6;
    color: white;
    border-color: #3b82f6;
}

.pagination-info {
    color: #6b7280;
    font-size: 0.875rem;
}

.pagination-buttons {
    display: flex;
    gap: 0.5rem;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: #6b7280;
}

.empty-state i {
    font-size: 5rem;
    margin-bottom: 1.5rem;
    color: #d1d5db;
}

.empty-state p {
    font-size: 1.125rem;
    margin-bottom: 2rem;
}

/* Loading State */
.loading-state {
    text-align: center;
    padding: 4rem 2rem;
    color: #6b7280;
}

.spinner {
    width: 50px;
    height: 50px;
    border: 4px solid #e5e7eb;
    border-top-color: #3b82f6;
    border-radius: 50%;
    margin: 0 auto 1.5rem;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
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

    .section-title {
        font-size: 1.5rem;
    }

    .products-grid {
        grid-template-columns: 1fr;
    }
}
</style>
