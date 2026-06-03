<template>
    <div class="hr-page hr-leave-form">
        <div class="page-header">
            <div class="page-title">
                <h1>{{ formTitle }}</h1>
                <p>إنشاء أو تعديل طلب إجازة مع معلومات الموظف وفترة الإجازة.</p>
            </div>
        </div>

        <el-card shadow="hover" class="form-card">
            <el-form :model="form" :rules="rules" ref="leaveForm" label-position="top" status-icon>
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
                        <el-form-item label="نوع الإجازة" prop="leave_type" :error="serverErrors.leave_type && serverErrors.leave_type[0]">
                            <el-input v-model="form.leave_type" placeholder="مثال: إجازة سنوية" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item label="تاريخ البداية" prop="start_date" :error="serverErrors.start_date && serverErrors.start_date[0]">
                            <el-date-picker
                                v-model="form.start_date"
                                type="date"
                                placeholder="اختر تاريخ البداية"
                                format="YYYY-MM-DD"
                                value-format="YYYY-MM-DD"
                                style="width: 100%;"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item label="تاريخ النهاية" prop="end_date" :error="serverErrors.end_date && serverErrors.end_date[0]">
                            <el-date-picker
                                v-model="form.end_date"
                                type="date"
                                placeholder="اختر تاريخ النهاية"
                                format="YYYY-MM-DD"
                                value-format="YYYY-MM-DD"
                                style="width: 100%;"
                            />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item label="الحالة" prop="status" :error="serverErrors.status && serverErrors.status[0]">
                            <el-select v-model="form.status" placeholder="اختر الحالة">
                                <el-option label="معلقة" value="pending" />
                                <el-option label="موافق عليها" value="approved" />
                                <el-option label="مرفوضة" value="rejected" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item label="السبب" prop="reason" :error="serverErrors.reason && serverErrors.reason[0]">
                            <el-input type="textarea" v-model="form.reason" placeholder="أدخل سبب الإجازة" rows="3" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-form-item label="ملاحظات" prop="notes" :error="serverErrors.notes && serverErrors.notes[0]">
                    <el-input type="textarea" v-model="form.notes" placeholder="ملاحظات إضافية" rows="4" />
                </el-form-item>

                <div class="form-actions">
                    <el-button type="primary" @click="submitForm" :loading="leaveStore.loading">{{ submitLabel }}</el-button>
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
import { useLeaveRequestsStore } from '@/stores/leaveRequests';
import { useEmployeesStore } from '@/stores/employees';

const router = useRouter();
const route = useRoute();
const leaveStore = useLeaveRequestsStore();
const employeesStore = useEmployeesStore();
const leaveForm = ref(null);

const form = ref({
    employee_id: null,
    leave_type: '',
    start_date: '',
    end_date: '',
    status: 'pending',
    reason: '',
    notes: ''
});

const rules = {
    employee_id: [{ required: true, message: 'الموظف مطلوب', trigger: 'change' }],
    leave_type: [{ required: true, message: 'نوع الإجازة مطلوب', trigger: 'blur' }],
    start_date: [{ required: true, message: 'تاريخ البداية مطلوب', trigger: 'change' }],
    end_date: [{ required: true, message: 'تاريخ النهاية مطلوب', trigger: 'change' }],
    status: [{ required: true, message: 'الحالة مطلوبة', trigger: 'change' }]
};

const isEdit = computed(() => !!route.params.id);
const formTitle = computed(() => (isEdit.value ? 'تعديل طلب الإجازة' : 'طلب إجازة جديد'));
const submitLabel = computed(() => (isEdit.value ? 'تحديث' : 'حفظ'));
const employees = computed(() => employeesStore.employees || []);
const serverErrors = computed(() => leaveStore.validationErrors || {});

const loadEmployees = async () => {
    await employeesStore.fetchEmployees().catch(() => {});
};

const loadRequest = async () => {
    if (!isEdit.value) return;
    const request = await leaveStore.fetchLeaveRequest(route.params.id).catch(() => null);
    if (!request) {
        ElMessage.error('لم يتم العثور على طلب الإجازة');
        router.push({ name: 'admin.hr.leaves' });
        return;
    }
    form.value = {
        employee_id: request.employee_id,
        leave_type: request.leave_type || '',
        start_date: request.start_date || '',
        end_date: request.end_date || '',
        status: request.status || 'pending',
        reason: request.reason || '',
        notes: request.notes || ''
    };
};

const submitForm = () => {
    leaveForm.value.validate(async (valid) => {
        if (!valid) return;

        try {
            if (isEdit.value) {
                await leaveStore.updateLeaveRequest(route.params.id, form.value);
                ElMessage.success('تم تحديث طلب الإجازة بنجاح');
            } else {
                await leaveStore.createLeaveRequest(form.value);
                ElMessage.success('تم إنشاء طلب الإجازة بنجاح');
            }
            router.push({ name: 'admin.hr.leaves' });
        } catch (err) {
            ElMessage.error(err.response?.data?.message || err.message || 'حدث خطأ أثناء الحفظ');
        }
    });
};

const cancel = () => {
    router.push({ name: 'admin.hr.leaves' });
};

onMounted(async () => {
    await loadEmployees();
    await loadRequest();
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
