<template>
    <el-dialog
        v-model="visible"
        :title="$t('rc_print_preview')"
        width="920px"
        class="rp-print-dialog"
        append-to-body
        destroy-on-close
        top="3vh"
    >
        <!-- Print settings (screen only) -->
        <div class="rp-toolbar">
            <div class="rp-toolbar-group">
                <span class="rp-toolbar-label">{{ $t('po_header_style') }}:</span>
                <el-radio-group v-model="settings.headerStyle" size="small">
                    <el-radio-button label="official">{{ $t('po_header_official') }}</el-radio-button>
                    <el-radio-button label="banner">{{ $t('po_header_banner') }}</el-radio-button>
                    <el-radio-button label="compact">{{ $t('po_header_compact') }}</el-radio-button>
                </el-radio-group>
            </div>
            <div class="rp-toolbar-group">
                <el-checkbox v-model="settings.showLogo">{{ $t('po_show_logo') }}</el-checkbox>
                <el-checkbox v-model="settings.showContacts">{{ $t('po_show_contacts') }}</el-checkbox>
                <el-checkbox v-model="settings.showPrices">{{ $t('rc_print_show_prices') }}</el-checkbox>
                <el-checkbox v-model="settings.showSignatures">{{ $t('po_show_signatures') }}</el-checkbox>
            </div>
        </div>

        <div v-loading="loading" class="rp-stage">
            <div v-if="receipt" :id="SHEET_ID" class="rp-sheet" dir="rtl" lang="ar">
                <PrintDocumentHeader
                    :header-style="settings.headerStyle"
                    :show-logo="settings.showLogo"
                    :show-contacts="settings.showContacts"
                    :show-meta="false"
                    :title="$t('rc_print_title')"
                    subtitle="GOODS RECEIVED NOTE"
                    :document-number="receipt.receipt_number"
                    banner-src="/Header.jpeg"
                />

                <!-- Supplier & receipt details -->
                <div class="rp-meta-grid">
                    <div class="rp-meta-card">
                        <div class="rp-meta-head"><i class="fas fa-truck-moving"></i> {{ $t('supplier_details') }}</div>
                        <div class="rp-meta-body">
                            <strong class="rp-supplier-name">{{ receipt.supplier?.name || '—' }}</strong>
                            <span v-if="receipt.supplier?.company" class="rp-muted">({{ receipt.supplier.company }})</span>
                            <div v-if="receipt.supplier?.phone" class="rp-meta-line">
                                <i class="fas fa-phone-alt"></i> <span dir="ltr">{{ receipt.supplier.phone }}</span>
                            </div>
                            <div v-if="receipt.supplier?.address" class="rp-meta-line">
                                <i class="fas fa-map-marker-alt"></i> <span>{{ receipt.supplier.address }}</span>
                            </div>
                            <div v-if="receipt.supplier?.tax_number" class="rp-meta-line">
                                <i class="fas fa-certificate"></i> <span>{{ $t('tax_number') }}: {{ receipt.supplier.tax_number }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="rp-meta-card">
                        <div class="rp-meta-head"><i class="fas fa-truck-loading"></i> {{ $t('rc_print_title') }}</div>
                        <div class="rp-meta-body rp-meta-pairs">
                            <span class="rp-label">{{ $t('receipt_number') }}:</span>
                            <strong class="rp-mono" dir="ltr">{{ receipt.receipt_number }}</strong>
                            <span class="rp-label">{{ $t('receipt_date_label') }}:</span>
                            <span dir="ltr">{{ formatDate(receipt.receipt_date) }}</span>
                            <span class="rp-label">{{ $t('linked_to_purchase_order_label') }}:</span>
                            <strong v-if="receipt.purchase_order" class="rp-mono" dir="ltr">#{{ receipt.purchase_order.order_number }}</strong>
                            <span v-else>{{ $t('direct_receipt') }}</span>
                            <template v-if="receipt.warehouse">
                                <span class="rp-label">{{ $t('rc_print_receiving_warehouse') }}:</span>
                                <span><i class="fas fa-warehouse rp-muted"></i> {{ receipt.warehouse.name }}</span>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Lines -->
                <table class="rp-table">
                    <thead>
                        <tr>
                            <th class="c-center" style="width: 30px;">#</th>
                            <th>{{ $t('product') }}</th>
                            <th style="width: 110px;">SKU</th>
                            <th class="c-center" style="width: 80px;">{{ $t('quantity_received') }}</th>
                            <th v-if="settings.showPrices" class="c-end" style="width: 110px;">{{ $t('purchase_price') }}</th>
                            <th v-if="settings.showPrices" class="c-end" style="width: 120px;">{{ $t('grand_total') }}</th>
                            <th v-if="!settings.showPrices" class="c-center" style="width: 60px;">{{ $t('rc_print_checked') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, idx) in items" :key="item.id || idx" class="rp-item-row">
                            <td class="c-center rp-idx">{{ idx + 1 }}</td>
                            <td class="rp-item-cell">
                                <div class="rp-item-name">{{ item.product?.name_ar || item.product?.name || item.description || '—' }}</div>
                                <div v-if="item.product?.name_en" class="rp-item-en">{{ item.product.name_en }}</div>
                                <div v-if="item.product_variant_id" class="rp-item-variant">
                                    <VariantChip :label="variantLabelOf(item.variant) || item.description" />
                                </div>
                            </td>
                            <td class="rp-sku"><code>{{ item.variant?.sku || item.product?.sku || '—' }}</code></td>
                            <td class="c-center rp-qty-cell">
                                <span class="rp-qty-num">{{ item.quantity }}</span>
                                <span v-if="unitOf(item)" class="rp-unit">{{ unitOf(item) }}</span>
                            </td>
                            <td v-if="settings.showPrices" class="c-end rp-amount" dir="ltr">
                                {{ money(item.unit_price) }}
                                <span v-if="unitOf(item)" class="rp-per-unit">/ {{ unitOf(item) }}</span>
                            </td>
                            <td v-if="settings.showPrices" class="c-end rp-amount rp-line-total" dir="ltr">
                                {{ money(num(item.quantity) * num(item.unit_price)) }}
                            </td>
                            <td v-if="!settings.showPrices" class="c-center"><span class="rp-check-box"></span></td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="rp-summary-row">
                            <td colspan="4" class="rp-sum-label">
                                <i class="fas fa-layer-group"></i>
                                <span>{{ $t('qt_print_lines_total') }}:</span>
                                <strong class="rp-sum-count">{{ items.length }}</strong>
                                ·
                                <span>{{ $t('qt_print_total_qty') }}:</span>
                                <strong class="rp-sum-count">{{ quantitySummary }}</strong>
                            </td>
                            <td v-if="settings.showPrices" colspan="2" class="rp-sum-total" dir="ltr">
                                <span class="rp-sum-sub">{{ $t('rc_goods_value') }}:</span>
                                <strong>{{ money(receipt.goods_total ?? goodsTotal) }}</strong>
                            </td>
                            <td v-else class="rp-sum-total"></td>
                        </tr>
                    </tfoot>
                </table>

                <!-- Notes & totals -->
                <div class="rp-closing">
                    <div class="rp-notes-col">
                        <div v-if="receipt.notes" class="rp-notes">
                            <strong><i class="fas fa-clipboard-list"></i> {{ $t('notes') }}:</strong>
                            <p>{{ receipt.notes }}</p>
                        </div>
                        <div v-if="!settings.showPrices" class="rp-unpriced">
                            <i class="fas fa-shield-halved"></i> {{ $t('rc_print_unpriced') }}
                        </div>
                    </div>
                    <table v-if="settings.showPrices" class="rp-totals">
                        <tr>
                            <td>{{ $t('rc_goods_value') }}:</td>
                            <td class="val" dir="ltr">{{ money(receipt.goods_total ?? goodsTotal) }}</td>
                        </tr>
                        <tr v-if="num(receipt.discount) > 0">
                            <td>
                                {{ $t('discount') }}
                                <span v-if="num(receipt.discount_percent) > 0">({{ receipt.discount_percent }}%)</span>:
                            </td>
                            <td class="val rp-discount" dir="ltr">− {{ money(receipt.discount) }}</td>
                        </tr>
                        <tr v-if="num(receipt.tax_amount) > 0">
                            <td>{{ $t('tax_label') }}:</td>
                            <td class="val" dir="ltr">+ {{ money(receipt.tax_amount) }}</td>
                        </tr>
                        <tr class="rp-grand">
                            <td>{{ $t('grand_total_label') }}:</td>
                            <td class="val" dir="ltr">{{ money(receipt.total_amount) }}</td>
                        </tr>
                        <tr>
                            <td>{{ $t('rc_paid') }}:</td>
                            <td class="val" dir="ltr">{{ money(receipt.paid_amount) }}</td>
                        </tr>
                        <tr :class="num(receipt.due_amount) >= 0.01 ? 'rp-due' : 'rp-settled'">
                            <td>{{ $t('rc_due') }}:</td>
                            <td class="val" dir="ltr">{{ num(receipt.due_amount) >= 0.01 ? money(receipt.due_amount) : $t('fully_paid') }}</td>
                        </tr>
                    </table>
                </div>

                <!-- Signatures -->
                <div v-if="settings.showSignatures" class="rp-signatures">
                    <div class="rp-sig">
                        <span class="rp-sig-title">{{ $t('rc_print_received_by') }}</span>
                        <div class="rp-sig-line"></div>
                    </div>
                    <div class="rp-sig">
                        <span class="rp-sig-title">{{ $t('rc_print_delivered_by') }}</span>
                        <div class="rp-sig-line"></div>
                    </div>
                    <div class="rp-sig">
                        <span class="rp-sig-title">{{ $t('approved_by') }}</span>
                        <div class="rp-sig-line"></div>
                    </div>
                    <div class="rp-sig">
                        <span class="rp-sig-title">{{ $t('company_seal') }}</span>
                        <div class="rp-seal-box"></div>
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <el-button @click="visible = false">{{ $t('cancel') }}</el-button>
            <el-button type="primary" :disabled="!receipt" :loading="printing" @click="print">
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
import { purchaseReceiptsApi } from '@/api/purchaseReceipts';
import { formatMoney } from '@/utils/currency';
import { variantLabelOf } from '@/utils/productPick';

/**
 * A goods received note for one purchase receipt: what arrived, from whom,
 * into which warehouse — priced for accounts, or unpriced for the warehouse.
 */
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    receiptId: { type: [Number, String], default: null },
});
const emit = defineEmits(['update:modelValue']);

