<template>
    <div
        class="print-document-header"
        :class="[`style-${headerStyle}`, { 'is-preview': previewMode, 'has-meta': showMeta && hasMetadata }]"
    >
        <!-- Style A: Official Corporate Letterhead Header (Default & Recommended) -->
        <template v-if="headerStyle !== 'banner'">
            <div class="header-main-row">
                <!-- Brand & Logo Side (Right in RTL, Left in LTR) -->
                <div class="brand-side">
                    <div v-if="showLogo" class="logo-wrapper">
                        <img
                            v-if="!logoFailed"
                            :src="resolvedLogo"
                            :alt="resolvedCompanyNameAr"
                            class="brand-logo"
                            @error="onLogoError"
                        />
                        <div v-else class="brand-logo-fallback" :title="resolvedCompanyNameAr">
                            <i class="fas fa-industry"></i>
                        </div>
                    </div>
                    <div class="brand-text">
                        <h1 class="brand-name-ar">{{ resolvedCompanyNameAr }}</h1>
                        <div class="brand-tagline">
                            <span>{{ resolvedTagline }}</span>
                        </div>
                        <div class="brand-domain-badge">
                            <i class="fas fa-globe"></i>
                            <span>{{ resolvedWebsite }}</span>
                        </div>
                    </div>
                </div>

                <!-- Center Document Title Badge (Optional if title exists) -->
                <div v-if="resolvedTitle" class="badge-side">
                    <div class="document-badge-card">
                        <div class="doc-badge-title">{{ resolvedTitle }}</div>
                        <div v-if="resolvedSubtitle" class="doc-badge-sub">{{ resolvedSubtitle }}</div>
                        <div v-if="documentNumber" class="doc-badge-number">
                            <span class="doc-num-label">REF:</span>
                            <span class="doc-num-val" dir="ltr">{{ documentNumber }}</span>
                        </div>
                    </div>
                </div>

                <!-- Contact & Official Reg Side (Left in RTL, Right in LTR) -->
                <div v-if="showContacts" class="info-side">
                    <div class="company-en-block">
                        <span class="en-name">{{ resolvedCompanyNameEn }}</span>
                        <span class="en-sub">{{ resolvedCompanySubEn }}</span>
                    </div>
                    <div class="contact-details-list">
                        <div v-if="resolvedPhone" class="contact-item">
                            <i class="fas fa-phone-alt"></i>
                            <span dir="ltr">{{ resolvedPhone }}</span>
                            <template v-if="resolvedSecondPhone">
                                <span class="sep">/</span>
                                <span dir="ltr">{{ resolvedSecondPhone }}</span>
                            </template>
                        </div>
                        <div v-if="resolvedEmail" class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <span dir="ltr">{{ resolvedEmail }}</span>
                        </div>
                        <div v-if="resolvedAddress" class="contact-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>{{ resolvedAddress }}</span>
                        </div>
                        <div v-if="resolvedCrNumber" class="contact-item cr-badge">
                            <i class="fas fa-certificate"></i>
                            <span>{{ $t('company_cr') || 'س.ت' }}: {{ resolvedCrNumber }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Multi-Color Brand Decorative Accent Bar -->
            <div class="header-divider-bar">
                <div class="divider-line-gradient"></div>
                <div class="divider-accent-dots">
                    <span class="dot dot-navy"></span>
                    <span class="dot dot-blue"></span>
                    <span class="dot dot-cyan"></span>
                    <span class="dot dot-gold"></span>
                </div>
            </div>
        </template>

        <!-- Style B: Legacy / Wide Graphic Banner -->
        <template v-else>
            <div class="header-banner-row">
                <img
                    :src="bannerSrc || '/Header.jpeg'"
                    :alt="resolvedCompanyNameAr"
                    class="print-banner-img"
                />
            </div>
            <div v-if="resolvedTitle || documentNumber" class="banner-title-bar">
                <div v-if="resolvedTitle" class="banner-title-badge">
                    <span class="banner-title-text">{{ resolvedTitle }}</span>
                    <span v-if="resolvedSubtitle" class="banner-subtitle-text">{{ resolvedSubtitle }}</span>
                </div>
                <div v-if="documentNumber" class="banner-doc-ref" dir="ltr">
                    <span class="doc-num-label">REF:</span>
                    <strong class="doc-num-val">{{ documentNumber }}</strong>
                </div>
            </div>
            <div class="header-divider-bar banner-divider">
                <div class="divider-line-gradient"></div>
            </div>
        </template>

        <!-- Structured Metadata Bar (Client, Date, Due Date, Notes) -->
        <div v-if="showMeta && hasMetadata" class="header-metadata-grid">
            <div v-if="customerName" class="meta-card">
                <div class="meta-icon"><i class="fas fa-user-tie"></i></div>
                <div class="meta-content">
                    <div class="meta-label">{{ $t('client_name') || 'العميل / الجهة' }}</div>
                    <div class="meta-value">{{ customerName }}</div>
                </div>
            </div>

            <div v-if="supplierName" class="meta-card">
                <div class="meta-icon"><i class="fas fa-truck-loading"></i></div>
                <div class="meta-content">
                    <div class="meta-label">{{ $t('supplier') || 'المورد' }}</div>
                    <div class="meta-value">{{ supplierName }}</div>
                </div>
            </div>

            <div v-if="date" class="meta-card">
                <div class="meta-icon"><i class="fas fa-calendar-check"></i></div>
                <div class="meta-content">
                    <div class="meta-label">{{ dateLabel || $t('offer_date') || 'التاريخ' }}</div>
                    <div class="meta-value" dir="ltr">{{ date }}</div>
                </div>
            </div>

            <div v-if="dueDate" class="meta-card">
                <div class="meta-icon"><i class="fas fa-calendar-alt"></i></div>
                <div class="meta-content">
                    <div class="meta-label">{{ dueDateLabel || $t('due_date') || 'تاريخ الاستحقاق' }}</div>
                    <div class="meta-value" dir="ltr">{{ dueDate }}</div>
                </div>
            </div>

            <div v-if="notes" class="meta-card meta-notes-card">
                <div class="meta-icon"><i class="fas fa-sticky-note"></i></div>
                <div class="meta-content">
                    <div class="meta-label">{{ notesLabel || $t('offer_notes') || 'ملاحظات' }}</div>
                    <div class="meta-value">{{ notes }}</div>
                </div>
            </div>

            <!-- Custom slot for extra metadata cards -->
            <slot name="extra-meta" />
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useSettingsStore } from '@/stores/settings';

