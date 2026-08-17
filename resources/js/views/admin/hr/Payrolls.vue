<template>
    <div class="hr-page hr-payrolls">
        <div class="page-header">
            <div class="page-title">
                <h1>{{ $t('salary_marches') }}</h1>
                <p>{{ $t('comprehensive_display_of_benefits_status') }}</p>
            </div>
            <el-input v-model="searchQuery" :placeholder="$t('search_by_job_number_or_employee_name')" clearable class="search-input" />
        </div>

        <AdminStatGrid>
            <el-card shadow="hover" class="summary-card">
                <p>{{ $t('total_marches') }}</p>
                <h3>{{ store.payrolls.length }}</h3>
            </el-card>
            <el-card shadow="hover" class="summary-card">
                <p>{{ $t('completed_marches') }}</p>
                <h3>{{ completedCount }}</h3>
            </el-card>
            <el-card shadow="hover" class="summary-card">
                <p>{{ $t('hanging_marches') }}</p>
                <h3>{{ pendingCount }}</h3>
            </el-card>
        </AdminStatGrid>

        <el-card shadow="hover" class="table-panel">
            <template #header>
                <div class="card-header">
                    <span>{{ $t('employee_payroll_list') }}</span>
                </div>
            </template>

            <div v-if="store.loading" class="loading-state">{{ $t('loading') }}</div>
            <div v-else>
                <el-table v-if="filteredPayrolls.length" :data="filteredPayrolls" style="width:100%" stripe highlight-current-row>
                    <el-table-column prop="payroll_number" label="#" width="140" />
                    <el-table-column prop="employee.name" :label="$t('employee')" />
                    <el-table-column prop="net_salary" :label="$t('net')" width="140" />
                    <el-table-column :label="$t('status')" width="140">
                        <template #default="{ row }">
                            <el-tag :type="statusTagType(row.status)">{{ statusLabel(row.status) }}</el-tag>
                        </template>
                    </el-table-column>

                    <!-- The two status changes that reach the ledger: one
                         recognises the wage as a cost of the period worked, the
                         other settles what it left owing. -->
                    <el-table-column :label="$t('actions')" width="220" align="center">
                        <template #default="{ row }">
                            <el-button
                                v-if="normalizeStatus(row.status) === 'pending'"
                                size="small"
                                type="primary"
                                plain
                                :loading="savingId === row.id"
                                @click="process(row)"
                            >
                                {{ $t('accrue_payroll') }}
                            </el-button>
                            <!-- Only once the cost is accrued: paying a wage the
                                 books have not yet recognised would settle a
                                 liability that was never raised. -->
                            <el-button
                                v-if="normalizeStatus(row.status) === 'processed'"
                                size="small"
                                type="success"
                                plain
                                @click="openPayDialog(row)"
                            >
                                {{ $t('pay_salary') }}
                            </el-button>
                            <span v-if="normalizeStatus(row.status) === 'paid'" class="muted">—</span>
                        </template>
                    </el-table-column>
                </el-table>
                <div v-if="!filteredPayrolls.length" class="empty-state">{{ $t('there_are_no_paths_matching_your_search') }}</div>
            </div>
        </el-card>

        <el-dialog v-model="payDialogVisible" :title="$t('pay_salary')" width="420px" destroy-on-close>
            <el-form label-position="top">
                <el-form-item :label="$t('payment_date')">
                    <el-date-picker
                        v-model="payForm.payment_date"
                        type="date"
                        format="YYYY-MM-DD"
                        value-format="YYYY-MM-DD"
                        style="width:100%"
                    />
                </el-form-item>
                <!-- Decides which account the money leaves: crediting the bank
                     for a wage handed over in cash overstates the till. -->
                <el-form-item :label="$t('payment_method')">
                    <el-select v-model="payForm.payment_method" style="width:100%">
                        <el-option :label="$t('cash')" value="cash" />
                        <el-option :label="$t('bank_transfer')" value="bank_transfer" />
                        <el-option :label="$t('cheque')" value="check" />
                    </el-select>
                </el-form-item>
            </el-form>

            <template #footer>
                <el-button @click="payDialogVisible = false">{{ $t('cancel') }}</el-button>
                <el-button type="primary" :loading="paying" @click="confirmPay">
                    {{ $t('save') }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { usePayrollsStore } from '@/stores/payrolls';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';

const { t } = useI18n();
const store = usePayrollsStore();
const searchQuery = ref('');
const savingId = ref(null);
const paying = ref(false);
const payDialogVisible = ref(false);
const payForm = reactive({
    id: null,
    payment_date: new Date().toISOString().slice(0, 10),
    payment_method: 'cash',
});

const normalizeStatus = (status) => String(status || '').toLowerCase();

const statusLabel = (status) => {
    const value = normalizeStatus(status);
    if (value === 'pending') return t('pending');
    if (value === 'processed') return t('accrued');
    if (value === 'paid') return t('paid');
    return status || t('undefined');
};

/**
 * Sends the payroll to `processed`, which is what recognises the wage as a
 * cost of the period it was earned in.
 */
const process = async (payroll) => {
    savingId.value = payroll.id;
    try {
        await store.updatePayroll(payroll.id, {
            employee_id: payroll.employee_id,
            pay_period_start: String(payroll.pay_period_start || '').slice(0, 10),
            pay_period_end: String(payroll.pay_period_end || '').slice(0, 10),
            basic_salary: Number(payroll.basic_salary || 0),
            overtime_hours: Number(payroll.overtime_hours || 0),
            overtime_rate: Number(payroll.overtime_rate || 0),
            bonuses: Number(payroll.bonuses || 0),
            deductions: Number(payroll.deductions || 0),
            status: 'processed',
        });
        ElMessage.success(t('payroll_accrued'));
        await store.fetchPayrolls();
    } catch (error) {
        ElMessage.error(error.response?.data?.message || t('failed_to_update_payroll'));
    } finally {
        savingId.value = null;
    }
};

const openPayDialog = (payroll) => {
    payForm.id = payroll.id;
    payForm.payment_date = new Date().toISOString().slice(0, 10);
    payForm.payment_method = payroll.payment_method || 'cash';
    payDialogVisible.value = true;
};

const confirmPay = async () => {
    paying.value = true;
    try {
        // Only the lifecycle fields: the server refuses to re-open the figures
        // of a payroll whose cost is already on the books.
        await store.updatePayroll(payForm.id, {
            status: 'paid',
            payment_date: payForm.payment_date,
            payment_method: payForm.payment_method,
        });
        payDialogVisible.value = false;
        ElMessage.success(t('payroll_paid'));
        await store.fetchPayrolls();
    } catch (error) {
        ElMessage.error(error.response?.data?.message || t('failed_to_update_payroll'));
    } finally {
        paying.value = false;
    }
};

const statusTagType = (status) => {
    const value = normalizeStatus(status);
    if (['completed', window.t('complete'), 'paid'].includes(value)) return 'success';
    if (['pending', window.t('hanging'), window.t('in_process')].includes(value)) return 'warning';
    if (['cancelled', window.t('canceled'), window.t('canceled')].includes(value)) return 'danger';
    return 'info';
};

const filteredPayrolls = computed(() => {
    if (!searchQuery.value.trim()) return store.payrolls;
    const query = searchQuery.value.toLowerCase();
    return store.payrolls.filter((payroll) => {
        return [
            payroll.payroll_number,
            payroll.employee?.name,
            payroll.net_salary,
            payroll.status
        ].some((field) => String(field || '').toLowerCase().includes(query));
    });
});

const completedCount = computed(() => store.payrolls.filter((payroll) => {
    const value = normalizeStatus(payroll.status);
    return ['completed', window.t('complete'), 'paid'].includes(value);
}).length);

const pendingCount = computed(() => store.payrolls.filter((payroll) => {
    const value = normalizeStatus(payroll.status);
    return ['pending', window.t('hanging'), window.t('in_process')].includes(value);
}).length);

onMounted(() => {
    store.fetchPayrolls().catch(() => {});
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

.search-input {
    width: min(100%, 320px);
}

.overview-cards {
    margin-bottom: 1.5rem;
}

.summary-card {
    min-height: 110px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 0.4rem;
    border-radius: 1rem;
}

.summary-card p {
    margin: 0;
    color: #6b7c98;
    font-size: 0.95rem;
}

.summary-card h3 {
    margin: 0;
    font-size: 2rem;
    color: #253358;
}

.table-panel {
    border-radius: 1rem;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.loading-state,
.empty-state {
    padding: 1.25rem;
    text-align: center;
    color: #6b7c98;
}
</style>
