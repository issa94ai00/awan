<template>
    <el-dialog
        v-model="visible"
        :title="$t('pk_print_preview')"
        width="940px"
        class="pk-print-dialog"
        append-to-body
        destroy-on-close
        top="3vh"
    >
        <!-- Print settings (screen only) -->
        <div class="pk-toolbar">
            <div class="pk-toolbar-group">
                <el-checkbox v-model="settings.showLogo">{{ $t('po_show_logo') }}</el-checkbox>
                <el-checkbox v-model="settings.showPicked" :disabled="!hasPicks">{{ $t('pk_print_show_picked') }}</el-checkbox>
                <el-checkbox v-model="settings.showBarcode">{{ $t('pk_print_show_barcode') }}</el-checkbox>
                <el-checkbox v-model="settings.showSignatures">{{ $t('po_show_signatures') }}</el-checkbox>
            </div>
        </div>

        <div class="pk-stage">
            <div v-if="list" :id="SHEET_ID" class="pk-sheet" dir="rtl" lang="ar">
                <div v-if="list.status === 'cancelled'" class="pk-watermark"><span>{{ list.status_text }}</span></div>

                <PrintDocumentHeader
                    header-style="compact"
                    :show-logo="settings.showLogo"
                    :show-contacts="false"
                    :show-meta="false"
                    :title="$t('pk_print_title')"
                    subtitle="PICKING LIST"
                    :document-number="list.list_number"
                    banner-src="/Header.jpeg"
                />

                <div class="pk-facts">
                    <div><span>{{ $t('pk_print_order') }}</span><strong class="pk-mono" dir="ltr">{{ list.order_number || '—' }}</strong></div>
                    <div><span>{{ $t('client') }}</span><strong>{{ list.customer_name || '—' }}</strong></div>
                    <div><span>{{ $t('warehouse') }}</span><strong>{{ list.warehouse_name || '—' }}</strong></div>
                    <div><span>{{ $t('pk_print_priority') }}</span><strong :class="`pr-${list.priority}`">{{ list.priority_text || '—' }}</strong></div>
                    <div><span>{{ $t('status') }}</span><strong>{{ list.status_text || '—' }}</strong></div>
                    <div><span>{{ $t('pk_print_created') }}</span><strong dir="ltr">{{ formatDateTime(list.created_at) }}</strong></div>
                    <div class="wide"><span>{{ $t('pk_print_picker') }}</span><strong :class="{ 'pk-write-in': !list.picker_name }">{{ list.picker_name || '' }}</strong></div>
                </div>

                <table class="pk-table">
                    <thead>
                        <tr>
                            <th class="c-center" style="width: 30px;">#</th>
                            <th style="width: 100px;">{{ $t('pk_print_location') }}</th>
                            <th>{{ $t('product') }}</th>
                            <th class="c-center" style="width: 74px;">{{ $t('required_quantity') }}</th>
                            <th class="c-center" style="width: 86px;">{{ $t('picked_quantity') }}</th>
                            <th class="c-center" style="width: 44px;">✓</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, idx) in items" :key="item.id || idx" class="pk-item-row" :class="{ short: isShort(item) }">
                            <td class="c-center pk-idx">{{ idx + 1 }}</td>
                            <td>
                                <code v-if="item.bin_code" class="pk-bin">{{ item.bin_code }}</code>
                                <span v-if="item.bin_location" class="pk-bin-loc">{{ item.bin_location }}</span>
                                <span v-if="!item.bin_code && !item.bin_location" class="pk-muted">—</span>
                            </td>
                            <td class="pk-item-cell">
                                <div class="pk-item-name">{{ item.product_name || '—' }}</div>
                                <code v-if="item.sku" class="pk-sku">{{ item.sku }}</code>
                                <span v-if="settings.showBarcode && (item.barcode)" class="pk-barcode" dir="ltr"><i class="fas fa-barcode"></i> {{ item.barcode }}</span>
                            </td>
                            <td class="c-center pk-qty-cell">
                                <span class="pk-qty-num">{{ item.quantity_to_pick }}</span>
                                <span v-if="item.unit" class="pk-unit">{{ item.unit }}</span>
                            </td>
                            <td class="c-center">
                                <span v-if="settings.showPicked && item.status !== 'pending'" class="pk-picked" :class="{ short: isShort(item) }">{{ item.quantity_picked }}</span>
                                <span v-else class="pk-count-box"></span>
                            </td>
                            <td class="c-center">
                                <span class="pk-check-box" :class="{ done: settings.showPicked && item.status === 'picked' }"></span>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="pk-summary-row">
                            <td colspan="3" class="pk-sum-label">
                                <i class="fas fa-layer-group"></i>
                                <span>{{ $t('qt_print_lines_total') }}:</span>
                                <strong class="pk-sum-count">{{ items.length }}</strong>
                                ·
                                <span>{{ $t('qt_print_total_qty') }}:</span>
                                <strong class="pk-sum-count">{{ quantitySummary }}</strong>
                            </td>
                            <td colspan="3" class="pk-sum-label c-center">
                                <template v-if="settings.showPicked && hasPicks">
                                    {{ $t('pk_print_picked_summary', { picked: list.picked_items, total: list.total_items }) }}
                                    <span v-if="shortCount" class="short-text"> · {{ $t('items_short_of_required', { count: shortCount }) }}</span>
                                </template>
                            </td>
                        </tr>
                    </tfoot>
                </table>

                <p class="pk-instructions"><i class="fas fa-circle-info"></i> {{ $t('pk_print_instructions') }}</p>

                <div v-if="list.notes" class="pk-notes">
                    <strong><i class="fas fa-clipboard-list"></i> {{ $t('notes') }}:</strong>
                    <p>{{ list.notes }}</p>
                </div>

                <div v-if="settings.showSignatures" class="pk-signatures">
                    <div class="pk-sig">
                        <span class="pk-sig-title">{{ $t('pk_print_sig_picker') }}</span>
                        <div v-if="list.picker_name" class="pk-sig-name">{{ list.picker_name }}</div>
                        <div class="pk-sig-line"></div>
                    </div>
                    <div class="pk-sig">
                        <span class="pk-sig-title">{{ $t('pk_print_sig_checker') }}</span>
                        <div class="pk-sig-line"></div>
                    </div>
                    <div class="pk-sig">
                        <span class="pk-sig-title">{{ $t('mv_print_sig_keeper') }}</span>
                        <div class="pk-sig-line"></div>
                    </div>
                </div>

                <div class="pk-footer">
                    <span>{{ $t('cc_print_printed_at') }}: {{ printedAt }}</span>
                    <span dir="ltr">{{ list.list_number }}</span>
                </div>
            </div>
        </div>

        <template #footer>
            <el-button @click="visible = false">{{ $t('cancel') }}</el-button>
            <el-button type="primary" :disabled="!list" :loading="printing" @click="print">
                <i class="fas fa-print"></i>&nbsp;{{ $t('print_now') }}
            </el-button>
        </template>
    </el-dialog>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import PrintDocumentHeader from '@/components/admin/PrintDocumentHeader.vue';
