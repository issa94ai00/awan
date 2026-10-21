<template>
    <el-dialog
        v-model="visible"
        :title="$t('pa_print_preview')"
        width="940px"
        class="pa-print-dialog"
        append-to-body
        destroy-on-close
        top="3vh"
    >
        <!-- Print settings (screen only) -->
        <div class="pa-toolbar">
            <el-radio-group v-model="mode" size="small">
                <el-radio-button value="slip"><i class="fas fa-file-lines"></i> {{ $t('pa_print_mode_slip') }}</el-radio-button>
                <el-radio-button value="labels"><i class="fas fa-tags"></i> {{ $t('pa_print_mode_labels') }}</el-radio-button>
            </el-radio-group>
            <div class="pa-toolbar-group">
                <el-checkbox v-model="settings.showLogo">{{ $t('po_show_logo') }}</el-checkbox>
                <el-checkbox v-if="mode === 'slip'" v-model="settings.showSignatures">{{ $t('po_show_signatures') }}</el-checkbox>
                <el-checkbox v-if="mode === 'labels'" v-model="settings.showContents">{{ $t('pa_print_label_contents') }}</el-checkbox>
            </div>
        </div>

        <div class="pa-stage">
            <div v-if="list" :id="SHEET_ID" class="pa-sheet" :class="`mode-${mode}`" dir="rtl" lang="ar">
                <!-- ── Packing slip: what is in each package ── -->
                <template v-if="mode === 'slip'">
                    <div v-if="list.status === 'cancelled'" class="pa-watermark"><span>{{ list.status_text }}</span></div>

                    <PrintDocumentHeader
                        header-style="compact"
                        :show-logo="settings.showLogo"
                        :show-contacts="false"
                        :show-meta="false"
                        :title="$t('pa_print_slip_title')"
                        subtitle="PACKING SLIP"
                        :document-number="list.list_number"
                        banner-src="/Header.jpeg"
                    />

                    <div class="pa-ship-grid">
                        <div class="pa-card">
                            <div class="pa-card-head"><i class="fas fa-user"></i> {{ $t('pa_print_ship_to') }}</div>
                            <div class="pa-card-body">
                                <strong class="pa-big">{{ list.customer_name || '—' }}</strong>
                                <div v-if="list.customer_phone" class="pa-line" dir="ltr">{{ list.customer_phone }}</div>
                                <div v-if="list.delivery_address" class="pa-line">{{ list.delivery_address }}</div>
                            </div>
                        </div>
                        <div class="pa-card">
                            <div class="pa-card-head"><i class="fas fa-box"></i> {{ $t('pa_print_shipment') }}</div>
                            <div class="pa-card-body pa-pairs">
                                <span>{{ $t('pk_print_order') }}</span><strong class="pa-mono" dir="ltr">{{ list.order_number || '—' }}</strong>
                                <span>{{ $t('wms.picking_list') }}</span><strong class="pa-mono" dir="ltr">{{ list.picking_list_number || '—' }}</strong>
                                <span>{{ $t('warehouse') }}</span><strong>{{ list.warehouse_name || '—' }}</strong>
                                <span>{{ $t('wms.total_packages') }}</span><strong>{{ packages.length }}</strong>
                                <span>{{ $t('wms.total_weight') }}</span><strong dir="ltr">{{ weightText(totalWeight) }}</strong>
                                <template v-if="list.carrier || list.tracking_number">
                                    <span>{{ $t('pa_print_carrier') }}</span>
                                    <strong>{{ list.carrier || '—' }}<span v-if="list.tracking_number" class="pa-mono" dir="ltr"> · {{ list.tracking_number }}</span></strong>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div v-if="list.packing_instructions" class="pa-instructions">
                        <strong><i class="fas fa-triangle-exclamation"></i> {{ $t('pa_print_instructions') }}:</strong> {{ list.packing_instructions }}
                    </div>

                    <section v-for="(pkg, idx) in packages" :key="pkg.key" class="pa-package">
                        <div class="pa-package-head">
                            <strong>{{ pkg.number ? $t('pa_print_package_n', { n: idx + 1, total: packages.length }) : $t('pa_print_unassigned') }}</strong>
                            <span v-if="pkg.number" class="pa-mono" dir="ltr">{{ pkg.number }}</span>
                            <span v-if="pkg.weight" dir="ltr">{{ weightText(pkg.weight) }}</span>
                            <span v-if="pkg.dimensions" dir="ltr">{{ pkg.dimensions }}</span>
                            <span v-if="pkg.fragile" class="pa-fragile"><i class="fas fa-wine-glass"></i> {{ $t('wms.fragile') }}</span>
                        </div>
                        <table class="pa-table">
                            <thead>
                                <tr>
                                    <th class="c-center" style="width: 30px;">#</th>
                                    <th>{{ $t('product') }}</th>
                                    <th class="c-center" style="width: 80px;">{{ $t('quantity') }}</th>
                                    <th class="c-center" style="width: 48px;">✓</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, i) in pkg.items" :key="item.id || i" class="pa-item-row">
                                    <td class="c-center pa-idx">{{ i + 1 }}</td>
                                    <td class="pa-item-cell">
                                        <div class="pa-item-name">{{ item.product_name || '—' }}</div>
                                        <code v-if="item.sku" class="pa-sku">{{ item.sku }}</code>
                                        <span v-if="item.notes" class="pa-item-note">{{ item.notes }}</span>
                                    </td>
                                    <td class="c-center pa-qty-cell">
                                        <span class="pa-qty-num">{{ item.quantity }}</span>
                                        <span v-if="item.unit" class="pa-unit">{{ item.unit }}</span>
                                    </td>
                                    <td class="c-center"><span class="pa-check-box"></span></td>
                                </tr>
                            </tbody>
                        </table>
                    </section>

                    <div class="pa-total">
                        <span>{{ $t('qt_print_total_qty') }}:</span> <strong>{{ quantitySummary }}</strong>
                    </div>

                    <div v-if="list.notes" class="pa-notes">
                        <strong><i class="fas fa-clipboard-list"></i> {{ $t('notes') }}:</strong>
                        <p>{{ list.notes }}</p>
                    </div>

                    <div v-if="settings.showSignatures" class="pa-signatures">
                        <div class="pa-sig">
                            <span class="pa-sig-title">{{ $t('pa_print_sig_packer') }}</span>
                            <div v-if="list.packer_name" class="pa-sig-name">{{ list.packer_name }}</div>
                            <div class="pa-sig-line"></div>
                        </div>
                        <div class="pa-sig">
                            <span class="pa-sig-title">{{ $t('pk_print_sig_checker') }}</span>
                            <div class="pa-sig-line"></div>
                        </div>
                        <div class="pa-sig">
                            <span class="pa-sig-title">{{ $t('trf_sig_driver') }}</span>
                            <div class="pa-sig-line"></div>
                        </div>
                        <div class="pa-sig">
                            <span class="pa-sig-title">{{ $t('pa_print_sig_customer') }}</span>
                            <div class="pa-sig-line"></div>
                        </div>
                    </div>
                </template>

                <!-- ── Labels: one per package, to stick on the box ── -->
                <div v-else class="pa-labels">
                    <div v-for="(pkg, idx) in labelPackages" :key="pkg.key" class="pa-label">
                        <div class="pa-label-head">
                            <img v-if="settings.showLogo && !logoFailed" :src="logoUrl" alt="" class="pa-label-logo" @error="logoFailed = true" />
                            <span class="pa-label-count">{{ $t('pa_print_package_n', { n: idx + 1, total: labelPackages.length }) }}</span>
                        </div>
                        <div class="pa-label-to">
                            <span class="pa-label-caption">{{ $t('pa_print_ship_to') }}</span>
                            <strong class="pa-label-name">{{ list.customer_name || '—' }}</strong>
                            <div v-if="list.customer_phone" class="pa-label-phone" dir="ltr">{{ list.customer_phone }}</div>
                            <div v-if="list.delivery_address" class="pa-label-address">{{ list.delivery_address }}</div>
                        </div>
                        <div class="pa-label-meta">
                            <div><span>{{ $t('pk_print_order') }}</span><strong class="pa-mono" dir="ltr">{{ list.order_number || '—' }}</strong></div>
                            <div v-if="pkg.weight"><span>{{ $t('wms.weight') }}</span><strong dir="ltr">{{ weightText(pkg.weight) }}</strong></div>
                            <div v-if="list.tracking_number"><span>{{ $t('tracking_number') }}</span><strong class="pa-mono" dir="ltr">{{ list.tracking_number }}</strong></div>
                        </div>
                        <ul v-if="settings.showContents" class="pa-label-contents">
                            <li v-for="item in pkg.items" :key="item.id">{{ item.product_name }} × {{ item.quantity }}</li>
                        </ul>
                        <div class="pa-label-foot">
                            <span v-if="pkg.fragile" class="pa-label-fragile"><i class="fas fa-wine-glass"></i> {{ $t('pa_print_fragile_handle') }}</span>
                            <span class="pa-label-from">{{ $t('pa_print_from') }}: {{ list.warehouse_name || '—' }}</span>
                        </div>
                    </div>
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
import { formatNumber } from '@/utils/currency';
import { useSettingsStore } from '@/stores/settings';

