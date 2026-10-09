<template>
    <div class="purchase-request-show">
        <el-page-header @back="goBack" :title="$t('return')" style="margin-bottom:1.5rem">
            <template #content>
                <span class="text-large font-600 mr-3">{{ $t('purchase_order_details') }} {{ order?.order_number }}</span>
            </template>
        </el-page-header>

        <div v-loading="loading">
            <template v-if="order">
                <el-card shadow="hover" style="margin-bottom:1.5rem">
                    <div style="display:flex; align-items:center; gap:1rem; flex-wrap:wrap;">
                        <h2 style="margin:0; flex:1;">{{ order.order_number }}</h2>
                        <el-tag :type="statusTagType(order.status)" size="large">{{ order.status_text }}</el-tag>
                        <!-- The next step, carried out on the order screen (see
                             executeStage); only moves the workflow allows. -->
                        <el-button
                            v-if="nextForward"
                            type="primary"
                            size="small"
                            @click="executeStage(nextForward)"
                        >
                            {{ stageActionLabel(nextForward) }}
                        </el-button>
                        <el-dropdown v-if="(order.allowed_transitions || []).length" @command="executeStage" trigger="click">
                            <el-button size="small">
                                {{ $t('change_status') }} <el-icon><ArrowDown /></el-icon>
                            </el-button>
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item
                                        v-for="stage in order.allowed_transitions"
                                        :key="stage"
                                        :command="stage"
                                        :divided="stage === 'cancelled'"
                                    >
                                        {{ stageActionLabel(stage) }}
                                    </el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>
                        <el-button size="small" plain @click="openOrder">
                            <i class="fas fa-route"></i>&nbsp;{{ $t('so_open_in_orders') }}
                        </el-button>
                    </div>
                </el-card>

                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-card shadow="hover" style="margin-bottom:1.5rem">
                            <template #header><span>{{ $t('customer_information') }}</span></template>
                            <div v-if="order.customer">
                                <el-descriptions :column="1" border>
                                    <el-descriptions-item :label="$t('name')">{{ order.customer.name }}</el-descriptions-item>
                                    <el-descriptions-item :label="$t('phone')">{{ order.customer.phone }}</el-descriptions-item>
                                    <el-descriptions-item :label="$t('mail')">
                                        {{ order.customer.email || '-' }}
                                    </el-descriptions-item>
                                    <el-descriptions-item :label="$t('address')">
                                        {{ order.customer.address || '-' }}
                                    </el-descriptions-item>
                                </el-descriptions>
                            </div>
                            <span v-else>-</span>
                        </el-card>
                    </el-col>
                    <el-col :span="12">
                        <el-card shadow="hover" style="margin-bottom:1.5rem">
                            <template #header><span>{{ $t('order_information') }}</span></template>
                            <el-descriptions :column="1" border>
                                <el-descriptions-item :label="$t('order_number')">{{ order.order_number }}</el-descriptions-item>
                                <el-descriptions-item :label="$t('the_date')">{{ order.order_date || '-' }}</el-descriptions-item>
                                <el-descriptions-item :label="$t('status')">
                                    <el-tag :type="statusTagType(order.status)" size="small">{{ order.status_text }}</el-tag>
                                </el-descriptions-item>
                                <el-descriptions-item :label="$t('creation_date')">{{ order.created_at || '-' }}</el-descriptions-item>
                            </el-descriptions>
                        </el-card>
                    </el-col>
                </el-row>

                <el-card shadow="hover" style="margin-bottom:1.5rem">
                    <template #header><span>{{ $t('products') }}</span></template>
                    <el-table v-if="order.items && order.items.length" :data="order.items" stripe style="width:100%">
                        <el-table-column label="#" type="index" width="50" />
                        <el-table-column prop="product_name" :label="$t('product_name')" min-width="200" />
                        <el-table-column prop="quantity" :label="$t('quantity')" width="100" />
                        <el-table-column :label="$t('unit_price')" width="130">
                            <template #default="{ row }">
                                {{ formatCurrency(row.unit_price) }}
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('total')" width="130">
                            <template #default="{ row }">
                                {{ formatCurrency(row.total) }}
                            </template>
                        </el-table-column>
                    </el-table>
                    <span v-else>-</span>

                    <div style="margin-top:1rem; text-align:left; font-size:1.1rem; font-weight:700;">
                        {{ $t('total') }}: {{ formatCurrency(order.total) }}
                    </div>
                </el-card>

                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-card shadow="hover" style="margin-bottom:1.5rem">
                            <template #header><span>{{ $t('associated_invoices') }}</span></template>
                            <div v-if="order.invoices && order.invoices.length">
                                <div v-for="inv in order.invoices" :key="inv.id"
                                    style="padding:10px; margin-bottom:8px; background:#f9fafb; border-radius:8px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                                    <div>
                                        <strong>{{ inv.invoice_number }}</strong>
                                        <el-tag :type="inv.status === 'paid' ? 'success' : 'warning'" size="small" style="margin-right:8px">
                                            {{ inv.status === 'paid' ? $t('paid') : $t('on_hold') }}
                                        </el-tag>
                                    </div>
                                    <span style="font-weight:700;">${{ parseFloat(inv.total).toFixed(2) }}</span>
                                </div>
                            </div>
                            <span v-else>-</span>
                        </el-card>
                    </el-col>
                    <el-col :span="12">
                        <el-card shadow="hover" style="margin-bottom:1.5rem">
                            <template #header><span>{{ $t('comments') }}</span></template>
                            <p v-if="order.notes" style="white-space:pre-wrap;">{{ order.notes }}</p>
                            <span v-else>-</span>
                        </el-card>
                    </el-col>
                </el-row>
            </template>

            <div v-if="!order && !loading" class="empty-state">
                <p>{{ $t('purchase_order_not_found') }}</p>
                <el-button @click="goBack">{{ $t('return') }}</el-button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { formatCurrency } from '@/utils/sales';
