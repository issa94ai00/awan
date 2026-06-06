<template>
    <div class="home-page">
        <!-- Preloader -->
        <div v-if="showPreloader" class="preloader">
            <div class="preloader-content">
                <div class="logo-pulse">
                    <img :src="siteLogo" :alt="siteName" class="preloader-logo">
                    <div class="spinner-ring"></div>
                </div>
                <div class="preloader-text">{{ siteName }}</div>
            </div>
        </div>

        <!-- Hero Section -->
        <section class="hero" id="home">
            <div class="hero-background">
                <div class="hero-gradient"></div>
                <div class="hero-pattern"></div>
            </div>
            <div class="hero-content">
                <div class="hero-badge">
                    <i class="fas fa-star"></i>
                    <span>جودة عالمية</span>
                </div>
                <h1>نبني معاً غد سورية الأجمل</h1>
                <p>مستلزمات البناء التي تجمع بين الجودة العالمية والعصرية في التصميم، لنكون شريكك الأمثل في مشاريعك الإنشائية.</p>
                <div class="hero-actions">
                    <router-link to="/categories" class="btn btn-primary btn-lg">
                        <i class="fas fa-th-large"></i>
                        <span>استكشف الفئات</span>
                    </router-link>
                    <router-link to="/contact" class="btn btn-outline-white btn-lg">
                        <i class="fas fa-phone"></i>
                        <span>تواصل معنا</span>
                    </router-link>
                </div>
                <div class="hero-stats">
                    <div class="hero-stat">
                        <i class="fas fa-cubes"></i>
                        <span>{{ stats.products }}+ منتج</span>
                    </div>
                    <div class="hero-stat">
                        <i class="fas fa-folder"></i>
                        <span>{{ stats.categories }}+ فئة</span>
                    </div>
                    <div class="hero-stat">
                        <i class="fas fa-users"></i>
                        <span>{{ stats.clients }}+ عميل</span>
                    </div>
                </div>
            </div>
            <div class="floating-icons">
                <i class="fas fa-trowel icon-1"></i>
                <i class="fas fa-wrench icon-2"></i>
                <i class="fas fa-paint-roller icon-3"></i>
                <i class="fas fa-hard-hat icon-4"></i>
                <i class="fas fa-hammer icon-5"></i>
                <i class="fas fa-ruler icon-6"></i>
            </div>
        </section>

        <!-- Services Section -->
        <section class="services fade-up" id="services">
            <div class="container">
                <div class="section-header">
                    <div class="section-badge">
                        <i class="fas fa-concierge-bell"></i>
                        <span>خدماتنا</span>
                    </div>
                    <h2>حلول متكاملة لمشروعك</h2>
                    <p>نقدم عرضاً احترافياً يجمع بين فئات متخصصة، منتجات مميزة، ودعم فني سريع وموثوق.</p>
                </div>
                <div class="services-grid">
                    <div v-for="service in services" :key="service.title" class="service-card">
                        <div class="service-icon">
                            <i :class="service.icon"></i>
                        </div>
                        <h3>{{ service.title }}</h3>
                        <p>{{ service.description }}</p>
                        <span class="service-highlight">{{ service.highlight }}</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Categories Section -->
        <section class="categories fade-up" id="categories">
            <div class="container">
                <div class="section-header">
                    <div class="section-badge">
                        <i class="fas fa-layer-group"></i>
                        <span>فئاتنا</span>
                    </div>
                    <h2>فئات المنتجات الرئيسية</h2>
                    <p>تصفح الفئات الأكثر طلباً مع وصف سريع لكل فئة.</p>
                </div>
                <div v-if="categories.length > 0" class="categories-grid">
                    <div 
                        v-for="(category, index) in categories" 
                        :key="category.id" 
                        class="category-card"
                        :style="{ animationDelay: `${index * 0.1}s` }"
                        @click="goToCategory(category.slug)"
                    >
                        <div class="category-visual">
                            <div v-if="category.image" class="category-image">
                                <img :src="category.image" :alt="category.name_ar">
                            </div>
                            <div v-else class="category-icon">
                                <i :class="category.icon || 'fas fa-cube'"></i>
                            </div>
                            <div class="category-overlay">
                                <span class="view-icon">
                                    <i class="fas fa-arrow-left"></i>
                                </span>
                            </div>
                        </div>
                        <div class="category-content">
                            <h3>{{ category.name_ar }}</h3>
                            <p>{{ truncateText(category.description || 'حلول ومواد بناء عالية الجودة', 8) }}</p>
                            <div class="category-footer">
                                <span class="category-count">
                                    <i class="fas fa-box"></i>
                                    {{ category.product_count || 0 }} منتج
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="no-categories">
                    <p>لا توجد فئات متاحة حالياً</p>
                </div>
            </div>
        </section>

        <!-- Featured Products Section -->
        <section v-if="featuredProducts.length > 0" class="featured-products fade-up" id="featured-products">
            <div class="container">
                <div class="section-header">
                    <div class="section-badge">
                        <i class="fas fa-star"></i>
                        <span>مميزة</span>
                    </div>
                    <h2>منتجات مميزة</h2>
                    <p>أحدث وأفضل المنتجات في مجال البناء</p>
                </div>
                <div class="products-grid">
                    <div 
                        v-for="(product, index) in featuredProducts" 
                        :key="product.id" 
                        class="product-card"
                        :style="{ animationDelay: `${index * 0.1}s` }"
                    >
                        <div class="product-image">
                            <div class="product-badges">
                                <span v-if="product.is_featured" class="badge badge-featured">مميز</span>
                                <span v-if="!product.in_stock" class="badge badge-out">غير متوفر</span>
                                <span v-else class="badge badge-in">متوفر</span>
                            </div>
                            <img 
                                :src="product.image_main ? product.image_main : '/assets/images/products/default-product.jpg'" 
                                :alt="product.name_ar"
                            >
                            <div class="product-overlay">
                                <router-link :to="{ name: 'product.show', params: { slug: product.slug } }" class="view-btn">
                                    <i class="fas fa-eye"></i>
                                </router-link>
                            </div>
                        </div>
                        <div class="product-info">
                            <div class="product-name-container">
                                <h3 class="product-title">{{ product.name_ar }}</h3>
                                <span v-if="product.name_en" class="product-subtitle">{{ product.name_en }}</span>
                            </div>
                            <div class="product-category">{{ product.category?.name_ar || 'منتجات بناء' }}</div>
                            <div class="product-actions">
                                <a :href="`https://wa.me/963900000000?text=${encodeURIComponent('مرحباً، أنا مهتم بمنتج: ' + product.name_ar)}`" class="btn-whatsapp" target="_blank">
                                    <i class="fab fa-whatsapp"></i>
                                </a>
                                <router-link :to="{ name: 'inquiry', query: { product_id: product.id, product_name: product.name_ar } }" class="btn-inquiry">
                                    <i class="fas fa-question-circle"></i>
                                </router-link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section (when no featured products) -->
        <section v-else class="cta-section">
            <div class="container">
                <div class="cta-content">
                    <h2>اكتشف عالمنا المتميز</h2>
                    <p>نقدم أفضل مستلزمات البناء بجودة عالمية وأسعار تنافسية</p>
                    <div class="cta-actions">
                        <router-link to="/categories" class="btn btn-primary btn-lg">
                            <i class="fas fa-th-large"></i> استكشف الفئات
                        </router-link>
                        <router-link to="/contact" class="btn btn-outline-primary btn-lg">
                            <i class="fas fa-phone"></i> تواصل معنا
                        </router-link>
                    </div>
                </div>
            </div>
        </section>

        <!-- Stats Section -->
        <section class="stats-section">
            <div class="container">
                <div class="stats-grid">
                    <div class="stat-item">
                        <i class="fas fa-cubes"></i>
                        <div class="stat-number">{{ stats.products }}</div>
                        <div class="stat-label">منتج</div>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-folder"></i>
                        <div class="stat-number">{{ stats.categories }}</div>
                        <div class="stat-label">فئة</div>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-users"></i>
                        <div class="stat-number">{{ stats.clients }}</div>
                        <div class="stat-label">عميل</div>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-award"></i>
                        <div class="stat-number">{{ stats.years }}</div>
                        <div class="stat-label">سنة خبرة</div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useProductsStore } from '@/stores/products';
