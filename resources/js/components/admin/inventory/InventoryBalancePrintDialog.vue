<template>
    <el-dialog
        v-model="visible"
        :title="sheetMode === 'audit' ? $t('inv_admin_print_count_sheet') : $t('inv_admin_print_sheet')"
        width="1000px"
        class="inv-print-dialog"
        append-to-body
        destroy-on-close
        top="3vh"
    >
        <!-- Print settings toolbar (screen only) -->
        <div class="inv-print-toolbar">
            <div class="inv-print-toolbar-group">
                <el-radio-group v-model="sheetMode" size="small" class="mode-selector">
                    <el-radio-button value="balance">{{ $t('inv_admin_print_sheet') }}</el-radio-button>
                    <el-radio-button value="audit">{{ $t('inv_admin_print_count_sheet') }}</el-radio-button>
                </el-radio-group>

                <el-checkbox v-model="settings.showLogo">{{ $t('po_show_logo') }}</el-checkbox>
                <el-checkbox v-if="!readOnly && sheetMode === 'balance'" v-model="settings.showCosts">{{ $t('trf_print_show_costs') }}</el-checkbox>
                <el-checkbox v-model="settings.showSignatures">{{ $t('po_show_signatures') }}</el-checkbox>
                <el-checkbox v-model="settings.hideZero">{{ $t('hide_zero_balances') || 'إخفاء الأرصدة الصفرية' }}</el-checkbox>
            </div>
            <span v-if="!loading" class="inv-print-count">
                {{ selectedRows?.length ? $t('inv_admin_selected_count', { count: displayedRows.length }) : $t('mv_print_rows_n', { count: displayedRows.length }) }}
            </span>
        </div>

        <el-alert
            v-if="truncated"
            type="warning"
            :closable="false"
            show-icon
            class="mb-3"
            :title="$t('mv_print_truncated', { shown: displayedRows.length, total: totalRows })"
        />

        <div v-loading="loading" class="inv-print-stage">
            <div :id="SHEET_ID" class="inv-sheet" dir="rtl" lang="ar">
                <PrintDocumentHeader
                    header-style="compact"
                    :show-logo="settings.showLogo"
                    :show-contacts="false"
                    :show-meta="false"
                    :title="sheetTitle"
                    :subtitle="sheetSubtitle"
                    :document-number="sheetNumber"
                    banner-src="/Header.jpeg"
                />

                <!-- Metadata / filter summary -->
                <div class="inv-sheet-meta">
                    <div class="meta-item">
                        <span class="meta-label">{{ $t('the_date') }}:</span>
                        <strong class="meta-val" dir="ltr">{{ printedAt }}</strong>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">{{ $t('warehouse') }}:</span>
                        <strong class="meta-val">{{ filterWarehouseName }}</strong>
                    </div>
                    <div v-if="filters?.status" class="meta-item">
                        <span class="meta-label">{{ $t('status') }}:</span>
                        <strong class="meta-val">{{ statusLabel(filters.status) }}</strong>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">{{ $t('total_items') || 'إجمالي الأصناف' }}:</span>
                        <strong class="meta-val">{{ formatNumber(displayedRows.length) }}</strong>
                    </div>
                </div>

                <!-- KPI totals strip -->
                <div class="inv-sheet-kpis">
                    <div class="kpi-box">
                        <span>{{ $t('inv_admin_on_hand') }}</span>
                        <strong>{{ formatNumber(totals.quantity) }}</strong>
                    </div>
                    <div class="kpi-box">
                        <span>{{ $t('reserved') }}</span>
                        <strong class="warn">{{ formatNumber(totals.reserved) }}</strong>
                    </div>
                    <div class="kpi-box">
                        <span>{{ $t('available_amount') }}</span>
                        <strong class="ok">{{ formatNumber(totals.available) }}</strong>
                    </div>
                    <div v-if="settings.showCosts && !readOnly && sheetMode === 'balance'" class="kpi-box">
                        <span>{{ $t('total_stock_value') }}</span>
                        <strong class="primary">{{ formatMoney(totals.value) }}</strong>
                    </div>
                </div>

                <!-- The table -->
                <table class="inv-sheet-table">
                    <thead>
                        <tr>
                            <th class="c-center" style="width: 32px;">#</th>
                            <th style="min-width: 180px;">{{ $t('product') }}</th>
                            <th style="width: 110px;">SKU</th>
                            <th style="width: 110px;">{{ $t('warehouse') }}</th>
                            <th class="c-center" style="width: 75px;">{{ $t('inv_admin_on_hand') }}</th>
                            <th class="c-center" style="width: 70px;">{{ $t('reserved') }}</th>
                            <th class="c-center" style="width: 80px;">{{ $t('available') }}</th>

                            <!-- In audit/physical count mode: blank columns for manual verification -->
                            <template v-if="sheetMode === 'audit'">
                                <th class="c-center audit-col" style="width: 100px;">{{ $t('inv_admin_physical_count_blank') }}</th>
                                <th class="audit-col" style="min-width: 120px;">{{ $t('inv_admin_variance_notes') }}</th>
                            </template>

                            <!-- In balance mode: costs if requested -->
                            <template v-else-if="settings.showCosts && !readOnly">
                                <th class="c-end" style="width: 85px;">{{ $t('cost') }}</th>
                                <th class="c-end" style="width: 95px;">{{ $t('value') }}</th>
                            </template>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, idx) in displayedRows" :key="row.id || idx" class="inv-sheet-row">
                            <td class="c-center num-idx">{{ idx + 1 }}</td>
                            <td class="product-cell">
                                <strong class="p-name">{{ productName(row) }}</strong>
                                <span v-if="row.bin?.code" class="p-bin" dir="ltr">[{{ row.bin.code }}]</span>
                            </td>
                            <td class="sku-cell" dir="ltr"><code>{{ row.product?.sku || '—' }}</code></td>
                            <td class="wh-cell">{{ row.warehouse?.name || '—' }}</td>
                            <td class="c-center" dir="ltr"><strong>{{ formatNumber(row.quantity) }}</strong></td>
                            <td class="c-center text-muted" dir="ltr">{{ Number(row.reserved_quantity) > 0 ? formatNumber(row.reserved_quantity) : '—' }}</td>
                            <td class="c-center num-available" dir="ltr">
                                <span :class="stockLevelClass(row)">{{ formatNumber(row.available) }}</span>
                            </td>

                            <template v-if="sheetMode === 'audit'">
                                <td class="c-center audit-blank"><div class="blank-box"></div></td>
                                <td class="audit-notes"><div class="blank-line"></div></td>
                            </template>

                            <template v-else-if="settings.showCosts && !readOnly">
                                <td class="c-end" dir="ltr">{{ Number(row.product?.cost_price) > 0 ? formatMoney(row.product.cost_price) : '—' }}</td>
                                <td class="c-end bold" dir="ltr">{{ rowValue(row) > 0 ? formatMoney(rowValue(row)) : '—' }}</td>
                            </template>
                        </tr>
                    </tbody>
                </table>

                <!-- Signatures -->
                <div v-if="settings.showSignatures" class="inv-sheet-signatures">
                    <div class="sig-col">
                        <span class="sig-title">{{ $t('inv_admin_keeper_sig') }}</span>
                        <div class="sig-line"></div>
                        <span class="sig-name">{{ $t('name_and_signature') || 'الاسم والتوقيع: ..................' }}</span>
                    </div>
                    <div class="sig-col">
                        <span class="sig-title">{{ $t('inv_admin_auditor_sig') }}</span>
                        <div class="sig-line"></div>
                        <span class="sig-name">{{ $t('name_and_signature') || 'الاسم والتوقيع: ..................' }}</span>
                    </div>
                    <div class="sig-col">
                        <span class="sig-title">{{ $t('inv_admin_manager_sig') }}</span>
                        <div class="sig-line"></div>
                        <span class="sig-name">{{ $t('name_and_signature') || 'الاسم والتوقيع: ..................' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="dialog-footer">
                <el-button @click="visible = false">{{ $t('cancel') }}</el-button>
                <el-button type="primary" :icon="Printer" :loading="printing" @click="print">
                    {{ $t('print') }}
                </el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script setup>
import PrintDocumentHeader from '@/components/admin/PrintDocumentHeader.vue';
import { inventoryApi } from '@/api/inventory';
import { formatMoney, formatNumber } from '@/utils/currency';
import { useI18n } from 'vue-i18n';
import { ref, reactive, computed, watch, nextTick } from 'vue';
import { Printer } from '@element-plus/icons-vue';

const SHEET_ID = 'inv-balance-print-sheet';
const IFRAME_ID = 'inv-balance-print-iframe';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    filters: { type: Object, default: () => ({}) },
    warehouses: { type: Array, default: () => [] },
    selectedRows: { type: Array, default: () => [] },
    readOnly: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);
const { t } = useI18n();

const visible = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
});

