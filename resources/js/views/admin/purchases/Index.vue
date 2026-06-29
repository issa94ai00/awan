<template>
    <div class="purchases-index">
        <!-- Hero Panel Header -->
        <div class="hero-panel">
            <div class="hero-copy">
                <p class="eyebrow"><i class="fas fa-truck-loading"></i> {{ $t('procurement_overview') || 'نظرة عامة على المشتريات' }}</p>
                <h1>{{ $t('procurement_management_dashboard') || 'لوحة إدارة المشتريات والموردين' }}</h1>
                <p>{{ $t('view_purchase_orders_suppliers_and') || 'إدارة علاقات الموردين وتتبع طلبات التوريد وإيصالات استلام المستودعات بأسلوب ذكي.' }}</p>
            </div>
            <div class="hero-actions">
                <el-button type="success" @click="goToBladeDashboard" class="control-btn">
                    <i class="fas fa-external-link-alt"></i> {{ $t('full_control_panel') || 'لوحة التحكم الكاملة (Blade)' }}
                </el-button>
                <el-button type="primary" @click="refreshData" class="control-btn" plain>
                    <i class="fas fa-sync"></i> {{ $t('data_update') || 'تحديث البيانات' }}
                </el-button>
            </div>
        </div>

        <!-- Metrics Cards Grid -->
        <el-row :gutter="16" class="overview-cards">
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="stat-card-wrapper">
                    <div class="stat-card-inner">
                        <div class="stat-icon-box blue-grad">
                            <i class="fas fa-file-invoice"></i>
                        </div>
                        <div class="stat-details">
                            <h3>{{ ordersStore.orders.length }}</h3>
                            <p>{{ $t('purchase_orders') || 'أوامر الشراء' }}</p>
                        </div>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="stat-card-wrapper">
                    <div class="stat-card-inner">
                        <div class="stat-icon-box teal-grad">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <div class="stat-details">
                            <h3>{{ suppliersStore.suppliers.length }}</h3>
                            <p>{{ $t('suppliers') || 'الموردون' }}</p>
                        </div>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="stat-card-wrapper">
                    <div class="stat-card-inner">
                        <div class="stat-icon-box orange-grad">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <div class="stat-details">
                            <h3>{{ receiptsStore.receipts.length }}</h3>
                            <p>{{ $t('receipts') || 'إيصالات الاستلام' }}</p>
                        </div>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="stat-card-wrapper">
                    <div class="stat-card-inner">
                        <div class="stat-icon-box purple-grad">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-details">
                            <h3>{{ pendingOrdersCount }}</h3>
                            <p>{{ $t('pending_orders') || 'الطلبات المعلقة' }}</p>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-row :gutter="20" class="mt-4">
            <!-- Latest orders list -->
            <el-col :xs="24" :lg="16">
                <el-card shadow="hover" class="activity-card">
                    <template #header>
                        <div class="card-header">
                            <span><i class="fas fa-list text-muted"></i> {{ $t('latest_purchase_orders') || 'آخر أوامر الشراء' }}</span>
                            <el-button type="text" @click="goToOrders" class="more-link">
                                {{ $t('view_all') || 'عرض الكل' }} <i class="fas fa-chevron-left"></i>
                            </el-button>
                        </div>
                    </template>
                    
                    <div v-if="ordersStore.loading" class="loading-state">
                        <el-skeleton :rows="4" animated />
                    </div>
                    <div v-else>
                        <el-table :data="ordersStore.orders.slice(0, 5)" style="width: 100%" stripe class="custom-table">
                            <el-table-column prop="order_number" label="رقم الطلب" width="130">
                                <template #default="{ row }">
                                    <strong class="order-link" @click="viewOrder(row.id)">{{ row.order_number }}</strong>
                                </template>
                            </el-table-column>
                            <el-table-column prop="supplier.name" :label="$t('supplier') || 'المورد'">
                                <template #default="{ row }">
                                    <i class="fas fa-user-tie text-muted mr-1"></i>
                                    <span>{{ row.supplier?.name || '-' }}</span>
                                </template>
                            </el-table-column>
                            <el-table-column prop="total" :label="$t('total') || 'الإجمالي'" width="140">
                                <template #default="{ row }">
                                    <strong>${{ parseFloat(row.total || 0).toFixed(2) }}</strong>
                                </template>
                            </el-table-column>
                            <el-table-column :label="$t('status') || 'الحالة'" width="140" align="center">
                                <template #default="{ row }">
                                    <el-tag :type="statusTagType(row.status)" effect="light">
                                        {{ getArabicStatus(row.status) }}
                                    </el-tag>
                                </template>
                            </el-table-column>
                        </el-table>
                        <div v-if="!ordersStore.orders.length" class="empty-state">
                            <i class="fas fa-file-invoice empty-icon"></i>
                            <p>{{ $t('there_are_no_purchase_orders_yet') || 'لا توجد أوامر شراء مسجلة حالياً.' }}</p>
                        </div>
                    </div>
                </el-card>
            </el-col>

            <!-- Quick shortcuts and actions -->
            <el-col :xs="24" :lg="8">
                <el-card shadow="hover" class="insight-card">
                    <template #header>
                        <div class="card-header">
                            <span><i class="fas fa-bolt text-warning"></i> {{ $t('quick_actions') || 'إجراءات سريعة' }}</span>
                        </div>
                    </template>
                    <div class="insight-list">
                        <div class="insight-item-btn" @click="goToSuppliers">
                            <div class="btn-icon bg-blue">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="btn-info">
                                <h4>قائمة الموردين</h4>
                                <p>إدارة بيانات الموردين وعناوينهم</p>
                            </div>
                            <i class="fas fa-chevron-left btn-arrow"></i>
                        </div>
                        
                        <div class="insight-item-btn" @click="goToOrders">
                            <div class="btn-icon bg-yellow">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="btn-info">
                                <h4>أوامر الشراء</h4>
                                <p>متابعة الطلبات وتوريد المخزون</p>
                            </div>
                            <i class="fas fa-chevron-left btn-arrow"></i>
                        </div>

                        <div class="insight-item-btn" @click="goToReceipts">
                            <div class="btn-icon bg-green">
                                <i class="fas fa-receipt"></i>
                            </div>
                            <div class="btn-info">
                                <h4>إيصالات الاستلام</h4>
                                <p>تسجيل الوصولات وتحديث المخازن</p>
                            </div>
                            <i class="fas fa-chevron-left btn-arrow"></i>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { usePurchaseOrdersStore } from '@/stores/purchaseOrders';
