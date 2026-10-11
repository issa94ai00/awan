<template>
    <div class="hr-page hr-payrolls">
        <AdminPageHeader
            :title="$t('salary_marches')"
            :subtitle="$t('comprehensive_display_of_benefits_status')"
        >
            <template #actions>
                <el-button-group>
                    <el-button :icon="Cpu" @click="openAutoGenerateModal">
                        {{ $t('auto_generate_payrolls') }}
                    </el-button>
                    <el-button type="primary" :icon="Plus" @click="openCreatePayrollModal">
                        {{ $t('new_payroll') }}
                    </el-button>
                </el-button-group>
            </template>
        </AdminPageHeader>

        <!-- Stat Cards -->
        <AdminStatGrid class="mb-4">
            <el-card shadow="hover" class="stat-card">
                <span class="stat-title">{{ $t('total_marches') }}</span>
                <h3 class="stat-value text-primary">{{ store.payrolls.length }}</h3>
                <span class="stat-sub">{{ money(totalNetSalaries) }}</span>
            </el-card>

            <el-card shadow="hover" class="stat-card">
                <span class="stat-title">{{ $t('hanging_marches') }}</span>
                <h3 class="stat-value text-warning">{{ pendingCount }}</h3>
                <span class="stat-sub">{{ $t('pending') }}</span>
            </el-card>

            <el-card shadow="hover" class="stat-card">
                <span class="stat-title">{{ $t('accrued') }}</span>
                <h3 class="stat-value text-info">{{ processedCount }}</h3>
                <span class="stat-sub">{{ $t('accrue_payroll') }}</span>
            </el-card>

            <el-card shadow="hover" class="stat-card">
                <span class="stat-title">{{ $t('completed_marches') }}</span>
                <h3 class="stat-value text-success">{{ paidCount }}</h3>
                <span class="stat-sub">{{ $t('paid') }}</span>
            </el-card>
        </AdminStatGrid>

        <!-- Filters Bar -->
        <AdminFilterBar>
            <el-input
                v-model="searchQuery"
                :placeholder="$t('search_by_job_number_or_employee_name')"
                :prefix-icon="Search"
                clearable
                style="max-width: 260px;"
            />

            <el-select
                v-model="selectedStatus"
                :placeholder="$t('status')"
                clearable
                style="min-width: 140px;"
            >
                <el-option :label="$t('pending')" value="pending" />
                <el-option :label="$t('accrued')" value="processed" />
                <el-option :label="$t('paid')" value="paid" />
            </el-select>

            <el-select
                v-model="selectedEmployeeId"
                :placeholder="$t('select_employee')"
                clearable
                filterable
                style="min-width: 180px;"
            >
                <el-option
                    v-for="emp in employeesStore.employees"
                    :key="emp.id"
                    :label="emp.name"
                    :value="emp.id"
                />
            </el-select>

            <el-button type="primary" :icon="Refresh" @click="fetchPayrolls">
                {{ $t('update') }}
            </el-button>
        </AdminFilterBar>

        <!-- Payrolls Table -->
        <el-card shadow="hover" class="table-card">
            <template #header>
                <div class="card-header">
                    <span>{{ $t('employee_payroll_list') }}</span>
                    <span class="record-count">{{ filteredPayrolls.length }} {{ $t('records') || 'مسيرة' }}</span>
                </div>
            </template>

            <div v-if="store.error" class="error-state">
                {{ store.error }}
            </div>

            <el-table
                v-loading="store.loading"
                :data="filteredPayrolls"
                style="width: 100%"
                stripe
                highlight-current-row
            >
                <el-table-column prop="payroll_number" label="#" width="140">
                    <template #default="{ row }">
                        <span class="font-mono font-medium">{{ row.payroll_number }}</span>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('employee')" min-width="160">
                    <template #default="{ row }">
                        <div class="emp-row-info">
                            <span class="emp-name">{{ row.employee?.name || $t('undefined') }}</span>
                            <small class="emp-dept text-muted">{{ row.employee?.department || '' }}</small>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('pay_period')" width="190">
                    <template #default="{ row }">
                        <div class="period-display">
                            <span>{{ formatDate(row.pay_period_start) }}</span>
                            <span class="text-muted">&rarr;</span>
                            <span>{{ formatDate(row.pay_period_end) }}</span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('basic_salary')" width="130" align="right">
                    <template #default="{ row }">
                        <span>{{ money(row.basic_salary) }}</span>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('bonuses')" width="120" align="right">
                    <template #default="{ row }">
                        <span v-if="Number(row.bonuses) > 0" class="text-success">+{{ money(row.bonuses) }}</span>
                        <span v-else class="text-muted">—</span>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('deductions')" width="120" align="right">
                    <template #default="{ row }">
                        <span v-if="Number(row.deductions) > 0" class="text-danger">-{{ money(row.deductions) }}</span>
                        <span v-else class="text-muted">—</span>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('net_salary')" width="140" align="right">
                    <template #default="{ row }">
                        <span class="net-salary-badge">{{ money(row.net_salary) }}</span>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('status')" width="120">
                    <template #default="{ row }">
                        <el-tag :type="statusTagType(row.status)">{{ statusLabel(row.status) }}</el-tag>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('actions')" width="230" fixed="right" align="center">
                    <template #default="{ row }">
                        <el-button-group>
                            <!-- View Payslip -->
                            <el-tooltip :content="$t('payslip')" placement="top">
                                <el-button size="small" :icon="Document" @click="openPayslip(row)" />
                            </el-tooltip>

                            <!-- Accrue / Process -->
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

                            <!-- Pay -->
                            <el-button
                                v-if="normalizeStatus(row.status) === 'processed'"
                                size="small"
                                type="success"
                                plain
                                @click="openPayDialog(row)"
                            >
                                {{ $t('pay_salary') }}
                            </el-button>

                            <!-- Delete only if pending (unaccrued) -->
                            <el-tooltip v-if="normalizeStatus(row.status) === 'pending'" :content="$t('delete')" placement="top">
                                <el-button size="small" type="danger" :icon="Delete" @click="deletePayroll(row)" />
                            </el-tooltip>
                        </el-button-group>
                    </template>
                </el-table-column>
            </el-table>

            <div v-if="!store.loading && !filteredPayrolls.length" class="empty-state">
                {{ $t('there_are_no_paths_matching_your_search') }}
            </div>
        </el-card>

        <!-- Payslip Modal -->
        <el-dialog v-model="payslipVisible" :title="$t('payslip')" width="580px" destroy-on-close>
            <div v-if="activePayslip" id="payslip-printable-area" class="payslip-box">
                <div class="payslip-header">
                    <div class="company-brand">
                        <h2>{{ $t('site_title') || 'مؤسسة أوان التقدم' }}</h2>
                        <span class="text-muted">{{ $t('payslip') }}</span>
                    </div>
                    <div class="payslip-number-tag">
                        <strong>{{ activePayslip.payroll_number }}</strong>
                        <el-tag :type="statusTagType(activePayslip.status)" size="small">
                            {{ statusLabel(activePayslip.status) }}
                        </el-tag>
                    </div>
                </div>

                <el-divider />

                <div class="payslip-emp-grid">
                    <div>
                        <small class="text-muted">{{ $t('employee') }}:</small>
                        <p class="font-bold">{{ activePayslip.employee?.name }}</p>
                    </div>
                    <div>
                        <small class="text-muted">{{ $t('department') }}:</small>
                        <p>{{ activePayslip.employee?.department || '—' }}</p>
                    </div>
                    <div>
                        <small class="text-muted">{{ $t('position') }}:</small>
                        <p>{{ activePayslip.employee?.position || '—' }}</p>
                    </div>
                    <div>
                        <small class="text-muted">{{ $t('pay_period') }}:</small>
                        <p>{{ formatDate(activePayslip.pay_period_start) }} — {{ formatDate(activePayslip.pay_period_end) }}</p>
                    </div>
                </div>

                <div class="payslip-breakdown-table mt-4">
                    <table class="payslip-table">
                        <thead>
                            <tr>
                                <th>{{ $t('statement') || 'البيان' }}</th>
                                <th class="text-right">{{ $t('amount') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>{{ $t('basic_salary') }}</td>
                                <td class="text-right">{{ money(activePayslip.basic_salary) }}</td>
                            </tr>
                            <tr v-if="Number(activePayslip.overtime_hours) > 0">
                                <td>{{ $t('overtime_pay') }} ({{ activePayslip.overtime_hours }} {{ $t('hours') || 'ساعات' }})</td>
                                <td class="text-right text-success">+{{ money(Number(activePayslip.overtime_hours) * Number(activePayslip.overtime_rate)) }}</td>
                            </tr>
                            <tr v-if="Number(activePayslip.bonuses) > 0">
                                <td>{{ $t('bonuses') }}</td>
                                <td class="text-right text-success">+{{ money(activePayslip.bonuses) }}</td>
                            </tr>
                            <tr v-if="Number(activePayslip.deductions) > 0">
                                <td>{{ $t('deductions') }}</td>
                                <td class="text-right text-danger">-{{ money(activePayslip.deductions) }}</td>
                            </tr>
                            <tr class="total-row">
                                <td><strong>{{ $t('net_salary') }}</strong></td>
                                <td class="text-right"><strong class="total-net">{{ money(activePayslip.net_salary) }}</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="activePayslip.payment_date" class="payslip-payment-meta mt-3">
                    <span class="text-muted">{{ $t('payment_date') }}: {{ formatDate(activePayslip.payment_date) }}</span>
                    <span v-if="activePayslip.payment_method" class="text-muted"> · {{ $t('payment_method') }}: {{ activePayslip.payment_method }}</span>
                </div>
            </div>

            <template #footer>
                <el-button @click="payslipVisible = false">{{ $t('cancel') }}</el-button>
                <el-button type="primary" :icon="Printer" @click="printPayslip">{{ $t('print') }}</el-button>
            </template>
        </el-dialog>

        <!-- Auto-generate Payroll Modal -->
        <el-dialog v-model="autoGenVisible" :title="$t('auto_generate_payrolls')" width="440px" destroy-on-close>
            <p class="dialog-note text-muted">{{ $t('auto_generate_hint') }}</p>
            <el-form label-position="top">
                <el-form-item :label="$t('pay_period_start')">
                    <el-date-picker
                        v-model="autoGenForm.pay_period_start"
                        type="date"
                        format="YYYY-MM-DD"
                        value-format="YYYY-MM-DD"
                        style="width: 100%"
                    />
                </el-form-item>
                <el-form-item :label="$t('pay_period_end')">
                    <el-date-picker
                        v-model="autoGenForm.pay_period_end"
                        type="date"
                        format="YYYY-MM-DD"
                        value-format="YYYY-MM-DD"
                        style="width: 100%"
                    />
                </el-form-item>
                <el-form-item :label="$t('payment_date')">
                    <el-date-picker
                        v-model="autoGenForm.payment_date"
                        type="date"
                        format="YYYY-MM-DD"
                        value-format="YYYY-MM-DD"
                        style="width: 100%"
                    />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="autoGenVisible = false">{{ $t('cancel') }}</el-button>
                <el-button type="primary" :loading="autoGenLoading" @click="confirmAutoGen">
                    {{ $t('auto_generate_payrolls') }}
                </el-button>
            </template>
        </el-dialog>

        <!-- Create Single Payroll Modal -->
        <el-dialog v-model="createModalVisible" :title="$t('new_payroll')" width="520px" destroy-on-close>
            <el-form label-position="top">
                <el-form-item :label="$t('employee')">
                    <el-select
                        v-model="createForm.employee_id"
                        :placeholder="$t('select_employee')"
                        style="width: 100%"
                        filterable
                        @change="onEmployeeSelected"
                    >
                        <el-option
                            v-for="emp in employeesStore.employees"
                            :key="emp.id"
                            :label="emp.name"
                            :value="emp.id"
                        />
                    </el-select>
                </el-form-item>
                <el-row :gutter="12">
                    <el-col :span="12">
                        <el-form-item :label="$t('pay_period_start')">
                            <el-date-picker
                                v-model="createForm.pay_period_start"
                                type="date"
                                format="YYYY-MM-DD"
                                value-format="YYYY-MM-DD"
                                style="width: 100%"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="$t('pay_period_end')">
                            <el-date-picker
                                v-model="createForm.pay_period_end"
                                type="date"
                                format="YYYY-MM-DD"
                                value-format="YYYY-MM-DD"
                                style="width: 100%"
                            />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="12">
                    <el-col :span="12">
                        <el-form-item :label="$t('basic_salary')">
                            <el-input-number v-model="createForm.basic_salary" :min="0" :precision="2" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="$t('bonuses')">
                            <el-input-number v-model="createForm.bonuses" :min="0" :precision="2" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="12">
                    <el-col :span="12">
                        <el-form-item :label="$t('deductions')">
                            <el-input-number v-model="createForm.deductions" :min="0" :precision="2" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="$t('payment_method')">
                            <el-select v-model="createForm.payment_method" style="width: 100%">
                                <el-option :label="$t('cash')" value="cash" />
                                <el-option :label="$t('bank_transfer')" value="bank_transfer" />
                                <el-option :label="$t('cheque')" value="check" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>

                <div class="net-preview-card">
                    <span>{{ $t('net_salary') }}:</span>
                    <strong>{{ money(createFormNet) }}</strong>
                </div>
            </el-form>
            <template #footer>
                <el-button @click="createModalVisible = false">{{ $t('cancel') }}</el-button>
                <el-button type="primary" :loading="createLoading" @click="confirmCreatePayroll">
                    {{ $t('save') }}
                </el-button>
            </template>
        </el-dialog>

        <!-- Pay Dialog (Existing logic preserved) -->
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
import { ElMessage, ElMessageBox } from 'element-plus';
import { usePayrollsStore } from '@/stores/payrolls';
import { useEmployeesStore } from '@/stores/employees';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';
import AdminFilterBar from '@/components/admin/AdminFilterBar.vue';
import { matchesSearch } from '@/utils/search';
import {
    Plus,
    Search,
    Refresh,
    Cpu,
    Document,
    Printer,
    Delete
} from '@element-plus/icons-vue';

const { t } = useI18n();
const store = usePayrollsStore();
const employeesStore = useEmployeesStore();

const searchQuery = ref('');
const selectedStatus = ref('');
const selectedEmployeeId = ref('');
const savingId = ref(null);
const paying = ref(false);
const payDialogVisible = ref(false);

const payslipVisible = ref(false);
const activePayslip = ref(null);

const autoGenVisible = ref(false);
const autoGenLoading = ref(false);
const autoGenForm = reactive({
    pay_period_start: '',
    pay_period_end: '',
    payment_date: ''
});

const createModalVisible = ref(false);
const createLoading = ref(false);
const createForm = reactive({
    employee_id: null,
    pay_period_start: '',
    pay_period_end: '',
    basic_salary: 0,
    overtime_hours: 0,
    overtime_rate: 0,
    bonuses: 0,
    deductions: 0,
    payment_method: 'cash'
});

const createFormNet = computed(() => {
    return Math.max(0, (Number(createForm.basic_salary) || 0) + (Number(createForm.bonuses) || 0) - (Number(createForm.deductions) || 0));
});

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

const statusTagType = (status) => {
    const value = normalizeStatus(status);
    if (['completed', 'paid'].includes(value)) return 'success';
    if (['pending'].includes(value)) return 'warning';
    if (['processed'].includes(value)) return 'primary';
    return 'info';
};

const money = (value) => Number(value || 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const formatDate = (val) => {
    if (!val) return '—';
    return String(val).slice(0, 10);
};

const totalNetSalaries = computed(() => {
    return store.payrolls.reduce((acc, p) => acc + (Number(p.net_salary) || 0), 0);
});

const pendingCount = computed(() => store.payrolls.filter(p => normalizeStatus(p.status) === 'pending').length);
const processedCount = computed(() => store.payrolls.filter(p => normalizeStatus(p.status) === 'processed').length);
const paidCount = computed(() => store.payrolls.filter(p => normalizeStatus(p.status) === 'paid').length);

const filteredPayrolls = computed(() => {
    return store.payrolls.filter((payroll) => {
        const matchesQuery = !searchQuery.value.trim() || matchesSearch([
            payroll.payroll_number,
            payroll.employee?.name,
            payroll.net_salary,
            payroll.status
        ], searchQuery.value);

        const matchesStatus = !selectedStatus.value || normalizeStatus(payroll.status) === normalizeStatus(selectedStatus.value);
        const matchesEmployee = !selectedEmployeeId.value || payroll.employee_id === selectedEmployeeId.value;

        return matchesQuery && matchesStatus && matchesEmployee;
    });
});

const fetchPayrolls = async () => {
    await store.fetchPayrolls().catch(() => {});
};

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
        ElMessage.success(t('payroll_accrued') || 'تم ترحيل استحقاق مسيرة الرواتب');
        await store.fetchPayrolls();
    } catch (error) {
        ElMessage.error(error.response?.data?.message || t('failed_to_update_payroll') || 'تعذر تحديث المسيرة');
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
        await store.updatePayroll(payForm.id, {
            status: 'paid',
            payment_date: payForm.payment_date,
            payment_method: payForm.payment_method,
        });
        payDialogVisible.value = false;
        ElMessage.success(t('payroll_paid') || 'تم صرف الراتب وترحيل قيده');
        await store.fetchPayrolls();
    } catch (error) {
        ElMessage.error(error.response?.data?.message || t('failed_to_update_payroll') || 'تعذر تحديث المسيرة');
    } finally {
        paying.value = false;
    }
};

const deletePayroll = async (payroll) => {
    try {
        await ElMessageBox.confirm(
            t('confirm_delete_record') || 'هل أنت متأكد من حذف هذه المسيرة؟',
            t('confirm_deletion') || 'تأكيد الحذف',
            {
                confirmButtonText: t('yes') || 'نعم',
                cancelButtonText: t('no') || 'إلغاء',
                type: 'warning'
            }
        );

        await store.deletePayroll(payroll.id);
        ElMessage.success(t('deleted_successfully') || 'تم الحذف بنجاح');
    } catch (err) {
        if (err !== 'cancel') {
            ElMessage.error(t('failed_to_delete') || 'تعذر حذف المسيرة');
        }
    }
};

const openPayslip = (payroll) => {
    activePayslip.value = payroll;
    payslipVisible.value = true;
};

const printPayslip = () => {
    window.print();
};

const openAutoGenerateModal = () => {
    const now = new Date();
    const y = now.getFullYear();
    const m = now.getMonth();
    const firstDay = new Date(y, m, 1).toISOString().slice(0, 10);
    const lastDay = new Date(y, m + 1, 0).toISOString().slice(0, 10);

    autoGenForm.pay_period_start = firstDay;
    autoGenForm.pay_period_end = lastDay;
    autoGenForm.payment_date = lastDay;
    autoGenVisible.value = true;
};

const confirmAutoGen = async () => {
    if (!autoGenForm.pay_period_start || !autoGenForm.pay_period_end) {
        ElMessage.warning(t('please_select_period') || 'يرجى تحديد فترة الراتب');
        return;
    }
    autoGenLoading.value = true;
    try {
        const res = await store.autoGenerate(autoGenForm);
        ElMessage.success(res.message || t('auto_generate_payrolls'));
        autoGenVisible.value = false;
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('action_failed') || 'فشل التوليد');
    } finally {
        autoGenLoading.value = false;
    }
};