import { useCategoriesStore } from '@/stores/categories';

const router = useRouter();
const productsStore = useProductsStore();
const categoriesStore = useCategoriesStore();

const showPreloader = ref(true);
const loading = ref(false);
const featuredProducts = ref([]);
const categories = ref([]);
const stats = ref({
    products: 0,
    categories: 0,
    clients: 0,
    years: 10
});

const services = ref([
    {
        icon: 'fas fa-layer-group',
        title: 'فئات متخصصة',
        description: 'استعرض فئات المنتجات الرئيسية للعثور على الحلول المناسبة لكل مشروع بناء.',
        highlight: 'تنظيم واضح ومباشر'
    },
    {
        icon: 'fas fa-star',
        title: 'منتجات مميزة',
        description: 'اختر من بين أفضل المنتجات الموثوقة ذات المواصفات العالية والأسعار التنافسية.',
        highlight: 'جودة مدعومة'
    },
    {
        icon: 'fas fa-headset',
        title: 'دعم فني مباشر',
        description: 'فريق الدعم جاهز للرد على استفساراتك ومساعدتك في اختيار الأنسب لمشروعك.',
        highlight: 'دعم سريع واحترافي'
    },
    {
        icon: 'fas fa-shield-alt',
        title: 'ضمان ومصداقية',
        description: 'منتجات موثوقة مع متابعة بعد البيع ومواصفات دقيقة لكل منتج.',
        highlight: 'ثقة وراحة كاملة'
    }
]);