const settingsStore = useSettingsStore();

const props = defineProps({
    /** Header style: 'official' (letterhead with logo & contacts) | 'compact' | 'banner' */
    headerStyle: {
        type: String,
        default: 'official',
        validator: (v) => ['official', 'compact', 'banner'].includes(v),
    },
    /** Document title, e.g. "عرض أسعار رسمي" / "أمر شراء رسمي" */
    title: {
        type: String,
        default: '',
    },
    /** English or secondary subtitle, e.g. "OFFICIAL PRICE OFFER" */
    subtitle: {
        type: String,
        default: '',
    },
    /** Reference / Document code, e.g. "PO-2026-0045" */
    documentNumber: {
        type: String,
        default: '',
    },
    /** Client / Customer name */
    customerName: {
        type: String,
        default: '',
    },
    /** Supplier name (for Purchase Orders) */
    supplierName: {
        type: String,
        default: '',
    },
    /** Document date string */
    date: {
        type: String,
        default: '',
    },
    dateLabel: {
        type: String,
        default: '',
    },
    /** Due date string */
    dueDate: {
        type: String,
        default: '',
    },
    dueDateLabel: {
        type: String,
        default: '',
    },
    /** Notes / Validity string */
    notes: {
        type: String,
        default: '',
    },
    notesLabel: {
        type: String,
        default: '',
    },
    /** Custom logo URL or fallback to site logo */
    logoSrc: {
        type: String,
        default: '',
    },
    /** Whether to show the logo in letterhead mode */
    showLogo: {
        type: Boolean,
        default: true,
    },
    /** Graphic banner image if headerStyle === 'banner' */
    bannerSrc: {
        type: String,
        default: '',
    },
    companyNameAr: {
        type: String,
        default: '',
    },
    companyNameEn: {
        type: String,
        default: '',
    },
    companySubEn: {
        type: String,
        default: '',
    },
    tagline: {
        type: String,
        default: '',
    },
    website: {
        type: String,
        default: '',
    },
    phone: {
        type: String,
        default: '',
    },
    secondPhone: {
        type: String,
        default: '',
    },
    email: {
        type: String,
        default: '',
    },
    address: {
        type: String,
        default: '',
    },
    crNumber: {
        type: String,
        default: '',
    },
    showContacts: {
        type: Boolean,
        default: true,
    },
    showMeta: {
        type: Boolean,
        default: true,
    },
    /** If true, renders scaled-down for dialog thumbnail/preview */
    previewMode: {
        type: Boolean,
        default: false,
    },
});