const openCreatePayrollModal = () => {
    const now = new Date();
    const y = now.getFullYear();
    const m = now.getMonth();
    createForm.employee_id = null;
    createForm.pay_period_start = new Date(y, m, 1).toISOString().slice(0, 10);
    createForm.pay_period_end = new Date(y, m + 1, 0).toISOString().slice(0, 10);
    createForm.basic_salary = 0;
    createForm.bonuses = 0;
    createForm.deductions = 0;
    createForm.payment_method = 'cash';
    createModalVisible.value = true;
};

const onEmployeeSelected = (id) => {
    const emp = employeesStore.employees.find(e => e.id === id);
    if (emp) {
        createForm.basic_salary = Number(emp.salary) || 0;
        createForm.bonuses = Number(emp.bonus) || 0;
    }
};

const confirmCreatePayroll = async () => {
    if (!createForm.employee_id) {
        ElMessage.warning(t('employee_required') || 'يرجى اختيار الموظف');
        return;
    }
    createLoading.value = true;
    try {
        await store.createPayroll({
            employee_id: createForm.employee_id,
            pay_period_start: createForm.pay_period_start,
            pay_period_end: createForm.pay_period_end,
            basic_salary: createForm.basic_salary,
            overtime_hours: 0,
            overtime_rate: 0,
            bonuses: createForm.bonuses,
            deductions: createForm.deductions,
            payment_method: createForm.payment_method
        });
        ElMessage.success(t('created_successfully') || 'تم إنشاء المسيرة بنجاح');
        createModalVisible.value = false;
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('action_failed') || 'فشل إنشاء المسيرة');
    } finally {
        createLoading.value = false;
    }
};

