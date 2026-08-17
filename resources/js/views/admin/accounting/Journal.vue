<template>
    <div class="accounting-page accounting-journal">
        <!-- Page Header -->
        <AdminPageHeader
            icon="fas fa-book text-primary"
            :title="$t('journal')"
            :subtitle="$t('journal_subtitle')"
        >
            <template #actions>
                <el-button type="primary" class="create-btn" @click="openCreateDrawer">
                    <i class="fas fa-plus"></i> {{ $t('record_manual_entry') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <!-- Says up front why there is no edit button, instead of leaving the
             answer to be discovered when the API refuses. -->
        <el-alert
            type="info"
            show-icon
            :closable="false"
            class="mb-4"
            :title="$t('posted_entry_is_final')"
        />

        <!-- Filters Bar Panel -->
        <el-card shadow="hover" class="filters-panel mb-4">
            <div class="filters-row">
                <div class="filter-item">
                    <label>{{ $t('ledger_account') }}</label>
                    <el-select v-model="filters.ledger_account_id" :placeholder="$t('all')" clearable style="width: 220px;" filterable @change="applyFilters">
                        <el-option
                            v-for="acc in ledgerStore.accounts"
                            :key="acc.id"
                            :label="acc.code + ' - ' + acc.name"
                            :value="acc.id"
                        />
                    </el-select>
                </div>
                <div class="filter-item">
                    <label>{{ $t('period_from') }}</label>
                    <el-date-picker v-model="filters.date_from" type="date" :placeholder="$t('date_from')" format="YYYY-MM-DD" value-format="YYYY-MM-DD" style="width: 160px;" @change="applyFilters" />
                </div>
                <div class="filter-item">
                    <label>{{ $t('to') }}</label>
                    <el-date-picker v-model="filters.date_to" type="date" :placeholder="$t('date_to')" format="YYYY-MM-DD" value-format="YYYY-MM-DD" style="width: 160px;" @change="applyFilters" />
                </div>
                <el-button type="info" plain @click="resetFilters" style="margin-top: 1.25rem;">{{ $t('reset') }}</el-button>
            </div>
        </el-card>

        <!-- Main Card & Entries Table -->
        <el-card shadow="hover" class="table-panel">
            <template #header>
                <div class="card-header">
                    <span><i class="fas fa-list-alt text-muted"></i> {{ $t('journal_entries') }}</span>
                </div>
            </template>

            <div v-if="store.loading" class="loading-state">
                <el-skeleton :rows="6" animated />
            </div>
            <div v-else>
                <el-table
                    v-if="store.entries.length"
                    :data="store.entries"
                    style="width: 100%"
                    stripe
                    highlight-current-row
                    class="custom-table"
                >
                    <el-table-column prop="entry_number" :label="$t('entry_number')" width="120" align="center">
                        <template #default="{ row }">
                            <span class="code-badge">{{ row.entry_number }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="entry_date" :label="$t('the_date')" width="120" align="center">
                        <template #default="{ row }">{{ formatDate(row.entry_date) }}</template>
                    </el-table-column>
                    <el-table-column prop="description" :label="$t('description')" min-width="220" show-overflow-tooltip />
                    <el-table-column :label="$t('lines_count')" width="110" align="center">
                        <template #default="{ row }">{{ row.lines?.length || 0 }}</template>
                    </el-table-column>
                    <el-table-column prop="total_debit" :label="$t('debtor')" width="150" align="right">
                        <template #default="{ row }">
                            <strong class="text-success">${{ parseFloat(row.total_debit).toFixed(2) }}</strong>
                        </template>
                    </el-table-column>
                    <el-table-column prop="total_credit" :label="$t('creditor')" width="150" align="right">
                        <template #default="{ row }">
                            <strong class="text-warning">${{ parseFloat(row.total_credit).toFixed(2) }}</strong>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('balance_status')" width="100" align="center">
                        <template #default="{ row }">
                            <el-tag :type="isRowBalanced(row) ? 'success' : 'danger'" effect="light">
                                {{ isRowBalanced(row) ? '✓ متوازن' : '✗ غير متوازن' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('status')" width="100" align="center">
                        <template #default="{ row }">
                            <el-tag :type="row.status === 'posted' ? 'success' : 'info'" effect="plain">{{ statusLabel(row.status) }}</el-tag>
                        </template>
                    </el-table-column>
                    <!-- Reversal is the only action: a posted entry stays as it
                         was recorded, and a correction is a second entry beside
                         it rather than an edit of it. -->
                    <el-table-column :label="$t('actions')" width="150" align="center" fixed="left">
                        <template #default="{ row }">
                            <el-tooltip :content="row.status === 'reversed' ? $t('reversed') : $t('reverse_entry')" placement="top">
                                <span>
                                    <el-button
                                        size="small"
                                        type="warning"
                                        plain
                                        :disabled="row.status === 'reversed'"
                                        @click="confirmReverse(row)"
                                    >
                                        <i class="fas fa-rotate-left"></i>
                                    </el-button>
                                </span>
                            </el-tooltip>
                        </template>
                    </el-table-column>
                </el-table>

                <!-- Empty State -->
                <div v-if="!store.entries.length" class="empty-state-box">
                    <i class="fas fa-book empty-icon"></i>
                    <p>{{ $t('no_matching_journal_entries') }}</p>
                    <el-button type="primary" size="medium" @click="openCreateDrawer">
                        <i class="fas fa-plus"></i> {{ $t('record_manual_entry') }}
                    </el-button>
                </div>
            </div>
        </el-card>

        <!-- Create Journal Entry Drawer -->
        <el-drawer
            v-model="drawerVisible"
            title="تسجيل قيد محاسبي يدوي جديد"
            size="55%"
            direction="rtl"
            destroy-on-close
            class="form-drawer"
        >
            <el-form :model="form" label-position="top">
                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-form-item :label="$t('entry_date')" required>
                            <el-date-picker v-model="form.entry_date" type="date" :placeholder="$t('choose_the_date')" format="YYYY-MM-DD" value-format="YYYY-MM-DD" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="$t('transaction_reference_optional')">
                            <el-input v-model="form.reference" :placeholder="$t('journal_reference_example')" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-form-item :label="$t('detailed_narration')" required>
                    <el-input v-model="form.description" type="textarea" :rows="2" :placeholder="$t('narration_placeholder')" />
                </el-form-item>

                <div class="lines-section">
                    <div class="lines-header">
                        <span>{{ $t('entry_lines_debit_credit') }}</span>
                        <el-button size="small" type="primary" plain @click="addLine"><i class="fas fa-plus"></i> {{ $t('add_line') }}</el-button>
                    </div>

                    <div v-for="(line, index) in form.lines" :key="index" class="line-row">
                        <el-select v-model="line.ledger_account_id" :placeholder="$t('the_account')" filterable style="flex: 2;">
                            <el-option
                                v-for="acc in ledgerStore.accounts"
                                :key="acc.id"
                                :label="acc.code + ' - ' + acc.name"
                                :value="acc.id"
                            />
                        </el-select>
                        <el-input v-model="line.debit" type="number" :placeholder="$t('debtor')" style="flex: 1;" @input="line.credit = 0">
                            <template #prefix>{{ $t('debtor') }}</template>
                        </el-input>
                        <el-input v-model="line.credit" type="number" :placeholder="$t('creditor')" style="flex: 1;" @input="line.debit = 0">
                            <template #prefix>{{ $t('creditor') }}</template>
                        </el-input>
                        <el-button
                            size="small"
                            type="danger"
                            circle
                            :disabled="form.lines.length <= 2"
                            @click="removeLine(index)"
                        >
                            <i class="fas fa-times"></i>
                        </el-button>
                    </div>

                    <div class="totals-row" :class="isFormBalanced ? 'balanced' : 'unbalanced'">
                        <span>{{ $t('total_debit') }} <strong>${{ formTotalDebit.toFixed(2) }}</strong></span>
                        <span>{{ $t('total_credit') }} <strong>${{ formTotalCredit.toFixed(2) }}</strong></span>
                        <span>{{ isFormBalanced ? '✓ متوازن' : '✗ غير متوازن' }}</span>
                    </div>
                </div>

                <div style="border-top: 1px solid var(--border-color); margin-top: 1.5rem; padding-top: 1.5rem; display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <el-button @click="drawerVisible = false">{{ $t('cancel') }}</el-button>
                    <el-button type="primary" :loading="submittingForm" :disabled="!canSubmit" @click="saveEntry">
                        تسجيل وإثبات القيد
                    </el-button>
                </div>
            </el-form>
        </el-drawer>
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import { ref, onMounted, reactive, computed } from 'vue';
import { useJournalEntriesStore } from '@/stores/journalEntries';
import { useLedgerAccountsStore } from '@/stores/ledgerAccounts';
import { ElMessage, ElMessageBox } from 'element-plus';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';

const { t } = useI18n();

const store = useJournalEntriesStore();
const ledgerStore = useLedgerAccountsStore();

// Filters state
const filters = reactive({
    ledger_account_id: '',
    date_from: '',
    date_to: ''
});

// Form state
const drawerVisible = ref(false);
const submittingForm = ref(false);
const form = reactive({
    entry_date: '',
    reference: '',
    description: '',
    lines: [],
});

const emptyLine = () => ({ ledger_account_id: '', debit: 0, credit: 0 });

const resetForm = () => {
    form.entry_date = new Date().toISOString().split('T')[0];
    form.reference = '';
    form.description = '';
    form.lines = [emptyLine(), emptyLine()];
};

const addLine = () => form.lines.push(emptyLine());
const removeLine = (index) => {
    if (form.lines.length > 2) form.lines.splice(index, 1);
};

const formTotalDebit = computed(() => form.lines.reduce((sum, l) => sum + (parseFloat(l.debit) || 0), 0));
const formTotalCredit = computed(() => form.lines.reduce((sum, l) => sum + (parseFloat(l.credit) || 0), 0));
const isFormBalanced = computed(() => form.lines.length > 0 && formTotalDebit.value.toFixed(2) === formTotalCredit.value.toFixed(2) && formTotalDebit.value > 0);
const canSubmit = computed(() => isFormBalanced.value && form.lines.every((l) => l.ledger_account_id));

const isRowBalanced = (row) => parseFloat(row.total_debit).toFixed(2) === parseFloat(row.total_credit).toFixed(2);

const statusLabel = (status) => {
    if (status === 'posted') return t('posted');
    if (status === 'reversed') return t('reversed');
    return status;
};

const formatDate = (date) => (date ? String(date).slice(0, 10) : '');

const applyFilters = () => {
    const params = {};
    if (filters.ledger_account_id) params.ledger_account_id = filters.ledger_account_id;
    if (filters.date_from) params.date_from = filters.date_from;
    if (filters.date_to) params.date_to = filters.date_to;
    store.fetchEntries(params).catch(() => {});
};

const resetFilters = () => {
    filters.ledger_account_id = '';
    filters.date_from = '';
    filters.date_to = '';
    store.fetchEntries().catch(() => {});
};

const openCreateDrawer = () => {
    resetForm();
    drawerVisible.value = true;
};

const saveEntry = async () => {
    if (!form.description.trim()) {
        ElMessage.warning(t('narration_required'));
        return;
    }
    if (!canSubmit.value) {
        ElMessage.warning(t('entry_must_balance'));
        return;
    }

    const payload = {
        entry_date: form.entry_date,
        reference: form.reference || null,
        description: form.description,
        lines: form.lines.map((l) => ({
            ledger_account_id: l.ledger_account_id,
            debit: parseFloat(l.debit) || 0,
            credit: parseFloat(l.credit) || 0,
        })),
    };

    submittingForm.value = true;
    try {
        await store.createEntry(payload);
        ElMessage.success(t('entry_recorded_and_posted'));
        drawerVisible.value = false;
        await store.fetchEntries();
        await ledgerStore.fetchAccounts({ per_page: 100 });
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('failed_to_save_entry'));
    } finally {
        submittingForm.value = false;
    }
};

const confirmReverse = (row) => {
    ElMessageBox.confirm(
        `${t('confirm_reverse_entry')}\n${row.entry_number}`,
        t('reverse_entry'),
        { confirmButtonText: t('confirm'), cancelButtonText: t('cancel'), type: 'warning' }
    ).then(async () => {
        try {
            await store.reverseEntry(row.id);
            ElMessage.success(t('entry_reversed'));
            await store.fetchEntries();
            await ledgerStore.fetchAccounts({ per_page: 100 });
        } catch (e) {
            ElMessage.error(e.response?.data?.message || t('failed_to_reverse_entry'));
        }
    }).catch(() => {});
};

onMounted(() => {
    store.fetchEntries().catch(() => {});
    ledgerStore.fetchAccounts({ per_page: 100 }).catch(() => {});
});
</script>

<style scoped>
.accounting-page {
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

.totals-row {
    display: flex;
    justify-content: space-between;
    padding: 0.75rem 1rem;
    border-radius: var(--radius-sm);
    margin-top: 0.75rem;
    font-weight: 600;
}

.totals-row.balanced {
    background: #ecfdf5;
    color: #065f46;
}

.totals-row.unbalanced {
    background: #fef2f2;
    color: #991b1b;
}
</style>
