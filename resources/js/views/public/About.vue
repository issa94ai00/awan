<template>
    <div class="about-page-view">
        <!-- Page Header -->
        <section class="page-header">
            <div class="container">
                <h1>{{ $p(settings, 'about_title') || t('nav_about') || 'من نحن' }}</h1>
                <div class="breadcrumb">
                    <router-link to="/">{{ t('nav_home') || 'الرئيسية' }}</router-link>
                    <span class="sep">›</span>
                    <span>{{ $p(settings, 'about_title') || t('nav_about') || 'من نحن' }}</span>
                </div>
            </div>
        </section>

        <!-- About Section -->
        <section class="about-section fade-up" id="about">
            <div class="container">
                <div class="section-header">
                    <h2>{{ $p(settings, 'site_name') || '' }}</h2>
                    <p>{{ $p(settings, 'about_description') || t('about_default_desc') || 'نحن في أوان التقدم نقدم مستلزمات البناء التي تجمع بين الجودة العالمية والعصرية في التصميم، لنكون شريكك الأمثل في مشاريعك الإنشائية.' }}</p>
                </div>

                <div class="about-content">
                    <div class="about-story">
                        <h3>{{ t('our_story') || 'قصتنا' }}</h3>
                        <!-- We use v-html to preserve any html tags formatted in the settings -->
                        <p v-html="$p(settings, 'about_story') || defaultStory"></p>
                    </div>

                    <div class="about-values">
                        <h3>{{ t('our_values') || 'قيمنا' }}</h3>
                        <div class="values-grid">
                            <div v-for="(val, index) in parsedValues" :key="'val-'+index" class="value-item">
                                <i :class="val.icon"></i>
                                <h4>{{ val.title }}</h4>
                                <p>{{ val.desc }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="about-services">
                        <h3>{{ t('what_we_offer') || 'ما نقدمه' }}</h3>
                        <div class="services-list">
                            <div v-for="(srv, index) in parsedServices" :key="'srv-'+index" class="service-item">
                                <i :class="srv.icon"></i>
                                <div class="service-text">
                                    <span class="service-title">{{ srv.title }}</span>
                                    <span class="service-desc" v-if="srv.desc">{{ srv.desc }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Stats Section -->
        <section class="stats-section fade-up">
            <div class="container">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon-wrapper">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="stat-info">
                            <span class="stat-number">+{{ settings.about_years || '10' }}</span>
                            <span class="stat-label">{{ t('years_experience') || 'سنوات خبرة' }}</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon-wrapper">
                            <i class="fas fa-project-diagram"></i>
                        </div>
                        <div class="stat-info">
                            <span class="stat-number">+{{ settings.about_projects || '500' }}</span>
                            <span class="stat-label">{{ t('completed_projects') || 'مشروع منجز' }}</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon-wrapper">
                            <i class="fas fa-smile"></i>
                        </div>
                        <div class="stat-info">
                            <span class="stat-number">+{{ settings.about_customers || '1000' }}</span>
                            <span class="stat-label">{{ t('happy_customer') || 'عميل سعيد' }}</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon-wrapper">
                            <i class="fas fa-award"></i>
                        </div>
                        <div class="stat-info">
                            <span class="stat-number">+{{ settings.about_partners || '50' }}</span>
                            <span class="stat-label">{{ t('trusted_partner') || 'شريك موثوق' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    </div>
</template>

<script setup>
import { computed, onMounted, watch } from 'vue';
import { useSettingsStore } from '@/stores/settings';
import { useI18n } from 'vue-i18n';
import { getImageUrl } from '@/utils/imageUrl';

const settingsStore = useSettingsStore();
const settings = computed(() => settingsStore.data);
const { t, locale } = useI18n();

// SEO Meta Tags
const updateSEOMetaTags = () => {
    const loc = locale.value === 'en' ? '_en' : '';
    const s = (field, fallback = '') => settings.value?.[`${field}${loc}`] || settings.value?.[field] || fallback;
    const siteName = s('site_name', 'أوان التقدم');
    const aboutTitle = s('about_title', 'من نحن');
    const aboutDescription = s('about_description', 'نحن في أوان التقدم نقدم مستلزمات البناء التي تجمع بين الجودة العالمية والعصرية في التصميم، لنكون شريكك الأمثل في مشاريعك الإنشائية.');
    const ogImage = settings.value.og_image ? getImageUrl(settings.value.og_image) : '/assets/images/logo.png';
    
    document.title = `${aboutTitle} - ${siteName}`;
    
    const metaDescription = document.querySelector('meta[name="description"]');
    if (metaDescription) {
        metaDescription.setAttribute('content', aboutDescription);
    }
    
    const ogTitle = document.querySelector('meta[property="og:title"]');
    if (ogTitle) {
        ogTitle.setAttribute('content', `${aboutTitle} - ${siteName}`);
    }
    
    const ogDescription = document.querySelector('meta[property="og:description"]');
    if (ogDescription) {
        ogDescription.setAttribute('content', aboutDescription);
    }
    
    const ogImageMeta = document.querySelector('meta[property="og:image"]');
    if (ogImageMeta) {
        ogImageMeta.setAttribute('content', ogImage);
    }
    
    const twitterTitle = document.querySelector('meta[property="twitter:title"]');
    if (twitterTitle) {
        twitterTitle.setAttribute('content', `${aboutTitle} - ${siteName}`);
    }
    
    const twitterDescription = document.querySelector('meta[property="twitter:description"]');
    if (twitterDescription) {
        twitterDescription.setAttribute('content', aboutDescription);
    }
    
    const twitterImage = document.querySelector('meta[property="twitter:image"]');
    if (twitterImage) {
        twitterImage.setAttribute('content', ogImage);
    }
};

const defaultStory = computed(() => {
    let siteName = 'أوان التكادوم';
    if (settings.value) {
        siteName = settings.value[`site_name_${locale.value || 'ar'}`] || settings.value['site_name'] || siteName;
    }
    
    if (locale.value === 'en') {
        return `We at <strong>${siteName}</strong> provide building materials that combine global quality with modern design, making us your ideal partner for your construction projects. We are committed to providing products that meet the highest standards of quality and durability to suit your diverse needs.`;
    }
    return `نحن في <strong>${siteName}</strong> نقدم مستلزمات البناء التي تجمع بين الجودة العالمية والعصرية في التصميم، لنكون شريكك الأمثل في مشاريعك الإنشائية. نحرص على توفير منتجات تلبي أعلى معايير الجودة والمتانة لتناسب احتياجاتكم المتنوعة.`;
});

const defaultValues = computed(() => [
    { title: t('about_value_1_title') || 'الجودة العالمية', desc: t('about_value_1_desc') || 'نلتزم بأعلى معايير الكفاءة والمتانة في كل منتج نقدّمه لضمان استدامة مشاريعكم.', icon: 'fas fa-award' },
    { title: t('about_value_2_title') || 'الابتكار والعصرية', desc: t('about_value_2_desc') || 'نواكب أحدث توجهات التصميم المعماري لنمنح مشاريعكم لمسة جمالية متجددة.', icon: 'fas fa-lightbulb' },
    { title: t('about_value_3_title') || 'الشراكة الحقيقية', desc: t('about_value_3_desc') || 'لا نرى عملاءنا كمشترين، بل كشركاء نجاح نرافقهم خطوة بخطوة حتى اكتمال البناء.', icon: 'fas fa-handshake' },
    { title: t('about_value_4_title') || 'النزاهة والشفافية', desc: t('about_value_4_desc') || 'نلتزم بالصدق والأمانة في التعامل، والوضوح التام في مواصفات المنتجات ومواعيد التسليم.', icon: 'fas fa-shield-alt' },
    { title: t('about_value_5_title') || 'الاستدامة والمسؤولية', desc: t('about_value_5_desc') || 'نحرص على توفير مستلزمات بناء صديقة للبيئة تساهم في إعمار مستقبل آمن وصحي.', icon: 'fas fa-leaf' }
]);

const parsedValues = computed(() => {
    const loc = locale.value === 'en' ? '_en' : '';
    const items = [];
    const icons = ['fas fa-gem', 'fas fa-lightbulb', 'fas fa-handshake', 'fas fa-users'];
    
    let hasCustom = false;
    
    for (let i = 1; i <= 5; i++) {
        let title = '';
        let desc = '';
        
        if (settings.value) {
            title = settings.value[`about_value_${i}_title${loc}`] || settings.value[`about_value_${i}_title`] || '';
            desc = settings.value[`about_value_${i}_desc${loc}`] || settings.value[`about_value_${i}_desc`] || '';
        }
        
        if (title || desc) {
            hasCustom = true;
            items.push({
                title: title || '',
                desc: desc || '',
                icon: icons[i-1] || 'fas fa-check'
            });
        }
    }
    
    if (!hasCustom) {
        return defaultValues.value;
    }
    
    return items;
});

const defaultServices = computed(() => [
    { title: t('materials_sanitary') || 'أدوات صحية وعصرية', desc: t('materials_sanitary_desc') || '', icon: 'fas fa-shower' },
    { title: t('materials_lighting') || 'أنظمة إضاءة ذكية', desc: t('materials_lighting_desc') || '', icon: 'fas fa-lightbulb' },
    { title: t('materials_ceramics') || 'سيراميك وبورسلان فاخر', desc: t('materials_ceramics_desc') || '', icon: 'fas fa-border-all' },
    { title: t('materials_insulation') || 'مواد عزل وحماية', desc: t('materials_insulation_desc') || '', icon: 'fas fa-shield-alt' },
    { title: t('materials_facades') || 'واجهات زجاجية وكلادينج', desc: t('materials_facades_desc') || '', icon: 'fas fa-building' },
    { title: t('materials_consulting') || 'استشارات وتوريد للمشاريع', desc: t('materials_consulting_desc') || '', icon: 'fas fa-handshake' }
]);

const parsedServices = computed(() => {
    let raw = '';
    if (settings.value) {
        raw = settings.value[`about_services_${locale.value || 'ar'}`] || settings.value['about_services'] || '';
    }
    if (!raw) return defaultServices.value;
    
    const lines = raw.split('\n').map(l => l.trim()).filter(l => l);
    return lines.map((line, index) => {
        const text = t(line.trim()) || line.trim();
        const colonIdx = text.indexOf(':');
        const title = colonIdx > 0 ? text.substring(0, colonIdx).trim() : text;
        const desc = colonIdx > 0 ? text.substring(colonIdx + 1).trim() : '';
        return {
            title,
            desc,
            icon: ['fas fa-shower', 'fas fa-lightbulb', 'fas fa-border-all', 'fas fa-shield-alt', 'fas fa-building', 'fas fa-handshake', 'fas fa-cogs', 'fas fa-cog'][index % 8]
        };
    });
});

// Update SEO reactively when settings or locale change
watch([settings, locale], () => {
    updateSEOMetaTags();
}, { immediate: true });

// Fetch settings on mount
onMounted(async () => {
    try {
        await settingsStore.fetch();
        updateSEOMetaTags();
    } catch (err) {
        console.warn(err);
    }
});
</script>

<style scoped>
.about-page-view {
    padding-bottom: 3rem;
}

.about-section {
    padding: 3rem 0 5rem;
}

.about-content {
    display: flex;
    flex-direction: column;
    gap: 40px;
    margin-top: 30px;
}

.about-story {
    background: rgba(255, 255, 255, 0.7) !important;
    backdrop-filter: blur(20px) saturate(160%);
    -webkit-backdrop-filter: blur(20px) saturate(160%);
    border: 1px solid rgba(255, 255, 255, 0.5) !important;
    border-radius: 24px !important;
    padding: 35px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.03) !important;
    color: #334155;
    line-height: 1.8;
}

[data-theme="dark"] .about-story {
    background: rgba(30, 41, 59, 0.45) !important;
    border-color: rgba(255, 255, 255, 0.08) !important;
    color: #cbd5e1;
}

.about-story h3 {
    font-size: 1.5rem;
    font-weight: 800;
    margin: 0 0 20px 0;
    color: var(--mobile-primary);
}

[data-theme="dark"] .about-story h3 {
    color: var(--mobile-primary);
}

.about-values h3, .about-services h3 {
    font-size: 1.5rem;
    font-weight: 800;
    margin: 0 0 25px 0;
    color: var(--mobile-primary);
}

[data-theme="dark"] .about-values h3, 
[data-theme="dark"] .about-services h3 {
    color: var(--mobile-primary);
}

.values-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 20px;
}

@media (max-width: 1199px) {
    .values-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 767px) {
    .values-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .values-grid {
        grid-template-columns: 1fr;
    }
}

.value-item {
    position: relative;
    background: rgba(255, 255, 255, 0.7) !important;
    backdrop-filter: blur(24px) saturate(180%);
    -webkit-backdrop-filter: blur(24px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.5) !important;
    border-radius: 24px !important;
    padding: 30px 24px 28px;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.04) !important;
    transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1) !important;
    text-align: center;
    overflow: hidden;
    z-index: 1;
}

