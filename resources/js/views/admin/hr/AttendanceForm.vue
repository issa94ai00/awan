<template>
    <div class="hr-page hr-attendance-form">
        <div class="page-header">
            <div class="page-title">
                <h1>{{ formTitle }}</h1>
                <p>سجل حضور الموظف وحرر التوقيت والحالة بسهولة.</p>
            </div>
        </div>

        <el-card shadow="hover" class="form-card">
            <el-form :model="form" :rules="rules" ref="attendanceForm" label-position="top" status-icon>
                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item label="الموظف" prop="employee_id" :error="serverErrors.employee_id && serverErrors.employee_id[0]">
                            <el-select v-model="form.employee_id" placeholder="اختر الموظف">
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
                        <el-form-item label="التاريخ" prop="date" :error="serverErrors.date && serverErrors.date[0]">
                            <el-date-picker
                                v-model="form.date"
                                type="date"
                                placeholder="اختر التاريخ"
                                format="YYYY-MM-DD"
                                value-format="YYYY-MM-DD"
                                style="width: 100%;"
                            />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item label="دخول" prop="clock_in" :error="serverErrors.clock_in && serverErrors.clock_in[0]">
                            <el-date-picker
                                v-model="form.clock_in"
                                type="datetime"
                                placeholder="وقت الدخول"
                                format="YYYY-MM-DD HH:mm:ss"
                                value-format="YYYY-MM-DD HH:mm:ss"
                                style="width: 100%;"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item label="خروج" prop="clock_out" :error="serverErrors.clock_out && serverErrors.clock_out[0]">
                            <el-date-picker
                                v-model="form.clock_out"
                                type="datetime"
                                placeholder="وقت الخروج"
                                format="YYYY-MM-DD HH:mm:ss"
                                value-format="YYYY-MM-DD HH:mm:ss"
                                style="width: 100%;"
                            />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item label="الحالة" prop="status" :error="serverErrors.status && serverErrors.status[0]">
                            <el-select v-model="form.status" placeholder="اختر الحالة">
                                <el-option label="حاضر" value="present" />
                                <el-option label="متأخر" value="late" />
                                <el-option label="غائب" value="absent" />
                                <el-option label="عن بُعد" value="remote" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item label="ملاحظات" prop="notes" :error="serverErrors.notes && serverErrors.notes[0]">
                            <el-input type="textarea" v-model="form.notes" placeholder="أضف ملاحظات" rows="3" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <div class="form-actions">
                    <el-button type="primary" @click="submitForm" :loading="attendanceStore.loading">{{ submitLabel }}</el-button>
                    <el-button type="text" @click="cancel">إلغاء</el-button>
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
    employee_id: [{ required: true, message: 'الموظف مطلوب', trigger: 'change' }],
    date: [{ required: true, message: 'التاريخ مطلوب', trigger: 'change' }],
    status: [{ required: true, message: 'الحالة مطلوبة', trigger: 'change' }]
};

const isEdit = computed(() => !!route.params.id);
const formTitle = computed(() => (isEdit.value ? 'تعديل سجل الحضور' : 'سجل حضور جديد'));
const submitLabel = computed(() => (isEdit.value ? 'تحديث' : 'حفظ'));
const employees = computed(() => employeesStore.employees || []);
const serverErrors = computed(() => attendanceStore.validationErrors || {});

const loadEmployees = async () => {
    await employeesStore.fetchEmployees().catch(() => {});
};

const loadRecord = async () => {
    if (!isEdit.value) return;
    const record = await attendanceStore.fetchAttendanceRecord(route.params.id).catch(() => null);
    if (!record) {
        ElMessage.error('لم يتم العثور على سجل الحضور');
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
                ElMessage.success('تم تحديث سجل الحضور بنجاح');
            } else {
                await attendanceStore.createAttendance(form.value);
                ElMessage.success('تم إنشاء سجل الحضور بنجاح');
            }
            router.push({ name: 'admin.hr.attendance' });
        } catch (err) {
            ElMessage.error(err.response?.data?.message || err.message || 'حدث خطأ أثناء الحفظ');
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
