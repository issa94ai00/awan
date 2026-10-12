<template>
    <el-dialog
        :model-value="modelValue"
        :width="fullscreen ? '100%' : '1040px'"
        :fullscreen="fullscreen"
        :top="fullscreen ? '0' : '2vh'"
        class="qp-dialog"
        destroy-on-close
        append-to-body
        @update:model-value="emit('update:modelValue', $event)"
    >
        <template #header>
            <div class="qp-head qp-screen-only">
                <div class="qp-head-main">
                    <span class="qp-head-icon"><i class="fas fa-print"></i></span>
                    <div>
                        <div class="qp-head-title">
                            {{ $t('qt_print_preview') }}
                            <strong v-if="quote" dir="ltr">{{ quote.quote_number }}</strong>
                        </div>
                        <div v-if="quote" class="qp-head-sub">
                            <span><i class="fas fa-calendar-day"></i> {{ formatDate(quote.created_at) }}</span>
                            <span><i class="fas fa-user"></i> {{ customerName(quote) }}</span>
                        </div>
                    </div>
                </div>
                <div v-if="quote" class="qp-head-badges">
                    <span class="qp-pill" :class="`s-${quote.status}`">
                        <i class="fas" :class="statusIcon(quote.status)"></i> {{ statusLabel(quote.status) }}
                    </span>
                    <span v-if="validity" class="qp-pill" :class="`v-${validity.tone}`">
                        <i class="fas fa-hourglass-half"></i> {{ validity.label }}
                    </span>
                    <span class="qp-pill">
                        <i class="fas fa-box-open"></i> {{ $t('qt_print_lines', { count: lineCount, qty: totalQuantity }) }}
                    </span>
                    <span class="qp-pill">
                        <i class="fas fa-file-lines"></i> {{ $t('qt_print_pages', { count: estimatedPages }, estimatedPages) }}
                    </span>
                </div>
                <el-button size="small" plain :type="fullscreen ? 'primary' : 'default'" @click="fullscreen = !fullscreen">
                    <i class="fas" :class="fullscreen ? 'fa-compress' : 'fa-expand'"></i>&nbsp;
                    {{ fullscreen ? $t('exit_fullscreen') : $t('fullscreen') }}
                </el-button>
            </div>
        </template>

        <el-alert
            v-if="quote && validity?.tone === 'lapsed'"
            class="qp-screen-only qp-warning"
            type="warning"
            :closable="false"
            show-icon
            :title="$t('qt_print_lapsed_warning')"
        />

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

        <div class="qp-stage" :class="{ 'is-fullscreen': fullscreen }" v-loading="loading" :element-loading-text="$t('loading')">
            <div class="qp-viewport" :style="zoomContainerStyle">
                <div
                    v-if="quote"
                    :id="SHEET_ID"
                    class="qp-sheet"
                    :dir="dir"
                    :lang="lang"
                    :style="sheetStyle"
                    :class="[`theme-${printSettings.theme}`, { 'density-compact': printSettings.density === 'compact' }]"
                >
                    <div v-if="printSettings.showCover" class="qp-cover">
                        <img :src="assetUrl(printSettings.coverImage, '/cover.jpeg')" alt="" />
                    </div>

                    <div class="qp-body">
                        <div v-if="watermarkLabel" class="qp-watermark" :class="`wm-${watermarkKey}`">
                            <span>{{ watermarkLabel }}</span>
                        </div>

                        <PrintDocumentHeader
                            :header-style="printSettings.headerStyle"
                            :show-logo="printSettings.showLogo"
                            :show-contacts="printSettings.showContacts"
                            :show-meta="false"
                            :title="$t('qt_print_heading')"
                            subtitle="PRICE QUOTATION"
                            :document-number="quote.quote_number"
                            :banner-src="assetUrl(printSettings.headerBanner, '/Header.jpeg')"
                        />

                        <!-- The three facts a customer looks for first. -->
                        <div class="qp-keyfacts">
                            <div class="qp-fact">
                                <span class="qp-fact-label">{{ $t('qt_print_number') }}</span>
                                <strong class="qp-mono" dir="ltr">{{ quote.quote_number }}</strong>
                            </div>
                            <div class="qp-fact">
                                <span class="qp-fact-label">{{ $t('qt_issue_date') }}</span>
                                <strong>{{ formatDate(quote.created_at) }}</strong>
                            </div>
                            <div class="qp-fact" :class="{ 'is-lapsed': validity?.tone === 'lapsed' }">
                                <span class="qp-fact-label">{{ $t('valid_until') }}</span>
                                <strong>{{ quote.valid_until ? formatDate(quote.valid_until) : $t('qt_no_expiry') }}</strong>
                                <small v-if="validity?.days > 0">{{ $t('qt_print_days_left', { count: validity.days }, validity.days) }}</small>
                            </div>
                            <div class="qp-fact qp-fact-total">
                                <span class="qp-fact-label">{{ $t('qt_print_amount') }}</span>
                                <strong dir="ltr">{{ formatCurrency(quote.total) }}</strong>
                            </div>
                            <div v-if="printSettings.showQrCode && qrDataUrl" class="qp-qr">
                                <img :src="qrDataUrl" alt="QR" />
                            </div>
                        </div>

                        <div v-if="printSettings.showCustomerInfo" class="qp-parties">
                            <section class="qp-card">
                                <h4><i class="fas fa-user-tie"></i> {{ $t('qt_print_prepared_for') }}</h4>
                                <strong class="qp-party-name">{{ customerName(quote) }}</strong>
                                <div v-if="quote.customer?.company" class="qp-muted">{{ quote.customer.company }}</div>
                                <div v-if="quote.customer?.phone" class="qp-line"><i class="fas fa-phone"></i><span dir="ltr">{{ quote.customer.phone }}</span></div>
                                <div v-if="quote.customer?.email" class="qp-line"><i class="fas fa-envelope"></i><span dir="ltr">{{ quote.customer.email }}</span></div>
                                <div v-if="quote.customer?.address" class="qp-line"><i class="fas fa-location-dot"></i><span>{{ quote.customer.address }}</span></div>
                                <div v-if="quote.customer?.tax_number" class="qp-line"><i class="fas fa-certificate"></i><span>{{ $t('qt_print_tax_number') }}: {{ quote.customer.tax_number }}</span></div>
                            </section>
                            <section class="qp-card">
                                <h4><i class="fas fa-building"></i> {{ $t('qt_print_prepared_by') }}</h4>
                                <strong class="qp-party-name">{{ companyName }}</strong>
                                <div v-if="quote.creator?.name" class="qp-line"><i class="fas fa-user"></i><span>{{ quote.creator.name }}</span></div>
                                <div v-if="companyPhone" class="qp-line"><i class="fas fa-phone"></i><span dir="ltr">{{ companyPhone }}</span></div>
                                <div v-if="companyEmail" class="qp-line"><i class="fas fa-envelope"></i><span dir="ltr">{{ companyEmail }}</span></div>
                            </section>
                        </div>

                        <table class="qp-table">
                            <thead>
                                <tr>
                                    <th class="c-idx">#</th>
                                    <th v-if="printSettings.showImages" class="c-img">{{ $t('qt_print_image') }}</th>
                                    <th>{{ $t('product') }}</th>
                                    <th v-if="printSettings.showSku" class="c-sku">SKU</th>
                                    <th class="c-qty">{{ $t('quantity') }}</th>
                                    <th class="c-num">{{ $t('unit_price') }}</th>
                                    <th v-if="hasLineDiscount" class="c-num">{{ $t('discount') }}</th>
                                    <th v-if="hasLineTax" class="c-num">{{ $t('tax') }}</th>
                                    <th class="c-num">{{ $t('total') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, i) in items" :key="item.id || i">
                                    <td class="c-idx">{{ i + 1 }}</td>
                                    <td v-if="printSettings.showImages" class="c-img">
                                        <EntityImage
                                            :src="resolveImageUrl(item.product?.image_main) || ''"
                                            type="product"
                                            :size="printSettings.density === 'compact' ? 30 : 40"
                                            :lazy="false"
                                        />
                                    </td>
                                    <td>
                                        <div class="qp-item-name">{{ itemName(item) }}</div>
                                        <div v-if="item.product?.name_en && lang === 'ar'" class="qp-item-sub" dir="ltr">{{ item.product.name_en }}</div>
                                        <div v-if="item.description && item.product && item.description !== itemName(item)" class="qp-item-sub">{{ item.description }}</div>
                                    </td>
                                    <td v-if="printSettings.showSku" class="c-sku"><code>{{ item.product?.sku || '—' }}</code></td>
                                    <td class="c-qty">
                                        <strong>{{ item.quantity }}</strong>
                                        <small v-if="item.product?.unit">{{ item.product.unit }}</small>
                                    </td>
                                    <td class="c-num" dir="ltr">{{ formatCurrency(item.unit_price) }}</td>
                                    <td v-if="hasLineDiscount" class="c-num qp-neg" dir="ltr">{{ toNum(item.discount) ? `−${formatCurrency(item.discount)}` : '—' }}</td>
                                    <td v-if="hasLineTax" class="c-num" dir="ltr">{{ toNum(item.tax) ? formatCurrency(item.tax) : '—' }}</td>
                                    <td class="c-num" dir="ltr"><strong>{{ formatCurrency(item.total) }}</strong></td>
                                </tr>
                                <tr v-if="!items.length">
                                    <td :colspan="columnCount" class="qp-empty">—</td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="qp-closing">
                            <div class="qp-summary">
                                <div class="qp-text-col">
                                    <div v-if="printSettings.showNotes && quote.terms" class="qp-note">
                                        <h4><i class="fas fa-file-signature"></i> {{ $t('qt_terms') }}</h4>
                                        <p>{{ quote.terms }}</p>
                                    </div>
                                    <div v-if="printSettings.showNotes && quote.notes" class="qp-note">
                                        <h4><i class="fas fa-clipboard-list"></i> {{ $t('notes') }}</h4>
                                        <p>{{ quote.notes }}</p>
                                    </div>
                                    <div class="qp-validity-note">
                                        <i class="fas fa-circle-info"></i>
                                        <span>{{ validityNotice }}</span>
                                    </div>
                                </div>

                                <div class="qp-totals">
                                    <div class="qp-trow">
                                        <span>{{ $t('subtotal') }}</span>
                                        <span dir="ltr">{{ formatCurrency(quote.subtotal) }}</span>
                                    </div>
                                    <div v-if="toNum(quote.discount)" class="qp-trow">
                                        <span>{{ $t('qt_extra_discount') }}</span>
                                        <span class="qp-neg" dir="ltr">−{{ formatCurrency(quote.discount) }}</span>
                                    </div>
                                    <div v-if="toNum(quote.tax)" class="qp-trow">
                                        <span>{{ $t('qt_extra_tax') }}</span>
                                        <span dir="ltr">+{{ formatCurrency(quote.tax) }}</span>
                                    </div>
                                    <div class="qp-trow qp-grand">
                                        <span>{{ $t('qt_print_grand_total') }}</span>
                                        <span dir="ltr">{{ formatCurrency(quote.total) }}</span>
                                    </div>
                                </div>
                            </div>

                            <div v-if="printSettings.showSignatures" class="qp-signatures">
                                <div class="qp-sig">
                                    <span class="qp-sig-title">{{ $t('qt_print_sig_company') }}</span>
                                    <span v-if="printSettings.authorizedPerson || quote.creator?.name" class="qp-sig-name">
                                        {{ printSettings.authorizedPerson || quote.creator?.name }}
                                        <small v-if="printSettings.authorizedPerson && printSettings.authorizedTitle">{{ printSettings.authorizedTitle }}</small>
                                    </span>
                                    <div class="qp-sig-space">
                                        <img v-if="signatureUrl" :src="signatureUrl" alt="" class="qp-sig-img" />
                                        <img v-if="stampUrl" :src="stampUrl" alt="" class="qp-stamp-img" />
                                    </div>
                                    <span class="qp-sig-line">{{ $t('qt_print_sig_hint') }}</span>
                                </div>
                                <div class="qp-sig">
                                    <span class="qp-sig-title">{{ $t('qt_print_sig_customer') }}</span>
                                    <span class="qp-sig-name">{{ customerName(quote) }}</span>
                                    <div class="qp-sig-space"></div>
                                    <span class="qp-sig-line">{{ $t('qt_print_sig_hint') }}</span>
                                </div>
                            </div>

                            <footer v-if="printSettings.showFooter" class="qp-footer">
                                <span>{{ $t('qt_print_footer') }}</span>
                                <span dir="ltr">{{ quote.quote_number }} · {{ printedAt }}</span>
                            </footer>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="qp-actions qp-screen-only">
                <span class="qp-shortcut"><kbd>Ctrl</kbd> + <kbd>P</kbd></span>
                <el-button @click="emit('update:modelValue', false)">{{ $t('cancel') }}</el-button>
                <el-button type="warning" plain :loading="isExportingPdf" :disabled="!quote || loading" @click="exportPdf">
                    <i class="fas fa-file-pdf"></i>&nbsp;{{ $t('download_pdf') }}
                </el-button>
                <el-button type="primary" :loading="isPrinting" :disabled="!quote || loading" @click="print">
                    <i class="fas fa-print"></i>&nbsp;{{ $t('print_now') }}
                </el-button>
            </div>
        </template>
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
import { resolveImageUrl } from '@/utils/productImages';
import { useSettingsStore } from '@/stores/settings';
import { customerName, formatCurrency, formatDate, statusIcon, statusLabel } from '@/utils/sales';
import { ensurePrintFont, printFontCssUrl, printFontStack, waitForPrintFont } from '@/utils/printFonts';
import { A4_HEIGHT_MM, A4_WIDTH_MM, addSheetPages, sheetCaptureOptions } from '@/utils/printPdf';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    /** A list row or a loaded quote; the full quote is fetched when lines are missing. */
    source: { type: Object, default: null },
});
const emit = defineEmits(['update:modelValue']);

