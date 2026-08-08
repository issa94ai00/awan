<template>
    <div class="stock-page">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title">
                <h1><i class="fas fa-warehouse"></i> إدارة التعيينات المخزنية</h1>
                <p>ربط المنتجات بالمستودعات وتحديد حدود الطلب ومستويات الأمان لكل صنف.</p>
            </div>
            <div class="header-actions">
                <router-link to="/admin/inventory">
                    <el-button><i class="fas fa-chart-pie mr-1"></i> لوحة المخزون</el-button>
                </router-link>
                <el-button type="info" plain @click="refreshData" :loading="refreshing">
                    <i class="fas fa-sync-alt mr-1"></i> تحديث
                </el-button>
                <el-button type="primary" @click="openAssignModal">
                    <i class="fas fa-plus mr-1"></i> إضافة تعيين
                </el-button>
            </div>
        </div>

        <!-- Error Banner -->
        <el-alert v-if="error" type="error" :title="'خطأ في جلب البيانات'" :description="error" show-icon :closable="false" class="mb-4">
            <template #default>
                <el-button size="small" type="danger" plain @click="fetchData" class="mt-1">إعادة المحاولة</el-button>
            </template>
        </el-alert>

        <!-- Stats Cards -->
        <el-row :gutter="16" class="overview-cards">
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="never" class="stat-card-wrapper">
                    <div class="stat-card-inner">
                        <div class="stat-icon-box blue-grad"><i class="fas fa-link"></i></div>
                        <div class="stat-details">
                            <h3>{{ formatNumber(stats.totalAssignments) }}</h3>
                            <p>إجمالي التعيينات</p>
                        </div>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="never" class="stat-card-wrapper">
                    <div class="stat-card-inner">
                        <div class="stat-icon-box green-grad"><i class="fas fa-cubes"></i></div>
                        <div class="stat-details">
                            <h3>{{ formatNumber(stats.totalQuantity) }}</h3>
                            <p>إجمالي الكمية</p>
                        </div>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="never" class="stat-card-wrapper">
                    <div class="stat-card-inner">
                        <div class="stat-icon-box gold-grad"><i class="fas fa-coins"></i></div>
                        <div class="stat-details">
                            <h3>{{ formatPrice(stats.totalValue) }}</h3>
                            <p>إجمالي القيمة</p>
                        </div>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="never" class="stat-card-wrapper">
                    <div class="stat-card-inner">
                        <div class="stat-icon-box" :class="stats.lowStock > 0 ? 'red-grad' : 'green-grad'">
                            <i class="fas" :class="stats.lowStock > 0 ? 'fa-exclamation-triangle' : 'fa-check'"></i>
                        </div>
                        <div class="stat-details">
                            <h3>{{ formatNumber(stats.lowStock) }}</h3>
                            <p>مخزون منخفض</p>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- Filters -->
        <el-card shadow="never" class="panel">
            <div class="filters-row">
                <el-input v-model="searchQuery" placeholder="بحث بالمنتج أو المستودع..." clearable style="width: 260px;">
                    <template #prefix><i class="fas fa-search"></i></template>
                </el-input>
                <el-select v-model="selectedProductFilter" placeholder="جميع المنتجات" clearable filterable style="width: 220px;">
                    <el-option v-for="p in products" :key="p.id" :label="`${p.name_ar || p.name_en || p.name} (SKU: ${p.sku})`" :value="p.id" />
                </el-select>
                <el-select v-model="selectedWarehouseFilter" placeholder="جميع المستودعات" clearable style="width: 200px;">
                    <el-option v-for="w in warehouses" :key="w.id" :label="w.name" :value="w.id" />
                </el-select>
                <el-button type="info" plain @click="resetFilters">إعادة تعيين</el-button>
            </div>
        </el-card>

        <!-- Assignments Table -->
        <el-card shadow="never" class="panel mt-4">
            <template #header>
                <div class="card-header">
                    <span><i class="fas fa-table"></i> التعيينات الحالية</span>
                    <el-tag type="info" effect="plain">{{ filteredAssignments.length }} تعيين</el-tag>
                </div>
            </template>

            <div v-loading="loading">
                <el-table :data="filteredAssignments" stripe empty-text="لا توجد تعيينات مطابقة">
                    <el-table-column label="المنتج" min-width="200">
                        <template #default="{ row }">
                            <strong style="color: var(--text-dark);">{{ row.product?.name || '-' }}</strong>
                            <p class="table-sub">{{ row.product?.code || row.product?.sku || '' }}</p>
                        </template>
                    </el-table-column>
                    <el-table-column label="المستودع" min-width="150">
                        <template #default="{ row }">
                            {{ row.warehouse?.name || '-' }}
                            <p class="table-sub">{{ row.warehouse?.code || '' }}</p>
                        </template>
                    </el-table-column>
                    <el-table-column label="الكمية" width="110" align="center">
                        <template #default="{ row }">
                            <strong>{{ formatNumber(row.quantity) }}</strong>
                            <p class="table-sub">متاح: {{ formatNumber(row.available_quantity) }}</p>
                        </template>
                    </el-table-column>
                    <el-table-column label="القيمة" width="120" align="center">
                        <template #default="{ row }">{{ formatPrice(Number(row.quantity) * Number(row.cost_price || 0)) }}</template>
                    </el-table-column>
                    <el-table-column label="الحد الأدنى" width="100" align="center">
                        <template #default="{ row }">{{ formatNumber(row.min_stock_level) }}</template>
                    </el-table-column>
                    <el-table-column label="الحد الأقصى" width="100" align="center">
                        <template #default="{ row }">{{ formatNumber(row.max_stock_level) }}</template>
                    </el-table-column>
                    <el-table-column label="مخزون الأمان" width="110" align="center">
                        <template #default="{ row }">{{ formatNumber(row.safety_stock) }}</template>
                    </el-table-column>
                    <el-table-column label="الحالة" width="100" align="center">
                        <template #default="{ row }">
                            <el-tag :type="assignmentStatus(row).type" effect="light" size="small">{{ assignmentStatus(row).label }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="الإجراءات" width="160" align="center" fixed="right">
                        <template #default="{ row }">
                            <el-button size="small" text type="primary" @click="openEditModal(row)">
                                <i class="fas fa-edit"></i>
                            </el-button>
                            <el-button size="small" text type="danger" @click="deleteAssignment(row.id)">
                                <i class="fas fa-trash-alt"></i>
                            </el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <div class="pagination-wrapper" v-if="pagination.last_page > 1">
                    <el-pagination
                        v-model:current-page="pagination.current_page"
                        :page-size="pagination.per_page"
                        :total="pagination.total"
                        layout="total, prev, pager, next"
                        @current-change="handlePageChange"
                    />
                </div>
            </div>
        </el-card>

        <!-- Add/Edit Assignment Dialog -->
        <el-dialog
            v-model="dialogVisible"
            :title="form.id ? 'تعديل التعيين' : 'إضافة تعيين جديد'"
            width="720px"
            top="6vh"
            destroy-on-close
        >
            <el-form :model="form" label-position="top">
                <el-row :gutter="16">
                    <el-col :xs="24" :sm="12">
                        <el-form-item label="المنتج" required>
                            <el-select v-model="form.product_id" placeholder="اختر المنتج..." style="width: 100%" filterable :disabled="!!form.id">
                                <el-option
                                    v-for="p in products"
                                    :key="p.id"
                                    :label="`${p.name_ar || p.name_en || p.name} (SKU: ${p.sku})`"
                                    :value="p.id"
                                />
                            </el-select>
                            <p v-if="errors.product_id" class="field-error">{{ errors.product_id }}</p>
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item label="المستودع" required>
                            <el-select v-model="form.warehouse_id" placeholder="اختر المستودع..." style="width: 100%" filterable :disabled="!!form.id">
                                <el-option v-for="w in warehouses" :key="w.id" :label="w.name" :value="w.id" />
                            </el-select>
                            <p v-if="errors.warehouse_id" class="field-error">{{ errors.warehouse_id }}</p>
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item label="طريقة التعبئة">
                            <el-select v-model="form.replenishment_method" style="width: 100%">
                                <el-option label="شراء" value="purchase" />
                                <el-option label="تصنيع" value="manufacture" />
                                <el-option label="توزيع داخلي" value="internal_distribution" />
                                <el-option label="نقل مخزني" value="warehouse_transfer" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item label="طريقة التخطيط">
                            <el-select v-model="form.planning_method" style="width: 100%">
                                <el-option label="نقطة إعادة طلب" value="rop" />
                                <el-option label="تخطيط متطلبات المواد" value="mrp" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item label="الحد الأدنى">
                            <el-input-number v-model="form.min_stock_level" :min="0" style="width: 100%" />
                            <p v-if="errors.min_stock_level" class="field-error">{{ errors.min_stock_level }}</p>
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item label="الحد الأقصى">
                            <el-input-number v-model="form.max_stock_level" :min="0" style="width: 100%" />
                            <p v-if="errors.max_stock_level" class="field-error">{{ errors.max_stock_level }}</p>
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item label="مخزون الأمان">
                            <el-input-number v-model="form.safety_stock" :min="0" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item label="زمن التسليم (أيام)">
                            <el-input-number v-model="form.lead_time_days" :min="1" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12" v-if="form.id">
                        <el-form-item label="الكمية الفعلية">
                            <el-input-number v-model="form.quantity" :min="0" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12" v-if="form.id">
                        <el-form-item label="سعر التكلفة">
                            <el-input-number v-model="form.cost_price" :min="0" :step="0.01" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item label="استراتيجية التخزين">
                            <el-select v-model="form.putaway_strategy" style="width: 100%">
                                <el-option label="FIFO (أول ما يدخل أولاً)" value="fifo" />
                                <el-option label="FEFO (أول ما ينتهي أولاً)" value="fefo" />
                                <el-option label="التشابه" value="similarity" />
                                <el-option label="بالوزن" value="weight_based" />
                                <el-option label="بالحجم" value="volume_based" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item label="التواريخ">
                            <el-date-picker
                                v-model="effectiveRange"
                                type="daterange"
                                range-separator="إلى"
                                start-placeholder="تاريخ البدء"
                                end-placeholder="تاريخ الانتهاء"
                                value-format="YYYY-MM-DD"
                                style="width: 100%"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24">
                        <el-form-item label="إعدادات متقدمة">
                            <div class="check-row">
                                <el-switch v-model="form.auto_reorder_enabled" />
                                <span>تفعيل إعادة الطلب التلقائي</span>
                            </div>
                            <div class="check-row" v-if="form.id">
                                <el-switch v-model="form.is_active" />
                                <span>التعيين نشط</span>
                            </div>
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24">
                        <el-form-item label="ملاحظات">
                            <el-input v-model="form.notes" type="textarea" :rows="3" placeholder="ملاحظات حول التعيين..." />
                        </el-form-item>
                    </el-col>
                </el-row>
            </el-form>

            <template #footer>
                <el-button @click="dialogVisible = false">إلغاء</el-button>
                <el-button type="primary" :loading="submitting" @click="submitAssignment">
                    {{ form.id ? 'تحديث' : 'حفظ' }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import api from '@/api/index';
import { useProductsStore } from '@/stores/products';
import { useInventoryStore } from '@/stores/inventory';

const productsStore = useProductsStore();
const inventoryStore = useInventoryStore();

const loading = ref(false);
const assignments = ref([]);
const error = ref(null);
const refreshing = ref(false);
const searchQuery = ref('');
const selectedWarehouseFilter = ref(null);
const selectedProductFilter = ref(null);
const dialogVisible = ref(false);
const submitting = ref(false);
const errors = ref({});
const effectiveRange = ref([]);
const pagination = ref({
    current_page: 1,
    per_page: 15,
    total: 0,
    last_page: 1,
});

const defaultForm = () => ({
    id: null,
    product_id: null,
    warehouse_id: null,
    quantity: 0,
    min_stock_level: 0,
    max_stock_level: 0,
    safety_stock: 0,
    cost_price: 0,
    primary_bin_id: null,
    replenishment_method: 'purchase',
    planning_method: 'rop',
    lead_time_days: 1,
    supplier_id: null,
    putaway_strategy: 'fifo',
    auto_reorder_enabled: false,
    is_active: true,
    notes: '',
});

const form = reactive(defaultForm());

const products = computed(() => productsStore.products);
const warehouses = computed(() => inventoryStore.warehouses);

const stats = computed(() => ({
    totalAssignments: pagination.value.total,
    totalQuantity: assignments.value.reduce((sum, a) => sum + (Number(a.quantity) || 0), 0),
    totalValue: assignments.value.reduce((sum, a) => sum + ((Number(a.quantity) || 0) * (Number(a.cost_price) || 0)), 0),
    lowStock: assignments.value.filter((a) => Number(a.available_quantity) <= Number(a.min_stock_level)).length,
}));

const filteredAssignments = computed(() => assignments.value);

const assignmentStatus = (row) => {
    if (Number(row.available_quantity) <= Number(row.min_stock_level)) return { label: 'منخفض', type: 'danger' };
    return { label: 'متاح', type: 'success' };
};

const fetchData = async () => {
    loading.value = true;
    error.value = null;
    try {
        const params = {
            page: pagination.value.current_page,
            per_page: pagination.value.per_page,
        };
        if (searchQuery.value) params.search = searchQuery.value;
        if (selectedWarehouseFilter.value) params.warehouse_id = selectedWarehouseFilter.value;
        if (selectedProductFilter.value) params.product_id = selectedProductFilter.value;

        const response = await api.get('/admin/wms/assignments', { params });
        assignments.value = response.data?.data || [];
        pagination.value = {
            current_page: response.data?.current_page || 1,
            per_page: response.data?.per_page || 15,
            total: response.data?.total || 0,
            last_page: response.data?.last_page || 1,
        };
    } catch (err) {
        error.value = err.response?.data?.message || err.message || 'فشل في جلب البيانات';
        ElMessage.error(error.value);
    } finally {
        loading.value = false;
    }
};

const refreshData = async () => {
    refreshing.value = true;
    await fetchData();
    refreshing.value = false;
    ElMessage.success('تم تحديث البيانات بنجاح');
};

const resetFilters = () => {
    searchQuery.value = '';
    selectedWarehouseFilter.value = null;
    selectedProductFilter.value = null;
    pagination.value.current_page = 1;
    fetchData();
};

const handlePageChange = (page) => {
    pagination.value.current_page = page;
    fetchData();
};

const openAssignModal = () => {
    Object.assign(form, defaultForm());
    effectiveRange.value = [];
    errors.value = {};
    dialogVisible.value = true;
};

const openEditModal = (assignment) => {
    Object.assign(form, defaultForm(), assignment);
    effectiveRange.value = assignment.effective_date && assignment.expiry_date
        ? [assignment.effective_date, assignment.expiry_date]
        : (assignment.effective_date ? [assignment.effective_date, null] : []);
    errors.value = {};
    dialogVisible.value = true;
};

const validateForm = () => {
    errors.value = {};
    if (!form.product_id) errors.value.product_id = 'يرجى اختيار المنتج';
    if (!form.warehouse_id) errors.value.warehouse_id = 'يرجى اختيار المستودع';
    if (Number(form.max_stock_level) <= Number(form.min_stock_level)) errors.value.max_stock_level = 'الحد الأقصى يجب أن يكون أكبر من الحد الأدنى';
    return Object.keys(errors.value).length === 0;
};

const submitAssignment = async () => {
    if (!validateForm()) return;
    submitting.value = true;
    const payload = {
        product_id: form.product_id,
        warehouse_id: form.warehouse_id,
        replenishment_method: form.replenishment_method,
        planning_method: form.planning_method,
        min_stock_level: form.min_stock_level,
        max_stock_level: form.max_stock_level,
        safety_stock: form.safety_stock,
        lead_time_days: form.lead_time_days,
        primary_bin_id: form.primary_bin_id,
        putaway_strategy: form.putaway_strategy,
        auto_reorder_enabled: form.auto_reorder_enabled,
        effective_date: effectiveRange.value?.[0] || null,
        expiry_date: effectiveRange.value?.[1] || null,
        notes: form.notes,
    };
    try {
        if (form.id) {
            await api.put(`/admin/wms/assignments/${form.id}`, {
                ...payload,
                quantity: form.quantity ?? undefined,
                cost_price: form.cost_price ?? undefined,
                is_active: form.is_active,
            });
            ElMessage.success('تم تحديث التعيين بنجاح');
        } else {
            await api.post('/admin/wms/assignments', payload);
            ElMessage.success('تم إضافة التعيين بنجاح');
        }
        dialogVisible.value = false;
        await fetchData();
    } catch (err) {
        if (err.response?.data?.errors) errors.value = err.response.data.errors;
        ElMessage.error(err.response?.data?.message || err.message || 'فشل في الحفظ');
    } finally {
        submitting.value = false;
    }
};

const deleteAssignment = async (id) => {
    try {
        await ElMessageBox.confirm('هل أنت متأكد من حذف هذا التعيين؟', 'تأكيد الحذف', {
            confirmButtonText: 'نعم، احذف',
            cancelButtonText: 'إلغاء',
            type: 'warning',
        });
        await api.delete(`/admin/wms/assignments/${id}`);
        ElMessage.success('تم حذف التعيين بنجاح');
        await fetchData();
    } catch (err) {
        if (err !== 'cancel') ElMessage.error(err.response?.data?.message || 'فشل في حذف التعيين');
    }
};

const formatNumber = (num) => {
    if (num === null || num === undefined) return '0';
    return Number(num).toLocaleString('en-US');
};

const formatPrice = (price) => {
    if (price === null || price === undefined) return '0';
    return Number(price).toLocaleString('en-US', { style: 'currency', currency: 'SAR', maximumFractionDigits: 2 });
};

onMounted(async () => {
    await Promise.all([
        fetchData(),
        productsStore.fetchProducts({ per_page: 500 }).catch(() => {}),
        inventoryStore.fetchWarehouses().catch(() => {}),
    ]);
});
</script>

<style scoped>
.stock-page {
    font-family: 'Cairo', sans-serif;
}

.mr-1 {
    margin-inline-end: 0.35rem;
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

.page-title h1 i {
    color: var(--el-color-primary);
}

.page-title p {
    margin: 0.5rem 0 0;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.header-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
}

.overview-cards {
    margin-bottom: 1rem;
}

.stat-card-wrapper {
    border-radius: 1rem;
    border: 1px solid var(--border-color);
    margin-bottom: 1rem;
    transition: box-shadow 0.25s ease, transform 0.25s ease;
}

.stat-card-wrapper:hover {
    transform: translateY(-3px);
    box-shadow: var(--card-shadow-hover, 0 10px 25px rgba(0, 0, 0, 0.08));
}

.stat-card-inner {
    display: flex;
    align-items: center;
    gap: 1.1rem;
}

.stat-icon-box {
    width: 52px;
    height: 52px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 1.35rem;
    flex-shrink: 0;
}

.blue-grad { background: linear-gradient(135deg, var(--accent-blue, #005a9c) 0%, #3b82f6 100%); }
.green-grad { background: linear-gradient(135deg, var(--success, #10b981) 0%, #059669 100%); }
.red-grad { background: linear-gradient(135deg, var(--danger, #ef4444) 0%, #dc2626 100%); }
.gold-grad { background: linear-gradient(135deg, var(--accent-gold, #c9a959) 0%, #d4af37 100%); }

.stat-details h3 {
    margin: 0;
    font-size: 1.55rem;
    font-weight: 700;
    color: var(--text-dark);
    line-height: 1.2;
}

.stat-details p {
    margin: 0.25rem 0 0;
    color: var(--text-muted);
    font-size: 0.82rem;
    font-weight: 500;
}

.panel {
    border-radius: 1rem;
    border: 1px solid var(--border-color);
    margin-bottom: 1rem;
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    font-weight: 700;
    color: var(--text-dark);
}

.card-header i {
    color: var(--el-color-primary);
}

.filters-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    align-items: center;
}

.table-sub {
    margin: 0.1rem 0 0;
    font-size: 0.78rem;
    color: var(--text-muted);
    font-weight: 400;
}

.field-error {
    margin: 0.25rem 0 0;
    color: var(--danger, #dc2626);
    font-size: 0.8rem;
}

.check-row {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    color: var(--text-dark);
    font-size: 0.9rem;
    margin-bottom: 0.6rem;
}

.mb-4 {
    margin-bottom: 1rem;
}

.mt-4 {
    margin-top: 1rem;
}

.pagination-wrapper {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 1.5rem 0;
    margin-top: 1rem;
}
</style>
