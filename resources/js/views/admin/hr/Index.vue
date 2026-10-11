<template>
    <div class="hr-index-page">
        <!-- Header -->
        <AdminPageHeader
            :title="$t('hr_dashboard')"
            :subtitle="$t('hr_subtitle')"
        >
            <template #actions>
                <el-button-group>
                    <el-button :icon="Refresh" :loading="loading" @click="refreshData">
                        {{ $t('refresh') || $t('data_update') }}
                    </el-button>
                    <el-button type="primary" :icon="Plus" @click="router.push({ name: 'admin.hr.employees.create' })">
                        {{ $t('add_an_employee') }}
                    </el-button>
                </el-button-group>
            </template>
        </AdminPageHeader>

        <!-- KPI Summary Cards -->
        <el-row :gutter="16" class="kpi-grid">
            <el-col :xs="24" :sm="12" :lg="6">
                <el-card shadow="hover" class="kpi-card" @click="router.push({ name: 'admin.hr.employees' })">
                    <div class="kpi-inner">
                        <div class="kpi-meta">
                            <span class="kpi-title">{{ $t('staff') }}</span>
                            <div class="kpi-value">{{ totalEmployees }}</div>
                            <div class="kpi-sub">
                                <span class="text-success">{{ activeEmployeesCount }} {{ $t('active') }}</span>
                                <span v-if="inactiveEmployeesCount > 0" class="text-muted"> · {{ inactiveEmployeesCount }} {{ $t('inactive') }}</span>
                            </div>
                        </div>
                        <div class="kpi-icon-badge badge-blue">
                            <el-icon><User /></el-icon>
                        </div>
                    </div>
                </el-card>
            </el-col>

            <el-col :xs="24" :sm="12" :lg="6">
                <el-card shadow="hover" class="kpi-card" @click="router.push({ name: 'admin.hr.attendance' })">
                    <div class="kpi-inner">
                        <div class="kpi-meta">
                            <span class="kpi-title">{{ $t('attendance_today') }}</span>
                            <div class="kpi-value">{{ presentTodayCount }}</div>
                            <div class="kpi-sub">
                                <span class="text-warning">{{ lateTodayCount }} {{ $t('late') }}</span>
                                <span class="text-danger"> · {{ absentTodayCount }} {{ $t('absent') }}</span>
                            </div>
                        </div>
                        <div class="kpi-icon-badge badge-green">
                            <el-icon><Timer /></el-icon>
                        </div>
                    </div>
                </el-card>
            </el-col>

            <el-col :xs="24" :sm="12" :lg="6">
                <el-card shadow="hover" class="kpi-card" @click="router.push({ name: 'admin.hr.leaves' })">
                    <div class="kpi-inner">
                        <div class="kpi-meta">
                            <span class="kpi-title">{{ $t('pending_leaves') }}</span>
                            <div class="kpi-value">{{ pendingLeavesCount }}</div>
                            <div class="kpi-sub">
                                <span class="text-info">{{ approvedLeavesCount }} {{ $t('approved') }}</span>
                                <span class="text-muted"> · {{ totalLeavesCount }} {{ $t('vacations') }}</span>
                            </div>
                        </div>
                        <div class="kpi-icon-badge badge-orange">
                            <el-icon><Calendar /></el-icon>
                        </div>
                    </div>
                </el-card>
            </el-col>

            <el-col :xs="24" :sm="12" :lg="6">
                <el-card shadow="hover" class="kpi-card" @click="router.push({ name: 'admin.payrolls.index' })">
                    <div class="kpi-inner">
                        <div class="kpi-meta">
                            <span class="kpi-title">{{ $t('total_salaries_liability') }}</span>
                            <div class="kpi-value">{{ formatMoney(totalSalariesMonthly) }}</div>
                            <div class="kpi-sub">
                                <span class="text-primary">{{ pendingPayrollsCount }} {{ $t('pending') }}</span>
                                <span class="text-success"> · {{ paidPayrollsCount }} {{ $t('paid') }}</span>
                            </div>
                        </div>
                        <div class="kpi-icon-badge badge-purple">
                            <el-icon><Money /></el-icon>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- Quick Action Hub -->
        <el-card shadow="hover" class="quick-hub-card mt-4">
            <template #header>
                <div class="section-card-header">
                    <span class="header-title">{{ $t('quick_access') }}</span>
                    <span class="header-subtitle">{{ $t('frequently_used_hr_actions') || 'الإجراءات الأكثر استخداماً' }}</span>
                </div>
            </template>
            <div class="action-grid">
                <div class="action-btn-item" @click="router.push({ name: 'admin.hr.employees.create' })">
                    <div class="action-btn-icon bg-blue-subtle">
                        <el-icon><Plus /></el-icon>
                    </div>
                    <span class="action-btn-label">{{ $t('add_an_employee') }}</span>
                </div>

                <div class="action-btn-item" @click="router.push({ name: 'admin.hr.attendance.create' })">
                    <div class="action-btn-icon bg-green-subtle">
                        <el-icon><Check /></el-icon>
                    </div>
                    <span class="action-btn-label">{{ $t('new_attendance_record') }}</span>
                </div>

                <div class="action-btn-item" @click="router.push({ name: 'admin.hr.leaves.create' })">
                    <div class="action-btn-icon bg-amber-subtle">
                        <el-icon><DocumentAdd /></el-icon>
                    </div>
                    <span class="action-btn-label">{{ $t('new_leave_request') }}</span>
                </div>

                <div class="action-btn-item" @click="openAutoGenerateModal">
                    <div class="action-btn-icon bg-purple-subtle">
                        <el-icon><Cpu /></el-icon>
                    </div>
                    <span class="action-btn-label">{{ $t('auto_generate_payrolls') }}</span>
                </div>

                <div class="action-btn-item" @click="router.push({ name: 'admin.hr.commissions' })">
                    <div class="action-btn-icon bg-teal-subtle">
                        <el-icon><TrendCharts /></el-icon>
                    </div>
                    <span class="action-btn-label">{{ $t('commission_statement') }}</span>
                </div>

                <div class="action-btn-item" @click="router.push({ name: 'admin.hr.employee-customers' })">
                    <div class="action-btn-icon bg-indigo-subtle">
                        <el-icon><Connection /></el-icon>
                    </div>
                    <span class="action-btn-label">{{ $t('employee_customer_relationship') }}</span>
                </div>
            </div>
        </el-card>

        <!-- Main Dashboard Split: Pending Leaves & Today's Attendance Overview -->
        <el-row :gutter="20" class="mt-4">
            <!-- Pending Leaves Awaiting Approval -->
            <el-col :xs="24" :lg="14">
                <el-card shadow="hover" class="dashboard-table-card">
                    <template #header>
                        <div class="card-header-flex">
                            <div class="header-left">
                                <span class="header-title">{{ $t('pending_leaves') }}</span>
                                <el-tag v-if="pendingLeaveList.length" type="warning" size="small" effect="plain" round>
                                    {{ pendingLeaveList.length }} {{ $t('pending') }}
                                </el-tag>
                            </div>
                            <el-button link type="primary" @click="router.push({ name: 'admin.hr.leaves' })">
                                {{ $t('view_all') || 'عرض الكل' }} &rarr;
                            </el-button>
                        </div>
                    </template>

                    <div v-if="pendingLeaveList.length === 0" class="empty-state-box">
                        <el-icon class="empty-icon text-success"><CircleCheckFilled /></el-icon>
                        <p class="empty-title">{{ $t('no_pending_requests') || 'لا توجد طلبات إجازة معلّقة حالياً' }}</p>
                        <p class="empty-desc">{{ $t('all_caught_up') || 'تمت معالجة جميع طلبات الإجازات السابقة.' }}</p>
                    </div>

                    <el-table v-else :data="pendingLeaveList.slice(0, 5)" stripe size="small" style="width: 100%">
                        <el-table-column :label="$t('employee')" min-width="140">
                            <template #default="{ row }">
                                <div class="employee-cell">
                                    <span class="emp-name">{{ row.employee?.name || $t('undefined') }}</span>
                                    <span class="emp-dept text-muted">{{ row.employee?.department || '' }}</span>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column prop="leave_type" :label="$t('leave_type')" width="110">
                            <template #default="{ row }">
                                <el-tag size="small" effect="plain">{{ row.leave_type }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('period')" width="170">
                            <template #default="{ row }">
                                <div class="date-range-cell">
                                    <span>{{ formatDate(row.start_date) }}</span>
                                    <span class="range-arrow">&rarr;</span>
                                    <span>{{ formatDate(row.end_date) }}</span>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column :label="$t('actions')" width="150" align="center">
                            <template #default="{ row }">
                                <el-button-group>
                                    <el-button
                                        type="success"
                                        size="small"
                                        :loading="processingLeaveId === row.id"
                                        @click="quickHandleLeave(row, 'approved')"
                                    >
                                        {{ $t('quick_approve') }}
                                    </el-button>
                                    <el-button
                                        type="danger"
                                        size="small"
                                        plain
                                        :loading="processingLeaveId === row.id"
                                        @click="quickHandleLeave(row, 'rejected')"
                                    >
                                        {{ $t('quick_reject') }}
                                    </el-button>
                                </el-button-group>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-col>

            <!-- Departments & Today's Attendance Breakdown -->
            <el-col :xs="24" :lg="10">
                <el-card shadow="hover" class="dashboard-side-card">
                    <template #header>
                        <div class="card-header-flex">
                            <span class="header-title">{{ $t('department_distribution') || 'توزيع الأقسام والموظفين' }}</span>
                            <el-button link type="primary" @click="router.push({ name: 'admin.hr.employees' })">
                                {{ $t('employees') }} &rarr;
                            </el-button>
                        </div>
                    </template>

                    <div v-if="departmentStats.length === 0" class="empty-state-box">
                        <p class="empty-title">{{ $t('no_employees_yet') || 'لا يوجد موظفون مسجلون' }}</p>
                    </div>

                    <div v-else class="dept-list">
                        <div v-for="dept in departmentStats" :key="dept.name" class="dept-item">
                            <div class="dept-info">
                                <span class="dept-name">{{ dept.name }}</span>
                                <span class="dept-count">{{ dept.count }} {{ $t('staff') }} ({{ dept.percent }}%)</span>
                            </div>
                            <el-progress
                                :percentage="dept.percent"
                                :color="dept.color"
                                :stroke-width="8"
                                :show-text="false"
                            />
                        </div>
                    </div>
                </el-card>

                <!-- Attendance Today Summary Card -->
                <el-card shadow="hover" class="dashboard-side-card mt-4">
                    <template #header>
                        <div class="card-header-flex">
                            <span class="header-title">{{ $t('recent_attendance') }}</span>
                            <el-button link type="primary" @click="router.push({ name: 'admin.hr.attendance' })">
                                {{ $t('the_audience') || $t('attendance') }} &rarr;
                            </el-button>
                        </div>
                    </template>

                    <div class="attendance-status-summary">
                        <div class="status-box box-present">
                            <span class="status-num">{{ presentTodayCount }}</span>
                            <span class="status-label">{{ $t('present') }}</span>
                        </div>
                        <div class="status-box box-late">
                            <span class="status-num">{{ lateTodayCount }}</span>
                            <span class="status-label">{{ $t('late') }}</span>
                        </div>
                        <div class="status-box box-absent">
                            <span class="status-num">{{ absentTodayCount }}</span>
                            <span class="status-label">{{ $t('absent') }}</span>
                        </div>
                        <div class="status-box box-remote">
                            <span class="status-num">{{ remoteTodayCount }}</span>
                            <span class="status-label">{{ $t('remote') }}</span>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- Auto-generate Payroll Modal -->
        <el-dialog
            v-model="autoGenModalVisible"
            :title="$t('auto_generate_payrolls')"
            width="460px"
            destroy-on-close
        >
            <p class="text-muted dialog-note">{{ $t('auto_generate_hint') }}</p>
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
                <el-button @click="autoGenModalVisible = false">{{ $t('cancel') }}</el-button>
                <el-button type="primary" :loading="autoGenLoading" @click="confirmAutoGen">
                    {{ $t('auto_generate_payrolls') }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, reactive, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import { usePayrollsStore } from '@/stores/payrolls';
import { useEmployeesStore } from '@/stores/employees';
import { useAttendanceStore } from '@/stores/attendance';
import { useLeaveRequestsStore } from '@/stores/leaveRequests';
import {
    Refresh,
    Plus,
    User,
    Timer,
    Calendar,
    Money,
    Check,
    DocumentAdd,
    Cpu,
    TrendCharts,
    Connection,
    CircleCheckFilled
} from '@element-plus/icons-vue';

const router = useRouter();
const { t } = useI18n();

const payrollsStore = usePayrollsStore();
const employeesStore = useEmployeesStore();
const attendanceStore = useAttendanceStore();
const leaveStore = useLeaveRequestsStore();

const loading = ref(false);
const processingLeaveId = ref(null);

// Auto-generate Payroll state
const autoGenModalVisible = ref(false);
const autoGenLoading = ref(false);
const autoGenForm = reactive({
    pay_period_start: '',
    pay_period_end: '',
    payment_date: ''
});

// Formatting Helpers
const formatMoney = (val) => {
    return Number(val || 0).toLocaleString(undefined, {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2
    });
};

const formatDate = (val) => {
    if (!val) return '—';
    return String(val).slice(0, 10);
};

// Computed Stats
const totalEmployees = computed(() => employeesStore.employees.length);
const activeEmployeesCount = computed(() =>
    employeesStore.employees.filter(e => e.status === 'نشط' || String(e.status).toLowerCase() === 'active').length
);
const inactiveEmployeesCount = computed(() => totalEmployees.value - activeEmployeesCount.value);

const totalSalariesMonthly = computed(() => {
    return employeesStore.employees.reduce((acc, emp) => {
        return acc + (Number(emp.salary) || 0);
    }, 0);
});

// Today's Attendance Counts
const todayIso = new Date().toISOString().slice(0, 10);
const todayRecords = computed(() => {
    return attendanceStore.records.filter(r => String(r.date || '').slice(0, 10) === todayIso);
});

const presentTodayCount = computed(() => {
    const list = todayRecords.value.length ? todayRecords.value : attendanceStore.records;
    return list.filter(r => ['present', 'حاضر'].includes(String(r.status).toLowerCase())).length;
});

const lateTodayCount = computed(() => {
    const list = todayRecords.value.length ? todayRecords.value : attendanceStore.records;
    return list.filter(r => ['late', 'متأخر'].includes(String(r.status).toLowerCase())).length;
});

const absentTodayCount = computed(() => {
    const list = todayRecords.value.length ? todayRecords.value : attendanceStore.records;
    return list.filter(r => ['absent', 'غائب'].includes(String(r.status).toLowerCase())).length;
});

const remoteTodayCount = computed(() => {
    const list = todayRecords.value.length ? todayRecords.value : attendanceStore.records;
    return list.filter(r => ['remote', 'عن بعد', 'عن بُعد'].includes(String(r.status).toLowerCase())).length;
});

// Leave Counts
const totalLeavesCount = computed(() => leaveStore.requests.length);
const pendingLeavesCount = computed(() =>
    leaveStore.requests.filter(r => ['pending', 'معلق', 'معلّق'].includes(String(r.status).toLowerCase())).length
);
const approvedLeavesCount = computed(() =>
    leaveStore.requests.filter(r => ['approved', 'موافق', 'معتمد'].includes(String(r.status).toLowerCase())).length
);

const pendingLeaveList = computed(() => {
    return leaveStore.requests.filter(r => ['pending', 'معلق', 'معلّق'].includes(String(r.status).toLowerCase()));
});

// Payroll Counts
const pendingPayrollsCount = computed(() =>
    payrollsStore.payrolls.filter(p => String(p.status).toLowerCase() === 'pending').length
);
const paidPayrollsCount = computed(() =>
    payrollsStore.payrolls.filter(p => String(p.status).toLowerCase() === 'paid').length
);

// Department Distribution
const deptColors = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4'];
const departmentStats = computed(() => {
    const map = {};
    employeesStore.employees.forEach(emp => {
        const d = emp.department ? emp.department.trim() : (t('undefined') || 'عام');
        map[d] = (map[d] || 0) + 1;
    });

    const total = employeesStore.employees.length || 1;
    return Object.entries(map).map(([name, count], idx) => ({
        name,
        count,
        percent: Math.round((count / total) * 100),
        color: deptColors[idx % deptColors.length]
    })).sort((a, b) => b.count - a.count);
});

// Actions
const refreshData = async () => {
    loading.value = true;
    try {
        await Promise.all([
            payrollsStore.fetchPayrolls().catch(() => {}),
            employeesStore.fetchEmployees().catch(() => {}),
            attendanceStore.fetchAttendance().catch(() => {}),
            leaveStore.fetchLeaveRequests().catch(() => {})
        ]);
    } finally {
        loading.value = false;
    }
};

const quickHandleLeave = async (leave, status) => {
    processingLeaveId.value = leave.id;
    try {
        await leaveStore.updateLeaveRequest(leave.id, { status });
        ElMessage.success(status === 'approved' ? t('leave_approved_success') : t('leave_rejected_success'));
        await leaveStore.fetchLeaveRequests();
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('action_failed') || 'فشل تنفيذ الإجراء');
    } finally {
        processingLeaveId.value = null;
    }
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
    autoGenModalVisible.value = true;
};

const confirmAutoGen = async () => {
    if (!autoGenForm.pay_period_start || !autoGenForm.pay_period_end) {
        ElMessage.warning(t('please_select_period') || 'يرجى تحديد فترة الراتب');
        return;
    }

    autoGenLoading.value = true;
    try {
        const res = await payrollsStore.autoGenerate(autoGenForm);
        ElMessage.success(res.message || t('auto_generate_payrolls'));
        autoGenModalVisible.value = false;
        router.push({ name: 'admin.payrolls.index' });
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('action_failed') || 'فشل التوليد التلقائي');
    } finally {
        autoGenLoading.value = false;
    }
};

