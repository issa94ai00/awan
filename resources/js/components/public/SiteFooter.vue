<template>
    <!-- The storefront footer. Colours come from the theme settings (footer
         background and text) through CSS variables, so the admin's choice
         holds while links, muted text and accents keep their own weights. -->
    <footer class="site-footer" id="site-contact" :style="themeVars">
        <div class="container">
            <div class="footer-main">
                <!-- Brand -->
                <section class="footer-brand" :aria-label="siteName">
                    <router-link to="/" class="brand-link">
                        <span class="brand-logo">
                            <img :src="getImageUrl(settings.site_logo || 'assets/images/logo.png')" alt="" width="44" height="44" loading="lazy">
                        </span>
                        <span class="brand-name">{{ siteName }}</span>
                    </router-link>
                    <p class="brand-desc">{{ $p(settings, 'site_description') || t('about_default_desc') }}</p>

                    <ul v-if="socials.length" class="ft-social">
                        <li v-for="social in socials" :key="social.key">
                            <a :href="social.url" class="ft-social-link" target="_blank" rel="noopener" :aria-label="social.label">
                                <i :class="social.icon" aria-hidden="true"></i>
                            </a>
                        </li>
                    </ul>
                </section>

                <!-- Shop -->
                <nav class="footer-col" :aria-label="t('ft_shop')">
                    <h2 class="footer-heading">{{ t('ft_shop') }}</h2>
                    <ul class="footer-links">
                        <li><router-link to="/products">{{ t('all_products') }}</router-link></li>
                        <li><router-link to="/categories">{{ t('nav_categories') }}</router-link></li>
                        <li><router-link to="/special-offers">{{ t('special_offers_title') }}</router-link></li>
                        <li><router-link to="/featured-products">{{ t('featured_products') }}</router-link></li>
                        <li><router-link to="/cart">{{ t('cart') }}</router-link></li>
                    </ul>
                </nav>

                <!-- Company -->
                <nav class="footer-col" :aria-label="t('ft_company')">
                    <h2 class="footer-heading">{{ t('ft_company') }}</h2>
                    <ul class="footer-links">
                        <li><router-link to="/about">{{ t('nav_about') }}</router-link></li>
                        <li><router-link to="/vision">{{ t('nav_vision') }}</router-link></li>
                        <li><router-link to="/purchase-request">{{ t('ft_purchase_request') }}</router-link></li>
                        <li><router-link to="/inquiry">{{ t('send_inquiry') }}</router-link></li>
                        <li><router-link to="/contact">{{ t('nav_contact') }}</router-link></li>
                    </ul>
                </nav>

                <!-- Contact: every line is something to tap. -->
                <section class="footer-col footer-contact" :aria-label="t('nav_contact')">
                    <h2 class="footer-heading">{{ t('nav_contact') }}</h2>
                    <!-- One entry per branch from Settings › Contact, head office
                         first; each with its own phone and directions when set. -->
                    <ul v-if="branches.length" class="branch-list">
                        <li v-for="(branch, index) in branches" :key="index" class="branch-item">
                            <i class="fas fa-location-dot" aria-hidden="true"></i>
                            <div class="branch-body">
                                <strong v-if="branch.name" class="branch-name">
                                    {{ branch.name }}
                                    <span v-if="branch.is_main && branches.length > 1" class="branch-badge">{{ t('settings_branch_main') }}</span>
                                </strong>
                                <address v-if="branch.address">{{ branch.address }}</address>
                                <span v-if="branch.phone || branch.map_url" class="branch-links">
                                    <a v-if="branch.phone" :href="`tel:+${internationalDigits(branch.phone)}`" dir="ltr">{{ formatPhone(branch.phone) }}</a>
                                    <a v-if="branch.map_url" :href="branch.map_url" target="_blank" rel="noopener">
                                        {{ t('ft_directions') }} <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i>
                                    </a>
                                </span>
                            </div>
                        </li>
                    </ul>

                    <ul class="contact-list">
                        <li v-if="!branches.length && addressLines.length">
                            <i class="fas fa-location-dot" aria-hidden="true"></i>
                            <address>
                                <span v-for="(line, index) in addressLines" :key="index">{{ line }}</span>
                            </address>
                        </li>
                        <li v-if="settings.contact_phone">
                            <i class="fas fa-phone" aria-hidden="true"></i>
                            <a :href="`tel:${telNumber}`" dir="ltr">{{ displayPhone }}</a>
                        </li>
                        <li v-if="whatsappUrl">
                            <i class="fab fa-whatsapp" aria-hidden="true"></i>
                            <a :href="whatsappUrl" target="_blank" rel="noopener">{{ t('ft_whatsapp') }}</a>
                        </li>
                        <li v-if="settings.contact_email">
                            <i class="fas fa-envelope" aria-hidden="true"></i>
                            <a :href="`mailto:${settings.contact_email}`" dir="ltr">{{ settings.contact_email }}</a>
                        </li>
                        <li v-if="workingHours">
                            <i class="far fa-clock" aria-hidden="true"></i>
                            <span>{{ workingHours }}</span>
                        </li>
                    </ul>
                </section>
            </div>

            <div class="footer-bottom-bar">
                <p>&copy; {{ year }} {{ siteName }} · {{ t('all_rights_reserved') }}</p>
                <button type="button" class="back-to-top" @click="backToTop">
                    {{ t('ft_back_to_top') }}
                    <i class="fas fa-arrow-up" aria-hidden="true"></i>
                </button>
            </div>
        </div>
    </footer>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { getImageUrl } from '@/utils/imageUrl';

