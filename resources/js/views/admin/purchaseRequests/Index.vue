<template>
    <div class="purchase-requests-page">
        <AdminPageHeader
            :title="$t('purchase_orders')"
            :subtitle="$t('managing_purchase_orders_received_from')"
        >
            <template #actions>
                <el-select v-model="filterStatus" :placeholder="$t('status')" clearable @change="handleFilter" style="width: 160px">
                    <el-option :label="$t('hanging')" value="pending" />
                    <el-option :label="$t('certain')" value="confirmed" />
                    <el-option :label="$t('in_process')" value="processing" />
                    <el-option :label="$t('shipped')" value="shipped" />
                    <el-option :label="$t('delivered')" value="delivered" />
                    <el-option :label="$t('canceled')" value="cancelled" />
                </el-select>
                <el-input v-model="searchQuery" :placeholder="$t('search_by_order_number_or_customer_name')" clearable @input="handleSearch" class="search-input" />
            </template>
        </AdminPageHeader>

        <AdminStatGrid>
            <el-card shadow="hover" class="summary-card">
                <p>{{ $t('total_orders') }}</p>
                <h3>{{ store.pagination.total }}</h3>
            </el-card>
            <el-card shadow="hover" class="summary-card">
                <p>{{ $t('on_hold') }}</p>
                <h3>{{ statusCount('pending') }}</h3>
            </el-card>
            <el-card shadow="hover" class="summary-card">
                <p>{{ $t('in_process') }}</p>
                <h3>{{ statusCount('processing') + statusCount('confirmed') }}</h3>
            </el-card>
            <el-card shadow="hover" class="summary-card">
                <p>{{ $t('complete') }}</p>
                <h3>{{ statusCount('delivered') }}</h3>
            </el-card>
        </AdminStatGrid>

        <el-card shadow="hover" class="table-panel">
            <template #header>
                <div class="card-header">
                    <span>{{ $t('purchase_order_list') }}</span>
                    <el-button type="primary" size="small" @click="refresh">
                        <el-icon><Refresh /></el-icon> {{ $t('update') }}
                    </el-button>
                </div>
            </template>

            <div v-if="store.loading" class="loading-state">
                <el-icon class="loading-icon"><Loading /></el-icon>
                {{ $t('loading') }}
            </div>

            <div v-else-if="store.error" class="error-state">
                <p>{{ store.error }}</p>
                <el-button @click="refresh">{{ $t('retry') }}</el-button>
            </div>

            <div v-else>
                <el-table v-if="store.orders.length" :data="store.orders" style="width:100%" stripe highlight-current-row>
                    <el-table-column prop="order_number" label="#" width="140" />
                    <el-table-column :label="$t('client')" width="200">
                        <template #default="{ row }">
                            <div v-if="row.customer">
                                <strong>{{ row.customer.name }}</strong>
                                <br>
                                <small style="color:#909399">{{ row.customer.phone }}</small>
                            </div>
                            <span v-else>-</span>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('products')" min-width="200">
                        <template #default="{ row }">
                            <div v-if="row.items && row.items.length">
                                <div v-for="item in row.items" :key="item.id" class="item-line">
                                    <span>{{ item.product_name }} × {{ item.quantity }}</span>
                                </div>
                            </div>
                            <span v-else>-</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="total" :label="$t('total')" width="120">
                        <template #default="{ row }">
                            {{ formatCurrency(row.total) }}
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('status')" width="160">
                        <template #default="{ row }">
                            <!-- Only the moves the workflow accepts from here, each
                                 carried out on the order screen — with its
                                 dialogs, reasons and stock checks. -->
                            <el-dropdown v-if="row.allowed_transitions?.length" @command="(val) => executeStage(row, val)" trigger="click">
                                <el-tag :type="statusTagType(row.status)" style="cursor:pointer">
                                    {{ row.status_text || row.status }}
                                    <el-icon class="el-icon--right"><ArrowDown /></el-icon>
                                </el-tag>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item
                                            v-for="stage in row.allowed_transitions"
                                            :key="stage"
                                            :command="stage"
                                            :divided="stage === 'cancelled'"
                                        >
                                            {{ stageActionLabel(stage) }}
                                        </el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                            <el-tag v-else :type="statusTagType(row.status)">{{ row.status_text || row.status }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('the_date')" width="120">
                        <template #default="{ row }">
                            {{ row.order_date || row.created_at || '-' }}
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('comments')" min-width="150">
                        <template #default="{ row }">
                            <span v-if="row.notes" style="color:#606266; font-size:0.85rem;">{{ row.notes }}</span>
                            <span v-else>-</span>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('procedures')" width="80" fixed="right">
                        <template #default="{ row }">
                            <el-button type="primary" size="small" @click="viewDetails(row)">
                                {{ $t('an_offer') }}
                            </el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <div v-if="!store.orders.length" class="empty-state">
                    <el-icon :size="48"><Folder /></el-icon>
                    <p>{{ $t('there_are_no_purchase_requests') }}</p>
                </div>

                <div v-if="store.pagination.total > store.pagination.per_page" class="pagination-wrapper">
                    <el-pagination
                        v-model:current-page="currentPage"
                        :page-size="store.pagination.per_page"
                        :total="store.pagination.total"
                        layout="prev, pager, next"
                        @current-change="handlePageChange"
                    />
                </div>
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { formatCurrency } from '@/utils/sales';
import { usePurchaseRequestsStore } from '@/stores/purchaseRequests';
import { ArrowDown, Refresh, Loading, Folder } from '@element-plus/icons-vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';

const router = useRouter();
const store = usePurchaseRequestsStore();
const searchQuery = ref('');
const filterStatus = ref('');
const currentPage = ref(1);

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

const statusCount = (status) => {
    return store.orders.filter(o => o.status === status).length;
};

const handleFilter = () => {
    currentPage.value = 1;
    loadOrders();
};

const handleSearch = () => {
    currentPage.value = 1;
    loadOrders();
};

const handlePageChange = (page) => {
    currentPage.value = page;
    loadOrders();
};

const loadOrders = () => {
    const params = { page: currentPage.value };
    if (filterStatus.value) params.status = filterStatus.value;
    if (searchQuery.value.trim()) params.search = searchQuery.value.trim();
    store.fetchOrders(params).catch(() => {});
};

const viewDetails = (row) => {
    router.push({ name: 'admin.purchase-requests.show', params: { id: row.id } });
};

const { t } = useI18n();

const stageActionLabel = (stage) => ({
    confirmed: t('confirm_order'),
    processing: t('start_preparation'),
    shipped: t('confirm_shipping'),
    delivered: t('deliver_and_settle_action'),
    cancelled: t('cancel_the_request'),
}[stage] || stage);

/**
 * A request is a sales order. Moving it is done where every sales order is
 * moved — the order screen opens on its execution tab and starts the step —
 * so confirming reserves stock and reports a shortage, shipping asks for the
 * tracking, delivery takes the payment and cancelling asks why. The status
 * menu here used to set any of six values directly and skip all of that.
 */
const executeStage = (row, stage) => {
    router.push({ path: '/admin/sales/sales-orders', query: { open: row.id, tab: 'execution', do: stage } });
};

const refresh = () => {
    loadOrders();
};

onMounted(() => {
    loadOrders();
});
</script>

<style scoped>
.purchase-requests-page {
    padding: 0;
}

.page-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.page-title h1 {
    margin: 0;
    font-size: 1.8rem;
    font-weight: 700;
    color: #1f2d3d;
}

.page-title p {
    margin: 0.35rem 0 0;
    color: #5f6d85;
}

.header-actions {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.search-input {
    width: 260px;
}

.overview-cards {
    margin-bottom: 1.5rem;
}

.summary-card {
    min-height: 100px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 0.4rem;
    border-radius: 1rem;
}

.summary-card p {
    margin: 0;
    color: #6b7c98;
    font-size: 0.95rem;
}

.summary-card h3 {
    margin: 0;
    font-size: 2rem;
    color: #253358;
}

.table-panel {
    border-radius: 1rem;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.item-line {
    padding: 2px 0;
    font-size: 0.875rem;
}

.loading-state,
.error-state,
.empty-state {
    padding: 3rem 1.25rem;
    text-align: center;
    color: #6b7c98;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
}

.loading-icon {
    font-size: 1.5rem;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.pagination-wrapper {
    display: flex;
    justify-content: center;
    margin-top: 1.5rem;
}
</style>