const siteName = 'أوان التقدم';
const siteLogo = '/assets/images/logo.png';

async function loadFeaturedProducts() {
    loading.value = true;
    try {
        await productsStore.fetchProducts({ featured: true, limit: 8 });
        featuredProducts.value = productsStore.products;
    } catch (error) {
        console.error('Failed to load featured products:', error);
    } finally {
        loading.value = false;
    }
}

async function loadCategories() {
    try {
        await categoriesStore.fetchCategories({ limit: 8, sort: 'name_ar', order: 'asc' });
        categories.value = categoriesStore.categories || [];
        stats.value.categories = categories.value.length;
    } catch (error) {
        console.error('Failed to load categories:', error);
        categories.value = [];
    }
}

async function loadStats() {
    try {
        await productsStore.fetchProducts({ limit: 1 });
        stats.value.products = productsStore.pagination.total || 0;
    } catch (error) {
        console.error('Failed to load stats:', error);
    }
}

function truncateText(text, words) {
    if (!text) return '';
    const wordsArray = text.split(' ');
    if (wordsArray.length <= words) return text;
    return wordsArray.slice(0, words).join(' ') + '...';
}

function goToCategory(slug) {
    router.push({ name: 'category.show', params: { slug } });
}

onMounted(() => {
    loadFeaturedProducts();
    loadCategories();
    loadStats();
    
    // Hide preloader after a short delay
    setTimeout(() => {
        showPreloader.value = false;
    }, 1500);
});
</script>

<style scoped>
/* Preloader Styles */
.preloader {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #1e3a8a, #3b82f6);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    transition: opacity 0.5s ease-out, visibility 0.5s ease-out;
}

.preloader.fade-out {
    opacity: 0;
    visibility: hidden;
}

.preloader-content {
    text-align: center;
    color: white;
}

.logo-pulse {
    position: relative;
    margin-bottom: 2rem;
    display: inline-block;
}

