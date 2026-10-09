import { reactive, ref, computed, watch } from 'vue';
import { useSettingsStore } from '@/stores/settings';
import { ElMessage } from 'element-plus';
import { useI18n } from 'vue-i18n';

export function usePrintSettings(docType = 'invoice', initialOverrides = {}) {
    const { t } = useI18n();
    const settingsStore = useSettingsStore();

    const storageKey = `awaan_print_settings_${docType}`;
    const zoomLevel = ref(100);

    // 1. Resolve System-wide defaults from backend settings
    const getSystemDefaults = () => {
        const sys = settingsStore?.data || {};
        return {
            preset: 'tax',
            headerStyle: sys.print_header_style || 'official', // 'official' | 'banner' | 'compact'
            theme: sys.print_theme || 'navy', // 'navy' | 'emerald' | 'charcoal' | 'indigo'
            density: sys.print_density || 'standard', // 'standard' | 'compact'
            watermark: sys.print_watermark || '', // '' | 'draft' | 'approved' | 'paid' | 'official'
            showCover: sys.print_show_cover === '1' || sys.print_show_cover === true || false,
            showQrCode: sys.print_show_qr !== '0' && sys.print_show_qr !== false,
            showLogo: sys.print_show_logo !== '0' && sys.print_show_logo !== false,
            showContacts: sys.print_show_contacts !== '0' && sys.print_show_contacts !== false,
            showImages: sys.print_show_images !== '0' && sys.print_show_images !== false,
            showSku: sys.print_show_sku !== '0' && sys.print_show_sku !== false,
            showCustomerInfo: sys.print_show_customer_info !== '0' && sys.print_show_customer_info !== false,
            showPaymentDetails: sys.print_show_payment_details !== '0' && sys.print_show_payment_details !== false,
            showNotes: sys.print_show_notes !== '0' && sys.print_show_notes !== false,
            showSignatures: sys.print_show_signatures !== '0' && sys.print_show_signatures !== false,
            showFooter: sys.print_show_footer !== '0' && sys.print_show_footer !== false,
            customNotes: sys.print_terms || '',
            authorizedPerson: sys.print_authorized_person || '',
            authorizedTitle: sys.print_authorized_title || '',
            stampImage: sys.stamp_image || '',
            signatureImage: sys.signature_image || '',
            headerBanner: sys.header_banner || '/Header.jpeg',
            coverImage: sys.cover_image || '/cover.jpeg',
            ...initialOverrides,
        };
    };

    // 2. Load user preferences from localStorage if exists
    const loadStoredPreferences = () => {
        try {
            if (typeof window !== 'undefined' && window.localStorage) {
                const raw = localStorage.getItem(storageKey);
                if (raw) {
                    return JSON.parse(raw);
                }
            }
        } catch (e) {
            console.warn('Failed to parse stored print preferences:', e);
        }
        return null;
    };

    const initial = { ...getSystemDefaults(), ...(loadStoredPreferences() || {}) };
    const printSettings = reactive(initial);

    // Watch settings store changes if loaded later
    watch(() => settingsStore?.data, () => {
        const stored = loadStoredPreferences();
        if (!stored) {
            Object.assign(printSettings, getSystemDefaults());
        }
    }, { deep: true });

    // 3. Presets Definitions
    const applyPreset = (presetKey) => {
        printSettings.preset = presetKey;
        if (presetKey === 'tax') {
            printSettings.headerStyle = 'official';
            printSettings.density = 'standard';
            printSettings.showCover = false;
            printSettings.showQrCode = true;
            printSettings.showLogo = true;
            printSettings.showContacts = true;
            printSettings.showImages = true;
            printSettings.showSku = true;
            printSettings.showCustomerInfo = true;
            printSettings.showPaymentDetails = true;
            printSettings.showNotes = true;
            printSettings.showSignatures = true;
            printSettings.showFooter = true;
        } else if (presetKey === 'receipt') {
            printSettings.headerStyle = 'compact';
            printSettings.density = 'compact';
            printSettings.showCover = false;
            printSettings.showQrCode = true;
            printSettings.showLogo = true;
            printSettings.showContacts = false;
            printSettings.showImages = false;
            printSettings.showSku = false;
            printSettings.showCustomerInfo = true;
            printSettings.showPaymentDetails = true;
            printSettings.showNotes = false;
            printSettings.showSignatures = false;
            printSettings.showFooter = true;
        } else if (presetKey === 'commercial') {
            printSettings.headerStyle = 'banner';
            printSettings.density = 'standard';
            printSettings.showCover = false;
            printSettings.showQrCode = true;
            printSettings.showLogo = true;
            printSettings.showContacts = true;
            printSettings.showImages = true;
            printSettings.showSku = true;
            printSettings.showCustomerInfo = true;
            printSettings.showPaymentDetails = true;
            printSettings.showNotes = true;
            printSettings.showSignatures = true;
            printSettings.showFooter = true;
        } else if (presetKey === 'warehouse') {
            printSettings.headerStyle = 'compact';
            printSettings.density = 'compact';
            printSettings.showCover = false;
            printSettings.showQrCode = true;
            printSettings.showLogo = true;
            printSettings.showContacts = false;
            printSettings.showImages = false;
            printSettings.showSku = true;
            printSettings.showCustomerInfo = true;
            printSettings.showPaymentDetails = false;
            printSettings.showNotes = true;
            printSettings.showSignatures = true;
            printSettings.showFooter = true;
        }
    };

    // 4. Persistence Actions
    const saveAsDefault = () => {
        try {
            if (typeof window !== 'undefined' && window.localStorage) {
                localStorage.setItem(storageKey, JSON.stringify(printSettings));
                ElMessage.success({
                    message: t('print_prefs_saved_default') || 'تم حفظ خيارات الطباعة كإعداد افتراضي لجهازك بنجاح',
                    duration: 4000,
                });
            }
        } catch (e) {
            console.error('Failed to save print preferences:', e);
            ElMessage.error(t('failed_to_save_prefs') || 'فشل حفظ الإعدادات الافتراضية');
        }
    };

    const resetToDefaults = () => {
        try {
            if (typeof window !== 'undefined' && window.localStorage) {
                localStorage.removeItem(storageKey);
            }
            const defaults = getSystemDefaults();
            Object.assign(printSettings, defaults);
            ElMessage.info({
                message: t('print_prefs_reset_done') || 'تمت استعادة الإعدادات الافتراضية للنظام بنجاح',
                duration: 3500,
            });
        } catch (e) {
            console.error('Failed to reset print preferences:', e);
        }
    };

    // 5. Zoom & Viewport Helpers
    const zoomIn = () => {
        if (zoomLevel.value < 150) zoomLevel.value += 10;
    };
    const zoomOut = () => {
        if (zoomLevel.value > 60) zoomLevel.value -= 10;
    };
    const resetZoom = () => {
        zoomLevel.value = 100;
    };

    const zoomContainerStyle = computed(() => {
        if (zoomLevel.value === 100) return {};
        return {
            transform: `scale(${zoomLevel.value / 100})`,
            transformOrigin: 'top center',
            transition: 'transform 0.2s cubic-bezier(0.4, 0, 0.2, 1)',
        };
    });

    const watermarkLabels = {
        draft: 'مسودة — DRAFT',
        approved: 'معتمد — APPROVED',
        paid: 'مدفوع بالكامل — PAID',
        official: 'فاتورة رسمية — OFFICIAL',
    };

    const watermarkText = computed(() => watermarkLabels[printSettings.watermark] || '');

    const isCustomized = computed(() => {
        const sys = getSystemDefaults();
        return JSON.stringify(printSettings) !== JSON.stringify(sys);
    });

    return {
        printSettings,
        zoomLevel,
        zoomIn,
        zoomOut,
        resetZoom,
        zoomContainerStyle,
        watermarkText,
        isCustomized,
        applyPreset,
        saveAsDefault,
        resetToDefaults,
        getSystemDefaults,
    };
}
