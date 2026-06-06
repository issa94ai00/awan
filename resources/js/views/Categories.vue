<template>
    <div class="categories-page">
        <!-- Page Header -->
        <section class="page-header">
            <div class="container">
                <h1>الفئات</h1>
                <div class="breadcrumb">
                    <router-link to="/">الرئيسية</router-link>
                    <span>/</span>
                    <span>الفئات</span>
                </div>
            </div>
        </section>

        <!-- Categories Section -->
        <section class="categories fade-up">
            <div class="container">
                <div class="section-header">
                    <h2>جميع الفئات</h2>
                    <p>تصفح جميع فئات المنتجات المتاحة</p>
                </div>

                <div v-if="loading" class="loading-state">
                    <div class="spinner"></div>
                    <p>جاري تحميل الفئات...</p>
                </div>

                <div class="categories-grid" v-else-if="categories.length > 0">
                    <div 
                        v-for="(category, index) in categories" 
                        :key="category.id" 
                        class="category-card"
                        :style="{ animationDelay: `${index * 0.1}s` }"
                        @click="goToCategory(category.slug)"
                    >
                        <div class="category-visual">
                            <div v-if="category.image" class="category-image">
                                <img 
                                    :src="category.image || ''" 
                                    :alt="category.name_ar"
                                    loading="lazy"
                                    @error="handleImageError"
                                >
                            </div>
                            <div v-else class="category-icon">
                                <i :class="`fas ${category.icon || 'fa-cube'}`"></i>
                            </div>
                            <div class="category-overlay">
                                <span class="view-btn">
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
                <div v-else class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <h3>لا توجد فئات متاحة حالياً</h3>
                    <p>يرجى العودة لاحقاً أو التواصل معنا للمزيد من المعلومات</p>
                    <router-link to="/contact" class="btn btn-primary">
                        <i class="fas fa-phone"></i>
                        تواصل معنا
                    </router-link>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useCategoriesStore } from '@/stores/categories';

const router = useRouter();
const categoriesStore = useCategoriesStore();

const loading = ref(false);
const categories = ref([]);

async function loadCategories() {
    loading.value = true;
    try {
        await categoriesStore.fetchCategories();
        categories.value = categoriesStore.categories;
    } catch (error) {
        console.error('Failed to load categories:', error);
    } finally {
        loading.value = false;
    }
}

function goToCategory(slug) {
    router.push({ name: 'category.show', params: { slug } });
}

function truncateText(text, words) {
    if (!text) return '';
    const wordsArray = text.split(' ');
    if (wordsArray.length <= words) return text;
    return wordsArray.slice(0, words).join(' ') + '...';
}

function handleImageError(event) {
    const parent = event.target.parentElement;
    const icon = event.target.closest('.category-card').querySelector('.category-icon')?.querySelector('i')?.className || 'fa-cube';
    parent.innerHTML = `<div class="category-icon"><i class="fas ${icon}"></i></div>`;
}

onMounted(() => loadCategories());
</script>

<style scoped>
.categories-page {
    min-height: 100vh;
}

/* Page Header */
.page-header {
    padding-top: 120px;
    background: linear-gradient(135deg, #1e3a8a, #3b82f6);
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
    background: url('/assets/images/pattern-bg.png') center/cover;
    opacity: 0.1;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 2rem;
    position: relative;
    z-index: 1;
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

/* Categories Section */
.categories {
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

.categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 2rem;
}

.category-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
    opacity: 0;
    animation: fadeUp 0.6s ease-out forwards;
}

.category-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

.category-visual {
    position: relative;
    height: 200px;
    overflow: hidden;
}

.category-image {
    width: 100%;
    height: 100%;
}

.category-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.4s;
}

.category-card:hover .category-image img {
    transform: scale(1.1);
}

.category-icon {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #3b82f6, #1e3a8a);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 3rem;
}

.category-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(to top, rgba(0, 0, 0, 0.6), transparent);
    display: flex;
    align-items: flex-end;
    justify-content: center;
    padding-bottom: 1.5rem;
    opacity: 0;
    transition: opacity 0.3s;
}

.category-card:hover .category-overlay {
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
    color: #3b82f6;
    font-size: 1.25rem;
    transform: translateY(20px);
    transition: all 0.3s;
}

.category-card:hover .view-btn {
    transform: translateY(0);
}

.category-content {
    padding: 1.5rem;
}

.category-content h3 {
    font-size: 1.25rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: #1a1a1a;
}

.category-content p {
    color: #6b7280;
    font-size: 0.875rem;
    line-height: 1.6;
    margin-bottom: 1rem;
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
    background: #f0f9ff;
    color: #3b82f6;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 600;
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

/* Empty State */
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

.empty-state h3 {
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #1a1a1a;
}

.empty-state p {
    font-size: 1rem;
    margin-bottom: 2rem;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 2rem;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
}

.btn-primary {
    background: #3b82f6;
    color: white;
}

.btn-primary:hover {
    background: #2563eb;
    transform: translateY(-2px);
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

@media (max-width: 768px) {
    .page-header h1 {
        font-size: 1.75rem;
    }

    .section-header h2 {
        font-size: 1.5rem;
    }

    .categories-grid {
        grid-template-columns: 1fr;
    }
}
</style>
