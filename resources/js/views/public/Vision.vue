<template>
    <div class="vision-page-view">
        <!-- Page Header -->
        <section class="page-header">
            <div class="container">
                <h1>{{ $p(settings.value, 'vision_title') || t('nav_vision') || 'الهوية والرؤية' }}</h1>
                <div class="breadcrumb">
                    <router-link to="/">{{ t('nav_home') || 'الرئيسية' }}</router-link>
                    <span class="sep">›</span>
                    <span>{{ $p(settings.value, 'vision_title') || t('nav_vision') || 'الهوية والرؤية' }}</span>
                </div>
            </div>
        </section>

        <!-- Vision Section -->
        <section class="features fade-up" id="vision">
            <div class="container">
                <div class="section-header">
                    <h2>{{ $p(settings.value, 'vision_title') || t('nav_vision') || 'الهوية والرؤية' }}</h2>
                    <p>{{ $p(settings.value, 'vision_description') || $p(settings.value, 'site_description') || '' }}</p>
                </div>
                <div class="features-grid">
                    <div class="feature-item">
                        <i class="fas fa-award"></i>
                        <h3>{{ $p(settings.value, 'vision_feature_1_title') || t('global_quality') || 'جودة عالمية' }}</h3>
                        <p>{{ $p(settings.value, 'vision_feature_1_description') || t('global_quality_desc') || 'منتجات مختارة بعناية من أفضل الموردين لتناسب المشاريع الاحترافية.' }}</p>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-compass-drafting"></i>
                        <h3>{{ $p(settings.value, 'vision_feature_2_title') || t('modern_design') || 'تصميم عصري' }}</h3>
                        <p>{{ $p(settings.value, 'vision_feature_2_description') || t('modern_design_desc') || 'حلول ومواد تجمع بين الأداء العالي والمظهر الحديث.' }}</p>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-handshake"></i>
                        <h3>{{ $p(settings.value, 'vision_feature_3_title') || t('trusted_partnership') || 'شراكة موثوقة' }}</h3>
                        <p>{{ $p(settings.value, 'vision_feature_3_description') || t('trusted_partnership_desc') || 'دعم مستمر وخبرة لمساعدتك في اختيار الأنسب لمشروعك.' }}</p>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { useSettingsStore } from '@/stores/settings';
import { useI18n } from 'vue-i18n';
import { getImageUrl } from '@/utils/imageUrl';

const settingsStore = useSettingsStore();
const settings = computed(() => settingsStore.data);
const { t, locale } = useI18n();

// SEO Meta Tags
const updateSEOMetaTags = () => {
    const siteName = settings.value[`site_name_${locale.value}`] || settings.value.site_name || 'أوان التقدم';
    const visionTitle = settings.value[`vision_title_${locale.value}`] || settings.value.vision_title || 'الهوية والرؤية';
    const visionDescription = settings.value[`vision_description_${locale.value}`] || settings.value.vision_description || 'تعرف على رؤيتنا وقيمنا في تقديم أفضل قطع الغيار';
    const ogImage = settings.value.og_image ? getImageUrl(settings.value.og_image) : '/assets/images/logo.png';
    
    // Update document title
    document.title = `${visionTitle} - ${siteName}`;
    
    // Update meta description
    const metaDescription = document.querySelector('meta[name="description"]');
    if (metaDescription) {
        metaDescription.setAttribute('content', visionDescription);
    }
    
    // Update og:title
    const ogTitle = document.querySelector('meta[property="og:title"]');
    if (ogTitle) {
        ogTitle.setAttribute('content', `${visionTitle} - ${siteName}`);
    }
    
    // Update og:description
    const ogDescription = document.querySelector('meta[property="og:description"]');
    if (ogDescription) {
        ogDescription.setAttribute('content', visionDescription);
    }
    
    // Update og:image
    const ogImageMeta = document.querySelector('meta[property="og:image"]');
    if (ogImageMeta) {
        ogImageMeta.setAttribute('content', ogImage);
    }
    
    // Update twitter:title
    const twitterTitle = document.querySelector('meta[property="twitter:title"]');
    if (twitterTitle) {
        twitterTitle.setAttribute('content', `${visionTitle} - ${siteName}`);
    }
    
    // Update twitter:description
    const twitterDescription = document.querySelector('meta[property="twitter:description"]');
    if (twitterDescription) {
        twitterDescription.setAttribute('content', visionDescription);
    }
    
    // Update twitter:image
    const twitterImage = document.querySelector('meta[property="twitter:image"]');
    if (twitterImage) {
        twitterImage.setAttribute('content', ogImage);
    }
};

// Fetch settings on mount
onMounted(() => {
    settingsStore.fetch().catch((err) => console.warn(err));
    // Update SEO meta tags
    updateSEOMetaTags();
});
</script>

<style scoped>
.vision-page-view {
    padding-bottom: 3rem;
}

.features {
    padding: 3rem 0 5rem;
}

.features-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
    margin-top: 30px;
}

@media (max-width: 992px) {
    .features-grid {
        grid-template-columns: 1fr;
    }
}

.feature-item {
    background: rgba(255, 255, 255, 0.7) !important;
    backdrop-filter: blur(20px) saturate(160%) !important;
    -webkit-backdrop-filter: blur(20px) saturate(160%) !important;
    border: 1px solid rgba(255, 255, 255, 0.5) !important;
    border-radius: 24px !important;
    padding: 35px;
    text-align: center;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.03) !important;
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1) !important;
}

[data-theme="dark"] .feature-item {
    background: rgba(30, 41, 59, 0.45) !important;
    border-color: rgba(255, 255, 255, 0.08) !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2) !important;
}

.feature-item:hover {
    transform: translateY(-8px) scale(1.01) !important;
    background: rgba(255, 255, 255, 0.85) !important;
    box-shadow: 0 20px 40px color-mix(in srgb, var(--mobile-primary) 8%, transparent), 0 15px 30px rgba(0, 0, 0, 0.04) !important;
    border-color: color-mix(in srgb, var(--mobile-primary) 25%, transparent) !important;
}

[data-theme="dark"] .feature-item:hover {
    background: rgba(30, 41, 59, 0.6) !important;
    border-color: color-mix(in srgb, var(--mobile-primary) 25%, transparent) !important;
    box-shadow: 0 20px 40px color-mix(in srgb, var(--mobile-primary) 10%, transparent), 0 15px 30px rgba(0, 0, 0, 0.3) !important;
}

.feature-item i {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    background: color-mix(in srgb, var(--mobile-primary) 10%, transparent);
    color: var(--mobile-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    margin: 0 auto 20px auto;
    transition: all 0.3s ease;
}

[data-theme="dark"] .feature-item i {
    background: color-mix(in srgb, var(--mobile-primary) 15%, transparent);
    color: var(--mobile-primary);
}

.feature-item:hover i {
    transform: scale(1.1) rotate(5deg);
    background: var(--mobile-primary);
    color: white;
}

[data-theme="dark"] .feature-item:hover i {
    background: var(--mobile-primary);
    color: #0f172a;
}

.feature-item h3 {
    font-size: 1.25rem;
    font-weight: 800;
    margin: 0 0 12px 0;
    color: #0f172a;
}

[data-theme="dark"] .feature-item h3 {
    color: #f1f5f9;
}

.feature-item p {
    font-size: 0.9rem;
    color: #64748b;
    margin: 0;
    line-height: 1.7;
}

[data-theme="dark"] .feature-item p {
    color: #94a3b8;
}
</style>

