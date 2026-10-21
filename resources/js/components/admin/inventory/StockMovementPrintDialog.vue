<template>
    <el-dialog
        v-model="visible"
        :title="isVoucher ? $t('mv_print_voucher_preview') : $t('mv_print_statement_preview')"
        width="960px"
        class="mv-print-dialog"
        append-to-body
        destroy-on-close
        top="3vh"
    >
        <!-- Print settings (screen only) -->
        <div class="mv-toolbar">
            <div class="mv-toolbar-group">
                <el-checkbox v-model="settings.showLogo">{{ $t('po_show_logo') }}</el-checkbox>
                <el-checkbox v-model="settings.showCosts">{{ $t('trf_print_show_costs') }}</el-checkbox>
                <el-checkbox v-model="settings.showSignatures">{{ $t('po_show_signatures') }}</el-checkbox>
                <el-checkbox v-if="!isVoucher" v-model="settings.showNotes">{{ $t('notes') }}</el-checkbox>
            </div>
            <span v-if="!isVoucher && !loading" class="mv-toolbar-count">{{ $t('mv_print_rows_n', { count: rows.length }) }}</span>
        </div>

        <el-alert v-if="truncated" type="warning" :closable="false" show-icon class="mv-alert" :title="$t('mv_print_truncated', { shown: rows.length, total: totalRows })" />

        <div v-loading="loading" class="mv-stage">
            <div v-if="isVoucher ? movement : !loading" :id="SHEET_ID" class="mv-sheet" dir="rtl" lang="ar">
                <PrintDocumentHeader
                    header-style="compact"
                    :show-logo="settings.showLogo"
                    :show-contacts="false"
                    :show-meta="false"
                    :title="isVoucher ? voucherTitle : $t('mv_print_statement_title')"
                    :subtitle="isVoucher ? voucherSubtitle : 'STOCK MOVEMENTS STATEMENT'"
                    :document-number="isVoucher ? movementNumber(movement) : ''"
                    banner-src="/Header.jpeg"
                />

                <!-- ── One movement ── -->
                <template v-if="isVoucher">
                    <div class="mv-hero" :class="`dir-${direction(movement)}`">
                        <div class="mv-hero-qty">
                            <span class="mv-hero-label">{{ directionLabel(movement) }}</span>
                            <strong dir="ltr">{{ signedQty(movement) }}</strong>
                            <span v-if="movement.product?.unit" class="mv-hero-unit">{{ movement.product.unit }}</span>
                        </div>
                        <div class="mv-hero-product">
                            <span class="mv-label">{{ $t('product') }}</span>
                            <strong>{{ productName(movement) }}</strong>
                            <code v-if="movement.product?.sku" class="mv-sku">{{ movement.product.sku }}</code>
                        </div>
                    </div>

                    <dl class="mv-details">
                        <div><dt>{{ $t('mv_print_number') }}</dt><dd class="mv-mono" dir="ltr">{{ movementNumber(movement) }}</dd></div>
                        <div><dt>{{ $t('date_and_time') }}</dt><dd dir="ltr">{{ formatDateTime(movement.created_at) }}</dd></div>
                        <div><dt>{{ $t('warehouse') }}</dt><dd>{{ movement.warehouse?.name || '—' }}</dd></div>
                        <div><dt>{{ $t('movement_type') }}</dt><dd>{{ typeLabel(movement.movement_type) }}</dd></div>
                        <div v-if="movement.reference"><dt>{{ $t('reference_code') }}</dt><dd class="mv-mono" dir="auto">{{ movement.reference }}</dd></div>
                        <div v-if="movement.source"><dt>{{ $t('source') }}</dt><dd dir="auto">{{ movement.source }}</dd></div>
                        <template v-if="settings.showCosts">
                            <div><dt>{{ $t('trf_unit_cost') }}</dt><dd dir="ltr">{{ money(movement.unit_cost) }}</dd></div>
                            <div><dt>{{ $t('mv_print_total_cost') }}</dt><dd dir="ltr"><strong>{{ money(movement.total_cost) }}</strong></dd></div>
                        </template>
                        <div v-if="movement.notes" class="wide"><dt>{{ $t('notes') }}</dt><dd class="mv-notes">{{ movement.notes }}</dd></div>
                    </dl>
                </template>

                <!-- ── The filtered log ── -->
                <template v-else>
                    <div class="mv-filters">
                        <div v-for="f in filterFacts" :key="f.label"><span>{{ f.label }}</span><strong>{{ f.value }}</strong></div>
                    </div>

                    <div class="mv-kpis">
                        <div><span>{{ $t('mv_print_movements') }}</span><strong>{{ rows.length }}</strong></div>
                        <div><span>{{ $t('mv_print_total_in') }}</span><strong class="in" dir="ltr">{{ unitTotals(totals.in) }}</strong></div>
                        <div><span>{{ $t('mv_print_total_out') }}</span><strong class="out" dir="ltr">{{ unitTotals(totals.out) }}</strong></div>
                        <div v-if="settings.showCosts"><span>{{ $t('mv_print_value_in') }}</span><strong class="in" dir="ltr">{{ money(totals.valueIn) }}</strong></div>
                        <div v-if="settings.showCosts"><span>{{ $t('mv_print_value_out') }}</span><strong class="out" dir="ltr">{{ money(totals.valueOut) }}</strong></div>
                    </div>

                    <table class="mv-table">
                        <thead>
                            <tr>
                                <th class="c-center" style="width: 28px;">#</th>
                                <th style="width: 92px;">{{ $t('the_date') }}</th>
                                <th>{{ $t('product') }}</th>
                                <th v-if="!singleWarehouse" style="width: 92px;">{{ $t('warehouse') }}</th>
                                <th class="c-center" style="width: 70px;">{{ $t('movement_type') }}</th>
                                <th class="c-center" style="width: 58px;">{{ $t('mv_print_in') }}</th>
                                <th class="c-center" style="width: 58px;">{{ $t('mv_print_out') }}</th>
                                <th v-if="settings.showCosts" class="c-end" style="width: 86px;">{{ $t('trf_value') }}</th>
                                <th style="width: 96px;">{{ $t('reference_code') }}</th>
                                <th v-if="settings.showNotes" style="width: 120px;">{{ $t('notes') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, idx) in rows" :key="row.id" class="mv-item-row">
                                <td class="c-center mv-idx">{{ idx + 1 }}</td>
                                <td class="mv-date" dir="ltr">{{ formatDateTime(row.created_at) }}</td>
                                <td class="mv-item-cell">
                                    <div class="mv-item-name">{{ productName(row) }}</div>
                                    <code v-if="row.product?.sku" class="mv-sku">{{ row.product.sku }}</code>
                                </td>
                                <td v-if="!singleWarehouse" class="mv-wh">{{ row.warehouse?.name || '—' }}</td>
                                <td class="c-center"><span class="mv-type" :class="`t-${row.movement_type}`">{{ typeLabel(row.movement_type) }}</span></td>
                                <td class="c-center mv-qty in">{{ direction(row) === 'in' ? magnitude(row) : '' }}</td>
                                <td class="c-center mv-qty out">{{ direction(row) === 'out' ? magnitude(row) : '' }}</td>
                                <td v-if="settings.showCosts" class="c-end mv-amount" dir="ltr">{{ money(row.total_cost) }}</td>
                                <td class="mv-ref" dir="auto">{{ row.reference || '—' }}<span v-if="row.creator?.name" class="mv-by">{{ row.creator.name }}</span></td>
                                <td v-if="settings.showNotes" class="mv-note-cell">{{ row.notes || '' }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="mv-summary-row">
                                <td :colspan="singleWarehouse ? 4 : 5" class="mv-sum-label">{{ $t('mv_print_totals') }}</td>
                                <td class="c-center mv-qty in">{{ unitTotals(totals.in) }}</td>
                                <td class="c-center mv-qty out">{{ unitTotals(totals.out) }}</td>
                                <td v-if="settings.showCosts" class="c-end mv-amount" dir="ltr">{{ signedMoney(totals.valueIn - totals.valueOut) }}</td>
                                <td :colspan="settings.showNotes ? 2 : 1"></td>
                            </tr>
                        </tfoot>
                    </table>
                    <p v-if="error" class="mv-error">{{ error }}</p>
                    <p v-else-if="!rows.length" class="mv-empty">{{ $t('no_matching_movements') }}</p>
                </template>

                <!-- Signatures -->
                <div v-if="settings.showSignatures" class="mv-signatures">
                    <div class="mv-sig">
                        <span class="mv-sig-title">{{ $t('spay_print_prepared_by') }}</span>
                        <div v-if="isVoucher && movement.creator?.name" class="mv-sig-name">{{ movement.creator.name }}</div>
                        <div class="mv-sig-line"></div>
                    </div>
                    <div class="mv-sig">
                        <span class="mv-sig-title">{{ $t('mv_print_sig_keeper') }}</span>
                        <div class="mv-sig-line"></div>
                    </div>
                    <div v-if="isVoucher && direction(movement) !== 'adjust'" class="mv-sig">
                        <span class="mv-sig-title">{{ direction(movement) === 'in' ? $t('mv_print_sig_deliverer') : $t('mv_print_sig_receiver') }}</span>
                        <div class="mv-sig-line"></div>
                    </div>
                    <div class="mv-sig">
                        <span class="mv-sig-title">{{ $t('approved_by') }}</span>
                        <div class="mv-sig-line"></div>
                    </div>
                </div>

                <div class="mv-footer">
                    <span>{{ $t('cc_print_printed_at') }}: {{ printedAt }}</span>
                    <span v-if="isVoucher" dir="ltr">{{ movementNumber(movement) }}</span>
                </div>
            </div>
        </div>

        <template #footer>
            <el-button @click="visible = false">{{ $t('cancel') }}</el-button>
            <el-button type="primary" :disabled="loading || (isVoucher && !movement)" :loading="printing" @click="print">
                <i class="fas fa-print"></i>&nbsp;{{ $t('print_now') }}
            </el-button>
        </template>
    </el-dialog>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import PrintDocumentHeader from '@/components/admin/PrintDocumentHeader.vue';
import { stockMovementsApi } from '@/api/stockMovements';
import { formatMoney } from '@/utils/currency';
import { apiErrorMessage } from '@/utils/sales';
import { formatDateTime } from '@/utils/transfers';

/**
 * Prints the movement log two ways. Given one movement, a voucher for it —
 * a goods-in, goods-out or adjustment slip with the hands that moved it.
 * Given the log's filters, a statement of every movement they match, with
 * what came in and what went out, so a period or a product can be signed off
 * on paper. Adjustments keep their sign: shrinkage reads as going out.
 */
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    /** A list row, for a voucher. */
    movement: { type: Object, default: null },
    /** The log's filters, for a statement (used when no movement is given). */
    filters: { type: Object, default: () => ({}) },
    /** What the filters mean in words: { product, warehouse, type }. */
    filterLabels: { type: Object, default: () => ({}) },
});
const emit = defineEmits(['update:modelValue']);