onMounted(async () => {
    await Promise.all([
        employeesStore.fetchEmployees().catch(() => {}),
        fetchPayrolls()
    ]);
});
</script>

<style scoped>
.hr-page {
    padding: 0;
}

.mb-4 {
    margin-bottom: 1.25rem;
}

.stat-card {
    border-radius: 1rem;
    border: 1px solid #eef2f6;
    padding: 0.25rem 0;
}

.stat-title {
    font-size: 0.85rem;
    color: #64748b;
    font-weight: 500;
}

.stat-value {
    margin: 0.35rem 0 0.15rem;
    font-size: 1.7rem;
    font-weight: 700;
}

.stat-sub {
    font-size: 0.78rem;
    color: #94a3b8;
}

.table-card {
    border-radius: 1rem;
    border: 1px solid #eef2f6;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
}

.record-count {
    color: #6b7c98;
    font-size: 0.9rem;
}

.emp-row-info {
    display: flex;
    flex-direction: column;
}

.emp-name {
    font-weight: 600;
    color: #1e293b;
}

.emp-dept {
    font-size: 0.75rem;
}

.period-display {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.85rem;
    color: #334155;
}

.net-salary-badge {
    font-weight: 700;
    color: #0f172a;
    font-size: 0.95rem;
}

.text-success { color: #16a34a; }
.text-warning { color: #d97706; }
.text-danger { color: #dc2626; }
.text-primary { color: #2563eb; }
.text-info { color: #0284c7; }
.text-muted { color: #64748b; }
.font-bold { font-weight: 700; }
.font-medium { font-weight: 600; }
.font-mono { font-family: monospace; }

.error-state,
.empty-state {
    padding: 2.5rem 1rem;
    text-align: center;
    color: #64748b;
}

.dialog-note {
    font-size: 0.85rem;
    margin-bottom: 1.25rem;
}

.net-preview-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.85rem 1rem;
    background: #f8fafc;
    border-radius: 0.75rem;
    border: 1px solid #e2e8f0;
    margin-top: 0.5rem;
}

.net-preview-card strong {
    font-size: 1.25rem;
    color: #16a34a;
}

/* Payslip styling */
.payslip-box {
    padding: 1.5rem;
    background: #ffffff;
    border-radius: 0.75rem;
    border: 1px solid #e2e8f0;
}

.payslip-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.company-brand h2 {
    margin: 0;
    font-size: 1.35rem;
    color: #1e293b;
}

.payslip-number-tag {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.35rem;
}

.payslip-emp-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    font-size: 0.9rem;
}

.payslip-emp-grid p {
    margin: 0.15rem 0 0;
    color: #1e293b;
}

.payslip-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.payslip-table th,
.payslip-table td {
    padding: 0.65rem 0.5rem;
    border-bottom: 1px solid #e2e8f0;
}

.payslip-table th {
    text-align: left;
    color: #64748b;
    font-weight: 600;
}

.text-right {
    text-align: right;
}

.total-row td {
    border-top: 2px solid #0f172a;
    border-bottom: 2px solid #0f172a;
    font-size: 1.05rem;
}

.total-net {
    color: #16a34a;
}

.payslip-payment-meta {
    font-size: 0.82rem;
    text-align: center;
}

.mt-3 { margin-top: 0.75rem; }
.mt-4 { margin-top: 1.25rem; }
</style>
