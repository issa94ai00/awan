<template>
    <div class="accounting-page accounting-ledger">
        <!-- Page Header -->
        <AdminPageHeader
            icon="fas fa-list-ol text-primary"
            :title="$t('ledger')"
            :subtitle="$t('ledger_subtitle')"
        >
            <template #actions>
                <el-button type="primary" class="create-btn" @click="openCreateDrawer">
                    <i class="fas fa-plus"></i> {{ $t('add_new_account') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <!-- Metric summaries of account types -->
        <AdminStatGrid>
            <el-card shadow="hover" class="type-stat-card">
                <div class="type-stat-inner">
                    <span class="label text-muted">{{ $t('asset_accounts') }}</span>
                    <strong class="count-val">{{ assetsCount }} حساب</strong>
                </div>
            </el-card>
            <el-card shadow="hover" class="type-stat-card">
                <div class="type-stat-inner">
                    <span class="label text-muted">{{ $t('liability_accounts') }}</span>
                    <strong class="count-val">{{ liabilitiesCount }} حساب</strong>
                </div>
            </el-card>
            <el-card shadow="hover" class="type-stat-card">
                <div class="type-stat-inner">
                    <span class="label text-muted">{{ $t('revenue_accounts') }}</span>
                    <strong class="count-val">{{ revenueCount }} حساب</strong>
                </div>
            </el-card>
            <el-card shadow="hover" class="type-stat-card">
                <div class="type-stat-inner">
                    <span class="label text-muted">{{ $t('expense_accounts') }}</span>
                    <strong class="count-val">{{ expenseCount }} حساب</strong>
                </div>
            </el-card>
        </AdminStatGrid>

        <!-- Main Card & Accounts Table -->
        <el-card shadow="hover" class="table-panel mt-4">
            <template #header>
                <div class="card-header">
                    <span><i class="fas fa-sitemap text-muted"></i> {{ $t('general_ledger_chart') }}</span>
                </div>
            </template>

            <div v-if="store.loading" class="loading-state">
                <el-skeleton :rows="6" animated />
            </div>
            <div v-else>
                <el-table 
                    v-if="store.accounts.length" 
                    :data="store.accounts" 
                    style="width: 100%" 
                    stripe 
                    highlight-current-row
                    class="custom-table"
                >
                    <el-table-column prop="code" :label="$t('code')" width="130" align="center">
                        <template #default="{ row }">
                            <span class="code-badge">{{ row.code }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="name" :label="$t('the_account')" min-width="180">
                        <template #default="{ row }">
                            <strong style="color: var(--text-dark); cursor: pointer;" @click="openStatementDrawer(row)">{{ row.name }}</strong>
                        </template>
                    </el-table-column>
                    <el-table-column prop="type" :label="$t('type')" width="160" align="center">
                        <template #default="{ row }">
                            <el-tag :type="typeTagType(row.type)" effect="light">{{ getArabicType(row.type) }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="balance" :label="$t('balance')" width="160" align="right">
                        <template #default="{ row }">
                            <strong :class="parseFloat(row.balance) >= 0 ? 'text-success' : 'text-danger'">
                                ${{ parseFloat(row.balance || 0).toFixed(2) }}
                            </strong>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('active')" width="120" align="center">
                        <template #default="{ row }">
                            <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                                {{ row.is_active ? 'نشط' : 'معطل' }}
                            </el-tag>
                        </template>
                    </el-table-column>

                    <!-- Actions Column -->
                    <el-table-column :label="$t('actions')" width="260" align="center">
                        <template #default="{ row }">
                            <el-button-group class="action-btn-group">
                                <el-button size="small" type="info" plain @click="openStatementDrawer(row)" :title="$t('account_statement')">
                                    <i class="fas fa-file-invoice"></i> {{ $t('statement') }}
                                </el-button>
                                <el-button size="small" type="warning" plain @click="openEditDrawer(row)" :title="$t('edit')">
                                    <i class="fas fa-edit"></i>
                                </el-button>
                                <el-button size="small" type="danger" plain @click="deleteAccount(row.id)" :disabled="row.is_system" :title="$t('delete')">
                                    <i class="fas fa-trash"></i>
                                </el-button>
                            </el-button-group>
                        </template>
                    </el-table-column>
                </el-table>

                <!-- Empty State -->
                <div v-if="!store.accounts.length" class="empty-state-box">
                    <i class="fas fa-sitemap empty-icon"></i>
                    <p>{{ $t('no_accounts_in_chart') }}</p>
                    <el-button type="primary" size="medium" @click="openCreateDrawer">
                        <i class="fas fa-plus"></i> {{ $t('add_account') }}
                    </el-button>
                </div>
            </div>
        </el-card>

        <!-- Create / Edit Account Drawer -->
        <el-drawer
            v-model="formDrawerVisible"
            :title="isEditMode ? 'تعديل حساب محاسبي' : 'إضافة حساب جديد لدليل الحسابات'"
            size="40%"
            direction="rtl"
            destroy-on-close
            class="form-drawer"
        >
            <el-form :model="form" label-position="top">
                <el-form-item :label="$t('account_code')" required>
                    <el-input v-model="form.code" :placeholder="$t('account_code_example')" :disabled="isEditMode" />
                </el-form-item>

                <el-form-item :label="$t('account_name')" required>
                    <el-input v-model="form.name" :placeholder="$t('account_name_example')" />
                </el-form-item>

                <el-form-item :label="$t('main_account_type')" required>
                    <el-select v-model="form.type" :placeholder="$t('choose_account_type')" style="width: 100%">
                        <el-option :label="$t('type_asset')" value="Asset" />
                        <el-option :label="$t('type_liability')" value="Liability" />
                        <el-option :label="$t('type_equity')" value="Equity" />
                        <el-option :label="$t('type_revenue')" value="Revenue" />
                        <el-option :label="$t('type_expense')" value="Expense" />
                    </el-select>
                </el-form-item>

                <el-form-item :label="$t('opening_balance')" v-if="!isEditMode">
                    <el-input v-model="form.balance" type="number" placeholder="0.00" style="width: 100%">
                        <template #suffix>$</template>
                    </el-input>
                </el-form-item>

                <el-form-item :label="$t('activity_status')" required>
                    <el-select v-model="form.is_active" style="width: 100%">
                        <el-option :label="$t('active')" :value="true" />
                        <el-option :label="$t('inactive')" :value="false" />
                    </el-select>
                </el-form-item>

                <el-form-item :label="$t('account_description')">
                    <el-input v-model="form.description" type="textarea" :rows="3" :placeholder="$t('account_notes_placeholder')" />
                </el-form-item>

                <div style="border-top: 1px solid var(--border-color); margin-top: 2rem; padding-top: 1.5rem; display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <el-button @click="formDrawerVisible = false">{{ $t('cancel') }}</el-button>
                    <el-button type="primary" :loading="submittingForm" @click="saveAccount">{{ $t('save_account') }}</el-button>
                </div>
            </el-form>
        </el-drawer>

        <!-- Statement / Transactions History Drawer -->
        <el-drawer
            v-model="statementDrawerVisible"
            :title="$t('detailed_account_statement')"
            size="55%"
            direction="rtl"
            destroy-on-close
            class="detail-drawer"
        >
            <div v-if="loadingStatement" v-loading="loadingStatement" style="min-height: 250px;"></div>
            <div v-else-if="selectedAccount" class="drawer-detail-content">
                <!-- Account header metrics -->
                <div class="mb-4" style="background: var(--bg-light); border: 1px solid var(--border-color); padding: 1.5rem; border-radius: var(--radius-md); display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <span class="code-badge" style="font-size: 1rem; padding: 0.25rem 0.75rem; margin-bottom: 0.5rem; display: inline-block;">{{ selectedAccount.code }}</span>
                        <h3 style="margin: 0; font-size: 1.3rem; font-weight: 700; color: var(--text-dark);">{{ selectedAccount.name }}</h3>
                        <p style="margin: 0.25rem 0 0 0; color: var(--text-muted); font-size: 0.9rem;">{{ $t('account_type_label') }} <strong>{{ getArabicType(selectedAccount.type) }}</strong></p>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-size: 0.85rem; color: var(--text-muted); display: block; margin-bottom: 0.25rem;">{{ $t('current_book_balance') }}</span>
                        <h2 :class="parseFloat(selectedAccount.balance) >= 0 ? 'text-success' : 'text-danger'" style="margin: 0; font-size: 1.8rem; font-weight: 800;">
                            ${{ parseFloat(selectedAccount.balance || 0).toFixed(2) }}
                        </h2>
                    </div>
                </div>

                <!-- Period, opening balance, and whether the rows land on the
                     account's stored balance. -->
                <div class="statement-controls mb-4">
                    <el-date-picker
                        v-model="statementRange"
                        type="daterange"
                        format="YYYY-MM-DD"
                        value-format="YYYY-MM-DD"
                        :start-placeholder="$t('period_from')"
                        :end-placeholder="$t('to')"
                        @change="reloadStatement"
                    />
                    <div v-if="statement" class="statement-summary">
                        <span>{{ $t('opening_balance') }}: <strong>{{ money(statement.opening_balance) }}</strong></span>
                        <span>{{ $t('closing_balance') }}: <strong>{{ money(statement.closing_balance) }}</strong></span>
                    </div>
                </div>

                <el-alert
                    v-if="statement && !statement.matches_stored_balance"
                    type="warning"
                    show-icon
                    :closable="false"
                    class="mb-4"
                    :title="$t('statement_does_not_match_stored_balance')"
                />

                <!-- Ledger Statement Table -->
                <el-table :data="statementEntriesWithBalance" style="width: 100%" stripe size="small">
                    <el-table-column prop="entry_date" :label="$t('date')" width="110" align="center" />
                    <el-table-column prop="description" :label="$t('narration')" min-width="180" />
                    <el-table-column prop="entry_number" :label="$t('entry_number')" width="120" show-overflow-tooltip />
                    <el-table-column prop="debit" :label="$t('debit_label')" width="110" align="right">
                        <template #default="{ row }">
                            <span v-if="row.debit > 0" class="text-success">${{ parseFloat(row.debit).toFixed(2) }}</span>
                            <span v-else>-</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="credit" :label="$t('credit_label')" width="110" align="right">
                        <template #default="{ row }">
                            <span v-if="row.credit > 0" class="text-warning">${{ parseFloat(row.credit).toFixed(2) }}</span>
                            <span v-else>-</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="running_balance" :label="$t('running_balance')" width="130" align="right">
                        <template #default="{ row }">
                            <strong :class="row.running_balance >= 0 ? 'text-success' : 'text-danger'">
                                ${{ parseFloat(row.running_balance).toFixed(2) }}
                            </strong>
                        </template>
                    </el-table-column>
                </el-table>

                <div v-if="!statementEntries.length" class="empty-state" style="padding: 3rem 0; text-align: center; color: var(--text-muted);">
                    <i class="fas fa-file-invoice" style="font-size: 2.5rem; opacity: 0.3; margin-bottom: 1rem; display: block;"></i>
                    {{ $t('no_movements_on_account') }}
                </div>
            </div>
        </el-drawer>
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import { ref, onMounted, computed, reactive } from 'vue';
import { useLedgerAccountsStore } from '@/stores/ledgerAccounts';
import { ledgerAccountsApi } from '@/api/ledgerAccounts';
import { accountingReportsApi } from '@/api/accountingReports';
import { ElMessage } from 'element-plus';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';

const { t } = useI18n();

const store = useLedgerAccountsStore();

// Type counts
const checkType = (type, target) => {
    const val = String(type || '').toLowerCase();
    const tgt = String(target || '').toLowerCase();
    return val === tgt || val === tgt + 's' || val + 's' === tgt;
};

const assetsCount = computed(() => store.accounts.filter(a => checkType(a.type, 'asset')).length);
const liabilitiesCount = computed(() => store.accounts.filter(a => checkType(a.type, 'liability')).length);
const revenueCount = computed(() => store.accounts.filter(a => checkType(a.type, 'revenue')).length);
const expenseCount = computed(() => store.accounts.filter(a => checkType(a.type, 'expense')).length);

const typeTagType = (type) => {
    const val = String(type || '').toLowerCase();
    if (val.includes('asset')) return 'success';
    if (val.includes('liability')) return 'warning';
    if (val.includes('equity')) return 'danger';
    if (val.includes('revenue')) return 'info';
    return 'info';
};

const getArabicType = (type) => {
    const val = String(type || '').toLowerCase();
    if (val.includes('asset')) return t('type_asset');
    if (val.includes('liability')) return t('liability_short');
    if (val.includes('equity')) return t('equity_short');
    if (val.includes('revenue')) return t('type_revenue');
    if (val.includes('expense')) return t('type_expense');
    return type;
};

// Form state
const formDrawerVisible = ref(false);
const isEditMode = ref(false);
const submittingForm = ref(false);
const editingAccountId = ref(null);
const form = reactive({
    code: '',
    name: '',
    type: 'Asset',
    balance: 0,
    is_active: true,
    description: ''
});

// Statement drawer state
const statementDrawerVisible = ref(false);
const loadingStatement = ref(false);
const selectedAccount = ref(null);
const statementEntries = ref([]);
// The whole server answer: opening balance, totals, and whether the rows land
// on the account's stored balance.
const statement = ref(null);
// Empty means "the report's default window"; the server decides that, so the
// screen does not have to invent a period of its own.
const statementRange = ref([]);

const resetForm = () => {
    form.code = '';
    form.name = '';
    form.type = 'Asset';
    form.balance = 0;
    form.is_active = true;
    form.description = '';
};

const openCreateDrawer = () => {
    isEditMode.value = false;
    resetForm();
    formDrawerVisible.value = true;
};

const openEditDrawer = (account) => {
    isEditMode.value = true;
    editingAccountId.value = account.id;
    formDrawerVisible.value = true;
    resetForm();
    form.code = account.code;
    form.name = account.name;
    form.type = account.type;
    form.balance = account.balance;
    form.is_active = account.is_active;
    form.description = account.description;
};

const saveAccount = async () => {
    if (!form.code.trim() || !form.name.trim()) {
        ElMessage.warning(t('account_code_and_name_required'));
        return;
    }

    submittingForm.value = true;
    try {
        if (isEditMode.value) {
            await ledgerAccountsApi.update(editingAccountId.value, form);
            ElMessage.success(t('account_updated'));
        } else {
            await ledgerAccountsApi.create(form);
            ElMessage.success(t('account_saved'));
        }
        formDrawerVisible.value = false;
        await store.fetchAccounts({ per_page: 100 });
    } catch (e) {
        ElMessage.error(t('failed_to_save_account'));
    } finally {
        submittingForm.value = false;
    }
};

const deleteAccount = async (id) => {
    if (confirm(t('confirm_delete_account'))) {
        try {
            await ledgerAccountsApi.delete(id);
            ElMessage.success(t('account_deleted'));
            await store.fetchAccounts({ per_page: 100 });
        } catch (e) {
            ElMessage.error(t('failed_to_delete_account'));
        }
    }
};

/**
 * Loads the account's statement for the chosen period.
 *
 * This used to be assembled here in the browser: the last 200 journal entries
 * for the account, flattened to its own lines, with a running total started
 * from zero. The period was ignored, anything past the 200th entry was
 * dropped, and a total that begins at zero only matches the account when
 * nothing happened before the first row — so the statement kept disagreeing
 * with the balance printed above it. The server now answers with the opening
 * balance and a balance per row, and says whether the two agree.
 */
const openStatementDrawer = async (account) => {
    statementDrawerVisible.value = true;
    selectedAccount.value = account;
    loadingStatement.value = true;
    statementEntries.value = [];
    statement.value = null;

    try {
        const res = await accountingReportsApi.accountStatement({
            account_id: account.id,
            date_from: statementRange.value?.[0] || undefined,
            date_to: statementRange.value?.[1] || undefined,
        });

        statement.value = res.data?.data || null;
        statementEntries.value = statement.value?.movements || [];
    } catch (e) {
        ElMessage.error(t('failed_to_load_account_movements'));
    } finally {
        loadingStatement.value = false;
    }
};

const reloadStatement = () => {
    if (selectedAccount.value) openStatementDrawer(selectedAccount.value);
};

const money = (value) => Number(value || 0).toFixed(2);

// Newest first for reading; the balance on each row comes from the server,
// where it was accumulated oldest-first on top of the opening figure.
const statementEntriesWithBalance = computed(() =>
    [...statementEntries.value].reverse().map((entry) => ({
        ...entry,
        running_balance: entry.balance,
    }))
);

onMounted(() => {
    store.fetchAccounts({ per_page: 100 }).catch(() => {});
});
</script>

<style scoped>
.accounting-page {
    font-family: 'Cairo', sans-serif;
}

.statement-controls {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.statement-summary {
    display: flex;
    gap: 1.25rem;
    color: var(--text-muted);
    font-size: 0.9rem;
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

.type-stat-card {
    border-radius: var(--radius-md);
    text-align: center;
}

.type-stat-inner {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.type-stat-inner .label {
    font-size: 0.85rem;
    font-weight: 600;
}

.type-stat-inner .count-val {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--text-dark);
}

.code-badge {
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    color: #475569;
    padding: 0.15rem 0.5rem;
    border-radius: var(--radius-sm);
    font-weight: 700;
    font-family: monospace;
    font-size: 0.85rem;
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

/* Detail Drawer */
.drawer-detail-content {
    padding: 1.5rem;
    font-family: 'Cairo', sans-serif;
}
</style>
