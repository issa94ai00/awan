<template>
    <el-drawer
        :model-value="modelValue"
        :size="drawerSize"
        direction="rtl"
        class="return-detail-drawer"
        @update:model-value="(value) => emit('update:modelValue', value)"
        @open="load"
    >
        <template #header>
            <div class="detail-header">
                <span class="detail-number" dir="ltr">{{ ret?.return_number || summary?.return_number }}</span>
                <el-tag type="success" effect="light" round size="small">{{ $t('pret_status_completed') }}</el-tag>
            </div>
        </template>

        <el-skeleton v-if="loading && !ret" :rows="8" animated />
        <el-result v-else-if="error" icon="error" :title="error">
            <template #extra>
                <el-button type="primary" @click="load">{{ $t('cat_admin_retry') }}</el-button>
            </template>
        </el-result>

        <template v-else-if="ret">
            <dl class="facts">
                <div>
                    <dt>{{ $t('supplier') }}</dt>
                    <dd>{{ ret.supplier?.name || '—' }}</dd>
                </div>
                <div>
                    <dt>{{ $t('return_date') }}</dt>
                    <dd>{{ formatDate(ret.return_date) }}</dd>
                </div>
                <div>
                    <dt>{{ $t('warehouse') }}</dt>
                    <dd>{{ ret.warehouse?.name || '—' }}</dd>
                </div>
                <div>
                    <dt>{{ $t('pret_from_delivery') }}</dt>
                    <dd dir="auto">{{ ret.purchase_receipt?.receipt_number || $t('pret_no_delivery') }}</dd>
                </div>
                <div>
                    <dt>{{ $t('reason') }}</dt>
                    <dd>{{ ret.reason || '—' }}</dd>
                </div>
                <div>
                    <dt>{{ $t('pret_recorded_by') }}</dt>
                    <dd>{{ ret.creator?.name || '—' }}</dd>
                </div>
            </dl>

            <el-table :data="ret.items || []" size="small" class="items-table">
                <el-table-column :label="$t('product')" min-width="200">
                    <template #default="{ row }">
                        <div class="item-name">{{ row.product?.name_ar || row.product?.name_en || `#${row.product_id}` }}</div>
                        <div v-if="row.product?.sku" class="item-sku" dir="ltr">{{ row.product.sku }}</div>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('quantity')" width="80" align="center" prop="quantity" />
                <el-table-column :label="$t('pret_unit_cost')" width="110" align="right">
                    <template #default="{ row }">{{ formatAmount(row.unit_cost) }}</template>
                </el-table-column>
                <el-table-column :label="$t('credit_per_unit')" width="110" align="right">
                    <template #default="{ row }">{{ formatAmount(row.unit_price) }}</template>
                </el-table-column>
                <el-table-column :label="$t('pret_line_credit')" width="120" align="right">
                    <template #default="{ row }"><strong>{{ formatAmount(row.unit_price * row.quantity) }}</strong></template>
                </el-table-column>
                <el-table-column :label="$t('pret_difference')" width="110" align="right">
                    <template #default="{ row }">
                        <span :class="toneOf((row.unit_price - row.unit_cost) * row.quantity)">
                            {{ signed((row.unit_price - row.unit_cost) * row.quantity) }}
                        </span>
                    </template>
                </el-table-column>
            </el-table>

            <dl class="totals">
                <div>
                    <dt>{{ $t('pret_goods_cost') }}</dt>
                    <dd>{{ formatAmount(totalCost) }}</dd>
                </div>
                <div>
                    <dt>{{ $t('credit_amount') }}</dt>
                    <dd>{{ formatAmount(ret.credit_amount) }}</dd>
                </div>
                <div>
                    <dt>{{ $t('tax_returned') }}</dt>
                    <dd>{{ formatAmount(ret.tax_amount) }}</dd>
                </div>
                <div class="grand">
                    <dt>{{ $t('pret_supplier_owed_less') }}</dt>
                    <dd>{{ formatCurrency(Number(ret.credit_amount) + Number(ret.tax_amount)) }}</dd>
                </div>
            </dl>

            <el-alert
                v-if="Math.abs(variance) >= 0.01"
                :type="variance < 0 ? 'warning' : 'success'"
                :closable="false"
                show-icon
                class="variance-alert"
                :title="variance < 0
                    ? $t('pret_variance_loss', { amount: formatAmount(-variance) })
                    : $t('pret_variance_gain', { amount: formatAmount(variance) })"
            />

            <div v-if="ret.notes" class="notes">
                <h5>{{ $t('notes') }}</h5>
                <p>{{ ret.notes }}</p>
            </div>

            <p class="final-note">
                <el-icon><InfoFilled /></el-icon>
                {{ $t('pret_cannot_delete_hint') }}
            </p>
        </template>
    </el-drawer>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { InfoFilled } from '@element-plus/icons-vue';
