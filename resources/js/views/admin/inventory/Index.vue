<template>
    <div class="inventory-page inventory-index">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title">
                <h1><i class="fas fa-warehouse text-primary"></i> {{ $t('inventory') || 'لوحة إدارة المخزون' }}</h1>
                <p>مراقبة مستويات المخزون، تتبع النواقص، وتسجيل التسويات والحركات المخزنية المباشرة.</p>
            </div>
            <div class="header-actions">
                <el-button-group>
                    <el-button type="primary" @click="openAdjustmentDrawer"><i class="fas fa-sliders-h mr-1"></i> تسوية مخزنية</el-button>
                    <router-link to="/admin/inventory/movements">
                        <el-button type="info" plain><i class="fas fa-history mr-1"></i> سجل الحركات</el-button>
                    </router-link>
                </el-button-group>
            </div>
        </div>

        <!-- Metric Cards -->
        <el-row :gutter="16" class="overview-cards">
            <!-- Total Products -->
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="stat-card-wrapper">
                    <div class="stat-card-inner">
                        <div class="stat-icon-box blue-grad">
                            <i class="fas fa-boxes"></i>
                        </div>
                        <div class="stat-details">
                            <h3>{{ productsStore.products.length }}</h3>
                            <p>أصناف السلع المسجلة</p>
                        </div>
                    </div>
                </el-card>
            </el-col>
            <!-- In Stock Products -->
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="stat-card-wrapper">
                    <div class="stat-card-inner">
                        <div class="stat-icon-box green-grad">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-details">
                            <h3>{{ inStockCount }}</h3>
                            <p>أصناف متوفرة بالمخزن</p>
                        </div>
                    </div>
                </el-card>
            </el-col>
            <!-- Low Stock Warnings -->
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="stat-card-wrapper">
                    <div class="stat-card-inner">
                        <div class="stat-icon-box" :class="lowStockCount > 0 ? 'red-grad' : 'green-grad'">
                            <i class="fas" :class="lowStockCount > 0 ? 'fa-exclamation-triangle' : 'fa-check'"></i>
                        </div>
                        <div class="stat-details">
                            <h3>{{ lowStockCount }}</h3>
                            <p>أصناف منخفضة (نواقص)</p>
                        </div>
                    </div>
                </el-card>
            </el-col>
            <!-- Total Movements -->
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="stat-card-wrapper">
                    <div class="stat-card-inner">
                        <div class="stat-icon-box purple-grad">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                        <div class="stat-details">
                            <h3>{{ movementsStore.movements.length }}</h3>
                            <p>إجمالي الحركات الموثقة</p>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-row :gutter="20" class="mt-4">
            <!-- Left: Low Stock Watchlist -->
            <el-col :xs="24" :lg="12">
                <el-card shadow="hover" class="mb-4">
                    <template #header>
                        <div class="card-header">
                            <span class="text-danger"><i class="fas fa-exclamation-circle"></i> قائمة تحذير المخزن المنخفض (نواقص)</span>
                        </div>
                    </template>
                    <el-table :data="lowStockProducts" style="width: 100%" stripe size="small">
                        <el-table-column prop="name_ar" label="اسم المنتج" min-width="150" />
                        <el-table-column prop="sku" label="رمز SKU" width="100" />
                        <el-table-column prop="stock" label="الكمية الحالية" width="110" align="center">
                            <template #default="{ row }">
                                <span class="badge-stock-warning">{{ row.stock || 0 }} وحدة</span>
                            </template>
                        </el-table-column>
                    </el-table>
                    <div v-if="!lowStockProducts.length" style="padding: 2rem; text-align: center; color: var(--text-muted);">
                        <i class="fas fa-smile text-success" style="font-size: 2.5rem; display: block; margin-bottom: 0.75rem;"></i>
                        جميع مستويات المخزون آمنة وفي الحدود المطلوبة.
                    </div>
                </el-card>
            </el-col>

            <!-- Right: Recent Movements list -->
            <el-col :xs="24" :lg="12">
                <el-card shadow="hover" class="table-panel">
                    <template #header>
                        <div class="card-header">
                            <span><i class="fas fa-history text-muted"></i> آخر الحركات والعمليات المخزنية</span>
                        </div>
                    </template>
                    <el-table :data="recentMovements" style="width: 100%" stripe size="small">
                        <el-table-column prop="created_at" label="التاريخ" width="110">
                            <template #default="{ row }">
                                <span>{{ row.created_at ? row.created_at.split('T')[0] : '-' }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="product.name_ar" label="المنتج" min-width="130" />
                        <el-table-column prop="movement_type" label="النوع" width="100" align="center">
                            <template #default="{ row }">
                                <el-tag :type="typeTagType(row.movement_type)" size="small">
                                    {{ getArabicType(row.movement_type) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="quantity" label="الكمية" width="100" align="center">
                            <template #default="{ row }">
                                <strong>{{ row.quantity }}</strong>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-col>
        </el-row>

        <!-- Quick Adjustment Form Drawer -->
        <el-drawer
            v-model="adjustmentDrawerVisible"
            title="تسجيل حركة مخزنية وتسوية الكميات"
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
import { ref, onMounted, computed, reactive } from 'vue';
import { useProductsStore } from '@/stores/products';
import { useStockMovementsStore } from '@/stores/stockMovements';
import { stockMovementsApi } from '@/api/stockMovements';
import { ElMessage } from 'element-plus';

const productsStore = useProductsStore();
const movementsStore = useStockMovementsStore();

const loadingData = ref(false);

const inStockCount = computed(() => {
    return productsStore.products.filter(p => p.stock > 0).length;
});

const lowStockCount = computed(() => {
    return productsStore.products.filter(p => p.stock <= 10).length;
});

const lowStockProducts = computed(() => {
    return productsStore.products.filter(p => p.stock <= 10);
});

const recentMovements = computed(() => {
    return movementsStore.movements.slice(0, 5);
});

const typeTagType = (type) => {
    const val = String(type || '').toLowerCase();
    if (val === 'in') return 'success';
    if (val === 'out') return 'danger';
    return 'warning';
};

const getArabicType = (type) => {
    const val = String(type || '').toLowerCase();
    if (val === 'in') return 'وارد (In)';
    if (val === 'out') return 'صادر (Out)';
    return 'تسوية (Adj)';
};

// Form state for adjustment
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
        await productsStore.fetchProducts({ per_page: 200 });
        await movementsStore.fetchMovements({ per_page: 50 });
    } catch (e) {
        ElMessage.error('حدث خطأ أثناء حفظ الحركة المخزنية.');
    } finally {
        submittingForm.value = false;
    }
};

onMounted(async () => {
    loadingData.value = true;
    try {
        await Promise.all([
            productsStore.fetchProducts({ per_page: 200 }),
            movementsStore.fetchMovements({ per_page: 50 })
        ]);
    } catch (e) {
        console.error('Inventory overview failed to load stores data', e);
    } finally {
        loadingData.value = false;
    }
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

.overview-cards {
    margin-bottom: 1.5rem;
}

.stat-card-wrapper {
    border-radius: 1rem;
    transition: all 0.3s ease;
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

.red-grad {
    background: linear-gradient(135deg, var(--danger) 0%, var(--danger-dark) 100%);
}

.purple-grad {
    background: linear-gradient(135deg, #8b5cf6 0%, #a78bfa 100%);
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
    font-size: 0.85rem;
    font-weight: 500;
}

.badge-stock-warning {
    background: #fee2e2;
    border: 1px solid #fca5a5;
    color: #991b1b;
    padding: 0.25rem 0.6rem;
    border-radius: 20px;
    font-weight: 700;
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
</style>
