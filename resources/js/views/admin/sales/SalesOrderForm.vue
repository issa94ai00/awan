<template>
    <div class="order-form-page">
        <AdminPageHeader :title="order.isEdit.value ? t('edit_sales_order') : t('create_sales_order')">
            <template #actions>
                <el-button @click="goBack">
                    <i class="fas" :class="backArrow"></i>&nbsp;{{ t('back_to_sales_orders') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <el-skeleton v-if="order.loading.value" :rows="8" animated />

        <!--
            A confirmed order holds stock, carries an invoice and is posted to
            the ledger, so the API refuses to rewrite its lines. Said up front,
            with the routes that do work, rather than after the order is redone.
        -->
        <section v-else-if="order.isLocked.value" class="locked">
            <i class="fas fa-lock locked-icon"></i>
            <h2>{{ t('order_locked_after_confirmation') }}</h2>
            <p>{{ lockedReason }}</p>
            <dl class="locked-facts">
                <div><dt>{{ t('status') }}</dt><dd>{{ statusLabel(order.loadedOrder.value.status) }}</dd></div>
                <div><dt>{{ t('customer') }}</dt><dd>{{ order.loadedOrder.value.customer?.name || '—' }}</dd></div>
                <div><dt>{{ t('grand_total') }}</dt><dd>{{ formatCurrency(order.loadedOrder.value.total) }}</dd></div>
            </dl>
            <div class="locked-actions">
                <el-button type="primary" @click="openOrder(orderId)">
                    <i class="fas fa-eye"></i>&nbsp;{{ t('open_order_to_follow_stages') }}
                </el-button>
            </div>
            <p class="locked-note">{{ t('why_items_are_locked') }}</p>
        </section>

        <template v-else>
            <OrderStepper />

            <el-alert
                v-if="order.serverErrors.value.length"
                type="error"
                show-icon
                class="server-errors"
                :title="t('please_fix_these_errors')"
                @close="order.serverErrors.value = []"
            >
                <ul><li v-for="(error, i) in order.serverErrors.value" :key="i">{{ error }}</li></ul>
            </el-alert>

            <div class="order-layout">
                <main class="order-main">
                    <Transition name="step" mode="out-in">
                        <div v-if="order.step.value === 0" key="lines" class="step-body">
                            <section class="search-panel">
                                <OrderProductSearch ref="searchRef" @added="flash" />
                            </section>
                            <OrderLinesTable :flash-key="flashKey" />
                        </div>
                        <OrderCustomerStep v-else-if="order.step.value === 1" key="customer" ref="customerRef" />
                        <OrderRoutingStep v-else-if="order.step.value === 2" key="routing" />
                        <OrderReviewStep v-else key="review" />
                    </Transition>
                </main>

                <OrderSummaryPanel @submit="submit" @clear="clearOrder" />
            </div>
        </template>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter, onBeforeRouteLeave } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import OrderStepper from '@/components/admin/sales/order-form/OrderStepper.vue';
import OrderProductSearch from '@/components/admin/sales/order-form/OrderProductSearch.vue';
import OrderLinesTable from '@/components/admin/sales/order-form/OrderLinesTable.vue';
import OrderCustomerStep from '@/components/admin/sales/order-form/OrderCustomerStep.vue';
import OrderRoutingStep from '@/components/admin/sales/order-form/OrderRoutingStep.vue';
import OrderReviewStep from '@/components/admin/sales/order-form/OrderReviewStep.vue';
import OrderSummaryPanel from '@/components/admin/sales/order-form/OrderSummaryPanel.vue';
import { provideSalesOrderForm } from '@/Composables/useSalesOrderForm';
import { useStockShortage } from '@/Composables/useStockShortage';
import { statusLabel, formatCurrency } from '@/utils/sales';

const { t, locale } = useI18n();
const route = useRoute();
const router = useRouter();

const orderId = route.params.id || null;
const order = provideSalesOrderForm({ orderId });
const { handleStockShortage } = useStockShortage();

const backArrow = computed(() => (locale.value === 'ar' ? 'fa-arrow-right' : 'fa-arrow-left'));

const lockedReason = computed(() => ({
    confirmed: t('lock_reason_confirmed'),
    processing: t('lock_reason_processing'),
    shipped: t('lock_reason_shipped'),
    delivered: t('order_delivered_cycle_complete'),
    cancelled: t('state_hint_cancelled'),
}[order.loadedOrder.value?.status] || t('status_forbids_item_edits')));

/* Focus follows the step ------------------------------------------ */

const searchRef = ref(null);
const customerRef = ref(null);

watch(() => order.step.value, async (step) => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
    await nextTick();
    // After the step's transition, so the field exists to take the focus.
    setTimeout(() => {
        if (step === 0) searchRef.value?.focus();
        if (step === 1 && !order.form.customer_id) customerRef.value?.focusCustomer();
    }, 220);
});

// The line a pick landed on glows for a moment: a repeat pick only raises a
// quantity, which is easy to miss.
const flashKey = ref(null);
let flashTimer = null;
const flash = (line) => {
    clearTimeout(flashTimer);
    flashKey.value = null;
    nextTick(() => { flashKey.value = line?.key || null; });
    flashTimer = setTimeout(() => { flashKey.value = null; }, 1500);
};

/* Saving ---------------------------------------------------------- */

