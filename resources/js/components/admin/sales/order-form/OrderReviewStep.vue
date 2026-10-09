<template>
    <div class="review">
        <!-- Each part of the order as decided, with a way straight back to
             the step that decides it. -->
        <section class="panel">
            <header class="panel-head">
                <h3><i class="fas fa-user"></i> {{ t('customer_and_shipping_info') }}</h3>
                <button type="button" class="link-btn" @click="order.goTo(1)"><i class="fas fa-pen"></i> {{ t('edit') }}</button>
            </header>
            <dl class="facts">
                <div>
                    <dt>{{ t('customer') }}</dt>
                    <dd>
                        {{ order.form.customer?.name || '—' }}
                        <small v-if="order.form.customer?.phone" dir="ltr">{{ order.form.customer.phone }}</small>
                    </dd>
                </div>
                <div>
                    <dt>{{ t('so_fulfillment_type') }}</dt>
                    <dd>{{ fulfillmentLabel }}</dd>
                </div>
                <div>
                    <dt>{{ t('order_date') }}</dt>
                    <dd>{{ formatDate(order.form.order_date) }}</dd>
                </div>
                <div>
                    <dt>{{ t('expected_delivery') }}</dt>
                    <dd>{{ order.form.expected_delivery ? formatDate(order.form.expected_delivery) : t('not_specified') }}</dd>
                </div>
                <div v-if="order.form.fulfillment_type !== 'pickup'" class="wide">
                    <dt>{{ t('shipping_address_label') }}</dt>
                    <dd>{{ order.form.shipping_address || t('no_delivery_address') }}</dd>
                </div>
            </dl>
            <el-alert
                v-if="order.creditCheck.value?.over"
                type="warning"
                show-icon
                :closable="false"
                class="mt"
                :title="t('sof_over_credit', { after: formatCurrency(order.creditCheck.value.after), limit: formatCurrency(order.creditCheck.value.limit) })"
            />
        </section>

        <section class="panel">
            <header class="panel-head">
                <h3><i class="fas fa-cart-shopping"></i> {{ t('ordered_goods_list') }}</h3>
                <button type="button" class="link-btn" @click="order.goTo(0)"><i class="fas fa-pen"></i> {{ t('edit') }}</button>
            </header>
            <div class="table-wrap">
                <table class="review-table">
                    <thead>
                        <tr>
                            <th>{{ t('product_item_name') }}</th>
                            <th class="num">{{ t('quantity') }}</th>
                            <th class="num">{{ t('unit_price') }}</th>
                            <th>
                                {{ t('so_from_warehouse') }}
                                <button type="button" class="link-btn small" @click="order.goTo(2)"><i class="fas fa-pen"></i></button>
                            </th>
                            <th class="num">{{ t('line_subtotal') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="line in order.lines.value" :key="line.key">
                            <td>
                                <strong>{{ line.name }}</strong>
                                <div class="sub">
                                    <VariantChip v-if="line.product_variant_id" :label="line.variant_label" />
                                    <span v-if="line.sku" class="mono">{{ line.sku }}</span>
                                </div>
                                <div v-if="order.belowCost(line) || order.overStock(line)" class="warns">
                                    <span v-if="order.belowCost(line)"><i class="fas fa-triangle-exclamation"></i> {{ t('sof_below_cost', { cost: formatCurrency(order.unitCost(line)) }) }}</span>
                                    <span v-if="order.overStock(line)"><i class="fas fa-triangle-exclamation"></i> {{ line.stock > 0 ? t('so_qty_over_stock', { n: line.stock }) : t('out_of_stock') }}</span>
                                </div>
                            </td>
                            <td class="num">{{ line.quantity }} <small>{{ line.unit?.name_ar || line.unit?.name }}</small></td>
                            <td class="num">{{ formatCurrency(line.price) }}</td>
                            <td class="sources">{{ order.sourcesText(line) }}</td>
                            <td class="num strong">{{ formatCurrency(order.lineTotal(line)) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel">
            <header class="panel-head">
                <h3><i class="fas fa-note-sticky"></i> {{ t('administrative_notes_for_order') }}</h3>
            </header>
            <el-input v-model="order.form.notes" type="textarea" :rows="3" maxlength="1000" show-word-limit :placeholder="t('order_notes_placeholder')" />
        </section>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { formatCurrency, formatDate } from '@/utils/sales';
import VariantChip from '@/components/admin/products/VariantChip.vue';
import { useSalesOrderForm } from '@/Composables/useSalesOrderForm';

const { t } = useI18n();
const order = useSalesOrderForm();

const fulfillmentLabel = computed(() => ({
    ship: t('so_fulfillment_ship'),
    delivery: t('so_fulfillment_delivery'),
    pickup: t('so_fulfillment_pickup'),
}[order.form.fulfillment_type] || order.form.fulfillment_type));
</script>

<style scoped>
.review { display: flex; flex-direction: column; gap: 1rem; }
.panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem; }
.panel-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 0.85rem; }
.panel-head h3 { margin: 0; font-size: 0.98rem; font-weight: 700; color: #1e293b; display: flex; gap: 0.5rem; align-items: center; }
.panel-head h3 i { color: #64748b; }
.mt { margin-top: 0.75rem; }

.link-btn { all: unset; cursor: pointer; font-size: 0.8rem; color: #2563eb; display: inline-flex; gap: 0.3rem; align-items: center; }
.link-btn:hover { text-decoration: underline; }
.link-btn:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }
.link-btn.small { font-size: 0.7rem; margin-inline-start: 0.3rem; }

.facts { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0.75rem 1rem; margin: 0; }
.facts .wide { grid-column: 1 / -1; }
.facts dt { font-size: 0.75rem; color: #64748b; margin-bottom: 0.15rem; }
.facts dd { margin: 0; font-weight: 600; color: #1e293b; font-size: 0.88rem; display: flex; flex-direction: column; }
.facts dd small { font-weight: 400; color: #64748b; align-self: flex-start; }

.table-wrap { overflow-x: auto; }
.review-table { width: 100%; border-collapse: collapse; font-size: 0.86rem; }
.review-table th {
    text-align: start;
    font-size: 0.75rem;
    font-weight: 600;
    color: #64748b;
    background: #f8fafc;
    padding: 0.5rem 0.7rem;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}
.review-table td { padding: 0.6rem 0.7rem; border-bottom: 1px solid #f1f5f9; vertical-align: top; color: #334155; }
.review-table tr:last-child td { border-bottom: none; }
.review-table strong { color: #1e293b; }
.num { text-align: end !important; font-variant-numeric: tabular-nums; white-space: nowrap; }
.num small { color: #94a3b8; }
.strong { font-weight: 700; color: #1e293b; }
.sub { display: flex; gap: 0.4rem; align-items: center; margin-top: 0.2rem; font-size: 0.74rem; color: #94a3b8; }
.mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; direction: ltr; }
.sources { font-size: 0.8rem; color: #475569; }
.warns { display: flex; flex-direction: column; gap: 0.1rem; margin-top: 0.3rem; font-size: 0.74rem; color: #b45309; }

@media (max-width: 768px) {
    .panel { padding: 0.85rem; }
    .facts { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
</style>