.preloader-logo {
    width: 180px;
    height: auto;
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

.preloader-text {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 2rem;
    opacity: 0.9;
}

.spinner-ring {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 220px;
    height: 220px;
    margin-top: -110px;
    margin-left: -110px;
    border: 3px solid rgba(255, 255, 255, 0.2);
    border-top: 3px solid white;
    border-radius: 50%;
    animation: spin 2s linear infinite;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
        transform: scale(1);
    }
    50% {
        opacity: 0.8;
        transform: scale(1.1);
    }
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Hero Section */
.hero {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
    padding: 6rem 2rem;
}

.hero-background {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 1;
}

.hero-background::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: url('/assets/images/hero-bg.jpg') center/cover no-repeat;
    z-index: 1;
    animation: backgroundZoom 20s ease-in-out infinite alternate;
}

@keyframes backgroundZoom {
    0% {
        transform: scale(1);
    }
    100% {
        transform: scale(1.1);
    }
}

.hero-gradient {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.95) 0%, rgba(30, 58, 138, 0.9) 40%, rgba(59, 130, 246, 0.85) 100%);
    z-index: 2;
}

.hero-pattern {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-image: 
        radial-gradient(circle at 20% 50%, rgba(251, 191, 36, 0.08) 0%, transparent 40%),
        radial-gradient(circle at 80% 80%, rgba(251, 191, 36, 0.08) 0%, transparent 40%),
        radial-gradient(circle at 50% 20%, rgba(255, 255, 255, 0.05) 0%, transparent 30%);
    background-size: 100% 100%;
    z-index: 3;
    animation: patternMove 15s ease-in-out infinite;
}

@keyframes patternMove {
    0%, 100% {
        opacity: 0.6;
    }
    50% {
        opacity: 1;
    }
}

.hero-content {
    max-width: 1000px;
    margin: 0 auto;
    position: relative;
    z-index: 4;
    text-align: center;
}

.hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    padding: 0.75rem 2rem;
    border-radius: 50px;
    margin-bottom: 2.5rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
    animation: fadeInDown 1s ease-out;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.hero-badge i {
    color: #fbbf24;
    font-size: 1rem;
    animation: pulse 2s ease-in-out infinite;
}

.hero-badge span {
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
    letter-spacing: 0.02em;
}

.hero h1 {
    font-size: 4rem;
    font-weight: 900;
    margin-bottom: 1.5rem;
    line-height: 1.1;
    color: white;
    animation: fadeInUp 1s ease-out 0.2s backwards;
    letter-spacing: -0.03em;
    text-shadow: 0 4px 30px rgba(0, 0, 0, 0.3);
}

.hero p {
    font-size: 1.35rem;
    opacity: 0.95;
    line-height: 1.9;
    margin-bottom: 3rem;
    color: white;
    animation: fadeInUp 1s ease-out 0.4s backwards;
    max-width: 700px;
    margin-left: auto;
    margin-right: auto;
    font-weight: 400;
}

.hero-actions {
    display: flex;
    gap: 1.25rem;
    justify-content: center;
    flex-wrap: wrap;
    margin-bottom: 4rem;
    animation: fadeInUp 1s ease-out 0.6s backwards;
}

.hero-stats {
    display: flex;
    gap: 2.5rem;
    justify-content: center;
    flex-wrap: wrap;
    animation: fadeInUp 1s ease-out 0.8s backwards;
}

.hero-stat {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(15px);
    -webkit-backdrop-filter: blur(15px);
    padding: 1.25rem 2rem;
    border-radius: 16px;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid rgba(255, 255, 255, 0.15);
}

.hero-stat:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-8px) scale(1.05);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
    border-color: rgba(251, 191, 36, 0.3);
}

.hero-stat i {
    font-size: 1.75rem;
    color: #fbbf24;
    filter: drop-shadow(0 2px 8px rgba(251, 191, 36, 0.4));
}

.hero-stat span {
    color: white;
    font-weight: 700;
    font-size: 1.1rem;
    letter-spacing: 0.01em;
}

.floating-icons {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 1;
}

