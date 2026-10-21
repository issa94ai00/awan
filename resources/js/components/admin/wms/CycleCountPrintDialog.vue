<template>
    <el-dialog
        v-model="visible"
        :title="$t('cc_print_preview')"
        width="960px"
        class="cc-print-dialog"
        append-to-body
        destroy-on-close
        top="3vh"
    >
        <!-- Print settings (screen only) -->
        <div class="cc-toolbar">
            <div class="cc-toolbar-group">
                <el-radio-group v-model="mode" size="small">
                    <el-radio-button value="sheet"><i class="fas fa-clipboard-list"></i> {{ $t('cc_print_mode_sheet') }}</el-radio-button>
                    <el-radio-button value="report" :disabled="!items.length"><i class="fas fa-scale-balanced"></i> {{ $t('cc_print_mode_report') }}</el-radio-button>
                </el-radio-group>
            </div>
            <div class="cc-toolbar-group">
                <el-checkbox v-model="settings.showLogo">{{ $t('po_show_logo') }}</el-checkbox>
                <el-checkbox v-if="mode === 'sheet'" v-model="settings.showExpected">{{ $t('cc_print_show_expected') }}</el-checkbox>
                <el-checkbox v-if="mode === 'report'" v-model="settings.varianceOnly">{{ $t('cc_print_variance_only') }}</el-checkbox>
                <el-checkbox v-if="mode === 'report'" v-model="settings.showValues">{{ $t('cc_print_show_values') }}</el-checkbox>
                <el-checkbox v-model="settings.showSignatures">{{ $t('po_show_signatures') }}</el-checkbox>
            </div>
        </div>

        <el-alert v-if="mode === 'sheet' && settings.showExpected" type="warning" :closable="false" show-icon class="blind-alert" :title="$t('cc_print_blind_hint')" />

        <div v-loading="loadingStock" class="cc-stage">
            <div v-if="count" :id="SHEET_ID" class="cc-sheet" dir="rtl" lang="ar">
                <div v-if="count.status === 'cancelled'" class="cc-watermark"><span>{{ count.status_text }}</span></div>

                <PrintDocumentHeader
                    header-style="compact"
                    :show-logo="settings.showLogo"
                    :show-contacts="false"
                    :show-meta="false"
                    :title="mode === 'sheet' ? $t('cc_print_sheet_title') : $t('cc_print_report_title')"
                    :subtitle="mode === 'sheet' ? 'STOCK COUNT SHEET' : 'STOCK COUNT VARIANCE REPORT'"
                    :document-number="count.count_number"
                    banner-src="/Header.jpeg"
                />

                <!-- What is being counted -->
                <div class="cc-facts">
                    <div><span>{{ $t('wms.warehouse') }}</span><strong>{{ count.warehouse_name || '—' }}</strong></div>
                    <div><span>{{ $t('wms.bin') }}</span><strong>{{ count.bin_code || $t('wms.whole_warehouse') }}</strong></div>
                    <div><span>{{ $t('wms.count_type') }}</span><strong>{{ count.type_text || '—' }}</strong></div>
                    <div><span>{{ $t('status') }}</span><strong>{{ count.status_text || '—' }}</strong></div>
                    <template v-if="mode === 'sheet'">
                        <div class="wide"><span>{{ $t('wms.counter') }}</span><strong class="cc-write-in">{{ count.counter_name || '' }}</strong></div>
                        <div class="wide"><span>{{ $t('cc_print_count_date') }}</span><strong class="cc-write-in"></strong></div>
                    </template>
                    <template v-else>
                        <div><span>{{ $t('wms.counter') }}</span><strong>{{ count.counter_name || '—' }}</strong></div>
                        <div><span>{{ $t('cc_print_completed_at') }}</span><strong dir="ltr">{{ formatDateTime(count.completed_at) }}</strong></div>
                        <div><span>{{ $t('wms.reviewer') }}</span><strong>{{ count.reviewer_name || '—' }}</strong></div>
                        <div><span>{{ $t('cc_print_adjustment') }}</span><strong :class="adjustmentState.cls">{{ adjustmentState.text }}</strong></div>
                    </template>
                </div>

                <!-- ── Count sheet: walk the shelves, write what is there ── -->
                <template v-if="mode === 'sheet'">
                    <table class="cc-table">
                        <thead>
                            <tr>
                                <th class="c-center" style="width: 30px;">#</th>
                                <th style="width: 90px;">{{ $t('wms.bin') }}</th>
                                <th>{{ $t('product') }}</th>
                                <th class="c-center" style="width: 60px;">{{ $t('cc_print_unit') }}</th>
                                <th v-if="settings.showExpected" class="c-center" style="width: 70px;">{{ $t('wms.system_quantity') }}</th>
                                <th class="c-center" style="width: 92px;">{{ $t('wms.counted_quantity') }}</th>
                                <th style="width: 130px;">{{ $t('notes') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, idx) in sheetRows" :key="row.key" class="cc-item-row">
                                <td class="c-center cc-idx">{{ idx + 1 }}</td>
                                <td><code v-if="row.bin_code" class="cc-bin">{{ row.bin_code }}</code><span v-else class="cc-muted">—</span></td>
                                <td class="cc-item-cell">
                                    <div class="cc-item-name">{{ row.name }}</div>
                                    <code v-if="row.sku" class="cc-sku">{{ row.sku }}</code>
                                </td>
                                <td class="c-center cc-unit">{{ row.unit || '—' }}</td>
                                <td v-if="settings.showExpected" class="c-center cc-qty">{{ row.expected }}</td>
                                <td class="c-center"><span class="cc-count-box"></span></td>
                                <td><span class="cc-write-line"></span></td>
                            </tr>
                            <!-- Room for what is on the shelf but not in the system. -->
                            <tr v-for="n in 5" :key="`blank-${n}`" class="cc-item-row cc-blank-row">
                                <td class="c-center cc-idx">{{ sheetRows.length + n }}</td>
                                <td><span class="cc-write-line"></span></td>
                                <td><span class="cc-write-line"></span></td>
                                <td><span class="cc-write-line"></span></td>
                                <td v-if="settings.showExpected"></td>
                                <td class="c-center"><span class="cc-count-box"></span></td>
                                <td><span class="cc-write-line"></span></td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="stockError" class="cc-error">{{ stockError }}</p>
                    <p class="cc-instructions"><i class="fas fa-circle-info"></i> {{ $t('cc_print_sheet_instructions') }}</p>
                </template>

                <!-- ── Variance report: what the count found ── -->
                <template v-else>
                    <div class="cc-kpis">
                        <div><span>{{ $t('cc_print_lines_counted') }}</span><strong>{{ items.length }}</strong></div>
                        <div><span>{{ $t('wms.variance_items') }}</span><strong :class="{ warn: varianceLines.length }">{{ varianceLines.length }}</strong></div>
                        <div><span>{{ $t('wms.accuracy') }}</span><strong>{{ accuracy }}</strong></div>
                        <div v-if="settings.showValues"><span>{{ $t('cc_print_gain') }}</span><strong class="gain" dir="ltr">{{ money(gainValue) }}</strong></div>
                        <div v-if="settings.showValues"><span>{{ $t('cc_print_loss') }}</span><strong class="loss" dir="ltr">{{ money(lossValue) }}</strong></div>
                        <div v-if="settings.showValues"><span>{{ $t('cc_print_net') }}</span><strong :class="netValue < 0 ? 'loss' : netValue > 0 ? 'gain' : ''" dir="ltr">{{ signedMoney(netValue) }}</strong></div>
                    </div>

                    <table class="cc-table">
                        <thead>
                            <tr>
                                <th class="c-center" style="width: 30px;">#</th>
                                <th>{{ $t('product') }}</th>
                                <th style="width: 80px;">{{ $t('wms.bin') }}</th>
                                <th class="c-center" style="width: 66px;">{{ $t('wms.system_quantity') }}</th>
                                <th class="c-center" style="width: 66px;">{{ $t('wms.counted_quantity') }}</th>
                                <th class="c-center" style="width: 66px;">{{ $t('wms.variance') }}</th>
                                <th v-if="settings.showValues" class="c-end" style="width: 90px;">{{ $t('trf_unit_cost') }}</th>
                                <th v-if="settings.showValues" class="c-end" style="width: 100px;">{{ $t('wms.variance_value') }}</th>
                                <th style="width: 100px;">{{ $t('wms.variance_reason') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, idx) in reportRows" :key="item.id || idx" class="cc-item-row" :class="{ 'has-variance': item.variance !== 0 }">
                                <td class="c-center cc-idx">{{ idx + 1 }}</td>
                                <td class="cc-item-cell">
                                    <div class="cc-item-name">{{ item.product_name || '—' }}</div>
                                    <code v-if="item.sku" class="cc-sku">{{ item.sku }}</code>
                                </td>
                                <td><code v-if="item.bin_code" class="cc-bin">{{ item.bin_code }}</code><span v-else class="cc-muted">—</span></td>
                                <td class="c-center cc-qty">{{ item.expected_quantity }}<span v-if="item.unit" class="cc-unit-sm">{{ item.unit }}</span></td>
                                <td class="c-center cc-qty">{{ item.counted_quantity }}</td>
                                <td class="c-center cc-qty" :class="item.variance < 0 ? 'loss' : item.variance > 0 ? 'gain' : 'cc-muted'">
                                    {{ item.variance > 0 ? '+' : '' }}{{ item.variance }}
                                </td>
                                <td v-if="settings.showValues" class="c-end cc-amount" dir="ltr">{{ item.unit_cost ? money(item.unit_cost) : '—' }}</td>
                                <td v-if="settings.showValues" class="c-end cc-amount" :class="item.variance < 0 ? 'loss' : item.variance > 0 ? 'gain' : 'cc-muted'" dir="ltr">
                                    {{ item.variance !== 0 ? signedMoney(signedValue(item)) : '—' }}
                                </td>
                                <td class="cc-reason">{{ item.variance !== 0 ? (item.variance_reason_text || '—') : '—' }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="cc-summary-row">
                                <td :colspan="settings.showValues ? 7 : 6" class="cc-sum-label">
                                    <i class="fas fa-layer-group"></i>
                                    {{ $t('cc_print_summary', { lines: reportRows.length, variance: varianceLines.length }) }}
                                </td>
                                <td v-if="settings.showValues" class="c-end cc-sum-total" :class="netValue < 0 ? 'loss' : netValue > 0 ? 'gain' : ''" dir="ltr">
                                    <strong>{{ signedMoney(netValue) }}</strong>
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                    <p v-if="settings.varianceOnly && reportRows.length < items.length" class="cc-instructions">
                        <i class="fas fa-filter"></i> {{ $t('cc_print_matching_hidden', { count: items.length - reportRows.length }) }}
                    </p>
                </template>

                <div v-if="count.notes" class="cc-notes">
                    <strong><i class="fas fa-clipboard-list"></i> {{ $t('notes') }}:</strong>
                    <p>{{ count.notes }}</p>
                </div>

                <!-- Signatures -->
                <div v-if="settings.showSignatures" class="cc-signatures">
                    <div class="cc-sig">
                        <span class="cc-sig-title">{{ $t('cc_print_sig_counter') }}</span>
                        <div v-if="count.counter_name" class="cc-sig-name">{{ count.counter_name }}</div>
                        <div class="cc-sig-line"></div>
                    </div>
                    <div class="cc-sig">
                        <span class="cc-sig-title">{{ $t('cc_print_sig_witness') }}</span>
                        <div class="cc-sig-line"></div>
                    </div>
                    <div class="cc-sig">
                        <span class="cc-sig-title">{{ $t('cc_print_sig_reviewer') }}</span>
                        <div v-if="mode === 'report' && count.reviewer_name" class="cc-sig-name">{{ count.reviewer_name }}</div>
                        <div class="cc-sig-line"></div>
                    </div>
                    <div class="cc-sig">
                        <span class="cc-sig-title">{{ $t('cc_print_sig_approver') }}</span>
                        <div v-if="mode === 'report' && count.adjuster_name" class="cc-sig-name">{{ count.adjuster_name }}</div>
                        <div class="cc-sig-line"></div>
                    </div>
                </div>

                <div class="cc-footer">
                    <span>{{ $t('cc_print_printed_at') }}: {{ printedAt }}</span>
                    <span dir="ltr">{{ count.count_number }}</span>
                </div>
            </div>
        </div>

        <template #footer>
            <el-button @click="visible = false">{{ $t('cancel') }}</el-button>
            <el-button type="primary" :disabled="!count || loadingStock" :loading="printing" @click="print">
                <i class="fas fa-print"></i>&nbsp;{{ $t('print_now') }}
            </el-button>
        </template>
    </el-dialog>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import PrintDocumentHeader from '@/components/admin/PrintDocumentHeader.vue';
import { inventoryApi } from '@/api/inventory';
import { formatMoney } from '@/utils/currency';
import { apiErrorMessage } from '@/utils/sales';
import { formatDateTime } from '@/utils/transfers';

/**
 * Two papers for one cycle count. Before it, a count sheet: what the system
 * holds there, in shelf order, with a box to write what is really on the
 * shelf — blind by default, since a counter shown the expected figure tends
 * to find it. After it, the variance report: expected against counted, what
 * the difference is worth, why, and who signed it off.
 */
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    /** The count as the detail page already holds it, lines included. */
    count: { type: Object, default: null },
});
const emit = defineEmits(['update:modelValue']);

const { t } = useI18n();

const SHEET_ID = 'cycle-count-printable-doc';
const IFRAME_ID = 'cc-print-hidden-iframe';
const MAX_STOCK_PAGES = 10;

const visible = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const mode = ref('sheet');
const settings = reactive({
    showLogo: true,
    showExpected: false,
    varianceOnly: false,
    showValues: true,
    showSignatures: true,
});

const printing = ref(false);
const printedAt = ref('');

const num = (value) => {
    const n = Number(value);
    return Number.isFinite(n) ? n : 0;
};
const money = (value) => formatMoney(value);
const signedMoney = (value) => (Math.abs(value) < 0.005 ? money(0) : `${value > 0 ? '+' : '−'}${money(Math.abs(value))}`);

// ── Count sheet: the system's stock at this place ─────────────────────────
const stockRows = ref([]);
const loadingStock = ref(false);
const stockError = ref('');

const loadStock = async () => {
    const c = props.count;
    if (!c?.warehouse_id) return;
    loadingStock.value = true;
    stockError.value = '';
    try {
        const rows = [];
        for (let page = 1; page <= MAX_STOCK_PAGES; page += 1) {
            const res = await inventoryApi.getStock({ warehouse_id: c.warehouse_id, per_page: 200, page });
            const data = res.data?.data || {};
            rows.push(...(data.stock || []));
            if (page >= (data.pagination?.last_page || 1)) break;
        }
        stockRows.value = rows;
    } catch (e) {
        stockError.value = apiErrorMessage(e, t('cc_print_stock_failed'));
        stockRows.value = [];
    } finally {
        loadingStock.value = false;
    }
};

// One line per product and location, in the order the shelves are walked.
const sheetRows = computed(() => {
    const binId = props.count?.bin_id;
    const lines = new Map();
    stockRows.value
        .filter((row) => !binId || Number(row.bin_id) === Number(binId))
        .forEach((row) => {
            const key = `${row.product_id}:${row.bin_id || 0}`;
            const line = lines.get(key) || {
                key,
                bin_code: row.bin?.bin_code || '',
                name: row.product?.name_ar || row.product?.name_en || `#${row.product_id}`,
                sku: row.product?.sku || '',
                unit: row.product?.unit || '',
                expected: 0,
            };
            line.expected += num(row.quantity);
            lines.set(key, line);
        });
    return [...lines.values()]
        .filter((line) => line.expected !== 0)
        .sort((a, b) => a.bin_code.localeCompare(b.bin_code, 'en', { numeric: true }) || a.name.localeCompare(b.name, 'ar'));
});

// ── Variance report ───────────────────────────────────────────────────────
const items = computed(() => props.count?.items || []);
const varianceLines = computed(() => items.value.filter((item) => num(item.variance) !== 0));
const reportRows = computed(() => (settings.varianceOnly ? varianceLines.value : items.value));

// The stored value is what the difference is worth; its sign follows the variance.
const signedValue = (item) => Math.sign(num(item.variance)) * Math.abs(num(item.variance_value));
const gainValue = computed(() => varianceLines.value.filter((i) => num(i.variance) > 0).reduce((s, i) => s + signedValue(i), 0));
const lossValue = computed(() => varianceLines.value.filter((i) => num(i.variance) < 0).reduce((s, i) => s + Math.abs(signedValue(i)), 0));
const netValue = computed(() => gainValue.value - lossValue.value);

const accuracy = computed(() => {
    if (props.count?.accuracy !== null && props.count?.accuracy !== undefined) return `${props.count.accuracy}%`;
    if (!items.value.length) return '—';
    return `${Math.round((1 - varianceLines.value.length / items.value.length) * 100)}%`;
});

const adjustmentState = computed(() => {
    const c = props.count || {};
    if (c.adjusted_at) return { cls: 'gain', text: t('cc_print_adjusted_on', { date: formatDateTime(c.adjusted_at) }) };
    if (c.requires_adjustment) return { cls: 'loss', text: t('cc_print_adjustment_pending') };
    return { cls: '', text: varianceLines.value.length ? '—' : t('cc_print_no_adjustment') };
});

// Before anything is counted the sheet is what is wanted; after, the report.
watch(() => props.modelValue, (open) => {
    if (!open) return;
    const d = new Date();
    printedAt.value = `${d.toLocaleDateString('en-CA')} ${d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })}`;
    settings.showExpected = false;
    mode.value = props.count?.status === 'completed' && items.value.length ? 'report' : 'sheet';
    if (!stockRows.value.length) loadStock();
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
        const title = mode.value === 'sheet' ? t('cc_print_sheet_title') : t('cc_print_report_title');
        const docTitle = escapeHtml(`${title} ${props.count?.count_number || ''}`);
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
.cc-toolbar {
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
.cc-toolbar-group { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 14px; }
.blind-alert { margin-bottom: 10px; }

.cc-stage { min-height: 300px; background: #f1f5f9; padding: 16px; border-radius: 8px; overflow-x: auto; }

/* ── The sheet ── */
.cc-sheet {
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
.cc-muted { color: #94a3b8; }

.cc-watermark { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; pointer-events: none; z-index: 2; overflow: hidden; }
.cc-watermark span {
    transform: rotate(-24deg);
    font-size: 80px;
    font-weight: 900;
    color: rgba(185, 28, 28, 0.13);
    border: 7px solid rgba(185, 28, 28, 0.13);
    border-radius: 16px;
    padding: 4px 36px;
    white-space: nowrap;
}

.cc-facts { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px 8px; margin: 12px 0; font-size: 11px; break-inside: avoid; }
.cc-facts > div { border: 1px solid #e2e8f0; border-radius: 6px; padding: 5px 8px; display: flex; flex-direction: column; min-width: 0; }
.cc-facts > div.wide { grid-column: span 2; }
.cc-facts span { font-size: 9.5px; color: #64748b; font-weight: 600; }
.cc-facts strong { overflow-wrap: anywhere; }
.cc-write-in { display: block; min-height: 18px; border-bottom: 1px dashed #94a3b8; }

.cc-kpis { display: grid; grid-template-columns: repeat(6, 1fr); gap: 6px; margin-bottom: 12px; break-inside: avoid; }
.cc-kpis > div { background: #f8fafc; border-radius: 6px; padding: 6px 8px; display: flex; flex-direction: column; align-items: center; text-align: center; }
.cc-kpis span { font-size: 9px; color: #64748b; font-weight: 600; }
.cc-kpis strong { font-size: 13px; font-variant-numeric: tabular-nums; white-space: nowrap; }
.cc-kpis strong.warn { color: #b45309; }

.gain { color: #15803d !important; }
.loss { color: #b91c1c !important; }

/* ── Lines ── */
.cc-table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 10px; }
.cc-table thead { display: table-header-group; }
.cc-table thead th { background: #334155; color: #ffffff; border: 1px solid #334155; padding: 6px 8px; text-align: right; font-weight: 800; font-size: 10.5px; }
.cc-table tbody td { border: 1px solid #e2e8f0; border-inline-color: #f1f5f9; padding: 6px 8px; text-align: right; vertical-align: middle; font-variant-numeric: tabular-nums; }
.cc-table tbody tr:nth-child(even) { background: #f8fafc; }
.cc-table .c-center { text-align: center; }
.cc-table .c-end { text-align: left; }
/* Each row reads as one unit: a clear rule under it, none of it split across pages. */
.cc-table tbody tr.cc-item-row td { border-bottom-color: #cbd5e1; }
.cc-table tbody tr.cc-item-row { break-inside: avoid; page-break-inside: avoid; }
.cc-table tbody tr.has-variance { background: #fffbeb; }
.cc-blank-row td { height: 28px; }
.cc-idx { color: #64748b; font-weight: 700; font-size: 10px; }
.cc-item-cell { overflow-wrap: anywhere; word-break: break-word; unicode-bidi: plaintext; }
.cc-item-name { font-weight: 700; font-size: 11.5px; line-height: 1.45; }
.cc-sku { display: inline-block; margin-top: 1px; background: #f1f5f9; padding: 1px 4px; border-radius: 4px; font-size: 9px; color: #475569; font-family: 'JetBrains Mono', monospace; }
.cc-bin { font-family: 'JetBrains Mono', monospace; font-size: 10px; font-weight: 700; color: #1e293b; }
.cc-unit { font-size: 10px; color: #475569; }
.cc-unit-sm { display: block; font-size: 8.5px; color: #64748b; font-weight: 600; }
.cc-qty { font-weight: 800; font-size: 12px; }
.cc-amount { white-space: nowrap; font-weight: 600; }
.cc-reason { font-size: 10px; color: #475569; }
/* A box big enough to write a number in by hand. */
.cc-count-box { display: inline-block; width: 70px; height: 22px; border: 1.5px solid #64748b; border-radius: 4px; background: #fff; }
.cc-write-line { display: inline-block; width: 100%; min-width: 30px; height: 16px; border-bottom: 1px dashed #94a3b8; }

.cc-summary-row td { background: #f8fafc; border: 1px solid #e2e8f0; border-top: 2px solid #cbd5e1; padding: 6px 10px; font-size: 11px; break-before: avoid; }
.cc-sum-label { color: #334155; }
.cc-sum-label i { color: #334155; margin-inline-end: 4px; }
.cc-sum-total { font-size: 12px; white-space: nowrap; }

.cc-instructions { margin: 0 0 10px; font-size: 10px; color: #475569; }
.cc-error { margin: 0 0 8px; font-size: 10.5px; color: #b91c1c; }
.cc-notes { border: 1px dashed #cbd5e1; border-radius: 6px; padding: 8px 10px; font-size: 10.5px; background: #fffbeb; margin-bottom: 4px; break-inside: avoid; }
.cc-notes p { margin: 4px 0 0; white-space: pre-line; unicode-bidi: plaintext; }

/* ── Signatures ── */
.cc-signatures { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-top: 20px; padding-top: 10px; border-top: 1px solid #e2e8f0; break-inside: avoid; }
.cc-sig { display: flex; flex-direction: column; align-items: center; text-align: center; }
.cc-sig-title { font-size: 10px; font-weight: 700; color: #334155; margin-bottom: 26px; }
.cc-sig-name { font-size: 10px; color: #475569; margin-top: -20px; margin-bottom: 8px; }
.cc-sig-line { width: 85%; border-bottom: 1px solid #94a3b8; }

.cc-footer { display: flex; justify-content: space-between; margin-top: 12px; padding-top: 6px; border-top: 1px solid #f1f5f9; font-size: 9px; color: #94a3b8; }

@media (max-width: 768px) {
    .cc-facts { grid-template-columns: 1fr 1fr; }
    .cc-kpis { grid-template-columns: repeat(3, 1fr); }
    .cc-signatures { grid-template-columns: repeat(2, 1fr); }
}
</style>