const { t } = useI18n();

const SHEET_ID = 'stock-movement-printable-doc';
const IFRAME_ID = 'mv-print-hidden-iframe';
const PER_PAGE = 200;
const MAX_PAGES = 25;

const visible = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const isVoucher = computed(() => Boolean(props.movement));

const settings = reactive({
    showLogo: true,
    showCosts: false,
    showSignatures: true,
    showNotes: false,
});

const printing = ref(false);
const printedAt = ref('');

const num = (value) => {
    const n = Number(value);
    return Number.isFinite(n) ? n : 0;
};
const money = (value) => formatMoney(value);
const signedMoney = (value) => (Math.abs(value) < 0.005 ? money(0) : `${value > 0 ? '+' : '−'}${money(Math.abs(value))}`);

// ── A movement's direction ─────────────────────────────────────────────────
// In and out are magnitudes with the type saying which way; an adjustment
// carries its own sign.
const direction = (row) => {
    if (!row) return 'in';
    if (row.movement_type === 'in') return 'in';
    if (row.movement_type === 'out') return 'out';
    return num(row.quantity) < 0 ? 'out' : 'in';
};
const magnitude = (row) => Math.abs(num(row.quantity));
const signedQty = (row) => `${direction(row) === 'out' ? '−' : '+'}${magnitude(row)}`;