.floating-icons i {
    position: absolute;
    font-size: 3rem;
    opacity: 0.15;
    animation: float 12s ease-in-out infinite;
    filter: drop-shadow(0 4px 12px rgba(251, 191, 36, 0.3));
}

.floating-icons .icon-1 {
    top: 15%;
    left: 10%;
    animation-delay: 0s;
    color: #fbbf24;
}

.floating-icons .icon-2 {
    top: 25%;
    right: 15%;
    animation-delay: 2.5s;
    color: #fbbf24;
}

.floating-icons .icon-3 {
    bottom: 30%;
    left: 15%;
    animation-delay: 5s;
    color: #fbbf24;
}

.floating-icons .icon-4 {
    bottom: 20%;
    right: 10%;
    animation-delay: 7.5s;
    color: #fbbf24;
}

.floating-icons .icon-5 {
    top: 50%;
    left: 5%;
    animation-delay: 10s;
    color: #fbbf24;
}

.floating-icons .icon-6 {
    top: 60%;
    right: 5%;
    animation-delay: 12.5s;
    color: #fbbf24;
}

@keyframes float {
    0%, 100% {
        transform: translateY(0) rotate(0deg);
    }
    25% {
        transform: translateY(-40px) rotate(5deg);
    }
    50% {
        transform: translateY(-20px) rotate(-5deg);
    }
    75% {
        transform: translateY(-50px) rotate(3deg);
    }
}

@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-40px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(40px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
        opacity: 1;
    }
    50% {
        transform: scale(1.2);
        opacity: 0.8;
    }
}

.services {
    background: #f8fbff;
    padding: 6rem 0;
    position: relative;
}

.services-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1.75rem;
    align-items: stretch;
}

.service-card {
    background: white;
    border-radius: 28px;
    padding: 2rem;
    box-shadow: 0 15px 40px rgba(15, 23, 42, 0.08);
    border: 1px solid rgba(59, 130, 246, 0.08);
    transition: transform 0.35s ease, box-shadow 0.35s ease;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    opacity: 0;
    animation: fadeUp 0.8s ease-out forwards;
}

.service-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 28px 60px rgba(15, 23, 42, 0.12);
}

.service-icon {
    width: 70px;
    height: 70px;
    display: grid;
    place-items: center;
    border-radius: 22px;
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(59, 130, 246, 0.04));
    color: #1d4ed8;
    font-size: 1.75rem;
}

.service-card h3 {
    font-size: 1.4rem;
    color: #1e3a8a;
    margin: 0;
}

.service-card p {
    color: #4b5563;
    line-height: 1.8;
    font-size: 1rem;
    flex: 1;
}

.service-highlight {
    display: inline-block;
    color: #2563eb;
    font-weight: 700;
    margin-top: auto;
}

/* Section Styles */
section {
    padding: 6rem 0;
}

.container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 2rem;
}

.section-header {
    text-align: center;
    margin-bottom: 3rem;
}

.section-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: #f0f9ff;
    color: #3b82f6;
    padding: 0.5rem 1.5rem;
    border-radius: 50px;
    margin-bottom: 1rem;
    font-weight: 600;
    font-size: 0.875rem;
}

.section-header h2 {
    font-size: 2.75rem;
    font-weight: 800;
    margin-bottom: 0.75rem;
    color: #1e3a8a;
    letter-spacing: -0.02em;
}

.section-header p {
    color: #6b7280;
    font-size: 1.125rem;
    max-width: 600px;
    margin: 0 auto;
    line-height: 1.7;
}

/* Categories Section */
.categories {
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    padding: 7rem 0;
    position: relative;
}

.categories::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle at 10% 20%, rgba(59, 130, 246, 0.03) 0%, transparent 50%),
                radial-gradient(circle at 90% 80%, rgba(59, 130, 246, 0.03) 0%, transparent 50%);
    pointer-events: none;
}

.categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 2.5rem;
    position: relative;
    z-index: 1;
}

.category-card {
    background: white;
    border-radius: 24px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
    opacity: 0;
    animation: fadeUp 0.8s ease-out forwards;
    border: 1px solid rgba(0, 0, 0, 0.04);
    position: relative;
}