import { useRoute, useRouter } from 'vue-router';
import { usePurchaseRequestsStore } from '@/stores/purchaseRequests';
import { ElMessage } from 'element-plus';
import { ArrowDown } from '@element-plus/icons-vue';

const route = useRoute();
const router = useRouter();
const store = usePurchaseRequestsStore();

const order = ref(null);
const loading = ref(true);

const statusTagType = (status) => {
    const map = {
        pending: 'warning',
        confirmed: 'primary',
        processing: 'info',
        shipped: 'success',
        delivered: 'success',
        cancelled: 'danger',
    };
    return map[status] || 'info';
};

const goBack = () => {
    router.push({ name: 'admin.purchase-requests.index' });
};

const { t } = useI18n();

const stageActionLabel = (stage) => ({
    confirmed: t('confirm_order'),
    processing: t('start_preparation'),
    shipped: t('confirm_shipping'),
    delivered: t('deliver_and_settle_action'),
    cancelled: t('cancel_the_request'),
}[stage] || stage);

/** The forward move from here — cancelling is an exit, not the next step. */
const nextForward = computed(() => (order.value?.allowed_transitions || []).find((stage) => stage !== 'cancelled') || null);

/**
 * A request is a sales order, moved where every sales order is: the order
 * screen opens on its execution tab and starts the step, with its dialogs,
 * stock checks and reasons. Setting the status from here skipped all of that
 * and left the page showing the old status text.
 */
const executeStage = (stage) => {
    router.push({ path: '/admin/sales/sales-orders', query: { open: order.value.id, tab: 'execution', do: stage } });
};

const openOrder = () => {
    router.push({ path: '/admin/sales/sales-orders', query: { open: order.value.id, tab: 'execution' } });
};

onMounted(async () => {
    const id = route.params.id;
    if (id) {
        try {
            const data = await store.fetchOrder(id);
            order.value = data;
        } catch {
            ElMessage.error(t('failed_to_load_order_details'));
        } finally {
            loading.value = false;
        }
    } else {
        loading.value = false;
    }
});
</script>

<style scoped>
.purchase-request-show {
    padding: 0;
}
.empty-state {
    text-align: center;
    padding: 3rem;
    color: #6b7c98;
}
</style>
