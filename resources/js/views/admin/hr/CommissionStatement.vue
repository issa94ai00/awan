<template>
    <div class="hr-page hr-commissions">
        <div class="page-header">
            <div class="page-title">
                <h1>{{ $t('commission_statement') }}</h1>
                <p>{{ $t('commission_statement_subtitle') }}</p>
            </div>
            <div class="page-actions">
                <el-button v-if="authStore.user?.is_admin" plain @click="trashDialogVisible = true">
                    {{ $t('trashed_records') }}
                </el-button>
                <el-button type="primary" @click="openCreateDialog">
                    {{ $t('add_commission_record') }}
                </el-button>
            </div>
        </div>

        <div class="filters-bar">
            <el-select
                v-model="filters.employee_id"
                :placeholder="$t('select_employee')"
                clearable
                filterable
                class="filter-input"
                @change="load"
            >
                <el-option
                    v-for="employee in employeesStore.employees"
                    :key="employee.id"
                    :label="employee.name"
                    :value="employee.id"
                />
            </el-select>
            <el-select
                v-model="filters.year"
                :placeholder="$t('year')"
                clearable
                class="filter-input filter-input-sm"
                @change="load"
            >
                <el-option v-for="year in yearOptions" :key="year" :label="year" :value="year" />
            </el-select>
        </div>

        <el-card shadow="hover" class="table-panel">
            <div v-if="store.loading" class="loading-state">{{ $t('loading') }}</div>
            <div v-else>
                <el-table v-if="store.records.length" :data="store.records" style="width:100%" stripe highlight-current-row>
                    <el-table-column :label="$t('employee')" min-width="160">
                        <template #default="{ row }">{{ row.employee?.name }}</template>
                    </el-table-column>
                    <el-table-column :label="$t('month')" width="110">
                        <template #default="{ row }">{{ formatMonth(row.month) }}</template>
                    </el-table-column>
                    <el-table-column :label="$t('total_sales')" width="130">
                        <template #default="{ row }">{{ formatMoney(row.total_sales) }}</template>
                    </el-table-column>
                    <el-table-column :label="$t('commission_rate')" width="90">
                        <template #default="{ row }">{{ row.commission_rate }}%</template>
                    </el-table-column>
                    <el-table-column :label="$t('commission_amount')" width="130">
                        <template #default="{ row }">{{ formatMoney(row.commission_amount) }}</template>
                    </el-table-column>
                    <el-table-column :label="$t('extra_expenses')" width="120">
                        <template #default="{ row }">{{ formatMoney(row.extra_expenses) }}</template>
                    </el-table-column>
                    <el-table-column :label="$t('withdrawals')" width="120">
                        <template #default="{ row }">{{ formatMoney(row.withdrawals) }}</template>
                    </el-table-column>
                    <el-table-column :label="$t('current_balance')" width="180">
                        <template #default="{ row }">
                            <el-tag :type="balanceTagType(row.balance_status)" effect="dark">
                                {{ formatMoney(Math.abs(row.balance)) }} — {{ balanceLabel(row.balance_status) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('cumulative_balance')" width="130">
                        <template #default="{ row }">{{ formatMoney(row.cumulative_balance) }}</template>
                    </el-table-column>
                    <el-table-column :label="$t('achievement_rate')" width="120">
                        <template #default="{ row }">
                            <span v-if="row.achievement_rate !== null">{{ row.achievement_rate }}%</span>
                            <span v-else class="muted">—</span>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('actions')" width="270" align="center">
                        <template #default="{ row }">
                            <el-button size="small" plain @click="openWithdrawalsDialog(row)">{{ $t('withdrawals') }}</el-button>
                            <el-button size="small" plain @click="openPrintView(row)">{{ $t('print') }}</el-button>
                            <el-button size="small" plain @click="openEditDialog(row)">{{ $t('edit') }}</el-button>
                            <el-button size="small" type="danger" plain @click="confirmDelete(row)">{{ $t('delete') }}</el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <div v-else class="empty-state">{{ $t('no_commission_records_yet') }}</div>
            </div>
        </el-card>

        <div v-if="printRow" class="print-overlay">
            <div class="print-sheet" id="commission-print-content">
                <pre class="print-text" dir="rtl">{{ printText }}</pre>
            </div>
            <div class="print-toolbar no-print">
                <el-button @click="printRow = null">{{ $t('close') }}</el-button>
                <el-button type="primary" @click="triggerPrint">{{ $t('print') }}</el-button>
            </div>
        </div>

        <el-dialog v-model="dialogVisible" :title="dialogTitle" width="480px" destroy-on-close>
            <el-form label-position="top">
                <el-form-item :label="$t('employee')" v-if="!isEditing">
                    <el-select v-model="form.employee_id" filterable style="width:100%" @change="onEmployeeChange">
                        <el-option
                            v-for="employee in employeesStore.employees"
                            :key="employee.id"
                            :label="employee.name"
                            :value="employee.id"
                        />
                    </el-select>
                </el-form-item>
                <el-form-item :label="$t('month')" v-if="!isEditing">
                    <el-date-picker
                        v-model="form.month"
                        type="month"
                        format="YYYY-MM"
                        value-format="YYYY-MM-DD"
                        style="width:100%"
                        @change="refreshSales"
                    />
                </el-form-item>

                <el-form-item :label="$t('total_sales')">
                    <div class="sales-preview">
                        <span>{{ formatMoney(previewSales.total_sales) }}</span>
                        <span class="muted" v-if="previewSales.invoice_count !== null">
                            ({{ previewSales.invoice_count }} {{ $t('invoice_count') }})
                        </span>
                        <el-button size="small" text :loading="recalculating" @click="refreshSales">
                            {{ $t('recalculate_sales') }}
                        </el-button>
                    </div>
                </el-form-item>

                <el-form-item :label="$t('commission_rate')">
                    <el-input-number v-model="form.commission_rate" :min="0" :max="100" :precision="2" style="width:100%" />
                </el-form-item>
                <el-form-item :label="$t('extra_expenses')">
                    <el-input-number v-model="form.extra_expenses" :min="0" :precision="2" style="width:100%" />
                </el-form-item>
                <el-form-item :label="$t('withdrawals')" v-if="isEditing">
                    <div class="sales-preview">
                        <span>{{ formatMoney(form.withdrawals) }}</span>
                        <el-button size="small" text @click="openWithdrawalsDialog({ id: editingId, employee: { name: currentEmployeeName } })">
                            {{ $t('edit') }}
                        </el-button>
                    </div>
                </el-form-item>
                <el-form-item :label="$t('withdrawals')" v-else>
                    <span class="muted">{{ $t('save_first_hint') }}</span>
                </el-form-item>
                <el-form-item :label="$t('monthly_target')">
                    <el-input-number v-model="form.monthly_target" :min="0" :precision="2" style="width:100%" />
                </el-form-item>
                <el-form-item :label="$t('notes')">
                    <el-input v-model="form.notes" type="textarea" :rows="2" />
                </el-form-item>
            </el-form>

            <template #footer>
                <el-button @click="dialogVisible = false">{{ $t('cancel') }}</el-button>
                <el-button type="primary" :loading="saving" @click="save">{{ $t('save') }}</el-button>
            </template>
        </el-dialog>

        <CommissionWithdrawalsDialog
            v-model="withdrawalsDialogVisible"
            :commission-id="withdrawalsCommissionId"
            :employee-name="withdrawalsEmployeeName"
            @changed="load"
        />

        <CommissionTrashDialog v-model="trashDialogVisible" @restored="load" />
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useEmployeeCommissionsStore } from '@/stores/employeeCommissions';
import { useEmployeesStore } from '@/stores/employees';
import { useAuthStore } from '@/stores/auth';
import { useCurrency } from '@/Composables/useCurrency';
import CommissionWithdrawalsDialog from '@/components/admin/hr/CommissionWithdrawalsDialog.vue';
import CommissionTrashDialog from '@/components/admin/hr/CommissionTrashDialog.vue';

const { t } = useI18n();
const store = useEmployeeCommissionsStore();
const employeesStore = useEmployeesStore();
const authStore = useAuthStore();
const { formatMoney } = useCurrency();

const trashDialogVisible = ref(false);

const filters = reactive({ employee_id: null, year: new Date().getFullYear() });
const yearOptions = computed(() => {
    const current = new Date().getFullYear();
    return Array.from({ length: 6 }, (_, i) => current - i);
});

const dialogVisible = ref(false);
const isEditing = ref(false);
const saving = ref(false);
const recalculating = ref(false);
const editingId = ref(null);
const previewSales = reactive({ total_sales: 0, invoice_count: null });

const form = reactive({
    employee_id: null,
    month: new Date().toISOString().slice(0, 8) + '01',
    commission_rate: 0,
    extra_expenses: 0,
    withdrawals: 0,
    monthly_target: null,
    notes: ''
});

const dialogTitle = computed(() => (isEditing.value ? t('edit_commission_record') : t('add_commission_record')));

const currentEmployeeName = computed(() => {
    const employee = employeesStore.employees.find((e) => e.id === form.employee_id);
    return employee?.name || '';
});

const withdrawalsDialogVisible = ref(false);
const withdrawalsCommissionId = ref(null);
const withdrawalsEmployeeName = ref('');

const openWithdrawalsDialog = (row) => {
    withdrawalsCommissionId.value = row.id;
    withdrawalsEmployeeName.value = row.employee?.name || '';
    withdrawalsDialogVisible.value = true;
};

const formatMonth = (value) => String(value || '').slice(0, 7);

const formatDate = (date) => {
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    return `${day}/${month}/${date.getFullYear()}`;
};

const formatDateTimeShort = (value) => String(value || '').replace('T', ' ').slice(0, 16);

const printRow = ref(null);
const printWithdrawals = reactive({ transactions: [], breakdown: [] });

const openPrintView = async (row) => {
    printRow.value = row;
    printWithdrawals.transactions = [];
    printWithdrawals.breakdown = [];
    try {
        const data = await store.fetchWithdrawals(row.id);
        printWithdrawals.transactions = data.transactions || [];
        printWithdrawals.breakdown = data.breakdown || [];
    } catch {
        // The statement still prints with the totals already on the row.
    }
};

const triggerPrint = () => {
    window.print();
};

const printText = computed(() => {
    const row = printRow.value;
    if (!row) return '';

    const [year, month] = String(row.month).slice(0, 7).split('-').map(Number);
    const periodStart = formatDate(new Date(year, month - 1, 1));
    const periodEnd = formatDate(new Date(year, month, 0));

    const box = (checked) => (checked ? '☑' : '☐');
    const status = row.balance_status;

    const divider = '━'.repeat(42);

    const lines = [
        divider,
        `${t('commission_statement')}: ${row.employee?.name || ''}`,
        `${t('period')}: ${periodStart} ${t('to')} ${periodEnd}`,
        divider,
    ];

    if (printWithdrawals.transactions.length) {
        lines.push(`${t('withdrawals')}:`);
        printWithdrawals.transactions.forEach((tx, index) => {
            lines.push(
                `${index + 1}. ${formatDateTimeShort(tx.withdrawn_at)}  `
                + `${formatMoney(tx.amount, { code: tx.currency_code })} → ${formatMoney(tx.base_amount)}  `
                + `(${tx.method === 'bank' ? t('bank_transfer') : t('cash')}${tx.reason ? ' — ' + tx.reason : ''})`
            );
        });

        if (printWithdrawals.breakdown.length > 1) {
            lines.push('', `${t('currency_code')}:`);
            printWithdrawals.breakdown.forEach((entry) => {
                lines.push(`  ${entry.currency_code}: ${formatMoney(entry.total_amount, { code: entry.currency_code })} → ${formatMoney(entry.total_base_amount)}`);
            });
        }

        lines.push(divider);
    }

    lines.push(
        `1. ${t('total_sales')}: ${formatMoney(row.total_sales)}`,
        `2. ${t('commission_rate')}: ${row.commission_rate}%`,
        `3. ${t('commission_amount')}: ${formatMoney(row.commission_amount)}`,
        `4. ${t('total_withdrawals')}: ${formatMoney(row.withdrawals)}`,
        `5. ${t('extra_expenses')}: ${formatMoney(row.extra_expenses)}`,
        `6. ${t('net_due')}: ${formatMoney(row.net_due)}`,
        `7. ${t('final_balance')}: ${formatMoney(Math.abs(row.balance))}`,
        `   ${t('status')}: ${box(status === 'creditor')} ${t('creditor')}  ${box(status === 'debtor')} ${t('debtor')}  ${box(status === 'balanced')} ${t('balanced')}`,
        divider,
        `${t('signatures')}: ${t('employee')}: .........  ${t('role_manager')}: .........  ${t('hr')}: .........`,
        divider
    );

    return lines.join('\n');
});

const balanceLabel = (status) => {
    if (status === 'creditor') return t('creditor');
    if (status === 'debtor') return t('debtor');
    return t('balanced');
};

const balanceTagType = (status) => {
    if (status === 'creditor') return 'success';
    if (status === 'debtor') return 'danger';
    return 'warning';
};

const load = () => {
    store.fetchRecords({
        employee_id: filters.employee_id || undefined,
        year: filters.year || undefined
    }).catch(() => {});
};

const resetForm = () => {
    form.employee_id = null;
    form.month = new Date().toISOString().slice(0, 8) + '01';
    form.commission_rate = 0;
    form.extra_expenses = 0;
    form.withdrawals = 0;
    form.monthly_target = null;
    form.notes = '';
    previewSales.total_sales = 0;
    previewSales.invoice_count = null;
};

const openCreateDialog = () => {
    isEditing.value = false;
    editingId.value = null;
    resetForm();
    dialogVisible.value = true;
};

const openEditDialog = (row) => {
    isEditing.value = true;
    editingId.value = row.id;
    form.employee_id = row.employee_id;
    form.month = row.month;
    form.commission_rate = Number(row.commission_rate);
    form.extra_expenses = Number(row.extra_expenses);
    form.withdrawals = Number(row.withdrawals);
    form.monthly_target = row.monthly_target !== null ? Number(row.monthly_target) : null;
    form.notes = row.notes || '';
    previewSales.total_sales = Number(row.total_sales);
    previewSales.invoice_count = null;
    dialogVisible.value = true;
};

const onEmployeeChange = () => {
    const employee = employeesStore.employees.find((e) => e.id === form.employee_id);
    if (employee) {
        if (employee.commission_rate !== undefined && employee.commission_rate !== null) {
            form.commission_rate = Number(employee.commission_rate);
        }
        if (employee.monthly_sales_target !== undefined && employee.monthly_sales_target !== null) {
            form.monthly_target = Number(employee.monthly_sales_target);
        }
    }
    refreshSales();
};

const refreshSales = async () => {
    if (!form.employee_id || !form.month) return;
    recalculating.value = true;
    try {
        const result = await store.calculateSales({ employee_id: form.employee_id, month: form.month });
        previewSales.total_sales = result.total_sales;
        previewSales.invoice_count = result.invoice_count;
    } catch (error) {
        ElMessage.error(error.response?.data?.message || t('failed_to_save_commission_record'));
    } finally {
        recalculating.value = false;
    }
};

const save = async () => {
    saving.value = true;
    try {
        if (isEditing.value) {
            await store.updateRecord(editingId.value, {
                commission_rate: form.commission_rate,
                extra_expenses: form.extra_expenses,
                monthly_target: form.monthly_target,
                notes: form.notes,
                recalculate_sales: true
            });
        } else {
            if (!form.employee_id) {
                ElMessage.error(t('select_employee'));
                saving.value = false;
                return;
            }
            await store.saveRecord({
                employee_id: form.employee_id,
                month: form.month,
                commission_rate: form.commission_rate,
                extra_expenses: form.extra_expenses,
                monthly_target: form.monthly_target,
                notes: form.notes
            });
        }
        ElMessage.success(t('commission_record_saved'));
        dialogVisible.value = false;
        load();
    } catch (error) {
        ElMessage.error(error.response?.data?.message || t('failed_to_save_commission_record'));
    } finally {
        saving.value = false;
    }
};

const confirmDelete = async (row) => {
    const message = Number(row.withdrawals) > 0
        ? t('confirm_delete_commission_record_with_withdrawals')
        : t('confirm_delete_commission_record');
    try {
        await ElMessageBox.confirm(message, t('warning'), { type: 'warning' });
    } catch {
        return;
    }
    try {
        await store.deleteRecord(row.id);
        ElMessage.success(t('commission_record_deleted'));
        load();
    } catch (error) {
        ElMessage.error(error.response?.data?.message || t('failed_to_delete_commission_record'));
    }
};

onMounted(() => {
    employeesStore.fetchEmployees().catch(() => {});
    load();
});
</script>

<style scoped>
.hr-page {
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
}

.page-title p {
    margin: 0.35rem 0 0;
    color: #5f6d85;
}

.page-actions {
    display: flex;
    gap: 0.75rem;
}

.filters-bar {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.25rem;
    flex-wrap: wrap;
}

.filter-input {
    width: min(100%, 280px);
}

.filter-input-sm {
    width: min(100%, 160px);
}

.table-panel {
    border-radius: 1rem;
}

.loading-state,
.empty-state {
    padding: 1.25rem;
    text-align: center;
    color: #6b7c98;
}

.muted {
    color: #9aa5b8;
}

.sales-preview {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.print-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
    z-index: 3000;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    padding: 1.5rem;
}

.print-sheet {
    background: #fff;
    border-radius: 0.75rem;
    padding: 2rem;
    max-width: 640px;
    width: 100%;
    max-height: 80vh;
    overflow: auto;
    box-shadow: 0 20px 45px rgba(15, 23, 42, 0.35);
}

.print-text {
    margin: 0;
    font-family: 'Courier New', Consolas, monospace;
    font-size: 0.95rem;
    line-height: 1.9;
    white-space: pre-wrap;
    color: #1f2d3d;
    text-align: right;
}

.print-toolbar {
    display: flex;
    gap: 0.75rem;
}
</style>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    #commission-print-content,
    #commission-print-content * {
        visibility: visible;
    }
    #commission-print-content {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        box-shadow: none;
        max-height: none;
    }
    .no-print {
        display: none !important;
    }
}
</style>
