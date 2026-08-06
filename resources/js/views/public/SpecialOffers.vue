<template>
    <div class="special-offers-page">
        <section class="page-header">
            <div class="container">
                <h1>{{ t('special_offers') || 'العروض المميزة' }}</h1>
                <div class="breadcrumb">
                    <router-link to="/">{{ t('nav_home') || 'الرئيسية' }}</router-link>
                    <span class="sep">›</span>
                    <span>{{ t('special_offers') || 'العروض المميزة' }}</span>
                </div>
            </div>
        </section>

        <section class="offers-section">
            <div class="container">
                <div class="section-header">
                    <h2>{{ t('special_offers_title') || 'العروض المميزة' }}</h2>
                    <p>{{ t('special_offers_subtitle') || 'اكتشف أقوى العروض والخصومات الحصرية لفترة محدودة' }}</p>
                </div>

                <div class="filter-tabs">
                    <button
                        v-for="tab in filterTabs"
                        :key="tab.key"
                        :class="['filter-tab', { active: activeFilter === tab.key }]"
                        @click="setFilter(tab.key)"
                    >
                        <i :class="tab.icon"></i>
                        {{ tab.label }}
                    </button>
                </div>

                <div v-if="loading" class="offers-loading">
                    <div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i></div>
                    <p>{{ t('loading') || 'جاري التحميل...' }}</p>
                </div>

                <template v-else>
                    <div v-if="filteredOffers.length > 0" class="offers-grid">
                        <div v-for="offer in filteredOffers" :key="offer.id" class="offer-card fade-up">
                            <div class="offer-image" v-if="offer.image">
                                <img :src="offer.image" :alt="$p(offer, 'title')" loading="lazy">
                                <div class="offer-badge" v-if="offer.discount_percentage">
                                    <span class="badge-value">-{{ offer.discount_percentage }}%</span>
                                    <span class="badge-label">{{ t('discount') || 'خصم' }}</span>
                                </div>
                                <div class="offer-countdown" v-if="offer.end_date">
                                    <i class="fas fa-clock"></i>
                                    <span>{{ getCountdown(offer.end_date) }}</span>
                                </div>
                            </div>
                            <div class="offer-image offer-image-placeholder" v-else>
                                <i class="fas fa-tag"></i>
                            </div>
                            <div class="offer-info">
                                <h3 class="offer-title">{{ $p(offer, 'title') }}</h3>
                                <p class="offer-desc">{{ truncateText($p(offer, 'description'), 120) }}</p>
                                <div class="offer-meta">
                                    <span v-if="offer.start_date" class="offer-date">
                                        <i class="fas fa-calendar-alt"></i>
                                        {{ formatDate(offer.start_date) }}
                                    </span>
                                    <span v-if="offer.end_date" class="offer-date">
                                        <i class="fas fa-hourglass-end"></i>
                                        {{ formatDate(offer.end_date) }}
                                    </span>
                                </div>
                                <div class="offer-actions">
                                    <router-link v-if="offer.product" :to="{ name: 'product.detail', params: { slug: offer.product.slug } }" class="btn-offer">
                                        <i class="fas fa-eye"></i> {{ t('view_product') || 'عرض المنتج' }}
                                    </router-link>
                                    <a v-else-if="offer.link" :href="offer.link" class="btn-offer" target="_blank">
                                        <i class="fas fa-external-link-alt"></i> {{ t('discover_more') || 'اكتشف المزيد' }}
                                    </a>
                                    <router-link v-else to="/categories" class="btn-offer">
                                        <i class="fas fa-th-large"></i> {{ t('browse_categories') || 'تصفح الفئات' }}
                                    </router-link>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="no-offers">
                        <i class="fas fa-tag"></i>
                        <h3>{{ t('no_offers') || 'لا توجد عروض متاحة حالياً' }}</h3>
                        <p>{{ t('no_offers_desc') || 'تحقق لاحقاً لاستكشاف أحدث العروض والخصومات' }}</p>
                        <router-link to="/" class="btn-home">
                            <i class="fas fa-home"></i> {{ t('back_to_home') || 'العودة للرئيسية' }}
                        </router-link>
                    </div>
                </template>
            </div>
        </section>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useSettingsStore } from '@/stores/settings';
