<template>
    <el-dialog
        v-model="visible"
        :title="$t('pret_print_preview')"
        width="920px"
        class="pr-print-dialog"
        append-to-body
        destroy-on-close
        top="3vh"
    >
        <!-- Print settings (screen only) -->
        <div class="pr-toolbar">
            <div class="pr-toolbar-group">
                <span class="pr-toolbar-label">{{ $t('po_header_style') }}:</span>
                <el-radio-group v-model="settings.headerStyle" size="small">
                    <el-radio-button label="official">{{ $t('po_header_official') }}</el-radio-button>
                    <el-radio-button label="banner">{{ $t('po_header_banner') }}</el-radio-button>
                    <el-radio-button label="compact">{{ $t('po_header_compact') }}</el-radio-button>
                </el-radio-group>
            </div>
            <div class="pr-toolbar-group">
                <el-checkbox v-model="settings.showLogo">{{ $t('po_show_logo') }}</el-checkbox>
                <el-checkbox v-model="settings.showContacts">{{ $t('po_show_contacts') }}</el-checkbox>
                <el-checkbox v-model="settings.showPrices">{{ $t('rc_print_show_prices') }}</el-checkbox>
                <el-checkbox v-model="settings.showSignatures">{{ $t('po_show_signatures') }}</el-checkbox>
            </div>
        </div>

        <div v-loading="loading" class="pr-stage">
            <div v-if="ret" :id="SHEET_ID" class="pr-sheet" dir="rtl" lang="ar">
                <PrintDocumentHeader
                    :header-style="settings.headerStyle"
                    :show-logo="settings.showLogo"
                    :show-contacts="settings.showContacts"
                    :show-meta="false"
                    :title="$t('pret_print_title')"
                    subtitle="PURCHASE RETURN NOTE"
                    :document-number="ret.return_number"
                    banner-src="/Header.jpeg"
                />

                <!-- Supplier & return details -->
                <div class="pr-meta-grid">
                    <div class="pr-meta-card">
                        <div class="pr-meta-head"><i class="fas fa-truck-moving"></i> {{ $t('supplier_details') }}</div>
                        <div class="pr-meta-body">
                            <strong class="pr-supplier-name">{{ ret.supplier?.name || '—' }}</strong>
                            <span v-if="ret.supplier?.company" class="pr-muted">({{ ret.supplier.company }})</span>
                            <div v-if="ret.supplier?.phone" class="pr-meta-line">
                                <i class="fas fa-phone-alt"></i> <span dir="ltr">{{ ret.supplier.phone }}</span>
                            </div>
                            <div v-if="ret.supplier?.address" class="pr-meta-line">
                                <i class="fas fa-map-marker-alt"></i> <span>{{ ret.supplier.address }}</span>
                            </div>
                            <div v-if="ret.supplier?.tax_number" class="pr-meta-line">
                                <i class="fas fa-certificate"></i> <span>{{ $t('tax_number') }}: {{ ret.supplier.tax_number }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="pr-meta-card">
                        <div class="pr-meta-head"><i class="fas fa-rotate-left"></i> {{ $t('pret_print_title') }}</div>
                        <div class="pr-meta-body pr-meta-pairs">
                            <span class="pr-label">{{ $t('reference') }}:</span>
                            <strong class="pr-mono" dir="ltr">{{ ret.return_number }}</strong>
                            <span class="pr-label">{{ $t('return_date') }}:</span>
                            <span dir="ltr">{{ formatDate(ret.return_date) }}</span>
                            <span class="pr-label">{{ $t('pret_from_delivery') }}:</span>
                            <strong v-if="ret.purchase_receipt" class="pr-mono" dir="ltr">{{ ret.purchase_receipt.receipt_number }}</strong>
                            <span v-else>{{ $t('pret_no_delivery') }}</span>
                            <template v-if="ret.warehouse">
                                <span class="pr-label">{{ $t('pret_print_from_warehouse') }}:</span>
                                <span><i class="fas fa-warehouse pr-muted"></i> {{ ret.warehouse.name }}</span>
                            </template>
                            <template v-if="ret.reason">
                                <span class="pr-label">{{ $t('reason') }}:</span>
                                <span class="pr-reason">{{ ret.reason }}</span>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Lines -->
                <table class="pr-table">
                    <thead>
                        <tr>
                            <th class="c-center" style="width: 30px;">#</th>
                            <th>{{ $t('product') }}</th>
                            <th style="width: 110px;">SKU</th>
                            <th class="c-center" style="width: 80px;">{{ $t('quantity') }}</th>
                            <th v-if="settings.showPrices" class="c-end" style="width: 110px;">{{ $t('credit_per_unit') }}</th>
                            <th v-if="settings.showPrices" class="c-end" style="width: 120px;">{{ $t('pret_line_credit') }}</th>
                            <th v-if="!settings.showPrices" class="c-center" style="width: 60px;">{{ $t('rc_print_checked') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, idx) in items" :key="item.id || idx" class="pr-item-row">
                            <td class="c-center pr-idx">{{ idx + 1 }}</td>
                            <td class="pr-item-cell">
                                <div class="pr-item-name">{{ item.product?.name_ar || item.product?.name_en || `#${item.product_id}` }}</div>
                                <div v-if="item.product?.name_en && item.product?.name_ar" class="pr-item-en">{{ item.product.name_en }}</div>
                                <div v-if="item.product_variant_id" class="pr-item-variant">
                                    <VariantChip :label="variantLabelOf(item.variant)" />
                                </div>
                            </td>
                            <td class="pr-sku"><code>{{ item.variant?.sku || item.product?.sku || '—' }}</code></td>
                            <td class="c-center pr-qty-cell">
                                <span class="pr-qty-num">{{ item.quantity }}</span>
                                <span v-if="unitOf(item)" class="pr-unit">{{ unitOf(item) }}</span>
                            </td>
                            <td v-if="settings.showPrices" class="c-end pr-amount" dir="ltr">
                                {{ money(item.unit_price) }}
                                <span v-if="unitOf(item)" class="pr-per-unit">/ {{ unitOf(item) }}</span>
                            </td>
                            <td v-if="settings.showPrices" class="c-end pr-amount pr-line-total" dir="ltr">
                                {{ money(num(item.quantity) * num(item.unit_price)) }}
                            </td>
                            <td v-if="!settings.showPrices" class="c-center"><span class="pr-check-box"></span></td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="pr-summary-row">
                            <td colspan="4" class="pr-sum-label">
                                <i class="fas fa-layer-group"></i>
                                <span>{{ $t('qt_print_lines_total') }}:</span>
                                <strong class="pr-sum-count">{{ items.length }}</strong>
                                ·
                                <span>{{ $t('qt_print_total_qty') }}:</span>
                                <strong class="pr-sum-count">{{ quantitySummary }}</strong>
                            </td>
                            <td v-if="settings.showPrices" colspan="2" class="pr-sum-total" dir="ltr">
                                <strong>{{ money(linesCredit) }}</strong>
                            </td>
                            <td v-else class="pr-sum-total"></td>
                        </tr>
                    </tfoot>
                </table>

                <!-- Notes & totals -->
                <div class="pr-closing">
                    <div class="pr-notes-col">
                        <div v-if="ret.notes" class="pr-notes">
                            <strong><i class="fas fa-clipboard-list"></i> {{ $t('notes') }}:</strong>
                            <p>{{ ret.notes }}</p>
                        </div>
                        <div v-if="!settings.showPrices" class="pr-unpriced">
                            <i class="fas fa-shield-halved"></i> {{ $t('rc_print_unpriced') }}
                        </div>
                    </div>
                    <!-- The header credit, so an agreed lump sum is what the supplier sees. -->
                    <table v-if="settings.showPrices" class="pr-totals">
                        <tr>
                            <td>{{ $t('credit_amount') }}:</td>
                            <td class="val" dir="ltr">{{ money(ret.credit_amount) }}</td>
                        </tr>
                        <tr v-if="num(ret.tax_amount) > 0">
                            <td>{{ $t('tax_returned') }}:</td>
                            <td class="val" dir="ltr">+ {{ money(ret.tax_amount) }}</td>
                        </tr>
                        <tr class="pr-grand">
                            <td>{{ $t('pret_supplier_owed_less') }}:</td>
                            <td class="val" dir="ltr">{{ money(num(ret.credit_amount) + num(ret.tax_amount)) }}</td>
                        </tr>
                    </table>
                </div>

                <!-- Signatures -->
                <div v-if="settings.showSignatures" class="pr-signatures">
                    <div class="pr-sig">
                        <span class="pr-sig-title">{{ $t('pret_print_handed_by') }}</span>
                        <div v-if="ret.creator?.name" class="pr-sig-name">{{ ret.creator.name }}</div>
                        <div class="pr-sig-line"></div>
                    </div>
                    <div class="pr-sig">
                        <span class="pr-sig-title">{{ $t('pret_print_taken_by') }}</span>
                        <div class="pr-sig-line"></div>
                    </div>
                    <div class="pr-sig">
                        <span class="pr-sig-title">{{ $t('approved_by') }}</span>
                        <div class="pr-sig-line"></div>
                    </div>
                    <div class="pr-sig">
                        <span class="pr-sig-title">{{ $t('company_seal') }}</span>
                        <div class="pr-seal-box"></div>
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <el-button @click="visible = false">{{ $t('cancel') }}</el-button>
            <el-button type="primary" :disabled="!ret" :loading="printing" @click="print">
                <i class="fas fa-print"></i>&nbsp;{{ $t('print_now') }}
            </el-button>
        </template>
    </el-dialog>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import PrintDocumentHeader from '@/components/admin/PrintDocumentHeader.vue';
import VariantChip from '@/components/admin/products/VariantChip.vue';
import { purchaseReturnsApi } from '@/api/purchaseReturns';
import { formatMoney } from '@/utils/currency';
import { apiErrorMessage } from '@/utils/sales';
import { variantLabelOf } from '@/utils/productPick';

/**
 * A purchase return note for one return: what went back to the supplier,
 * from which warehouse, and what it takes off what we owe — priced for the
 * supplier, or unpriced for the warehouse handing the goods over.
 */
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    returnId: { type: [Number, String], default: null },
});
const emit = defineEmits(['update:modelValue']);

