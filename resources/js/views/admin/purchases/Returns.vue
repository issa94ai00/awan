<template>
    <div class="purchases-page purchase-returns">
        <AdminPageHeader
            icon="fas fa-rotate-left text-primary"
            :title="$t('purchase_returns')"
            :subtitle="$t('purchase_returns_subtitle')"
        >
            <template #actions>
                <el-button :icon="Refresh" :loading="loading" @click="reload" />
                <el-button type="primary" :icon="Plus" @click="openCreate">
                    {{ $t('record_purchase_return') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <el-card shadow="hover" class="table-panel">
            <template #header>
                <div class="card-header">
                    <span><i class="fas fa-list text-muted"></i> {{ $t('purchase_returns') }}</span>
                </div>
            </template>

            <el-skeleton v-if="loading" :rows="5" animated />
            <el-alert v-else-if="error" type="error" show-icon :closable="false" :title="error" />

            <template v-else>
                <el-table v-if="returns.length" :data="returns" stripe style="width:100%">
                    <el-table-column prop="return_number" :label="$t('reference')" width="120">
                        <template #default="{ row }"><span class="mono">{{ row.return_number }}</span></template>
                    </el-table-column>
                    <el-table-column :label="$t('supplier')" min-width="150">
                        <template #default="{ row }">{{ row.supplier?.name || '—' }}</template>
                    </el-table-column>
                    <el-table-column :label="$t('return_date')" width="120" align="center">
                        <template #default="{ row }">{{ String(row.return_date || '').slice(0, 10) }}</template>
                    </el-table-column>
                    <el-table-column :label="$t('items_count')" width="100" align="center">
                        <template #default="{ row }">{{ row.items?.length || 0 }}</template>
                    </el-table-column>
                    <el-table-column :label="$t('credit_amount')" width="140" align="right">
                        <template #default="{ row }"><strong>{{ money(row.credit_amount) }}</strong></template>
                    </el-table-column>
                    <el-table-column :label="$t('tax')" width="110" align="right">
                        <template #default="{ row }">{{ money(row.tax_amount) }}</template>
                    </el-table-column>
                    <el-table-column :label="$t('reason')" min-width="140" show-overflow-tooltip>
                        <template #default="{ row }">{{ row.reason || '—' }}</template>
                    </el-table-column>
                </el-table>

                <el-empty v-else :description="$t('no_purchase_returns_yet')" />
            </template>
        </el-card>

        <el-drawer
            v-model="createVisible"
            :title="$t('record_purchase_return')"
            size="55%"
            direction="rtl"
            destroy-on-close
        >
            <!-- Says what this document is for, because the alternative people
                 reach for — a stock adjustment — books the goods out as
                 shrinkage and leaves the supplier owed in full. -->
            <el-alert
                type="info"
                show-icon
                :closable="false"
                class="mb-4"
                :title="$t('purchase_return_is_not_shrinkage')"
            />

            <el-form :model="form" label-position="top">
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="$t('supplier')" required>
                            <el-select v-model="form.supplier_id" filterable style="width:100%">
                                <el-option
                                    v-for="supplier in suppliers"
                                    :key="supplier.id"
                                    :label="supplier.name"
                                    :value="supplier.id"
                                />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="$t('warehouse')" required>
                            <el-select v-model="form.warehouse_id" style="width:100%">
                                <el-option
                                    v-for="warehouse in warehouses"
                                    :key="warehouse.id"
                                    :label="warehouse.name"
                                    :value="warehouse.id"
                                />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item :label="$t('return_date')">
                            <el-date-picker
                                v-model="form.return_date"
                                type="date"
                                format="YYYY-MM-DD"
                                value-format="YYYY-MM-DD"
                                style="width:100%"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="$t('tax_returned')">
                            <el-input v-model="form.tax_amount" type="number" min="0" step="0.01" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="$t('reason')">
                            <el-input v-model="form.reason" :placeholder="$t('return_reason_example')" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <div class="lines-section">
                    <div class="lines-header">
                        <span>{{ $t('returned_items') }}</span>
                        <el-button size="small" type="primary" plain @click="addLine">
                            <i class="fas fa-plus"></i> {{ $t('add_item') }}
                        </el-button>
                    </div>

                    <div v-for="(line, index) in form.items" :key="index" class="line-row">
                        <el-select
                            v-model="line.product_id"
                            :placeholder="$t('select_item')"
                            filterable
                            style="flex: 2.5;"
                        >
                            <el-option
                                v-for="product in products"
                                :key="product.id"
                                :label="product.name_ar + (product.sku ? ' (' + product.sku + ')' : '')"
                                :value="product.id"
                            />
                        </el-select>
                        <el-input-number v-model="line.quantity" :min="1" style="flex: 1;" />
                        <el-input
                            v-model="line.unit_price"
                            type="number"
                            min="0"
                            step="0.01"
                            :placeholder="$t('credit_per_unit')"
                            style="flex: 1;"
                        />
                        <el-button
                            type="danger"
                            circle
                            size="small"
                            :disabled="form.items.length <= 1"
                            @click="removeLine(index)"
                        >
                            <i class="fas fa-times"></i>
                        </el-button>
                    </div>

                    <!-- The price is what the supplier credits, which is not
                         always what the goods cost us; the gap is a real result
                         and the server books it as one. -->
                    <small class="hint">{{ $t('credit_per_unit_hint') }}</small>
                </div>

                <div class="drawer-footer">
                    <el-button @click="createVisible = false">{{ $t('cancel') }}</el-button>
                    <el-button type="primary" :loading="saving" :disabled="!canSubmit" @click="submit">
                        {{ $t('save') }}
                    </el-button>
                </div>
            </el-form>
        </el-drawer>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Plus, Refresh } from '@element-plus/icons-vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import { purchaseReturnsApi } from '@/api/purchaseReturns';
import { suppliersApi } from '@/api/suppliers';
import { productsApi } from '@/api/products';
import { useInventoryStore } from '@/stores/inventory';

const { t } = useI18n();
const inventoryStore = useInventoryStore();

const returns = ref([]);
const suppliers = ref([]);
const products = ref([]);
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const createVisible = ref(false);

const form = reactive({
    supplier_id: null,
    warehouse_id: null,
    return_date: new Date().toISOString().slice(0, 10),
    tax_amount: 0,
    reason: '',
    items: [{ product_id: null, quantity: 1, unit_price: '' }],
});

const warehouses = computed(() => inventoryStore.warehouses || []);

const money = (value) => Number(value || 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const canSubmit = computed(() =>
    form.supplier_id
    && form.warehouse_id
    && form.items.some((line) => line.product_id && Number(line.quantity) > 0)
);

const reload = async () => {
    loading.value = true;
    error.value = '';
    try {
        const res = await purchaseReturnsApi.getAll({ per_page: 50 });
        returns.value = res.data?.data?.returns || [];
    } catch (e) {
        error.value = e.response?.data?.message || e.message || t('failed_to_load_report');
    } finally {
        loading.value = false;
    }
};

const openCreate = async () => {
    form.supplier_id = null;
    form.warehouse_id = warehouses.value.length === 1 ? warehouses.value[0].id : null;
    form.return_date = new Date().toISOString().slice(0, 10);
    form.tax_amount = 0;
    form.reason = '';
    form.items = [{ product_id: null, quantity: 1, unit_price: '' }];
    createVisible.value = true;

    if (!suppliers.value.length) {
        try {
            const res = await suppliersApi.getAll({ per_page: 200 });
            suppliers.value = res.data?.data?.suppliers || [];
        } catch {
            // The form still works with a supplier typed by id elsewhere; the
            // server validates it either way.
        }
    }

    if (!products.value.length) {
        try {
            const res = await productsApi.getAll({ per_page: 300 });
            products.value = res.data?.data?.products || res.data?.data || [];
        } catch {
            // Same: the field is a convenience, not the validation.
        }
    }
};

const addLine = () => form.items.push({ product_id: null, quantity: 1, unit_price: '' });

const removeLine = (index) => {
    if (form.items.length > 1) form.items.splice(index, 1);
};

const submit = async () => {
    saving.value = true;
    try {
        await purchaseReturnsApi.create({
            supplier_id: form.supplier_id,
            warehouse_id: form.warehouse_id,
            return_date: form.return_date,
            tax_amount: Number(form.tax_amount) || 0,
            reason: form.reason || null,
            items: form.items
                .filter((line) => line.product_id && Number(line.quantity) > 0)
                .map((line) => ({
                    product_id: line.product_id,
                    quantity: Number(line.quantity),
                    // Left out when blank, so the server falls back to what the
                    // units actually cost — the honest default.
                    ...(line.unit_price !== '' ? { unit_price: Number(line.unit_price) } : {}),
                })),
        });

        createVisible.value = false;
        ElMessage.success(t('purchase_return_recorded'));
        await reload();
    } catch (e) {
        // The server explains precisely why — stock not on the shelf, or a
        // closed period — and echoing a generic failure would hide both.
        ElMessage.error(e.response?.data?.message || t('failed_to_save_purchase_return'));
    } finally {
        saving.value = false;
    }
};

onMounted(async () => {
    await reload();

    if (!warehouses.value.length) {
        await inventoryStore.fetchWarehouses?.().catch(() => {});
    }
});
</script>

<style scoped>
.purchase-returns {
    font-family: 'Cairo', sans-serif;
}

.table-panel {
    border-radius: 1rem;
}

.card-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 700;
    color: var(--text-dark);
}

.mono {
    font-family: monospace;
    font-weight: 700;
}

.lines-section {
    margin-top: 1rem;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding: 1rem;
    background: var(--bg-light);
}

.lines-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    font-weight: 700;
}

.line-row {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    margin-bottom: 0.75rem;
}

.hint {
    display: block;
    color: var(--text-muted);
    font-size: 0.8rem;
}

.drawer-footer {
    border-top: 1px solid var(--border-color);
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}
</style>