.category-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(30, 58, 138, 0.05) 100%);
    opacity: 0;
    transition: opacity 0.5s;
    z-index: 0;
}

.category-card:hover {
    transform: translateY(-12px) scale(1.02);
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
    border-color: rgba(59, 130, 246, 0.2);
}

.category-card:hover::before {
    opacity: 1;
}

.category-visual {
    position: relative;
    height: 220px;
    overflow: hidden;
    z-index: 1;
}

.category-image {
    width: 100%;
    height: 100%;
}

.category-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

.category-card:hover .category-image img {
    transform: scale(1.15);
}

.category-icon {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #3b82f6 0%, #1e3a8a 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 4.5rem;
    position: relative;
}

.category-icon::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle at 30% 30%, rgba(255, 255, 255, 0.2) 0%, transparent 60%);
}

.category-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(to top, rgba(0, 0, 0, 0.7) 0%, transparent 60%);
    display: flex;
    align-items: flex-end;
    justify-content: center;
    padding-bottom: 2rem;
    opacity: 0;
    transition: opacity 0.5s;
    z-index: 2;
}

.category-card:hover .category-overlay {
    opacity: 1;
}

.view-icon {
    width: 60px;
    height: 60px;
    background: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #3b82f6;
    font-size: 1.5rem;
    transform: translateY(30px) scale(0.8);
    transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
}

.category-card:hover .view-icon {
    transform: translateY(0) scale(1);
}

.category-content {
    padding: 2rem;
    position: relative;
    z-index: 1;
}

.category-content h3 {
    font-size: 1.375rem;
    font-weight: 800;
    margin-bottom: 0.75rem;
    color: #1a1a1a;
    letter-spacing: -0.01em;
    transition: color 0.3s;
}

.category-card:hover .category-content h3 {
    color: #3b82f6;
}

.category-content p {
    color: #6b7280;
    font-size: 0.9375rem;
    line-height: 1.7;
    margin-bottom: 1.25rem;
    font-weight: 400;
}

.category-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.category-count {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    color: #3b82f6;
    padding: 0.625rem 1.25rem;
    border-radius: 25px;
    font-size: 0.875rem;
    font-weight: 700;
    transition: all 0.3s;
    border: 1px solid rgba(59, 130, 246, 0.1);
}

.category-card:hover .category-count {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    transform: scale(1.05);
}

.no-categories {
    text-align: center;
    padding: 3rem;
    color: #6b7280;
    font-size: 1.125rem;
}

/* Products Section */
.featured-products {
    background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
    padding: 7rem 0;
    position: relative;
}

.featured-products::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle at 90% 10%, rgba(59, 130, 246, 0.03) 0%, transparent 50%),
                radial-gradient(circle at 10% 90%, rgba(59, 130, 246, 0.03) 0%, transparent 50%);
    pointer-events: none;
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 2.5rem;
    position: relative;
    z-index: 1;
}

.product-card {
    background: white;
    border-radius: 24px;
    overflow: hidden;
    transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
    opacity: 0;
    animation: fadeUp 0.8s ease-out forwards;
    border: 1px solid rgba(0, 0, 0, 0.04);
    position: relative;
}

.product-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(30, 58, 138, 0.05) 100%);
    opacity: 0;
    transition: opacity 0.5s;
    z-index: 0;
}

.product-card:hover {
    transform: translateY(-12px) scale(1.02);
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
    border-color: rgba(59, 130, 246, 0.2);
}

.product-card:hover::before {
    opacity: 1;
}

.product-image {
    position: relative;
    height: 280px;
    overflow: hidden;
    z-index: 1;
}

.product-badges {
    position: absolute;
    top: 1.25rem;
    right: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    z-index: 5;
}