import axios from 'axios';

const { t, locale } = useI18n();
const route = useRoute();
const router = useRouter();
const settingsStore = useSettingsStore();

const offers = ref([]);
const loading = ref(true);
const activeFilter = ref('all');
let countdownTimer = null;

const filterTabs = computed(() => [
    { key: 'all', label: t('all_offers') || 'جميع العروض', icon: 'fas fa-tag' },
    { key: 'flash', label: t('flash_sales') || 'التخفيضات السريعة', icon: 'fas fa-bolt' },
    { key: 'clearance', label: t('clearance') || 'التصفية', icon: 'fas fa-percent' },
    { key: 'bundle', label: t('bundle_offers') || 'عروض الحزم', icon: 'fas fa-boxes' },
]);

const filteredOffers = computed(() => {
    if (activeFilter.value === 'all') return offers.value;
    return offers.value.filter(o => {
        const link = (o.link || '').toLowerCase();
        const title = ($p(o, 'title') || '').toLowerCase();
        const desc = ($p(o, 'description') || '').toLowerCase();
        return link.includes(activeFilter.value) || title.includes(activeFilter.value) || desc.includes(activeFilter.value);
    });
});

const $p = (obj, field) => {
    if (!obj) return '';
    if (locale.value === 'en') return obj[`${field}_en`] || obj[`${field}_ar`] || obj[field] || '';
    return obj[`${field}_ar`] || obj[field] || '';
};

function truncateText(text, max) {
    if (!text) return '';
    return text.length > max ? text.substring(0, max) + '...' : text;
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleDateString(locale.value === 'ar' ? 'ar-SA' : 'en-US', {
        year: 'numeric', month: 'short', day: 'numeric'
    });
}

function getCountdown(endDate) {
    if (!endDate) return '';
    const now = new Date();
    const end = new Date(endDate);
    const diff = end - now;
    if (diff <= 0) return locale.value === 'ar' ? 'انتهى' : 'Ended';
    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    if (days > 0) return `${days}d ${hours}h`;
    const mins = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    return `${hours}h ${mins}m`;
}

function setFilter(key) {
    activeFilter.value = key;
    const query = key === 'all' ? {} : { type: key };
    router.replace({ query });
}

onMounted(async () => {
    if (route.query.type) {
        activeFilter.value = route.query.type;
    }
    try {
        const res = await axios.get('/api/v1/special-offers');
        if (res.data?.success) {
            offers.value = res.data.data || [];
        }
    } catch (e) {
        console.error('Failed to load special offers', e);
    } finally {
        loading.value = false;
    }
    countdownTimer = setInterval(() => {}, 60000);
});

onUnmounted(() => {
    if (countdownTimer) clearInterval(countdownTimer);
});
</script>

<style scoped>
.special-offers-page {
    min-height: 60vh;
}

