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
                        <el-dropdown @command="(val) => updateStatus(val)" trigger="click">
                            <el-button type="primary" size="small">
                                {{ $t('change_status') }} <el-icon><ArrowDown /></el-icon>
                            </el-button>
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item command="pending">{{ $t('hanging') }}</el-dropdown-item>
                                    <el-dropdown-item command="confirmed">{{ $t('certain') }}</el-dropdown-item>
                                    <el-dropdown-item command="processing">{{ $t('in_process') }}</el-dropdown-item>
                                    <el-dropdown-item command="shipped">{{ $t('shipped') }}</el-dropdown-item>
                                    <el-dropdown-item command="delivered">{{ $t('delivered') }}</el-dropdown-item>
                                    <el-dropdown-item command="cancelled" divided>{{ $t('canceled') }}</el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>
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
                                ${{ parseFloat(row.unit_price).toFixed(2) }}
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('total')" width="130">
                            <template #default="{ row }">
                                ${{ parseFloat(row.total).toFixed(2) }}
                            </template>
                        </el-table-column>
                    </el-table>
                    <span v-else>-</span>

                    <div style="margin-top:1rem; text-align:left; font-size:1.1rem; font-weight:700;">
                        {{ $t('total') }}{{ parseFloat(order.total).toFixed(2) }}
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
import { ref, onMounted } from 'vue';
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

const updateStatus = async (status) => {
    try {
        await store.updateOrderStatus(order.value.id, status);
        order.value.status = status;
        ElMessage.success(window.t('status_updated_successfully'));
    } catch {
        ElMessage.error(window.t('status_update_failed'));
    }
};

onMounted(async () => {
    const id = route.params.id;
    if (id) {
        try {
            const data = await store.fetchOrder(id);
            order.value = data;
        } catch {
            ElMessage.error(window.t('failed_to_load_order_details'));
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