.value-item::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 24px;
    padding: 2px;
    background: linear-gradient(135deg, transparent 40%, var(--mobile-primary) 50%, transparent 60%);
    background-size: 200% 200%;
    -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    opacity: 0;
    transition: opacity 0.6s ease, background-position 1s ease;
    z-index: -1;
}

.value-item:hover::before {
    opacity: 1;
    background-position: 100% 100%;
}

[data-theme="dark"] .value-item {
    background: rgba(30, 41, 59, 0.4) !important;
    border-color: rgba(255, 255, 255, 0.06) !important;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.3) !important;
}

.value-item:hover {
    transform: translateY(-8px) scale(1.02);
    border-color: transparent !important;
    background: rgba(255, 255, 255, 0.85) !important;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.08) !important;
}

[data-theme="dark"] .value-item:hover {
    border-color: transparent !important;
    background: rgba(30, 41, 59, 0.55) !important;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4) !important;
}

.value-item i {
    width: 56px;
    height: 56px;
    border-radius: 16px;
    background: linear-gradient(135deg, color-mix(in srgb, var(--mobile-primary) 10%, transparent), color-mix(in srgb, var(--mobile-primary) 5%, transparent));
    color: var(--mobile-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    margin: 0 auto 18px auto;
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
}

[data-theme="dark"] .value-item i {
    background: linear-gradient(135deg, color-mix(in srgb, var(--mobile-primary) 20%, transparent), color-mix(in srgb, var(--mobile-primary) 8%, transparent));
    color: var(--mobile-primary-light, var(--mobile-primary));
}

.value-item:hover i {
    transform: scale(1.12) rotate(6deg);
    background: linear-gradient(135deg, var(--mobile-primary), color-mix(in srgb, var(--mobile-primary) 70%, #000));
    color: white;
    box-shadow: 0 8px 24px color-mix(in srgb, var(--mobile-primary) 30%, transparent);
}

[data-theme="dark"] .value-item:hover i {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
}

.value-item h4 {
    font-size: 1.1rem;
    font-weight: 700;
    margin: 0 0 10px 0;
    color: #0f172a;
    transition: color 0.3s ease;
}

.value-item:hover h4 {
    color: var(--mobile-primary-dark, var(--mobile-primary));
}

[data-theme="dark"] .value-item h4 {
    color: #f1f5f9;
}

[data-theme="dark"] .value-item:hover h4 {
    color: var(--mobile-primary-light, var(--mobile-primary));
}

.value-item p {
    font-size: 0.88rem;
    color: #64748b;
    margin: 0;
    line-height: 1.7;
}

[data-theme="dark"] .value-item p {
    color: #94a3b8;
}

.services-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}

.service-item {
    background: rgba(255, 255, 255, 0.5);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.4);
    border-radius: 16px;
    padding: 18px 20px;
    display: flex;
    align-items: flex-start;
    gap: 15px;
    transition: all 0.3s ease;
}

[data-theme="dark"] .service-item {
    background: rgba(30, 41, 59, 0.3);
    border-color: rgba(255, 255, 255, 0.05);
}

.service-item:hover {
    transform: translateY(-3px);
    border-color: var(--mobile-primary);
    box-shadow: 0 8px 24px color-mix(in srgb, var(--mobile-primary) 8%, transparent);
}

[data-theme="dark"] .service-item:hover {
    border-color: var(--mobile-primary);
}

.service-item i {
    font-size: 1.3rem;
    color: var(--mobile-primary);
    margin-top: 3px;
    flex-shrink: 0;
}

[data-theme="dark"] .service-item i {
    color: var(--mobile-primary-light, var(--mobile-primary));
}

.service-text {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.service-title {
    font-weight: 700;
    font-size: 0.98rem;
    color: #0f172a;
}

.service-desc {
    font-size: 0.82rem;
    color: #64748b;
    line-height: 1.5;
    font-weight: 400;
}

[data-theme="dark"] .service-title {
    color: #f1f5f9;
}

[data-theme="dark"] .service-desc {
    color: #94a3b8;
}

/* Stats Section */
.stats-section {
    padding: 60px 0;
    background: rgba(255, 255, 255, 0.4);
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    border-top: 1px solid rgba(0, 0, 0, 0.06);
    border-bottom: 1px solid rgba(0, 0, 0, 0.06);
    margin-top: 50px;
}

[data-theme="dark"] .stats-section {
    background: rgba(15, 23, 42, 0.4) !important;
    border-top: 1px solid rgba(255, 255, 255, 0.08) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 24px;
}

.stat-card {
    background: rgba(255, 255, 255, 0.7) !important;
    backdrop-filter: blur(20px) saturate(160%);
    -webkit-backdrop-filter: blur(20px) saturate(160%);
    border: 1px solid rgba(255, 255, 255, 0.5) !important;
    border-radius: 20px !important;
    padding: 28px 24px;
    display: flex;
    align-items: center;
    gap: 20px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.02) !important;
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1) !important;
}

