<template>
    <div class="featured-products-page">
        <!-- Page Header -->
        <section class="page-header">
            <div class="container">
                <h1>المنتجات المميزة</h1>
                <div class="breadcrumb">
                    <router-link to="/">الرئيسية</router-link>
                    <span>/</span>
                    <span>المنتجات المميزة</span>
                </div>
            </div>
        </section>

        <!-- Featured Products Section -->
        <section class="featured-products-section fade-up">
            <div class="container">
                <div class="section-header">
                    <h2>منتجاتنا المميزة</h2>
                    <p>اختر من مجموعتنا المتميزة من منتجات البناء عالية الجودة</p>
                </div>

                <div v-if="products.length > 0">
                    <div class="products-grid">
                        <div 
                            v-for="product in products" 
                            :key="product.id" 
                            class="product-card"
                        >
                            <div class="product-image">
                                <img 
                                    :src="product.image_main ? product.image_main : '/assets/images/products/default-product.jpg'" 
                                    :alt="product.name_ar"
                                    loading="lazy"
                                >
                                <div class="product-overlay">
                                    <router-link :to="{ name: 'product.show', params: { slug: product.slug } }" class="view-btn">
                                        <i class="fas fa-eye"></i>
                                    </router-link>
                                </div>
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
                                </div>
                                <div class="product-actions-row">
                                    <a :href="`https://wa.me/${whatsappNumber}?text=${encodeURIComponent('مرحباً، أنا مهتم بمنتج: ' + product.name_ar)}`" class="btn-whatsapp" target="_blank">
                                        <i class="fab fa-whatsapp"></i>
                                        <span>واتساب</span>
                                    </a>
                                    <router-link :to="{ name: 'inquiry.form', query: { product_id: product.id, product_name: product.name_ar } }" class="btn-inquiry">
                                        <i class="fas fa-question-circle"></i>
                                        <span>استفسار</span>
                                    </router-link>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div v-if="pagination.total > pagination.per_page" class="pagination-tailwind">
                        <!-- Mobile view -->
                        <div class="mobile-pagination">
                            <span v-if="pagination.current_page === 1" class="btn-prev disabled">
                                <i class="fas fa-chevron-right"></i>
                                السابق
                            </span>
                            <a v-else @click="pageChanged(pagination.current_page - 1)" class="btn-prev">
                                <i class="fas fa-chevron-right"></i>
                                السابق
                            </a>

                            <span v-if="pagination.current_page >= pagination.last_page" class="btn-next disabled">
                                التالي
                                <i class="fas fa-chevron-left"></i>
                            </span>
                            <a v-else @click="pageChanged(pagination.current_page + 1)" class="btn-next">
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
                                <!-- Previous -->
                                <span v-if="pagination.current_page === 1" class="page-btn prev disabled">
                                    <i class="fas fa-chevron-right"></i>
                                </span>
                                <a v-else @click="pageChanged(pagination.current_page - 1)" class="page-btn prev">
                                    <i class="fas fa-chevron-right"></i>
                                </a>

                                <!-- Page Numbers -->
                                <span 
                                    v-for="page in totalPages" 
                                    :key="page"
                                    :class="['page-btn', { active: page === pagination.current_page }]"
                                    @click="pageChanged(page)"
                                >
                                    {{ page }}
                                </span>

                                <!-- Next -->
                                <span v-if="pagination.current_page >= pagination.last_page" class="page-btn next disabled">
                                    <i class="fas fa-chevron-left"></i>
                                </span>
                                <a v-else @click="pageChanged(pagination.current_page + 1)" class="page-btn next">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="no-products">
                    <i class="fas fa-box-open"></i>
                    <h3>لا توجد منتجات مميزة حالياً</h3>
                    <p>يمكنك تصفح جميع منتجاتنا من خلال صفحة الفئات</p>
                    <router-link to="/categories" class="btn">
                        <i class="fas fa-th-large"></i>
                        تصفح الفئات
                    </router-link>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useProductsStore } from '@/stores/products';

const productsStore = useProductsStore();

const loading = ref(false);
const products = ref([]);
const pagination = ref({});
const whatsappNumber = ref('963900000000');

const totalPages = computed(() => {
    if (!pagination.value.last_page) return [];
    const pages = [];
    for (let i = 1; i <= pagination.value.last_page; i++) {
        pages.push(i);
    }
    return pages;
});

async function loadProducts(page = 1) {
    loading.value = true;
    try {
        const params = { featured: true, page };
        await productsStore.fetch(params);
        products.value = productsStore.items;
        pagination.value = productsStore.pagination;
    } catch (error) {
        console.error('Failed to load products:', error);
    } finally {
        loading.value = false;
    }
}

function pageChanged(page) {
    loadProducts(page);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

onMounted(() => loadProducts());
</script>

<style scoped>
.featured-products-page {
    min-height: 100vh;
}

/* Page Header */
.page-header {
    padding-top: 120px;
    background: linear-gradient(135deg, #1e3a8a, #3b82f6);
    color: white;
    padding-bottom: 3rem;
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

/* Featured Products Section */
.featured-products-section {
    padding: 4rem 0;
}

.section-header {
    text-align: center;
    margin-bottom: 3rem;
}

.section-header h2 {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: #1e3a8a;
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
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
}

.product-image {
    position: relative;
    height: 250px;
    overflow: hidden;
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

.product-actions-row {
    display: flex;
    gap: 0.5rem;
}

.btn-whatsapp,
.btn-inquiry {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s;
    font-size: 0.875rem;
}

.btn-whatsapp {
    background: #25d366;
    color: white;
}

.btn-whatsapp:hover {
    background: #128c7e;
}

.btn-inquiry {
    background: #3b82f6;
    color: white;
}

.btn-inquiry:hover {
    background: #2563eb;
}

/* No Products */
.no-products {
    text-align: center;
    padding: 4rem 2rem;
}

.no-products i {
    font-size: 4rem;
    color: #3b82f6;
    margin-bottom: 1.25rem;
}

.no-products h3 {
    color: #1e3a8a;
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
    background: #3b82f6;
    color: white;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
}

.no-products .btn:hover {
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
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.desktop-pagination {
    display: none;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.btn-prev,
.btn-next {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s;
}

.btn-prev {
    background: #3b82f6;
    color: white;
}

.btn-prev:hover {
    background: #2563eb;
}

.btn-prev.disabled {
    background: #e5e7eb;
    color: #9ca3af;
    cursor: not-allowed;
}

.btn-next {
    background: #3b82f6;
    color: white;
}

.btn-next:hover {
    background: #2563eb;
}

.btn-next.disabled {
    background: #e5e7eb;
    color: #9ca3af;
    cursor: not-allowed;
}

.pagination-info {
    color: #6b7280;
    font-size: 0.875rem;
}

.pagination-info span {
    font-weight: 600;
    color: #1e3a8a;
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
    border-radius: 6px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s;
    background: white;
    color: #1e3a8a;
    border: 1px solid #e5e7eb;
    cursor: pointer;
}

.page-btn:hover:not(.disabled) {
    background: #3b82f6;
    color: white;
    border-color: #3b82f6;
}

.page-btn.active {
    background: #3b82f6;
    color: white;
    border-color: #3b82f6;
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
}
</style>