const typeLabel = (type) => ({
    in: t('movement_in'),
    out: t('movement_out_label'),
    adjustment: t('movement_adjustment'),
}[type] || type || '—');

const directionLabel = (row) => {
    if (row?.movement_type === 'adjustment') return direction(row) === 'out' ? t('mv_print_shrinkage') : t('mv_print_surplus');
    return direction(row) === 'out' ? t('mv_print_out') : t('mv_print_in');
};

const voucherTitle = computed(() => ({
    in: t('mv_print_title_in'),
    out: t('mv_print_title_out'),
    adjustment: t('mv_print_title_adjustment'),
}[props.movement?.movement_type] || t('mv_print_title_adjustment')));

const voucherSubtitle = computed(() => ({
    in: 'GOODS RECEIVED SLIP',
    out: 'GOODS ISSUE SLIP',
    adjustment: 'STOCK ADJUSTMENT SLIP',
}[props.movement?.movement_type] || 'STOCK MOVEMENT SLIP'));

const movementNumber = (row) => (row?.id ? `MV-${String(row.id).padStart(6, '0')}` : '—');
const productName = (row) => row?.product?.name_ar || row?.product?.name_en || row?.product?.name || `#${row?.product_id}`;

// ── The statement ─────────────────────────────────────────────────────────
const rows = ref([]);
const totalRows = ref(0);
const loading = ref(false);
const error = ref('');

