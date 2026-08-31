<!-- resources/js/views/admin/wms/Stock/Balances.vue -->
<template>
    <div class="balances-page">
        <AdminPageHeader
            icon="fas fa-scale-balanced"
            :title="$t('balances_and_movements')"
            :subtitle="$t('balances_subtitle')"
        >
            <template #actions>
                <el-button plain :loading="refreshing" @click="refreshData">
                    <i class="fas fa-rotate"></i> {{ $t('update') }}
                </el-button>
                <el-button type="primary" @click="openMovementModal">
                    <i class="fas fa-plus"></i> {{ $t('add_movement') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <!-- Product / warehouse picker -->
        <el-card shadow="never" class="picker-card">
            <div class="picker-grid">
                <el-form-item :label="$t('product')" class="no-margin">
                    <el-select v-model="selectedProduct" filterable :placeholder="$t('choose_product')" style="width: 100%" @change="selectProductAndWarehouse">
                        <el-option v-for="prod in products" :key="prod.id" :value="prod" :label="`${prod.name} (${prod.code})`" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="$t('warehouse')" class="no-margin">
                    <el-select v-model="selectedWarehouse" :placeholder="$t('choose_warehouse')" style="width: 100%" @change="selectProductAndWarehouse">
                        <el-option v-for="wh in warehouses" :key="wh.id" :value="wh" :label="`${wh.name} (${wh.code})`" />
                    </el-select>
                </el-form-item>
            </div>
        </el-card>

        <el-alert v-if="error" type="error" :title="error" show-icon :closable="false" class="mt-3" />

        <!-- Balance stat cards -->
        <div v-if="balance" class="stat-grid mt-3">
            <el-card shadow="never" class="stat-card stat-blue">
                <span class="stat-label">{{ $t('current_balance') }}</span>
                <strong class="stat-value">{{ formatNumber(balance.quantity) }}</strong>
            </el-card>
            <el-card shadow="never" class="stat-card stat-orange">
                <span class="stat-label">{{ $t('reserved') }}</span>
                <strong class="stat-value">{{ formatNumber(balance.reserved_quantity) }}</strong>
            </el-card>
            <el-card shadow="never" class="stat-card stat-green">
                <span class="stat-label">{{ $t('available') }}</span>
                <strong class="stat-value">{{ formatNumber(balance.available_quantity) }}</strong>
            </el-card>
            <el-card shadow="never" class="stat-card stat-purple">
                <span class="stat-label">{{ $t('safety_stock') }}</span>
                <strong class="stat-value">{{ formatNumber(balance.safety_stock) }}</strong>
            </el-card>
        </div>

        <!-- Status + fill level -->
        <el-card v-if="balance" shadow="never" class="mt-3">
            <div class="status-head">
                <div>
                    <h3 class="card-title">{{ $t('balance_status') }}</h3>
                    <p class="status-sub">{{ $t('minimum') }}: {{ formatNumber(balance.reorder_point) }} · {{ $t('maximum') }}: {{ formatNumber(balance.max_stock) }}</p>
                </div>
                <el-tag :type="balanceStatus.type" size="large" effect="dark">{{ balanceStatus.icon }} {{ balanceStatus.text }}</el-tag>
            </div>
            <el-progress
                :percentage="fillPercentage"
                :status="balanceStatus.type === 'danger' ? 'exception' : (balanceStatus.type === 'warning' ? 'warning' : 'success')"
                :stroke-width="10"
            />
        </el-card>

        <!-- Movement ledger -->
        <el-card shadow="never" class="mt-3">
            <template #header>
                <div class="card-header">
                    <span><i class="fas fa-list"></i> {{ $t('movement_ledger') }}</span>
                    <el-button size="small" plain @click="exportTransactions">
                        <i class="fas fa-download"></i> {{ $t('export_csv') }}
                    </el-button>
                </div>
            </template>

            <div class="filters-row">
                <el-select v-model="movementTypeFilter" :placeholder="$t('movement_type')" clearable style="width: 160px">
                    <el-option value="in" :label="$t('deposit')" />
                    <el-option value="out" :label="$t('issue_movement')" />
                    <el-option value="adjustment" :label="$t('adjustment')" />
                    <el-option value="transfer" :label="$t('transfer_movement')" />
                </el-select>
                <el-date-picker v-model="dateFromFilter" type="date" value-format="YYYY-MM-DD" :placeholder="$t('date_from')" style="width: 160px" />
                <el-date-picker v-model="dateToFilter" type="date" value-format="YYYY-MM-DD" :placeholder="$t('date_to')" style="width: 160px" />
            </div>

            <el-table v-if="selectedProduct && selectedWarehouse" :data="filteredTransactions" stripe>
                <el-table-column :label="$t('date')" width="160">
                    <template #default="{ row }">{{ row.created_at }}</template>
                </el-table-column>
                <el-table-column :label="$t('type')" width="120">
                    <template #default="{ row }">
                        <el-tag :type="movementTagType(row.movement_type)" size="small">{{ getMovementTypeText(row.movement_type) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('quantity')" width="110" align="center">
                    <template #default="{ row }">
                        <strong :class="{ 'text-success': row.movement_type === 'in', 'text-danger': row.movement_type === 'out' || row.movement_type === 'transfer' }">
                            {{ row.movement_type === 'in' ? '+' : (row.movement_type === 'adjustment' ? (row.quantity >= 0 ? '+' : '') : '-') }}{{ formatNumber(Math.abs(row.quantity)) }}
                        </strong>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('document')" min-width="130">
                    <template #default="{ row }">{{ row.reference_document || '—' }}</template>
                </el-table-column>
                <el-table-column :label="$t('notes')" min-width="160">
                    <template #default="{ row }">{{ row.notes || '—' }}</template>
                </el-table-column>
            </el-table>

            <el-empty v-else-if="!selectedProduct || !selectedWarehouse" :description="$t('choose_product_and_warehouse')" />
            <el-empty v-else-if="!filteredTransactions.length" :description="$t('no_movements')" />
        </el-card>

        <!-- Add movement dialog -->
        <el-dialog v-model="showMovementModal" :title="$t('add_stock_movement')" width="520px" @close="handleCancel">
            <el-form :model="form" label-position="top">
                <el-form-item :label="$t('movement_type')">
                    <el-select v-model="form.movement_type" style="width: 100%">
                        <el-option value="in" :label="$t('deposit')" />
                        <el-option value="out" :label="$t('issue_movement')" />
                        <el-option value="adjustment" :label="$t('adjustment')" />
                        <el-option value="transfer" :label="$t('transfer_movement')" />
                    </el-select>
                </el-form-item>

                <el-form-item v-if="form.movement_type === 'transfer'" :label="$t('destination_warehouse')">
                    <el-select v-model="form.to_warehouse_id" :placeholder="$t('choose_warehouse')" style="width: 100%">
                        <el-option
                            v-for="wh in warehouses.filter(w => w.id !== form.warehouse_id)"
                            :key="wh.id" :value="wh.id" :label="`${wh.name} (${wh.code})`"
                        />
                    </el-select>
                </el-form-item>

                <el-form-item :label="$t('quantity')">
                    <el-input-number v-model="form.quantity" :min="1" style="width: 100%" />
                </el-form-item>

                <el-alert v-if="balance" type="info" :closable="false" show-icon>
                    <template #title>
                        {{ $t('available_after_operation') }} <strong>{{ formatNumber(previewBalance) }}</strong>
                    </template>
                </el-alert>

                <el-form-item :label="$t('reference_document')" class="mt-3">
                    <el-input v-model="form.reference_document" :placeholder="$t('example_po_number')" />
                </el-form-item>

                <el-form-item :label="$t('notes')">
                    <el-input v-model="form.notes" type="textarea" :rows="2" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button :disabled="submitting" @click="handleCancel">{{ $t('cancel') }}</el-button>
                <el-button type="primary" :loading="submitting" @click="submitMovement">{{ $t('save') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { formatNumber as formatCount } from '@/utils/currency';
import { useI18n } from 'vue-i18n';
import { ref, computed, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import api from '@/api';
import { wmsService } from '@/services/wms';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';

const { t } = useI18n();

// State management
const products = ref([]);
const warehouses = ref([]);
const selectedProduct = ref(null);
const selectedWarehouse = ref(null);
const balance = ref(null);
const transactions = ref([]);
const showMovementModal = ref(false);
const submitting = ref(false);
const error = ref(null);
const refreshing = ref(false);

const movementTypeFilter = ref('');
const dateFromFilter = ref('');
const dateToFilter = ref('');

const form = ref({
    product_id: null,
    warehouse_id: null,
    to_warehouse_id: null,
    movement_type: 'in',
    quantity: 1,
    reference_document: '',
    notes: '',
});

const previewBalance = computed(() => {
    if (!balance.value) return 0;
    const availableQty = Number(balance.value.available_quantity) || 0;
    const qty = Number(form.value.quantity) || 0;
    if (form.value.movement_type === 'in' || form.value.movement_type === 'adjustment') return availableQty + qty;
    if (form.value.movement_type === 'out' || form.value.movement_type === 'transfer') return availableQty - qty;
    return availableQty;
});

const balanceStatus = computed(() => {
    if (!balance.value) return { type: 'info', text: t('out_of_stock'), icon: '○' };
    const availableQty = Number(balance.value.available_quantity) || 0;
    const minStock = Number(balance.value.reorder_point) || 0;
    const safetyStock = Number(balance.value.safety_stock) || 0;
    if (availableQty <= safetyStock) return { type: 'danger', text: t('very_low'), icon: '⚠' };
    if (availableQty <= minStock) return { type: 'warning', text: t('low'), icon: '⚠' };
    return { type: 'success', text: t('good'), icon: '✓' };
});

const fillPercentage = computed(() => {
    if (!balance.value || !balance.value.max_stock) return 0;
    return Math.min(100, Math.round((Number(balance.value.available_quantity) / Number(balance.value.max_stock)) * 100));
});

const filteredTransactions = computed(() => {
    if (!Array.isArray(transactions.value)) return [];
    let result = [...transactions.value];
    if (movementTypeFilter.value) result = result.filter((r) => r.movement_type === movementTypeFilter.value);
    if (dateFromFilter.value) result = result.filter((r) => r.created_at >= dateFromFilter.value);
    if (dateToFilter.value) result = result.filter((r) => r.created_at <= dateToFilter.value + ' 23:59:59');
    return result;
});

async function fetchProducts() {
    try {
        const response = await api.get('/products', { params: { per_page: 200 } });
        products.value = response.data?.data || [];
    } catch {
        ElMessage.warning(t('failed_to_fetch_products'));
    }
}

async function fetchWarehouses() {
    try {
        const response = await wmsService.getWarehouses();
        warehouses.value = response.data?.data || response.data || [];
    } catch {
        ElMessage.warning(t('failed_to_fetch_warehouses'));
    }
}

async function fetchBalance() {
    if (!selectedProduct.value || !selectedWarehouse.value) return;
    try {
        const response = await wmsService.getStockBalance({
            product_id: selectedProduct.value.id,
            warehouse_id: selectedWarehouse.value.id,
        });
        balance.value = response.data?.data || null;
        error.value = null;
    } catch (err) {
        error.value = err.response?.data?.message || t('failed_to_fetch_balance');
        ElMessage.error(error.value);
    }
}

async function fetchTransactions() {
    if (!selectedProduct.value || !selectedWarehouse.value) return;
    try {
        const response = await wmsService.getStockTransactions({
            product_id: selectedProduct.value.id,
            warehouse_id: selectedWarehouse.value.id,
        });
        transactions.value = Array.isArray(response.data?.data) ? response.data.data : [];
    } catch {
        ElMessage.warning(t('failed_to_fetch_movements'));
    }
}

async function refreshData() {
    refreshing.value = true;
    await Promise.all([fetchProducts(), fetchWarehouses()]);
    if (selectedProduct.value && selectedWarehouse.value) {
        await Promise.all([fetchBalance(), fetchTransactions()]);
    }
    refreshing.value = false;
    ElMessage.success(t('data_updated_successfully'));
}

function selectProductAndWarehouse() {
    if (!selectedProduct.value || !selectedWarehouse.value) {
        balance.value = null;
        transactions.value = [];
        return;
    }
    form.value.product_id = selectedProduct.value.id;
    form.value.warehouse_id = selectedWarehouse.value.id;
    fetchBalance();
    fetchTransactions();
}

function openMovementModal() {
    if (!selectedProduct.value || !selectedWarehouse.value) {
        ElMessage.warning(t('choose_product_and_warehouse_first'));
        return;
    }
    selectProductAndWarehouse();
    showMovementModal.value = true;
}

function validateQuantity() {
    const qty = Number(form.value.quantity);
    if (!qty || qty <= 0) {
        ElMessage.error(t('quantity_must_be_positive'));
        return false;
    }
    if ((form.value.movement_type === 'out' || form.value.movement_type === 'transfer') && previewBalance.value < 0) {
        ElMessage.error(t('insufficient_available_balance'));
        return false;
    }
    if (form.value.movement_type === 'transfer' && !form.value.to_warehouse_id) {
        ElMessage.error(t('please_choose_destination_warehouse'));
        return false;
    }
    return true;
}

function resetForm() {
    form.value = {
        product_id: selectedProduct.value?.id,
        warehouse_id: selectedWarehouse.value?.id,
        to_warehouse_id: null,
        movement_type: 'in',
        quantity: 1,
        reference_document: '',
        notes: '',
    };
}

async function submitMovement() {
    if (!validateQuantity()) return;
    submitting.value = true;
    try {
        await wmsService.createStockMovement(form.value);
        ElMessage.success(t('movement_added'));
        showMovementModal.value = false;
        resetForm();
        await Promise.all([fetchBalance(), fetchTransactions()]);
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('failed_to_add_movement'));
    } finally {
        submitting.value = false;
    }
}

function exportTransactions() {
    if (!filteredTransactions.value.length) {
        ElMessage.warning(t('no_movements_to_export'));
        return;
    }
    try {
        const csv = [
            [t('date'), t('type'), t('quantity'), t('document'), t('notes')],
            ...filteredTransactions.value.map((row) => [
                row.created_at,
                getMovementTypeText(row.movement_type),
                row.quantity,
                row.reference_document || '-',
                row.notes || '-',
            ]),
        ].map((row) => row.join(',')).join('\n');

        const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `stock_movements_${selectedProduct.value?.code}_${selectedWarehouse.value?.code}_${new Date().toISOString().split('T')[0]}.csv`;
        link.click();
        ElMessage.success(t('movements_exported'));
    } catch {
        ElMessage.error(t('failed_to_export_movements'));
    }
}

function handleCancel() {
    showMovementModal.value = false;
    resetForm();
}

function formatNumber(num) {
    if (num === null || num === undefined) return '—';
    return formatCount(num);
}

function movementTagType(type) {
    return { in: 'success', out: 'danger', adjustment: 'warning', transfer: 'primary' }[type] || 'info';
}

function getMovementTypeText(type) {
    return { in: t('deposit'), out: t('issue_movement'), adjustment: t('adjustment'), transfer: t('transfer_movement') }[type] || type;
}

onMounted(() => {
    fetchProducts();
    fetchWarehouses();
});
</script>

<style scoped>
.balances-page { font-family: 'Cairo', sans-serif; }

.picker-card { border-radius: 12px; }
.picker-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; }
.no-margin :deep(.el-form-item__content) { margin: 0 !important; }

.stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; }
.stat-card { border-radius: 12px; border-inline-start: 4px solid var(--el-color-info); }
.stat-card :deep(.el-card__body) { display: flex; flex-direction: column; gap: 0.3rem; }
.stat-blue { border-inline-start-color: var(--el-color-primary); }
.stat-orange { border-inline-start-color: var(--el-color-warning); }
.stat-green { border-inline-start-color: var(--el-color-success); }
.stat-purple { border-inline-start-color: #8b5cf6; }
.stat-label { font-size: 0.8rem; color: var(--text-muted); }
.stat-value { font-size: 1.5rem; font-weight: 800; }

.status-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap; }
.card-title { margin: 0; font-size: 1.05rem; font-weight: 700; }
.status-sub { margin: 0.25rem 0 0; font-size: 0.82rem; color: var(--text-muted); }

.card-header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; font-weight: 700; }
.filters-row { display: flex; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 1rem; }

.text-success { color: var(--el-color-success); }
.text-danger { color: var(--el-color-danger); }

.mt-3 { margin-top: 0.75rem; }
</style>