const sheetMode = ref('balance'); // 'balance' or 'audit'
const loading = ref(false);
const printing = ref(false);
const rows = ref([]);
const totalRows = ref(0);
const printedAt = ref('');

const settings = reactive({
    showLogo: true,
    showCosts: false,
    showSignatures: true,
    hideZero: false,
});

const truncated = computed(() => totalRows.value > rows.value.length);

const sheetTitle = computed(() =>
    sheetMode.value === 'audit' ? t('inv_admin_print_count_sheet') : t('inv_admin_print_title')
);

const sheetSubtitle = computed(() =>
    sheetMode.value === 'audit' ? 'PHYSICAL INVENTORY COUNT SHEET' : 'WAREHOUSE BALANCES STATEMENT'
);

const sheetNumber = computed(() => {
    const d = new Date();
    const dateStr = d.toISOString().slice(0, 10).replace(/-/g, '');
    return `INV-${dateStr}`;
});

const filterWarehouseName = computed(() => {
    if (props.filters?.warehouse_id) {
        const found = props.warehouses.find((w) => w.id === props.filters.warehouse_id);
        if (found) return found.name;
    }
    return t('inv_admin_all_warehouses');
});

const statusLabel = (status) => ({
    ok: t('available'),
    low: t('low'),
    out: t('out_of_stock_short'),
}[status] || status);