const props = defineProps({
    settings: { type: Object, required: true },
});

const { t, locale } = useI18n();

const isEn = computed(() => locale.value === 'en');
const year = new Date().getFullYear();

const siteName = computed(() => (isEn.value
    ? (props.settings.site_name_en || props.settings.site_name)
    : props.settings.site_name) || (isEn.value ? 'Awaan Altakadom' : 'أوان التقدم'));

const themeVars = computed(() => ({
    ...(props.settings.theme_footer_bg_color ? { '--footer-bg': props.settings.theme_footer_bg_color } : {}),
    ...(props.settings.theme_footer_text_color ? { '--footer-text': props.settings.theme_footer_text_color } : {}),
}));

// The address setting holds the head office and the branch on separate lines.
const addressLines = computed(() => {
    const raw = (isEn.value && props.settings.address_en)
        || props.settings.contact_address
        || props.settings.address
        || '';
    return String(raw)
        .split(/\r?\n/)
        .map((line) => line.replace(/\s*-\s*$/, '').trim())
        .filter(Boolean);
});

// "00963962889577" → dialled as +963…, shown grouped.
const internationalDigits = (value) => String(value || '').replace(/\D/g, '').replace(/^00/, '');
const formatPhone = (value) => {
    const digits = internationalDigits(value);
    const match = digits.match(/^(963)(\d{3})(\d{3})(\d{3})$/);
    return match ? `+${match[1]} ${match[2]} ${match[3]} ${match[4]}` : `+${digits}`;
};
const telNumber = computed(() => `+${internationalDigits(props.settings.contact_phone)}`);
const displayPhone = computed(() => formatPhone(props.settings.contact_phone));

/**
 * Branches from Settings › Contact (`contact_branches`, a JSON list), head
 * office first, in the viewer's language with Arabic standing in for missing
 * English. Empty when none are saved: the old address text shows instead.
 */
const branches = computed(() => {
    let list = props.settings.contact_branches;
    if (typeof list === 'string') {
        try {
            list = list ? JSON.parse(list) : [];
        } catch {
            list = [];
        }
    }
    if (!Array.isArray(list)) return [];

    return list
        .map((branch) => ({
            name: (isEn.value ? branch.name_en : '') || branch.name_ar || branch.name_en || '',
            address: (isEn.value ? branch.address_en : '') || branch.address_ar || branch.address_en || '',
            phone: internationalDigits(branch.phone) ? branch.phone : '',
            map_url: /^https?:\/\//i.test(branch.map_url || '') ? branch.map_url : '',
            is_main: Boolean(branch.is_main),
        }))
        .filter((branch) => branch.name || branch.address)
        .sort((a, b) => Number(b.is_main) - Number(a.is_main));
});

// wa.me takes the number without "00" or "+"; the old link kept the 00 and
// WhatsApp refused it.
const whatsappUrl = computed(() => {
    const digits = internationalDigits(props.settings.contact_whatsapp);
    return digits ? `https://wa.me/${digits}` : '';
});

const workingHours = computed(() => (isEn.value
    ? (props.settings.working_hours_en || props.settings.working_hours)
    : props.settings.working_hours) || '');

// Only the networks the shop has an address on; the old footer linked "#"
// for any it lacked.
const socials = computed(() => [
    { key: 'facebook', icon: 'fab fa-facebook-f', label: 'Facebook', url: props.settings.contact_facebook || props.settings.facebook },
    { key: 'instagram', icon: 'fab fa-instagram', label: 'Instagram', url: props.settings.contact_instagram || props.settings.instagram },
    { key: 'whatsapp', icon: 'fab fa-whatsapp', label: 'WhatsApp', url: whatsappUrl.value },
].filter((social) => social.url));

const backToTop = () => {
    const reduce = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
};
</script>

<style scoped>
.site-footer {
    --footer-bg: #1e1b4b;
    --footer-text: #e2e8f0;
    margin-top: 3rem;
    padding: 56px 0 0;
    background: var(--footer-bg) !important;
    color: var(--footer-text);
}

[data-theme="dark"] .site-footer {
    background: #080f1a !important;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.footer-main {
    display: grid;
    grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1.4fr);
    gap: 40px;
    padding-bottom: 40px;
}

/* ── Brand ── */
.brand-link {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    color: inherit;
    text-decoration: none;
}

.brand-logo {
    flex: none;
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fff;
    padding: 6px;
}

.brand-logo img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.brand-name {
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--footer-text);
}