.stat-card:hover {
    transform: translateY(-6px);
    border-color: var(--mobile-primary) !important;
    box-shadow: 0 12px 40px color-mix(in srgb, var(--mobile-primary) 10%, transparent) !important;
}

.stat-icon-wrapper {
    width: 58px;
    height: 58px;
    border-radius: 18px;
    background: color-mix(in srgb, var(--mobile-primary) 10%, transparent);
    color: var(--mobile-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    transition: all 0.3s ease;
}

.stat-card:hover .stat-icon-wrapper {
    background: var(--mobile-primary);
    color: white;
    transform: scale(1.1) rotate(5deg);
}

.stat-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.stat-number {
    font-size: 1.9rem;
    font-weight: 800;
    color: var(--mobile-primary-dark, #1e3a1a);
    line-height: 1.2;
}

.stat-label {
    font-size: 0.95rem;
    color: #64748b;
    font-weight: 600;
}

/* Dark Mode overrides */
[data-theme="dark"] .stat-card {
    background: rgba(30, 41, 59, 0.45) !important;
    border-color: rgba(255, 255, 255, 0.08) !important;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.25) !important;
}

[data-theme="dark"] .stat-card:hover {
    background: rgba(30, 41, 59, 0.55) !important;
    border-color: var(--mobile-primary-light, var(--mobile-primary)) !important;
    box-shadow: 0 12px 40px color-mix(in srgb, var(--mobile-primary) 20%, transparent) !important;
}

[data-theme="dark"] .stat-icon-wrapper {
    background: rgba(255, 255, 255, 0.05);
    color: var(--mobile-primary-light, var(--mobile-primary));
    border: 1px solid rgba(255, 255, 255, 0.08);
}

[data-theme="dark"] .stat-card:hover .stat-icon-wrapper {
    background: var(--mobile-primary);
    color: #ffffff;
    border-color: var(--mobile-primary);
}

[data-theme="dark"] .stat-number {
    color: #f1f5f9;
}

[data-theme="dark"] .stat-label {
    color: #94a3b8;
}
</style>