const { t } = useI18n();

const SHEET_ID = 'purchase-return-printable-doc';
const IFRAME_ID = 'pr-print-hidden-iframe';

const visible = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const settings = reactive({
    headerStyle: 'official',
    showLogo: true,
    showContacts: true,
    showPrices: true,
    showSignatures: true,
});

const ret = ref(null);
const loading = ref(false);
const printing = ref(false);

const num = (value) => {
    const n = Number(value);
    return Number.isFinite(n) ? n : 0;
};
const money = (value) => formatMoney(value);
const formatDate = (value) => (value ? String(value).slice(0, 10) : '—');

const items = computed(() => ret.value?.items || []);
const linesCredit = computed(() => items.value.reduce((sum, item) => sum + num(item.quantity) * num(item.unit_price), 0));

// The unit a line went back in, as the supplier's paperwork reads it.
const unitOf = (item) => item?.unit_name || item?.product?.unit || '';

// Quantities only add up within one unit: "12 قطعة · 3 كرتونة", never "15 قطعة".
const quantitySummary = computed(() => {
    const totals = new Map();
    items.value.forEach((item) => {
        const unit = unitOf(item) || t('qt_print_pcs');
        totals.set(unit, (totals.get(unit) || 0) + num(item.quantity));
    });
    if (!totals.size) return '0';
    return [...totals].map(([unit, qty]) => `${qty} ${unit}`).join(' · ');
});