import { formatDateTime } from '@/utils/transfers';

/**
 * The picker's paper: the lines in the order the shelves are walked, with a
 * box for what was taken and a tick for done. Once picking is under way it
 * can carry what was actually picked instead, with shortages marked.
 */
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    /** The list as its detail page holds it, lines in walking order. */
    list: { type: Object, default: null },
});
const emit = defineEmits(['update:modelValue']);

const { t } = useI18n();

const SHEET_ID = 'picking-list-printable-doc';
const IFRAME_ID = 'pk-print-hidden-iframe';

const visible = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const settings = reactive({
    showLogo: true,
    showPicked: false,
    showBarcode: true,
    showSignatures: true,
});

const printing = ref(false);
const printedAt = ref('');

const items = computed(() => props.list?.items || []);
const hasPicks = computed(() => items.value.some((item) => item.status !== 'pending'));
const isShort = (item) => settings.showPicked && item.status !== 'pending' && Number(item.quantity_picked) < Number(item.quantity_to_pick);
const shortCount = computed(() => items.value.filter(isShort).length);

// Quantities only add up within one unit: "12 قطعة · 3 كرتونة".
const quantitySummary = computed(() => {
    const totals = new Map();
    items.value.forEach((item) => {
        const unit = item.unit || t('qt_print_pcs');
        totals.set(unit, (totals.get(unit) || 0) + (Number(item.quantity_to_pick) || 0));
    });
    if (!totals.size) return '0';
    return [...totals].map(([unit, qty]) => `${qty} ${unit}`).join(' · ');
});