import { purchaseReturnsApi } from '@/api/purchaseReturns';
import { apiErrorMessage, formatCurrency, formatDate } from '@/utils/sales';
import { baseCurrencyCode, currencyDecimals, numberLocale } from '@/utils/currency';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    /** The list row: shown straight away while the full record loads. */
    summary: { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue']);
const { t } = useI18n();

const ret = ref(null);
const loading = ref(false);
const error = ref('');

const drawerSize = computed(() => (window.innerWidth < 768 ? '100%' : '720px'));

const load = async () => {
    if (!props.summary?.id) return;
    // The list already carries the lines; show them while the rest arrives.
    ret.value = props.summary;
    loading.value = true;
    error.value = '';
    try {
        const res = await purchaseReturnsApi.get(props.summary.id);
        ret.value = res.data?.data || props.summary;
    } catch (e) {
        if (!ret.value) error.value = apiErrorMessage(e, t('failed_to_load_report'));
    } finally {
        loading.value = false;
    }
};

const totalCost = computed(() => (ret.value?.items || [])
    .reduce((sum, item) => sum + Number(item.unit_cost) * Number(item.quantity), 0));

// Against the header credit, so an agreed lump sum is what's compared.
const variance = computed(() => (Number(ret.value?.credit_amount) || 0) - totalCost.value);

const formatAmount = (value) => {
    const places = currencyDecimals(baseCurrencyCode());
    return new Intl.NumberFormat(numberLocale(), { minimumFractionDigits: places, maximumFractionDigits: places })
        .format(Number(value) || 0);
};

const signed = (value) => {
    if (Math.abs(value) < 0.005) return '0';
    return `${value > 0 ? '+' : '−'}${formatAmount(Math.abs(value))}`;
};

const toneOf = (value) => (Math.abs(value) < 0.005 ? 'tone-flat' : value > 0 ? 'tone-good' : 'tone-bad');
</script>

<style scoped>
.detail-header { display: flex; align-items: center; gap: 0.6rem; }
.detail-number { font-family: ui-monospace, monospace; font-weight: 700; font-size: 1.1rem; color: #0f172a; }

.facts {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 0.9rem 1.25rem;
    margin: 0 0 1.25rem;
    padding: 1rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
}
.facts dt { font-size: 0.75rem; color: #64748b; margin-bottom: 0.15rem; }
.facts dd { margin: 0; font-weight: 600; color: #0f172a; word-break: break-word; }

.items-table { margin-bottom: 1rem; }
.item-name { font-weight: 600; }
.item-sku { font-size: 0.75rem; color: #64748b; font-family: ui-monospace, monospace; }

.tone-good { color: #16a34a; font-weight: 600; }
.tone-bad { color: #dc2626; font-weight: 600; }
.tone-flat { color: #94a3b8; }

.totals {
    display: flex;
    flex-wrap: wrap;
    gap: 0.6rem 1.75rem;
    margin: 0 0 1rem;
    padding: 0.85rem 1rem;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
}
.totals > div { display: flex; flex-direction: column; }
.totals dt { font-size: 0.75rem; color: #64748b; }
.totals dd { margin: 0; font-weight: 700; font-variant-numeric: tabular-nums; }
.totals .grand { margin-inline-start: auto; text-align: end; }
.totals .grand dd { font-size: 1.1rem; color: #16a34a; }

.variance-alert { margin-bottom: 1rem; }

.notes h5 { margin: 0 0 0.3rem; font-size: 0.8rem; color: #64748b; }
.notes p { margin: 0 0 1rem; white-space: pre-line; }

.final-note { display: flex; gap: 0.4rem; align-items: flex-start; font-size: 0.8rem; color: #64748b; margin: 0; line-height: 1.6; }
.final-note .el-icon { margin-top: 0.2rem; flex-shrink: 0; }

@media (max-width: 768px) {
    .totals .grand { margin-inline-start: 0; text-align: start; }
}
</style>