const { t } = useI18n();

const SHEET_ID = 'purchase-receipt-printable-doc';
const IFRAME_ID = 'rp-print-hidden-iframe';

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

const receipt = ref(null);
const loading = ref(false);
const printing = ref(false);

const num = (value) => {
    const n = Number(value);
    return Number.isFinite(n) ? n : 0;
};
const money = (value) => formatMoney(value);
const formatDate = (value) => (value ? String(value).slice(0, 10) : '—');

const items = computed(() => receipt.value?.items || []);
const goodsTotal = computed(() => items.value.reduce((sum, item) => sum + num(item.quantity) * num(item.unit_price), 0));

// The unit a line was received in, as the supplier's paperwork reads it.
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

watch(() => [props.modelValue, props.receiptId], async ([open, id]) => {
    if (!open || !id) return;
    receipt.value = null;
    loading.value = true;
    try {
        const res = await purchaseReceiptsApi.getById(id);
        receipt.value = res.data.data;
    } catch (e) {
        ElMessage.error(t('failed_to_load_receipt_details'));
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
        const title = escapeHtml(`${t('rc_print_title')} ${receipt.value?.receipt_number || ''}`);
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
.rp-toolbar {
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
.rp-toolbar-group { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 14px; }
.rp-toolbar-label { font-size: 0.82rem; font-weight: 600; color: #475569; }

.rp-stage { min-height: 300px; background: #f1f5f9; padding: 16px; border-radius: 8px; overflow-x: auto; }

/* ── The sheet ── */
.rp-sheet {
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
.rp-muted { color: #64748b; }
.rp-mono { font-family: ui-monospace, monospace; font-weight: 700; }

.rp-meta-grid { display: grid; grid-template-columns: 1.2fr 1fr; gap: 12px; margin: 12px 0; }
.rp-meta-card { border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; break-inside: avoid; }
.rp-meta-head { background: #eff6ff; color: #1e3a8a; font-weight: 800; font-size: 11px; padding: 6px 10px; display: flex; align-items: center; gap: 6px; }
.rp-meta-body { padding: 8px 10px; font-size: 11px; }
.rp-supplier-name { font-size: 13px; margin-inline-end: 4px; }
.rp-meta-line { display: flex; align-items: center; gap: 6px; color: #334155; margin-top: 3px; }
.rp-meta-line i { color: #94a3b8; width: 12px; }
.rp-meta-pairs { display: grid; grid-template-columns: auto 1fr; gap: 4px 10px; align-items: center; }
.rp-label { color: #64748b; font-weight: 600; }

/* ── Lines ── */
.rp-table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 12px; }
.rp-table thead { display: table-header-group; }
.rp-table thead th { background: #1e3a8a; color: #ffffff; border: 1px solid #1e3a8a; padding: 6px 8px; text-align: right; font-weight: 800; font-size: 10.5px; }
.rp-table tbody td { border: 1px solid #e2e8f0; border-inline-color: #f1f5f9; padding: 6px 8px; text-align: right; vertical-align: middle; font-variant-numeric: tabular-nums; }
.rp-table tbody tr:nth-child(even) { background: #f8fafc; }
.rp-table .c-center { text-align: center; }
.rp-table .c-end { text-align: left; }
/* Each row reads as one unit: a clear rule under it, none of it split across pages. */
.rp-table tbody tr.rp-item-row td { border-bottom-color: #cbd5e1; }
.rp-table tbody tr.rp-item-row { break-inside: avoid; page-break-inside: avoid; }
.rp-idx { color: #64748b; font-weight: 700; font-size: 10px; }
/* Long product names wrap inside their column instead of stretching the table. */
.rp-item-cell { overflow-wrap: anywhere; word-break: break-word; unicode-bidi: plaintext; }
.rp-item-name { font-weight: 700; font-size: 11.5px; line-height: 1.45; }
.rp-item-en { font-size: 9px; color: #64748b; direction: ltr; text-align: right; }
.rp-item-variant { font-size: 9.5px; color: #0369a1; margin-top: 2px; }
.rp-sku code { background: #f1f5f9; padding: 1px 4px; border-radius: 4px; font-size: 9.5px; color: #475569; font-family: 'JetBrains Mono', monospace; }
.rp-qty-cell { font-weight: 800; background: #f8fafc; }
.rp-qty-num { display: block; font-size: 13px; line-height: 1.2; }
.rp-unit { display: block; font-size: 9px; font-weight: 600; color: #64748b; line-height: 1.2; }
.rp-amount { white-space: nowrap; color: #334155; font-weight: 600; }
.rp-per-unit { display: block; font-size: 8.5px; font-weight: 500; color: #94a3b8; }
.rp-line-total { color: #1e3a8a; font-weight: 800; }
.rp-check-box { display: inline-block; width: 16px; height: 16px; border: 1.5px solid #64748b; border-radius: 3px; }

.rp-summary-row td { background: #f8fafc; border: 1px solid #e2e8f0; border-top: 2px solid #cbd5e1; padding: 6px 10px; font-size: 11px; break-before: avoid; }
.rp-sum-label { color: #334155; }
.rp-sum-label i, .rp-sum-count { color: #1e3a8a; }
.rp-sum-count { font-weight: 800; margin-inline: 2px; }
.rp-sum-total { text-align: left; font-size: 12px; color: #1e3a8a; white-space: nowrap; }
.rp-sum-sub { font-size: 10px; color: #64748b; margin-inline-end: 4px; }

/* ── Notes & totals ── */
.rp-closing { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; break-inside: avoid; }
.rp-notes-col { flex: 1; min-width: 0; }
.rp-notes { border: 1px dashed #cbd5e1; border-radius: 6px; padding: 8px 10px; font-size: 10.5px; background: #fffbeb; }
.rp-notes p { margin: 4px 0 0; white-space: pre-line; unicode-bidi: plaintext; }
.rp-unpriced { margin-top: 8px; font-size: 10.5px; font-weight: 700; color: #b45309; }
.rp-totals { width: 290px; border-collapse: collapse; font-size: 11px; }
.rp-totals td { padding: 5px 8px; border-bottom: 1px solid #f1f5f9; }
.rp-totals .val { text-align: left; font-weight: 700; white-space: nowrap; font-variant-numeric: tabular-nums; }
.rp-discount { color: #dc2626; }
.rp-grand td { background: #1e3a8a; color: #ffffff; font-weight: 800; font-size: 12px; }
.rp-due td { color: #b91c1c; font-weight: 700; }
.rp-settled td { color: #15803d; font-weight: 700; }

/* ── Signatures ── */
.rp-signatures { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-top: 22px; padding-top: 10px; border-top: 1px solid #e2e8f0; break-inside: avoid; }
.rp-sig { display: flex; flex-direction: column; align-items: center; text-align: center; }
.rp-sig-title { font-size: 10px; font-weight: 700; color: #334155; margin-bottom: 26px; }
.rp-sig-line { width: 85%; border-bottom: 1px solid #94a3b8; }
.rp-seal-box { width: 70px; height: 50px; border: 1px dashed #94a3b8; border-radius: 50%; }

@media (max-width: 768px) {
    .rp-meta-grid { grid-template-columns: 1fr; }
    .rp-closing { flex-direction: column; }
    .rp-totals { width: 100%; }
    .rp-signatures { grid-template-columns: repeat(2, 1fr); }
}
</style>
