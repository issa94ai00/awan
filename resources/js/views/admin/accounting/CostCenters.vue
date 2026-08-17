<template>
    <div class="accounting-page cost-centers">
        <AdminPageHeader
            icon="fas fa-diagram-project text-primary"
            :title="$t('cost_centers')"
            :subtitle="$t('cost_centers_subtitle')"
        >
            <template #actions>
                <el-date-picker
                    v-model="range"
                    type="daterange"
                    format="YYYY-MM-DD"
                    value-format="YYYY-MM-DD"
                    :start-placeholder="$t('period_from')"
                    :end-placeholder="$t('to')"
                    @change="loadStatement"
                />
                <el-button :icon="Refresh" :loading="loading" @click="reload" />
                <el-button type="primary" :icon="Plus" @click="openForm()">
                    {{ $t('add_cost_center') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <el-alert v-if="error" type="error" show-icon :closable="false" class="mb-4" :title="error" />

        <!-- How much of the period belongs to no branch. A large share means the
             dimension is not being captured, and every per-branch figure below
             is that much less complete — so it is stated before them. -->
        <el-alert
            v-if="statement && statement.unattributed_share !== null && statement.unattributed_share > 0"
            :type="statement.unattributed_share > 40 ? 'warning' : 'info'"
            show-icon
            :closable="false"
            class="mb-4"
            :title="$t('unattributed_share_notice', { percent: statement.unattributed_share })"
        />

        <el-card shadow="hover" class="table-panel mb-4">
            <template #header>
                <div class="card-header">
                    <span><i class="fas fa-chart-pie text-muted"></i> {{ $t('result_per_center') }}</span>
                    <span v-if="statement" class="muted">
                        {{ statement.period.from }} → {{ statement.period.to }}
                    </span>
                </div>
            </template>

            <el-skeleton v-if="loading" :rows="4" animated />

            <template v-else-if="statement && statement.centers.length">
                <el-table :data="statement.centers" stripe style="width:100%">
                    <el-table-column :label="$t('the_center')" min-width="170">
                        <template #default="{ row }">
                            <span :class="{ muted: row.id === null }">{{ row.name }}</span>
                            <span v-if="row.code" class="mono ms-2">{{ row.code }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('revenue')" width="130" align="right">
                        <template #default="{ row }">{{ money(row.revenue) }}</template>
                    </el-table-column>
                    <el-table-column :label="$t('cost_of_sales')" width="130" align="right">
                        <template #default="{ row }">{{ money(row.cost_of_sales) }}</template>
                    </el-table-column>
                    <el-table-column :label="$t('gross_profit')" width="130" align="right">
                        <template #default="{ row }"><strong>{{ money(row.gross_profit) }}</strong></template>
                    </el-table-column>
                    <el-table-column :label="$t('margin')" width="100" align="right">
                        <template #default="{ row }">
                            <span v-if="row.margin_percentage !== null">{{ row.margin_percentage }}%</span>
                            <span v-else class="muted">—</span>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('operating_expenses')" width="140" align="right">
                        <template #default="{ row }">{{ money(row.operating_expenses) }}</template>
                    </el-table-column>
                    <el-table-column :label="$t('net_result')" width="140" align="right">
                        <template #default="{ row }">
                            <strong :class="row.net_result >= 0 ? 'up' : 'down'">
                                {{ money(row.net_result) }}
                            </strong>
                        </template>
                    </el-table-column>
                </el-table>

                <div class="totals-row">
                    <span>{{ $t('total') }}</span>
                    <span>{{ $t('revenue') }}: <strong>{{ money(statement.totals.revenue) }}</strong></span>
                    <span>
                        {{ $t('net_result') }}:
                        <strong :class="statement.totals.net_result >= 0 ? 'up' : 'down'">
                            {{ money(statement.totals.net_result) }}
                        </strong>
                    </span>
                </div>
            </template>

            <el-empty v-else :description="$t('no_activity_in_this_period')" />
        </el-card>

        <el-card shadow="hover" class="table-panel">
            <template #header>
                <div class="card-header">
                    <span><i class="fas fa-sitemap text-muted"></i> {{ $t('the_centers') }}</span>
                </div>
            </template>

            <el-table v-if="centers.length" :data="centers" stripe style="width:100%">
                <el-table-column prop="code" :label="$t('code')" width="110">
                    <template #default="{ row }"><span class="mono">{{ row.code }}</span></template>
                </el-table-column>
                <el-table-column prop="name" :label="$t('name')" min-width="170" />
                <el-table-column :label="$t('linked_warehouse')" min-width="150">
                    <template #default="{ row }">
                        <el-tag v-if="row.warehouse" type="success" effect="plain" size="small">
                            {{ row.warehouse.name }}
                        </el-tag>
                        <!-- A centre with no warehouse cannot be attributed to
                             automatically; it carries what is posted to it by
                             hand, which is what overheads are. -->
                        <el-tooltip v-else :content="$t('center_without_warehouse_hint')" placement="top">
                            <el-tag type="info" effect="plain" size="small">{{ $t('overhead_center') }}</el-tag>
                        </el-tooltip>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('entry_lines')" width="120" align="center">
                    <template #default="{ row }">{{ row.journal_entry_lines_count }}</template>
                </el-table-column>
                <el-table-column :label="$t('status')" width="110" align="center">
                    <template #default="{ row }">
                        <el-tag :type="row.is_active ? 'success' : 'info'" effect="plain" size="small">
                            {{ row.is_active ? $t('active') : $t('inactive') }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('actions')" width="140" align="center">
                    <template #default="{ row }">
                        <el-button-group>
                            <el-button size="small" @click="openForm(row)"><i class="fas fa-edit"></i></el-button>
                            <el-button
                                size="small"
                                type="danger"
                                plain
                                :disabled="row.journal_entry_lines_count > 0"
                                @click="remove(row)"
                            >
                                <i class="fas fa-trash"></i>
                            </el-button>
                        </el-button-group>
                    </template>
                </el-table-column>
            </el-table>

            <el-empty v-else :description="$t('no_cost_centers_yet')" />
        </el-card>

        <el-dialog
            v-model="formVisible"
            :title="editingId ? $t('edit_cost_center') : $t('add_cost_center')"
            width="480px"
            destroy-on-close
        >
            <el-form :model="form" label-position="top">
                <el-row :gutter="16">
                    <el-col :span="10">
                        <el-form-item :label="$t('code')" required>
                            <el-input v-model="form.code" placeholder="CC-010" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="14">
                        <el-form-item :label="$t('name')" required>
                            <el-input v-model="form.name" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-form-item :label="$t('linked_warehouse')">
                    <el-select v-model="form.warehouse_id" clearable filterable style="width:100%">
                        <el-option
                            v-for="warehouse in warehouseOptions"
                            :key="warehouse.id"
                            :label="warehouse.name"
                            :value="warehouse.id"
                        />
                    </el-select>
                    <small class="hint">{{ $t('linked_warehouse_hint') }}</small>
                </el-form-item>

                <el-form-item v-if="editingId" :label="$t('status')">
                    <el-switch
                        v-model="form.is_active"
                        :active-text="$t('active')"
                        :inactive-text="$t('inactive')"
                    />
                </el-form-item>

                <el-form-item :label="$t('notes')">
                    <el-input v-model="form.notes" type="textarea" :rows="2" />
                </el-form-item>
            </el-form>

            <template #footer>
                <el-button @click="formVisible = false">{{ $t('cancel') }}</el-button>
                <el-button type="primary" :loading="saving" :disabled="!canSubmit" @click="submit">
                    {{ $t('save') }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Refresh } from '@element-plus/icons-vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import { costCentersApi } from '@/api/costCenters';
import { accountingReportsApi } from '@/api/accountingReports';

const { t } = useI18n();

const centers = ref([]);
const availableWarehouses = ref([]);
const statement = ref(null);
const range = ref([]);
const loading = ref(false);
const saving = ref(false);
const error = ref('');

const formVisible = ref(false);
const editingId = ref(null);
const form = reactive({
    code: '',
    name: '',
    warehouse_id: null,
    is_active: true,
    notes: '',
});

const money = (value) => Number(value || 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const canSubmit = computed(() => form.code.trim() && form.name.trim());

// A warehouse belongs to one centre only, so the list offers the free ones —
// plus the one this centre already holds, which would otherwise vanish from
// its own edit form.
const warehouseOptions = computed(() => {
    const options = [...availableWarehouses.value];
    const current = centers.value.find((c) => c.id === editingId.value);

    if (current?.warehouse && !options.some((w) => w.id === current.warehouse.id)) {
        options.unshift(current.warehouse);
    }

    return options;
});

const loadCenters = async () => {
    const res = await costCentersApi.getAll();
    const data = res.data?.data || {};
    centers.value = data.centers || [];
    availableWarehouses.value = data.available_warehouses || [];
};

const loadStatement = async () => {
    const res = await accountingReportsApi.costCenterStatement({
        date_from: range.value?.[0] || undefined,
        date_to: range.value?.[1] || undefined,
    });
    statement.value = res.data?.data || null;
};

const reload = async () => {
    loading.value = true;
    error.value = '';
    try {
        await Promise.all([loadCenters(), loadStatement()]);
    } catch (e) {
        error.value = e.response?.data?.message || e.message || t('failed_to_load_report');
    } finally {
        loading.value = false;
    }
};

const openForm = (center = null) => {
    editingId.value = center?.id ?? null;
    form.code = center?.code ?? '';
    form.name = center?.name ?? '';
    form.warehouse_id = center?.warehouse_id ?? null;
    form.is_active = center ? Boolean(center.is_active) : true;
    form.notes = center?.notes ?? '';
    formVisible.value = true;
};

const submit = async () => {
    saving.value = true;
    try {
        const payload = {
            code: form.code.trim(),
            name: form.name.trim(),
            warehouse_id: form.warehouse_id || null,
            notes: form.notes || null,
        };

        if (editingId.value) {
            await costCentersApi.update(editingId.value, { ...payload, is_active: form.is_active });
        } else {
            await costCentersApi.create(payload);
        }

        formVisible.value = false;
        ElMessage.success(t('cost_center_saved'));
        await reload();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('failed_to_save_cost_center'));
    } finally {
        saving.value = false;
    }
};

const remove = async (center) => {
    try {
        await ElMessageBox.confirm(t('confirm_delete_cost_center'), t('confirm_deletion'), {
            type: 'warning',
            confirmButtonText: t('delete'),
            cancelButtonText: t('cancel'),
        });
    } catch {
        return;
    }

    try {
        await costCentersApi.remove(center.id);
        ElMessage.success(t('cost_center_deleted'));
        await reload();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('failed_to_save_cost_center'));
    }
};

onMounted(reload);
</script>

<style scoped>
.cost-centers {
    font-family: 'Cairo', sans-serif;
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

.totals-row {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    padding-top: 0.85rem;
    margin-top: 0.85rem;
    border-top: 1px solid var(--border-color);
    font-weight: 600;
}

.mono {
    font-family: monospace;
    font-weight: 700;
    font-size: 0.85em;
}

.ms-2 {
    margin-inline-start: 0.5rem;
}

.muted {
    color: var(--text-muted);
    font-size: 0.88rem;
}

.hint {
    display: block;
    margin-top: 0.35rem;
    color: var(--text-muted);
    font-size: 0.78rem;
}

.up { color: #1b6b4c; }
.down { color: #9b2c2c; }
</style>