.badge {
    padding: 0.375rem 1rem;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    text-transform: uppercase;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.badge-in {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.badge-out {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
}

.badge-featured {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

.product-card:hover .product-image img {
    transform: scale(1.15);
}

.product-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(to top, rgba(0, 0, 0, 0.7) 0%, transparent 60%);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.5s;
    z-index: 2;
}

.product-card:hover .product-overlay {
    opacity: 1;
}

.view-btn {
    width: 60px;
    height: 60px;
    background: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #1e3a8a;
    text-decoration: none;
    transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
    transform: translateY(30px) scale(0.8);
}

.product-card:hover .view-btn {
    transform: translateY(0) scale(1);
}

.view-btn:hover {
    background: #3b82f6;
    color: white;
    transform: scale(1.1) !important;
}

.product-info {
    padding: 2rem;
    position: relative;
    z-index: 1;
}

.product-name-container {
    margin-bottom: 1.25rem;
}

.product-title {
    font-size: 1.25rem;
    font-weight: 800;
    margin-bottom: 0.375rem;
    color: #1a1a1a;
    letter-spacing: -0.01em;
    transition: color 0.3s;
}

.product-card:hover .product-title {
    color: #3b82f6;
}

.product-subtitle {
    font-size: 0.875rem;
    color: #6b7280;
    font-weight: 500;
}

.product-category {
    color: #6b7280;
    font-size: 0.875rem;
    margin-bottom: 1.25rem;
    font-weight: 500;
}

.product-actions {
    display: flex;
    gap: 0.75rem;
}

.btn-whatsapp,
.btn-inquiry {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.875rem 1rem;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 700;
    font-size: 0.875rem;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.btn-whatsapp {
    background: linear-gradient(135deg, #25d366 0%, #128c7e 100%);
    color: white;
}

.btn-whatsapp:hover {
    background: linear-gradient(135deg, #128c7e 0%, #075e54 100%);
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(37, 211, 102, 0.4);
}

.btn-inquiry {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
}

.btn-inquiry:hover {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4);
}

/* CTA Section */
.cta-section {
    background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 50%, #2563eb 100%);
    padding: 8rem 0;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.cta-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: url('/assets/images/hero-bg.jpg') center/cover;
    opacity: 0.08;
    animation: backgroundPan 30s ease-in-out infinite alternate;
}

@keyframes backgroundPan {
    0% {
        transform: scale(1);
    }
    100% {
        transform: scale(1.1);
    }
}

.cta-section::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle at 20% 50%, rgba(251, 191, 36, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 50%, rgba(251, 191, 36, 0.1) 0%, transparent 50%);
    animation: pulseGlow 4s ease-in-out infinite;
}

@keyframes pulseGlow {
    0%, 100% {
        opacity: 0.5;
    }
    50% {
        opacity: 1;
    }
}

.cta-content {
    position: relative;
    z-index: 2;
}

.cta-content h2 {
    font-size: 3.5rem;
    font-weight: 900;
    margin-bottom: 1.5rem;
    color: white;
    letter-spacing: -0.03em;
    text-shadow: 0 4px 30px rgba(0, 0, 0, 0.3);
}

.cta-content p {
    font-size: 1.375rem;
    margin-bottom: 3rem;
    color: rgba(255, 255, 255, 0.95);
    max-width: 650px;
    margin-left: auto;
    margin-right: auto;
    line-height: 1.8;
    font-weight: 400;
}

.cta-actions {
    display: flex;
    gap: 1.5rem;
    justify-content: center;
    flex-wrap: wrap;
}

/* Stats Section */
.stats-section {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    padding: 8rem 0;
    position: relative;
}

.stats-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle at 50% 50%, rgba(59, 130, 246, 0.05) 0%, transparent 60%);
    pointer-events: none;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2.5rem;
    position: relative;
    z-index: 1;
}

.stat-item {
    text-align: center;
    padding: 3rem 2rem;
    background: white;
    border-radius: 28px;
    transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
    border: 1px solid rgba(0, 0, 0, 0.04);
    position: relative;
    overflow: hidden;
}

.stat-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(30, 58, 138, 0.05) 100%);
    opacity: 0;
    transition: opacity 0.5s;
}