/**
 * Two papers for a packing list. The packing slip goes in or with the
 * shipment: who it is for and what is in each package, to be checked off on
 * arrival. The labels go on the boxes: one per package, readable at arm's
 * length, with "n of m" so a missing box is noticed.
 */
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    /** The list as its detail page holds it, lines included. */
    list: { type: Object, default: null },
});
const emit = defineEmits(['update:modelValue']);

const { t } = useI18n();

const SHEET_ID = 'packing-list-printable-doc';
const IFRAME_ID = 'pa-print-hidden-iframe';

const visible = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const mode = ref('slip');
const settings = reactive({ showLogo: true, showSignatures: true, showContents: true });
const printing = ref(false);

// The site's logo, found the way the printed letterhead finds it.
const settingsStore = useSettingsStore();
const logoFailed = ref(false);
const logoUrl = computed(() => {
    const logo = settingsStore?.data?.site_logo || settingsStore?.data?.logo
        || window.systemData?.settings?.site_logo || window.systemData?.settings?.logo;
    if (!logo) return '/assets/images/logo.png';
    return /^https?:\/\//.test(logo) || logo.startsWith('/') ? logo : `/${logo}`;
});

const weightText = (kg) => `${formatNumber(kg)} ${t('wms.kg')}`;

// A package is the lines that share its number; its size and care travel on
// each of them.
const packages = computed(() => {
    const groups = new Map();
    (props.list?.items || []).forEach((item) => {
        const number = item.package_number ? String(item.package_number) : '';
        const key = number || '__none__';
        const pkg = groups.get(key) || { key, number, items: [], weight: 0, dimensions: '', fragile: false };
        pkg.items.push(item);
        pkg.weight += Number(item.weight) || 0;
        pkg.dimensions = pkg.dimensions || item.dimensions || '';
        pkg.fragile = pkg.fragile || Boolean(item.fragile);
        groups.set(key, pkg);
    });
    // Numbered packages first, in their own order; anything not yet boxed last.
    return [...groups.values()].sort((a, b) => {
        if (!a.number) return 1;
        if (!b.number) return -1;
        return a.number.localeCompare(b.number, 'en', { numeric: true });
    });
});

