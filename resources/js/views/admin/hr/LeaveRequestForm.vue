<template>
    <div class="hr-page hr-leave-form">
        <div class="page-header">
            <div class="page-title">
                <h1>{{ formTitle }}</h1>
                <p>{{ $t('create_or_edit_a_leave') }}</p>
            </div>
        </div>

        <el-card shadow="hover" class="form-card">
            <el-form :model="form" :rules="rules" ref="leaveForm" label-position="top" status-icon>
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
                        <el-form-item :label="$t('leave_type')" prop="leave_type" :error="serverErrors.leave_type && serverErrors.leave_type[0]">
                            <el-input v-model="form.leave_type" :placeholder="$t('example_annual_leave')" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
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
                    <el-col :xs="24" :sm="12">
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
                </el-row>

                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('status')" prop="status" :error="serverErrors.status && serverErrors.status[0]">
                            <el-select v-model="form.status" :placeholder="$t('choose_the_status')">
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
                    <el-input type="textarea" v-model="form.notes" :placeholder="$t('additional_notes')" rows="4" />
                </el-form-item>

                <div class="form-actions">
                    <el-button type="primary" @click="submitForm" :loading="leaveStore.loading">{{ submitLabel }}</el-button>
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
    employee_id: [{ required: true, message: window.t('employee_required'), trigger: 'change' }],
    leave_type: [{ required: true, message: window.t('leave_type_required'), trigger: 'blur' }],
    start_date: [{ required: true, message: window.t('start_date_required'), trigger: 'change' }],
    end_date: [{ required: true, message: window.t('end_date_required'), trigger: 'change' }],
    status: [{ required: true, message: window.t('status_is_required'), trigger: 'change' }]
};

const isEdit = computed(() => !!route.params.id);
const formTitle = computed(() => (isEdit.value ? window.t('modifying_leave_request') : window.t('new_leave_request')));
const submitLabel = computed(() => (isEdit.value ? window.t('update') : window.t('save')));
const employees = computed(() => employeesStore.employees || []);
const serverErrors = computed(() => leaveStore.validationErrors || {});

const loadEmployees = async () => {
    await employeesStore.fetchEmployees().catch(() => {});
};

const loadRequest = async () => {
    if (!isEdit.value) return;
    const request = await leaveStore.fetchLeaveRequest(route.params.id).catch(() => null);
    if (!request) {
        ElMessage.error(window.t('leave_request_not_found'));
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
                ElMessage.success(window.t('your_leave_request_has_been'));
            } else {
                await leaveStore.createLeaveRequest(form.value);
                ElMessage.success(window.t('leave_request_has_been_created'));
            }
            router.push({ name: 'admin.hr.leaves' });
        } catch (err) {
            ElMessage.error(err.response?.data?.message || err.message || window.t('an_error_occurred_while_saving'));
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
