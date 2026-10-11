<template>
    <div class="hr-page hr-leave-form">
        <AdminPageHeader
            :title="formTitle"
            :subtitle="$t('create_or_edit_a_leave')"
        >
            <template #actions>
                <el-button @click="cancel">{{ $t('cancel') }}</el-button>
                <el-button type="primary" @click="submitForm" :loading="leaveStore.loading">
                    {{ submitLabel }}
                </el-button>
            </template>
        </AdminPageHeader>

        <el-card shadow="hover" class="form-card">
            <el-form :model="form" :rules="rules" ref="leaveForm" label-position="top" status-icon>
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
                        <el-form-item :label="$t('leave_type')" prop="leave_type" :error="serverErrors.leave_type && serverErrors.leave_type[0]">
                            <el-select
                                v-model="form.leave_type"
                                :placeholder="$t('leave_type')"
                                filterable
                                allow-create
                                default-first-option
                                style="width: 100%"
                            >
                                <el-option label="إجازة سنوية (Annual Leave)" value="إجازة سنوية" />
                                <el-option label="إجازة مرضية (Sick Leave)" value="إجازة مرضية" />
                                <el-option label="إجازة اضطرارية (Emergency Leave)" value="إجازة اضطرارية" />
                                <el-option label="إجازة بدون راتب (Unpaid Leave)" value="إجازة بدون راتب" />
                                <el-option label="إجازة أمومة / رعاية (Maternity Leave)" value="إجازة أمومة" />
                                <el-option label="أخرى (Other)" value="أخرى" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20">
                    <el-col :xs="24" :sm="10">
                        <el-form-item :label="$t('start_date')" prop="start_date" :error="serverErrors.start_date && serverErrors.start_date[0]">
                            <el-date-picker
                                v-model="form.start_date"
                                type="date"
                                :placeholder="$t('choose_a_start_date')"
                                format="YYYY-MM-DD"
                                value-format="YYYY-MM-DD"
                                style="width: 100%;"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="10">
                        <el-form-item :label="$t('end_date')" prop="end_date" :error="serverErrors.end_date && serverErrors.end_date[0]">
                            <el-date-picker
                                v-model="form.end_date"
                                type="date"
                                :placeholder="$t('choose_an_end_date')"
                                format="YYYY-MM-DD"
                                value-format="YYYY-MM-DD"
                                style="width: 100%;"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="4">
                        <el-form-item :label="$t('duration_days')">
                            <div class="days-badge-box">
                                <el-tag v-if="calculatedDays" size="large" type="primary" effect="dark">
                                    {{ calculatedDays }} {{ $t('duration_days') }}
                                </el-tag>
                                <span v-else class="text-muted">—</span>
                            </div>
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('status')" prop="status" :error="serverErrors.status && serverErrors.status[0]">
                            <el-select v-model="form.status" :placeholder="$t('choose_the_status')" style="width: 100%">
                                <el-option :label="$t('suspended')" value="pending" />
                                <el-option :label="$t('agreed')" value="approved" />
                                <el-option :label="$t('rejected')" value="rejected" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('the_reason')" prop="reason" :error="serverErrors.reason && serverErrors.reason[0]">
                            <el-input type="textarea" v-model="form.reason" :placeholder="$t('enter_the_reason_for_the_leave')" rows="3" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-form-item :label="$t('comments')" prop="notes" :error="serverErrors.notes && serverErrors.notes[0]">
                    <el-input type="textarea" v-model="form.notes" :placeholder="$t('additional_notes')" rows="3" />
                </el-form-item>

                <div class="form-actions">
                    <el-button type="primary" @click="submitForm" :loading="leaveStore.loading">
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
import { useLeaveRequestsStore } from '@/stores/leaveRequests';
import { useEmployeesStore } from '@/stores/employees';

const { t } = useI18n();
const router = useRouter();
const route = useRoute();
const leaveStore = useLeaveRequestsStore();
const employeesStore = useEmployeesStore();
const leaveForm = ref(null);

const form = ref({
    employee_id: null,
    leave_type: 'إجازة سنوية',
    start_date: new Date().toISOString().slice(0, 10),
    end_date: new Date().toISOString().slice(0, 10),
    status: 'pending',
    reason: '',
    notes: ''
});

const calculatedDays = computed(() => {
    if (!form.value.start_date || !form.value.end_date) return null;
    const d1 = new Date(form.value.start_date);
    const d2 = new Date(form.value.end_date);
    const diff = Math.round((d2 - d1) / (1000 * 60 * 60 * 24)) + 1;
    return diff > 0 ? diff : 1;
});

const rules = {
    employee_id: [{ required: true, message: t('employee_required') || 'الموظف مطلوب', trigger: 'change' }],
    leave_type: [{ required: true, message: t('leave_type_required') || 'نوع الإجازة مطلوب', trigger: 'blur' }],
    start_date: [{ required: true, message: t('start_date_required') || 'تاريخ البداية مطلوب', trigger: 'change' }],
    end_date: [
        { required: true, message: t('end_date_required') || 'تاريخ النهاية مطلوب', trigger: 'change' },
        {
            validator: (rule, value, callback) => {
                if (form.value.start_date && value && value < form.value.start_date) {
                    callback(new Error(t('end_date_after_start') || 'تاريخ النهاية يجب أن يكون بعد تاريخ البداية'));
                } else {
                    callback();
                }
            },
            trigger: 'change'
        }
    ],
    status: [{ required: true, message: t('status_is_required') || 'الحالة مطلوبة', trigger: 'change' }]
};

const isEdit = computed(() => !!route.params.id);
const formTitle = computed(() => (isEdit.value ? t('modifying_leave_request') || 'تعديل طلب إجازة' : t('new_leave_request') || 'طلب إجازة جديد'));
const submitLabel = computed(() => (isEdit.value ? t('update') || 'تحديث' : t('save') || 'حفظ'));
const employees = computed(() => employeesStore.employees || []);
const serverErrors = computed(() => leaveStore.validationErrors || {});

const loadEmployees = async () => {
    await employeesStore.fetchEmployees().catch(() => {});
};

const loadRequest = async () => {
    if (!isEdit.value) return;
    const request = await leaveStore.fetchLeaveRequest(route.params.id).catch(() => null);
    if (!request) {
        ElMessage.error(t('leave_request_not_found') || 'لم يتم العثور على طلب الإجازة');
        router.push({ name: 'admin.hr.leaves' });
        return;
    }
    form.value = {
        employee_id: request.employee_id,
        leave_type: request.leave_type || '',
        start_date: request.start_date ? String(request.start_date).slice(0, 10) : '',
        end_date: request.end_date ? String(request.end_date).slice(0, 10) : '',
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
                ElMessage.success(t('leave_request_has_been_updated') || 'تم تحديث طلب الإجازة بنجاح');
            } else {
                await leaveStore.createLeaveRequest(form.value);
                ElMessage.success(t('leave_request_created_successfully') || 'تم تقديم طلب الإجازة بنجاح');
            }
            router.push({ name: 'admin.hr.leaves' });
        } catch (err) {
            ElMessage.error(err.response?.data?.message || err.message || t('an_error_occurred_while_saving_data') || 'حدث خطأ أثناء حفظ البيانات');
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

.form-card {
    border-radius: 1rem;
    border: 1px solid #eef2f6;
}

.days-badge-box {
    display: flex;
    align-items: center;
    height: 32px;
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
    padding-top: 1.25rem;
    border-top: 1px solid #f1f5f9;
}

.text-muted {
    color: #94a3b8;
}
</style>