const SHEET_ID = 'quote-printable-doc';
const IFRAME_ID = 'qp-print-iframe';

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
const sheetStyle = computed(() => ({ '--qp-font': printFontStack(printSettings.fontFamily) }));

const assetUrl = (path, fallback = '') => {
    const value = path || fallback;
    if (!value) return '';
    return value.startsWith('/') || value.startsWith('http') ? value : `/storage/${value}`;
};
const stampUrl = computed(() => assetUrl(printSettings.stampImage));
const signatureUrl = computed(() => assetUrl(printSettings.signatureImage));

const s = computed(() => settings.data || {});
const companyName = computed(() => (lang.value === 'ar' ? s.value.site_name : s.value.site_name_en || s.value.site_name) || '');
const companyPhone = computed(() => s.value.contact_phone || s.value.phone || '');
const companyEmail = computed(() => s.value.contact_email || s.value.email || '');

const items = computed(() => quote.value?.items || []);
const lineCount = computed(() => items.value.length);
const totalQuantity = computed(() => items.value.reduce((sum, item) => sum + toNum(item.quantity), 0));
const hasLineDiscount = computed(() => items.value.some((item) => toNum(item.discount)));
const hasLineTax = computed(() => items.value.some((item) => toNum(item.tax)));
const columnCount = computed(() => 5
    + (printSettings.showImages ? 1 : 0)
    + (printSettings.showSku ? 1 : 0)
    + (hasLineDiscount.value ? 1 : 0)
    + (hasLineTax.value ? 1 : 0));