/** Opens the order on its execution tab, where it goes next. */
const openOrder = (id) => {
    router.push({ path: '/admin/sales/sales-orders', query: { open: id, tab: 'execution' } });
};

const submit = async ({ confirm }) => {
    const result = await order.submit({ confirm });
    if (!result) {
        window.scrollTo({ top: 0, behavior: 'smooth' });
        return;
    }
    const { id, execution } = result;

    if (execution && !execution.confirmed) {
        // Saved, but not confirmed: say why, and offer the purchase order
        // that would fix a shortage, as the order screen does.
        const shown = await handleStockShortage(
            { response: { data: { data: { shortages: execution.shortages || [] } } } },
            id,
        );
        if (!shown) {
            await ElMessageBox.alert(execution.message || t('so_saved_not_confirmed'), t('so_saved_as_draft'), { type: 'warning' }).catch(() => {});
        }
    } else {
        ElMessage.success(execution?.confirmed
            ? t('so_saved_and_confirmed')
            : (order.isEdit.value ? t('sales_order_updated') : t('sales_order_created')));
    }

    if (id) openOrder(id);
    else router.push('/admin/sales/sales-orders');
};

const clearOrder = async () => {
    try {
        await ElMessageBox.confirm(t('confirm_clear_all_items'), t('clear_the_form'), { type: 'warning' });
    } catch {
        return;
    }
    order.reset();
    ElMessage.success(t('form_cleared'));
};

const goBack = () => router.push('/admin/sales/sales-orders');

/* Leaving with work on screen ------------------------------------- */

onBeforeRouteLeave(async () => {
    if (!order.dirty.value || order.isLocked.value || order.submitting.value) return true;
    // A new order is kept as a draft in this browser, so leaving loses
    // nothing; an edit is not, so it asks.
    if (!order.isEdit.value) return true;
    try {
        await ElMessageBox.confirm(t('unsaved_order_warning'), t('leave_without_saving'), {
            type: 'warning', confirmButtonText: t('leave'), cancelButtonText: t('stay'),
        });
        return true;
    } catch {
        return false;
    }
});

const onBeforeUnload = (event) => {
    if (order.isEdit.value && order.dirty.value && !order.submitting.value) {
        event.preventDefault();
        event.returnValue = '';
    }
};

/* Keyboard -------------------------------------------------------- */

// F2 finds a product from anywhere; Ctrl+Enter moves on, and on the last step
// saves without confirming. The old Ctrl+N and Ctrl+B belong to the browser
// and never reliably reached the page.
const onKeydown = (event) => {
    if (order.isLocked.value || order.loading.value) return;
    if (event.key === 'F2') {
        event.preventDefault();
        if (order.step.value !== 0) order.goTo(0);
        else searchRef.value?.focus();
    }
    if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
        event.preventDefault();
        if (order.step.value < 3) order.goTo(order.step.value + 1);
        else if (!order.submitting.value) submit({ confirm: false });
    }
};

onMounted(async () => {
    window.addEventListener('keydown', onKeydown);
    window.addEventListener('beforeunload', onBeforeUnload);
    if (orderId) {
        await order.loadOrder();
    } else {
        await order.offerDraft();
        setTimeout(() => searchRef.value?.focus(), 150);
    }
    await nextTick();
    order.startTracking();
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown);
    window.removeEventListener('beforeunload', onBeforeUnload);
    clearTimeout(flashTimer);
});
</script>

<style scoped>
.order-form-page { font-family: 'Cairo', sans-serif; padding-bottom: 2rem; }

.server-errors { margin-bottom: 1rem; }
.server-errors ul { margin: 0.25rem 0 0; padding-inline-start: 1.1rem; }

.order-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 290px;
    gap: 1rem;
    align-items: start;
}
.order-main { min-width: 0; }
.step-body { display: flex; flex-direction: column; gap: 1rem; }

.search-panel {
    position: relative;
    z-index: 2;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 0.85rem;
}

.step-enter-active, .step-leave-active { transition: opacity 0.18s ease, transform 0.18s ease; }
.step-enter-from { opacity: 0; transform: translateY(6px); }
.step-leave-to { opacity: 0; }

.locked {
    max-width: 640px;
    margin: 2rem auto;
    padding: 2rem;
    text-align: center;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
}
.locked-icon { font-size: 2rem; color: #94a3b8; }
.locked h2 { margin: 0.75rem 0 0.5rem; font-size: 1.2rem; color: #1e293b; }
.locked p { color: #475569; font-size: 0.9rem; line-height: 1.7; }
.locked-facts { display: flex; justify-content: center; gap: 2rem; margin: 1.25rem 0; }
.locked-facts dt { font-size: 0.75rem; color: #64748b; }
.locked-facts dd { margin: 0; font-weight: 700; color: #1e293b; }
.locked-actions { display: flex; justify-content: center; gap: 0.5rem; }
.locked-note { margin-top: 1.25rem; font-size: 0.8rem !important; color: #94a3b8 !important; }

@media (max-width: 992px) {
    .order-layout { grid-template-columns: minmax(0, 1fr); }
}

@media (max-width: 768px) {
    /* Room for the bar that holds the total and "next". */
    .order-form-page { padding-bottom: 5.5rem; }
    .search-panel { padding: 0.65rem; }
    .locked { padding: 1.25rem; margin: 1rem 0; }
    .locked-facts { flex-direction: column; gap: 0.6rem; }
}
</style>