const truncated = computed(() => !isVoucher.value && totalRows.value > rows.value.length);
const singleWarehouse = computed(() => Boolean(props.filters?.warehouse_id));

const loadStatement = async () => {
    loading.value = true;
    error.value = '';
    rows.value = [];
    try {
        const params = Object.fromEntries(Object.entries(props.filters || {}).filter(([, v]) => v !== '' && v !== null && v !== undefined));
        const all = [];
        for (let page = 1; page <= MAX_PAGES; page += 1) {
            const res = await stockMovementsApi.getAll({ ...params, per_page: PER_PAGE, page });
            const data = res.data?.data || {};
            all.push(...(data.movements || []));
            totalRows.value = data.pagination?.total || all.length;
            if (page >= (data.pagination?.last_page || 1)) break;
        }
        // Oldest first: a statement reads in the order things happened.
        rows.value = all.reverse();
    } catch (e) {
        error.value = apiErrorMessage(e, t('mv_print_load_failed'));
    } finally {
        loading.value = false;
    }
};

// Quantities only add up within one unit: "12 قطعة · 3 كرتونة".
const unitTotals = (map) => {
    const entries = [...map.entries()].filter(([, qty]) => qty !== 0);
    if (!entries.length) return '0';
    return entries.map(([unit, qty]) => `${qty} ${unit}`).join(' · ');
};

const totals = computed(() => {
    const result = { in: new Map(), out: new Map(), valueIn: 0, valueOut: 0 };
    rows.value.forEach((row) => {
        const side = direction(row);
        const unit = row.product?.unit || t('qt_print_pcs');
        result[side].set(unit, (result[side].get(unit) || 0) + magnitude(row));
        if (side === 'in') result.valueIn += Math.abs(num(row.total_cost));
        else result.valueOut += Math.abs(num(row.total_cost));
    });
    return result;
});

const filterFacts = computed(() => {
    const f = props.filters || {};
    const l = props.filterLabels || {};
    return [
        { label: t('product'), value: f.product_id ? (l.product || `#${f.product_id}`) : t('all') },
        { label: t('warehouse'), value: f.warehouse_id ? (l.warehouse || `#${f.warehouse_id}`) : t('all') },
        { label: t('movement_type'), value: f.movement_type ? typeLabel(f.movement_type) : t('all') },
        { label: t('mv_print_period'), value: f.from_date || f.to_date ? `${f.from_date || '…'} ← ${f.to_date || '…'}` : t('all') },
    ];
});

