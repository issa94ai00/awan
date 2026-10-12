<template>
    <!-- Quote Print Preview Dialog (A4 Simulation & Isolated Iframe Print), laid out like the invoice's -->
    <el-dialog
        :model-value="modelValue"
        :width="fullscreen ? '100%' : '1040px'"
        :fullscreen="fullscreen"
        :top="fullscreen ? '0' : '2vh'"
        class="qp-print-dialog"
        destroy-on-close
        append-to-body
        @update:model-value="emit('update:modelValue', $event)"
    >
        <template #header>
            <div class="qp-dialog-header qp-screen-only">
                <div class="qp-dh-left">
                    <div class="qp-dh-icon"><i class="fas fa-print"></i></div>
                    <div class="qp-dh-info">
                        <div class="qp-dh-main-line">
                            <span class="qp-dh-title-text">{{ $t('qt_print_preview') }}</span>
                            <strong v-if="quote" class="qp-dh-num" dir="ltr">{{ quote.quote_number }}</strong>
                        </div>
                        <div v-if="quote" class="qp-dh-subline">
                            <span><i class="fas fa-calendar-day"></i> {{ formatDate(quote.created_at) }}</span>
                            <span class="qp-dh-customer-crumb"><i class="fas fa-user"></i> {{ customerName(quote) }}</span>
                        </div>
                    </div>
                </div>

                <div v-if="quote" class="qp-dh-badges">
                    <span class="qp-status-pill" :class="`pill-${quote.status}`">
                        <i class="fas" :class="statusIcon(quote.status)"></i> {{ statusLabel(quote.status) }}
                    </span>
                    <span v-if="validity" class="qp-validity-pill" :class="`v-${validity.tone}`">
                        <i class="fas fa-hourglass-half"></i> {{ validity.label }}
                    </span>
                    <span class="qp-dh-count">
                        <i class="fas fa-box-open"></i> {{ $t('qt_print_lines', { count: lineCount, qty: totalQuantity }) }}
                    </span>
                    <span class="qp-dh-pages-est">
                        <i class="fas fa-file-lines"></i> {{ $t('qt_print_pages', { count: estimatedPages }, estimatedPages) }}
                    </span>
                </div>

                <div class="qp-dh-actions">
                    <el-button size="small" class="qp-fs-toggle-btn" :type="fullscreen ? 'primary' : 'default'" plain @click="fullscreen = !fullscreen">
                        <i class="fas" :class="fullscreen ? 'fa-compress' : 'fa-expand'"></i>
                        <span>{{ fullscreen ? $t('exit_fullscreen') : $t('fullscreen') }}</span>
                    </el-button>
                </div>
            </div>
        </template>

        <!-- Print Settings Toolbar (Screen Only) -->
        <PrintSettingsToolbar
            v-model="printSettings"
            :zoom-level="zoomLevel"
            :is-exporting-pdf="isExportingPdf"
            :is-printing="isPrinting"
            show-font-picker
            @apply-preset="applyPreset"
            @save-default="saveAsDefault"
            @reset-default="resetToDefaults"
            @zoom-in="zoomIn"
            @zoom-out="zoomOut"
            @reset-zoom="resetZoom"
            @export-pdf="exportPdf"
            @print-now="print"
        />

        <div v-if="quote && validity?.tone === 'lapsed'" class="qp-lapsed-banner qp-screen-only">
            <i class="fas fa-triangle-exclamation"></i>
            <span>{{ $t('qt_print_lapsed_warning') }}</span>
        </div>

        <!-- Paper simulation stage with Zoom Container -->
        <div class="qp-paper-stage" :class="{ 'is-fullscreen': fullscreen }" v-loading="loading" :element-loading-text="$t('loading')">
            <div class="qp-paper-viewport" :style="zoomContainerStyle">
                <div
                    v-if="quote"
                    :id="SHEET_ID"
                    class="qp-printable-sheet"
                    :lang="lang"
                    :dir="dir"
                    :style="sheetStyle"
                    :class="[
                        `theme-${printSettings.theme}`,
                        { 'density-compact': printSettings.density === 'compact' },
                        { 'has-cover': printSettings.showCover },
                    ]"
                >
                    <!-- Optional Full-page Cover -->
                    <div v-if="printSettings.showCover" class="qp-print-cover">
                        <img :src="assetUrl(printSettings.coverImage, '/cover.jpeg')" alt="" />
                    </div>

                    <div class="qp-document-body">
                        <div v-if="watermarkLabel" class="qp-sheet-watermark" :class="`wm-${watermarkKey}`">
                            <span>{{ watermarkLabel }}</span>
                        </div>

                        <PrintDocumentHeader
                            :header-style="printSettings.headerStyle"
                            :show-logo="printSettings.showLogo"
                            :show-contacts="printSettings.showContacts"
                            :show-meta="false"
                            :title="$t('qt_print_official_title')"
                            subtitle="OFFICIAL PRICE QUOTATION"
                            :document-number="quote.quote_number"
                            :banner-src="assetUrl(printSettings.headerBanner, '/Header.jpeg')"
                        />

                        <!-- Document Ribbon: type, status, validity, dates & QR -->
                        <div class="qp-doc-ribbon">
                            <div class="qp-ribbon-left">
                                <span class="qp-ribbon-doc-type">
                                    <i class="fas fa-file-signature"></i> {{ $t('qt_print_official_title') }}
                                </span>
                                <span class="qp-ribbon-status" :class="`status-${quote.status}`">
                                    <i class="fas" :class="statusIcon(quote.status)"></i> {{ statusLabel(quote.status) }}
                                </span>
                                <span v-if="validity" class="qp-ribbon-validity" :class="`v-${validity.tone}`">
                                    <i class="fas fa-hourglass-half"></i> {{ validity.label }}
                                </span>
                                <span v-if="quote.sales_order" class="qp-ribbon-order">
                                    <i class="fas fa-cart-shopping"></i>
                                    {{ $t('qt_sales_order') }}: <strong dir="ltr">{{ quote.sales_order.order_number }}</strong>
                                </span>
                            </div>
                            <div class="qp-ribbon-right">
                                <div class="qp-ribbon-item">
                                    <span class="lbl"><i class="fas fa-calendar-day"></i> {{ $t('qt_print_issue_date') }}:</span>
                                    <strong class="val">{{ formatDate(quote.created_at) }}</strong>
                                </div>
                                <div class="qp-ribbon-item">
                                    <span class="lbl"><i class="fas fa-clock"></i> {{ $t('valid_until') }}:</span>
                                    <strong class="val">{{ quote.valid_until ? formatDate(quote.valid_until) : $t('qt_no_expiry') }}</strong>
                                </div>
                                <div v-if="printSettings.showQrCode && qrDataUrl" class="qp-ribbon-qr">
                                    <img :src="qrDataUrl" alt="QR" class="qp-ribbon-qr-img" />
                                    <span class="qp-ribbon-qr-label">{{ $t('qr_verification_label') }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Customer & Quote Metadata Grid -->
                        <div v-if="printSettings.showCustomerInfo" class="qp-print-meta-grid">
                            <div class="qp-meta-card">
                                <div class="qp-meta-card-header">
                                    <div class="qp-card-header-icon"><i class="fas fa-user-tie"></i></div>
                                    <span class="qp-meta-card-title">{{ $t('qt_print_customer_card') }}</span>
                                </div>
                                <div class="qp-meta-card-body">
                                    <div class="qp-customer-primary">
                                        <strong class="qp-customer-name">{{ customerName(quote) }}</strong>
                                        <span v-if="quote.customer?.company" class="qp-customer-company">({{ quote.customer.company }})</span>
                                    </div>
                                    <div class="qp-customer-meta-list">
                                        <div v-if="quote.customer?.phone" class="qp-cm-item">
                                            <i class="fas fa-phone-alt"></i><span dir="ltr">{{ quote.customer.phone }}</span>
                                        </div>
                                        <div v-if="quote.customer?.email" class="qp-cm-item">
                                            <i class="fas fa-envelope"></i><span dir="ltr">{{ quote.customer.email }}</span>
                                        </div>
                                        <div v-if="quote.customer?.address" class="qp-cm-item">
                                            <i class="fas fa-location-dot"></i><span>{{ quote.customer.address }}</span>
                                        </div>
                                        <div v-if="quote.customer?.tax_number" class="qp-cm-item qp-cm-tax">
                                            <i class="fas fa-certificate"></i>
                                            <span>{{ $t('qt_print_tax_number') }}: <strong>{{ quote.customer.tax_number }}</strong></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="qp-meta-card">
                                <div class="qp-meta-card-header">
                                    <div class="qp-card-header-icon"><i class="fas fa-file-invoice"></i></div>
                                    <span class="qp-meta-card-title">{{ $t('qt_print_details_card') }}</span>
                                </div>
                                <div class="qp-meta-card-body">
                                    <div class="qp-details-meta-grid">
                                        <div class="qp-dm-item">
                                            <span class="qp-dm-label">{{ $t('qt_print_number') }}:</span>
                                            <strong class="qp-dm-val qp-mono" dir="ltr">{{ quote.quote_number }}</strong>
                                        </div>
                                        <div class="qp-dm-item">
                                            <span class="qp-dm-label">{{ $t('status') }}:</span>
                                            <span class="qp-dm-val qp-state-pill" :class="`p-${quote.status}`">{{ statusLabel(quote.status) }}</span>
                                        </div>
                                        <div class="qp-dm-item">
                                            <span class="qp-dm-label">{{ $t('qt_print_validity') }}:</span>
                                            <span class="qp-dm-val" :class="{ 'is-lapsed': validity?.tone === 'lapsed' }">
                                                {{ validity ? validity.label : $t('qt_no_expiry') }}
                                            </span>
                                        </div>
                                        <div v-if="quote.creator?.name" class="qp-dm-item">
                                            <span class="qp-dm-label">{{ $t('qt_print_sales_officer') }}:</span>
                                            <span class="qp-dm-val">{{ quote.creator.name }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Line Items Table -->
                        <table class="qp-print-table">
                            <thead>
                                <tr>
                                    <th class="c-center" style="width: 34px;">#</th>
                                    <th v-if="printSettings.showImages" class="c-center" style="width: 48px;">{{ $t('qt_print_image') }}</th>
                                    <th>{{ $t('product') }}</th>
                                    <th v-if="printSettings.showSku" style="width: 105px;">SKU</th>
                                    <th class="c-center" style="width: 80px;">{{ $t('quantity') }}</th>
                                    <th class="c-end" style="width: 110px;">{{ $t('unit_price') }}</th>
                                    <th v-if="hasLineDiscount" class="c-end" style="width: 85px;">{{ $t('discount') }}</th>
                                    <th v-if="hasLineTax" class="c-end" style="width: 85px;">{{ $t('tax') }}</th>
                                    <th class="c-end" style="width: 125px;">{{ $t('total') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, idx) in items" :key="item.id || idx">
                                    <td class="c-center qp-row-idx">{{ idx + 1 }}</td>
                                    <td v-if="printSettings.showImages" class="c-center qp-print-img-cell">
                                        <EntityImage
                                            :src="resolveImageUrl(item.product?.image_main) || ''"
                                            type="product"
                                            :size="printSettings.density === 'compact' ? 30 : 38"
                                            shape="square"
                                            :lazy="false"
                                        />
                                    </td>
                                    <td>
                                        <div class="qp-item-name">{{ itemName(item) }}</div>
                                        <div v-if="item.product?.name_en && lang === 'ar'" class="qp-item-name-en">{{ item.product.name_en }}</div>
                                        <div v-if="item.description && item.product && item.description !== itemName(item)" class="qp-item-desc">{{ item.description }}</div>
                                    </td>
                                    <td v-if="printSettings.showSku" class="qp-sku-cell"><code>{{ item.product?.sku || '—' }}</code></td>
                                    <td class="c-center qp-qty-cell">
                                        <span class="qp-qty-num">{{ item.quantity }}</span>
                                        <span v-if="item.product?.unit" class="qp-unit-label">{{ item.product.unit }}</span>
                                    </td>
                                    <td class="c-end qp-price-cell" dir="ltr">{{ formatCurrency(item.unit_price) }}</td>
                                    <td v-if="hasLineDiscount" class="c-end qp-discount-cell" dir="ltr">{{ toNum(item.discount) ? `− ${formatCurrency(item.discount)}` : '—' }}</td>
                                    <td v-if="hasLineTax" class="c-end qp-tax-cell" dir="ltr">{{ toNum(item.tax) ? formatCurrency(item.tax) : '—' }}</td>
                                    <td class="c-end qp-total-cell" dir="ltr"><strong>{{ formatCurrency(item.total) }}</strong></td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="qp-table-summary-row">
                                    <td :colspan="(printSettings.showImages ? 1 : 0) + (printSettings.showSku ? 1 : 0) + 3" class="qp-sum-label">
                                        <i class="fas fa-layer-group"></i>
                                        <span>{{ $t('qt_print_lines_total') }}:</span>
                                        <strong class="qp-sum-count">{{ lineCount }}</strong>
                                        ·
                                        <span>{{ $t('qt_print_total_qty') }}:</span>
                                        <strong class="qp-sum-count">{{ totalQuantity }}</strong>
                                    </td>
                                    <td :colspan="(hasLineDiscount ? 1 : 0) + (hasLineTax ? 1 : 0) + 2" class="qp-sum-total" dir="ltr">
                                        <span class="qp-sum-subtext">{{ $t('subtotal') }}:</span>
                                        <strong>{{ formatCurrency(quote.subtotal) }}</strong>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>

                        <!-- Closing Section: Terms, Totals, Signatures & Footer -->
                        <div class="qp-print-closing-section">
                            <div class="qp-print-summary-row">
                                <div class="qp-print-notes-col">
                                    <div v-if="printSettings.showNotes && quote.terms" class="qp-print-notes-box">
                                        <div class="qp-notes-header">
                                            <i class="fas fa-file-contract"></i>
                                            <strong>{{ $t('qt_terms') }}:</strong>
                                        </div>
                                        <p class="qp-notes-text">{{ quote.terms }}</p>
                                    </div>
                                    <div v-if="printSettings.showNotes && quote.notes" class="qp-print-notes-box">
                                        <div class="qp-notes-header">
                                            <i class="fas fa-clipboard-list"></i>
                                            <strong>{{ $t('notes') }}:</strong>
                                        </div>
                                        <p class="qp-notes-text">{{ quote.notes }}</p>
                                    </div>
                                    <div class="qp-print-legal-notice">
                                        <i class="fas fa-circle-info"></i>
                                        <span>{{ validityNotice }}</span>
                                    </div>
                                </div>

                                <div class="qp-print-totals-col">
                                    <div class="qp-totals-card">
                                        <div class="qp-totals-header">
                                            <i class="fas fa-coins"></i>
                                            <strong>{{ $t('qt_print_financial_summary') }}</strong>
                                        </div>
                                        <table class="qp-totals-table">
                                            <tr>
                                                <td>{{ $t('subtotal') }}:</td>
                                                <td class="val" dir="ltr">{{ formatCurrency(quote.subtotal) }}</td>
                                            </tr>
                                            <tr v-if="toNum(quote.discount) > 0">
                                                <td>{{ $t('qt_extra_discount') }}:</td>
                                                <td class="val discount-val" dir="ltr">− {{ formatCurrency(quote.discount) }}</td>
                                            </tr>
                                            <tr v-if="toNum(quote.tax) > 0">
                                                <td>{{ $t('qt_extra_tax') }}:</td>
                                                <td class="val" dir="ltr">{{ formatCurrency(quote.tax) }}</td>
                                            </tr>
                                            <tr class="grand-total-row">
                                                <td>{{ $t('qt_print_grand_total') }}:</td>
                                                <td class="val grand-val" dir="ltr">{{ formatCurrency(quote.total) }}</td>
                                            </tr>
                                            <tr v-if="quote.valid_until" class="valid-row" :class="{ 'is-lapsed': validity?.tone === 'lapsed' }">
                                                <td>{{ $t('valid_until') }}:</td>
                                                <td class="val">{{ formatDate(quote.valid_until) }}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Signatures -->
                            <div v-if="printSettings.showSignatures" class="qp-print-signatures">
                                <div class="sig-card">
                                    <span class="sig-title"><i class="fas fa-pen-nib"></i> {{ $t('qt_print_sig_prepared') }}</span>
                                    <div v-if="quote.creator?.name" class="sig-name">{{ quote.creator.name }}</div>
                                    <div class="sig-line"></div>
                                    <span class="sig-sub">{{ $t('qt_print_sig_hint') }}</span>
                                </div>
                                <div class="sig-card">
                                    <span class="sig-title"><i class="fas fa-user-check"></i> {{ $t('qt_print_sig_customer') }}</span>
                                    <div class="sig-name">{{ customerName(quote) }}</div>
                                    <div class="sig-line"></div>
                                    <span class="sig-sub">{{ $t('qt_print_sig_hint') }}</span>
                                </div>
                                <div class="sig-card">
                                    <span class="sig-title"><i class="fas fa-stamp"></i> {{ $t('qt_print_sig_company') }}</span>
                                    <div v-if="printSettings.authorizedPerson" class="sig-name">
                                        {{ printSettings.authorizedPerson }}
                                        <small v-if="printSettings.authorizedTitle" class="sig-name-title">{{ printSettings.authorizedTitle }}</small>
                                    </div>
                                    <div class="sig-seal-box" :class="{ 'has-stamp': !!stampUrl }">
                                        <img v-if="stampUrl" :src="stampUrl" alt="" class="sig-stamp-img" />
                                        <span v-else>{{ $t('qt_print_seal') }}</span>
                                    </div>
                                    <img v-if="signatureUrl" :src="signatureUrl" alt="" class="sig-digital-img" />
                                </div>
                            </div>

                            <!-- Running Footer -->
                            <div v-if="printSettings.showFooter" class="qp-print-footer">
                                <div class="qp-footer-brand">
                                    <strong>{{ companyName }}</strong>
                                    <template v-if="companyAddress"> — {{ companyAddress }}</template>
                                </div>
                                <div class="qp-footer-meta">
                                    <span>{{ printedAt }}</span>
                                    <span>·</span>
                                    <span>A4 Price Quotation · {{ quote.quote_number }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </el-dialog>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import QRCode from 'qrcode';
import jsPDF from 'jspdf';
import html2canvas from 'html2canvas';
import PrintDocumentHeader from '@/components/admin/PrintDocumentHeader.vue';
import PrintSettingsToolbar from '@/components/admin/PrintSettingsToolbar.vue';
import EntityImage from '@/components/admin/EntityImage.vue';
import { usePrintSettings } from '@/composables/usePrintSettings';
import { useQuotesStore } from '@/stores/quotes';
import { useSettingsStore } from '@/stores/settings';
import { resolveImageUrl } from '@/utils/productImages';
import { customerName, formatCurrency, formatDate, statusIcon, statusLabel } from '@/utils/sales';
import { ensurePrintFont, printFontCssUrl, printFontStack, waitForPrintFont } from '@/utils/printFonts';
import { A4_HEIGHT_MM, A4_WIDTH_MM, addSheetPages, sheetCaptureOptions } from '@/utils/printPdf';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    /** A list row or a loaded quote; the full quote is always fetched for the sheet. */
    source: { type: Object, default: null },
});
const emit = defineEmits(['update:modelValue']);

const SHEET_ID = 'quote-printable-doc';
const IFRAME_ID = 'qp-print-hidden-iframe';
const BREAK_SELECTOR = '.qp-print-table thead, .qp-print-table tr, .qp-doc-ribbon, .qp-print-meta-grid, '
    + '.qp-print-summary-row, .qp-print-signatures, .qp-print-footer, .print-document-header';

const { t, locale } = useI18n();
const store = useQuotesStore();
const settings = useSettingsStore();

const quote = ref(null);
const loading = ref(false);
const fullscreen = ref(false);
const isPrinting = ref(false);
const isExportingPdf = ref(false);
const qrDataUrl = ref('');

const {
    printSettings,
    zoomLevel,
    zoomIn,
    zoomOut,
    resetZoom,
    zoomContainerStyle,
    applyPreset,
    saveAsDefault,
    resetToDefaults,
    watermarkText,
} = usePrintSettings('quote');

const toNum = (value) => Number(value) || 0;

const lang = computed(() => (locale.value === 'en' ? 'en' : 'ar'));
const dir = computed(() => (lang.value === 'en' ? 'ltr' : 'rtl'));
// The sheet's typeface; every element inside inherits it through --qp-font.
const sheetStyle = computed(() => ({ '--qp-font': printFontStack(printSettings.fontFamily) }));

const assetUrl = (path, fallback = '') => {
    const value = path || fallback;
    if (!value) return '';
    return value.startsWith('/') || value.startsWith('http') ? value : `/storage/${value}`;
};
const stampUrl = computed(() => assetUrl(printSettings.stampImage));
const signatureUrl = computed(() => assetUrl(printSettings.signatureImage));

const site = computed(() => settings.data || {});
const companyName = computed(() => (lang.value === 'ar' ? site.value.site_name : site.value.site_name_en || site.value.site_name) || '');
const companyAddress = computed(() => site.value.contact_address || site.value.address || '');

const items = computed(() => quote.value?.items || []);
const lineCount = computed(() => items.value.length);
const totalQuantity = computed(() => items.value.reduce((sum, item) => sum + toNum(item.quantity), 0));
const hasLineDiscount = computed(() => items.value.some((item) => toNum(item.discount)));
const hasLineTax = computed(() => items.value.some((item) => toNum(item.tax)));

const itemName = (item) => (lang.value === 'en' && item.product?.name_en) || item.product?.name_ar || item.description || '—';

const estimatedPages = computed(() => {
    const limit = printSettings.density === 'compact' ? 14 : 9;
    const pages = lineCount.value <= limit ? 1 : Math.ceil(lineCount.value / limit);
    return printSettings.showCover ? pages + 1 : pages;
});

const DAY = 86400000;
/** Days until the quote lapses, counted in whole calendar days. */
const validity = computed(() => {
    const q = quote.value;
    if (!q?.valid_until) return null;
    const end = new Date(q.valid_until);
    if (Number.isNaN(end.getTime())) return null;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    end.setHours(0, 0, 0, 0);
    const days = Math.round((end - today) / DAY);
    if (days < 0 || q.is_past_validity) return { days, tone: 'lapsed', label: t('qt_print_lapsed') };
    if (days === 0) return { days, tone: 'soon', label: t('qt_print_last_day') };
    return { days, tone: days <= 3 ? 'soon' : 'ok', label: t('qt_print_days_left', { count: days }, days) };
});

const validityNotice = computed(() => printSettings.customNotes || (quote.value?.valid_until
    ? t('qt_print_valid_notice', { date: formatDate(quote.value.valid_until) })
    : t('qt_print_open_notice')));

// A draft goes out marked as one unless the toolbar picked a watermark.
const watermarkKey = computed(() => printSettings.watermark || (quote.value?.status === 'draft' ? 'draft' : ''));
const watermarkLabel = computed(() => {
    if (printSettings.watermark) return watermarkText.value;
    return watermarkKey.value === 'draft' ? 'مسودة — DRAFT' : '';
});

const printedAt = computed(() => {
    const d = new Date();
    const loc = lang.value === 'en' ? 'en-GB' : 'ar-SY';
    return `${d.toLocaleDateString(loc)} ${d.toLocaleTimeString(loc, { hour: '2-digit', minute: '2-digit' })}`;
});

const buildQr = async (q) => {
    try {
        const payload = [q.quote_number, formatDate(q.created_at), formatCurrency(q.total), customerName(q)].join(' | ');
        qrDataUrl.value = await QRCode.toDataURL(payload, { width: 140, margin: 1, color: { dark: '#0f172a', light: '#ffffff' } });
    } catch {
        qrDataUrl.value = '';
    }
};

const load = async (source) => {
    quote.value = { ...source };
    zoomLevel.value = 100;
    loading.value = true;
    try {
        const needsSettings = !Object.keys(settings.data || {}).length;
        const [full] = await Promise.all([
            store.fetchQuote(source.id),
            needsSettings ? settings.fetch() : null,
        ]);
        if (full) quote.value = full;
    } catch {
        // Keep what the list row gave us; a partial preview beats none.
        if (!source.items) ElMessage.warning(t('qt_load_failed'));
    } finally {
        loading.value = false;
    }
    buildQr(quote.value);
};

watch(() => [props.modelValue, props.source], ([open, source]) => {
    if (open && source) load(source);
}, { immediate: true });

watch(() => [props.modelValue, printSettings.fontFamily], ([open, font]) => {
    if (open) ensurePrintFont(font);
});

const documentTitle = () => `${t('qt_print_heading')} ${quote.value?.quote_number || ''}`.trim();

// ── Print through an isolated iframe so only the sheet reaches the printer ──
const printCss = () => `
    @page { size: A4 portrait; margin: 0 !important; }
    @page :left { margin: 0 !important; }
    @page :right { margin: 0 !important; }
    @page :first { margin: 0 !important; }
    * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; box-sizing: border-box !important; }
    html, body {
        margin: 0 !important; padding: 0 !important; background: #fff !important; color: #0f172a !important;
        font-family: ${printFontStack(printSettings.fontFamily)} !important; direction: ${dir.value} !important;
        width: 100% !important; height: auto !important; overflow: visible !important;
        text-rendering: optimizeLegibility; -webkit-font-smoothing: antialiased;
    }
    a { text-decoration: none !important; color: inherit !important; }
    a[href]:after, abbr[title]:after { content: none !important; }
    .brand-domain-badge, .domain-badge { display: none !important; }
    #${SHEET_ID} { width: 100% !important; max-width: 100% !important; margin: 0 !important; padding: 0 !important; border: none !important; box-shadow: none !important; background: #fff !important; display: block !important; }
    .qp-print-cover { display: block !important; width: 100% !important; height: 297mm !important; margin: 0 !important; border-radius: 0 !important; box-shadow: none !important; overflow: hidden !important; break-after: page !important; page-break-after: always !important; }
    .qp-print-cover img { width: 100% !important; height: 297mm !important; object-fit: cover !important; }
    .qp-document-body { width: 100% !important; max-width: 100% !important; margin: 0 !important; padding: 10mm 12mm !important; border: none !important; border-radius: 0 !important; box-shadow: none !important; background: #fff !important; }
    .qp-print-table thead { display: table-header-group !important; }
    .qp-print-table tfoot { display: table-row-group !important; }
    .qp-print-table tr, .qp-doc-ribbon, .qp-print-meta-grid, .qp-print-summary-row, .qp-print-signatures, .qp-print-footer, .qp-print-closing-section {
        break-inside: avoid !important; page-break-inside: avoid !important;
    }
    .qp-screen-only { display: none !important; }
`;

const waitForImages = (doc) => new Promise((resolve) => {
    const pending = Array.from(doc.images || []).filter((img) => !img.complete);
    if (!pending.length) return setTimeout(resolve, 200);
    let left = pending.length;
    const done = () => { if (--left <= 0) resolve(); };
    pending.forEach((img) => { img.onload = done; img.onerror = done; });
    setTimeout(resolve, 1500);
});

const print = async () => {
    const sheet = document.getElementById(SHEET_ID);
    if (!sheet || isPrinting.value || loading.value) return;
    isPrinting.value = true;
    try {
        const fontKey = printSettings.fontFamily;
        const styles = Array.from(document.querySelectorAll('link[rel="stylesheet"], style')).map((node) => node.outerHTML).join('\n');
        document.getElementById(IFRAME_ID)?.remove();
        const iframe = document.createElement('iframe');
        iframe.id = IFRAME_ID;
        Object.assign(iframe.style, { position: 'fixed', left: '0', top: '0', width: '100vw', height: '100vh', border: '0', opacity: '0', pointerEvents: 'none', zIndex: '-9999' });
        document.body.appendChild(iframe);

        const doc = iframe.contentWindow.document;
        const title = documentTitle().replace(/[<>&"]/g, '');
        doc.open();
        doc.write(`<!DOCTYPE html><html dir="${dir.value}" lang="${lang.value}"><head><meta charset="utf-8">
            <base href="${window.location.origin}/"><title>${title}</title>${styles}
            <link rel="stylesheet" href="${printFontCssUrl(fontKey)}" data-print-font="${fontKey}"><style>${printCss()}</style>
            </head><body>${sheet.outerHTML}</body></html>`);
        doc.close();

        await waitForImages(doc);
        // Printing before the webfont arrives prints a fallback face or blank Arabic.
        await waitForPrintFont(fontKey, doc);
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
        setTimeout(() => iframe.remove(), 60000);
    } catch (e) {
        console.error('Quote print failed:', e);
        ElMessage.error(t('qt_print_failed'));
    } finally {
        isPrinting.value = false;
    }
};

const exportPdf = async () => {
    const sheet = document.getElementById(SHEET_ID);
    if (!sheet || isExportingPdf.value || loading.value) return;
    isExportingPdf.value = true;
    const prevZoom = zoomLevel.value;
    try {
        zoomLevel.value = 100;
        await nextTick();
        // html2canvas draws with whatever font is loaded at capture time.
        await waitForPrintFont(printSettings.fontFamily);

        const pdf = new jsPDF({ orientation: 'p', unit: 'mm', format: 'a4', compress: true });
        const captureOptions = sheetCaptureOptions(`#${SHEET_ID} .qp-document-body`);
        const cover = sheet.querySelector('.qp-print-cover');
        if (printSettings.showCover && cover) {
            const canvas = await html2canvas(cover, captureOptions);
            pdf.addImage(canvas.toDataURL('image/jpeg', 0.92), 'JPEG', 0, 0, A4_WIDTH_MM, A4_HEIGHT_MM);
            pdf.addPage();
        }
        await addSheetPages(pdf, html2canvas, sheet.querySelector('.qp-document-body'), { captureOptions, breakSelector: BREAK_SELECTOR });
        pdf.save(`${documentTitle().replace(/\s+/g, '_')}.pdf`);
        ElMessage.success(t('pdf_downloaded_successfully') || 'PDF ready');
    } catch (e) {
        console.error('Quote PDF export failed:', e);
        ElMessage.error(t('failed_to_export_pdf') || 'PDF export failed');
    } finally {
        zoomLevel.value = prevZoom;
        isExportingPdf.value = false;
    }
};

// Ctrl/Cmd+P in the preview prints the quote, not the admin page behind it.
const onKeydown = (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'p') {
        event.preventDefault();
        print();
    }
};
watch(() => props.modelValue, (open) => {
    if (open) window.addEventListener('keydown', onKeydown);
    else window.removeEventListener('keydown', onKeydown);
}, { immediate: true });
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<style scoped>
/* Mirrors the invoice print preview (views/admin/sales/Invoices.vue) so the
   two documents look like one family. Scoped attributes travel with the
   sheet's markup into the print iframe, where these rules are copied too. */

/* ── Dialog chrome ── */
.qp-print-dialog :global(.el-dialog__header) { margin-right: 0; padding: 14px 20px; border-bottom: 1px solid #e2e8f0; background: #ffffff; }
.qp-print-dialog :global(.el-dialog__body) { padding: 0; background: #f1f5f9; }

.qp-dialog-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
.qp-dh-left { display: flex; align-items: center; gap: 12px; }
.qp-dh-icon {
    width: 36px; height: 36px; border-radius: 8px; flex-shrink: 0;
    background: linear-gradient(135deg, #1e40af, #3b82f6); color: #ffffff;
    display: flex; align-items: center; justify-content: center; font-size: 15px;
    box-shadow: 0 2px 6px rgba(30, 64, 175, 0.25);
}
.qp-dh-info { display: flex; flex-direction: column; gap: 2px; }
.qp-dh-main-line { display: flex; align-items: center; gap: 8px; }
.qp-dh-title-text { font-size: 14.5px; font-weight: 800; color: #0f172a; }
.qp-dh-num {
    background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; padding: 1px 8px; border-radius: 6px;
    font-family: 'JetBrains Mono', monospace; font-size: 12.5px; font-weight: 800;
}
.qp-dh-subline { display: flex; align-items: center; gap: 10px; font-size: 11px; color: #64748b; }
.qp-dh-customer-crumb { color: #334155; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; }
.qp-dh-badges { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.qp-status-pill, .qp-validity-pill {
    display: inline-flex; align-items: center; gap: 5px; padding: 2px 10px; border-radius: 20px; font-size: 11.5px; font-weight: 700;
}
.qp-status-pill.pill-draft { background: #f1f5f9; color: #475569; }
.qp-status-pill.pill-sent { background: #e0f2fe; color: #0369a1; }
.qp-status-pill.pill-accepted { background: #dcfce7; color: #15803d; }
.qp-status-pill.pill-rejected { background: #fee2e2; color: #b91c1c; }
.qp-status-pill.pill-expired { background: #fef3c7; color: #b45309; }
.qp-validity-pill.v-ok { background: #dcfce7; color: #15803d; }
.qp-validity-pill.v-soon { background: #fef3c7; color: #b45309; }
.qp-validity-pill.v-lapsed { background: #fee2e2; color: #b91c1c; }
.qp-dh-count {
    font-size: 11.5px; color: #475569; background: #f8fafc; border: 1px solid #e2e8f0; padding: 2px 8px; border-radius: 6px;
    display: inline-flex; align-items: center; gap: 5px;
}
.qp-dh-pages-est {
    font-size: 11px; color: #0284c7; background: #f0f9ff; border: 1px solid #bae6fd; padding: 2px 8px; border-radius: 6px;
    display: inline-flex; align-items: center; gap: 5px; font-weight: 600;
}
.qp-dh-actions { display: flex; align-items: center; gap: 8px; }
.qp-fs-toggle-btn { font-size: 11.5px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }

.qp-lapsed-banner {
    display: flex; align-items: center; gap: 8px; padding: 8px 18px; font-size: 12px; font-weight: 600;
    background: #fef2f2; color: #b91c1c; border-bottom: 1px solid #fecaca;
}

/* ── Paper Simulation Stage (Screen Only) ── */
.qp-paper-stage { padding: 24px; background: #e2e8f0; display: flex; justify-content: center; overflow: auto; min-height: 480px; box-sizing: border-box; }
.qp-paper-stage.is-fullscreen { min-height: calc(100vh - 165px); background: #cbd5e1; }
.qp-paper-viewport { display: flex; flex-direction: column; align-items: center; width: 100%; transform-origin: top center; }

/* ── Printable Sheet ── */
.qp-printable-sheet {
    --qp-primary: #1e3a8a;
    --qp-header-bg: #1e293b;
    --qp-accent-subtle: #eff6ff;
    --qp-border-color: #cbd5e1;
    --qp-highlight-bg: #f8fafc;

    position: relative;
    width: 100%;
    max-width: 860px;
    margin: 0 auto;
    font-family: var(--qp-font, 'Cairo', 'Noto Sans Arabic', Tahoma, sans-serif);
    /* Arabic needs room above and below for dots and diacritics. */
    line-height: 1.5;
    text-rendering: optimizeLegibility;
    -webkit-font-smoothing: antialiased;
    font-kerning: normal;
}
.qp-printable-sheet :deep(.print-document-header) { font-family: inherit; }
.qp-printable-sheet .qp-item-name,
.qp-printable-sheet .qp-item-desc,
.qp-printable-sheet .qp-notes-text,
.qp-printable-sheet .qp-cm-item span { unicode-bidi: plaintext; }
.qp-printable-sheet .qp-print-table td,
.qp-printable-sheet .qp-totals-table td { font-variant-numeric: tabular-nums; }

.qp-printable-sheet.theme-emerald { --qp-primary: #047857; --qp-header-bg: #064e3b; --qp-accent-subtle: #ecfdf5; --qp-border-color: #a7f3d0; --qp-highlight-bg: #f0fdf4; }
.qp-printable-sheet.theme-charcoal { --qp-primary: #18181b; --qp-header-bg: #09090b; --qp-accent-subtle: #f4f4f5; --qp-border-color: #d4d4d8; --qp-highlight-bg: #fafafa; }
.qp-printable-sheet.theme-indigo { --qp-primary: #4338ca; --qp-header-bg: #312e81; --qp-accent-subtle: #eef2ff; --qp-border-color: #c7d2fe; --qp-highlight-bg: #f5f3ff; }

.qp-print-cover {
    width: 100%; max-width: 860px; aspect-ratio: 1 / 1.4142; border-radius: 8px; overflow: hidden; margin-bottom: 24px;
    box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.16), 0 0 0 1px rgba(15, 23, 42, 0.05); background: #0f172a;
}
.qp-print-cover img { width: 100%; height: 100%; object-fit: cover; display: block; }

.qp-document-body {
    position: relative; width: 100%; background: #ffffff; color: #0f172a; padding: 18px 24px; box-sizing: border-box;
    border: 1px solid var(--qp-border-color); border-radius: 8px;
    box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.12), 0 0 0 1px rgba(15, 23, 42, 0.04);
}

/* Watermark */
.qp-sheet-watermark { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; pointer-events: none; user-select: none; z-index: 1; overflow: hidden; }
.qp-sheet-watermark span { transform: rotate(-28deg); font-size: 54px; font-weight: 900; line-height: 1.4; padding: 12px 30px; border-radius: 12px; text-align: center; white-space: nowrap; }
.qp-sheet-watermark.wm-draft span { color: rgba(220, 38, 38, 0.08); border: 4px dashed rgba(220, 38, 38, 0.14); }
.qp-sheet-watermark.wm-approved span { color: rgba(22, 163, 74, 0.08); border: 4px solid rgba(22, 163, 74, 0.14); }
.qp-sheet-watermark.wm-paid span { color: rgba(13, 148, 136, 0.08); border: 4px solid rgba(13, 148, 136, 0.14); }
.qp-sheet-watermark.wm-official span { color: rgba(30, 58, 138, 0.08); border: 4px solid rgba(30, 58, 138, 0.14); }

/* Ribbon */
.qp-doc-ribbon {
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px 16px; padding: 7px 12px;
    background: var(--qp-highlight-bg); border: 1px solid var(--qp-border-color); border-radius: 6px; margin-bottom: 12px;
}
.qp-ribbon-left, .qp-ribbon-right { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.qp-ribbon-right { gap: 12px; }
.qp-ribbon-doc-type { font-size: 11px; font-weight: 800; color: var(--qp-primary); display: inline-flex; align-items: center; gap: 5px; }
.qp-ribbon-status, .qp-ribbon-validity { display: inline-flex; align-items: center; gap: 4px; padding: 1px 8px; border-radius: 4px; font-size: 10px; font-weight: 700; }
.qp-ribbon-status.status-draft { background: #f1f5f9; color: #475569; }
.qp-ribbon-status.status-sent { background: #e0f2fe; color: #0369a1; }
.qp-ribbon-status.status-accepted { background: #dcfce7; color: #15803d; }
.qp-ribbon-status.status-rejected { background: #fee2e2; color: #b91c1c; }
.qp-ribbon-status.status-expired { background: #fef3c7; color: #b45309; }
.qp-ribbon-validity.v-ok { background: #dcfce7; color: #15803d; }
.qp-ribbon-validity.v-soon { background: #fef3c7; color: #b45309; }
.qp-ribbon-validity.v-lapsed { background: #fee2e2; color: #b91c1c; }
.qp-ribbon-order { font-size: 10.5px; color: #475569; background: #eff6ff; padding: 1px 6px; border-radius: 4px; border: 1px solid #bfdbfe; }
.qp-ribbon-item { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; }
.qp-ribbon-item .lbl { color: #64748b; font-weight: 600; }
.qp-ribbon-item .val { color: #0f172a; font-weight: 700; }
.qp-ribbon-qr { display: inline-flex; align-items: center; gap: 5px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 4px; padding: 2px 6px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04); }
.qp-ribbon-qr-img { width: 28px; height: 28px; display: block; image-rendering: pixelated; }
.qp-ribbon-qr-label { font-size: 8px; font-weight: 700; color: #475569; }

/* Meta cards */
.qp-print-meta-grid { display: grid; grid-template-columns: 1.15fr 1fr; gap: 12px; margin-bottom: 12px; }
.qp-meta-card { border: 1px solid #e2e8f0; border-radius: 8px; background: #ffffff; overflow: hidden; }
.qp-meta-card-header { display: flex; align-items: center; gap: 8px; padding: 6px 12px; background: var(--qp-highlight-bg); border-bottom: 1px solid #e2e8f0; }
.qp-card-header-icon {
    width: 20px; height: 20px; border-radius: 4px; background: var(--qp-accent-subtle); color: var(--qp-primary);
    display: flex; align-items: center; justify-content: center; font-size: 10px; flex-shrink: 0;
}
.qp-meta-card-title { font-size: 11.5px; font-weight: 800; color: var(--qp-primary); }
.qp-meta-card-body { padding: 8px 12px; font-size: 11px; }
.qp-customer-primary { margin-bottom: 6px; display: flex; align-items: baseline; gap: 6px; flex-wrap: wrap; }
.qp-customer-name { font-size: 13.5px; color: #0f172a; font-weight: 800; }
.qp-customer-company { font-size: 11.5px; color: #475569; font-weight: 600; background: #f1f5f9; padding: 1px 6px; border-radius: 4px; }
.qp-customer-meta-list { display: flex; flex-direction: column; gap: 4px; color: #334155; }
.qp-cm-item { display: flex; align-items: center; gap: 6px; line-height: 1.35; }
.qp-cm-item i { color: #64748b; width: 13px; text-align: center; font-size: 9.5px; flex-shrink: 0; }
.qp-cm-tax, .qp-cm-tax i { color: var(--qp-primary); font-weight: 600; }
.qp-details-meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 12px; }
.qp-dm-item { display: flex; flex-direction: column; gap: 2px; }
.qp-dm-label { font-size: 9.5px; color: #64748b; font-weight: 600; }
.qp-dm-val { font-size: 11px; font-weight: 700; color: #0f172a; }
.qp-dm-val.is-lapsed { color: #b91c1c; }
.qp-mono { font-family: 'JetBrains Mono', monospace; color: var(--qp-primary); font-weight: 800; }
.qp-state-pill { display: inline-block; padding: 1px 6px; border-radius: 4px; font-size: 9.5px; font-weight: 700; width: fit-content; }
.qp-state-pill.p-draft { background: #f1f5f9; color: #475569; }
.qp-state-pill.p-sent { background: #e0f2fe; color: #0369a1; }
.qp-state-pill.p-accepted { background: #dcfce7; color: #15803d; }
.qp-state-pill.p-rejected { background: #fee2e2; color: #b91c1c; }
.qp-state-pill.p-expired { background: #fef3c7; color: #b45309; }

/* Items table */
.qp-print-table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 12px; }
.qp-print-table thead th {
    background: var(--qp-header-bg); color: #ffffff; border: 1px solid var(--qp-header-bg);
    padding: 6px 8px; text-align: start; font-weight: 800; font-size: 10.5px; line-height: 1.4;
}
.qp-print-table tbody td { border: 1px solid #e2e8f0; padding: 5px 8px; text-align: start; vertical-align: middle; }
.qp-print-table tbody tr:nth-child(even) { background: #fbfcfd; }
.qp-print-table .c-center { text-align: center; }
.qp-print-table .c-end { text-align: end; }
.qp-row-idx { color: #64748b; font-weight: 700; font-size: 10px; }
.qp-print-img-cell { padding: 2px !important; }
.qp-item-name { font-weight: 700; color: #0f172a; line-height: 1.45; }
.qp-item-name-en { font-size: 9px; color: #64748b; direction: ltr; text-align: start; }
.qp-item-desc { font-size: 9px; color: #64748b; margin-top: 2px; }
.qp-sku-cell code { background: #f1f5f9; padding: 1px 4px; border-radius: 4px; font-size: 9.5px; color: #475569; font-family: 'JetBrains Mono', monospace; }
.qp-qty-cell { font-weight: 800; }
.qp-qty-num { font-size: 12px; color: #0f172a; }
.qp-unit-label { font-size: 9px; font-weight: normal; color: #64748b; margin-inline-start: 2px; }
.qp-price-cell { color: #334155; font-weight: 600; }
.qp-discount-cell { color: #dc2626; font-weight: 600; }
.qp-tax-cell { color: #64748b; }
.qp-total-cell { color: var(--qp-primary); font-weight: 800; }
.qp-table-summary-row td { background: #f8fafc; border-top: 2px solid #cbd5e1; padding: 6px 10px; font-size: 11px; }
.qp-sum-label { color: #334155; }
.qp-sum-label i { color: var(--qp-primary); }
.qp-sum-count { color: var(--qp-primary); font-weight: 800; margin-inline: 2px; }
.qp-sum-total { text-align: end !important; font-size: 12px; color: var(--qp-primary); }
.qp-sum-subtext { font-size: 10px; color: #64748b; margin-inline-end: 4px; }

/* Summary & notes */
.qp-print-summary-row { display: flex; justify-content: space-between; gap: 16px; margin-bottom: 16px; }
.qp-print-notes-col { flex: 1; display: flex; flex-direction: column; gap: 6px; }
.qp-print-notes-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 7px 10px; font-size: 10.5px; }
.qp-notes-header { display: flex; align-items: center; gap: 5px; margin-bottom: 3px; color: #475569; font-size: 10.5px; }
.qp-notes-header i { color: var(--qp-primary); font-size: 10px; }
.qp-notes-text { margin: 0; color: #0f172a; white-space: pre-wrap; line-height: 1.7; }
.qp-print-legal-notice {
    font-size: 9.5px; color: #64748b; line-height: 1.6; padding: 4px 6px; background: #fafafa; border-radius: 4px;
    display: flex; align-items: baseline; gap: 5px;
}
.qp-print-legal-notice i { color: #94a3b8; font-size: 9px; }
.qp-print-totals-col { width: 320px; flex-shrink: 0; }
.qp-totals-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px; }
.qp-totals-header {
    font-size: 11.5px; font-weight: 800; color: var(--qp-primary); display: flex; align-items: center; gap: 6px;
    margin-bottom: 8px; border-bottom: 1px dashed #cbd5e1; padding-bottom: 6px;
}
.qp-totals-table { width: 100%; border-collapse: collapse; font-size: 11.5px; }
.qp-totals-table td { padding: 4px; color: #334155; }
.qp-totals-table td.val { text-align: end; font-weight: 700; }
.qp-totals-table td.discount-val { color: #dc2626; }
.qp-totals-table .grand-total-row td { font-weight: 900; font-size: 14px; color: var(--qp-primary); border-top: 2px solid var(--qp-primary); padding-top: 6px; }
.qp-totals-table .grand-val { font-size: 14.5px; }
.qp-totals-table .valid-row td { color: #15803d; font-weight: 700; font-size: 11px; }
.qp-totals-table .valid-row.is-lapsed td { color: #dc2626; }

/* Signatures */
.qp-print-signatures { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 16px; padding-top: 10px; border-top: 1px dashed #cbd5e1; }
.sig-card { display: flex; flex-direction: column; align-items: center; text-align: center; }
.sig-title { font-size: 10.5px; font-weight: 700; color: #334155; margin-bottom: 20px; display: inline-flex; align-items: center; gap: 4px; }
.sig-title i { color: var(--qp-primary); font-size: 9.5px; }
.sig-name { font-size: 9.5px; color: var(--qp-primary); font-weight: 700; margin-bottom: 3px; }
.sig-name-title { display: block; font-size: 9px; color: #64748b; }
.sig-line { width: 80%; border-bottom: 1px solid #94a3b8; margin-bottom: 4px; }
.sig-sub { font-size: 8.5px; color: #94a3b8; }
.sig-seal-box {
    width: 60px; height: 60px; border: 2px dashed #94a3b8; border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-size: 8.5px; color: #94a3b8; text-align: center; overflow: hidden;
}
.sig-seal-box.has-stamp { border: none; background: transparent; }
.sig-stamp-img { max-width: 100%; max-height: 100%; object-fit: contain; }
.sig-digital-img { max-width: 85px; max-height: 35px; object-fit: contain; margin-top: 4px; }

/* Footer */
.qp-print-footer {
    margin-top: 14px; padding-top: 6px; border-top: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between;
    gap: 12px; font-size: 9px; color: #94a3b8; line-height: 1.3;
}
.qp-footer-meta { display: flex; align-items: center; gap: 12px; direction: ltr; }

/* Compact density */
.qp-printable-sheet.density-compact .qp-document-body { padding: 10px 14px; }
.qp-printable-sheet.density-compact .qp-doc-ribbon { padding: 4px 8px; margin-bottom: 8px; }
.qp-printable-sheet.density-compact .qp-print-meta-grid { gap: 8px; margin-bottom: 8px; }
.qp-printable-sheet.density-compact .qp-meta-card-body { padding: 5px 8px; }
.qp-printable-sheet.density-compact .qp-print-table tbody td { padding: 3px 6px; }
.qp-printable-sheet.density-compact .qp-print-table thead th { padding: 4px 6px; }
.qp-printable-sheet.density-compact .qp-item-name { font-size: 10px; }
.qp-printable-sheet.density-compact .qp-print-summary-row { margin-bottom: 10px; }
.qp-printable-sheet.density-compact .qp-print-signatures { margin-top: 10px; padding-top: 6px; }
.qp-printable-sheet.density-compact .sig-title { margin-bottom: 12px; }

@media (max-width: 767px) {
    .qp-paper-stage { padding: 12px 6px; }
}
</style>
