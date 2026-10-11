<template>
    <div class="hr-page hr-attendance">
        <AdminPageHeader
            :title="$t('attendance_and_departure')"
            :subtitle="$t('follow_up_on_daily_attendance')"
        >
            <template #actions>
                <el-button-group>
                    <el-button :icon="Timer" @click="openQuickClockDialog">
                        {{ $t('clock_in_now') }} / {{ $t('clock_out_now') }}
                    </el-button>
                    <el-button type="primary" :icon="Plus" @click="createAttendance">
                        {{ $t('new_attendance_record') }}
                    </el-button>
                </el-button-group>
            </template>
        </AdminPageHeader>

        <!-- Stat Cards -->
        <AdminStatGrid class="mb-4">
            <el-card shadow="hover" class="stat-card">
                <span class="stat-title">{{ $t('attendance_today') }}</span>
                <h3 class="stat-value text-success">{{ presentCount }}</h3>
                <span class="stat-sub">{{ $t('present') }}</span>
            </el-card>

            <el-card shadow="hover" class="stat-card">
                <span class="stat-title">{{ $t('late') }}</span>
                <h3 class="stat-value text-warning">{{ lateCount }}</h3>
                <span class="stat-sub">{{ $t('latecomers') }}</span>
            </el-card>

            <el-card shadow="hover" class="stat-card">
                <span class="stat-title">{{ $t('absence') }}</span>
                <h3 class="stat-value text-danger">{{ absentCount }}</h3>
                <span class="stat-sub">{{ $t('absent') }}</span>
            </el-card>

            <el-card shadow="hover" class="stat-card">
                <span class="stat-title">{{ $t('remote') }}</span>
                <h3 class="stat-value text-info">{{ remoteCount }}</h3>
                <span class="stat-sub">{{ $t('remote') }}</span>
            </el-card>
        </AdminStatGrid>

        <!-- Filters Bar -->
        <AdminFilterBar>
            <el-date-picker
                v-model="selectedDate"
                type="date"
                format="YYYY-MM-DD"
                value-format="YYYY-MM-DD"
                :placeholder="$t('filter_by_date')"
                clearable
                style="max-width: 170px;"
                @change="onFilterChange"
            />
            <el-select
                v-model="selectedEmployeeId"
                :placeholder="$t('select_employee')"
                clearable
                filterable
                style="min-width: 190px;"
                @change="onFilterChange"
            >
                <el-option
                    v-for="emp in employeesStore.employees"
                    :key="emp.id"
                    :label="emp.name"
                    :value="emp.id"
                />
            </el-select>
            <el-select
                v-model="selectedStatus"
                :placeholder="$t('status')"
                clearable
                style="min-width: 130px;"
                @change="onFilterChange"
            >
                <el-option :label="$t('present')" value="present" />
                <el-option :label="$t('late')" value="late" />
                <el-option :label="$t('absent')" value="absent" />
                <el-option :label="$t('remote')" value="remote" />
            </el-select>
            <el-input
                v-model="searchQuery"
                :placeholder="$t('search_by_employee_or_case')"
                :prefix-icon="Search"
                clearable
                style="max-width: 240px;"
            />
            <el-button type="primary" :icon="Refresh" @click="loadAttendance">
                {{ $t('update') }}
            </el-button>
        </AdminFilterBar>

        <!-- Attendance Table -->
        <el-card shadow="hover" class="table-card">
            <template #header>
                <div class="card-header">
                    <span>{{ $t('attendance_record') }}</span>
                    <span class="record-count">{{ filteredRecords.length }} {{ $t('records') || 'سجل' }}</span>
                </div>
            </template>

            <div v-if="attendanceStore.error" class="error-state">
                {{ attendanceStore.error }}
            </div>

            <el-table
                v-loading="attendanceStore.loading"
                :data="filteredRecords"
                style="width: 100%"
                stripe
                highlight-current-row
            >
                <el-table-column :label="$t('employee')" min-width="170">
                    <template #default="{ row }">
                        <div class="emp-row-info">
                            <span class="emp-name">{{ row.employeeName }}</span>
                            <small class="emp-dept text-muted">{{ row.department }}</small>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column prop="date" :label="$t('the_date')" width="120">
                    <template #default="{ row }">
                        {{ formatDate(row.date) }}
                    </template>
                </el-table-column>

                <el-table-column :label="$t('entrance')" width="130">
                    <template #default="{ row }">
                        <el-tag v-if="row.clock_in_time" size="small" type="success" effect="plain">
                            {{ row.clock_in_time }}
                        </el-tag>
                        <span v-else class="text-muted">—</span>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('exit')" width="130">
                    <template #default="{ row }">
                        <el-tag v-if="row.clock_out_time" size="small" type="info" effect="plain">
                            {{ row.clock_out_time }}
                        </el-tag>
                        <el-button
                            v-else-if="row.clock_in_time && !row.clock_out_time"
                            size="small"
                            type="warning"
                            plain
                            :loading="clockingOutId === row.id"
                            @click="quickClockOut(row)"
                        >
                            {{ $t('clock_out_now') }}
                        </el-button>
                        <span v-else class="text-muted">—</span>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('hours_worked')" width="120" align="center">
                    <template #default="{ row }">
                        <span v-if="row.duration" class="font-medium">{{ row.duration }}</span>
                        <span v-else class="text-muted">—</span>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('status')" width="120">
                    <template #default="{ row }">
                        <el-tag :type="statusTagType(row.status)">
                            {{ formatStatusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column prop="notes" :label="$t('comments')" min-width="140" show-overflow-tooltip />

                <el-table-column :label="$t('procedures')" width="130" fixed="right" align="center">
                    <template #default="{ row }">
                        <el-button-group>
                            <el-button :icon="Edit" size="small" @click="editAttendance(row)" />
                            <el-button :icon="Delete" size="small" type="danger" @click="deleteAttendance(row)" />
                        </el-button-group>
                    </template>
                </el-table-column>
            </el-table>

            <div v-if="!attendanceStore.loading && !filteredRecords.length" class="empty-state">
                {{ $t('there_are_no_records_matching') }}
            </div>
        </el-card>

        <!-- Quick Clock-In / Out Modal -->
        <el-dialog
            v-model="quickClockDialogVisible"
            :title="$t('clock_in_now') + ' / ' + $t('clock_out_now')"
            width="440px"
            destroy-on-close
        >
            <el-form label-position="top">
                <el-form-item :label="$t('employee')">
                    <el-select
                        v-model="quickClockForm.employee_id"
                        :placeholder="$t('select_employee')"
                        style="width: 100%"
                        filterable
                    >
                        <el-option
                            v-for="emp in employeesStore.employees"
                            :key="emp.id"
                            :label="emp.name"
                            :value="emp.id"
                        />
                    </el-select>
                </el-form-item>
                <el-form-item :label="$t('the_date')">
                    <el-date-picker
                        v-model="quickClockForm.date"
                        type="date"
                        format="YYYY-MM-DD"
                        value-format="YYYY-MM-DD"
                        style="width: 100%"
                    />
                </el-form-item>
                <el-form-item :label="$t('status')">
                    <el-select v-model="quickClockForm.status" style="width: 100%">
                        <el-option :label="$t('present')" value="present" />
                        <el-option :label="$t('late')" value="late" />
                        <el-option :label="$t('absent')" value="absent" />
                        <el-option :label="$t('remote')" value="remote" />
                    </el-select>
                </el-form-item>
                <el-row :gutter="12">
                    <el-col :span="12">
                        <el-form-item :label="$t('entrance')">
                            <el-time-picker
                                v-model="quickClockForm.clock_in_time"
                                format="HH:mm"
                                value-format="HH:mm"
                                style="width: 100%"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="$t('exit')">
                            <el-time-picker
                                v-model="quickClockForm.clock_out_time"
                                format="HH:mm"
                                value-format="HH:mm"
                                style="width: 100%"
                            />
                        </el-form-item>
                    </el-col>
                </el-row>
            </el-form>
            <template #footer>
                <el-button @click="quickClockDialogVisible = false">{{ $t('cancel') }}</el-button>
                <el-button type="primary" :loading="quickClockSaving" @click="saveQuickClock">
                    {{ $t('save') }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useAttendanceStore } from '@/stores/attendance';
import { useEmployeesStore } from '@/stores/employees';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';
import AdminFilterBar from '@/components/admin/AdminFilterBar.vue';
import { matchesSearch } from '@/utils/search';
import {
    Plus,
    Search,
    Refresh,
    Timer,
    Edit,
    Delete
} from '@element-plus/icons-vue';

const { t } = useI18n();
const router = useRouter();
const attendanceStore = useAttendanceStore();
const employeesStore = useEmployeesStore();

const searchQuery = ref('');
const selectedDate = ref('');
const selectedEmployeeId = ref('');
const selectedStatus = ref('');
const clockingOutId = ref(null);

// Quick Clock Dialog
const quickClockDialogVisible = ref(false);
const quickClockSaving = ref(false);
const quickClockForm = reactive({
    employee_id: null,
    date: new Date().toISOString().slice(0, 10),
    status: 'present',
    clock_in_time: '09:00',
    clock_out_time: ''
});

const formatDate = (val) => {
    if (!val) return '—';
    return String(val).slice(0, 10);
};

const formatTimeOnly = (val) => {
    if (!val) return '';
    const str = String(val).trim();
    if (str.includes(' ')) {
        return str.split(' ')[1].slice(0, 5);
    }
    if (str.includes('T')) {
        return str.split('T')[1].slice(0, 5);
    }
    return str.slice(0, 5);
};

const calculateDuration = (inTime, outTime) => {
    if (!inTime || !outTime) return null;
    const dIn = new Date(inTime.includes(' ') || inTime.includes('T') ? inTime : `2000-01-01 ${inTime}`);
    const dOut = new Date(outTime.includes(' ') || outTime.includes('T') ? outTime : `2000-01-01 ${outTime}`);
    const diffMs = dOut - dIn;
    if (isNaN(diffMs) || diffMs <= 0) return null;
    const hours = Math.floor(diffMs / 3600000);
    const mins = Math.floor((diffMs % 3600000) / 60000);
    return `${hours}h ${mins}m`;
};

const formatStatusLabel = (status) => {
    const val = String(status || '').toLowerCase();
    if (val === 'present') return t('present');
    if (val === 'late') return t('late');
    if (val === 'absent') return t('absent');
    if (val === 'remote') return t('remote');
    return status || t('undefined');
};

const statusTagType = (status) => {
    const val = String(status || '').toLowerCase();
    if (val === 'present') return 'success';
    if (val === 'late') return 'warning';
    if (val === 'absent') return 'danger';
    if (val === 'remote') return 'info';
    return 'info';
};

const attendanceRecords = computed(() => attendanceStore.records.map((record) => ({
    ...record,
    employeeName: record.employee?.name || t('undefined'),
    department: record.employee?.department || '—',
    clock_in_time: formatTimeOnly(record.clock_in),
    clock_out_time: formatTimeOnly(record.clock_out),
    duration: calculateDuration(record.clock_in, record.clock_out)
})));

const filteredRecords = computed(() => {
    return attendanceRecords.value.filter((record) => {
        const matchesQuery = !searchQuery.value.trim() || matchesSearch([
            record.employeeName,
            record.department,
            record.notes
        ], searchQuery.value);

        const matchesEmp = !selectedEmployeeId.value || record.employee_id === selectedEmployeeId.value;
        const matchesDate = !selectedDate.value || String(record.date || '').slice(0, 10) === selectedDate.value;
        const matchesStat = !selectedStatus.value || String(record.status).toLowerCase() === selectedStatus.value.toLowerCase();

        return matchesQuery && matchesEmp && matchesDate && matchesStat;
    });
});

const presentCount = computed(() => attendanceRecords.value.filter((r) => String(r.status).toLowerCase() === 'present').length);
const lateCount = computed(() => attendanceRecords.value.filter((r) => String(r.status).toLowerCase() === 'late').length);
const absentCount = computed(() => attendanceRecords.value.filter((r) => String(r.status).toLowerCase() === 'absent').length);
const remoteCount = computed(() => attendanceRecords.value.filter((r) => String(r.status).toLowerCase() === 'remote').length);

const loadAttendance = async () => {
    const params = {};
    if (selectedDate.value) params.date = selectedDate.value;
    if (selectedEmployeeId.value) params.employee_id = selectedEmployeeId.value;
    if (selectedStatus.value) params.status = selectedStatus.value;
    await attendanceStore.fetchAttendance(params).catch(() => {});
};

const onFilterChange = () => {
    loadAttendance();
};

const createAttendance = () => {
    router.push({ name: 'admin.hr.attendance.create' });
};

const editAttendance = (record) => {
    if (!record?.id) return;
    router.push({ name: 'admin.hr.attendance.edit', params: { id: record.id } });
};

const quickClockOut = async (record) => {
    clockingOutId.value = record.id;
    try {
        const now = new Date();
        const dateStr = record.date ? String(record.date).slice(0, 10) : now.toISOString().slice(0, 10);
        const timeStr = now.toTimeString().slice(0, 8);
        const clockOutVal = `${dateStr} ${timeStr}`;

        await attendanceStore.updateAttendance(record.id, {
            clock_out: clockOutVal
        });
        ElMessage.success(t('attendance_recorded') || 'تم تسجيل وقت الانصراف بنجاح');
        await loadAttendance();
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('action_failed') || 'فشل تسجيل الانصراف');
    } finally {
        clockingOutId.value = null;
    }
};

const deleteAttendance = async (record) => {
    try {
        await ElMessageBox.confirm(
            t('confirm_delete_record') || 'هل أنت متأكد من حذف هذا السجل؟',
            t('confirm_deletion') || 'تأكيد الحذف',
            {
                confirmButtonText: t('yes') || 'نعم',
                cancelButtonText: t('no') || 'إلغاء',
                type: 'warning'
            }
        );

        await attendanceStore.deleteAttendance(record.id);
        ElMessage.success(t('deleted_successfully') || 'تم الحذف بنجاح');
    } catch (err) {
        if (err !== 'cancel') {
            ElMessage.error(t('failed_to_delete') || 'فشل حذف السجل');
        }
    }
};

const openQuickClockDialog = () => {
    const now = new Date();
    quickClockForm.employee_id = null;
    quickClockForm.date = now.toISOString().slice(0, 10);
    quickClockForm.status = 'present';
    quickClockForm.clock_in_time = now.toTimeString().slice(0, 5);
    quickClockForm.clock_out_time = '';
    quickClockDialogVisible.value = true;
};

const saveQuickClock = async () => {
    if (!quickClockForm.employee_id) {
        ElMessage.warning(t('employee_required') || 'يرجى اختيار الموظف');
        return;
    }

    quickClockSaving.value = true;
    try {
        const payload = {
            employee_id: quickClockForm.employee_id,
            date: quickClockForm.date,
            status: quickClockForm.status,
            clock_in: quickClockForm.clock_in_time ? `${quickClockForm.date} ${quickClockForm.clock_in_time}:00` : null,
            clock_out: quickClockForm.clock_out_time ? `${quickClockForm.date} ${quickClockForm.clock_out_time}:00` : null
        };

        await attendanceStore.createAttendance(payload);
        ElMessage.success(t('attendance_recorded') || 'تم تسجيل الحضور بنجاح');
        quickClockDialogVisible.value = false;
        await loadAttendance();
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('an_error_occurred_while_saving_data') || 'حدث خطأ أثناء الحفظ');
    } finally {
        quickClockSaving.value = false;
    }
};

onMounted(async () => {
    await Promise.all([
        employeesStore.fetchEmployees().catch(() => {}),
        loadAttendance()
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

.font-medium {
    font-weight: 600;
    color: #1e293b;
}

.text-success { color: #16a34a; }
.text-warning { color: #d97706; }
.text-danger { color: #dc2626; }
.text-info { color: #0284c7; }
.text-muted { color: #64748b; }

.error-state,
.empty-state {
    padding: 2.5rem 1rem;
    text-align: center;
    color: #64748b;
}
</style>
