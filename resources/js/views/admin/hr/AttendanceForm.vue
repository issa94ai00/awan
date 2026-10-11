<template>
    <div class="hr-page hr-attendance-form">
        <AdminPageHeader
            :title="formTitle"
            :subtitle="$t('record_employee_attendance_and_edit')"
        >
            <template #actions>
                <el-button @click="cancel">{{ $t('cancel') }}</el-button>
                <el-button type="primary" @click="submitForm" :loading="attendanceStore.loading">
                    {{ submitLabel }}
                </el-button>
            </template>
        </AdminPageHeader>

        <el-card shadow="hover" class="form-card">
            <el-form :model="form" :rules="rules" ref="attendanceForm" label-position="top" status-icon>
                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('employee')" prop="employee_id" :error="serverErrors.employee_id && serverErrors.employee_id[0]">
                            <el-select
                                v-model="form.employee_id"
                                :placeholder="$t('select_employee')"
                                filterable
                                style="width: 100%"
                            >
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
                            <el-select v-model="form.status" :placeholder="$t('choose_the_status')" style="width: 100%">
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
                    <el-button type="primary" @click="submitForm" :loading="attendanceStore.loading">
                        {{ submitLabel }}
                    </el-button>
                    <el-button @click="cancel">{{ $t('cancel') }}</el-button>
                </div>
            </el-form>
        </el-card>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import { useAttendanceStore } from '@/stores/attendance';
import { useEmployeesStore } from '@/stores/employees';

const { t } = useI18n();
const router = useRouter();
const route = useRoute();
const attendanceStore = useAttendanceStore();
const employeesStore = useEmployeesStore();
const attendanceForm = ref(null);

const form = ref({
    employee_id: null,
    date: new Date().toISOString().slice(0, 10),
    clock_in: '',
    clock_out: '',
    status: 'present',
    notes: ''
});

const rules = {
    employee_id: [{ required: true, message: t('employee_required') || 'الموظف مطلوب', trigger: 'change' }],
    date: [{ required: true, message: t('date_required') || 'التاريخ مطلوب', trigger: 'change' }],
    status: [{ required: true, message: t('status_is_required') || 'الحالة مطلوبة', trigger: 'change' }]
};

const isEdit = computed(() => !!route.params.id);
const formTitle = computed(() => (isEdit.value ? t('edit_attendance_record') || 'تعديل سجل الحضور' : t('new_attendance_record') || 'تسجيل حضور جديد'));
const submitLabel = computed(() => (isEdit.value ? t('update') || 'تحديث' : t('save') || 'حفظ'));
const employees = computed(() => employeesStore.employees || []);
const serverErrors = computed(() => attendanceStore.validationErrors || {});

const loadEmployees = async () => {
    await employeesStore.fetchEmployees().catch(() => {});
};

const loadRecord = async () => {
    if (!isEdit.value) return;
    const record = await attendanceStore.fetchAttendanceRecord(route.params.id).catch(() => null);
    if (!record) {
        ElMessage.error(t('no_attendance_record_found') || 'لم يتم العثور على سجل الحضور');
        router.push({ name: 'admin.hr.attendance' });
        return;
    }

    form.value = {
        employee_id: record.employee_id,
        date: record.date ? String(record.date).slice(0, 10) : '',
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
                ElMessage.success(t('attendance_recorded') || 'تم تحديث السجل بنجاح');
            } else {
                await attendanceStore.createAttendance(form.value);
                ElMessage.success(t('attendance_recorded') || 'تم إنشاء السجل بنجاح');
            }
            router.push({ name: 'admin.hr.attendance' });
        } catch (err) {
            ElMessage.error(err.response?.data?.message || err.message || t('an_error_occurred_while_saving_data') || 'حدث خطأ أثناء حفظ البيانات');
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

.form-card {
    border-radius: 1rem;
    border: 1px solid #eef2f6;
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
    padding-top: 1.25rem;
    border-top: 1px solid #f1f5f9;
}
</style>
