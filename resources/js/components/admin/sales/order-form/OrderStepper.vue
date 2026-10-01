<template>
    <!-- The four steps as one slim bar. Any step can be clicked: going back
         is always allowed, and going forward stops at the first step that is
         not ready, saying why. -->
    <nav class="order-stepper" :aria-label="t('sof_steps')">
        <button
            v-for="(item, index) in steps"
            :key="item.key"
            type="button"
            class="stepper-item"
            :class="{
                'is-current': index === order.step.value,
                'is-done': index < order.step.value,
                'has-issue': index < order.step.value && order.issuesFor(index).length,
            }"
            :aria-current="index === order.step.value ? 'step' : undefined"
            @click="order.goTo(index)"
        >
            <span class="stepper-dot">
                <i v-if="index < order.step.value && !order.issuesFor(index).length" class="fas fa-check"></i>
                <i v-else-if="index < order.step.value" class="fas fa-exclamation"></i>
                <template v-else>{{ index + 1 }}</template>
            </span>
            <span class="stepper-text">
                <span class="stepper-title">{{ item.title }}</span>
                <span class="stepper-detail">{{ item.detail }}</span>
            </span>
        </button>
    </nav>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useSalesOrderForm } from '@/Composables/useSalesOrderForm';
import { formatCurrency } from '@/utils/sales';

const { t } = useI18n();
const order = useSalesOrderForm();

// Each step names what was decided on it, once it has been, so the bar
// doubles as a summary of the order so far.
const steps = computed(() => [
    {
        key: 'lines',
        title: t('choose_products'),
        detail: order.lines.value.length
            ? t('sof_lines_detail', { n: order.lines.value.length, total: formatCurrency(order.subtotal.value) })
            : t('set_products_units_quantities'),
    },
    {
        key: 'customer',
        title: t('customer_and_shipping_data'),
        detail: order.form.customer?.name || t('assign_customer_delivery_finance'),
    },
    {
        key: 'routing',
        title: t('so_wizard_routing'),
        detail: order.routing.mode === 'later'
            ? t('so_routing_later')
            : (order.warehousesUsed.value.size === 1
                ? order.warehouseName([...order.warehousesUsed.value][0])
                : (order.warehousesUsed.value.size > 1 ? t('so_routing_summary_split', { n: order.warehousesUsed.value.size }) : t('so_wizard_routing_hint'))),
    },
    {
        key: 'review',
        title: t('so_wizard_review'),
        detail: t('so_wizard_review_hint'),
    },
]);
</script>

<style scoped>
.order-stepper {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.stepper-item {
    all: unset;
    box-sizing: border-box;
    display: flex;
    align-items: center;
    gap: 0.65rem;
    min-width: 0;
    padding: 0.6rem 0.75rem;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    background: #fff;
    cursor: pointer;
    transition: border-color 0.15s, background-color 0.15s;
}
.stepper-item:hover { border-color: #93c5fd; }
.stepper-item:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }

.stepper-dot {
    flex: none;
    display: grid;
    place-items: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #f1f5f9;
    color: #64748b;
    font-size: 0.8rem;
    font-weight: 700;
}

.stepper-text { display: flex; flex-direction: column; min-width: 0; }
.stepper-title { font-size: 0.86rem; font-weight: 700; color: #334155; }
.stepper-detail {
    font-size: 0.74rem;
    color: #94a3b8;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.stepper-item.is-current { border-color: #2563eb; background: #eff6ff; }
.stepper-item.is-current .stepper-dot { background: #2563eb; color: #fff; }
.stepper-item.is-current .stepper-title { color: #1d4ed8; }

.stepper-item.is-done .stepper-dot { background: #dcfce7; color: #16a34a; }
.stepper-item.is-done .stepper-detail { color: #475569; }

.stepper-item.has-issue .stepper-dot { background: #fef3c7; color: #b45309; }

@media (max-width: 768px) {
    .order-stepper { gap: 0.35rem; }
    .stepper-item { flex-direction: column; justify-content: center; gap: 0.3rem; padding: 0.5rem 0.25rem; text-align: center; }
    .stepper-title { font-size: 0.72rem; line-height: 1.3; }
    .stepper-detail { display: none; }
}
</style>
