<template>
    <el-dialog
        v-model="visible"
        :title="$t('cn_print_preview')"
        width="920px"
        class="cn-print-dialog"
        append-to-body
        destroy-on-close
        top="3vh"
    >
        <!-- Print settings (screen only) -->
        <div class="cn-toolbar">
            <div class="cn-toolbar-group">
                <span class="cn-toolbar-label">{{ $t('po_header_style') }}:</span>
                <el-radio-group v-model="settings.headerStyle" size="small">
                    <el-radio-button label="official">{{ $t('po_header_official') }}</el-radio-button>
                    <el-radio-button label="banner">{{ $t('po_header_banner') }}</el-radio-button>
                    <el-radio-button label="compact">{{ $t('po_header_compact') }}</el-radio-button>
                </el-radio-group>
            </div>
            <div class="cn-toolbar-group">
                <el-checkbox v-model="settings.showLogo">{{ $t('po_show_logo') }}</el-checkbox>
                <el-checkbox v-model="settings.showContacts">{{ $t('po_show_contacts') }}</el-checkbox>
                <el-checkbox v-model="settings.showSettlement">{{ $t('cn_print_show_settlement') }}</el-checkbox>
                <el-checkbox v-model="settings.showSignatures">{{ $t('po_show_signatures') }}</el-checkbox>
            </div>
        </div>

        <div v-loading="loading" class="cn-stage">
            <div v-if="note" :id="SHEET_ID" class="cn-sheet" dir="rtl" lang="ar">
                <!-- A cancelled note must not pass for a live one. -->
                <div v-if="isCancelled" class="cn-watermark"><span>{{ note.status_text }}</span></div>

                <PrintDocumentHeader
                    :header-style="settings.headerStyle"
                    :show-logo="settings.showLogo"
                    :show-contacts="settings.showContacts"
                    :show-meta="false"
                    :title="$t('cn_print_title')"
                    subtitle="CREDIT NOTE"
                    :document-number="note.credit_note_number"
                    banner-src="/Header.jpeg"
                />

                <!-- Customer & note details -->
                <div class="cn-meta-grid">
                    <div class="cn-meta-card">
                        <div class="cn-meta-head"><i class="fas fa-user-tie"></i> {{ $t('customer_info') }}</div>
                        <div class="cn-meta-body">
                            <strong class="cn-customer-name">{{ note.customer?.name || '—' }}</strong>
                            <span v-if="note.customer?.company" class="cn-muted">({{ note.customer.company }})</span>
                            <div v-if="note.customer?.phone" class="cn-meta-line">
                                <i class="fas fa-phone-alt"></i> <span dir="ltr">{{ note.customer.phone }}</span>
                            </div>
                            <div v-if="note.customer?.address" class="cn-meta-line">
                                <i class="fas fa-map-marker-alt"></i> <span>{{ note.customer.address }}</span>
                            </div>
                            <div v-if="note.customer?.tax_number" class="cn-meta-line">
                                <i class="fas fa-certificate"></i> <span>{{ $t('tax_number') }}: {{ note.customer.tax_number }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="cn-meta-card">
                        <div class="cn-meta-head"><i class="fas fa-file-circle-minus"></i> {{ $t('cn_print_title') }}</div>
                        <div class="cn-meta-body cn-meta-pairs">
                            <span class="cn-label">{{ $t('note_number') }}:</span>
                            <strong class="cn-mono" dir="ltr">{{ note.credit_note_number }}</strong>
                            <span class="cn-label">{{ $t('date') }}:</span>
                            <span dir="ltr">{{ formatDate(note.issue_date || note.created_at) }}</span>
                            <template v-if="note.invoice">
                                <span class="cn-label">{{ $t('invoice_number') }}:</span>
                                <strong class="cn-mono" dir="ltr">{{ note.invoice.invoice_number }}</strong>
                            </template>
                            <template v-if="note.rma_request">
                                <span class="cn-label">{{ $t('cn_print_return_ref') }}:</span>
                                <strong class="cn-mono" dir="ltr">{{ note.rma_request.rma_number }}</strong>
                            </template>
                            <span class="cn-label">{{ $t('status') }}:</span>
                            <span class="cn-status" :class="`st-${note.status}`">{{ note.status_text }}</span>
                        </div>
                    </div>
                </div>

                <div v-if="note.reason" class="cn-reason">
                    <strong>{{ $t('reason') }}:</strong> <span>{{ note.reason }}</span>
                </div>

                <!-- Lines -->
                <table class="cn-table">
                    <thead>
                        <tr>
                            <th class="c-center" style="width: 30px;">#</th>
                            <th>{{ $t('product') }}</th>
                            <th class="c-center" style="width: 76px;">{{ $t('quantity') }}</th>
                            <th class="c-end" style="width: 110px;">{{ $t('unit_price') }}</th>
                            <th class="c-end" style="width: 120px;">{{ $t('total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, idx) in items" :key="item.id || idx" class="cn-item-row">
                            <td class="c-center cn-idx">{{ idx + 1 }}</td>
                            <td class="cn-item-cell">
                                <div class="cn-item-name">{{ item.product?.name_ar || item.product?.name || item.description || '—' }}</div>
                                <div v-if="item.product?.name_en" class="cn-item-en">{{ item.product.name_en }}</div>
                                <div v-if="item.description && item.product && item.description !== item.product.name_ar" class="cn-item-desc">{{ item.description }}</div>
                                <code v-if="item.product?.sku" class="cn-sku">{{ item.product.sku }}</code>
                            </td>
                            <td class="c-center cn-qty-cell">
                                <span class="cn-qty-num">{{ item.quantity }}</span>
                                <span v-if="unitOf(item)" class="cn-unit">{{ unitOf(item) }}</span>
                            </td>
                            <td class="c-end cn-amount" dir="ltr">
                                {{ money(item.unit_price) }}
                                <span v-if="unitOf(item)" class="cn-per-unit">/ {{ unitOf(item) }}</span>
                            </td>
                            <td class="c-end cn-amount cn-line-total" dir="ltr">{{ money(lineTotal(item)) }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="cn-summary-row">
                            <td colspan="3" class="cn-sum-label">
                                <i class="fas fa-layer-group"></i>
                                <span>{{ $t('qt_print_lines_total') }}:</span>
                                <strong class="cn-sum-count">{{ items.length }}</strong>
                                ·
                                <span>{{ $t('qt_print_total_qty') }}:</span>
                                <strong class="cn-sum-count">{{ quantitySummary }}</strong>
                            </td>
                            <td colspan="2" class="cn-sum-total" dir="ltr">
                                <span class="cn-sum-sub">{{ $t('subtotal') }}:</span>
                                <strong>{{ money(note.subtotal) }}</strong>
                            </td>
                        </tr>
                    </tfoot>
                </table>

                <!-- Settlement & totals -->
                <div class="cn-closing">
                    <div class="cn-left-col">
                        <!-- Where the credit went: the trail the customer can check against. -->
                        <div v-if="settings.showSettlement" class="cn-settlement">
                            <div class="cn-settlement-head"><i class="fas fa-route"></i> {{ $t('cn_print_settlement') }}</div>
                            <div v-if="num(note.applied_to_invoice) > 0" class="cn-settle-row">
                                <span>{{ $t('deducted_from_invoice') }}</span>
                                <strong dir="ltr">{{ money(note.applied_to_invoice) }}</strong>
                            </div>
                            <div v-if="num(note.refunded_amount) > 0" class="cn-settle-row">
                                <span>{{ $t('refunded_in_cash') }}</span>
                                <strong dir="ltr">{{ money(note.refunded_amount) }}</strong>
                            </div>
                            <div v-if="num(note.store_credit_amount) > 0" class="cn-settle-row">
                                <span>{{ $t('credited_to_customer') }}</span>
                                <strong dir="ltr">{{ money(note.store_credit_amount) }}</strong>
                            </div>
                            <div v-if="!isCancelled" class="cn-settle-row" :class="num(note.open_amount) > 0.009 ? 'open' : 'closed'">
                                <span>{{ $t('not_settled_yet') }}</span>
                                <strong dir="ltr">{{ money(note.open_amount) }}</strong>
                            </div>
                        </div>
                        <div v-if="note.notes" class="cn-notes">
                            <strong><i class="fas fa-clipboard-list"></i> {{ $t('notes') }}:</strong>
                            <p>{{ note.notes }}</p>
                        </div>
                    </div>
                    <table class="cn-totals">
                        <tr>
                            <td>{{ $t('subtotal') }}:</td>
                            <td class="val" dir="ltr">{{ money(note.subtotal) }}</td>
                        </tr>
                        <tr v-if="num(note.tax) > 0">
                            <td>{{ $t('tax') }}:</td>
                            <td class="val" dir="ltr">{{ money(note.tax) }}</td>
                        </tr>
                        <tr class="cn-grand">
                            <td>{{ $t('cn_print_total_credit') }}:</td>
                            <td class="val" dir="ltr">{{ money(note.total) }}</td>
                        </tr>
                    </table>
                </div>

                <!-- Signatures -->
                <div v-if="settings.showSignatures" class="cn-signatures">
                    <div class="cn-sig">
                        <span class="cn-sig-title">{{ $t('spay_print_prepared_by') }}</span>
                        <div v-if="note.creator?.name" class="cn-sig-name">{{ note.creator.name }}</div>
                        <div class="cn-sig-line"></div>
                    </div>
                    <div class="cn-sig">
                        <span class="cn-sig-title">{{ $t('approved_by') }}</span>
                        <div class="cn-sig-line"></div>
                    </div>
                    <div class="cn-sig">
                        <span class="cn-sig-title">{{ $t('cn_print_customer_ack') }}</span>
                        <div class="cn-sig-line"></div>
                    </div>
                    <div class="cn-sig">
                        <span class="cn-sig-title">{{ $t('company_seal') }}</span>
                        <div class="cn-seal-box"></div>
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <el-button @click="visible = false">{{ $t('cancel') }}</el-button>
            <el-button type="primary" :disabled="!note" :loading="printing" @click="print">
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
import { creditNotesApi } from '@/api/creditNotes';
import { formatMoney } from '@/utils/currency';
import { apiErrorMessage } from '@/utils/sales';

/**
 * A credit note for the customer: what was credited and why, line by line,
 * and where the credit went — taken off an invoice, refunded, held on
 * account, or still open.
 */
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    creditNoteId: { type: [Number, String], default: null },
});
const emit = defineEmits(['update:modelValue']);

const { t } = useI18n();

const SHEET_ID = 'credit-note-printable-doc';
const IFRAME_ID = 'cn-print-hidden-iframe';

const visible = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const settings = reactive({
    headerStyle: 'official',
    showLogo: true,
    showContacts: true,
    showSettlement: true,
    showSignatures: true,
});

const note = ref(null);
const loading = ref(false);
const printing = ref(false);

const num = (value) => {
    const n = Number(value);
    return Number.isFinite(n) ? n : 0;
};
const money = (value) => formatMoney(value);
const formatDate = (value) => (value ? String(value).slice(0, 10) : '—');

const items = computed(() => note.value?.items || []);
const isCancelled = computed(() => note.value?.status === 'cancelled');
const lineTotal = (item) => (item.total !== null && item.total !== undefined ? num(item.total) : num(item.quantity) * num(item.unit_price));

// The unit a line was sold in, as the customer reads it.
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

watch(() => [props.modelValue, props.creditNoteId], async ([open, id]) => {
    if (!open || !id) return;
    note.value = null;
    loading.value = true;
    try {
        const res = await creditNotesApi.get(id);
        note.value = res.data?.data || null;
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
        const docTitle = escapeHtml(`${t('cn_print_title')} ${note.value?.credit_note_number || ''}`);
        const doc = iframe.contentWindow.document;
        doc.open();
        doc.write(`<!DOCTYPE html><html dir="rtl" lang="ar"><head><meta charset="utf-8">
            <base href="${window.location.origin}/"><title>${docTitle}</title>
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
.cn-toolbar {
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
.cn-toolbar-group { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 14px; }
.cn-toolbar-label { font-size: 0.82rem; font-weight: 600; color: #475569; }

.cn-stage { min-height: 300px; background: #f1f5f9; padding: 16px; border-radius: 8px; overflow-x: auto; }

/* ── The sheet ── */
.cn-sheet {
    position: relative;
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
.cn-muted { color: #64748b; }
.cn-mono { font-family: ui-monospace, monospace; font-weight: 700; }

.cn-watermark {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
    z-index: 2;
    overflow: hidden;
}
.cn-watermark span {
    transform: rotate(-24deg);
    font-size: 80px;
    font-weight: 900;
    color: rgba(185, 28, 28, 0.13);
    border: 7px solid rgba(185, 28, 28, 0.13);
    border-radius: 16px;
    padding: 4px 36px;
    white-space: nowrap;
}

.cn-meta-grid { display: grid; grid-template-columns: 1.2fr 1fr; gap: 12px; margin: 12px 0; }
.cn-meta-card { border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; break-inside: avoid; }
.cn-meta-head { background: #f5f3ff; color: #5b21b6; font-weight: 800; font-size: 11px; padding: 6px 10px; display: flex; align-items: center; gap: 6px; }
.cn-meta-body { padding: 8px 10px; font-size: 11px; }
.cn-customer-name { font-size: 13px; margin-inline-end: 4px; }
.cn-meta-line { display: flex; align-items: center; gap: 6px; color: #334155; margin-top: 3px; }
.cn-meta-line i { color: #94a3b8; width: 12px; }
.cn-meta-pairs { display: grid; grid-template-columns: auto 1fr; gap: 4px 10px; align-items: center; }
.cn-label { color: #64748b; font-weight: 600; }
.cn-status { font-weight: 700; }
.cn-status.st-issued { color: #1d4ed8; }
.cn-status.st-partially_applied { color: #b45309; }
.cn-status.st-applied { color: #15803d; }
.cn-status.st-cancelled { color: #b91c1c; }

.cn-reason {
    font-size: 11px;
    background: #f8fafc;
    border-inline-start: 3px solid #7c3aed;
    border-radius: 4px;
    padding: 6px 10px;
    margin-bottom: 12px;
    unicode-bidi: plaintext;
}

/* ── Lines ── */
.cn-table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 12px; }
.cn-table thead { display: table-header-group; }
.cn-table thead th { background: #5b21b6; color: #ffffff; border: 1px solid #5b21b6; padding: 6px 8px; text-align: right; font-weight: 800; font-size: 10.5px; }
.cn-table tbody td { border: 1px solid #e2e8f0; border-inline-color: #f1f5f9; padding: 6px 8px; text-align: right; vertical-align: middle; font-variant-numeric: tabular-nums; }
.cn-table tbody tr:nth-child(even) { background: #f8fafc; }
.cn-table .c-center { text-align: center; }
.cn-table .c-end { text-align: left; }
/* Each row reads as one unit: a clear rule under it, none of it split across pages. */
.cn-table tbody tr.cn-item-row td { border-bottom-color: #cbd5e1; }
.cn-table tbody tr.cn-item-row { break-inside: avoid; page-break-inside: avoid; }
.cn-idx { color: #64748b; font-weight: 700; font-size: 10px; }
/* Long product names wrap inside their column instead of stretching the table. */
.cn-item-cell { overflow-wrap: anywhere; word-break: break-word; unicode-bidi: plaintext; }
.cn-item-name { font-weight: 700; font-size: 11.5px; line-height: 1.45; }
.cn-item-en { font-size: 9px; color: #64748b; direction: ltr; text-align: right; }
.cn-item-desc { font-size: 9px; color: #64748b; margin-top: 2px; }
.cn-sku { display: inline-block; margin-top: 2px; background: #f1f5f9; padding: 1px 4px; border-radius: 4px; font-size: 9px; color: #475569; font-family: 'JetBrains Mono', monospace; }
.cn-qty-cell { font-weight: 800; background: #f8fafc; }
.cn-qty-num { display: block; font-size: 13px; line-height: 1.2; }
.cn-unit { display: block; font-size: 9px; font-weight: 600; color: #64748b; line-height: 1.2; }
.cn-amount { white-space: nowrap; color: #334155; font-weight: 600; }
.cn-per-unit { display: block; font-size: 8.5px; font-weight: 500; color: #94a3b8; }
.cn-line-total { color: #5b21b6; font-weight: 800; }

.cn-summary-row td { background: #f8fafc; border: 1px solid #e2e8f0; border-top: 2px solid #cbd5e1; padding: 6px 10px; font-size: 11px; break-before: avoid; }
.cn-sum-label { color: #334155; }
.cn-sum-label i, .cn-sum-count { color: #5b21b6; }
.cn-sum-count { font-weight: 800; margin-inline: 2px; }
.cn-sum-total { text-align: left; font-size: 12px; color: #5b21b6; white-space: nowrap; }
.cn-sum-sub { font-size: 10px; color: #64748b; margin-inline-end: 4px; }

/* ── Settlement, notes & totals ── */
.cn-closing { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; break-inside: avoid; }
.cn-left-col { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 8px; }
.cn-settlement { border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; font-size: 10.5px; }
.cn-settlement-head { background: #f8fafc; font-weight: 800; color: #334155; padding: 5px 10px; display: flex; align-items: center; gap: 6px; }
.cn-settle-row { display: flex; justify-content: space-between; gap: 10px; padding: 4px 10px; border-top: 1px solid #f1f5f9; }
.cn-settle-row strong { font-variant-numeric: tabular-nums; white-space: nowrap; }
.cn-settle-row.open strong { color: #b45309; }
.cn-settle-row.closed strong { color: #15803d; }
.cn-notes { border: 1px dashed #cbd5e1; border-radius: 6px; padding: 8px 10px; font-size: 10.5px; background: #fffbeb; }
.cn-notes p { margin: 4px 0 0; white-space: pre-line; unicode-bidi: plaintext; }
.cn-totals { width: 280px; border-collapse: collapse; font-size: 11px; }
.cn-totals td { padding: 5px 8px; border-bottom: 1px solid #f1f5f9; }
.cn-totals .val { text-align: left; font-weight: 700; white-space: nowrap; font-variant-numeric: tabular-nums; }
.cn-grand td { background: #5b21b6; color: #ffffff; font-weight: 800; font-size: 12px; }

/* ── Signatures ── */
.cn-signatures { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-top: 22px; padding-top: 10px; border-top: 1px solid #e2e8f0; break-inside: avoid; }
.cn-sig { display: flex; flex-direction: column; align-items: center; text-align: center; }
.cn-sig-title { font-size: 10px; font-weight: 700; color: #334155; margin-bottom: 26px; }
.cn-sig-name { font-size: 10px; color: #475569; margin-top: -20px; margin-bottom: 8px; }
.cn-sig-line { width: 85%; border-bottom: 1px solid #94a3b8; }
.cn-seal-box { width: 70px; height: 50px; border: 1px dashed #94a3b8; border-radius: 50%; }

@media (max-width: 768px) {
    .cn-meta-grid { grid-template-columns: 1fr; }
    .cn-closing { flex-direction: column; }
    .cn-totals { width: 100%; }
    .cn-signatures { grid-template-columns: repeat(2, 1fr); }
}
</style>
