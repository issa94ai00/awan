<template>
    <div class="accounting-page bank-reconciliation">
        <AdminPageHeader
            icon="fas fa-scale-balanced text-primary"
            :title="$t('bank_reconciliation')"
            :subtitle="$t('bank_reconciliation_subtitle')"
        >
            <template #actions>
                <el-button :icon="Refresh" :loading="loading" @click="reload" />
                <el-button type="primary" :icon="Plus" @click="openCreate">
                    {{ $t('start_reconciliation') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <el-skeleton v-if="loading && !current" :rows="5" animated />
        <el-alert v-else-if="error" type="error" show-icon :closable="false" :title="error" />

        <template v-else>
            <!-- The list, when nothing is open on screen -->
            <el-card v-if="!current" shadow="hover" class="table-panel">
                <template #header>
                    <div class="card-header">
                        <span><i class="fas fa-list text-muted"></i> {{ $t('reconciliations') }}</span>
                    </div>
                </template>

                <el-table v-if="reconciliations.length" :data="reconciliations" stripe style="width:100%">
                    <el-table-column prop="reference" :label="$t('reference')" width="110">
                        <template #default="{ row }"><span class="mono">{{ row.reference }}</span></template>
                    </el-table-column>
                    <el-table-column :label="$t('the_account')" min-width="160">
                        <template #default="{ row }">{{ row.account?.code }} — {{ row.account?.name }}</template>
                    </el-table-column>
                    <el-table-column :label="$t('statement_date')" width="130" align="center">
                        <template #default="{ row }">{{ String(row.statement_date || '').slice(0, 10) }}</template>
                    </el-table-column>
                    <el-table-column :label="$t('statement_balance')" width="150" align="right">
                        <template #default="{ row }">{{ money(row.statement_balance) }}</template>
                    </el-table-column>
                    <el-table-column :label="$t('difference')" width="140" align="right">
                        <template #default="{ row }">
                            <el-tag :type="row.summary.is_reconciled ? 'success' : 'danger'" effect="light">
                                {{ row.summary.is_reconciled ? $t('matching') : money(row.summary.difference) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('status')" width="110" align="center">
                        <template #default="{ row }">
                            <el-tag :type="row.status === 'completed' ? 'success' : 'info'" effect="plain">
                                {{ row.status === 'completed' ? $t('completed_state') : $t('open_period') }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('actions')" width="100" align="center">
                        <template #default="{ row }">
                            <el-button size="small" @click="openSheet(row.id)">{{ $t('open_sheet') }}</el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <el-empty v-else :description="$t('no_reconciliations_yet')" />
            </el-card>

            <!-- The working sheet -->
            <template v-else>
                <AdminStatGrid>
                    <el-card shadow="hover" class="stat-card">
                        <p>{{ $t('book_balance') }}</p>
                        <h3>{{ money(current.summary.book_balance) }}</h3>
                    </el-card>
                    <el-card shadow="hover" class="stat-card">
                        <p>{{ $t('still_outstanding') }}</p>
                        <h3>{{ money(current.summary.outstanding_total) }}</h3>
                    </el-card>
                    <el-card shadow="hover" class="stat-card">
                        <p>{{ $t('statement_balance') }}</p>
                        <h3>{{ money(current.summary.statement_balance) }}</h3>
                    </el-card>
                    <el-card shadow="hover" class="stat-card" :class="current.summary.is_reconciled ? 'ok' : 'bad'">
                        <p>{{ $t('difference') }}</p>
                        <h3>{{ money(current.summary.difference) }}</h3>
                        <small>{{ current.summary.is_reconciled ? $t('all_differences_are_timing') : $t('difference_is_not_timing') }}</small>
                    </el-card>
                </AdminStatGrid>

                <el-card shadow="hover" class="table-panel mt-4">
                    <template #header>
                        <div class="card-header">
                            <span>
                                <i class="fas fa-check-double text-muted"></i>
                                {{ current.reference }} — {{ current.account?.name }}
                            </span>
                            <div class="sheet-actions">
                                <el-button size="small" @click="current = null">{{ $t('back_to_list') }}</el-button>
                                <el-button
                                    v-if="current.status === 'open'"
                                    size="small"
                                    type="primary"
                                    :disabled="!current.summary.is_reconciled"
                                    :loading="saving"
                                    @click="complete"
                                >
                                    {{ $t('complete_reconciliation') }}
                                </el-button>
                                <el-button v-else size="small" type="warning" plain :loading="saving" @click="reopen">
                                    {{ $t('reopen_period') }}
                                </el-button>
                            </div>
                        </div>
                    </template>

                    <p class="section-note">{{ $t('tick_what_the_bank_has_seen') }}</p>

                    <el-table v-if="current.movements.length" :data="current.movements" stripe style="width:100%">
                        <el-table-column width="60" align="center">
                            <template #default="{ row }">
                                <el-checkbox
                                    :model-value="row.is_cleared"
                                    :disabled="current.status !== 'open' || saving"
                                    @change="toggle(row)"
                                />
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('date')" width="115" align="center">
                            <template #default="{ row }">{{ String(row.entry_date || '').slice(0, 10) }}</template>
                        </el-table-column>
                        <el-table-column prop="entry_number" :label="$t('entry_number')" width="120">
                            <template #default="{ row }"><span class="mono">{{ row.entry_number }}</span></template>
                        </el-table-column>
                        <el-table-column :label="$t('narration')" min-width="200" show-overflow-tooltip>
                            <template #default="{ row }">{{ row.line_description || row.description }}</template>
                        </el-table-column>
                        <el-table-column :label="$t('amount')" width="140" align="right">
                            <template #default="{ row }">
                                <strong :class="row.amount >= 0 ? 'up' : 'down'">{{ money(row.amount) }}</strong>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('status')" width="120" align="center">
                            <template #default="{ row }">
                                <el-tag :type="row.is_cleared ? 'success' : 'info'" effect="light" size="small">
                                    {{ row.is_cleared ? $t('cleared') : $t('in_transit') }}
                                </el-tag>
                            </template>
                        </el-table-column>
                    </el-table>

                    <el-empty v-else :description="$t('no_movements_up_to_this_date')" />
                </el-card>
            </template>
        </template>

        <el-dialog v-model="createVisible" :title="$t('start_reconciliation')" width="460px" destroy-on-close>
            <el-form :model="form" label-position="top">
                <el-form-item :label="$t('the_account')" required>
                    <el-select v-model="form.account_id" style="width:100%">
                        <el-option
                            v-for="account in accounts"
                            :key="account.id"
                            :label="account.code + ' — ' + account.name"
                            :value="account.id"
                        />
                    </el-select>
                </el-form-item>
                <el-form-item :label="$t('statement_date')" required>
                    <el-date-picker
                        v-model="form.statement_date"
                        type="date"
                        format="YYYY-MM-DD"
                        value-format="YYYY-MM-DD"
                        style="width:100%"
                    />
                </el-form-item>
                <el-form-item :label="$t('statement_balance')" required>
                    <el-input v-model="form.statement_balance" type="number" step="0.01" />
                    <small class="hint">{{ $t('statement_balance_hint') }}</small>
                </el-form-item>
            </el-form>

            <template #footer>
                <el-button @click="createVisible = false">{{ $t('cancel') }}</el-button>
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
import { ElMessage } from 'element-plus';
import { Plus, Refresh } from '@element-plus/icons-vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';
import { bankReconciliationsApi } from '@/api/bankReconciliations';

const { t } = useI18n();

const reconciliations = ref([]);
const accounts = ref([]);
const current = ref(null);
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const createVisible = ref(false);

const form = reactive({
    account_id: null,
    statement_date: new Date().toISOString().slice(0, 10),
    statement_balance: '',
});

const money = (value) => Number(value || 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const canSubmit = computed(() => form.account_id && form.statement_date && form.statement_balance !== '');

const reload = async () => {
    loading.value = true;
    error.value = '';
    try {
        const res = await bankReconciliationsApi.getAll({ per_page: 50 });
        const data = res.data?.data || {};
        reconciliations.value = data.reconciliations || [];
        accounts.value = data.accounts || [];
    } catch (e) {
        error.value = e.response?.data?.message || e.message || t('failed_to_load_report');
    } finally {
        loading.value = false;
    }
};

const openSheet = async (id) => {
    loading.value = true;
    try {
        const res = await bankReconciliationsApi.get(id);
        current.value = res.data?.data || null;
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('failed_to_load_report'));
    } finally {
        loading.value = false;
    }
};

const openCreate = () => {
    form.account_id = accounts.value[0]?.id ?? null;
    form.statement_date = new Date().toISOString().slice(0, 10);
    form.statement_balance = '';
    createVisible.value = true;
};

const submit = async () => {
    saving.value = true;
    try {
        const res = await bankReconciliationsApi.create({
            account_id: form.account_id,
            statement_date: form.statement_date,
            statement_balance: Number(form.statement_balance),
        });

        createVisible.value = false;
        current.value = res.data?.data || null;
        await reload();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('failed_to_save_reconciliation'));
    } finally {
        saving.value = false;
    }
};

/**
 * Ticking a movement re-reads the whole sheet rather than flipping the row
 * locally: the arithmetic that decides whether this reconciles is computed on
 * the server, and a screen that recomputed it would be a second implementation
 * free to disagree.
 */
const toggle = async (row) => {
    saving.value = true;
    try {
        const res = await bankReconciliationsApi.toggleLine(current.value.id, row.id);
        current.value = res.data?.data || current.value;
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('failed_to_save_reconciliation'));
    } finally {
        saving.value = false;
    }
};

const complete = async () => {
    saving.value = true;
    try {
        const res = await bankReconciliationsApi.complete(current.value.id);
        current.value = res.data?.data || current.value;
        ElMessage.success(t('reconciliation_completed'));
        await reload();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('failed_to_save_reconciliation'));
    } finally {
        saving.value = false;
    }
};

const reopen = async () => {
    saving.value = true;
    try {
        const res = await bankReconciliationsApi.reopen(current.value.id);
        current.value = res.data?.data || current.value;
        await reload();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('failed_to_save_reconciliation'));
    } finally {
        saving.value = false;
    }
};

onMounted(reload);
</script>

<style scoped>
.bank-reconciliation {
    font-family: 'Cairo', sans-serif;
}

.stat-card p {
    margin: 0;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.stat-card h3 {
    margin: 0.35rem 0 0.2rem;
    font-size: 1.5rem;
    font-weight: 800;
}

.stat-card small {
    color: var(--text-muted);
    font-size: 0.76rem;
}

.stat-card.ok h3 { color: #1b6b4c; }
.stat-card.bad h3 { color: #9b2c2c; }

.table-panel {
    border-radius: 1rem;
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.75rem;
    font-weight: 700;
    color: var(--text-dark);
}

.sheet-actions {
    display: flex;
    gap: 0.5rem;
}

.section-note {
    margin: 0 0 1rem;
    color: var(--text-muted);
    font-size: 0.88rem;
}

.hint {
    display: block;
    margin-top: 0.35rem;
    color: var(--text-muted);
    font-size: 0.78rem;
}

.mono {
    font-family: monospace;
    font-weight: 700;
}

.up { color: #1b6b4c; }
.down { color: #9b2c2c; }
</style>