// Lines not yet put in a box have no box to label.
const labelPackages = computed(() => {
    const numbered = packages.value.filter((pkg) => pkg.number);
    return numbered.length ? numbered : packages.value;
});

const totalWeight = computed(() => Number(props.list?.total_weight) || packages.value.reduce((sum, pkg) => sum + pkg.weight, 0));

// Quantities only add up within one unit: "12 قطعة · 3 كرتونة".
const quantitySummary = computed(() => {
    const totals = new Map();
    (props.list?.items || []).forEach((item) => {
        const unit = item.unit || t('qt_print_pcs');
        totals.set(unit, (totals.get(unit) || 0) + (Number(item.quantity) || 0));
    });
    if (!totals.size) return '0';
    return [...totals].map(([unit, qty]) => `${qty} ${unit}`).join(' · ');
});

watch(() => props.modelValue, (open) => {
    if (open) mode.value = 'slip';
});

const PRINT_STYLES = `
    @page { size: A4 portrait; margin: 0 !important; }
    * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; box-sizing: border-box !important; }
    html, body { margin: 0 !important; padding: 0 !important; background: #fff !important; color: #0f172a !important; direction: rtl !important; height: auto !important; overflow: visible !important; }
    a[href]:after, abbr[title]:after { content: none !important; }
    .brand-domain-badge, .domain-badge { display: none !important; }
    /* A print stylesheet another screen left loaded may hide every element. */
    #${SHEET_ID}, #${SHEET_ID} * { visibility: visible !important; }
    #${SHEET_ID} { width: 100% !important; margin: 0 !important; padding: 10mm 12mm !important; border: none !important; border-radius: 0 !important; box-shadow: none !important; }
    #${SHEET_ID}.mode-labels { padding: 8mm !important; }
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
        const title = mode.value === 'slip' ? t('pa_print_slip_title') : t('pa_print_mode_labels');
        const doc = iframe.contentWindow.document;
        doc.open();
        doc.write(`<!DOCTYPE html><html dir="rtl" lang="ar"><head><meta charset="utf-8">
            <base href="${window.location.origin}/"><title>${escapeHtml(`${title} ${props.list?.list_number || ''}`)}</title>
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
.pa-toolbar {
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
.pa-toolbar-group { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 14px; }

.pa-stage { min-height: 300px; background: #f1f5f9; padding: 16px; border-radius: 8px; overflow-x: auto; }

.pa-sheet {
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
.pa-mono { font-family: ui-monospace, monospace; font-weight: 700; }

.pa-watermark { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; pointer-events: none; z-index: 2; overflow: hidden; }
.pa-watermark span { transform: rotate(-24deg); font-size: 80px; font-weight: 900; color: rgba(185, 28, 28, 0.13); border: 7px solid rgba(185, 28, 28, 0.13); border-radius: 16px; padding: 4px 36px; white-space: nowrap; }

/* ── Slip ── */
.pa-ship-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 12px 0; break-inside: avoid; }
.pa-card { border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
.pa-card-head { background: #f0f9ff; color: #075985; font-weight: 800; font-size: 11px; padding: 5px 10px; display: flex; align-items: center; gap: 6px; }
.pa-card-body { padding: 8px 10px; font-size: 11px; }
.pa-big { font-size: 14px; }
.pa-line { color: #334155; margin-top: 2px; overflow-wrap: anywhere; }
.pa-pairs { display: grid; grid-template-columns: auto 1fr; gap: 3px 10px; align-items: center; }
.pa-pairs > span { color: #64748b; font-weight: 600; }
.pa-instructions { font-size: 11px; background: #fff7ed; border-inline-start: 3px solid #f97316; border-radius: 4px; padding: 6px 10px; margin-bottom: 10px; }

.pa-package { margin-bottom: 10px; break-inside: avoid; }
.pa-package-head { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 14px; background: #075985; color: #fff; border-radius: 6px 6px 0 0; padding: 5px 10px; font-size: 11px; }
.pa-fragile { background: #fef3c7; color: #92400e; border-radius: 999px; padding: 0 8px; font-weight: 800; }
.pa-table { width: 100%; border-collapse: collapse; font-size: 11px; }
.pa-table thead th { background: #f1f5f9; color: #334155; border: 1px solid #e2e8f0; padding: 5px 8px; text-align: right; font-weight: 800; font-size: 10px; }
.pa-table tbody td { border: 1px solid #e2e8f0; border-inline-color: #f1f5f9; padding: 6px 8px; text-align: right; vertical-align: middle; font-variant-numeric: tabular-nums; }
.pa-table .c-center { text-align: center; }
.pa-table tbody tr.pa-item-row td { border-bottom-color: #cbd5e1; }
.pa-table tbody tr.pa-item-row { break-inside: avoid; page-break-inside: avoid; }
.pa-idx { color: #64748b; font-weight: 700; font-size: 10px; }
.pa-item-cell { overflow-wrap: anywhere; word-break: break-word; unicode-bidi: plaintext; }
.pa-item-name { font-weight: 700; font-size: 11.5px; line-height: 1.45; }
.pa-sku { display: inline-block; margin-top: 1px; background: #f1f5f9; padding: 1px 4px; border-radius: 4px; font-size: 9px; color: #475569; font-family: 'JetBrains Mono', monospace; }
.pa-item-note { display: block; font-size: 9.5px; color: #64748b; }
.pa-qty-cell { font-weight: 800; background: #f8fafc; }
.pa-qty-num { display: block; font-size: 13px; line-height: 1.2; }
.pa-unit { display: block; font-size: 9px; font-weight: 600; color: #64748b; line-height: 1.2; }
.pa-check-box { display: inline-block; width: 16px; height: 16px; border: 1.5px solid #64748b; border-radius: 3px; }

.pa-total { font-size: 11px; text-align: left; margin: 4px 0 10px; }
.pa-notes { border: 1px dashed #cbd5e1; border-radius: 6px; padding: 8px 10px; font-size: 10.5px; background: #fffbeb; break-inside: avoid; }
.pa-notes p { margin: 4px 0 0; white-space: pre-line; unicode-bidi: plaintext; }

.pa-signatures { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-top: 20px; padding-top: 10px; border-top: 1px solid #e2e8f0; break-inside: avoid; }
.pa-sig { display: flex; flex-direction: column; align-items: center; text-align: center; }
.pa-sig-title { font-size: 10px; font-weight: 700; color: #334155; margin-bottom: 26px; }
.pa-sig-name { font-size: 10px; color: #475569; margin-top: -20px; margin-bottom: 8px; }
.pa-sig-line { width: 85%; border-bottom: 1px solid #94a3b8; }

/* ── Labels: two to a row, each kept whole ── */
.pa-labels { display: grid; grid-template-columns: 1fr 1fr; gap: 6mm; }
.pa-label {
    border: 2px solid #0f172a;
    border-radius: 8px;
    padding: 10px 12px;
    min-height: 88mm;
    display: flex;
    flex-direction: column;
    gap: 6px;
    break-inside: avoid;
    page-break-inside: avoid;
}
.pa-label-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; border-bottom: 1px solid #cbd5e1; padding-bottom: 5px; }
.pa-label-logo { height: 26px; object-fit: contain; }
.pa-label-count { font-size: 15px; font-weight: 900; margin-inline-start: auto; }
.pa-label-to { display: flex; flex-direction: column; }
.pa-label-caption { font-size: 9.5px; color: #64748b; font-weight: 700; }
.pa-label-name { font-size: 18px; font-weight: 900; line-height: 1.3; overflow-wrap: anywhere; }
.pa-label-phone { font-size: 14px; font-weight: 800; text-align: right; }
.pa-label-address { font-size: 12px; line-height: 1.5; overflow-wrap: anywhere; }
.pa-label-meta { display: flex; flex-wrap: wrap; gap: 4px 14px; font-size: 11px; }
.pa-label-meta > div { display: flex; flex-direction: column; }
.pa-label-meta span { font-size: 9px; color: #64748b; font-weight: 600; }
.pa-label-contents { margin: 0; padding-inline-start: 16px; font-size: 9.5px; color: #334155; flex: 1; }
.pa-label-foot { display: flex; align-items: center; justify-content: space-between; gap: 6px; margin-top: auto; font-size: 9.5px; color: #475569; }
.pa-label-fragile { background: #fef3c7; color: #92400e; border: 1px solid #f59e0b; border-radius: 4px; padding: 1px 8px; font-weight: 900; font-size: 11px; }

@media (max-width: 768px) {
    .pa-ship-grid, .pa-labels { grid-template-columns: 1fr; }
    .pa-signatures { grid-template-columns: repeat(2, 1fr); }
}
</style>
