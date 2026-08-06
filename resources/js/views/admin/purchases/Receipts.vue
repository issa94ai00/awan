<template>
    <div class="purchases-page purchases-receipts">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title">
                <h1><i class="fas fa-truck-loading text-primary"></i> {{ $t('receipts') || 'إيصالات الاستلام' }}</h1>
                <p>{{ $t('clearly_view_receipts_with_live') || 'إدارة وتوثيق البضائع الواردة إلى المخازن وتتبع تاريخ الاستلام والملاحظات.' }}</p>
            </div>
            <div class="header-actions">
                <el-input 
                    v-model="searchQuery" 
                    :placeholder="$t('search_by_receipt_number_or') || 'ابحث برقم الإيصال أو المورد...'" 
                    clearable 
                    class="search-input"
                    :prefix-icon="Search"
                />
                <el-button type="primary" @click="openCreateDrawer" class="create-btn">
                    <i class="fas fa-plus-circle"></i> إيصال جديد
                </el-button>
            </div>
        </div>

        <!-- Metrics cards row -->
        <el-row :gutter="16" class="overview-cards">
            <el-col :xs="24" :sm="12" :md="12">
                <el-card shadow="hover" class="stat-card-wrapper">
                    <div class="stat-card-inner">
                        <div class="stat-icon-box blue-grad">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <div class="stat-details">
                            <h3>{{ store.receipts.length }}</h3>
                            <p>{{ $t('total_receipts') || 'إجمالي إيصالات الاستلام' }}</p>
                        </div>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="12">
                <el-card shadow="hover" class="stat-card-wrapper">
                    <div class="stat-card-inner">
                        <div class="stat-icon-box green-grad">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-details">
                            <h3>{{ recentCount }}</h3>
                            <p>{{ $t('latest_receipts') || 'الإيصالات الحديثة' }}</p>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- Table Panel -->
        <el-card shadow="hover" class="table-panel">
            <template #header>
                <div class="card-header">
                    <span><i class="fas fa-list text-muted"></i> {{ $t('list_of_receipts') || 'جدول إيصالات الاستلام' }}</span>
                </div>
            </template>

            <div v-if="store.loading" class="loading-state">
                <el-skeleton :rows="6" animated />
            </div>
            <div v-else>
                <el-table 
                    v-if="filteredReceipts.length" 
                    :data="filteredReceipts" 
                    style="width: 100%" 
                    stripe 
                    highlight-current-row
                    class="custom-table"
                >
                    <el-table-column prop="receipt_number" label="رقم الإيصال" width="140">
                        <template #default="{ row }">
                            <span class="receipt-link-txt" @click="openDetailDrawer(row.id)">{{ row.receipt_number }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="supplier.name" :label="$t('supplier') || 'المورد'">
                        <template #default="{ row }">
                            <div class="supplier-cell">
                                <i class="fas fa-truck text-muted"></i>
                                <span>{{ row.supplier?.name || '-' }}</span>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column prop="receipt_date" :label="$t('the_date') || 'التاريخ'" width="180" align="center" />
                    <el-table-column prop="notes" :label="$t('comments') || 'ملاحظات'" min-width="200" show-overflow-tooltip />
                    
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
                                <el-button size="small" type="danger" plain @click="deleteReceipt(row.id)" title="حذف">
                                    <i class="fas fa-trash"></i>
                                </el-button>
                            </el-button-group>
                        </template>
                    </el-table-column>
                </el-table>

                <!-- Empty State -->
                <div v-if="!filteredReceipts.length" class="empty-state-box">
                    <i class="fas fa-receipt empty-icon"></i>
                    <p>{{ $t('there_are_no_receipts_matching') || 'لا توجد إيصالات استلام مطابقة لشروط البحث.' }}</p>
                    <el-button type="primary" size="medium" @click="openCreateDrawer">
                        <i class="fas fa-plus-circle"></i> إنشاء إيصال جديد
                    </el-button>
                </div>
            </div>
        </el-card>

        <!-- Detail Drawer -->
        <el-drawer
            v-model="detailDrawerVisible"
            title="تفاصيل إيصال استلام البضائع"
            size="55%"
            direction="rtl"
            destroy-on-close
            class="detail-drawer"
        >
            <div v-if="loadingDetail" v-loading="loadingDetail" style="min-height: 250px;"></div>
            <div v-else-if="selectedReceipt" class="drawer-detail-content">
                <!-- Timeline status step -->
                <div class="timeline-step-tracker mb-4">
                    <div class="visual-progress-timeline">
                        <div class="progress-base-bar"></div>
                        <div class="progress-fill-bar" style="width: 100%;"></div>
                        
                        <div class="timeline-nodes-wrapper">
                            <div class="timeline-node completed">
                                <div class="node-icon"><i class="fas fa-file-signature"></i></div>
                                <span>أمر الشراء المرتبط</span>
                            </div>
                            <div class="timeline-node completed">
                                <div class="node-icon"><i class="fas fa-truck-loading"></i></div>
                                <span>توثيق الاستلام</span>
                            </div>
                            <div class="timeline-node completed">
                                <div class="node-icon"><i class="fas fa-boxes"></i></div>
                                <span>تحديث المستودع</span>
                            </div>
                        </div>
                    </div>
                </div>

                <el-row :gutter="20">
                    <!-- Left: items table & totals -->
                    <el-col :xs="24" :lg="16">
                        <el-card shadow="never" class="mb-4">
                            <template #header>
                                <span class="card-title-txt"><i class="fas fa-list text-muted mr-1"></i> الأصناف والكميات المستلمة</span>
                            </template>
                            <el-table :data="selectedReceipt.items || []" style="width: 100%" stripe>
                                <el-table-column prop="product.name_ar" label="الصنف / المنتج" />
                                <el-table-column prop="quantity" label="الكمية المستلمة" width="140" align="center" />
                                <el-table-column prop="unit_price" label="سعر الشراء" width="130">
                                    <template #default="{ row }">${{ parseFloat(row.unit_price || 0).toFixed(2) }}</template>
                                </el-table-column>
                                <el-table-column label="الإجمالي" width="130">
                                    <template #default="{ row }">${{ (row.quantity * row.unit_price).toFixed(2) }}</template>
                                </el-table-column>
                            </el-table>

                            <div class="financial-summary-block mt-4">
                                <div class="financial-row grand-total">
                                    <span>الإجمالي الكلي:</span>
                                    <span>${{ parseFloat(selectedReceipt.total || 0).toFixed(2) }}</span>
                                </div>
                            </div>
                        </el-card>

                        <!-- Stock Alert Log -->
                        <div class="alert-success-box mb-4">
                            <i class="fas fa-check-circle text-success"></i>
                            <div>
                                <h4>تم إدخال الكميات للمخزن بنجاح</h4>
                                <ul>
                                    <li v-for="item in selectedReceipt.items" :key="item.id">
                                        {{ item.product?.name_ar }}: <strong>+{{ item.quantity }} وحدة</strong>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </el-col>

                    <!-- Right: supplier & reference orders info -->
                    <el-col :xs="24" :lg="8">
                        <el-card shadow="never" class="mb-3">
                            <template #header>
                                <span class="card-title-txt"><i class="fas fa-user-tie text-muted mr-1"></i> بيانات المورد</span>
                            </template>
                            <div class="info-list">
                                <div class="info-item">
                                    <span class="lbl">اسم المورد:</span>
                                    <strong>{{ selectedReceipt.supplier?.name || '-' }}</strong>
                                </div>
                                <div class="info-item" v-if="selectedReceipt.supplier?.company">
                                    <span class="lbl">الشركة:</span>
                                    <strong>{{ selectedReceipt.supplier.company }}</strong>
                                </div>
                                <div class="info-item" v-if="selectedReceipt.supplier?.phone">
                                    <span class="lbl">الهاتف:</span>
                                    <strong>{{ selectedReceipt.supplier.phone }}</strong>
                                </div>
                            </div>
                        </el-card>

                        <el-card shadow="never" class="mb-3">
                            <template #header>
                                <span class="card-title-txt"><i class="fas fa-info-circle text-muted mr-1"></i> تفاصيل الشحنة</span>
                            </template>
                            <div class="info-list">
                                <div class="info-item">
                                    <span class="lbl">تاريخ الاستلام:</span>
                                    <strong>{{ selectedReceipt.receipt_date || '-' }}</strong>
                                </div>
                                <div class="info-item">
                                    <span class="lbl">مرتبط بأمر شراء:</span>
                                    <strong v-if="selectedReceipt.purchase_order" style="color: var(--accent-blue);">
                                        #{{ selectedReceipt.purchase_order.order_number }}
                                    </strong>
                                    <strong v-else>استلام مباشر</strong>
                                </div>
                            </div>
                        </el-card>

                        <el-card v-if="selectedReceipt.notes" shadow="never">
                            <template #header>
                                <span class="card-title-txt"><i class="fas fa-sticky-note text-muted mr-1"></i> ملاحظات</span>
                            </template>
                            <p class="notes-txt-view">{{ selectedReceipt.notes }}</p>
                        </el-card>
                    </el-col>
                </el-row>
            </div>
        </el-drawer>

        <!-- Form Drawer (Create / Edit) -->
        <el-drawer
            v-model="formDrawerVisible"
            :title="isEditMode ? 'تعديل إيصال استلام' : 'إنشاء إيصال استلام جديد'"
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
                    <el-col :span="12">
                        <el-form-item label="أمر الشراء المرتبط">
                            <el-select v-model="form.purchase_order_id" placeholder="اختر أمر الشراء (شراء مباشر إذا كان خالياً)" style="width: 100%" filterable clearable>
                                <el-option 
                                    v-for="o in purchaseOrdersStore.orders" 
                                    :key="o.id" 
                                    :label="o.order_number" 
                                    :value="o.id" 
                                />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20" class="mt-3">
                    <el-col :span="24">
                        <el-form-item label="تاريخ الاستلام الفعلي">
                            <el-date-picker v-model="form.receipt_date" type="date" placeholder="تاريخ الاستلام" format="YYYY-MM-DD" value-format="YYYY-MM-DD" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-form-item label="ملاحظات الاستلام" class="mt-3">
                    <el-input v-model="form.notes" type="textarea" :rows="3" placeholder="أدخل أي ملاحظات حول الشحنة أو حالة الاستلام..." />
                </el-form-item>

                <!-- Dynamic items grid -->
                <div style="border-top: 1px solid var(--border-color); margin-top: 2rem; padding-top: 1.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                        <h3 style="margin: 0; font-size: 1.1rem; font-weight: 700;"><i class="fas fa-boxes text-primary"></i> الأصناف والكميات الواردة</h3>
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
                                    :label="p.name_ar + ' (SKU: ' + p.sku + ')'" 
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
                    <el-button type="primary" :loading="submittingForm" @click="saveReceipt">حفظ الإيصال</el-button>
                </div>
            </el-form>
        </el-drawer>
    </div>
</template>

<script setup>
import { ref, onMounted, computed, reactive } from 'vue';
import { usePurchaseReceiptsStore } from '@/stores/purchaseReceipts';
import { useSuppliersStore } from '@/stores/suppliers';
import { usePurchaseOrdersStore } from '@/stores/purchaseOrders';
import { useProductsStore } from '@/stores/products';
import { purchaseReceiptsApi } from '@/api/purchaseReceipts';
import { Search } from '@element-plus/icons-vue';
import { ElMessage } from 'element-plus';

const store = usePurchaseReceiptsStore();
const suppliersStore = useSuppliersStore();
const purchaseOrdersStore = usePurchaseOrdersStore();
const productsStore = useProductsStore();

const searchQuery = ref('');

// Drawers and actions state
const detailDrawerVisible = ref(false);
const loadingDetail = ref(false);
const selectedReceipt = ref(null);

const formDrawerVisible = ref(false);
const isEditMode = ref(false);
const submittingForm = ref(false);
const editingReceiptId = ref(null);

const form = reactive({
    supplier_id: '',
    purchase_order_id: '',
    receipt_date: '',
    notes: '',
    items: []
});

const resetForm = () => {
    form.supplier_id = '';
    form.purchase_order_id = '';
    form.receipt_date = new Date().toISOString().split('T')[0];
    form.notes = '';
    form.items = [{ product_id: '', quantity: 1, unit_price: '' }];
};

const filteredReceipts = computed(() => {
    if (!searchQuery.value.trim()) return store.receipts;
    const query = searchQuery.value.toLowerCase();
    return store.receipts.filter((receipt) => {
        return [
            receipt.receipt_number,
            receipt.supplier?.name,
            receipt.receipt_date,
            receipt.notes
        ].some((field) => String(field || '').toLowerCase().includes(query));
    });
});

const recentCount = computed(() => Math.min(store.receipts.length, 5));

// Drawer Actions
const openDetailDrawer = async (id) => {
    detailDrawerVisible.value = true;
    loadingDetail.value = true;
    try {
        const res = await purchaseReceiptsApi.getById(id);
        selectedReceipt.value = res.data.data;
    } catch (e) {
        ElMessage.error('خطأ أثناء تحميل تفاصيل الإيصال.');
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
    editingReceiptId.value = id;
    formDrawerVisible.value = true;
    resetForm();
    submittingForm.value = true;
    try {
        const res = await purchaseReceiptsApi.getById(id);
        const receipt = res.data.data;
        form.supplier_id = receipt.supplier_id;
        form.purchase_order_id = receipt.purchase_order_id;
        form.receipt_date = receipt.receipt_date;
        form.notes = receipt.notes;
        form.items = receipt.items.map(item => ({
            product_id: item.product_id,
            quantity: item.quantity,
            unit_price: item.unit_price
        }));
    } catch (e) {
        ElMessage.error('خطأ أثناء تحميل بيانات الإيصال للتعديل.');
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

const saveReceipt = async () => {
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
            await purchaseReceiptsApi.update(editingReceiptId.value, form);
            ElMessage.success('تم تحديث إيصال الاستلام بنجاح.');
        } else {
            await purchaseReceiptsApi.create(form);
            ElMessage.success('تم حفظ إيصال الاستلام وتعديل المخازن بنجاح.');
        }
        formDrawerVisible.value = false;
        await store.fetchReceipts();
    } catch (e) {
        ElMessage.error('خطأ أثناء حفظ إيصال الاستلام.');
    } finally {
        submittingForm.value = false;
    }
};

const deleteReceipt = async (id) => {
    if (confirm('هل أنت متأكد من حذف إيصال الاستلام هذا وتعديل كميات المخزن؟')) {
        try {
            await purchaseReceiptsApi.delete(id);
            ElMessage.success('تم حذف إيصال الاستلام بنجاح.');
            await store.fetchReceipts();
        } catch (error) {
            ElMessage.error('خطأ أثناء حذف إيصال الاستلام.');
        }
    }
};

onMounted(async () => {
    store.fetchReceipts().catch(() => {});
    suppliersStore.fetchSuppliers().catch(() => {});
    purchaseOrdersStore.fetchOrders().catch(() => {});
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

.receipt-link-txt {
    color: var(--accent-blue);
    font-weight: 700;
    cursor: pointer;
}

.receipt-link-txt:hover {
    text-decoration: underline;
}

.supplier-cell {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
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
    left: 15%;
    right: 15%;
    height: 4px;
    background: var(--border-color);
    z-index: 1;
}

.progress-fill-bar {
    position: absolute;
    top: 20px;
    left: 15%;
    right: 15%;
    height: 4px;
    background: var(--success);
    z-index: 2;
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
    background: var(--success);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    box-shadow: var(--shadow-sm);
}

.timeline-node span {
    font-size: 0.85rem;
    font-weight: 600;
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

.alert-success-box {
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    padding: 1.25rem 1.5rem;
    border-radius: var(--radius-md);
    display: flex;
    gap: 1rem;
    align-items: flex-start;
    color: #065f46;
}

.alert-success-box i {
    font-size: 1.4rem;
    margin-top: 0.15rem;
}

.alert-success-box h4 {
    margin: 0 0 0.5rem 0;
    font-weight: 700;
}

.alert-success-box ul {
    margin: 0;
    padding-right: 1.25rem;
    font-size: 0.9rem;
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