const logoFailed = ref(false);

watch(() => props.logoSrc, () => {
    logoFailed.value = false;
});

const getSystemSetting = (key, fallback = '') => {
    try {
        if (settingsStore?.data && settingsStore.data[key]) {
            return settingsStore.data[key];
        }
        if (typeof window !== 'undefined' && window.systemData?.settings?.[key]) {
            return window.systemData.settings[key];
        }
    } catch {
        // Ignore errors
    }
    return fallback;
};

const resolvedLogo = computed(() => {
    if (props.logoSrc) return props.logoSrc;
    const sysLogo = getSystemSetting('site_logo') || getSystemSetting('logo');
    if (sysLogo) {
        if (sysLogo.startsWith('http://') || sysLogo.startsWith('https://')) {
            return sysLogo;
        }
        if (sysLogo.startsWith('/')) {
            return sysLogo;
        }
        return `/${sysLogo}`;
    }
    return '/assets/images/logo.png';
});

const onLogoError = () => {
    logoFailed.value = true;
};

const resolvedCompanyNameAr = computed(() => {
    return props.companyNameAr || getSystemSetting('site_name', 'أوان التقدم للتجهيزات الصحية ومواد البناء');
});

const resolvedCompanyNameEn = computed(() => {
    return props.companyNameEn || getSystemSetting('site_name_en', 'AWAAN AL-TAKADOM CO.');
});

const resolvedCompanySubEn = computed(() => {
    return props.companySubEn || 'Sanitary Ware & Building Materials';
});

const resolvedTagline = computed(() => {
    return props.tagline || getSystemSetting('site_tagline', 'نبني معاً غد سورية الأجمل · Building a Better Tomorrow');
});

const resolvedWebsite = computed(() => {
    if (props.website) return props.website;
    const siteDomain = getSystemSetting('site_domain') || getSystemSetting('domain');
    if (siteDomain) return siteDomain;
    if (typeof window !== 'undefined' && window.location?.hostname) {
        return window.location.hostname;
    }
    return 'sanitary.awaanaltakadom.sy';
});

const resolvedPhone = computed(() => {
    return props.phone || getSystemSetting('contact_phone', '00963962889577');
});

const resolvedSecondPhone = computed(() => {
    return props.secondPhone || getSystemSetting('contact_second_phone', '0980831477');
});

const resolvedEmail = computed(() => {
    return props.email || getSystemSetting('contact_email', 'info@awaanaltakadom.sy');
});

const resolvedAddress = computed(() => {
    if (props.address) return props.address;
    const raw = getSystemSetting('contact_address');
    if (raw) {
        // Return cleaned single-line version for the header
        return raw.replace(/[\r\n]+/g, ' · ');
    }
    return 'دمشق - سوريا / الرياض - المملكة العربية السعودية';
});

const resolvedCrNumber = computed(() => {
    return props.crNumber || getSystemSetting('cr_number', '5048');
});

const resolvedTitle = computed(() => {
    return props.title;
});

const resolvedSubtitle = computed(() => {
    return props.subtitle;
});

const hasMetadata = computed(() => {
    return !!(
        props.customerName ||
        props.supplierName ||
        props.date ||
        props.dueDate ||
        props.notes
    );
});
</script>

<style scoped>
.print-document-header {
    width: 100%;
    margin-bottom: 12px;
    box-sizing: border-box;
    font-family: 'Cairo', 'Almarai', 'Tajawal', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    color: #0f172a;
    background: #ffffff;
    direction: rtl;
    text-align: right;
    user-select: none;
}

/* ──────────────────────────────────────────────────────────────────────────
   1. MAIN ROW: BRAND / LOGO + BADGE + CONTACTS
   ────────────────────────────────────────────────────────────────────────── */
.header-main-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 6px 4px 10px 4px;
}

/* Brand Side: Logo + Names + Domain Badge */
.brand-side {
    display: flex;
    align-items: center;
    gap: 14px;
    flex: 1 1 auto;
    min-width: 0;
}

.logo-wrapper {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 76px;
    height: 76px;
    padding: 2px;
    border-radius: 12px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.04);
}

.brand-logo {
    max-width: 100%;
    max-height: 100%;
    width: auto;
    height: auto;
    object-fit: contain;
    display: block;
}

