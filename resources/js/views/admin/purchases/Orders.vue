<template>
    <div class="purchases-page purchases-orders">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title">
                <h1><i class="fas fa-file-signature text-primary"></i> {{ $t('purchase_orders') || 'أوامر الشراء' }}</h1>
                <p>{{ $t('follow_current_orders_and_use') || 'متابعة وإدارة طلبات المشتريات المصدرة للموردين وتتبع حالاتها.' }}</p>
            </div>
            <div class="header-actions">
                <el-input 
                    v-model="searchQuery" 
                    :placeholder="$t('search_by_order_number_or_supplier_name') || 'ابحث برقم الطلب أو المورد...'" 
                    clearable 
                    class="search-input"
                    :prefix-icon="Search"
                />
                <el-button type="primary" class="create-btn" @click="openCreateDrawer">
                    <i class="fas fa-plus"></i> أمر شراء جديد
                </el-button>
            </div>
        </div>

        <!-- Metrics cards row -->
        <el-row :gutter="16" class="overview-cards">
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="stat-card-wrapper">
                    <div class="stat-card-inner">
                        <div class="stat-icon-box blue-grad">
                            <i class="fas fa-file-signature"></i>
                        </div>
                        <div class="stat-details">
                            <h3>{{ store.orders.length }}</h3>
                            <p>{{ $t('total_orders') || 'إجمالي الأوامر' }}</p>
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
                            <p>{{ $t('pending_orders') || 'أوامر معلقة' }}</p>
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
                            <p>{{ $t('completed_orders') || 'أوامر مكتملة' }}</p>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- Table Panel -->
        <el-card shadow="hover" class="table-panel">
            <template #header>
                <div class="card-header">
                    <span><i class="fas fa-list text-muted"></i> {{ $t('purchase_order_list') || 'جدول أوامر الشراء' }}</span>
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
                    <el-table-column prop="supplier.name" :label="$t('supplier') || 'المورد'">
                        <template #default="{ row }">
                            <div class="supplier-cell">
                                <i class="fas fa-user-tie text-muted"></i>
                                <span>{{ row.supplier?.name || '-' }}</span>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column prop="total" :label="$t('total') || 'المبلغ الإجمالي'" width="160">
                        <template #default="{ row }">
                            <strong class="amount-txt">${{ parseFloat(row.total || 0).toFixed(2) }}</strong>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('status') || 'الحالة'" width="150" align="center">
                        <template #default="{ row }">
                            <el-tag :type="statusTagType(row.status)" effect="light" class="status-tag">
                                <i class="fas status-dot-icon" :class="statusIconClass(row.status)"></i>
                                {{ getArabicStatus(row.status) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="due_date" :label="$t('due_date') || 'تاريخ الاستحقاق'" width="160" align="center" />
                    
                    <!-- Actions Column -->
                    <el-table-column label="الإجراءات" width="260" align="center">
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
                            
                            <el-button 
                                v-if="['confirmed', 'processing'].includes(normalizeStatus(row.status))"
                                size="small" 
                                type="success" 
                                plain 
                                style="margin-right: 0.5rem;"
                                @click="receiveGoods(row.id)"
                            >
                                <i class="fas fa-arrow-alt-circle-down"></i> تلقي
                            </el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <!-- Empty State -->
                <div v-if="!filteredOrders.length" class="empty-state-box">
                    <i class="fas fa-file-signature empty-icon"></i>
                    <p>{{ $t('there_are_no_requests_matching') || 'لا توجد أوامر شراء مطابقة.' }}</p>
                    <el-button type="primary" size="medium" @click="openCreateDrawer">
                        <i class="fas fa-plus"></i> إنشاء أول أمر شراء
                    </el-button>
                </div>
            </div>
        </el-card>

        <!-- Detail Drawer -->
        <el-drawer
            v-model="detailDrawerVisible"
            title="تفاصيل أمر الشراء"
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
                            <div class="timeline-node" :class="{ completed: isStepCompleted(selectedOrder.status, 'completed') }">
                                <div class="node-icon"><i class="fas fa-check-double"></i></div>
                                <span>مكتمل</span>
                            </div>
                        </div>
                    </div>
                </div>

                <el-row :gutter="20">
                    <!-- Left: items table & billing -->
                    <el-col :xs="24" :lg="16">
                        <el-card shadow="never" class="mb-4">
                            <template #header>
                                <span class="card-title-txt"><i class="fas fa-boxes text-muted mr-1"></i> الأصناف المطلوبة للشراء</span>
                            </template>
                            <el-table :data="selectedOrder.items || []" style="width: 100%" stripe>
                                <el-table-column prop="product.name_ar" label="الصنف / المنتج" />
                                <el-table-column prop="quantity" label="الكمية المطلوبة" width="130" align="center" />
                                <el-table-column prop="unit_price" label="سعر الوحدة" width="120">
                                    <template #default="{ row }">${{ parseFloat(row.unit_price || 0).toFixed(2) }}</template>
                                </el-table-column>
                                <el-table-column label="الإجمالي" width="120">
                                    <template #default="{ row }">${{ (row.quantity * row.unit_price).toFixed(2) }}</template>
                                </el-table-column>
                            </el-table>

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
                                <span class="card-title-txt"><i class="fas fa-sticky-note text-muted mr-1"></i> ملاحظات</span>
                            </template>
                            <p class="notes-txt-view">{{ selectedOrder.notes }}</p>
                        </el-card>
                    </el-col>

                    <!-- Right: supplier info -->
                    <el-col :xs="24" :lg="8">
                        <el-card shadow="never" class="mb-3">
                            <template #header>
                                <span class="card-title-txt"><i class="fas fa-user-tie text-muted mr-1"></i> بيانات المورد</span>
                            </template>
                            <div class="info-list">
                                <div class="info-item">
                                    <span class="lbl">اسم المورد:</span>
                                    <strong>{{ selectedOrder.supplier?.name || '-' }}</strong>
                                </div>
                                <div class="info-item" v-if="selectedOrder.supplier?.company">
                                    <span class="lbl">الشركة:</span>
                                    <strong>{{ selectedOrder.supplier.company }}</strong>
                                </div>
                                <div class="info-item" v-if="selectedOrder.supplier?.phone">
                                    <span class="lbl">الهاتف:</span>
                                    <strong>{{ selectedOrder.supplier.phone }}</strong>
                                </div>
                            </div>
                        </el-card>

                        <el-card shadow="never" class="mb-3">
                            <template #header>
                                <span class="card-title-txt"><i class="fas fa-calendar-alt text-muted mr-1"></i> التواريخ الهامة</span>
                            </template>
                            <div class="info-list">
                                <div class="info-item">
                                    <span class="lbl">تاريخ الطلب:</span>
                                    <strong>{{ selectedOrder.order_date || '-' }}</strong>
                                </div>
                                <div class="info-item">
                                    <span class="lbl">تاريخ الاستحقاق:</span>
                                    <strong>{{ selectedOrder.due_date || '-' }}</strong>
                                </div>
                            </div>
                        </el-card>

                        <!-- Receive button inside drawer -->
                        <div v-if="['confirmed', 'processing'].includes(normalizeStatus(selectedOrder.status))" class="receive-card-box">
                            <p class="receive-tip">يمكن استلام هذه البضائع وتوثيق إدخالها للمستودع الآن.</p>
                            <el-button type="success" style="width: 100%; font-weight: 700;" @click="receiveGoods(selectedOrder.id)">
                                <i class="fas fa-arrow-alt-circle-down"></i> تسجيل إيصال الاستلام
                            </el-button>
                        </div>
                    </el-col>
                </el-row>
            </div>
        </el-drawer>

        <!-- Form Drawer (Create / Edit) -->
        <el-drawer
            v-model="formDrawerVisible"
            :title="isEditMode ? 'تعديل أمر شراء' : 'إنشاء أمر شراء جديد'"
            size="55%"
            direction="rtl"
            destroy-on-close
            class="form-drawer"
        >
            <el-form :model="form" label-position="top">
                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-form-item label="المورد" required>
                            <el-select v-model="form.supplier_id" placeholder="اختر المورد" style="width: 100%" filterable>
                                <el-option 
                                    v-for="s in suppliersStore.suppliers" 
                                    :key="s.id" 
                                    :label="s.name" 
                                    :value="s.id" 
                                />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12" v-if="isEditMode">
                        <el-form-item label="حالة أمر الشراء" required>
                            <el-select v-model="form.status" placeholder="اختر حالة الطلب" style="width: 100%">
                                <option value="pending" label="معلق" />
                                <option value="confirmed" label="مؤكد" />
                                <option value="processing" label="قيد المعالجة" />
                                <option value="completed" label="مكتمل" />
                                <option value="cancelled" label="ملغي" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20" class="mt-3">
                    <el-col :span="12">
                        <el-form-item label="تاريخ أمر الشراء">
                            <el-date-picker v-model="form.order_date" type="date" placeholder="تاريخ الطلب" format="YYYY-MM-DD" value-format="YYYY-MM-DD" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="تاريخ الاستحقاق">
                            <el-date-picker v-model="form.due_date" type="date" placeholder="تاريخ الاستحقاق" format="YYYY-MM-DD" value-format="YYYY-MM-DD" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20" class="mt-3">
                    <el-col :span="12">
                        <el-form-item label="الخصم">
                            <el-input v-model="form.discount" type="number" placeholder="قيمة الخصم..." style="width: 100%">
                                <template #suffix>$</template>
                            </el-input>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="الضريبة">
                            <el-input v-model="form.tax" type="number" placeholder="قيمة الضريبة..." style="width: 100%">
                                <template #suffix>$</template>
                            </el-input>
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-form-item label="ملاحظات" class="mt-3">
                    <el-input v-model="form.notes" type="textarea" :rows="3" placeholder="أضف أي ملاحظات خاصة بأمر الشراء هنا..." />
                </el-form-item>

                <!-- Dynamic items grid -->
                <div style="border-top: 1px solid var(--border-color); margin-top: 2rem; padding-top: 1.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                        <h3 style="margin: 0; font-size: 1.1rem; font-weight: 700;"><i class="fas fa-boxes text-primary"></i> الأصناف والكميات</h3>
                        <el-button type="primary" size="small" plain @click="addItemRow">
                            <i class="fas fa-plus"></i> إضافة صنف
                        </el-button>
                    </div>

                    <div class="items-grid-wrapper">
                        <div v-for="(item, idx) in form.items" :key="idx" class="item-grid-row">
                            <el-select v-model="item.product_id" placeholder="اختر الصنف" filterable style="flex: 2.5;" @change="(val) => updateItemPrice(val, idx)">
                                <el-option 
                                    v-for="p in productsStore.products" 
                                    :key="p.id" 
                                    :label="p.name_ar + ' - $' + p.price" 
                                    :value="p.id" 
                                />
                            </el-select>
                            <el-input-number v-model="item.quantity" :min="1" placeholder="الكمية" style="flex: 1;" />
                            <el-input v-model="item.unit_price" placeholder="السعر" style="flex: 1;">
                                <template #suffix>$</template>
                            </el-input>
                            <el-button type="danger" circle @click="removeItemRow(idx)" :disabled="form.items.length <= 1">
                                <i class="fas fa-trash"></i>
                            </el-button>
                        </div>
                    </div>
                </div>

                <div style="border-top: 1px solid var(--border-color); margin-top: 2rem; padding-top: 1.5rem; display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <el-button @click="formDrawerVisible = false">إلغاء</el-button>
                    <el-button type="primary" :loading="submittingForm" @click="saveOrder">حفظ أمر الشراء</el-button>
                </div>
            </el-form>
        </el-drawer>
    </div>
</template>

<script setup>
import { ref, onMounted, computed, reactive } from 'vue';
import { useRouter } from 'vue-router';
import { usePurchaseOrdersStore } from '@/stores/purchaseOrders';
import { useSuppliersStore } from '@/stores/suppliers';
import { useProductsStore } from '@/stores/products';
import { purchaseOrdersApi } from '@/api/purchaseOrders';
import { Search } from '@element-plus/icons-vue';
import { ElMessage } from 'element-plus';

const router = useRouter();
const store = usePurchaseOrdersStore();
const suppliersStore = useSuppliersStore();
const productsStore = useProductsStore();

const searchQuery = ref('');

// Drawers and actions state
const detailDrawerVisible = ref(false);
const loadingDetail = ref(false);
const selectedOrder = ref(null);

const formDrawerVisible = ref(false);
const isEditMode = ref(false);
const submittingForm = ref(false);
const editingOrderId = ref(null);

const form = reactive({
    supplier_id: '',
    status: 'pending',
    order_date: '',
    due_date: '',
    discount: 0,
    tax: 0,
    notes: '',
    items: []
});

const resetForm = () => {
    form.supplier_id = '';
    form.status = 'pending';
    form.order_date = new Date().toISOString().split('T')[0];
    form.due_date = '';
    form.discount = 0;
    form.tax = 0;
    form.notes = '';
    form.items = [{ product_id: '', quantity: 1, unit_price: '' }];
};

const normalizeStatus = (status) => String(status || '').toLowerCase();

const statusTagType = (status) => {
    const value = normalizeStatus(status);
    if (['completed', 'complete', 'paid', 'delivered'].includes(value)) return 'success';
    if (['pending', 'hanging', 'in_process', 'processing', 'in progress'].includes(value)) return 'warning';
    if (['confirmed', 'shipped'].includes(value)) return 'info';
    if (['cancelled', 'canceled'].includes(value)) return 'danger';
    return 'info';
};

const statusIconClass = (status) => {
    const value = normalizeStatus(status);
    if (['completed', 'complete', 'paid', 'delivered'].includes(value)) return 'fa-check-circle';
    if (['pending', 'processing'].includes(value)) return 'fa-clock';
    if (['cancelled', 'cancelled'].includes(value)) return 'fa-times-circle';
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
        'completed': 'مكتمل',
        'cancelled': 'ملغي',
        'canceled': 'ملغي'
    };
    return mapping[value] || status;
};

// Timeline progress
const getTimelineProgressWidth = (status) => {
    const val = normalizeStatus(status);
    if (val === 'pending') return '0%';
    if (val === 'confirmed') return '33%';
    if (val === 'processing') return '66%';
    if (val === 'completed' || val === 'delivered') return '100%';
    return '0%';
};

const isStepCompleted = (currentStatus, step) => {
    const val = normalizeStatus(currentStatus);
    const steps = ['pending', 'confirmed', 'processing', 'completed'];
    const currentIndex = steps.indexOf(val);
    const stepIndex = steps.indexOf(step);
    return stepIndex <= currentIndex;
};

const filteredOrders = computed(() => {
    if (!searchQuery.value.trim()) return store.orders;
    const query = searchQuery.value.toLowerCase();
    return store.orders.filter((order) => {
        return [
            order.order_number,
            order.supplier?.name,
            order.status,
            order.due_date
        ].some((field) => String(field || '').toLowerCase().includes(query));
    });
});

const pendingCount = computed(() => store.orders.filter((order) => {
    const value = normalizeStatus(order.status);
    return ['pending', 'processing'].includes(value);
}).length);

const completedCount = computed(() => store.orders.filter((order) => {
    const value = normalizeStatus(order.status);
    return ['completed', 'complete', 'paid', 'delivered'].includes(value);
}).length);

// Drawer Actions
const openDetailDrawer = async (id) => {
    detailDrawerVisible.value = true;
    loadingDetail.value = true;
    try {
        const res = await purchaseOrdersApi.getById(id);
        selectedOrder.value = res.data.data;
    } catch (e) {
        ElMessage.error('خطأ أثناء تحميل تفاصيل الطلب.');
    } finally {
        loadingDetail.value = false;
    }
};

const openCreateDrawer = () => {
    isEditMode.value = false;
    resetForm();
    formDrawerVisible.value = true;
};

const openEditDrawer = async (id) => {
    isEditMode.value = true;
    editingOrderId.value = id;
    formDrawerVisible.value = true;
    resetForm();
    submittingForm.value = true;
    try {
        const res = await purchaseOrdersApi.getById(id);
        const order = res.data.data;
        form.supplier_id = order.supplier_id;
        form.status = order.status;
        form.order_date = order.order_date;
        form.due_date = order.due_date;
        form.discount = order.discount;
        form.tax = order.tax;
        form.notes = order.notes;
        form.items = order.items.map(item => ({
            product_id: item.product_id,
            quantity: item.quantity,
            unit_price: item.unit_price
        }));
    } catch (e) {
        ElMessage.error('خطأ أثناء تحميل بيانات الطلب للتعديل.');
        formDrawerVisible.value = false;
    } finally {
        submittingForm.value = false;
    }
};

// Form Dynamic items grid actions
const addItemRow = () => {
    form.items.push({ product_id: '', quantity: 1, unit_price: '' });
};

const removeItemRow = (idx) => {
    form.items.splice(idx, 1);
};

const updateItemPrice = (productId, idx) => {
    const prod = productsStore.products.find(p => p.id === productId);
    if (prod) {
        form.items[idx].unit_price = prod.price;
    }
};

const saveOrder = async () => {
    if (!form.supplier_id) {
        ElMessage.warning('يرجى تحديد المورد أولاً.');
        return;
    }
    if (form.items.some(item => !item.product_id || !item.quantity || !item.unit_price)) {
        ElMessage.warning('يرجى تعبئة كافة حقول الأصناف المضافة.');
        return;
    }
    
    submittingForm.value = true;
    try {
        if (isEditMode.value) {
            await purchaseOrdersApi.update(editingOrderId.value, form);
            ElMessage.success('تم تحديث أمر الشراء بنجاح.');
        } else {
            await purchaseOrdersApi.create(form);
            ElMessage.success('تم حفظ أمر الشراء بنجاح.');
        }
        formDrawerVisible.value = false;
        await store.fetchOrders();
    } catch (e) {
        ElMessage.error('خطأ أثناء حفظ أمر الشراء.');
    } finally {
        submittingForm.value = false;
    }
};

const deleteOrder = async (id) => {
    if (confirm('هل أنت متأكد من حذف أمر الشراء هذا؟')) {
        try {
            await purchaseOrdersApi.delete(id);
            ElMessage.success('تم حذف أمر الشراء بنجاح.');
            await store.fetchOrders();
        } catch (error) {
            ElMessage.error('خطأ أثناء حذف أمر الشراء.');
        }
    }
};

const receiveGoods = (id) => {
    router.push(`/admin/purchases/receipts?create_for_order=${id}`);
};

onMounted(() => {
    store.fetchOrders().catch(() => {});
    suppliersStore.fetchSuppliers().catch(() => {});
    productsStore.fetchProducts({ per_page: 100 }).catch(() => {});
});
</script>

<style scoped>
.purchases-page {
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

.supplier-cell {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
}

.amount-txt {
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
    left: 12%;
    right: 12%;
    height: 4px;
    background: var(--border-color);
    z-index: 1;
}

.progress-fill-bar {
    position: absolute;
    top: 20px;
    left: 12%;
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

.receive-card-box {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    padding: 1.25rem;
    border-radius: var(--radius-md);
    text-align: center;
}

.receive-tip {
    font-size: 0.85rem;
    color: #1e3a8a;
    margin: 0 0 1rem 0;
    line-height: 1.5;
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
