<template>
    <div class="hr-index">
        <div class="hero-panel">
            <div class="hero-copy">
                <p class="eyebrow">{{ $t('human_resources') }}</p>
                <h1>{{ $t('modern_human_resources_management_dashboard') }}</h1>
                <p>{{ $t('track_employees_attendance_vacations_and') }}</p>
            </div>
            <div class="hero-actions">
                <el-button type="primary" @click="refreshPayrolls">{{ $t('data_update') }}</el-button>
            </div>
        </div>

        <el-row :gutter="20" class="overview-cards">
            <el-col :xs="24" :sm="12" :md="6" v-for="item in stats" :key="item.title">
                <el-card shadow="hover" class="overview-card">
                    <div class="card-meta">
                        <span>{{ item.title }}</span>
                    </div>
                    <h2>{{ item.value }}</h2>
                    <p>{{ item.subtitle }}</p>
                </el-card>
            </el-col>
        </el-row>

        <el-row :gutter="20" class="mt-4">
            <el-col :xs="24" :lg="8">
                <el-card shadow="hover" class="insight-card">
                    <template #header>
                        <span>{{ $t('quick_access') }}</span>
                    </template>
                    <div class="insight-list">
                        <div class="insight-item">
                            <span>{{ $t('staff') }}</span>
                            <strong>{{ employeesStore.employees.length }}</strong>
                        </div>
                        <div class="insight-item">
                            <span>{{ $t('the_audience') }}</span>
                            <strong>{{ attendanceStore.records.length }}</strong>
                        </div>
                        <div class="insight-item">
                            <span>{{ $t('vacations') }}</span>
                            <strong>{{ leaveStore.requests.length }}</strong>
                        </div>
                        <div class="insight-item">
                            <span>{{ $t('salaries') }}</span>
                            <strong>{{ payrollsStore.payrolls.length }}</strong>
                        </div>
                    </div>
                </el-card>
            </el-col>

            <el-col :xs="24" :lg="16">
                <el-card shadow="hover" class="activity-card">
                    <template #header>
                        <span>{{ $t('latest_updates') }}</span>
                    </template>
                    <div class="activity-copy">
                        <p>{{ $t('we_are_developing_attendance_leave') }}</p>
                        <el-button type="success" plain>{{ $t('see_salaries') }}</el-button>
                    </div>
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
import { onMounted, computed } from 'vue';
import { usePayrollsStore } from '@/stores/payrolls';
import { useEmployeesStore } from '@/stores/employees';
import { useAttendanceStore } from '@/stores/attendance';
import { useLeaveRequestsStore } from '@/stores/leaveRequests';

const payrollsStore = usePayrollsStore();
const employeesStore = useEmployeesStore();
const attendanceStore = useAttendanceStore();
const leaveStore = useLeaveRequestsStore();

const stats = computed(() => [
    { title: window.t('salary_marches'), value: payrollsStore.payrolls.length, subtitle: window.t('quick_view_of_the_marches')},
    { title: window.t('staff'), value: employeesStore.employees.length, subtitle: window.t('number_of_registered_employees')},
    { title: window.t('the_audience'), value: attendanceStore.records.length, subtitle: window.t('recorded_attendance_records')},
    { title: window.t('vacations'), value: leaveStore.requests.length, subtitle: window.t('existing_leave_requests')}
]);

const refreshData = async () => {
    await Promise.all([
        payrollsStore.fetchPayrolls().catch(() => {}),
        employeesStore.fetchEmployees().catch(() => {}),
        attendanceStore.fetchAttendance().catch(() => {}),
        leaveStore.fetchLeaveRequests().catch(() => {})
    ]);
};

onMounted(refreshData);
</script>

<style scoped>
.hr-index {
    padding: 0;
}

.hero-panel {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1.5rem;
    padding: 1.75rem;
    border-radius: 1.25rem;
    background: #f4f7ff;
    margin-bottom: 1.75rem;
}

.hero-copy .eyebrow {
    margin: 0 0 0.5rem;
    color: #409eff;
    letter-spacing: 0.08em;
    font-size: 0.85rem;
    font-weight: 700;
}

.hero-copy h1 {
    margin: 0;
    font-size: 2.2rem;
    color: #1f2d3d;
}

.hero-copy p {
    margin: 1rem 0 0;
    color: #5f6d85;
    font-size: 1rem;
    max-width: 44rem;
}

.overview-cards {
    margin-bottom: 1rem;
}

.overview-card {
    min-height: 148px;
    border-radius: 1rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 0.75rem;
}

.card-meta {
    color: #5f6d85;
    font-size: 0.95rem;
}

.overview-card h2 {
    margin: 0;
    font-size: 2.1rem;
    color: #24314f;
}

.overview-card p {
    margin: 0;
    color: #6b7c98;
}

.activity-card,
.insight-card {
    border-radius: 1rem;
}

.insight-list {
    display: grid;
    gap: 1rem;
    padding: 1rem 0;
}

.insight-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    border-radius: 1rem;
    background: #f8fbff;
}

.insight-item strong {
    color: #24314f;
    font-size: 1.15rem;
}

.activity-copy {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.mt-4 {
    margin-top: 1.5rem;
}
</style>
