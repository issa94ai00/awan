<!-- resources/js/views/admin/wms/Dashboard.vue -->
<template>
    <div class="wms-dashboard-page">
        <AdminPageHeader icon="fas fa-gauge-high" :title="$t('wms_dashboard')">
            <template #actions>
                <el-popover placement="bottom-end" trigger="click" width="340" popper-class="alerts-popover">
                    <template #reference>
                        <el-badge :value="alerts.length" :hidden="!alerts.length" type="danger">
                            <el-button type="danger" plain>
                                <i class="fas fa-triangle-exclamation"></i> {{ $t('alerts_with_icon') }}
                            </el-button>
                        </el-badge>
                    </template>
                    <div class="alerts-list">
                        <h4 class="alerts-title">{{ $t('recent_alerts') }}</h4>
                        <el-empty v-if="!alerts.length" :description="$t('no_alerts')" :image-size="60" />
                        <div v-for="alert in alerts" :key="alert.id" class="alert-row" @click="navigateTo(`/admin/products/${alert.product_id}`)">
                            <i class="fas fa-triangle-exclamation alert-icon"></i>
                            <div>
                                <p class="alert-message">{{ alert.message }}</p>
                                <p class="alert-date">{{ alert.created_at }}</p>
                            </div>
                        </div>
                    </div>
                </el-popover>
            </template>
        </AdminPageHeader>

        <div v-if="loading" class="loading-state"><el-skeleton :rows="8" animated /></div>

        <template v-else>
            <div class="stat-grid mb-3">
                <el-card shadow="never" class="stat-card stat-blue">
                    <span class="stat-label">{{ $t('linked_products') }}</span>
                    <strong class="stat-value">{{ formatNumber(stats.linked_products) }}</strong>
                </el-card>
                <el-card shadow="never" class="stat-card stat-green">
                    <span class="stat-label">{{ $t('active_warehouses') }}</span>
                    <strong class="stat-value">{{ stats.active_warehouses }} / {{ stats.total_warehouses }}</strong>
                </el-card>
                <el-card shadow="never" class="stat-card stat-yellow">
                    <span class="stat-label">{{ $t('products_needing_reorder') }}</span>
                    <strong class="stat-value">{{ formatNumber(stats.reorder_products) }}</strong>
                </el-card>
                <el-card shadow="never" class="stat-card stat-purple">
                    <span class="stat-label">{{ $t('total_stock') }}</span>
                    <strong class="stat-value">{{ formatNumber(stats.total_stock) }}</strong>
                </el-card>
            </div>

            <div class="stat-grid mb-3">
                <el-card shadow="never" class="stat-card stat-orange">
                    <span class="stat-label">{{ $t('stock_value') }}</span>
                    <strong class="stat-value">{{ formatMoney(stats.total_value) }}</strong>
                </el-card>
                <el-card shadow="never" class="stat-card stat-red">
                    <span class="stat-label">{{ $t('movements_today') }}</span>
                    <strong class="stat-value">{{ formatNumber(stats.today_movements) }}</strong>
                </el-card>
                <el-card shadow="never" class="stat-card stat-blue">
                    <span class="stat-label">{{ $t('active_users') }}</span>
                    <strong class="stat-value">{{ formatNumber(stats.active_users) }}</strong>
                </el-card>
                <el-card shadow="never" class="stat-card stat-green">
                    <span class="stat-label">{{ $t('avg_utilization') }}</span>
                    <strong class="stat-value">{{ stats.avg_utilization }}%</strong>
                </el-card>
            </div>

            <div class="two-col mb-3">
                <el-card shadow="never">
                    <template #header>{{ $t('top_five_consumed_products') }}</template>
                    <div v-if="topProducts.length" class="bar-list">
                        <div v-for="(product, index) in topProducts" :key="product.id" class="bar-row">
                            <span class="bar-rank">{{ index + 1 }}</span>
                            <div class="bar-body">
                                <div class="bar-head">
                                    <span>{{ product.name }}</span>
                                    <span class="bar-value">{{ formatNumber(product.consumption) }}</span>
                                </div>
                                <el-progress :percentage="pct(product.consumption, topProducts[0].consumption)" :show-text="false" :stroke-width="8" />
                            </div>
                        </div>
                    </div>
                    <el-empty v-else :description="$t('no_data')" :image-size="60" />
                </el-card>

                <el-card shadow="never">
                    <template #header>{{ $t('stock_distribution_across_warehouses') }}</template>
                    <div v-if="warehouseDistribution.length" class="bar-list">
                        <div v-for="(warehouse, index) in warehouseDistribution" :key="warehouse.id" class="bar-row">
                            <span class="bar-rank bar-rank-green">{{ index + 1 }}</span>
                            <div class="bar-body">
                                <div class="bar-head">
                                    <span>{{ warehouse.name }}</span>
                                    <span class="bar-value">{{ warehouse.percentage }}%</span>
                                </div>
                                <el-progress :percentage="warehouse.percentage" :show-text="false" :stroke-width="8" color="#67c23a" />
                            </div>
                        </div>
                    </div>
                    <el-empty v-else :description="$t('no_data')" :image-size="60" />
                </el-card>
            </div>

            <el-card shadow="never" class="mb-3">
                <template #header>
                    <div class="card-header">
                        <span>{{ $t('latest_stock_movements') }}</span>
                        <el-button text @click="navigateTo('/admin/wms/stock/balances')">{{ $t('view_all') }}</el-button>
                    </div>
                </template>
                <el-table :data="recentMovements" stripe>
                    <el-table-column :label="$t('date')" width="150">
                        <template #default="{ row }">{{ row.date }}</template>
                    </el-table-column>
                    <el-table-column :label="$t('product')" min-width="160">
                        <template #default="{ row }">{{ row.product }}</template>
                    </el-table-column>
                    <el-table-column :label="$t('warehouse')" min-width="140">
                        <template #default="{ row }">{{ row.warehouse }}</template>
                    </el-table-column>
                    <el-table-column :label="$t('type')" width="110" align="center">
                        <template #default="{ row }">
                            <el-tag size="small" :type="{ in: 'success', out: 'danger', adjustment: 'warning' }[row.type] || 'info'">{{ row.type_text }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('quantity')" width="100" align="center">
                        <template #default="{ row }">{{ row.quantity }}</template>
                    </el-table-column>
                </el-table>
                <el-empty v-if="!recentMovements.length" :description="$t('no_movements_today')" :image-size="60" />
            </el-card>

            <el-card shadow="never">
                <template #header>{{ $t('quick_links') }}</template>
                <div class="quick-links-grid">
                    <button class="quick-link" @click="navigateTo('/admin/wms/products')">
                        <i class="fas fa-boxes-packing"></i> {{ $t('nav_products') }}
                    </button>
                    <button class="quick-link" @click="navigateTo('/admin/wms/warehouses')">
                        <i class="fas fa-warehouse"></i> {{ $t('warehouses') }}
                    </button>
                    <button class="quick-link" @click="navigateTo('/admin/wms/stock/balances')">
                        <i class="fas fa-scale-balanced"></i> {{ $t('balances') }}
                    </button>
                    <button class="quick-link" @click="navigateTo('/admin/reports/inventory')">
                        <i class="fas fa-chart-column"></i> {{ $t('nav_reports') }}
                    </button>
                </div>
            </el-card>
        </template>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { ElNotification } from 'element-plus';
import api from '@/api';
import { formatNumber as formatCount, formatMoney as formatMoneyValue } from '@/utils/currency';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';

const router = useRouter();
const { t } = useI18n();
const loading = ref(true);
const stats = ref({
    linked_products: 0,
    total_products: 0,
    active_warehouses: 0,
    total_warehouses: 0,
    reorder_products: 0,
    total_stock: 0,
    total_value: 0,
    today_movements: 0,
    active_users: 0,
    avg_utilization: 0,
});
const topProducts = ref([]);
const warehouseDistribution = ref([]);
const recentMovements = ref([]);
const alerts = ref([]);

async function fetchDashboardData() {
    loading.value = true;
    try {
        const response = await api.get('/admin/wms/dashboard');
        stats.value = response.data.stats;
        topProducts.value = response.data.top_products || [];
        warehouseDistribution.value = response.data.warehouse_distribution || [];
        recentMovements.value = response.data.recent_movements || [];
        alerts.value = response.data.alerts || [];
    } catch (error) {
        console.error('Error fetching dashboard data:', error);
    } finally {
        loading.value = false;
    }
}

function setupEchoListeners() {
    if (typeof window.Echo === 'undefined') return;
    window.Echo.private('dashboard.alerts')
        .listen('StockAlert', (e) => {
            alerts.value.unshift(e.alert);
            ElNotification({ title: t('recent_alerts'), message: e.alert.message, type: 'warning', position: 'bottom-right' });
        });
}

onMounted(() => {
    fetchDashboardData();
    setupEchoListeners();
});

onUnmounted(() => {
    if (window.Echo) window.Echo.leave('dashboard.alerts');
});

function navigateTo(path) {
    router.push(path);
}

function pct(value, max) {
    if (!max) return 0;
    return Math.round((value / max) * 100);
}

function formatNumber(num) {
    if (num === null || num === undefined) return '—';
    return formatCount(num);
}

function formatMoney(value) {
    return formatMoneyValue(value);
}
</script>

<style scoped>
.wms-dashboard-page { font-family: 'Cairo', sans-serif; }

.stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; }
.stat-card { border-radius: 12px; border-inline-start: 4px solid var(--el-color-info); }
.stat-card :deep(.el-card__body) { display: flex; flex-direction: column; gap: 0.3rem; }
.stat-blue { border-inline-start-color: var(--el-color-primary); }
.stat-green { border-inline-start-color: var(--el-color-success); }
.stat-yellow { border-inline-start-color: var(--el-color-warning); }
.stat-purple { border-inline-start-color: #8b5cf6; }
.stat-orange { border-inline-start-color: #f97316; }
.stat-red { border-inline-start-color: var(--el-color-danger); }
.stat-label { font-size: 0.8rem; color: var(--text-muted); }
.stat-value { font-size: 1.4rem; font-weight: 800; }

.two-col { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1rem; }

.bar-list { display: flex; flex-direction: column; gap: 0.9rem; }
.bar-row { display: flex; align-items: center; gap: 0.75rem; }
.bar-rank { flex: 0 0 auto; width: 26px; height: 26px; border-radius: 50%; background: var(--el-color-primary-light-8); color: var(--el-color-primary); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.8rem; }
.bar-rank-green { background: var(--el-color-success-light-8); color: var(--el-color-success); }
.bar-body { flex: 1 1 auto; min-width: 0; }
.bar-head { display: flex; justify-content: space-between; gap: 0.5rem; font-size: 0.85rem; margin-bottom: 0.3rem; }
.bar-value { color: var(--text-muted); }

.card-header { display: flex; align-items: center; justify-content: space-between; }

.quick-links-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 0.75rem; }
.quick-link {
    display: flex; flex-direction: column; align-items: center; gap: 0.5rem;
    padding: 1.1rem 0.5rem; border: 1px solid var(--border-color); border-radius: 10px;
    background: var(--el-fill-color-blank); cursor: pointer; font-size: 0.85rem; font-weight: 600;
    transition: box-shadow .15s ease, transform .15s ease;
}
.quick-link:hover { box-shadow: 0 4px 12px rgba(0,0,0,.08); transform: translateY(-1px); }
.quick-link i { font-size: 1.4rem; color: var(--el-color-primary); }

.alerts-list { max-height: 360px; overflow-y: auto; }
.alerts-title { margin: 0 0 0.5rem; font-size: 0.9rem; font-weight: 700; }
.alert-row { display: flex; gap: 0.6rem; padding: 0.6rem 0.2rem; border-bottom: 1px solid var(--border-color); cursor: pointer; }
.alert-row:hover { background: var(--el-fill-color-light); }
.alert-row:last-child { border-bottom: none; }
.alert-icon { color: var(--el-color-warning); margin-top: 0.15rem; }
.alert-message { margin: 0; font-size: 0.82rem; }
.alert-date { margin: 0.15rem 0 0; font-size: 0.72rem; color: var(--text-muted); }

.loading-state { padding: 2rem; }
.mb-3 { margin-bottom: 0.75rem; }
</style>