.page-header {
    background: linear-gradient(135deg, var(--mobile-primary, #1e3a5f), var(--mobile-primary-dark, #0f2440));
    padding: 60px 0 50px;
    text-align: center;
    position: relative;
    overflow: hidden;
}
.page-header h1 {
    color: #fff;
    font-size: 2rem;
    margin: 0 0 10px;
}
.breadcrumb {
    color: rgba(255,255,255,0.7);
    font-size: 0.9rem;
}
.breadcrumb a {
    color: rgba(255,255,255,0.9);
    text-decoration: none;
}
.breadcrumb a:hover {
    color: #fff;
}
.breadcrumb .sep {
    margin: 0 8px;
}

.offers-section {
    padding: 50px 0;
}
.section-header {
    text-align: center;
    margin-bottom: 30px;
}
.section-header h2 {
    font-size: 1.6rem;
    margin: 0 0 8px;
}
.section-header p {
    color: #64748b;
    margin: 0;
}

.filter-tabs {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-bottom: 40px;
    flex-wrap: wrap;
}
.filter-tab {
    padding: 10px 22px;
    border: 2px solid #e2e8f0;
    border-radius: 50px;
    background: #fff;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 500;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
    color: #475569;
}
.filter-tab:hover {
    border-color: var(--mobile-primary, #1e3a5f);
    color: var(--mobile-primary, #1e3a5f);
}
.filter-tab.active {
    background: var(--mobile-primary, #1e3a5f);
    color: #fff;
    border-color: var(--mobile-primary, #1e3a5f);
}

.offers-loading {
    text-align: center;
    padding: 80px 0;
    color: #94a3b8;
}
.loading-spinner {
    font-size: 2.5rem;
    margin-bottom: 16px;
}

.offers-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 24px;
}

.offer-card {
    border-radius: 16px;
    overflow: hidden;
    background: #fff;
    border: 1px solid #e2e8f0;
    transition: all 0.3s;
    display: flex;
    flex-direction: column;
}
.offer-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.08);
}

.offer-image {
    position: relative;
    height: 200px;
    overflow: hidden;
    background: #f1f5f9;
}
.offer-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s;
}
.offer-card:hover .offer-image img {
    transform: scale(1.05);
}
.offer-image-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    color: #cbd5e1;
}

.offer-badge {
    position: absolute;
    top: 12px;
    left: 12px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: #fff;
    border-radius: 12px;
    padding: 8px 14px;
    text-align: center;
    line-height: 1.2;
    box-shadow: 0 4px 12px rgba(239,68,68,0.4);
}
.badge-value {
    display: block;
    font-size: 1.1rem;
    font-weight: 800;
}
.badge-label {
    display: block;
    font-size: 0.65rem;
    opacity: 0.9;
}

.offer-countdown {
    position: absolute;
    bottom: 12px;
    right: 12px;
    background: rgba(0,0,0,0.7);
    color: #fff;
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 0.8rem;
    display: flex;
    align-items: center;
    gap: 6px;
    backdrop-filter: blur(4px);
}

.offer-info {
    padding: 20px;
    flex: 1;
    display: flex;
    flex-direction: column;
}
.offer-title {
    margin: 0 0 8px;
    font-size: 1.1rem;
}
.offer-desc {
    color: #64748b;
    font-size: 0.88rem;
    line-height: 1.6;
    margin: 0 0 12px;
    flex: 1;
}
.offer-meta {
    display: flex;
    gap: 16px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}
.offer-date {
    font-size: 0.78rem;
    color: #94a3b8;
    display: flex;
    align-items: center;
    gap: 4px;
}
.offer-actions {
    display: flex;
    gap: 10px;
}
.btn-offer {
    padding: 10px 20px;
    border-radius: 10px;
    background: var(--mobile-primary, #1e3a5f);
    color: #fff;
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
}
.btn-offer:hover {
    background: var(--mobile-primary-dark, #0f2440);
    transform: translateY(-2px);
}

.no-offers {
    text-align: center;
    padding: 80px 20px;
    color: #94a3b8;
}
.no-offers i {
    font-size: 3.5rem;
    margin-bottom: 16px;
    color: #cbd5e1;
}
.no-offers h3 {
    color: #475569;
    margin: 0 0 8px;
}
.no-offers p {
    margin: 0 0 24px;
}
.btn-home {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 28px;
    background: var(--mobile-primary, #1e3a5f);
    color: #fff;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 500;
}

@media (max-width: 768px) {
    .page-header { padding: 40px 0 30px; }
    .page-header h1 { font-size: 1.4rem; }
    .offers-grid { grid-template-columns: 1fr; }
    .filter-tabs { gap: 6px; }
    .filter-tab { padding: 8px 16px; font-size: 0.82rem; }
}

[data-theme="dark"] .offer-card {
    background: #1e293b;
    border-color: #334155;
}
[data-theme="dark"] .offer-desc { color: #94a3b8; }
[data-theme="dark"] .offer-date { color: #64748b; }
[data-theme="dark"] .filter-tab {
    background: #1e293b;
    border-color: #334155;
    color: #cbd5e1;
}
[data-theme="dark"] .filter-tab:hover {
    border-color: var(--mobile-primary, #3b82f6);
    color: var(--mobile-primary, #3b82f6);
}
[data-theme="dark"] .offer-image { background: #0f172a; }
[data-theme="dark"] .no-offers i { color: #334155; }
[data-theme="dark"] .no-offers h3 { color: #cbd5e1; }
[data-theme="dark"] .section-header p { color: #94a3b8; }
</style>