import { useSuppliersStore } from '@/stores/suppliers';
import { usePurchaseReceiptsStore } from '@/stores/purchaseReceipts';

const router = useRouter();
const ordersStore = usePurchaseOrdersStore();
const suppliersStore = useSuppliersStore();
const receiptsStore = usePurchaseReceiptsStore();

const normalizeStatus = (status) => String(status || '').toLowerCase();

const statusTagType = (status) => {
    const value = normalizeStatus(status);
    if (['completed', 'complete', 'paid', 'delivered'].includes(value)) return 'success';
    if (['pending', 'hanging', 'in_process', 'processing', 'in progress'].includes(value)) return 'warning';
    if (['confirmed', 'shipped'].includes(value)) return 'info';
    if (['cancelled', 'canceled'].includes(value)) return 'danger';
    return 'info';
};

const getArabicStatus = (status) => {
    const value = normalizeStatus(status);
    const mapping = {
        'pending': 'معلق',
        'confirmed': 'مؤكد',
        'processing': 'قيد المعالجة',
        'shipped': 'تم الشحن',
        'delivered': 'تم التسليم',
        'completed': 'مكتمل',
        'cancelled': 'ملغي',
        'canceled': 'ملغي'
    };
    return mapping[value] || status;
};

const pendingOrdersCount = computed(() => {
    return ordersStore.orders.filter(order => {
        const val = normalizeStatus(order.status);
        return ['pending', 'processing', 'in progress'].includes(val);
    }).length;
});

const refreshData = async () => {
    await Promise.all([
        ordersStore.fetchOrders().catch(() => {}),
        suppliersStore.fetchSuppliers().catch(() => {}),
        receiptsStore.fetchReceipts().catch(() => {})
    ]);
};