.brand-logo-fallback {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    color: #1e3a8a;
    font-size: 28px;
    background: #f8fafc;
    border-radius: 8px;
}

.brand-text {
    display: flex;
    flex-direction: column;
    gap: 3px;
    min-width: 0;
}

.brand-name-ar {
    margin: 0;
    font-size: 17px;
    font-weight: 800;
    line-height: 1.25;
    color: #1e3a8a;
    letter-spacing: -0.02em;
    white-space: nowrap;
}

.brand-tagline {
    font-size: 10px;
    font-weight: 600;
    color: #64748b;
    line-height: 1.3;
}

.brand-domain-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    width: fit-content;
    margin-top: 2px;
    padding: 2px 8px;
    border-radius: 20px;
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    font-size: 10.5px;
    font-weight: 700;
    color: #0369a1;
    letter-spacing: 0.02em;
    direction: ltr;
}

.brand-domain-badge i {
    font-size: 10px;
    color: #0284c7;
}

/* Center Document Title Badge */
.badge-side {
    flex: 0 0 auto;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
}

.document-badge-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 6px 16px;
    border-radius: 10px;
    background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(30, 58, 138, 0.18);
    border: 1px solid #1e3a8a;
    min-width: 140px;
}

.doc-badge-title {
    font-size: 13.5px;
    font-weight: 800;
    line-height: 1.2;
    letter-spacing: 0.02em;
    color: #ffffff;
}

.doc-badge-sub {
    font-size: 8.5px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #93c5fd;
    margin-top: 2px;
}

.doc-badge-number {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-top: 4px;
    padding: 1px 7px;
    background: rgba(255, 255, 255, 0.18);
    border-radius: 4px;
    font-size: 9.5px;
    font-family: monospace;
    font-weight: 700;
    color: #fef08a;
}

.doc-num-label {
    opacity: 0.85;
    font-size: 8.5px;
}

/* Info Side: English Name, Contacts, Address, CR */
.info-side {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 3px;
    text-align: left;
    direction: ltr;
    font-size: 10px;
    flex: 0 0 auto;
    max-width: 250px;
}

.company-en-block {
    display: flex;
    flex-direction: column;
    margin-bottom: 2px;
}

.en-name {
    font-size: 11.5px;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: 0.04em;
    line-height: 1.2;
}

.en-sub {
    font-size: 8.5px;
    font-weight: 600;
    color: #64748b;
    line-height: 1.2;
}

.contact-details-list {
    display: flex;
    flex-direction: column;
    gap: 2px;
    color: #334155;
    font-size: 9.5px;
    font-weight: 600;
}

.contact-item {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    line-height: 1.3;
}

.contact-item i {
    font-size: 9px;
    color: #1e3a8a;
    width: 11px;
    text-align: center;
    flex-shrink: 0;
}

.contact-item .sep {
    color: #94a3b8;
    margin: 0 1px;
}

.cr-badge {
    color: #0369a1;
    font-weight: 700;
}

/* ──────────────────────────────────────────────────────────────────────────
   2. LUXURY BRAND DIVIDER BAR
   ────────────────────────────────────────────────────────────────────────── */
.header-divider-bar {
    position: relative;
    width: 100%;
    margin: 4px 0 8px 0;
}

.divider-line-gradient {
    height: 3px;
    border-radius: 2px;
    background: linear-gradient(90deg, #1e3a8a 0%, #2563eb 30%, #06b6d4 70%, #f59e0b 100%);
}

.divider-accent-dots {
    display: none;
}

/* Banner style fallback */
.header-banner-row {
    width: 100%;
    text-align: center;
}

.print-banner-img {
    max-width: 100%;
    height: auto;
    max-height: 90px;
    object-fit: contain;
    border-radius: 6px;
    display: block;
    margin: 0 auto;
}

.banner-title-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 6px 12px;
    margin-top: 6px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
}

.banner-title-badge {
    display: flex;
    align-items: baseline;
    gap: 8px;
}

.banner-title-text {
    font-size: 14px;
    font-weight: 800;
    color: #1e3a8a;
}

.banner-subtitle-text {
    font-size: 10px;
    color: #64748b;
    font-weight: 600;
}

.banner-doc-ref {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
}

.banner-doc-ref .doc-num-label {
    font-weight: 600;
    color: #64748b;
    font-size: 10.5px;
}

