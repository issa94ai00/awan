<template>
    <div class="hr-page hr-attendance-form">
        <div class="page-header">
            <div class="page-title">
                <h1>{{ formTitle }}</h1>
                <p>{{ $t('record_employee_attendance_and_edit') }}</p>
            </div>
        </div>

        <el-card shadow="hover" class="form-card">
            <el-form :model="form" :rules="rules" ref="attendanceForm" label-position="top" status-icon>
                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('employee')" prop="employee_id" :error="serverErrors.employee_id && serverErrors.employee_id[0]">
                            <el-select v-model="form.employee_id" :placeholder="$t('select_employee')">
                                <el-option
                                    v-for="employee in employees"
                                    :key="employee.id"
                                    :label="employee.name"
                                    :value="employee.id"
                                />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('the_date')" prop="date" :error="serverErrors.date && serverErrors.date[0]">
                            <el-date-picker
                                v-model="form.date"
                                type="date"
                                :placeholder="$t('choose_the_date')"
                                format="YYYY-MM-DD"
                                value-format="YYYY-MM-DD"
                                style="width: 100%;"
                            />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('entrance')" prop="clock_in" :error="serverErrors.clock_in && serverErrors.clock_in[0]">
                            <el-date-picker
                                v-model="form.clock_in"
                                type="datetime"
                                :placeholder="$t('entry_time')"
                                format="YYYY-MM-DD HH:mm:ss"
                                value-format="YYYY-MM-DD HH:mm:ss"
                                style="width: 100%;"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('exit')" prop="clock_out" :error="serverErrors.clock_out && serverErrors.clock_out[0]">
                            <el-date-picker
                                v-model="form.clock_out"
                                type="datetime"
                                :placeholder="$t('check_out_time')"
                                format="YYYY-MM-DD HH:mm:ss"
                                value-format="YYYY-MM-DD HH:mm:ss"
                                style="width: 100%;"
                            />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('status')" prop="status" :error="serverErrors.status && serverErrors.status[0]">
                            <el-select v-model="form.status" :placeholder="$t('choose_the_status')">
                                <el-option :label="$t('present')" value="present" />
                                <el-option :label="$t('late')" value="late" />
                                <el-option :label="$t('absent')" value="absent" />
                                <el-option :label="$t('remote')" value="remote" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('comments')" prop="notes" :error="serverErrors.notes && serverErrors.notes[0]">
                            <el-input type="textarea" v-model="form.notes" :placeholder="$t('add_notes')" rows="3" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <div class="form-actions">
                    <el-button type="primary" @click="submitForm" :loading="attendanceStore.loading">{{ submitLabel }}</el-button>
                    <el-button type="text" @click="cancel">{{ $t('cancel') }}</el-button>
                </div>
            </el-form>
        </el-card>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { ElMessage } from 'element-plus';
import { useAttendanceStore } from '@/stores/attendance';
import { useEmployeesStore } from '@/stores/employees';

const router = useRouter();
const route = useRoute();
const attendanceStore = useAttendanceStore();
const employeesStore = useEmployeesStore();
const attendanceForm = ref(null);

const form = ref({
    employee_id: null,
    date: '',
    clock_in: '',
    clock_out: '',
    status: 'present',
    notes: ''
});

const rules = {
    employee_id: [{ required: true, message: window.t('employee_required'), trigger: 'change' }],
    date: [{ required: true, message: window.t('date_required'), trigger: 'change' }],
    status: [{ required: true, message: window.t('status_is_required'), trigger: 'change' }]
};

const isEdit = computed(() => !!route.params.id);
const formTitle = computed(() => (isEdit.value ? window.t('edit_attendance_record') : window.t('new_attendance_record')));
const submitLabel = computed(() => (isEdit.value ? window.t('update') : window.t('save')));
const employees = computed(() => employeesStore.employees || []);
const serverErrors = computed(() => attendanceStore.validationErrors || {});

const loadEmployees = async () => {
    await employeesStore.fetchEmployees().catch(() => {});
};

const loadRecord = async () => {
    if (!isEdit.value) return;
    const record = await attendanceStore.fetchAttendanceRecord(route.params.id).catch(() => null);
    if (!record) {
        ElMessage.error(window.t('no_attendance_record_found'));
        router.push({ name: 'admin.hr.attendance' });
        return;
    }

    form.value = {
        employee_id: record.employee_id,
        date: record.date || '',
        clock_in: record.clock_in || '',
        clock_out: record.clock_out || '',
        status: record.status || 'present',
        notes: record.notes || ''
    };
};

const submitForm = () => {
    attendanceForm.value.validate(async (valid) => {
        if (!valid) return;

        try {
            if (isEdit.value) {
                await attendanceStore.updateAttendance(route.params.id, form.value);
                ElMessage.success(window.t('the_attendance_record_has_been'));
            } else {
                await attendanceStore.createAttendance(form.value);
                ElMessage.success(window.t('the_attendance_record_has_been'));
            }
            router.push({ name: 'admin.hr.attendance' });
        } catch (err) {
            ElMessage.error(err.response?.data?.message || err.message || window.t('an_error_occurred_while_saving'));
        }
    });
};

const cancel = () => {
    router.push({ name: 'admin.hr.attendance' });
};

onMounted(async () => {
    await loadEmployees();
    await loadRecord();
});
</script>

<style scoped>
.hr-page {
    padding: 0;
}

.page-header {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.page-title h1 {
    margin: 0;
    font-size: 1.8rem;
    font-weight: 700;
}

.form-card {
    border-radius: 1rem;
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
}
</style>