watch(() => [props.modelValue, props.returnId], async ([open, id]) => {
    if (!open || !id) return;
    ret.value = null;
    loading.value = true;
    try {
        const res = await purchaseReturnsApi.get(id);
        ret.value = res.data?.data || null;
    } catch (e) {
        ElMessage.error(apiErrorMessage(e, t('failed_to_load_report')));
        visible.value = false;
    } finally {
        loading.value = false;
    }
}, { immediate: true });

const PRINT_STYLES = `
    @page { size: A4 portrait; margin: 0 !important; }
    * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; box-sizing: border-box !important; }
    html, body { margin: 0 !important; padding: 0 !important; background: #fff !important; color: #0f172a !important; direction: rtl !important; height: auto !important; overflow: visible !important; }
    a[href]:after, abbr[title]:after { content: none !important; }
    .brand-domain-badge, .domain-badge { display: none !important; }
    /* A print stylesheet another screen left loaded may hide every element. */
    #${SHEET_ID}, #${SHEET_ID} * { visibility: visible !important; }
    #${SHEET_ID} { width: 100% !important; margin: 0 !important; padding: 10mm 12mm !important; border: none !important; border-radius: 0 !important; box-shadow: none !important; }
`;

const escapeHtml = (text) => String(text ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

// Print through an isolated iframe holding only the sheet.
const print = async () => {
    if (printing.value) return;
    await nextTick();
    const sheet = document.getElementById(SHEET_ID);
    if (!sheet) return;
    printing.value = true;
    try {
        document.getElementById(IFRAME_ID)?.remove();
        const iframe = document.createElement('iframe');
        iframe.id = IFRAME_ID;
        Object.assign(iframe.style, { position: 'fixed', left: '0', top: '0', width: '100vw', height: '100vh', border: '0', opacity: '0', pointerEvents: 'none', zIndex: '-9999' });
        document.body.appendChild(iframe);

        const styles = Array.from(document.querySelectorAll('link[rel="stylesheet"], style')).map((node) => node.outerHTML).join('\n');
        const title = escapeHtml(`${t('pret_print_title')} ${ret.value?.return_number || ''}`);
        const doc = iframe.contentWindow.document;
        doc.open();
        doc.write(`<!DOCTYPE html><html dir="rtl" lang="ar"><head><meta charset="utf-8">
            <base href="${window.location.origin}/"><title>${title}</title>
            ${styles}<style>${PRINT_STYLES}</style>
            </head><body>${sheet.outerHTML}</body></html>`);
        doc.close();

        // Images and webfonts have to arrive before the print dialog snapshots the page.
        const pending = Array.from(doc.images).filter((img) => !img.complete);
        await Promise.race([
            Promise.all(pending.map((img) => new Promise((resolve) => { img.onload = resolve; img.onerror = resolve; }))),
            new Promise((resolve) => setTimeout(resolve, 1500)),
        ]);
        await Promise.race([doc.fonts?.ready, new Promise((resolve) => setTimeout(resolve, 1500))]);

        iframe.contentWindow.focus();
        iframe.contentWindow.print();
        setTimeout(() => iframe.remove(), 60000);
    } finally {
        printing.value = false;
    }
};

onBeforeUnmount(() => document.getElementById(IFRAME_ID)?.remove());
</script>

<style scoped>
.pr-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px 18px;
    padding: 10px 14px;
    margin-bottom: 14px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
}
.pr-toolbar-group { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 14px; }
.pr-toolbar-label { font-size: 0.82rem; font-weight: 600; color: #475569; }

.pr-stage { min-height: 300px; background: #f1f5f9; padding: 16px; border-radius: 8px; overflow-x: auto; }

/* ── The sheet ── */
.pr-sheet {
    width: 100%;
    max-width: 860px;
    margin: 0 auto;
    background: #ffffff;
    color: #0f172a;
    font-family: 'Cairo', 'Almarai', Tahoma, sans-serif;
    line-height: 1.5;
    direction: rtl;
    padding: 18px 24px;
    box-sizing: border-box;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.12);
}
.pr-muted { color: #64748b; }
.pr-mono { font-family: ui-monospace, monospace; font-weight: 700; }

.pr-meta-grid { display: grid; grid-template-columns: 1.2fr 1fr; gap: 12px; margin: 12px 0; }
.pr-meta-card { border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; break-inside: avoid; }
.pr-meta-head { background: #fef2f2; color: #991b1b; font-weight: 800; font-size: 11px; padding: 6px 10px; display: flex; align-items: center; gap: 6px; }
.pr-meta-body { padding: 8px 10px; font-size: 11px; }
.pr-supplier-name { font-size: 13px; margin-inline-end: 4px; }
.pr-meta-line { display: flex; align-items: center; gap: 6px; color: #334155; margin-top: 3px; }
.pr-meta-line i { color: #94a3b8; width: 12px; }
.pr-meta-pairs { display: grid; grid-template-columns: auto 1fr; gap: 4px 10px; align-items: center; }
.pr-label { color: #64748b; font-weight: 600; }
.pr-reason { unicode-bidi: plaintext; overflow-wrap: anywhere; }

/* ── Lines ── */
.pr-table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 12px; }
.pr-table thead { display: table-header-group; }
.pr-table thead th { background: #991b1b; color: #ffffff; border: 1px solid #991b1b; padding: 6px 8px; text-align: right; font-weight: 800; font-size: 10.5px; }
.pr-table tbody td { border: 1px solid #e2e8f0; border-inline-color: #f1f5f9; padding: 6px 8px; text-align: right; vertical-align: middle; font-variant-numeric: tabular-nums; }
.pr-table tbody tr:nth-child(even) { background: #f8fafc; }
.pr-table .c-center { text-align: center; }
.pr-table .c-end { text-align: left; }
/* Each row reads as one unit: a clear rule under it, none of it split across pages. */
.pr-table tbody tr.pr-item-row td { border-bottom-color: #cbd5e1; }
.pr-table tbody tr.pr-item-row { break-inside: avoid; page-break-inside: avoid; }
.pr-idx { color: #64748b; font-weight: 700; font-size: 10px; }
/* Long product names wrap inside their column instead of stretching the table. */
.pr-item-cell { overflow-wrap: anywhere; word-break: break-word; unicode-bidi: plaintext; }
.pr-item-name { font-weight: 700; font-size: 11.5px; line-height: 1.45; }
.pr-item-en { font-size: 9px; color: #64748b; direction: ltr; text-align: right; }
.pr-item-variant { font-size: 9.5px; color: #0369a1; margin-top: 2px; }
.pr-sku code { background: #f1f5f9; padding: 1px 4px; border-radius: 4px; font-size: 9.5px; color: #475569; font-family: 'JetBrains Mono', monospace; }
.pr-qty-cell { font-weight: 800; background: #f8fafc; }
.pr-qty-num { display: block; font-size: 13px; line-height: 1.2; }
.pr-unit { display: block; font-size: 9px; font-weight: 600; color: #64748b; line-height: 1.2; }
.pr-amount { white-space: nowrap; color: #334155; font-weight: 600; }
.pr-per-unit { display: block; font-size: 8.5px; font-weight: 500; color: #94a3b8; }
.pr-line-total { color: #991b1b; font-weight: 800; }
.pr-check-box { display: inline-block; width: 16px; height: 16px; border: 1.5px solid #64748b; border-radius: 3px; }

.pr-summary-row td { background: #f8fafc; border: 1px solid #e2e8f0; border-top: 2px solid #cbd5e1; padding: 6px 10px; font-size: 11px; break-before: avoid; }
.pr-sum-label { color: #334155; }
.pr-sum-label i, .pr-sum-count { color: #991b1b; }
.pr-sum-count { font-weight: 800; margin-inline: 2px; }
.pr-sum-total { text-align: left; font-size: 12px; color: #991b1b; white-space: nowrap; }

/* ── Notes & totals ── */
.pr-closing { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; break-inside: avoid; }
.pr-notes-col { flex: 1; min-width: 0; }
.pr-notes { border: 1px dashed #cbd5e1; border-radius: 6px; padding: 8px 10px; font-size: 10.5px; background: #fffbeb; }
.pr-notes p { margin: 4px 0 0; white-space: pre-line; unicode-bidi: plaintext; }
.pr-unpriced { margin-top: 8px; font-size: 10.5px; font-weight: 700; color: #b45309; }
.pr-totals { width: 300px; border-collapse: collapse; font-size: 11px; }
.pr-totals td { padding: 5px 8px; border-bottom: 1px solid #f1f5f9; }
.pr-totals .val { text-align: left; font-weight: 700; white-space: nowrap; font-variant-numeric: tabular-nums; }
.pr-grand td { background: #991b1b; color: #ffffff; font-weight: 800; font-size: 12px; }

/* ── Signatures ── */
.pr-signatures { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-top: 22px; padding-top: 10px; border-top: 1px solid #e2e8f0; break-inside: avoid; }
.pr-sig { display: flex; flex-direction: column; align-items: center; text-align: center; }
.pr-sig-title { font-size: 10px; font-weight: 700; color: #334155; margin-bottom: 26px; }
.pr-sig-name { font-size: 10px; color: #475569; margin-top: -20px; margin-bottom: 8px; }
.pr-sig-line { width: 85%; border-bottom: 1px solid #94a3b8; }
.pr-seal-box { width: 70px; height: 50px; border: 1px dashed #94a3b8; border-radius: 50%; }

@media (max-width: 768px) {
    .pr-meta-grid { grid-template-columns: 1fr; }
    .pr-closing { flex-direction: column; }
    .pr-totals { width: 100%; }
    .pr-signatures { grid-template-columns: repeat(2, 1fr); }
}
</style>