.banner-doc-ref .doc-num-val {
    color: #0369a1;
    font-family: 'JetBrains Mono', monospace;
    font-weight: 800;
}

.banner-divider {
    margin-top: 6px;
}

/* ──────────────────────────────────────────────────────────────────────────
   3. METADATA BAR (CARDS & CHIPS)
   ────────────────────────────────────────────────────────────────────────── */
.header-metadata-grid {
    display: flex;
    flex-wrap: wrap;
    align-items: stretch;
    gap: 8px;
    padding: 7px 10px;
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    box-sizing: border-box;
}

.meta-card {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px 10px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    flex: 1 1 auto;
    min-width: 120px;
}

.meta-card.meta-notes-card {
    flex: 2 1 200px;
}

.meta-icon {
    width: 26px;
    height: 26px;
    border-radius: 6px;
    background: #eff6ff;
    color: #1e3a8a;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    flex-shrink: 0;
}

.meta-content {
    display: flex;
    flex-direction: column;
    min-width: 0;
    line-height: 1.25;
}

.meta-label {
    font-size: 9.5px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
}

.meta-value {
    font-size: 12px;
    font-weight: 800;
    color: #0f172a;
    word-break: break-word;
}

/* ──────────────────────────────────────────────────────────────────────────
   4. COMPACT STYLE VARIANT
   ────────────────────────────────────────────────────────────────────────── */
.style-compact .header-main-row {
    padding: 2px 2px 6px 2px;
}

.style-compact .logo-wrapper {
    width: 54px;
    height: 54px;
}

.style-compact .brand-name-ar {
    font-size: 14px;
}

.style-compact .brand-tagline {
    display: none;
}

.style-compact .brand-domain-badge {
    font-size: 9px;
    padding: 1px 6px;
}

.style-compact .document-badge-card {
    padding: 4px 10px;
    min-width: 110px;
}

.style-compact .doc-badge-title {
    font-size: 11.5px;
}

.style-compact .info-side {
    font-size: 8.5px;
}

.style-compact .company-en-block {
    display: none;
}

/* ──────────────────────────────────────────────────────────────────────────
   5. PREVIEW MODE (FOR SETTINGS DRAWER THUMBNAIL)
   ────────────────────────────────────────────────────────────────────────── */
.is-preview {
    padding: 6px 8px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: #fdfdfe;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
}

.is-preview .header-main-row {
    gap: 8px;
    padding: 2px;
}

.is-preview .logo-wrapper {
    width: 36px;
    height: 36px;
    border-radius: 6px;
}

.is-preview .brand-name-ar {
    font-size: 10px;
}

.is-preview .brand-tagline {
    display: none;
}

.is-preview .brand-domain-badge {
    font-size: 8px;
    padding: 1px 4px;
}

.is-preview .document-badge-card {
    padding: 3px 6px;
    min-width: 70px;
    border-radius: 6px;
}

.is-preview .doc-badge-title {
    font-size: 8.5px;
}

.is-preview .doc-badge-sub {
    font-size: 6.5px;
}

.is-preview .doc-badge-number {
    display: none;
}

.is-preview .info-side {
    font-size: 7.5px;
    max-width: 120px;
}

.is-preview .company-en-block {
    display: none;
}

.is-preview .contact-item {
    font-size: 7.5px;
}

.is-preview .contact-item i {
    font-size: 7px;
}

.is-preview .header-metadata-grid {
    padding: 3px 6px;
    gap: 4px;
}

.is-preview .meta-card {
    padding: 2px 6px;
    min-width: 70px;
}

.is-preview .meta-icon {
    width: 16px;
    height: 16px;
    font-size: 8px;
}

.is-preview .meta-label {
    font-size: 7px;
}

.is-preview .meta-value {
    font-size: 8.5px;
}

/* ──────────────────────────────────────────────────────────────────────────
   6. PRINT STYLES & RENDERING REFINEMENTS
   ────────────────────────────────────────────────────────────────────────── */
@media print {
    .print-document-header {
        margin-bottom: 8px !important;
        break-inside: avoid !important;
        page-break-inside: avoid !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .document-badge-card {
        background: #1e3a8a !important;
        color: #ffffff !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .divider-line-gradient {
        background: #1e3a8a !important;
        border-bottom: 2px solid #06b6d4 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .header-metadata-grid {
        background: #f8fafc !important;
        border-color: #cbd5e1 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .meta-card {
        background: #ffffff !important;
        border-color: #cbd5e1 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
</style>