watch(() => props.modelValue, (open) => {
    if (!open) return;
    const d = new Date();
    printedAt.value = `${d.toLocaleDateString('en-CA')} ${d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })}`;
    // A slip is about one product's cost; a statement is usually about quantities.
    settings.showCosts = false;
    if (!isVoucher.value) loadStatement();
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
        const title = isVoucher.value ? `${voucherTitle.value} ${movementNumber(props.movement)}` : t('mv_print_statement_title');
        const doc = iframe.contentWindow.document;
        doc.open();
        doc.write(`<!DOCTYPE html><html dir="rtl" lang="ar"><head><meta charset="utf-8">
            <base href="${window.location.origin}/"><title>${escapeHtml(title)}</title>
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
.mv-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px 18px;
    padding: 10px 14px;
    margin-bottom: 10px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
}
.mv-toolbar-group { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 14px; }
.mv-toolbar-count { font-size: 0.82rem; color: #475569; font-weight: 600; }
.mv-alert { margin-bottom: 10px; }

.mv-stage { min-height: 300px; background: #f1f5f9; padding: 16px; border-radius: 8px; overflow-x: auto; }

/* ── The sheet ── */
.mv-sheet {
    position: relative;
    width: 100%;
    max-width: 880px;
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
.mv-mono { font-family: ui-monospace, monospace; font-weight: 700; }
.mv-label { display: block; font-size: 10px; color: #64748b; font-weight: 600; }
.mv-sku { display: inline-block; margin-top: 1px; background: #f1f5f9; padding: 1px 4px; border-radius: 4px; font-size: 9px; color: #475569; font-family: 'JetBrains Mono', monospace; }
.in { color: #15803d !important; }
.out { color: #b91c1c !important; }

/* ── Voucher ── */
.mv-hero { display: flex; gap: 12px; margin: 12px 0; break-inside: avoid; }
.mv-hero-qty {
    flex: 0 0 30%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    padding: 10px;
    border: 1.5px solid #10b981;
    background: #ecfdf5;
}
.mv-hero.dir-out .mv-hero-qty { border-color: #ef4444; background: #fef2f2; }
.mv-hero-label { font-size: 10.5px; font-weight: 700; color: #475569; }
.mv-hero-qty strong { font-size: 26px; font-weight: 900; font-variant-numeric: tabular-nums; color: #065f46; }
.mv-hero.dir-out .mv-hero-qty strong { color: #991b1b; }
.mv-hero-unit { font-size: 11px; font-weight: 700; color: #475569; }
.mv-hero-product { flex: 1; min-width: 0; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 12px; display: flex; flex-direction: column; justify-content: center; gap: 2px; }
.mv-hero-product strong { font-size: 14px; overflow-wrap: anywhere; unicode-bidi: plaintext; }

.mv-details { display: grid; grid-template-columns: repeat(2, 1fr); margin: 0 0 12px; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; font-size: 11px; }
.mv-details > div { display: flex; gap: 10px; align-items: baseline; padding: 7px 10px; border-bottom: 1px solid #f1f5f9; min-width: 0; }
.mv-details > div.wide { grid-column: 1 / -1; }
.mv-details dt { flex: 0 0 auto; min-width: 90px; color: #64748b; font-weight: 600; }
.mv-details dd { margin: 0; flex: 1; min-width: 0; overflow-wrap: anywhere; }
.mv-notes { white-space: pre-line; unicode-bidi: plaintext; }

/* ── Statement ── */
.mv-filters { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px 8px; margin: 12px 0 8px; font-size: 11px; break-inside: avoid; }
.mv-filters > div { border: 1px solid #e2e8f0; border-radius: 6px; padding: 5px 8px; display: flex; flex-direction: column; min-width: 0; }
.mv-filters span { font-size: 9.5px; color: #64748b; font-weight: 600; }
.mv-filters strong { overflow-wrap: anywhere; }
.mv-kpis { display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px; margin-bottom: 10px; break-inside: avoid; }
.mv-kpis > div { background: #f8fafc; border-radius: 6px; padding: 6px 8px; display: flex; flex-direction: column; align-items: center; text-align: center; min-width: 0; }
.mv-kpis span { font-size: 9px; color: #64748b; font-weight: 600; }
.mv-kpis strong { font-size: 12px; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }

.mv-table { width: 100%; border-collapse: collapse; font-size: 10.5px; margin-bottom: 10px; }
.mv-table thead { display: table-header-group; }
.mv-table thead th { background: #334155; color: #ffffff; border: 1px solid #334155; padding: 5px 6px; text-align: right; font-weight: 800; font-size: 10px; }
.mv-table tbody td { border: 1px solid #e2e8f0; border-inline-color: #f1f5f9; padding: 5px 6px; text-align: right; vertical-align: middle; font-variant-numeric: tabular-nums; }
.mv-table tbody tr:nth-child(even) { background: #f8fafc; }
.mv-table .c-center { text-align: center; }
.mv-table .c-end { text-align: left; }
/* Each row reads as one unit: a clear rule under it, none of it split across pages. */
.mv-table tbody tr.mv-item-row td { border-bottom-color: #cbd5e1; }
.mv-table tbody tr.mv-item-row { break-inside: avoid; page-break-inside: avoid; }
.mv-idx { color: #64748b; font-weight: 700; font-size: 9.5px; }
.mv-date { font-size: 9.5px; white-space: nowrap; color: #334155; text-align: right; }
.mv-item-cell { overflow-wrap: anywhere; word-break: break-word; unicode-bidi: plaintext; }
.mv-item-name { font-weight: 700; font-size: 11px; line-height: 1.4; }
.mv-wh { font-size: 10px; color: #334155; }
.mv-type { font-size: 9.5px; font-weight: 700; padding: 1px 6px; border-radius: 999px; white-space: nowrap; }
.mv-type.t-in { background: #dcfce7; color: #166534; }
.mv-type.t-out { background: #fee2e2; color: #991b1b; }
.mv-type.t-adjustment { background: #fef3c7; color: #92400e; }
.mv-qty { font-weight: 800; font-size: 11.5px; }
.mv-amount { white-space: nowrap; font-weight: 600; }
.mv-ref { font-size: 9.5px; overflow-wrap: anywhere; }
.mv-by { display: block; color: #64748b; font-size: 9px; }
.mv-note-cell { font-size: 9.5px; color: #475569; overflow-wrap: anywhere; unicode-bidi: plaintext; }

.mv-summary-row td { background: #f8fafc; border: 1px solid #e2e8f0; border-top: 2px solid #cbd5e1; padding: 6px; font-size: 10.5px; break-before: avoid; }
.mv-sum-label { font-weight: 800; color: #334155; }
.mv-empty, .mv-error { margin: 0 0 10px; text-align: center; font-size: 11px; color: #64748b; }
.mv-error { color: #b91c1c; }

/* ── Signatures ── */
.mv-signatures { display: flex; gap: 12px; margin-top: 20px; padding-top: 10px; border-top: 1px solid #e2e8f0; break-inside: avoid; }
.mv-sig { flex: 1; display: flex; flex-direction: column; align-items: center; text-align: center; }
.mv-sig-title { font-size: 10px; font-weight: 700; color: #334155; margin-bottom: 26px; }
.mv-sig-name { font-size: 10px; color: #475569; margin-top: -20px; margin-bottom: 8px; }
.mv-sig-line { width: 85%; border-bottom: 1px solid #94a3b8; }

.mv-footer { display: flex; justify-content: space-between; margin-top: 12px; padding-top: 6px; border-top: 1px solid #f1f5f9; font-size: 9px; color: #94a3b8; }

@media (max-width: 768px) {
    .mv-hero { flex-direction: column; }
    .mv-details, .mv-filters { grid-template-columns: 1fr 1fr; }
    .mv-kpis { grid-template-columns: repeat(3, 1fr); }
    .mv-signatures { flex-wrap: wrap; }
}
</style>