const goToBladeDashboard = () => {
    router.push('/admin/purchases');
};

const goToSuppliers = () => {
    router.push('/admin/purchases/suppliers');
};

const goToOrders = () => {
    router.push('/admin/purchases/orders');
};

const goToReceipts = () => {
    router.push('/admin/purchases/receipts');
};

const viewOrder = (id) => {
    router.push(`/admin/purchases/orders?search=${id}`);
};

onMounted(refreshData);
</script>

<style scoped>
.purchases-index {
    padding: 0;
    font-family: 'Cairo', sans-serif;
}

.hero-panel {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 1.5rem;
    padding: 2rem;
    border-radius: 1.25rem;
    background: linear-gradient(135deg, #f8fafc 0%, #eff6ff 100%);
    margin-bottom: 2rem;
    border: 1px solid var(--border-color);
}

.hero-copy .eyebrow {
    margin: 0 0 0.5rem;
    color: var(--accent-blue);
    font-size: 0.9rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.hero-copy h1 {
    margin: 0;
    font-size: 1.8rem;
    font-weight: 800;
    color: var(--text-dark);
}

.hero-copy p {
    margin: 0.5rem 0 0;
    max-width: 44rem;
    color: var(--text-muted);
    font-size: 0.95rem;
    line-height: 1.6;
}

.hero-actions {
    display: flex;
    gap: 0.75rem;
}

.control-btn {
    font-weight: 600;
    border-radius: var(--radius-md);
    padding: 0.625rem 1.25rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.overview-cards {
    margin-bottom: 1.5rem;
}

.stat-card-wrapper {
    border-radius: 1rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.stat-card-wrapper:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
}

.stat-card-inner {
    display: flex;
    align-items: center;
    gap: 1.25rem;
}

.stat-icon-box {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.blue-grad {
    background: linear-gradient(135deg, var(--accent-blue) 0%, var(--accent-blue-light) 100%);
}

.teal-grad {
    background: linear-gradient(135deg, #0d9488 0%, #2dd4bf 100%);
}

.orange-grad {
    background: linear-gradient(135deg, var(--success) 0%, var(--success-dark) 100%);
}

.purple-grad {
    background: linear-gradient(135deg, var(--warning) 0%, var(--warning-dark) 100%);
}

.stat-details h3 {
    margin: 0;
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--text-dark);
    line-height: 1.2;
}

.stat-details p {
    margin: 0.25rem 0 0;
    color: var(--text-muted);
    font-size: 0.875rem;
    font-weight: 500;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: 700;
    color: var(--text-dark);
}

.card-header span {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.more-link {
    font-weight: 600;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.order-link {
    color: var(--accent-blue);
    cursor: pointer;
}

.order-link:hover {
    text-decoration: underline;
}

.empty-state {
    padding: 3rem 1.5rem;
    text-align: center;
    color: var(--text-muted);
}

.empty-icon {
    font-size: 2.5rem;
    color: var(--text-light);
    margin-bottom: 1rem;
    opacity: 0.5;
}

.insight-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.insight-item-btn {
    display: flex;
    align-items: center;
    padding: 1rem;
    border-radius: 12px;
    background: var(--bg-light);
    border: 1px solid var(--border-color);
    cursor: pointer;
    transition: var(--transition);
}

.insight-item-btn:hover {
    border-color: var(--accent-blue);
    transform: translateX(-4px);
    background: #fff;
    box-shadow: var(--shadow-sm);
}

.btn-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
    flex-shrink: 0;
    margin-left: 1rem;
}

.bg-blue { background-color: var(--accent-blue); }
.bg-yellow { background-color: var(--warning-dark); }
.bg-green { background-color: var(--success); }

.btn-info {
    flex: 1;
}

.btn-info h4 {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--text-dark);
}

.btn-info p {
    margin: 0.2rem 0 0 0;
    font-size: 0.8rem;
    color: var(--text-muted);
}

.btn-arrow {
    color: var(--text-light);
    font-size: 0.9rem;
    transition: var(--transition);
}

.insight-item-btn:hover .btn-arrow {
    color: var(--accent-blue);
}
</style>
