<template>
    <div class="accounting-page fixed-assets">
        <AdminPageHeader
            icon="fas fa-building-columns text-primary"
            :title="$t('fixed_assets')"
            :subtitle="$t('fixed_assets_subtitle')"
        >
            <template #actions>
                <el-button :icon="Refresh" :loading="loading" @click="reload" />
                <el-button type="primary" :icon="Plus" @click="openCreate">
                    {{ $t('register_asset') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <AdminStatGrid>
            <el-card shadow="hover" class="stat-card">
                <p>{{ $t('assets_at_cost') }}</p>
                <h3>{{ money(totals.cost) }}</h3>
            </el-card>
            <el-card shadow="hover" class="stat-card">
                <p>{{ $t('accumulated_depreciation') }}</p>
                <h3>{{ money(totals.accumulated_depreciation) }}</h3>
            </el-card>
            <el-card shadow="hover" class="stat-card">
                <p>{{ $t('net_book_value') }}</p>
                <h3 class="accent">{{ money(totals.cost - totals.accumulated_depreciation) }}</h3>
            </el-card>
        </AdminStatGrid>

        <!-- Depreciation has no document behind it to notice a duplicate, so
             the run is deliberate rather than automatic. -->
        <el-alert
            type="info"
            show-icon
            :closable="false"
            class="mb-4 mt-4"
            :title="$t('depreciation_is_a_monthly_run')"
        />

        <el-card shadow="hover" class="table-panel">
            <template #header>
                <div class="card-header">
                    <span><i class="fas fa-list text-muted"></i> {{ $t('asset_register') }}</span>
                    <el-radio-group v-model="statusFilter" size="small" @change="reload">
                        <el-radio-button value="active">{{ $t('in_use') }}</el-radio-button>
                        <el-radio-button value="disposed">{{ $t('disposed') }}</el-radio-button>
                    </el-radio-group>
                </div>
            </template>

            <el-skeleton v-if="loading" :rows="5" animated />
            <el-alert v-else-if="error" type="error" show-icon :closable="false" :title="error" />

            <template v-else>
                <el-table v-if="assets.length" :data="assets" stripe style="width:100%">
                    <el-table-column prop="asset_number" :label="$t('asset_number')" width="110">
                        <template #default="{ row }"><span class="mono">{{ row.asset_number }}</span></template>
                    </el-table-column>
                    <el-table-column prop="name" :label="$t('name')" min-width="160" show-overflow-tooltip />
                    <el-table-column prop="category" :label="$t('category')" width="120" show-overflow-tooltip />
                    <el-table-column :label="$t('acquired_on')" width="120" align="center">
                        <template #default="{ row }">{{ String(row.acquired_on || '').slice(0, 10) }}</template>
                    </el-table-column>
                    <el-table-column :label="$t('cost')" width="120" align="right">
                        <template #default="{ row }">{{ money(row.cost) }}</template>
                    </el-table-column>
                    <el-table-column :label="$t('accumulated_depreciation')" width="130" align="right">
                        <template #default="{ row }">{{ money(row.accumulated_depreciation) }}</template>
                    </el-table-column>
                    <el-table-column :label="$t('net_book_value')" width="130" align="right">
                        <template #default="{ row }"><strong>{{ money(row.net_book_value) }}</strong></template>
                    </el-table-column>
                    <el-table-column :label="$t('monthly_charge')" width="120" align="right">
                        <template #default="{ row }">{{ money(row.monthly_charge) }}</template>
                    </el-table-column>
                    <el-table-column :label="$t('actions')" width="110" align="center">
                        <template #default="{ row }">
                            <el-button
                                v-if="row.status === 'active'"
                                size="small"
                                type="warning"
                                plain
                                @click="openDispose(row)"
                            >
                                {{ $t('dispose_asset') }}
                            </el-button>
                            <span v-else class="muted">{{ String(row.disposed_on || '').slice(0, 10) }}</span>
                        </template>
                    </el-table-column>
                </el-table>

                <el-empty v-else :description="$t('no_assets_registered')" />
            </template>
        </el-card>

        <!-- Register -->
        <el-dialog v-model="createVisible" :title="$t('register_asset')" width="560px" destroy-on-close>
            <el-form :model="form" label-position="top">
                <el-row :gutter="16">
                    <el-col :span="14">
                        <el-form-item :label="$t('name')" required>
                            <el-input v-model="form.name" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="10">
                        <el-form-item :label="$t('category')">
                            <el-input v-model="form.category" :placeholder="$t('asset_category_example')" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item :label="$t('cost')" required>
                            <el-input v-model="form.cost" type="number" min="0" step="0.01" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="$t('salvage_value')">
                            <el-input v-model="form.salvage_value" type="number" min="0" step="0.01" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="$t('useful_life_months')" required>
                            <el-input v-model="form.useful_life_months" type="number" min="1" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="$t('acquired_on')" required>
                            <el-date-picker
                                v-model="form.acquired_on"
                                type="date"
                                format="YYYY-MM-DD"
                                value-format="YYYY-MM-DD"
                                style="width:100%"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="$t('settlement')">
                            <el-select v-model="form.settlement" style="width:100%">
                                <el-option :label="$t('on_account')" value="credit" />
                                <el-option :label="$t('cash')" value="cash" />
                                <el-option :label="$t('bank_transfer')" value="bank" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-form-item v-if="form.settlement === 'credit'" :label="$t('supplier')">
                    <el-select v-model="form.supplier_id" filterable clearable style="width:100%">
                        <el-option
                            v-for="supplier in suppliers"
                            :key="supplier.id"
                            :label="supplier.name"
                            :value="supplier.id"
                        />
                    </el-select>
                </el-form-item>

                <!-- The figure the whole schedule is derived from, shown before
                     it is committed rather than discovered a month later. -->
                <el-alert v-if="previewCharge > 0" type="success" show-icon :closable="false">
                    {{ $t('monthly_charge') }}: <strong>{{ money(previewCharge) }}</strong>
                </el-alert>
            </el-form>

            <template #footer>
                <el-button @click="createVisible = false">{{ $t('cancel') }}</el-button>
                <el-button type="primary" :loading="saving" :disabled="!canSubmit" @click="submit">
                    {{ $t('save') }}
                </el-button>
            </template>
        </el-dialog>

        <!-- Dispose -->
        <el-dialog v-model="disposeVisible" :title="$t('dispose_asset')" width="440px" destroy-on-close>
            <p class="muted mb-3">{{ $t('dispose_asset_hint') }}</p>

            <el-form label-position="top">
                <el-form-item :label="$t('disposed_on')">
                    <el-date-picker
                        v-model="disposeForm.disposed_on"
                        type="date"
                        format="YYYY-MM-DD"
                        value-format="YYYY-MM-DD"
                        style="width:100%"
                    />
                </el-form-item>
                <el-form-item :label="$t('disposal_proceeds')">
                    <el-input v-model="disposeForm.proceeds" type="number" min="0" step="0.01" />
                </el-form-item>
                <el-form-item :label="$t('settlement')">
                    <el-select v-model="disposeForm.settlement" style="width:100%">
                        <el-option :label="$t('cash')" value="cash" />
                        <el-option :label="$t('bank_transfer')" value="bank" />
                    </el-select>
                </el-form-item>
            </el-form>

            <template #footer>
                <el-button @click="disposeVisible = false">{{ $t('cancel') }}</el-button>
                <el-button type="warning" :loading="saving" @click="confirmDispose">
                    {{ $t('dispose_asset') }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Plus, Refresh } from '@element-plus/icons-vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';
import { fixedAssetsApi } from '@/api/accountingReports';
import { suppliersApi } from '@/api/suppliers';

const { t } = useI18n();

const assets = ref([]);
const suppliers = ref([]);
const totals = ref({ cost: 0, accumulated_depreciation: 0 });
const statusFilter = ref('active');
const loading = ref(false);
const saving = ref(false);
const error = ref('');

const createVisible = ref(false);
const disposeVisible = ref(false);
const disposingId = ref(null);

const form = reactive({
    name: '',
    category: '',
    cost: '',
    salvage_value: 0,
    useful_life_months: 60,
    acquired_on: new Date().toISOString().slice(0, 10),
    settlement: 'credit',
    supplier_id: null,
});

const disposeForm = reactive({
    disposed_on: new Date().toISOString().slice(0, 10),
    proceeds: 0,
    settlement: 'cash',
});

const money = (value) => Number(value || 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

/** Straight-line: only the part above the salvage value is ever charged. */
const previewCharge = computed(() => {
    const cost = Number(form.cost) || 0;
    const salvage = Number(form.salvage_value) || 0;
    const months = Number(form.useful_life_months) || 0;

    if (cost <= salvage || months <= 0) return 0;

    return (cost - salvage) / months;
});

const canSubmit = computed(() =>
    form.name.trim() && Number(form.cost) > 0 && Number(form.useful_life_months) > 0
);

const reload = async () => {
    loading.value = true;
    error.value = '';
    try {
        const res = await fixedAssetsApi.getAll({ status: statusFilter.value, per_page: 100 });
        const data = res.data?.data || {};
        assets.value = data.assets || [];
        totals.value = data.totals || { cost: 0, accumulated_depreciation: 0 };
    } catch (e) {
        error.value = e.response?.data?.message || e.message || t('failed_to_load_report');
    } finally {
        loading.value = false;
    }
};

const openCreate = async () => {
    form.name = '';
    form.category = '';
    form.cost = '';
    form.salvage_value = 0;
    form.useful_life_months = 60;
    form.acquired_on = new Date().toISOString().slice(0, 10);
    form.settlement = 'credit';
    form.supplier_id = null;
    createVisible.value = true;

    if (!suppliers.value.length) {
        try {
            const res = await suppliersApi.getAll({ per_page: 200 });
            suppliers.value = res.data?.data?.suppliers || [];
        } catch {
            // A missing supplier list does not stop a cash purchase.
        }
    }
};

const submit = async () => {
    saving.value = true;
    try {
        await fixedAssetsApi.create({
            name: form.name.trim(),
            category: form.category || null,
            cost: Number(form.cost),
            salvage_value: Number(form.salvage_value) || 0,
            useful_life_months: Number(form.useful_life_months),
            acquired_on: form.acquired_on,
            settlement: form.settlement,
            supplier_id: form.settlement === 'credit' ? form.supplier_id : null,
        });

        createVisible.value = false;
        ElMessage.success(t('asset_registered'));
        await reload();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('failed_to_save_asset'));
    } finally {
        saving.value = false;
    }
};

const openDispose = (asset) => {
    disposingId.value = asset.id;
    disposeForm.disposed_on = new Date().toISOString().slice(0, 10);
    disposeForm.proceeds = 0;
    disposeForm.settlement = 'cash';
    disposeVisible.value = true;
};

const confirmDispose = async () => {
    saving.value = true;
    try {
        await fixedAssetsApi.dispose(disposingId.value, {
            disposed_on: disposeForm.disposed_on,
            proceeds: Number(disposeForm.proceeds) || 0,
            settlement: disposeForm.settlement,
        });

        disposeVisible.value = false;
        ElMessage.success(t('asset_disposed'));
        await reload();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('failed_to_save_asset'));
    } finally {
        saving.value = false;
    }
};

onMounted(reload);
</script>

<style scoped>
.fixed-assets {
    font-family: 'Cairo', sans-serif;
}

.stat-card p {
    margin: 0;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.stat-card h3 {
    margin: 0.35rem 0 0;
    font-size: 1.6rem;
    font-weight: 800;
}

.stat-card h3.accent {
    color: #22406e;
}

.table-panel {
    border-radius: 1rem;
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    font-weight: 700;
    color: var(--text-dark);
}

.mono {
    font-family: monospace;
    font-weight: 700;
}

.muted {
    color: var(--text-muted);
    font-size: 0.88rem;
}
</style>
