<template>
    <div class="hr-page hr-attendance">
        <div class="page-header">
            <div class="page-title">
                <h1>{{ $t('attendance_and_departure') }}</h1>
                <p>{{ $t('follow_up_on_daily_attendance') }}</p>
            </div>
            <el-input v-model="searchQuery" :placeholder="$t('search_by_employee_or_case')" clearable class="search-input" />
        </div>

        <el-row :gutter="16" class="overview-cards">
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>{{ $t('attendance_today') }}</p>
                    <h3>{{ presentCount }}</h3>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>{{ $t('latecomers') }}</p>
                    <h3>{{ lateCount }}</h3>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>{{ $t('absence') }}</p>
                    <h3>{{ absentCount }}</h3>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="hover" class="table-card">
            <template #header>
                <div class="card-header">
                    <span>{{ $t('attendance_record') }}</span>
                    <el-button type="primary" size="small" @click="createAttendance">{{ $t('new_attendance_record') }}</el-button>
                </div>
            </template>

            <el-table v-loading="attendanceStore.loading" :data="filteredRecords" style="width:100%" stripe>
                <el-table-column prop="employeeName" :label="$t('employee')" />
                <el-table-column prop="department" :label="$t('department')" />
                <el-table-column prop="clock_in" :label="$t('entrance')" width="120" />
                <el-table-column prop="clock_out" :label="$t('exit')" width="120" />
                <el-table-column prop="statusLabel" :label="$t('status')" width="120">
                    <template #default="{ row }">
                        <el-tag :type="statusTagType(row.statusLabel)">{{ row.statusLabel }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('procedures')" width="160">
                    <template #default="{ row }">
                        <el-button type="text" size="small" @click="editAttendance(row)">{{ $t('edit') }}</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div v-if="attendanceStore.error" class="error-state">
                {{ attendanceStore.error }}
            </div>
            <div v-else-if="!filteredRecords.length" class="empty-state">
                {{ $t('there_are_no_records_matching') }}
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAttendanceStore } from '@/stores/attendance';

const router = useRouter();
const attendanceStore = useAttendanceStore();
const searchQuery = ref('');

const formatStatus = (status) => {
    const value = String(status || '').toLowerCase();
    if (['present', window.t('present')].includes(value)) return window.t('present');
    if (['late', window.t('late')].includes(value)) return window.t('late');
    if (['absent', window.t('absent')].includes(value)) return window.t('absent');
    return status || window.t('undefined');
};

const attendanceRecords = computed(() => attendanceStore.records.map((record) => ({
    ...record,
    employeeName: record.employee?.name || window.t('undefined'),
    department: record.employee?.department || window.t('undefined'),
    clock_in: record.clock_in ? String(record.clock_in).split(' ')[1] || record.clock_in : '-',
    clock_out: record.clock_out ? String(record.clock_out).split(' ')[1] || record.clock_out : '-',
    statusLabel: formatStatus(record.status)
})));

const filteredRecords = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();
    if (!query) return attendanceRecords.value;
    return attendanceRecords.value.filter((record) => {
        return [record.employeeName, record.department, record.statusLabel].some((field) =>
            String(field || '').toLowerCase().includes(query)
        );
    });
});

const statusTagType = (status) => {
    if (status === window.t('present')) return 'success';
    if (status === window.t('late')) return 'warning';
    if (status === window.t('absent')) return 'danger';
    return 'info';
};

const presentCount = computed(() => attendanceRecords.value.filter((record) => record.statusLabel === window.t('present')).length);
const lateCount = computed(() => attendanceRecords.value.filter((record) => record.statusLabel === window.t('late')).length);
const absentCount = computed(() => attendanceRecords.value.filter((record) => record.statusLabel === window.t('absent')).length);

const loadAttendance = async () => {
    await attendanceStore.fetchAttendance().catch(() => {});
};

const createAttendance = () => {
    router.push({ name: 'admin.hr.attendance.create' });
};

const editAttendance = (record) => {
    if (!record?.id) return;
    router.push({ name: 'admin.hr.attendance.edit', params: { id: record.id } });
};

onMounted(loadAttendance);
</script>

<style scoped>
.hr-page {
    padding: 0;
}

.page-header {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
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
    min-width: 260px;
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

.table-card {
    border-radius: 1rem;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
}

.empty-state {
    padding: 1.25rem;
    text-align: center;
    color: #6b7c98;
}
</style>

<style scoped>
.hr-page {
    padding: 0;
}

.page-header {
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

.content-card {
    border-radius: 1rem;
}

.empty-state {
    padding: 1.5rem;
    color: #58657e;
}
</style>