const itemName = (item) => (lang.value === 'en' && item.product?.name_en) || item.product?.name_ar || item.description || '—';

const estimatedPages = computed(() => {
    const perPage = printSettings.density === 'compact' ? 16 : 10;
    const pages = Math.max(1, Math.ceil(lineCount.value / perPage));
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

const validityNotice = computed(() => (quote.value?.valid_until
    ? t('qt_print_valid_notice', { date: formatDate(quote.value.valid_until) })
    : t('qt_print_open_notice')));

// A draft goes out marked as one unless the toolbar picked a watermark.
const watermarkKey = computed(() => printSettings.watermark || (quote.value?.status === 'draft' ? 'draft' : ''));
const watermarkLabel = computed(() => (printSettings.watermark ? watermarkText.value : watermarkKey.value === 'draft' ? 'مسودة — DRAFT' : ''));

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
        // Keep what the list row gave us; better a partial preview than none.
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
const PRINT_CSS = `
    @page { size: A4 portrait; margin: 0; }
    * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    html, body { margin: 0 !important; padding: 0 !important; background: #fff !important; }
    #${SHEET_ID} { width: 100% !important; margin: 0 !important; box-shadow: none !important; border: 0 !important; }
    #${SHEET_ID} .qp-body { padding: 10mm 12mm !important; min-height: 0 !important; }
    #${SHEET_ID} .qp-cover { height: 297mm !important; break-after: page; page-break-after: always; }
    .qp-table thead { display: table-header-group; }
    .qp-table tr, .qp-keyfacts, .qp-parties, .qp-summary, .qp-signatures, .qp-footer { break-inside: avoid; page-break-inside: avoid; }
`;

const waitForImages = (doc) => new Promise((resolve) => {
    const pending = Array.from(doc.images || []).filter((img) => !img.complete);
    if (!pending.length) return setTimeout(resolve, 150);
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
        Object.assign(iframe.style, { position: 'fixed', inset: '0', width: '100vw', height: '100vh', border: '0', opacity: '0', pointerEvents: 'none', zIndex: '-1' });
        document.body.appendChild(iframe);

        const doc = iframe.contentWindow.document;
        const title = documentTitle().replace(/[<>&]/g, '');
        doc.open();
        doc.write(`<!DOCTYPE html><html dir="${dir.value}" lang="${lang.value}"><head><meta charset="utf-8">
            <base href="${window.location.origin}/"><title>${title}</title>${styles}
            <link rel="stylesheet" href="${printFontCssUrl(fontKey)}"><style>${PRINT_CSS}</style>
            </head><body>${sheet.outerHTML}</body></html>`);
        doc.close();

        await waitForImages(doc);
        // Printing before the webfont arrives prints a fallback face.
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
        await waitForPrintFont(printSettings.fontFamily);

        const pdf = new jsPDF({ orientation: 'p', unit: 'mm', format: 'a4', compress: true });
        const captureOptions = sheetCaptureOptions(`#${SHEET_ID} .qp-body`);
        const cover = sheet.querySelector('.qp-cover');
        if (printSettings.showCover && cover) {
            const canvas = await html2canvas(cover, captureOptions);
            pdf.addImage(canvas.toDataURL('image/jpeg', 0.92), 'JPEG', 0, 0, A4_WIDTH_MM, A4_HEIGHT_MM);
            pdf.addPage();
        }
        await addSheetPages(pdf, html2canvas, sheet.querySelector('.qp-body'), {
            captureOptions,
            breakSelector: '.qp-table thead, .qp-table tr, .qp-keyfacts, .qp-parties, .qp-summary, .qp-signatures, .qp-footer, .print-document-header',
        });
        pdf.save(`${documentTitle().replace(/\s+/g, '_')}.pdf`);
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

<style>
/* Not scoped: the sheet's markup is copied into the print iframe and the PDF
   capture, and these rules must travel with it unchanged. */
.qp-dialog .el-dialog__body { padding-top: 0; }
.qp-head { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; padding-inline-end: 28px; }
.qp-head-main { display: flex; align-items: center; gap: 10px; flex: 1 1 260px; min-width: 0; }
.qp-head-icon { width: 38px; height: 38px; border-radius: 10px; display: grid; place-items: center; background: #eff6ff; color: #1d4ed8; flex-shrink: 0; }
.qp-head-title { font-weight: 700; font-size: 15px; display: flex; gap: 8px; align-items: baseline; flex-wrap: wrap; }
.qp-head-title strong { font-family: 'JetBrains Mono', monospace; color: #1e3a8a; }
.qp-head-sub { display: flex; gap: 14px; font-size: 12px; color: #64748b; margin-top: 2px; flex-wrap: wrap; }
.qp-head-sub i { margin-inline-end: 4px; }
.qp-head-badges { display: flex; gap: 6px; flex-wrap: wrap; }
.qp-pill { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 999px; font-size: 12px; background: #f1f5f9; color: #334155; white-space: nowrap; }
.qp-pill.s-draft { background: #f1f5f9; color: #475569; }
.qp-pill.s-sent { background: #e0f2fe; color: #0369a1; }
.qp-pill.s-accepted { background: #dcfce7; color: #15803d; }
.qp-pill.s-rejected, .qp-pill.v-lapsed { background: #fee2e2; color: #b91c1c; }
.qp-pill.s-expired { background: #fef3c7; color: #b45309; }
.qp-pill.v-soon { background: #fef3c7; color: #b45309; }
.qp-pill.v-ok { background: #ecfdf5; color: #047857; }
.qp-warning { margin-bottom: 10px; }

.qp-stage { background: #e2e8f0; border-radius: 10px; padding: 24px 12px; max-height: 68vh; overflow: auto; min-height: 240px; }
.qp-stage.is-fullscreen { max-height: calc(100vh - 230px); }
.qp-viewport { width: max-content; margin: 0 auto; }

.qp-actions { display: flex; align-items: center; gap: 8px; justify-content: flex-end; flex-wrap: wrap; }
.qp-shortcut { margin-inline-end: auto; font-size: 12px; color: #94a3b8; }
.qp-shortcut kbd { border: 1px solid #cbd5e1; border-bottom-width: 2px; border-radius: 4px; padding: 0 5px; font-family: inherit; background: #fff; }

/* ── The A4 sheet ─────────────────────────────────────────────────────── */
.qp-sheet {
    --qp-accent: #1e3a8a; --qp-accent-soft: #eff6ff; --qp-ink: #0f172a; --qp-muted: #64748b; --qp-line: #e2e8f0;
    width: 210mm; background: #fff; color: var(--qp-ink); font-family: var(--qp-font, 'Cairo', Tahoma, sans-serif);
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.18); font-size: 12px; line-height: 1.55;
}
.qp-sheet.theme-emerald { --qp-accent: #047857; --qp-accent-soft: #ecfdf5; }
.qp-sheet.theme-charcoal { --qp-accent: #334155; --qp-accent-soft: #f1f5f9; }
.qp-sheet.theme-indigo { --qp-accent: #4338ca; --qp-accent-soft: #eef2ff; }
.qp-cover img { width: 100%; height: 297mm; object-fit: cover; display: block; }
.qp-body { position: relative; padding: 12mm 13mm; min-height: 297mm; box-sizing: border-box; overflow: hidden; }
.density-compact .qp-body { padding: 8mm 10mm; font-size: 11px; }
.qp-mono { font-family: 'JetBrains Mono', monospace; }
.qp-muted { color: var(--qp-muted); }
.qp-neg { color: #b91c1c; }

.qp-watermark { position: absolute; inset: 0; display: grid; place-items: center; pointer-events: none; z-index: 0; }
.qp-watermark span { transform: rotate(-30deg); font-size: 64px; font-weight: 900; color: rgba(100, 116, 139, 0.1); white-space: nowrap; border: 6px solid rgba(100, 116, 139, 0.1); padding: 8px 28px; border-radius: 16px; }
.qp-watermark.wm-draft span { color: rgba(220, 38, 38, 0.09); border-color: rgba(220, 38, 38, 0.09); }
.qp-body > * { position: relative; z-index: 1; }

.qp-keyfacts { display: flex; align-items: stretch; gap: 8px; margin: 14px 0; }
.qp-fact { flex: 1; border: 1px solid var(--qp-line); border-radius: 8px; padding: 8px 10px; display: flex; flex-direction: column; gap: 2px; background: #fff; }
.qp-fact-label { font-size: 10px; color: var(--qp-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.02em; }
.qp-fact strong { font-size: 13px; }
.qp-fact small { font-size: 10px; color: #047857; }
.qp-fact.is-lapsed strong, .qp-fact.is-lapsed small { color: #b91c1c; }
.qp-fact-total { background: var(--qp-accent); border-color: var(--qp-accent); color: #fff; }
.qp-fact-total .qp-fact-label { color: rgba(255, 255, 255, 0.8); }
.qp-fact-total strong { font-size: 15px; font-weight: 800; }
.qp-qr { display: flex; align-items: center; }
.qp-qr img { width: 60px; height: 60px; border: 1px solid var(--qp-line); border-radius: 6px; padding: 2px; }

.qp-parties { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 14px; }
.qp-card { border: 1px solid var(--qp-line); border-radius: 8px; padding: 10px 12px; }
.qp-card h4, .qp-note h4 { margin: 0 0 6px; font-size: 11px; color: var(--qp-accent); font-weight: 700; display: flex; gap: 6px; align-items: center; }
.qp-party-name { display: block; font-size: 14px; margin-bottom: 2px; }
.qp-line { display: flex; gap: 6px; align-items: baseline; color: #334155; font-size: 11px; }
.qp-line i { color: var(--qp-muted); width: 12px; text-align: center; font-size: 10px; }

.qp-table { width: 100%; border-collapse: collapse; }
.qp-table th { background: var(--qp-accent); color: #fff; font-weight: 700; font-size: 11px; padding: 7px 8px; text-align: start; }
.qp-table th:first-child { border-start-start-radius: 6px; }
.qp-table th:last-child { border-start-end-radius: 6px; }
.qp-table td { padding: 7px 8px; border-bottom: 1px solid var(--qp-line); vertical-align: middle; }
.qp-table tbody tr:nth-child(even) td { background: #f8fafc; }
.density-compact .qp-table td { padding: 4px 6px; }
.qp-table .c-idx { width: 28px; text-align: center; color: var(--qp-muted); }
.qp-table .c-img { width: 50px; text-align: center; }
.qp-table .c-sku { width: 92px; }
.qp-table .c-sku code { font-family: 'JetBrains Mono', monospace; font-size: 10px; }
.qp-table .c-qty { width: 64px; text-align: center; }
.qp-table .c-qty small { display: block; font-size: 10px; color: var(--qp-muted); }
.qp-table .c-num { width: 96px; text-align: end; font-variant-numeric: tabular-nums; white-space: nowrap; }
.qp-item-name { font-weight: 600; }
.qp-item-sub { font-size: 10.5px; color: var(--qp-muted); }
.qp-empty { text-align: center; color: var(--qp-muted); }

.qp-summary { display: grid; grid-template-columns: 1fr 260px; gap: 16px; margin-top: 14px; align-items: start; }
.qp-note { border-inline-start: 3px solid var(--qp-accent); background: var(--qp-accent-soft); padding: 8px 12px; border-radius: 6px; margin-bottom: 8px; }
.qp-note p { margin: 0; white-space: pre-line; }
.qp-validity-note { display: flex; gap: 6px; font-size: 11px; color: var(--qp-muted); }
.qp-totals { border: 1px solid var(--qp-line); border-radius: 8px; overflow: hidden; }
.qp-trow { display: flex; justify-content: space-between; gap: 10px; padding: 6px 12px; font-variant-numeric: tabular-nums; }
.qp-trow + .qp-trow { border-top: 1px solid var(--qp-line); }
.qp-grand { background: var(--qp-accent); color: #fff; font-weight: 800; font-size: 14px; padding: 9px 12px; }

.qp-signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 22px; }
.qp-sig { display: flex; flex-direction: column; gap: 2px; }
.qp-sig-title { font-size: 11px; font-weight: 700; color: var(--qp-accent); }
.qp-sig-name { font-size: 12px; }
.qp-sig-name small { display: block; font-size: 10px; color: var(--qp-muted); }
.qp-sig-space { position: relative; height: 64px; }
.qp-sig-img { position: absolute; inset-inline-start: 0; bottom: 4px; max-height: 50px; max-width: 140px; object-fit: contain; }
.qp-stamp-img { position: absolute; inset-inline-start: 90px; bottom: 0; max-height: 64px; max-width: 90px; object-fit: contain; opacity: 0.9; }
.qp-sig-line { border-top: 1px dashed #94a3b8; padding-top: 3px; font-size: 10px; color: var(--qp-muted); }

.qp-footer { display: flex; justify-content: space-between; gap: 12px; margin-top: 18px; padding-top: 8px; border-top: 1px solid var(--qp-line); font-size: 10px; color: var(--qp-muted); }

@media (max-width: 767px) {
    .qp-stage { padding: 12px 6px; }
}
</style>