.brand-desc {
    margin: 16px 0 0;
    max-width: 38ch;
    font-size: 0.92rem;
    line-height: 1.8;
    color: color-mix(in srgb, var(--footer-text) 72%, transparent);
}

.ft-social {
    display: flex;
    gap: 10px;
    margin: 20px 0 0;
    padding: 0;
    list-style: none;
}

.ft-social-link {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: color-mix(in srgb, var(--footer-text) 10%, transparent);
    color: var(--footer-text);
    font-size: 1.05rem;
    text-decoration: none;
    transition: background-color 0.2s ease, transform 0.2s ease;
}

.ft-social-link:hover {
    background: var(--mobile-primary);
    transform: translateY(-2px);
}

/* ── Link columns ── */
.footer-heading {
    margin: 0 0 18px;
    font-size: 0.95rem;
    font-weight: 800;
    letter-spacing: 0.02em;
    color: var(--footer-text);
}

.footer-links,
.contact-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.footer-links a,
.contact-list a {
    color: color-mix(in srgb, var(--footer-text) 75%, transparent);
    font-size: 0.92rem;
    text-decoration: none;
    transition: color 0.15s ease;
}

.footer-links a:hover,
.contact-list a:hover {
    color: var(--footer-text);
    text-decoration: underline;
    text-underline-offset: 4px;
}

.footer-links a.router-link-exact-active {
    color: var(--footer-text);
    font-weight: 700;
}

.site-footer a:focus-visible,
.back-to-top:focus-visible {
    outline: 2px solid currentColor;
    outline-offset: 3px;
    border-radius: 4px;
}

/* ── Contact ── */
.contact-list li {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    font-size: 0.92rem;
    line-height: 1.6;
    color: color-mix(in srgb, var(--footer-text) 75%, transparent);
}

.contact-list i {
    flex: none;
    width: 18px;
    margin-top: 4px;
    text-align: center;
    color: var(--footer-text);
    opacity: 0.85;
}

.contact-list address {
    display: flex;
    flex-direction: column;
    gap: 2px;
    font-style: normal;
}

/* ── Branches ── */
.branch-list {
    display: flex;
    flex-direction: column;
    gap: 14px;
    margin: 0 0 16px;
    padding: 0 0 16px;
    list-style: none;
    border-bottom: 1px solid color-mix(in srgb, var(--footer-text) 12%, transparent);
}

.branch-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    font-size: 0.92rem;
    line-height: 1.6;
}

.branch-item > i {
    flex: none;
    width: 18px;
    margin-top: 4px;
    text-align: center;
    color: var(--footer-text);
    opacity: 0.85;
}

.branch-body {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.branch-name {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
    color: var(--footer-text);
    font-weight: 700;
}

.branch-badge {
    padding: 0 8px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--footer-text) 14%, transparent);
    font-size: 0.7rem;
    font-weight: 700;
}

.branch-body address {
    font-style: normal;
    color: color-mix(in srgb, var(--footer-text) 75%, transparent);
}

.branch-links {
    display: flex;
    flex-wrap: wrap;
    gap: 4px 14px;
    margin-top: 2px;
}

.branch-links a {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: color-mix(in srgb, var(--footer-text) 75%, transparent);
    font-size: 0.85rem;
    text-decoration: underline;
    text-decoration-color: color-mix(in srgb, var(--footer-text) 30%, transparent);
    text-underline-offset: 3px;
}

.branch-links a:hover {
    color: var(--footer-text);
}

.branch-links i {
    font-size: 0.7rem;
}

/* ── Bottom bar ── */
.footer-bottom-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px 24px;
    padding: 20px 0 24px;
    border-top: 1px solid color-mix(in srgb, var(--footer-text) 14%, transparent);
}

.footer-bottom-bar p {
    margin: 0;
    font-size: 0.85rem;
    color: color-mix(in srgb, var(--footer-text) 65%, transparent);
}

.back-to-top {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 40px;
    padding: 0 16px;
    border: 1px solid color-mix(in srgb, var(--footer-text) 22%, transparent);
    border-radius: 999px;
    background: transparent;
    color: var(--footer-text);
    font: inherit;
    font-size: 0.85rem;
    font-weight: 700;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.back-to-top:hover {
    background: color-mix(in srgb, var(--footer-text) 10%, transparent);
}

/* ── Tablet: brand and contact across, the two link lists side by side ── */
@media (max-width: 1024px) {
    .footer-main {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 32px 24px;
    }

    .footer-brand,
    .footer-contact {
        grid-column: 1 / -1;
    }

    .brand-desc {
        max-width: 60ch;
    }
}

/* ── Phone ── */
@media (max-width: 640px) {
    .site-footer {
        padding-top: 40px;
    }

    .footer-main {
        gap: 28px 16px;
        padding-bottom: 28px;
    }

    .footer-bottom-bar {
        flex-direction: column;
        text-align: center;
    }

    /* Clear of the floating contact buttons. */
    .footer-bottom-bar {
        padding-bottom: 88px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .ft-social-link,
    .footer-links a {
        transition: none;
    }

    .ft-social-link:hover {
        transform: none;
    }
}
</style>
