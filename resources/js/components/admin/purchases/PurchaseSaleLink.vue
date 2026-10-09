<template>
    <!-- The sale a purchase order buys in for: a sales order or a sales
         invoice. Picking one links it; the page decides whether to fill the
         lines from it. -->
    <div class="sale-link">
        <div v-if="modelValue" class="sale-card">
            <span class="sale-icon"><i class="fas" :class="modelValue.type === 'invoice' ? 'fa-file-invoice-dollar' : 'fa-cart-shopping'"></i></span>
            <div class="sale-text">
                <small>{{ modelValue.type === 'invoice' ? t('sales_invoice') : t('sales_order') }}</small>
                <strong>{{ modelValue.number || `#${modelValue.id}` }}</strong>
                <span v-if="modelValue.customer" class="sale-customer"><i class="fas fa-user"></i> {{ modelValue.customer }}</span>
            </div>
            <div class="sale-actions">
                <el-button size="small" plain @click="openSale">
                    <i class="fas fa-arrow-up-right-from-square"></i>&nbsp;{{ t('po_sale_open') }}
                </el-button>
                <el-button v-if="!disabled" size="small" text type="danger" @click="emit('update:modelValue', null)">
                    <i class="fas fa-link-slash"></i>&nbsp;{{ t('unlink') }}
                </el-button>
            </div>
        </div>

        <div v-else-if="!disabled" class="sale-picker">
            <div class="type-switch" role="radiogroup">
                <button
                    v-for="option in types"
                    :key="option.value"
                    type="button"
                    role="radio"
                    :aria-checked="type === option.value"
                    :class="{ 'is-on': type === option.value }"
                    @click="setType(option.value)"
                >
                    <i class="fas" :class="option.icon"></i> {{ option.label }}
                </button>
            </div>
            <el-select
                :key="type"
                :model-value="null"
                filterable
                remote
                clearable
                class="sale-select"
                popper-class="sale-link-popper"
                :remote-method="search"
                :loading="loading"
                :placeholder="type === 'invoice' ? t('po_sale_search_invoice') : t('po_sale_search_order')"
                :no-data-text="t('no_matching_results')"
                @visible-change="(visible) => visible && !results.length && search('')"
                @update:model-value="choose"
            >
                <el-option v-for="row in results" :key="row.id" :value="row.id" :label="row.number">
                    <div class="sale-option">
                        <span class="sale-option-main">
                            <strong>{{ row.number }}</strong>
                            <small>{{ row.customer || '—' }}</small>
                        </span>
                        <span class="sale-option-side">
                            <small>{{ statusLabel(row.status) }}</small>
                            <span>{{ formatCurrency(row.total) }}</span>
                        </span>
                    </div>
                </el-option>
            </el-select>
        </div>

        <p v-else class="sale-none">{{ t('po_sale_none') }}</p>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import { salesOrdersApi } from '@/api/salesOrders';
import { invoicesApi } from '@/api/invoices';
import { formatCurrency, statusLabel } from '@/utils/sales';

