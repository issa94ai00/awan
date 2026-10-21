<template>
    <el-dialog
        v-model="visible"
        :title="$t('trf_print_preview')"
        width="920px"
        class="tp-print-dialog"
        append-to-body
        destroy-on-close
        top="3vh"
    >
        <!-- Print settings (screen only) -->
        <div class="tp-toolbar">
            <div class="tp-toolbar-group">
                <span class="tp-toolbar-label">{{ $t('po_header_style') }}:</span>
                <el-radio-group v-model="settings.headerStyle" size="small">
                    <el-radio-button label="official">{{ $t('po_header_official') }}</el-radio-button>
                    <el-radio-button label="banner">{{ $t('po_header_banner') }}</el-radio-button>
                    <el-radio-button label="compact">{{ $t('po_header_compact') }}</el-radio-button>
                </el-radio-group>
            </div>
            <div class="tp-toolbar-group">
                <el-checkbox v-model="settings.showLogo">{{ $t('po_show_logo') }}</el-checkbox>
                <el-checkbox v-model="settings.pickingCopy">{{ $t('trf_print_picking_copy') }}</el-checkbox>
                <el-checkbox v-model="settings.showCosts">{{ $t('trf_print_show_costs') }}</el-checkbox>
                <el-checkbox v-model="settings.showSignatures">{{ $t('po_show_signatures') }}</el-checkbox>
            </div>
        </div>

        <div v-loading="loading" class="tp-stage">
            <div v-if="transfer" :id="SHEET_ID" class="tp-sheet" dir="rtl" lang="ar">
                <!-- A cancelled transfer must not pass for a live one. -->
                <div v-if="transfer.status === 'cancelled'" class="tp-watermark"><span>{{ transferStatusLabel(t, 'cancelled') }}</span></div>

                <PrintDocumentHeader
                    :header-style="settings.headerStyle"
                    :show-logo="settings.showLogo"
                    :show-contacts="false"
                    :show-meta="false"
                    :title="settings.pickingCopy ? $t('trf_print_picking_title') : $t('trf_print_title')"
                    :subtitle="settings.pickingCopy ? 'TRANSFER PICKING SLIP' : 'STOCK TRANSFER NOTE'"
                    :document-number="transfer.transfer_number"
                    banner-src="/Header.jpeg"
                />

                <!-- From → to, side by side -->
                <div class="tp-route">
                    <div class="tp-wh">
                        <div class="tp-wh-head"><i class="fas fa-warehouse"></i> {{ $t('trf_from') }}</div>
                        <div class="tp-wh-body">
                            <strong>{{ transfer.from_warehouse?.name || '—' }}</strong>
                            <code v-if="transfer.from_warehouse?.code" class="tp-code">{{ transfer.from_warehouse.code }}</code>
                            <div v-if="transfer.from_warehouse?.address" class="tp-line">{{ transfer.from_warehouse.address }}</div>
                            <div v-if="transfer.from_warehouse?.manager_name" class="tp-line">
                                {{ transfer.from_warehouse.manager_name }}
                                <span v-if="transfer.from_warehouse.manager_phone" dir="ltr">· {{ transfer.from_warehouse.manager_phone }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="tp-arrow"><i class="fas fa-arrow-left"></i></div>
                    <div class="tp-wh">
                        <div class="tp-wh-head"><i class="fas fa-store"></i> {{ $t('trf_to') }}</div>
                        <div class="tp-wh-body">
                            <strong>{{ transfer.to_warehouse?.name || '—' }}</strong>
                            <code v-if="transfer.to_warehouse?.code" class="tp-code">{{ transfer.to_warehouse.code }}</code>
                            <div v-if="transfer.to_warehouse?.address" class="tp-line">{{ transfer.to_warehouse.address }}</div>
                            <div v-if="transfer.to_warehouse?.manager_name" class="tp-line">
                                {{ transfer.to_warehouse.manager_name }}
                                <span v-if="transfer.to_warehouse.manager_phone" dir="ltr">· {{ transfer.to_warehouse.manager_phone }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tp-facts">
                    <div><span>{{ $t('trf_number') }}</span><strong class="tp-mono" dir="ltr">{{ transfer.transfer_number }}</strong></div>
                    <div><span>{{ $t('trf_step_requested') }}</span><strong dir="ltr">{{ formatDateTime(transfer.requested_at || transfer.created_at) }}</strong></div>
                    <div><span>{{ $t('trf_method') }}</span><strong>{{ transferMethodLabel(t, transfer.fulfillment_method) }}</strong></div>
                    <div><span>{{ $t('status') }}</span><strong>{{ transferStatusLabel(t, transfer.status) }}</strong></div>
                </div>

                <!-- Lines -->
                <table class="tp-table">
                    <thead>
                        <tr>
                            <th class="c-center" style="width: 30px;">#</th>
                            <th>{{ $t('product') }}</th>
                            <th class="c-center" style="width: 74px;">{{ $t('trf_requested') }}</th>
                            <th v-if="showShipped" class="c-center" style="width: 74px;">{{ $t('trf_shipped') }}</th>
                            <th v-if="showReceived" class="c-center" style="width: 74px;">{{ $t('trf_received') }}</th>
                            <th v-if="settings.pickingCopy" class="c-center" style="width: 84px;">{{ $t('so_picked_qty') }}</th>
                            <th v-if="settings.pickingCopy" class="c-center" style="width: 48px;">✓</th>
                            <th v-if="settings.showCosts" class="c-end" style="width: 96px;">{{ $t('trf_unit_cost') }}</th>
                            <th v-if="settings.showCosts" class="c-end" style="width: 110px;">{{ $t('trf_value') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, idx) in items" :key="item.id || idx" class="tp-item-row">
                            <td class="c-center tp-idx">{{ idx + 1 }}</td>
                            <td class="tp-item-cell">
                                <div class="tp-item-name">{{ transferProductName(item) }}</div>
                                <div v-if="item.product?.name_en && item.product?.name_ar" class="tp-item-en">{{ item.product.name_en }}</div>
                                <code v-if="item.variant?.sku || item.product?.sku" class="tp-sku">{{ item.variant?.sku || item.product?.sku }}</code>
                            </td>
                            <td class="c-center tp-qty-cell">
                                <span class="tp-qty-num">{{ item.quantity_requested }}</span>
                                <span v-if="unitOf(item)" class="tp-unit">{{ unitOf(item) }}</span>
                            </td>
                            <td v-if="showShipped" class="c-center tp-qty">{{ item.quantity_shipped ?? '—' }}</td>
                            <td v-if="showReceived" class="c-center tp-qty" :class="{ short: isShort(item) }">{{ item.quantity_received ?? '—' }}</td>
                            <td v-if="settings.pickingCopy" class="c-center"><span class="tp-write-in"></span></td>
                            <td v-if="settings.pickingCopy" class="c-center"><span class="tp-check-box"></span></td>
                            <td v-if="settings.showCosts" class="c-end tp-amount" dir="ltr">{{ money(item.unit_cost) }}</td>
                            <td v-if="settings.showCosts" class="c-end tp-amount tp-line-total" dir="ltr">{{ money(num(item.unit_cost) * num(item.quantity_requested)) }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="tp-summary-row">
                            <td :colspan="summaryColspan" class="tp-sum-label">
                                <i class="fas fa-layer-group"></i>
                                <span>{{ $t('qt_print_lines_total') }}:</span>
                                <strong class="tp-sum-count">{{ items.length }}</strong>
                                ·
                                <span>{{ $t('qt_print_total_qty') }}:</span>
                                <strong class="tp-sum-count">{{ quantitySummary }}</strong>
                            </td>
                            <td v-if="settings.showCosts" colspan="2" class="tp-sum-total" dir="ltr">
                                <strong>{{ money(totalValue) }}</strong>
                            </td>
                        </tr>
                    </tfoot>
                </table>

                <div v-if="transfer.notes" class="tp-notes">
                    <strong><i class="fas fa-clipboard-list"></i> {{ $t('notes') }}:</strong>
                    <p>{{ transfer.notes }}</p>
                </div>

                <!-- Who handled it at each end -->
                <div v-if="settings.showSignatures" class="tp-signatures">
                    <div class="tp-sig">
                        <span class="tp-sig-title">{{ $t('trf_sig_requested') }}</span>
                        <div v-if="transfer.created_by?.name" class="tp-sig-name">{{ transfer.created_by.name }}</div>
                        <div class="tp-sig-line"></div>
                    </div>
                    <div class="tp-sig">
                        <span class="tp-sig-title">{{ $t('trf_sig_source_keeper') }}</span>
                        <div v-if="(transfer.shipped_by || transfer.approved_by)?.name" class="tp-sig-name">{{ (transfer.shipped_by || transfer.approved_by).name }}</div>
                        <div class="tp-sig-line"></div>
                    </div>
                    <div class="tp-sig">
                        <span class="tp-sig-title">{{ transfer.fulfillment_method === 'pickup' ? $t('trf_sig_collector') : $t('trf_sig_driver') }}</span>
                        <div class="tp-sig-line"></div>
                    </div>
                    <div class="tp-sig">
                        <span class="tp-sig-title">{{ $t('trf_sig_destination_keeper') }}</span>
                        <div v-if="transfer.received_by?.name" class="tp-sig-name">{{ transfer.received_by.name }}</div>
                        <div class="tp-sig-line"></div>
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <el-button @click="visible = false">{{ $t('cancel') }}</el-button>
            <el-button type="primary" :disabled="!transfer" :loading="printing" @click="print">
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
import { inventoryTransfersApi } from '@/api/inventoryTransfers';
import { formatMoney } from '@/utils/currency';
import { apiErrorMessage } from '@/utils/sales';
import { formatDateTime, transferMethodLabel, transferProductName, transferStatusLabel } from '@/utils/transfers';

/**
 * The paper that travels with a transfer: what leaves which warehouse for
 * which, with the quantities at each stage and a signature for each pair of
 * hands. As a picking slip it adds a column to write what was actually taken
 * off the shelf, and costs stay off unless asked for — it is an internal move.
 */
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    transferId: { type: [Number, String], default: null },
});
const emit = defineEmits(['update:modelValue']);

const { t } = useI18n();

const SHEET_ID = 'transfer-printable-doc';
const IFRAME_ID = 'tp-print-hidden-iframe';

const visible = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const settings = reactive({
    headerStyle: 'compact',
    showLogo: true,
    pickingCopy: false,
    showCosts: false,
    showSignatures: true,
});

const transfer = ref(null);
const loading = ref(false);
const printing = ref(false);

const num = (value) => {
    const n = Number(value);
    return Number.isFinite(n) ? n : 0;
};
const money = (value) => formatMoney(value);

const items = computed(() => transfer.value?.items || []);
const totalValue = computed(() => items.value.reduce((sum, item) => sum + num(item.unit_cost) * num(item.quantity_requested), 0));

// Shown once there is something in them.
const showShipped = computed(() => items.value.some((item) => item.quantity_shipped !== null && item.quantity_shipped !== undefined && transfer.value?.status !== 'pending'));
const showReceived = computed(() => transfer.value?.status === 'completed');
const summaryColspan = computed(() => 3 + (showShipped.value ? 1 : 0) + (showReceived.value ? 1 : 0) + (settings.pickingCopy ? 2 : 0));

const isShort = (item) => transfer.value?.fulfillment_method !== 'pickup'
    && num(item.quantity_received) < num(item.quantity_shipped);

const unitOf = (item) => item?.product?.unit || '';

// Quantities only add up within one unit: "12 قطعة · 3 كرتونة", never "15 قطعة".
const quantitySummary = computed(() => {
    const totals = new Map();
    items.value.forEach((item) => {
        const unit = unitOf(item) || t('qt_print_pcs');
        totals.set(unit, (totals.get(unit) || 0) + num(item.quantity_requested));
    });
    if (!totals.size) return '0';
    return [...totals].map(([unit, qty]) => `${qty} ${unit}`).join(' · ');
});

watch(() => [props.modelValue, props.transferId], async ([open, id]) => {
    if (!open || !id) return;
    transfer.value = null;
    loading.value = true;
    // Still at the source: what the picker needs is the slip.
    settings.pickingCopy = false;
    try {
        const res = await inventoryTransfersApi.get(id);
        transfer.value = res.data?.data || null;
        settings.pickingCopy = transfer.value?.status === 'pending';
    } catch (e) {
        ElMessage.error(apiErrorMessage(e, t('trf_load_failed')));
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
        const docTitle = escapeHtml(`${t('trf_print_title')} ${transfer.value?.transfer_number || ''}`);
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
.tp-toolbar {
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
.tp-toolbar-group { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 14px; }
.tp-toolbar-label { font-size: 0.82rem; font-weight: 600; color: #475569; }

.tp-stage { min-height: 300px; background: #f1f5f9; padding: 16px; border-radius: 8px; overflow-x: auto; }

/* ── The sheet ── */
.tp-sheet {
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
.tp-mono { font-family: ui-monospace, monospace; font-weight: 700; }

.tp-watermark { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; pointer-events: none; z-index: 2; overflow: hidden; }
.tp-watermark span {
    transform: rotate(-24deg);
    font-size: 80px;
    font-weight: 900;
    color: rgba(185, 28, 28, 0.13);
    border: 7px solid rgba(185, 28, 28, 0.13);
    border-radius: 16px;
    padding: 4px 36px;
    white-space: nowrap;
}

/* From → to */
.tp-route { display: flex; align-items: stretch; gap: 10px; margin: 12px 0 10px; break-inside: avoid; }
.tp-wh { flex: 1; min-width: 0; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
.tp-wh-head { background: #ecfeff; color: #0e7490; font-weight: 800; font-size: 11px; padding: 5px 10px; display: flex; align-items: center; gap: 6px; }
.tp-wh-body { padding: 7px 10px; font-size: 11px; }
.tp-wh-body strong { font-size: 13px; margin-inline-end: 6px; }
.tp-code { font-size: 9.5px; color: #475569; background: #f1f5f9; padding: 1px 5px; border-radius: 4px; }
.tp-line { color: #475569; margin-top: 2px; overflow-wrap: anywhere; }
.tp-arrow { display: flex; align-items: center; color: #0e7490; font-size: 16px; }

.tp-facts { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 12px; font-size: 11px; break-inside: avoid; }
.tp-facts > div { border: 1px solid #e2e8f0; border-radius: 6px; padding: 5px 8px; display: flex; flex-direction: column; }
.tp-facts span { font-size: 9.5px; color: #64748b; font-weight: 600; }

/* ── Lines ── */
.tp-table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 12px; }
.tp-table thead { display: table-header-group; }
.tp-table thead th { background: #0e7490; color: #ffffff; border: 1px solid #0e7490; padding: 6px 8px; text-align: right; font-weight: 800; font-size: 10.5px; }
.tp-table tbody td { border: 1px solid #e2e8f0; border-inline-color: #f1f5f9; padding: 6px 8px; text-align: right; vertical-align: middle; font-variant-numeric: tabular-nums; }
.tp-table tbody tr:nth-child(even) { background: #f8fafc; }
.tp-table .c-center { text-align: center; }
.tp-table .c-end { text-align: left; }
/* Each row reads as one unit: a clear rule under it, none of it split across pages. */
.tp-table tbody tr.tp-item-row td { border-bottom-color: #cbd5e1; }
.tp-table tbody tr.tp-item-row { break-inside: avoid; page-break-inside: avoid; }
.tp-idx { color: #64748b; font-weight: 700; font-size: 10px; }
/* Long product names wrap inside their column instead of stretching the table. */
.tp-item-cell { overflow-wrap: anywhere; word-break: break-word; unicode-bidi: plaintext; }
.tp-item-name { font-weight: 700; font-size: 11.5px; line-height: 1.45; }
.tp-item-en { font-size: 9px; color: #64748b; direction: ltr; text-align: right; }
.tp-sku { display: inline-block; margin-top: 2px; background: #f1f5f9; padding: 1px 4px; border-radius: 4px; font-size: 9px; color: #475569; font-family: 'JetBrains Mono', monospace; }
.tp-qty-cell { font-weight: 800; background: #f8fafc; }
.tp-qty-num { display: block; font-size: 13px; line-height: 1.2; }
.tp-unit { display: block; font-size: 9px; font-weight: 600; color: #64748b; line-height: 1.2; }
.tp-qty { font-weight: 700; font-size: 12px; }
.tp-qty.short { color: #b91c1c; }
.tp-amount { white-space: nowrap; color: #334155; font-weight: 600; }
.tp-line-total { color: #0e7490; font-weight: 800; }
.tp-write-in { display: inline-block; width: 80%; min-width: 40px; height: 16px; border-bottom: 1px dashed #94a3b8; }
.tp-check-box { display: inline-block; width: 16px; height: 16px; border: 1.5px solid #64748b; border-radius: 3px; }

.tp-summary-row td { background: #f8fafc; border: 1px solid #e2e8f0; border-top: 2px solid #cbd5e1; padding: 6px 10px; font-size: 11px; break-before: avoid; }
.tp-sum-label { color: #334155; }
.tp-sum-label i, .tp-sum-count { color: #0e7490; }
.tp-sum-count { font-weight: 800; margin-inline: 2px; }
.tp-sum-total { text-align: left; font-size: 12px; color: #0e7490; white-space: nowrap; }

.tp-notes { border: 1px dashed #cbd5e1; border-radius: 6px; padding: 8px 10px; font-size: 10.5px; background: #fffbeb; margin-bottom: 4px; break-inside: avoid; }
.tp-notes p { margin: 4px 0 0; white-space: pre-line; unicode-bidi: plaintext; }

/* ── Signatures ── */
.tp-signatures { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-top: 22px; padding-top: 10px; border-top: 1px solid #e2e8f0; break-inside: avoid; }
.tp-sig { display: flex; flex-direction: column; align-items: center; text-align: center; }
.tp-sig-title { font-size: 10px; font-weight: 700; color: #334155; margin-bottom: 26px; }
.tp-sig-name { font-size: 10px; color: #475569; margin-top: -20px; margin-bottom: 8px; }
.tp-sig-line { width: 85%; border-bottom: 1px solid #94a3b8; }

@media (max-width: 768px) {
    .tp-route { flex-direction: column; }
    .tp-arrow { justify-content: center; transform: rotate(-90deg); }
    .tp-facts { grid-template-columns: 1fr 1fr; }
    .tp-signatures { grid-template-columns: repeat(2, 1fr); }
}
</style>