const productName = (row) =>
    row.product?.name_ar || row.product?.name_en || row.product?.name || `Product #${row.product_id}`;

const rowValue = (row) =>
    Math.max(0, Number(row.available) || 0) * (Number(row.product?.cost_price) || 0);

const stockLevelClass = (row) => {
    const avail = Number(row.available ?? 0);
    if (avail <= 0) return 'text-danger font-bold';
    if (avail <= Number(row.reorder_point ?? 0)) return 'text-warning font-bold';
    return 'text-success font-bold';
};

const displayedRows = computed(() => {
    let list = props.selectedRows?.length ? props.selectedRows : rows.value;
    if (settings.hideZero) {
        list = list.filter((r) => Number(r.quantity) > 0 || Number(r.available) > 0);
    }
    return list;
});

const totals = computed(() => {
    return displayedRows.value.reduce(
        (acc, r) => {
            acc.quantity += Number(r.quantity) || 0;
            acc.reserved += Number(r.reserved_quantity) || 0;
            acc.available += Math.max(0, Number(r.available) || 0);
            acc.value += rowValue(r);
            return acc;
        },
        { quantity: 0, reserved: 0, available: 0, value: 0 }
    );
});

const loadData = async () => {
    if (props.selectedRows?.length) {
        rows.value = [...props.selectedRows];
        totalRows.value = props.selectedRows.length;
        return;
    }
    loading.value = true;
    try {
        const res = await inventoryApi.getStock({
            warehouse_id: props.filters?.warehouse_id || undefined,
            status: props.filters?.status || undefined,
            search: props.filters?.search || undefined,
            per_page: 300,
            sort_by: 'warehouse_id',
            sort_dir: 'asc',
        });
        const data = res.data?.data || {};
        rows.value = data.stock || [];
        totalRows.value = data.pagination?.total || rows.value.length;
    } catch {
        rows.value = [];
        totalRows.value = 0;
    } finally {
        loading.value = false;
    }
};

watch(
    () => props.modelValue,
    (open) => {
        if (!open) return;
        const d = new Date();
        printedAt.value = `${d.toLocaleDateString('en-CA')} ${d.toLocaleTimeString('en-GB', {
            hour: '2-digit',
            minute: '2-digit',
        })}`;
        loadData();
    },
    { immediate: true }
);

const PRINT_STYLES = `
    @page { size: A4 landscape; margin: 8mm 10mm !important; }
    * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; box-sizing: border-box !important; }
    html, body { margin: 0 !important; padding: 0 !important; background: #fff !important; color: #0f172a !important; direction: rtl !important; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif !important; }
    #${SHEET_ID} { width: 100% !important; margin: 0 !important; padding: 0 !important; border: none !important; }
    .inv-sheet-table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 11px; }
    .inv-sheet-table th, .inv-sheet-table td { border: 1px solid #cbd5e1; padding: 5px 6px; text-align: start; }
    .inv-sheet-table th { background: #f1f5f9 !important; font-weight: 700; color: #1e293b; }
    .inv-sheet-table .c-center { text-align: center; }
    .inv-sheet-table .c-end { text-align: end; }
    .inv-sheet-table tbody tr:nth-child(even) { background: #f8fafc !important; }
    .blank-box { height: 18px; border: 1px dashed #94a3b8; border-radius: 3px; background: #fff; }
    .blank-line { height: 18px; border-bottom: 1px dashed #94a3b8; }
    .inv-sheet-meta { display: flex; flex-wrap: wrap; gap: 14px; padding: 6px 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 11px; margin: 8px 0; }
    .inv-sheet-kpis { display: flex; gap: 10px; margin-bottom: 8px; }
    .kpi-box { flex: 1; padding: 6px 10px; border: 1px solid #e2e8f0; border-radius: 6px; background: #f8fafc; text-align: center; }
    .kpi-box span { display: block; font-size: 10px; color: #64748b; }
    .kpi-box strong { font-size: 13px; font-weight: 800; }
    .inv-sheet-signatures { display: flex; justify-content: space-between; margin-top: 25px; page-break-inside: avoid; }
    .sig-col { width: 28%; text-align: center; }
    .sig-title { display: block; font-weight: 700; font-size: 11px; margin-bottom: 30px; }
    .sig-line { border-bottom: 1px solid #94a3b8; margin-bottom: 6px; }
    .sig-name { font-size: 10px; color: #64748b; }
`;

