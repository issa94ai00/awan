<template>
    <div class="purchase-requests-page">
        <div class="page-header">
            <div class="page-title">
                <h1>{{ $t('purchase_orders') }}</h1>
                <p>{{ $t('managing_purchase_orders_received_from') }}</p>
            </div>
            <div class="header-actions">
                <el-select v-model="filterStatus" :placeholder="$t('status')" clearable @change="handleFilter" style="width: 160px">
                    <el-option :label="$t('hanging')" value="pending" />
                    <el-option :label="$t('certain')" value="confirmed" />
                    <el-option :label="$t('in_process')" value="processing" />
                    <el-option :label="$t('shipped')" value="shipped" />
                    <el-option :label="$t('delivered')" value="delivered" />
                    <el-option :label="$t('canceled')" value="cancelled" />
                </el-select>
                <el-input v-model="searchQuery" :placeholder="$t('search_by_order_number_or_customer_name')" clearable @input="handleSearch" class="search-input" />
            </div>
        </div>

        <el-row :gutter="16" class="overview-cards">
            <el-col :xs="12" :sm="6">
                <el-card shadow="hover" class="summary-card">
                    <p>{{ $t('total_orders') }}</p>
                    <h3>{{ store.pagination.total }}</h3>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6">
                <el-card shadow="hover" class="summary-card">
                    <p>{{ $t('on_hold') }}</p>
                    <h3>{{ statusCount('pending') }}</h3>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6">
                <el-card shadow="hover" class="summary-card">
                    <p>{{ $t('in_process') }}</p>
                    <h3>{{ statusCount('processing') + statusCount('confirmed') }}</h3>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6">
                <el-card shadow="hover" class="summary-card">
                    <p>{{ $t('complete') }}</p>
                    <h3>{{ statusCount('delivered') }}</h3>
                </el-card>
            </el-col>
        </el-row>

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
                            ${{ parseFloat(row.total).toFixed(2) }}
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('status')" width="160">
                        <template #default="{ row }">
                            <el-dropdown @command="(val) => updateStatus(row.id, val)" trigger="click">
                                <el-tag :type="statusTagType(row.status)" style="cursor:pointer">
                                    {{ row.status_text || row.status }}
                                    <el-icon class="el-icon--right"><ArrowDown /></el-icon>
                                </el-tag>
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
import { usePurchaseRequestsStore } from '@/stores/purchaseRequests';
import { ElMessage } from 'element-plus';
import { ArrowDown, Refresh, Loading, Folder } from '@element-plus/icons-vue';

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

const updateStatus = async (id, status) => {
    try {
        await store.updateOrderStatus(id, status);
        ElMessage.success(window.t('status_updated_successfully'));
    } catch {
        ElMessage.error(window.t('status_update_failed'));
    }
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
