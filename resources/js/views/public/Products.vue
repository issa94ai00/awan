<template>
    <div class="products-page">
        <!-- Page Header -->
        <section class="page-header">
            <div class="container">
                <h1>{{ t('nav_products') || 'المنتجات' }}</h1>
                <div class="breadcrumb">
                    <router-link to="/">{{ t('nav_home') || 'الرئيسية' }}</router-link>
                    <span class="sep">/</span>
                    <span>{{ t('nav_products') || 'المنتجات' }}</span>
                </div>
            </div>
        </section>

        <!-- Filters Section -->
        <div class="products-filters">
            <div class="container">
                <div class="filters-row">
                    <div class="filter-group">
                        <label class="filter-label">{{ t('search') || 'البحث' }}</label>
                        <input 
                            type="text" 
                            v-model="searchQuery" 
                            @input="handleSearch"
                            class="filter-input" 
                            :placeholder="t('search_placeholder') || 'ابحث عن منتج...'"
                        >
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">{{ t('nav_categories') || 'الفئة' }}</label>
                        <select v-model="selectedCategory" @change="resetAndLoad" class="filter-select">
                            <option value="">{{ t('main_categories') || 'جميع الفئات' }}</option>
                            <option v-for="category in categories" :key="category.id" :value="category.id">
                                {{ $p(category, 'name') }} ({{ category.product_count || 0 }})
                            </option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">{{ t('price') || 'نطاق السعر' }}</label>
                        <div class="price-range">
                            <input 
                                type="number" 
                                v-model="priceMin" 
                                @change="resetAndLoad"
                                class="filter-input" 
                                placeholder="Min"
                                min="0"
                            >
                            <span class="price-separator">-</span>
                            <input 
                                type="number" 
                                v-model="priceMax" 
                                @change="resetAndLoad"
                                class="filter-input" 
                                placeholder="Max"
                                min="0"
                            >
                        </div>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">{{ t('sort_by') || 'الترتيب' }}</label>
                        <select v-model="sortBy" @change="resetAndLoad" class="filter-select">
                            <option value="latest">{{ t('newest') || 'الأحدث' }}</option>
                            <option value="price_asc">{{ t('price_low_high') || 'السعر: من الأقل للأعلى' }}</option>
                            <option value="price_desc">{{ t('price_high_low') || 'السعر: من الأعلى للأقل' }}</option>
                            <option value="name_asc">A-Z</option>
                            <option value="name_desc">Z-A</option>
                        </select>
                    </div>
                    <div class="filter-group view-toggle">
                        <label class="filter-label">{{ t('view_label') || 'العرض' }}</label>
                        <div class="view-buttons">
                            <button 
                                @click="viewMode = 'grid'" 
                                :class="['view-btn', { active: viewMode === 'grid' }]"
                                :title="t('view_grid') || 'عرض الشبكة'"
                            >
                                <i class="fas fa-th-large"></i>
                            </button>
                            <button 
                                @click="viewMode = 'list'" 
                                :class="['view-btn', { active: viewMode === 'list' }]"
                                :title="t('view_list') || 'عرض القائمة'"
                            >
                                <i class="fas fa-list"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Products Grid -->
        <div class="products-content">
            <!-- Success Message -->
            <transition name="fade-slide">
                <div v-if="showSuccessMessage" class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <span>{{ successMessage }}</span>
                </div>
            </transition>

            <div class="container">
                <div v-if="loading && products.length === 0" class="products-loading">
                    <div class="loading-spinner"></div>
                    <p>{{ t('loading_products') || 'جاري تحميل المنتجات...' }}</p>
                </div>

                <div v-else-if="products.length === 0" class="products-empty">
                    <i class="fas fa-box-open"></i>
                    <p>{{ t('no_products_found') || 'لا توجد منتجات حالياً' }}</p>
                </div>

                <div v-else :class="['products-grid', viewMode]">
                    <div 
                        v-for="product in products" 
                        :key="product.id" 
                        class="product-card"
                        @click="goToProduct(product.slug)"
                    >
                        <div class="product-image">
                            <img 
                                :src="getImageUrl(product.image_main)" 
                                :alt="$p(product, 'name')" 
                                class="product-img"
                            >
                            <div v-if="product.discount_percentage && product.discount_percentage > 0" class="product-badge">
                                -{{ product.discount_percentage }}%
                            </div>
                        </div>
                        <div class="product-info">
                            <h3 class="product-name">{{ $p(product, 'name') }}</h3>
                            <p class="product-category">{{ getCategoryName(product.category_id) }}</p>
                            <p v-if="viewMode === 'list'" class="product-description">
                                {{ $p(product, 'description')?.substring(0, 150) }}...
                            </p>
                            <div class="product-price">
                                <span v-if="product.discount_percentage && product.discount_percentage > 0" class="product-old-price">
                                    {{ formatPrice(product.price) }}
                                </span>
                                <span class="product-current-price">
                                    {{ formatPrice(product.sale_price || product.price) }}
                                </span>
                            </div>
                            <div class="product-actions">
                                <button class="btn-view-details" @click.stop="goToProduct(product.slug)">
                                    <i class="fas fa-eye"></i> {{ t('product_details') || 'عرض التفاصيل' }}
                                </button>
                                <button class="btn-add-cart" @click.stop="addToCart(product)">
                                    <i class="fas fa-cart-plus"></i> {{ t('add_to_cart') || 'إضافة للسلة' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Load More / Loading Indicator -->
                <div v-if="products.length > 0" class="products-pagination">
                    <div v-if="loading" class="loading-more">
                        <div class="loading-spinner"></div>
                        <p>{{ t('loading_more') || 'جاري تحميل المزيد...' }}</p>
                    </div>
                    <div v-else-if="hasMore" class="load-more-container">
                        <button @click="loadMore" class="btn-load-more">
                            {{ t('load_more') || 'تحميل المزيد' }}
                        </button>
                    </div>
                    <div v-else class="no-more-products">
                        <p>{{ t('all_products_loaded') || 'تم تحميل جميع المنتجات' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useProductsStore } from '@/stores/products';
import { useCartStore } from '@/stores/cart';
import { useSettingsStore } from '@/stores/settings';
import { useI18n } from 'vue-i18n';

const router = useRouter();
const productsStore = useProductsStore();
const cartStore = useCartStore();
const settingsStore = useSettingsStore();
const { t, locale } = useI18n();

// State
const products = ref([]);
const categories = ref([]);
const loading = ref(false);
const hasMore = ref(true);
const currentPage = ref(1);
const perPage = ref(12);
const searchQuery = ref('');
const selectedCategory = ref('');
const sortBy = ref('latest');
const priceMin = ref('');
const priceMax = ref('');
const viewMode = ref('grid');
const searchTimeout = ref(null);
const successMessage = ref('');
const showSuccessMessage = ref(false);

// Computed
const settings = computed(() => settingsStore.data || {});

// Methods
const getImageUrl = (image) => {
    if (!image) return '/assets/images/placeholder.jpg';
    if (image.startsWith('http')) return image;
    return `/storage/${image}`;
};

const formatPrice = (price) => {
    if (!price) return '0';
    const currency = settings.value?.currency || 'SYP';
    return `${parseFloat(price).toLocaleString()} ${currency}`;
};

const getCategoryName = (categoryId) => {
    const category = categories.value.find(c => c.id === categoryId);
    if (!category) return '';
    const localizedField = `name_${locale.value || 'ar'}`;
    return category[localizedField] || category.name_ar || '';
};

const loadProducts = async (page = 1, append = false) => {
    if (loading.value) return;
    
    loading.value = true;
    
    try {
        const params = {
            page: page,
            per_page: perPage.value,
            sort: sortBy.value
        };
        
        if (searchQuery.value) {
            params.search = searchQuery.value;
        }
        
        if (selectedCategory.value) {
            params.category_id = selectedCategory.value;
        }
        
        if (priceMin.value) {
            params.price_min = priceMin.value;
        }
        
        if (priceMax.value) {
            params.price_max = priceMax.value;
        }
        
        const response = await productsStore.fetchPublicProducts(params);
        
        // Handle different response structures
        const productsData = response?.data || response || [];
        const productsArray = Array.isArray(productsData) ? productsData : [];
        
        if (append) {
            products.value = [...products.value, ...productsArray];
        } else {
            products.value = productsArray;
        }
        
        if (response?.pagination) {
            hasMore.value = response.pagination.has_more_pages;
        } else if (response?.has_more_pages !== undefined) {
            hasMore.value = response.has_more_pages;
        } else {
            hasMore.value = productsArray.length === perPage.value;
        }
        currentPage.value = page;
    } catch (error) {
        console.error('Error loading products:', error);
        // Ensure products is always an array
        if (!append) {
            products.value = [];
        }
    } finally {
        loading.value = false;
    }
};

const loadCategories = async () => {
    try {
        categories.value = await productsStore.fetchPublicCategories();
    } catch (error) {
        console.error('Error loading categories:', error);
    }
};

const loadMore = () => {
    if (!loading.value && hasMore.value) {
        loadProducts(currentPage.value + 1, true);
    }
};

const resetAndLoad = () => {
    products.value = [];
    currentPage.value = 1;
    hasMore.value = true;
    loadProducts(1, false);
};

const handleSearch = () => {
    clearTimeout(searchTimeout.value);
    searchTimeout.value = setTimeout(() => {
        resetAndLoad();
    }, 500);
};

const goToProduct = (slug) => {
    router.push(`/product/${slug}`);
};

const addToCart = (product) => {
    const localizedName = product[`name_${locale.value || 'ar'}`] || product.name_ar || '';
    cartStore.addToCart({
        id: product.id,
        name: localizedName,
        price: product.sale_price || product.price,
        image: product.image_main,
        quantity: 1
    });
    
    // Show success message
    successMessage.value = t('added_to_cart_success', { name: localizedName }) || `تمت إضافة "${localizedName}" إلى السلة بنجاح`;
    showSuccessMessage.value = true;
    
    // Hide message after 3 seconds
    setTimeout(() => {
        showSuccessMessage.value = false;
    }, 3000);
};

// Infinite Scroll
const handleScroll = () => {
    const scrollTop = window.scrollY;
    const windowHeight = window.innerHeight;
    const documentHeight = document.documentElement.scrollHeight;
    
    if (scrollTop + windowHeight >= documentHeight - 500 && !loading.value && hasMore.value) {
        loadMore();
    }
};

// Lifecycle
onMounted(async () => {
    await loadCategories();
    await loadProducts(1, false);
    
    window.addEventListener('scroll', handleScroll);
});

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
    clearTimeout(searchTimeout.value);
});
</script>

<style scoped>
.products-page {
    min-height: 100vh;
    background: #f8f9fa;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 15px;
}

.products-filters {
    background: #ffffff;
    padding: 20px 0;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    position: sticky;
    top: 70px;
    z-index: 100;
}

.filters-row {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.filter-group {
    flex: 1;
    min-width: 200px;
}

.price-range {
    display: flex;
    align-items: center;
    gap: 10px;
}

.price-range .filter-input {
    flex: 1;
}

.price-separator {
    color: #666;
    font-weight: 600;
}

.view-toggle {
    flex: 0 0 auto;
    min-width: auto;
}

.view-buttons {
    display: flex;
    gap: 8px;
}

.view-btn {
    padding: 10px 15px;
    border: 1px solid #ddd;
    background: #ffffff;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    color: #666;
}

.view-btn:hover {
    background: #f3f4f6;
    border-color: #ccc;
}

.view-btn.active {
    background: var(--navbar-bg-color, var(--mobile-primary-dark));
    color: #ffffff;
    border-color: var(--navbar-bg-color, var(--mobile-primary-dark));
}

.filter-label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    color: #333;
}

.filter-input,
.filter-select {
    width: 100%;
    padding: 10px 15px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.filter-input:focus,
.filter-select:focus {
    outline: none;
    border-color: var(--navbar-bg-color, var(--mobile-primary-dark));
    box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
}

.products-content {
    padding: 40px 0;
    position: relative;
}

.success-message {
    position: fixed;
    top: 100px;
    left: 50%;
    transform: translateX(-50%);
    background: #10b981;
    color: #ffffff;
    padding: 15px 25px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    display: flex;
    align-items: center;
    gap: 10px;
    z-index: 1000;
    font-weight: 600;
}

.success-message i {
    font-size: 1.2rem;
}

.fade-slide-enter-active,
.fade-slide-leave-active {
    transition: all 0.3s ease;
}

.fade-slide-enter-from,
.fade-slide-leave-to {
    opacity: 0;
    transform: translate(-50%, -20px);
}

.products-loading,
.products-empty {
    text-align: center;
    padding: 60px 20px;
}

.loading-spinner {
    width: 50px;
    height: 50px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid var(--navbar-bg-color, var(--mobile-primary-dark));
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 20px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.products-empty i {
    font-size: 4rem;
    color: #ccc;
    margin-bottom: 20px;
}

.products-grid {
    display: grid;
    gap: 25px;
    margin-bottom: 40px;
}

.products-grid.grid {
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
}

.products-grid.list {
    grid-template-columns: 1fr;
}

.products-grid.list .product-card {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 20px;
}

.products-grid.list .product-image {
    width: 250px;
    height: 200px;
    flex-shrink: 0;
}

.products-grid.list .product-info {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.products-grid.list .product-description {
    font-size: 0.9rem;
    color: #666;
    margin-bottom: 12px;
    line-height: 1.5;
}

.product-card {
    background: #ffffff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    cursor: pointer;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.product-image {
    position: relative;
    height: 220px;
    overflow: hidden;
}

.product-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.product-card:hover .product-img {
    transform: scale(1.05);
}

.product-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    background: #ef4444;
    color: #ffffff;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
}

.product-info {
    padding: 20px;
}

.product-name {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 8px;
    color: #333;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.product-category {
    font-size: 0.9rem;
    color: #666;
    margin-bottom: 12px;
}

.product-price {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
}

.product-old-price {
    text-decoration: line-through;
    color: #999;
    font-size: 0.9rem;
}

.product-current-price {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--navbar-bg-color, var(--mobile-primary-dark));
}

.product-actions {
    display: flex;
    gap: 10px;
}

.btn-view-details,
.btn-add-cart {
    flex: 1;
    padding: 10px 15px;
    border: none;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-view-details {
    background: #f3f4f6;
    color: #333;
}

.btn-view-details:hover {
    background: #e5e7eb;
}

.btn-add-cart {
    background: var(--navbar-bg-color, var(--mobile-primary-dark));
    color: #ffffff;
}

.btn-add-cart:hover {
    background: var(--navbar-scrolled-bg-color, var(--mobile-primary-dark));
}

.products-pagination {
    text-align: center;
    padding: 30px 0;
}

.loading-more {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 15px;
}

.btn-load-more {
    padding: 12px 30px;
    background: var(--navbar-bg-color, var(--mobile-primary-dark));
    color: #ffffff;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-load-more:hover {
    background: var(--navbar-scrolled-bg-color, var(--mobile-primary-dark));
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

/* Dark theme styles */
[data-theme="dark"] .products-page {
    background: #0f172a;
}

[data-theme="dark"] .products-filters {
    background: rgba(30, 41, 59, 0.8);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

[data-theme="dark"] .view-btn {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.1);
    color: #e2e8f0;
}

[data-theme="dark"] .view-btn:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(255, 255, 255, 0.2);
}

[data-theme="dark"] .view-btn.active {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: #ffffff;
    border-color: transparent;
}

[data-theme="dark"] .filter-label {
    color: #cbd5e1;
}

[data-theme="dark"] .filter-input,
[data-theme="dark"] .filter-select {
    background: rgba(15, 23, 42, 0.5);
    border-color: rgba(255, 255, 255, 0.1);
    color: #f1f5f9;
}

[data-theme="dark"] .filter-input:focus,
[data-theme="dark"] .filter-select:focus {
    border-color: var(--mobile-primary);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--mobile-primary) 20%, transparent);
}

[data-theme="dark"] .price-separator {
    color: #94a3b8;
}

[data-theme="dark"] .success-message {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

[data-theme="dark"] .loading-spinner {
    border-color: rgba(255, 255, 255, 0.1);
    border-top-color: var(--mobile-primary);
}

[data-theme="dark"] .products-empty i {
    color: #475569;
}

[data-theme="dark"] .product-card {
    background: rgba(30, 41, 59, 0.6);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.2);
}

[data-theme="dark"] .product-card:hover {
    background: rgba(30, 41, 59, 0.8);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
}

[data-theme="dark"] .product-name {
    color: #f1f5f9;
}

[data-theme="dark"] .product-category {
    color: #94a3b8;
}

[data-theme="dark"] .product-description {
    color: #cbd5e1;
}

[data-theme="dark"] .product-old-price {
    color: #64748b;
}

[data-theme="dark"] .product-current-price {
    color: var(--mobile-primary);
}

[data-theme="dark"] .btn-view-details {
    background: rgba(255, 255, 255, 0.05);
    color: #e2e8f0;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

[data-theme="dark"] .btn-view-details:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(255, 255, 255, 0.2);
}

[data-theme="dark"] .btn-add-cart {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
}

[data-theme="dark"] .btn-add-cart:hover {
    background: linear-gradient(135deg, #818cf8 0%, #a78bfa 100%);
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
}

[data-theme="dark"] .btn-load-more {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
}

[data-theme="dark"] .btn-load-more:hover {
    background: linear-gradient(135deg, #818cf8 0%, #a78bfa 100%);
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
}

.no-more-products {
    color: #666;
    font-size: 0.95rem;
}

@media (max-width: 768px) {
    .products-title {
        font-size: 2rem;
    }
    
    .products-subtitle {
        font-size: 1rem;
    }
    
    .filters-row {
        flex-direction: column;
    }
    
    .filter-group {
        min-width: 100%;
    }
    
    .price-range {
        flex-direction: column;
        gap: 10px;
    }
    
    .price-separator {
        display: none;
    }
    
    .products-grid.grid {
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 20px;
    }
    
    .products-grid.list .product-card {
        flex-direction: column;
    }
    
    .products-grid.list .product-image {
        width: 100%;
        height: 200px;
    }
    
    .product-actions {
        flex-direction: column;
    }
}
</style>