const escapeHtml = (text) =>
    String(text ?? '').replace(
        /[&<>"']/g,
        (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])
    );

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
        Object.assign(iframe.style, {
            position: 'fixed',
            left: '0',
            top: '0',
            width: '100vw',
            height: '100vh',
            border: '0',
            opacity: '0',
            pointerEvents: 'none',
            zIndex: '-9999',
        });
        document.body.appendChild(iframe);

        const styles = Array.from(document.querySelectorAll('link[rel="stylesheet"], style'))
            .map((node) => node.outerHTML)
            .join('\n');
        const doc = iframe.contentWindow.document;
        doc.open();
        doc.write(`<!DOCTYPE html><html dir="rtl" lang="ar"><head><meta charset="utf-8">
            <base href="${window.location.origin}/"><title>${escapeHtml(sheetTitle.value)}</title>
            ${styles}<style>${PRINT_STYLES}</style>
            </head><body>${sheet.outerHTML}</body></html>`);
        doc.close();

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
</script>

<style scoped>
.inv-print-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.75rem;
    padding: 0.6rem 0.85rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    margin-bottom: 0.75rem;
}
.inv-print-toolbar-group {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.75rem;
}
.inv-print-count {
    font-size: 0.82rem;
    font-weight: 700;
    color: #475569;
}
.inv-print-stage {
    max-height: 65vh;
    overflow-y: auto;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 1.25rem;
    background: #ffffff;
}
.inv-sheet-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1.25rem;
    padding: 0.65rem 0.9rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    margin: 0.75rem 0;
    font-size: 0.85rem;
}
.meta-item {
    display: flex;
    gap: 0.35rem;
    align-items: center;
}
.meta-label {
    color: #64748b;
}
.meta-val {
    color: #0f172a;
    font-weight: 700;
}
.inv-sheet-kpis {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 0.6rem;
    margin-bottom: 0.9rem;
}
.kpi-box {
    padding: 0.6rem 0.75rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    text-align: center;
}
.kpi-box span {
    display: block;
    font-size: 0.75rem;
    color: #64748b;
    margin-bottom: 0.15rem;
}
.kpi-box strong {
    font-size: 1.1rem;
    font-weight: 800;
    color: #0f172a;
}
.kpi-box strong.ok { color: #16a34a; }
.kpi-box strong.warn { color: #d97706; }
.kpi-box strong.primary { color: #2563eb; }

.inv-sheet-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
}
.inv-sheet-table th,
.inv-sheet-table td {
    padding: 0.45rem 0.55rem;
    border: 1px solid #e2e8f0;
    text-align: start;
}
.inv-sheet-table th {
    background: #f1f5f9;
    font-weight: 700;
    color: #334155;
}
.inv-sheet-table tbody tr:nth-child(even) {
    background: #fbfcfe;
}
.c-center { text-align: center !important; }
.c-end { text-align: end !important; }

.p-name { color: #0f172a; font-size: 0.85rem; }
.p-bin { font-size: 0.72rem; color: #64748b; margin-inline-start: 4px; }
.sku-cell code {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.75rem;
    color: #475569;
}
.blank-box {
    width: 60px;
    height: 22px;
    margin: 0 auto;
    border: 1px dashed #cbd5e1;
    border-radius: 4px;
}
.blank-line {
    width: 100%;
    height: 20px;
    border-bottom: 1px dashed #cbd5e1;
}

.inv-sheet-signatures {
    display: flex;
    justify-content: space-between;
    margin-top: 2rem;
    padding-top: 1rem;
}
.sig-col {
    width: 28%;
    text-align: center;
}
.sig-title {
    display: block;
    font-weight: 700;
    font-size: 0.85rem;
    color: #334155;
    margin-bottom: 2.5rem;
}
.sig-line {
    border-bottom: 1px solid #cbd5e1;
    margin-bottom: 0.35rem;
}
.sig-name {
    font-size: 0.75rem;
    color: #64748b;
}

.text-danger { color: #dc2626; }
.text-warning { color: #d97706; }
.text-success { color: #16a34a; }
.font-bold { font-weight: 700; }
.bold { font-weight: 700; }
.text-muted { color: #94a3b8; }
.dialog-footer { display: flex; justify-content: flex-end; gap: 0.75rem; }
</style>