const props = defineProps({
    // { type: 'sales_order' | 'invoice', id, number, customer } or null
    modelValue: { type: Object, default: null },
    disabled: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue', 'pick']);

const { t } = useI18n();
const router = useRouter();

const type = ref('sales_order');
const types = computed(() => [
    { value: 'sales_order', icon: 'fa-cart-shopping', label: t('sales_order') },
    { value: 'invoice', icon: 'fa-file-invoice-dollar', label: t('sales_invoice') },
]);

const results = ref([]);
const loading = ref(false);
let seq = 0;

const setType = (value) => {
    type.value = value;
    results.value = [];
};

// A cancelled sale needs nothing bought in, and the server refuses its draft.
const search = async (text) => {
    const mine = ++seq;
    const kind = type.value;
    loading.value = true;
    try {
        const params = { search: (text || '').trim() || undefined, per_page: 15 };
        let rows;
        if (kind === 'invoice') {
            const { data } = await invoicesApi.getAll(params);
            rows = (data?.data?.invoices || []).map((i) => ({
                id: i.id, number: i.invoice_number, customer: i.customer_name, status: i.status, total: i.total,
            }));
        } else {
            const { data } = await salesOrdersApi.getAll(params);
            rows = (data?.data?.sales_orders || []).map((o) => ({
                id: o.id, number: o.order_number, customer: o.customer?.name, status: o.status, total: o.total,
            }));
        }
        if (mine === seq) results.value = rows.filter((row) => row.status !== 'cancelled');
    } catch {
        if (mine === seq) results.value = [];
    } finally {
        if (mine === seq) loading.value = false;
    }
};

const choose = (id) => {
    const row = results.value.find((r) => r.id === id);
    if (!row) return;
    const link = { type: type.value, id: row.id, number: row.number, customer: row.customer || '' };
    emit('update:modelValue', link);
    emit('pick', link);
};

// In a new tab: the purchase form stays open with whatever is typed in it.
const openSale = () => {
    const link = props.modelValue;
    if (!link) return;
    const target = link.type === 'invoice'
        ? { path: '/admin/sales/invoices', query: { invoice: link.number } }
        : { path: '/admin/sales/sales-orders', query: { open: link.id } };
    window.open(router.resolve(target).href, '_blank', 'noopener');
};
</script>

<style scoped>
.sale-link { width: 100%; }

.sale-card {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.65rem 0.8rem;
    border: 1px solid #bfdbfe;
    background: #eff6ff;
    border-radius: 10px;
}
.sale-icon {
    flex: none;
    display: grid;
    place-items: center;
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: #dbeafe;
    color: #1d4ed8;
}
.sale-text { display: flex; flex-direction: column; min-width: 0; flex: 1; line-height: 1.35; }
.sale-text small { font-size: 0.72rem; color: #64748b; }
.sale-text strong { color: #1e293b; font-size: 0.92rem; direction: ltr; text-align: start; }
.sale-customer { font-size: 0.78rem; color: #475569; }
.sale-customer i { color: #94a3b8; margin-inline-end: 0.2rem; }
.sale-actions { display: flex; gap: 0.25rem; flex-wrap: wrap; justify-content: flex-end; }
.sale-actions .el-button { margin: 0; }

.sale-picker { display: flex; gap: 0.5rem; align-items: stretch; }
.sale-select { flex: 1; min-width: 0; }
.type-switch { flex: none; display: flex; border: 1px solid #dcdfe6; border-radius: 6px; overflow: hidden; }
.type-switch button {
    all: unset;
    cursor: pointer;
    padding: 0 0.7rem;
    font-size: 0.8rem;
    color: #475569;
    display: flex;
    align-items: center;
    gap: 0.35rem;
    white-space: nowrap;
}
.type-switch button.is-on { background: #2563eb; color: #fff; }
.type-switch button:focus-visible { outline: 2px solid #2563eb; outline-offset: -2px; }

.sale-option { display: flex; justify-content: space-between; gap: 1rem; line-height: 1.3; padding: 0.2rem 0; }
.sale-option-main, .sale-option-side { display: flex; flex-direction: column; }
.sale-option-main small, .sale-option-side small { font-size: 0.72rem; color: #94a3b8; }
.sale-option-side { align-items: flex-end; font-variant-numeric: tabular-nums; }

.sale-none { margin: 0; font-size: 0.82rem; color: #94a3b8; }

@media (max-width: 768px) {
    .sale-picker { flex-direction: column; }
    .type-switch button { flex: 1; justify-content: center; padding: 0.45rem 0.5rem; }
    .sale-card { flex-wrap: wrap; }
    .sale-actions { width: 100%; justify-content: flex-start; }
}
</style>

<!-- The dropdown is rendered at the end of the page, out of reach of the
     scoped rules: two-line options need their row to grow. -->
<style>
.sale-link-popper .el-select-dropdown__item { height: auto; line-height: 1.3; padding-block: 0.35rem; }
</style>
