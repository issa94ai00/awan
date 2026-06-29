<template>
    <div class="sales-page sales-orders">
        <!-- Modern Header -->
        <div class="page-header">
            <div class="page-title">
                <h1><i class="fas fa-shopping-cart text-primary"></i> {{ $t('sales_orders') || 'طلبات البيع' }}</h1>
                <p>{{ $t('view_current_orders_with_quick') || 'إدارة ومتابعة طلبات بيع العملاء وتحويلها إلى فواتير.' }}</p>
            </div>
            <div class="header-actions">
                <el-input 
                    v-model="searchQuery" 
                    :placeholder="$t('search_by_order_number_or_customer_name') || 'ابحث برقم الطلب أو اسم العميل...'" 
                    clearable 
                    class="search-input"
                    :prefix-icon="Search"
                />
                <el-button type="primary" class="create-btn" @click="openCreateDrawer">
                    <i class="fas fa-plus"></i> {{ $t('new_sales_order') || 'طلب بيع جديد' }}
                </el-button>
            </div>
        </div>

        <!-- Metric Cards -->
        <el-row :gutter="16" class="overview-cards">
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="stat-card-wrapper">
                    <div class="stat-card-inner">
                        <div class="stat-icon-box blue-grad">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="stat-details">
                            <h3>{{ store.orders.length }}</h3>
                            <p>{{ $t('total_orders') || 'إجمالي طلبات البيع' }}</p>
                        </div>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="stat-card-wrapper">
                    <div class="stat-card-inner">
                        <div class="stat-icon-box orange-grad">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-details">
                            <h3>{{ pendingCount }}</h3>
                            <p>{{ $t('orders_are_being_processed') || 'طلبات معلقة' }}</p>
                        </div>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="stat-card-wrapper">
                    <div class="stat-card-inner">
                        <div class="stat-icon-box green-grad">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-details">
                            <h3>{{ completedCount }}</h3>
                            <p>{{ $t('completed_orders') || 'طلبات مكتملة' }}</p>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- Main Card & Table -->
        <el-card shadow="hover" class="table-panel">
            <template #header>
                <div class="card-header">
                    <span><i class="fas fa-list text-muted"></i> {{ $t('list_of_sales_orders') || 'جدول طلبات البيع' }}</span>
                </div>
            </template>

            <div v-if="store.loading" class="loading-state">
                <el-skeleton :rows="6" animated />
            </div>
            <div v-else>
                <el-table 
                    v-if="filteredOrders.length" 
                    :data="filteredOrders" 
                    style="width: 100%" 
                    stripe 
                    highlight-current-row
                    class="custom-table"
                >
                    <el-table-column prop="order_number" label="رقم الطلب" width="140">
                        <template #default="{ row }">
                            <span class="order-number-link" @click="openDetailDrawer(row.id)">{{ row.order_number }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="customer.name" :label="$t('client') || 'العميل'">
                        <template #default="{ row }">
                            <div class="customer-info-cell">
                                <i class="fas fa-user-circle text-muted"></i>
                                <span>{{ row.customer?.name || '-' }}</span>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column prop="total" :label="$t('total') || 'المبلغ الإجمالي'" width="160">
                        <template #default="{ row }">
                            <strong class="total-amount">${{ parseFloat(row.total || 0).toFixed(2) }}</strong>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('status') || 'حالة الطلب'" width="150" align="center">
                        <template #default="{ row }">
                            <el-tag :type="statusTagType(row.status)" effect="light" class="status-tag">
                                <i class="fas status-dot-icon" :class="statusIconClass(row.status)"></i>
                                {{ getArabicStatus(row.status) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="order_date" :label="$t('order_date') || 'التاريخ'" width="160" align="center" />
                    
                    <!-- Actions Column -->
                    <el-table-column label="الإجراءات" width="220" align="center">
                        <template #default="{ row }">
                            <el-button-group class="action-btn-group">
                                <el-button size="small" type="info" plain @click="openDetailDrawer(row.id)" title="عرض التفاصيل">
                                    <i class="fas fa-eye"></i>
                                </el-button>
                                <el-button size="small" type="warning" plain @click="openEditDrawer(row.id)" title="تعديل">
                                    <i class="fas fa-edit"></i>
                                </el-button>
                                <el-button size="small" type="danger" plain @click="deleteOrder(row.id)" title="حذف">
                                    <i class="fas fa-trash"></i>
                                </el-button>
                            </el-button-group>
                        </template>
                    </el-table-column>
                </el-table>

                <!-- Empty State -->
                <div v-if="!filteredOrders.length" class="empty-state-box">
                    <i class="fas fa-shopping-cart empty-icon"></i>
                    <p>{{ $t('there_are_no_requests_matching') || 'لا توجد طلبات بيع مطابقة حالياً.' }}</p>
                    <el-button type="primary" size="medium" @click="openCreateDrawer">
                        <i class="fas fa-plus"></i> إنشاء طلب جديد
                    </el-button>
                </div>
            </div>
        </el-card>

        <!-- Detail Drawer -->
        <el-drawer
            v-model="detailDrawerVisible"
            title="عرض تفاصيل طلب البيع"
            size="55%"
            direction="rtl"
            destroy-on-close
            class="detail-drawer"
        >
            <div v-if="loadingDetail" v-loading="loadingDetail" style="min-height: 250px;"></div>
            <div v-else-if="selectedOrder" class="drawer-detail-content">
                <!-- Timeline status tracker -->
                <div class="timeline-step-tracker mb-4">
                    <div class="visual-progress-timeline">
                        <div class="progress-base-bar"></div>
                        <div class="progress-fill-bar" :style="{ width: getTimelineProgressWidth(selectedOrder.status) }"></div>
                        
                        <div class="timeline-nodes-wrapper">
                            <div class="timeline-node" :class="{ completed: isStepCompleted(selectedOrder.status, 'pending') }">
                                <div class="node-icon"><i class="fas fa-clock"></i></div>
                                <span>معلق</span>
                            </div>
                            <div class="timeline-node" :class="{ completed: isStepCompleted(selectedOrder.status, 'confirmed') }">
                                <div class="node-icon"><i class="fas fa-check-circle"></i></div>
                                <span>مؤكد</span>
                            </div>
                            <div class="timeline-node" :class="{ completed: isStepCompleted(selectedOrder.status, 'processing') }">
                                <div class="node-icon"><i class="fas fa-sync-alt"></i></div>
                                <span>معالجة</span>
                            </div>
                            <div class="timeline-node" :class="{ completed: isStepCompleted(selectedOrder.status, 'shipped') }">
                                <div class="node-icon"><i class="fas fa-shipping-fast"></i></div>
                                <span>شحن</span>
                            </div>
                            <div class="timeline-node" :class="{ completed: isStepCompleted(selectedOrder.status, 'delivered') }">
                                <div class="node-icon"><i class="fas fa-truck-loading"></i></div>
                                <span>تسليم</span>
                            </div>
                        </div>
                    </div>
                </div>

                <el-row :gutter="20">
                    <!-- Left: Table of items -->
                    <el-col :xs="24" :lg="16">
                        <el-card shadow="never" class="mb-4">
                            <template #header>
                                <span class="card-title-txt"><i class="fas fa-boxes text-muted mr-1"></i> الأصناف المدرجة بالطلب</span>
                            </template>
                            <el-table :data="selectedOrder.items || []" style="width: 100%" stripe>
                                <el-table-column prop="product.name_ar" label="الصنف / المنتج" />
                                <el-table-column prop="quantity" label="الكمية" width="100" align="center" />
                                <el-table-column prop="unit_price" label="سعر الوحدة" width="120">
                                    <template #default="{ row }">${{ parseFloat(row.unit_price).toFixed(2) }}</template>
                                </el-table-column>
                                <el-table-column label="الإجمالي" width="120">
                                    <template #default="{ row }">${{ (row.quantity * row.unit_price).toFixed(2) }}</template>
                                </el-table-column>
                            </el-table>

                            <!-- Financial summary -->
                            <div class="financial-summary-block mt-4">
                                <div class="financial-row">
                                    <span>الخصم:</span>
                                    <span>${{ parseFloat(selectedOrder.discount || 0).toFixed(2) }}</span>
                                </div>
                                <div class="financial-row">
                                    <span>الضريبة:</span>
                                    <span>${{ parseFloat(selectedOrder.tax || 0).toFixed(2) }}</span>
                                </div>
                                <div class="financial-row grand-total">
                                    <span>الإجمالي الكلي:</span>
                                    <span>${{ parseFloat(selectedOrder.total || 0).toFixed(2) }}</span>
                                </div>
                            </div>
                        </el-card>

                        <!-- Notes card -->
                        <el-card v-if="selectedOrder.notes" shadow="never" class="mb-4">
                            <template #header>
                                <span class="card-title-txt"><i class="fas fa-sticky-note text-muted mr-1"></i> ملاحظات الطلب</span>
                            </template>
                            <p class="notes-txt-view">{{ selectedOrder.notes }}</p>
                        </el-card>
                    </el-col>

                    <!-- Right: Info cards & conversion -->
                    <el-col :xs="24" :lg="8">
                        <el-card shadow="never" class="mb-3">
                            <template #header>
                                <span class="card-title-txt"><i class="fas fa-user-circle text-muted mr-1"></i> بيانات العميل</span>
                            </template>
                            <div class="info-list">
                                <div class="info-item">
                                    <span class="lbl">الاسم:</span>
                                    <strong>{{ selectedOrder.customer?.name || '-' }}</strong>
                                </div>
                                <div class="info-item" v-if="selectedOrder.customer?.company">
                                    <span class="lbl">الشركة:</span>
                                    <strong>{{ selectedOrder.customer.company }}</strong>
                                </div>
                                <div class="info-item" v-if="selectedOrder.customer?.phone">
                                    <span class="lbl">الهاتف:</span>
                                    <strong>{{ selectedOrder.customer.phone }}</strong>
                                </div>
                            </div>
                        </el-card>

                        <el-card shadow="never" class="mb-3">
                            <template #header>
                                <span class="card-title-txt"><i class="fas fa-shipping-fast text-muted mr-1"></i> الشحن والتواريخ</span>
                            </template>
                            <div class="info-list">
                                <div class="info-item">
                                    <span class="lbl">عنوان التسليم:</span>
                                    <strong>{{ selectedOrder.shipping_address || '-' }}</strong>
                                </div>
                                <div class="info-item">
                                    <span class="lbl">تاريخ الطلب:</span>
                                    <strong>{{ selectedOrder.order_date || '-' }}</strong>
                                </div>
                                <div class="info-item">
                                    <span class="lbl">التسليم المتوقع:</span>
                                    <strong>{{ selectedOrder.expected_delivery || '-' }}</strong>
                                </div>
                            </div>
                        </el-card>

                        <!-- Invoice button conversion -->
                        <div v-if="selectedOrder.status === 'confirmed'" class="convert-card-box">
                            <p class="convert-tip">هذا الطلب مؤكد ويمكن تحويله لفاتورة مبيعات نشطة حالاً.</p>
                            <el-button type="success" class="convert-btn-invoice" @click="handleConvertToInvoice(selectedOrder.id)">
                                <i class="fas fa-file-invoice-dollar"></i> تحويل لفاتورة مبيعات
                            </el-button>
                        </div>
                    </el-col>
                </el-row>
            </div>
        </el-drawer>
    </div>
</template>

<script setup>
import { ref, onMounted, computed, reactive } from 'vue';
import { useRouter } from 'vue-router';
import { useSalesOrdersStore } from '@/stores/salesOrders';
import { useCustomersStore } from '@/stores/customers';
import { useProductsStore } from '@/stores/products';
import { salesOrdersApi } from '@/api/salesOrders';
import { ElMessage } from 'element-plus';
import { Search } from '@element-plus/icons-vue';

const router = useRouter();
const store = useSalesOrdersStore();
const customersStore = useCustomersStore();
const productsStore = useProductsStore();

const searchQuery = ref('');

// Drawers and actions state
const detailDrawerVisible = ref(false);
const loadingDetail = ref(false);
const selectedOrder = ref(null);

const normalizeStatus = (status) => String(status || '').toLowerCase();

const statusTagType = (status) => {
    const value = normalizeStatus(status);
    if (['completed', 'delivered', 'done'].includes(value)) return 'success';
    if (['pending', 'processing', 'in progress'].includes(value)) return 'warning';
    if (['confirmed', 'shipped'].includes(value)) return 'info';
    if (['cancelled', 'canceled'].includes(value)) return 'danger';
    return 'info';
};

const statusIconClass = (status) => {
    const value = normalizeStatus(status);
    if (['completed', 'delivered', 'done'].includes(value)) return 'fa-check-circle';
    if (['pending', 'processing'].includes(value)) return 'fa-clock';
    if (['cancelled', 'canceled'].includes(value)) return 'fa-times-circle';
    return 'fa-sync-alt';
};

const getArabicStatus = (status) => {
    const value = normalizeStatus(status);
    const mapping = {
        'pending': 'معلق',
        'confirmed': 'مؤكد',
        'processing': 'قيد المعالجة',
        'shipped': 'تم الشحن',
        'delivered': 'تم التسليم',
        'cancelled': 'ملغي',
        'canceled': 'ملغي'
    };
    return mapping[value] || status;
};

// Timeline step mappings
const getTimelineProgressWidth = (status) => {
    const value = normalizeStatus(status);
    if (value === 'pending') return '0%';
    if (value === 'confirmed') return '25%';
    if (value === 'processing') return '50%';
    if (value === 'shipped') return '75%';
    if (value === 'delivered') return '100%';
    return '0%';
};

const isStepCompleted = (currentStatus, step) => {
    const val = normalizeStatus(currentStatus);
    const steps = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];
    const currentIndex = steps.indexOf(val);
    const stepIndex = steps.indexOf(step);
    return stepIndex <= currentIndex;
};

const filteredOrders = computed(() => {
    if (!searchQuery.value.trim()) {
        return store.orders;
    }

    const query = searchQuery.value.toLowerCase();
    return store.orders.filter((order) => {
        return [
            order.order_number,
            order.customer?.name,
            order.status,
            order.order_date
        ].some((field) => String(field || '').toLowerCase().includes(query));
    });
});

const pendingCount = computed(() => store.orders.filter((order) => {
    const value = normalizeStatus(order.status);
    return ['pending', 'processing'].includes(value);
}).length);

const completedCount = computed(() => store.orders.filter((order) => {
    const value = normalizeStatus(order.status);
    return ['completed', 'delivered', 'done'].includes(value);
}).length);

// Drawer Actions
const openDetailDrawer = async (id) => {
    detailDrawerVisible.value = true;
    loadingDetail.value = true;
    try {
        const res = await salesOrdersApi.getById(id);
        selectedOrder.value = res.data.data;
    } catch (e) {
        ElMessage.error('خطأ أثناء تحميل تفاصيل الطلب.');
    } finally {
        loadingDetail.value = false;
    }
};

const openCreateDrawer = () => {
    router.push('/admin/sales/sales-orders/create');
};

const openEditDrawer = (id) => {
    router.push(`/admin/sales/sales-orders/${id}/edit`);
};

// Detail view actions

const deleteOrder = async (id) => {
    if (confirm('هل أنت متأكد من حذف هذا الطلب؟')) {
        try {
            await salesOrdersApi.delete(id);
            ElMessage.success('تم حذف طلب البيع بنجاح.');
            await store.fetchOrders();
        } catch (error) {
            ElMessage.error('خطأ أثناء حذف طلب البيع.');
        }
    }
};

const handleConvertToInvoice = async (id) => {
    try {
        await salesOrdersApi.convertToInvoice(id);
        ElMessage.success('تم تحويل طلب البيع إلى فاتورة بنجاح.');
        detailDrawerVisible.value = false;
        await store.fetchOrders();
    } catch (e) {
        ElMessage.error('حدث خطأ أثناء تحويل الطلب إلى فاتورة.');
    }
};

onMounted(async () => {
    store.fetchOrders().catch(() => {});
    customersStore.fetchCustomers().catch(() => {});
    productsStore.fetchProducts({ per_page: 100 }).catch(() => {});
});
</script>

<style scoped>
.sales-page {
    padding: 0;
    font-family: 'Cairo', sans-serif;
}

.page-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1.25rem;
    margin-bottom: 2rem;
    padding-bottom: 1.25rem;
    border-bottom: 2px solid var(--border-color);
}

.page-title h1 {
    margin: 0;
    font-size: 1.6rem;
    font-weight: 700;
    color: var(--text-dark);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.page-title p {
    margin: 0.5rem 0 0;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.header-actions {
    display: flex;
    gap: 0.75rem;
    align-items: center;
    flex-wrap: wrap;
}

.search-input {
    width: min(100%, 280px);
}

.create-btn {
    font-weight: 600;
    border-radius: var(--radius-md);
    padding: 0.625rem 1.25rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.overview-cards {
    margin-bottom: 2rem;
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

.orange-grad {
    background: linear-gradient(135deg, var(--warning) 0%, var(--warning-dark) 100%);
}

.green-grad {
    background: linear-gradient(135deg, var(--success) 0%, var(--success-dark) 100%);
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

.table-panel {
    border-radius: 1rem;
    overflow: hidden;
}

.card-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 700;
    color: var(--text-dark);
}

.order-number-link {
    color: var(--accent-blue);
    font-weight: 700;
    cursor: pointer;
    transition: var(--transition);
}

.order-number-link:hover {
    text-decoration: underline;
    opacity: 0.8;
}

.customer-info-cell {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
}

.total-amount {
    color: var(--text-dark);
    font-size: 0.95rem;
}

.status-tag {
    font-size: 0.8rem;
    font-weight: 600;
    border-radius: 20px;
    padding: 0.25rem 0.75rem;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.status-dot-icon {
    font-size: 0.8rem;
}

.action-btn-group .el-button {
    padding: 0.4rem 0.6rem;
}

.loading-state {
    padding: 2rem;
}

.empty-state-box {
    padding: 4rem 2rem;
    text-align: center;
    color: var(--text-muted);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.empty-icon {
    font-size: 3.5rem;
    color: var(--text-light);
    margin-bottom: 1.25rem;
    opacity: 0.5;
}

.empty-state-box p {
    font-weight: 500;
    font-size: 1.05rem;
    margin-bottom: 1.5rem;
}

/* Detail Drawer Styles */
.drawer-detail-content {
    padding: 1.5rem;
    font-family: 'Cairo', sans-serif;
}

.timeline-step-tracker {
    background: var(--bg-light);
    border: 1px solid var(--border-color);
    padding: 1.75rem 1.25rem;
    border-radius: var(--radius-md);
}

.visual-progress-timeline {
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
}

.progress-base-bar {
    position: absolute;
    top: 20px;
    left: 8%;
    right: 8%;
    height: 4px;
    background: var(--border-color);
    z-index: 1;
}

.progress-fill-bar {
    position: absolute;
    top: 20px;
    left: 8%;
    height: 4px;
    background: var(--success);
    z-index: 2;
    transition: width 0.4s ease;
}

.timeline-nodes-wrapper {
    display: flex;
    justify-content: space-around;
    width: 100%;
    z-index: 3;
}

.timeline-node {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    position: relative;
}

.node-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--text-light);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    box-shadow: var(--shadow-sm);
    transition: background 0.3s ease;
}

.timeline-node.completed .node-icon {
    background: var(--success);
}

.timeline-node span {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-muted);
}

.timeline-node.completed span {
    color: var(--text-dark);
}

.card-title-txt {
    font-weight: 700;
    font-size: 0.95rem;
    color: var(--text-dark);
}

.financial-summary-block {
    background: var(--bg-light);
    border: 1px solid var(--border-color);
    padding: 1.25rem;
    border-radius: var(--radius-md);
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.5rem;
}

.financial-row {
    display: flex;
    justify-content: space-between;
    width: 250px;
    color: var(--text-medium);
    font-size: 0.9rem;
}

.financial-row.grand-total {
    border-top: 2px solid var(--border-color);
    padding-top: 0.5rem;
    font-weight: 700;
    font-size: 1.05rem;
    color: var(--accent-blue);
}

.notes-txt-view {
    margin: 0;
    font-size: 0.9rem;
    color: var(--text-medium);
    line-height: 1.6;
}

.info-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.info-item {
    display: flex;
    justify-content: space-between;
    font-size: 0.9rem;
}

.info-item .lbl {
    color: var(--text-muted);
}

.info-item strong {
    color: var(--text-dark);
}

.convert-card-box {
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    padding: 1.25rem;
    border-radius: var(--radius-md);
    text-align: center;
}

.convert-tip {
    font-size: 0.85rem;
    color: #065f46;
    margin: 0 0 1rem 0;
    line-height: 1.5;
}

.convert-btn-invoice {
    width: 100%;
    font-weight: 700;
}

/* Form Grid row */
.item-grid-row {
    display: flex;
    gap: 1rem;
    align-items: center;
    margin-bottom: 1rem;
    padding: 1rem;
    background: var(--bg-light);
    border-radius: var(--radius-md);
    border: 1px solid var(--border-color);
}
</style>