// A list not yet started is a sheet to fill in; a finished one is a record.
watch(() => props.modelValue, (open) => {
    if (!open) return;
    const d = new Date();
    printedAt.value = `${d.toLocaleDateString('en-CA')} ${d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })}`;
    settings.showPicked = props.list?.status === 'completed';
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
        const doc = iframe.contentWindow.document;
        doc.open();
        doc.write(`<!DOCTYPE html><html dir="rtl" lang="ar"><head><meta charset="utf-8">
            <base href="${window.location.origin}/"><title>${escapeHtml(`${t('pk_print_title')} ${props.list?.list_number || ''}`)}</title>
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
.pk-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px 18px;
    padding: 10px 14px;
    margin-bottom: 10px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
}
.pk-toolbar-group { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 14px; }

.pk-stage { min-height: 300px; background: #f1f5f9; padding: 16px; border-radius: 8px; overflow-x: auto; }

.pk-sheet {
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
.pk-mono { font-family: ui-monospace, monospace; font-weight: 700; }
.pk-muted { color: #94a3b8; }

.pk-watermark { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; pointer-events: none; z-index: 2; overflow: hidden; }
.pk-watermark span { transform: rotate(-24deg); font-size: 80px; font-weight: 900; color: rgba(185, 28, 28, 0.13); border: 7px solid rgba(185, 28, 28, 0.13); border-radius: 16px; padding: 4px 36px; white-space: nowrap; }

.pk-facts { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px 8px; margin: 12px 0; font-size: 11px; break-inside: avoid; }
.pk-facts > div { border: 1px solid #e2e8f0; border-radius: 6px; padding: 5px 8px; display: flex; flex-direction: column; min-width: 0; }
.pk-facts > div.wide { grid-column: span 2; }
.pk-facts span { font-size: 9.5px; color: #64748b; font-weight: 600; }
.pk-facts strong { overflow-wrap: anywhere; }
.pk-facts .pr-urgent, .pk-facts .pr-high { color: #b91c1c; }
.pk-write-in { display: block; min-height: 18px; border-bottom: 1px dashed #94a3b8; }

.pk-table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 10px; }
.pk-table thead { display: table-header-group; }
.pk-table thead th { background: #1e3a8a; color: #ffffff; border: 1px solid #1e3a8a; padding: 6px 8px; text-align: right; font-weight: 800; font-size: 10.5px; }
.pk-table tbody td { border: 1px solid #e2e8f0; border-inline-color: #f1f5f9; padding: 6px 8px; text-align: right; vertical-align: middle; font-variant-numeric: tabular-nums; }
.pk-table tbody tr:nth-child(even) { background: #f8fafc; }
.pk-table .c-center { text-align: center; }
/* Each row reads as one unit: a clear rule under it, none of it split across pages. */
.pk-table tbody tr.pk-item-row td { border-bottom-color: #cbd5e1; }
.pk-table tbody tr.pk-item-row { break-inside: avoid; page-break-inside: avoid; }
.pk-table tbody tr.short { background: #fef2f2; }
.pk-idx { color: #64748b; font-weight: 700; font-size: 10px; }
.pk-bin { display: block; font-family: 'JetBrains Mono', monospace; font-size: 11px; font-weight: 800; color: #1e293b; }
.pk-bin-loc { display: block; font-size: 9px; color: #64748b; }
.pk-item-cell { overflow-wrap: anywhere; word-break: break-word; unicode-bidi: plaintext; }
.pk-item-name { font-weight: 700; font-size: 11.5px; line-height: 1.45; }
.pk-sku { display: inline-block; margin-top: 1px; background: #f1f5f9; padding: 1px 4px; border-radius: 4px; font-size: 9px; color: #475569; font-family: 'JetBrains Mono', monospace; }
.pk-barcode { display: inline-block; margin-inline-start: 6px; font-size: 9px; color: #475569; font-family: 'JetBrains Mono', monospace; }
.pk-qty-cell { font-weight: 800; background: #f8fafc; }
.pk-qty-num { display: block; font-size: 14px; line-height: 1.2; }
.pk-unit { display: block; font-size: 9px; font-weight: 600; color: #64748b; line-height: 1.2; }
.pk-picked { font-weight: 800; font-size: 13px; color: #15803d; }
.pk-picked.short { color: #b91c1c; }
.pk-count-box { display: inline-block; width: 62px; height: 22px; border: 1.5px solid #64748b; border-radius: 4px; }
.pk-check-box { display: inline-block; width: 16px; height: 16px; border: 1.5px solid #64748b; border-radius: 3px; position: relative; }
.pk-check-box.done { background: #15803d; border-color: #15803d; }

.pk-summary-row td { background: #f8fafc; border: 1px solid #e2e8f0; border-top: 2px solid #cbd5e1; padding: 6px 10px; font-size: 11px; break-before: avoid; }
.pk-sum-label { color: #334155; }
.pk-sum-label i, .pk-sum-count { color: #1e3a8a; }
.pk-sum-count { font-weight: 800; margin-inline: 2px; }
.short-text { color: #b91c1c; font-weight: 700; }

.pk-instructions { margin: 0 0 10px; font-size: 10px; color: #475569; }
.pk-notes { border: 1px dashed #cbd5e1; border-radius: 6px; padding: 8px 10px; font-size: 10.5px; background: #fffbeb; break-inside: avoid; }
.pk-notes p { margin: 4px 0 0; white-space: pre-line; unicode-bidi: plaintext; }

.pk-signatures { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 20px; padding-top: 10px; border-top: 1px solid #e2e8f0; break-inside: avoid; }
.pk-sig { display: flex; flex-direction: column; align-items: center; text-align: center; }
.pk-sig-title { font-size: 10px; font-weight: 700; color: #334155; margin-bottom: 26px; }
.pk-sig-name { font-size: 10px; color: #475569; margin-top: -20px; margin-bottom: 8px; }
.pk-sig-line { width: 85%; border-bottom: 1px solid #94a3b8; }

.pk-footer { display: flex; justify-content: space-between; margin-top: 12px; padding-top: 6px; border-top: 1px solid #f1f5f9; font-size: 9px; color: #94a3b8; }

@media (max-width: 768px) {
    .pk-facts { grid-template-columns: 1fr 1fr; }
}
</style>
