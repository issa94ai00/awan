<template>
    <div class="product-units-page">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title">
                <h1>
                    <el-icon><ScaleToOriginal /></el-icon>
                    {{ t('product_units_management') }}
                </h1>
                <p>{{ t('manage_product_units_barcodes') }}</p>
            </div>
        </div>

        <!-- Product Selection -->
        <el-card shadow="hover" class="selection-card">
            <template #header>
                <div class="card-header">
                    <el-icon><Box /></el-icon>
                    <span>{{ t('select_product') }}</span>
                </div>
            </template>

            <div class="product-selector">
                <el-select
                    v-model="selectedProductId"
                    filterable
                    remote
                    :remote-method="searchProducts"
                    :loading="productSearchLoading"
                    :placeholder="t('search_for_product')"
                    size="large"
                    class="product-select"
                    @change="loadProductUnits"
                >
                    <el-option
                        v-for="product in products"
                        :key="product.id"
                        :label="`${product.name_ar || product.name_en} - ${product.sku || ''}`"
                        :value="product.id"
                    />
                </el-select>
            </div>
        </el-card>

        <!-- Units Management -->
        <el-card v-if="selectedProduct" shadow="hover" class="units-card">
            <template #header>
                <div class="card-header">
                    <div class="header-left">
                        <el-icon><ScaleToOriginal /></el-icon>
                        <span>{{ t('units_for') }} {{ selectedProduct.name_ar || selectedProduct.name_en }}</span>
                    </div>
                    <el-button type="primary" @click="openAddUnitDialog" :icon="Plus">
                        {{ t('add_unit') }}
                    </el-button>
                </div>
            </template>

            <!-- Units Table -->
            <el-table :data="units" v-loading="unitsLoading" stripe highlight-current-row>
                <el-table-column prop="name" :label="t('unit_name')" min-width="120">
                    <template #default="{ row }">
                        <div class="unit-name">
                            <strong>{{ row.name }}</strong>
                            <span v-if="row.name_ar" class="unit-name-ar">{{ row.name_ar }}</span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column prop="barcode" :label="t('barcode')" min-width="150">
                    <template #default="{ row }">
                        <span v-if="row.barcode" class="barcode-text">
                            <el-icon><Ticket /></el-icon> {{ row.barcode }}
                        </span>
                        <span v-else class="no-barcode">--</span>
                    </template>
                </el-table-column>

                <el-table-column prop="base_unit_multiplier" :label="t('conversion_factor')" width="150">
                    <template #default="{ row }">
                        <el-tag type="info" round>
                            {{ row.base_unit_multiplier }} {{ t('base_unit') }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column prop="price_multiplier" :label="t('price_multiplier')" width="130">
                    <template #default="{ row }">
                        x{{ row.price_multiplier }}
                    </template>
                </el-table-column>

                <el-table-column :label="t('status')" width="100">
                    <template #default="{ row }">
                        <el-tag v-if="row.is_default" type="success" round>
                            {{ t('base_unit') }}
                        </el-tag>
                        <el-tag v-else type="info" round>
                            {{ t('additional') }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column :label="t('actions')" width="150" fixed="right">
                    <template #default="{ row }">
                        <el-button-group>
                            <el-button
                                type="primary"
                                :icon="Edit"
                                size="small"
                                circle
                                @click="openEditUnitDialog(row)"
                            />
                            <el-button
                                v-if="!row.is_default"
                                type="danger"
                                :icon="Delete"
                                size="small"
                                circle
                                @click="confirmDeleteUnit(row)"
                            />
                        </el-button-group>
                    </template>
                </el-table-column>
            </el-table>

            <!-- Empty State -->
            <div v-if="!units.length && !unitsLoading" class="empty-state">
                <el-icon :size="48"><ScaleToOriginal /></el-icon>
                <p>{{ t('no_units_for_product') }}</p>
                <el-button type="primary" @click="openAddUnitDialog" :icon="Plus">
                    {{ t('add_first_unit') }}
                </el-button>
            </div>
        </el-card>

        <!-- Add/Edit Unit Dialog -->
        <el-dialog
            v-model="unitDialogVisible"
            :title="isEditMode ? t('edit_unit') : t('add_unit')"
            width="500px"
            :close-on-click-modal="false"
        >
            <el-form
                ref="unitFormRef"
                :model="unitForm"
                :rules="unitRules"
                label-position="top"
            >
                <el-form-item :label="t('unit_name')" prop="name">
                    <el-input v-model="unitForm.name" :placeholder="t('enter_unit_name')" />
                </el-form-item>

                <el-form-item :label="t('unit_name_arabic')" prop="name_ar">
                    <el-input v-model="unitForm.name_ar" :placeholder="t('enter_unit_name_arabic')" />
                </el-form-item>

                <el-form-item :label="t('barcode')" prop="barcode">
                    <el-input v-model="unitForm.barcode" :placeholder="t('enter_barcode')">
                        <template #prefix>
                            <el-icon><Ticket /></el-icon>
                        </template>
                    </el-input>
                </el-form-item>

                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="t('conversion_factor')" prop="base_unit_multiplier">
                            <el-input-number
                                v-model="unitForm.base_unit_multiplier"
                                :min="0.01"
                                :precision="2"
                                style="width: 100%"
                            />
                            <div class="form-hint">{{ t('how_many_base_units') }}</div>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t('price_multiplier')" prop="price_multiplier">
                            <el-input-number
                                v-model="unitForm.price_multiplier"
                                :min="0.01"
                                :precision="2"
                                style="width: 100%"
                            />
                            <div class="form-hint">{{ t('price_multiplier_hint') }}</div>
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-form-item>
                    <el-checkbox v-model="unitForm.is_default">{{ t('set_as_default_unit') }}</el-checkbox>
                </el-form-item>
            </el-form>

            <template #footer>
                <el-button @click="unitDialogVisible = false">{{ t('cancel') }}</el-button>
                <el-button type="primary" @click="saveUnit" :loading="savingUnit">
                    {{ isEditMode ? t('update') : t('save') }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { productsApi } from '@/api/products';
import { posApi } from '@/api/pos';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
    ScaleToOriginal, Box, Plus, Edit, Delete, Ticket
} from '@element-plus/icons-vue';

const { t } = useI18n();

// Product search
const products = ref([]);
const selectedProductId = ref(null);
const selectedProduct = ref(null);
const productSearchLoading = ref(false);

// Units
const units = ref([]);
const unitsLoading = ref(false);

// Dialog
const unitDialogVisible = ref(false);
const isEditMode = ref(false);
const editingUnitId = ref(null);
const unitFormRef = ref(null);
const savingUnit = ref(false);

const unitForm = reactive({
    name: '',
    name_ar: '',
    barcode: '',
    base_unit_multiplier: 1,
    price_multiplier: 1,
    is_default: false,
});

const unitRules = {
    name: [{ required: true, message: t('unit_name_required'), trigger: 'blur' }],
    base_unit_multiplier: [
        { required: true, message: t('conversion_factor_required'), trigger: 'blur' },
        { type: 'number', min: 0.01, message: t('conversion_factor_min'), trigger: 'blur' }
    ],
    price_multiplier: [
        { required: true, message: t('price_multiplier_required'), trigger: 'blur' },
        { type: 'number', min: 0.01, message: t('price_multiplier_min'), trigger: 'blur' }
    ],
};

// API base URL
const apiBase = '/api/v1';

// Search products
const searchProducts = async (query) => {
    if (!query || query.length < 2) return;

    productSearchLoading.value = true;
    try {
        const res = await posApi.productLookup({ q: query });
        const data = res.data?.data || res.data || [];
        products.value = Array.isArray(data) ? data : [];
    } catch (error) {
        console.error('Search error:', error);
    } finally {
        productSearchLoading.value = false;
    }
};

// Load product units
const loadProductUnits = async (productId) => {
    if (!productId) {
        selectedProduct.value = null;
        units.value = [];
        return;
    }

    // Find product details
    selectedProduct.value = products.value.find(p => p.id === productId);

    unitsLoading.value = true;
    try {
        const token = localStorage.getItem('token');
        const res = await fetch(`${apiBase}/admin/products/${productId}/units`, {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json',
            }
        });
        const data = await res.json();

        if (data.success) {
            units.value = data.data;
        }
    } catch (error) {
        console.error('Failed to load units:', error);
        ElMessage.error(t('failed_to_load_units'));
    } finally {
        unitsLoading.value = false;
    }
};

// Reset form
const resetForm = () => {
    unitForm.name = '';
    unitForm.name_ar = '';
    unitForm.barcode = '';
    unitForm.base_unit_multiplier = 1;
    unitForm.price_multiplier = 1;
    unitForm.is_default = false;
    editingUnitId.value = null;
};

// Open add dialog
const openAddUnitDialog = () => {
    resetForm();
    isEditMode.value = false;
    unitDialogVisible.value = true;
};

// Open edit dialog
const openEditUnitDialog = (unit) => {
    resetForm();
    isEditMode.value = true;
    editingUnitId.value = unit.id;
    unitForm.name = unit.name;
    unitForm.name_ar = unit.name_ar || '';
    unitForm.barcode = unit.barcode || '';
    unitForm.base_unit_multiplier = parseFloat(unit.base_unit_multiplier);
    unitForm.price_multiplier = parseFloat(unit.price_multiplier);
    unitForm.is_default = unit.is_default;
    unitDialogVisible.value = true;
};

// Save unit
const saveUnit = async () => {
    if (!unitFormRef.value) return;

    try {
        await unitFormRef.value.validate();
    } catch {
        return;
    }

    savingUnit.value = true;
    try {
        const token = localStorage.getItem('token');
        const url = isEditMode.value
            ? `${apiBase}/admin/products/${selectedProductId.value}/units/${editingUnitId.value}`
            : `${apiBase}/admin/products/${selectedProductId.value}/units`;

        const method = isEditMode.value ? 'PUT' : 'POST';

        const res = await fetch(url, {
            method,
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify(unitForm),
        });

        const data = await res.json();

        if (data.success) {
            ElMessage.success(data.message);
            unitDialogVisible.value = false;
            loadProductUnits(selectedProductId.value);
        } else {
            ElMessage.error(data.message || t('failed_to_save_unit'));
        }
    } catch (error) {
        console.error('Save error:', error);
        ElMessage.error(t('failed_to_save_unit'));
    } finally {
        savingUnit.value = false;
    }
};

// Confirm delete
const confirmDeleteUnit = (unit) => {
    ElMessageBox.confirm(
        t('confirm_delete_unit', { name: unit.name }),
        t('confirm'),
        {
            confirmButtonText: t('delete'),
            cancelButtonText: t('cancel'),
            type: 'warning',
        }
    ).then(async () => {
        await deleteUnit(unit);
    }).catch(() => {});
};

// Delete unit
const deleteUnit = async (unit) => {
    try {
        const token = localStorage.getItem('token');
        const res = await fetch(`${apiBase}/admin/products/${selectedProductId.value}/units/${unit.id}`, {
            method: 'DELETE',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json',
            }
        });

        const data = await res.json();

        if (data.success) {
            ElMessage.success(data.message);
            loadProductUnits(selectedProductId.value);
        } else {
            ElMessage.error(data.message || t('failed_to_delete_unit'));
        }
    } catch (error) {
        console.error('Delete error:', error);
        ElMessage.error(t('failed_to_delete_unit'));
    }
};