onMounted(refreshData);
</script>

<style scoped>
.hr-index-page {
    padding: 0;
}

.kpi-grid {
    margin-bottom: 0.5rem;
}

.kpi-card {
    border-radius: 1rem;
    border: 1px solid #eef2f6;
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    margin-bottom: 1rem;
}

.kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
}

.kpi-inner {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 0.25rem 0;
}

.kpi-meta .kpi-title {
    font-size: 0.85rem;
    color: #64748b;
    font-weight: 500;
}

.kpi-meta .kpi-value {
    font-size: 1.85rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0.35rem 0 0.25rem;
    line-height: 1.2;
}

.kpi-meta .kpi-sub {
    font-size: 0.8rem;
    display: flex;
    gap: 0.35rem;
}

.kpi-icon-badge {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
}

.badge-blue {
    background: #eff6ff;
    color: #3b82f6;
}

.badge-green {
    background: #ecfdf5;
    color: #10b981;
}

.badge-orange {
    background: #fffbeb;
    color: #f59e0b;
}

.badge-purple {
    background: #f5f3ff;
    color: #8b5cf6;
}

.quick-hub-card {
    border-radius: 1rem;
    border: 1px solid #eef2f6;
}

.section-card-header {
    display: flex;
    flex-direction: column;
}

.header-title {
    font-size: 1rem;
    font-weight: 700;
    color: #1e293b;
}