.stat-item:hover {
    transform: translateY(-12px) scale(1.03);
    box-shadow: 0 30px 60px rgba(0, 0, 0, 0.12);
    border-color: rgba(59, 130, 246, 0.2);
}

.stat-item:hover::before {
    opacity: 1;
}

.stat-item i {
    font-size: 3.5rem;
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 1.5rem;
    position: relative;
    z-index: 1;
    filter: drop-shadow(0 4px 12px rgba(59, 130, 246, 0.2));
    transition: transform 0.4s;
}

.stat-item:hover i {
    transform: scale(1.1) rotate(5deg);
}

.stat-number {
    font-size: 3.5rem;
    font-weight: 900;
    background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 0.75rem;
    letter-spacing: -0.03em;
    position: relative;
    z-index: 1;
    transition: transform 0.4s;
}

.stat-item:hover .stat-number {
    transform: scale(1.05);
}

.stat-label {
    color: #6b7280;
    font-size: 1.125rem;
    font-weight: 600;
    position: relative;
    z-index: 1;
    letter-spacing: 0.02em;
    text-transform: uppercase;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem 2rem;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
}

.btn-primary {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    border: none;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4);
}

.btn-outline-primary {
    border: 2px solid #3b82f6;
    color: #3b82f6;
    background: transparent;
}

.btn-outline-primary:hover {
    background: #3b82f6;
    color: white;
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
}

.btn-outline-white {
    border: 2px solid white;
    color: white;
    background: transparent;
}

.btn-outline-white:hover {
    background: white;
    color: #1e3a8a;
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(255, 255, 255, 0.3);
}

.btn-lg {
    padding: 1rem 3rem;
    font-size: 1.125rem;
}

/* Fade Up Animation */
.fade-up {
    opacity: 0;
    transform: translateY(30px);
    animation: fadeUp 0.8s ease-out forwards;
}

@keyframes fadeUp {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Stagger Animation */
.stagger-in {
    opacity: 0;
    animation: staggerIn 0.6s ease-out forwards;
}

@keyframes staggerIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Scale In Animation */
.scale-in {
    opacity: 0;
    transform: scale(0.9);
    animation: scaleIn 0.6s ease-out forwards;
}

@keyframes scaleIn {
    to {
        opacity: 1;
        transform: scale(1);
    }
}

/* Smooth Scroll */
html {
    scroll-behavior: smooth;
}

/* Scroll Reveal Animation */
.scroll-reveal {
    opacity: 0;
    transform: translateY(40px);
    transition: all 0.8s cubic-bezier(0.4, 0, 0.2, 1);
}

.scroll-reveal.visible {
    opacity: 1;
    transform: translateY(0);
}

@media (max-width: 1024px) {
    .hero h1 {
        font-size: 3rem;
    }

    .hero p {
        font-size: 1.25rem;
    }

    .cta-content h2 {
        font-size: 2.75rem;
    }
}

@media (max-width: 768px) {
    .hero h1 {
        font-size: 2.5rem;
    }

    .hero p {
        font-size: 1.125rem;
    }

    .section-header h2 {
        font-size: 2rem;
    }

    .section-header p {
        font-size: 1rem;
    }

    .categories-grid,
    .products-grid {
        grid-template-columns: 1fr;
        gap: 2rem;
    }

    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 1.5rem;
    }

    .cta-actions {
        flex-direction: column;
    }

    .hero-stats {
        gap: 1.5rem;
    }

    .hero-stat {
        padding: 1rem 1.5rem;
    }

    .cta-content h2 {
        font-size: 2rem;
    }

    .cta-content p {
        font-size: 1.125rem;
    }
}

@media (max-width: 480px) {
    .hero h1 {
        font-size: 2rem;
    }

    .hero p {
        font-size: 1rem;
    }

    .section-header h2 {
        font-size: 1.75rem;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }

    .hero-stats {
        flex-direction: column;
        gap: 1rem;
    }

    .hero-stat {
        width: 100%;
        justify-content: center;
    }
}
</style>
