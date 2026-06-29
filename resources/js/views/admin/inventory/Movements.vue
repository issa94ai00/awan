<template>
    <div class="inventory-page inventory-movements">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title">
                <h1><i class="fas fa-history text-primary"></i> {{ $t('inventory_movements') || 'سجل الحركة المخزنية' }}</h1>
                <p>سجل تفصيلي لكافة الواردات والصادرات والتسويات اليدوية الحادثة على مخازن السلع والمستودعات.</p>
            </div>
            <div class="header-actions">
                <el-button type="primary" class="create-btn" @click="openAdjustmentDrawer">
                    <i class="fas fa-plus"></i> تسوية مخزنية جديدة
                </el-button>
            </div>
        </div>

        <!-- Filter Panel -->
        <el-card shadow="hover" class="filters-panel mb-4">
            <div class="filters-row">
                <div class="filter-item">
                    <label>المنتج / الصنف</label>
                    <el-select v-model="filters.product_id" placeholder="الكل" clearable style="width: 250px;" filterable @change="applyFilters">
                        <el-option 
                            v-for="p in productsStore.products" 
                            :key="p.id" 
                            :label="p.name_ar + ' (SKU: ' + p.sku + ')'" 
                            :value="p.id" 
                        />
                    </el-select>
                </div>
                <div class="filter-item">
                    <label>نوع الحركة</label>
                    <el-select v-model="filters.movement_type" placeholder="الكل" clearable style="width: 180px;" @change="applyFilters">
                        <el-option label="وارد (In)" value="in" />
                        <el-option label="صادر (Out)" value="out" />
                        <el-option label="تسوية (Adjustment)" value="adjustment" />
                    </el-select>
                </div>
                <el-button type="info" plain @click="resetFilters" style="margin-top: 1.25rem;">إعادة تعيين</el-button>
            </div>
        </el-card>

        <!-- Main Card & Movements Table -->
        <el-card shadow="hover" class="table-panel">
            <template #header>
                <div class="card-header">
                    <span><i class="fas fa-exchange-alt text-muted"></i> كشف المعاملات المخزنية</span>
                </div>
            </template>

            <div v-if="store.loading" class="loading-state">
                <el-skeleton :rows="6" animated />
            </div>
            <div v-else>
                <el-table 
                    v-if="store.movements.length" 
                    :data="store.movements" 
                    style="width: 100%" 
                    stripe 
                    highlight-current-row
                    class="custom-table"
                >
                    <el-table-column prop="created_at" label="التاريخ والوقت" width="180" align="center">
                        <template #default="{ row }">
                            <span>{{ row.created_at ? row.created_at.replace('T', ' ').substring(0, 19) : '-' }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="product.name_ar" label="الصنف / المنتج" min-width="180">
                        <template #default="{ row }">
                            <strong style="color: var(--text-dark);">{{ row.product?.name_ar || '-' }}</strong>
                            <p style="margin: 0.15rem 0 0 0; font-size: 0.8rem; color: var(--text-muted);">SKU: {{ row.product?.sku || '-' }}</p>
                        </template>
                    </el-table-column>
                    <el-table-column prop="movement_type" label="نوع الحركة" width="130" align="center">
                        <template #default="{ row }">
                            <el-tag :type="typeTagType(row.movement_type)" effect="light" class="status-tag">
                                <i class="fas status-dot-icon" :class="statusIconClass(row.movement_type)"></i>
                                {{ getArabicType(row.movement_type) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="quantity" label="الكمية" width="120" align="center">
                        <template #default="{ row }">
                            <strong :class="quantityColorClass(row.movement_type)" style="font-size: 1rem;">
                                {{ row.movement_type === 'in' ? '+' : '-' }}{{ row.quantity }}
                            </strong>
                        </template>
                    </el-table-column>
                    <el-table-column prop="reference" label="رمز المرجع" width="150" show-overflow-tooltip />
                    <el-table-column prop="creator.name" label="المسؤول" width="140" show-overflow-tooltip />
                    <el-table-column prop="notes" label="ملاحظات" min-width="180" show-overflow-tooltip />
                </el-table>

                <!-- Empty State -->
                <div v-if="!store.movements.length" class="empty-state-box">
                    <i class="fas fa-exchange-alt empty-icon"></i>
                    <p>لا توجد حركات مخزنية مطابقة لشروط البحث.</p>
                    <el-button type="primary" size="medium" @click="openAdjustmentDrawer">
                        <i class="fas fa-plus"></i> تسجيل حركة مخزنية
                    </el-button>
                </div>
            </div>
        </el-card>

        <!-- Quick Adjustment Form Drawer -->
        <el-drawer
            v-model="adjustmentDrawerVisible"
            title="تسجيل حركة مخزنية جديدة"
            size="40%"
            direction="rtl"
            destroy-on-close
            class="form-drawer"
        >
            <el-form :model="form" label-position="top">
                <el-form-item label="المنتج المراد تسويته" required>
                    <el-select v-model="form.product_id" placeholder="اختر المنتج" style="width: 100%" filterable>
                        <el-option 
                            v-for="p in productsStore.products" 
                            :key="p.id" 
                            :label="p.name_ar + ' (المخزون الحالي: ' + p.stock + ')'" 
                            :value="p.id" 
                        />
                    </el-select>
                </el-form-item>

                <el-form-item label="نوع الحركة المخزنية" required>
                    <el-select v-model="form.movement_type" style="width: 100%">
                        <el-option label="إدخال بضائع (In / توريد)" value="in" />
                        <el-option label="إخراج بضائع (Out / صرف)" value="out" />
                        <el-option label="تسوية يدوية (Adjustment)" value="adjustment" />
                    </el-select>
                </el-form-item>

                <el-form-item label="الكمية" required>
                    <el-input-number v-model="form.quantity" :min="1" style="width: 100%" />
                </el-form-item>

                <el-form-item label="رمز المرجع (رقم الإذن / الفاتورة)">
                    <el-input v-model="form.reference" placeholder="مثال: ADJ-2026-09" />
                </el-form-item>

                <el-form-item label="المصدر">
                    <el-input v-model="form.source" placeholder="مثال: مستودع جدة الرئيسي..." />
                </el-form-item>

                <el-form-item label="ملاحظات التسوية">
                    <el-input v-model="form.notes" type="textarea" :rows="3" placeholder="ملاحظات توضيحية حول سبب الحركة أو التسوية..." />
                </el-form-item>

                <div style="border-top: 1px solid var(--border-color); margin-top: 2rem; padding-top: 1.5rem; display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <el-button @click="adjustmentDrawerVisible = false">إلغاء</el-button>
                    <el-button type="primary" :loading="submittingForm" @click="saveAdjustment">تأكيد الحركة المخزنية</el-button>
                </div>
            </el-form>
        </el-drawer>
    </div>
</template>

<script setup>
import { ref, onMounted, reactive } from 'vue';
import { useStockMovementsStore } from '@/stores/stockMovements';
import { useProductsStore } from '@/stores/products';
import { stockMovementsApi } from '@/api/stockMovements';
import { ElMessage } from 'element-plus';

const store = useStockMovementsStore();
const productsStore = useProductsStore();

// Filters state
const filters = reactive({
    product_id: '',
    movement_type: ''
});

// Adjustment Drawer state
const adjustmentDrawerVisible = ref(false);
const submittingForm = ref(false);
const form = reactive({
    product_id: '',
    movement_type: 'adjustment',
    quantity: 1,
    reference: '',
    source: '',
    notes: ''
});

const resetForm = () => {
    form.product_id = '';
    form.movement_type = 'adjustment';
    form.quantity = 1;
    form.reference = '';
    form.source = '';
    form.notes = '';
};

const applyFilters = () => {
    const params = {};
    if (filters.product_id) params.product_id = filters.product_id;
    if (filters.movement_type) params.movement_type = filters.movement_type;
    store.fetchMovements(params).catch(() => {});
};

const resetFilters = () => {
    filters.product_id = '';
    filters.movement_type = '';
    store.fetchMovements().catch(() => {});
};

const openAdjustmentDrawer = () => {
    resetForm();
    adjustmentDrawerVisible.value = true;
};

const saveAdjustment = async () => {
    if (!form.product_id) {
        ElMessage.warning('يرجى اختيار صنف السلعة أولاً.');
        return;
    }
    if (!form.quantity) {
        ElMessage.warning('يرجى تحديد الكمية.');
        return;
    }

    submittingForm.value = true;
    try {
        await stockMovementsApi.create(form);
        ElMessage.success('تم تسجيل وإثبات الحركة المخزنية بنجاح.');
        adjustmentDrawerVisible.value = false;
        await store.fetchMovements();
        await productsStore.fetchProducts({ per_page: 200 });
    } catch (e) {
        ElMessage.error('حدث خطأ أثناء حفظ الحركة المخزنية.');
    } finally {
        submittingForm.value = false;
    }
};

const typeTagType = (type) => {
    const val = String(type || '').toLowerCase();
    if (val === 'in') return 'success';
    if (val === 'out') return 'danger';
    return 'warning';
};

const statusIconClass = (type) => {
    const val = String(type || '').toLowerCase();
    if (val === 'in') return 'fa-arrow-alt-circle-down';
    if (val === 'out') return 'fa-arrow-alt-circle-up';
    return 'fa-sliders-h';
};

const getArabicType = (type) => {
    const val = String(type || '').toLowerCase();
    if (val === 'in') return 'وارد (In)';
    if (val === 'out') return 'صادر (Out)';
    return 'تسوية (Adjustment)';
};

const quantityColorClass = (type) => {
    const val = String(type || '').toLowerCase();
    if (val === 'in') return 'text-success';
    if (val === 'out') return 'text-danger';
    return 'text-warning';
};

onMounted(() => {
    store.fetchMovements().catch(() => {});
    productsStore.fetchProducts({ per_page: 200 }).catch(() => {});
});
</script>

<style scoped>
.inventory-page {
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

.create-btn {
    font-weight: 600;
    border-radius: var(--radius-md);
    padding: 0.625rem 1.25rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.filters-panel {
    border-radius: var(--radius-md);
}

.filters-row {
    display: flex;
    flex-wrap: wrap;
    gap: 1.25rem;
    align-items: flex-end;
}

.filter-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-item label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-muted);
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
</style>