.header-subtitle {
    font-size: 0.8rem;
    color: #64748b;
    margin-top: 0.15rem;
}

.action-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 1rem;
    padding: 0.5rem 0;
}

.action-btn-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 0.6rem;
    padding: 1rem 0.5rem;
    border-radius: 0.85rem;
    border: 1px solid #f1f5f9;
    background: #f8fafc;
    cursor: pointer;
    transition: all 0.2s ease;
}

.action-btn-item:hover {
    background: #ffffff;
    border-color: #cbd5e1;
    transform: translateY(-2px);
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
}

.action-btn-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.bg-blue-subtle { background: #dbeafe; color: #1d4ed8; }
.bg-green-subtle { background: #dcfce7; color: #15803d; }
.bg-amber-subtle { background: #fef3c7; color: #b45309; }
.bg-purple-subtle { background: #f3e8ff; color: #7e22ce; }
.bg-teal-subtle { background: #ccfbf1; color: #0f766e; }
.bg-indigo-subtle { background: #e0e7ff; color: #4338ca; }

.action-btn-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #334155;
    line-height: 1.2;
}

.dashboard-table-card,
.dashboard-side-card {
    border-radius: 1rem;
    border: 1px solid #eef2f6;
    min-height: 280px;
}

.card-header-flex {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.empty-state-box {
    padding: 2.5rem 1rem;
    text-align: center;
}

.empty-icon {
    font-size: 2.4rem;
    margin-bottom: 0.5rem;
}

.empty-title {
    font-size: 0.95rem;
    font-weight: 600;
    color: #334155;
    margin: 0;
}

.empty-desc {
    font-size: 0.82rem;
    color: #64748b;
    margin: 0.35rem 0 0;
}

.employee-cell {
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

.date-range-cell {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.8rem;
    color: #475569;
}

.range-arrow {
    color: #94a3b8;
}

.dept-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    padding: 0.25rem 0;
}

.dept-item {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.dept-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.85rem;
}

.dept-name {
    font-weight: 600;
    color: #1e293b;
}

.dept-count {
    color: #64748b;
    font-size: 0.8rem;
}

.attendance-status-summary {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.75rem;
    text-align: center;
}

.status-box {
    padding: 0.75rem 0.5rem;
    border-radius: 0.75rem;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.box-present { background: #ecfdf5; color: #065f46; }
.box-late { background: #fffbeb; color: #92400e; }
.box-absent { background: #fef2f2; color: #991b1b; }
.box-remote { background: #eff6ff; color: #1e40af; }

.status-num {
    font-size: 1.4rem;
    font-weight: 700;
}

.status-label {
    font-size: 0.75rem;
    font-weight: 600;
}

.dialog-note {
    font-size: 0.85rem;
    margin-bottom: 1.25rem;
}

.mt-4 {
    margin-top: 1.25rem;
}

.text-success { color: #16a34a; }
.text-warning { color: #d97706; }
.text-danger { color: #dc2626; }
.text-info { color: #0284c7; }
.text-primary { color: #2563eb; }
.text-muted { color: #64748b; }
</style>