// Load initial products
onMounted(async () => {
    try {
        const res = await posApi.productLookup({ q: '' });
        const data = res.data?.data || res.data || [];
        products.value = Array.isArray(data) ? data : [];
    } catch (error) {
        console.error('Failed to load products:', error);
    }
});
</script>

<style scoped>
.product-units-page {
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
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.page-title p {
    margin: 0.35rem 0 0;
    color: #5f6d85;
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    font-weight: 600;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.product-selector {
    max-width: 500px;
}

.product-select {
    width: 100%;
}

.unit-name {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.unit-name-ar {
    font-size: 0.85rem;
    color: #6b7c98;
}

.barcode-text {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-family: monospace;
    font-size: 0.95rem;
    color: #253358;
}

.no-barcode {
    color: #9ca3af;
}

.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: #9ca3af;
}

.empty-state .el-icon {
    margin-bottom: 1rem;
    color: #d1d5db;
}

.empty-state p {
    margin: 0 0 1rem;
    font-size: 1rem;
}

.form-hint {
    font-size: 0.75rem;
    color: #9ca3af;
    margin-top: 0.25rem;
}

.selection-card,
.units-card {
    margin-bottom: 1.5rem;
    border-radius: 1rem;
}

@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .page-title h1 {
        font-size: 1.5rem;
    }

    .card-header {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
