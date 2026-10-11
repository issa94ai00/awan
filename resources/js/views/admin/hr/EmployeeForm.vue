<template>
    <div class="hr-page hr-employee-form">
        <AdminPageHeader
            :title="formTitle"
            :subtitle="$t('add_or_edit_employee_data')"
        >
            <template #actions>
                <el-button @click="cancel">{{ $t('cancel') }}</el-button>
                <el-button type="primary" @click="submitForm" :loading="store.loading">
                    {{ submitLabel }}
                </el-button>
            </template>
        </AdminPageHeader>

        <el-card shadow="hover" class="form-card">
            <el-form :model="form" :rules="rules" ref="employeeForm" label-position="top" status-icon>
                <!-- Section 1: Basic & Contact Info -->
                <div class="form-section-title">
                    <el-icon><User /></el-icon>
                    <span>{{ $t('personal_info') }}</span>
                </div>

                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('name')" prop="name" :error="serverErrors.name && serverErrors.name[0]">
                            <el-input v-model="form.name" :placeholder="$t('enter_the_employee_s_name')" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('status')" prop="status" :error="serverErrors.status && serverErrors.status[0]">
                            <el-select v-model="form.status" :placeholder="$t('choose_the_status')" style="width: 100%">
                                <el-option :label="$t('active')" value="نشط" />
                                <el-option :label="$t('inactive')" value="غير نشط" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('email')" prop="email" :error="serverErrors.email && serverErrors.email[0]">
                            <el-input type="email" v-model="form.email" placeholder="example@domain.com" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('phone')" prop="phone" :error="serverErrors.phone && serverErrors.phone[0]">
                            <el-input v-model="form.phone" placeholder="+966 50 000 0000" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('national_id')" prop="national_id" :error="serverErrors.national_id && serverErrors.national_id[0]">
                            <el-input v-model="form.national_id" :placeholder="$t('national_id')" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('nationality')" prop="nationality" :error="serverErrors.nationality && serverErrors.nationality[0]">
                            <el-input v-model="form.nationality" :placeholder="$t('nationality')" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('photo')" prop="avatar" :error="serverErrors.avatar && serverErrors.avatar[0]">
                            <el-input v-model="form.avatar" :placeholder="$t('employee_photo_link')" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <!-- Section 2: Employment & Job -->
                <el-divider />
                <div class="form-section-title">
                    <el-icon><Briefcase /></el-icon>
                    <span>{{ $t('job_info') }}</span>
                </div>

                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('department')" prop="department" :error="serverErrors.department && serverErrors.department[0]">
                            <el-input v-model="form.department" :placeholder="$t('enter_the_section')" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('position')" prop="position" :error="serverErrors.position && serverErrors.position[0]">
                            <el-input v-model="form.position" :placeholder="$t('enter_the_job')" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('job_title')" prop="job_title" :error="serverErrors.job_title && serverErrors.job_title[0]">
                            <el-input v-model="form.job_title" :placeholder="$t('job_title')" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('employment_type')" prop="employment_type">
                            <el-select v-model="form.employment_type" :placeholder="$t('employment_type')" style="width: 100%" clearable>
                                <el-option :label="$t('full_time')" value="دوام كامل" />
                                <el-option :label="$t('part_time')" value="دوام جزئي" />
                                <el-option :label="$t('contract_val')" value="عقد محدد" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('appointment_date')" prop="hire_date" :error="serverErrors.hire_date && serverErrors.hire_date[0]">
                            <el-date-picker
                                v-model="form.hire_date"
                                type="date"
                                :placeholder="$t('choose_appointment_date')"
                                format="YYYY-MM-DD"
                                value-format="YYYY-MM-DD"
                                style="width: 100%;"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('contract_type')" prop="contract_type">
                            <el-input v-model="form.contract_type" :placeholder="$t('contract_type')" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('contract_start')" prop="contract_start">
                            <el-date-picker
                                v-model="form.contract_start"
                                type="date"
                                format="YYYY-MM-DD"
                                value-format="YYYY-MM-DD"
                                style="width: 100%;"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('contract_end')" prop="contract_end">
                            <el-date-picker
                                v-model="form.contract_end"
                                type="date"
                                format="YYYY-MM-DD"
                                value-format="YYYY-MM-DD"
                                style="width: 100%;"
                            />
                        </el-form-item>
                    </el-col>
                </el-row>

                <!-- Section 3: Financials & Compensation -->
                <el-divider />
                <div class="form-section-title">
                    <el-icon><Money /></el-icon>
                    <span>{{ $t('financial_info') }}</span>
                </div>

                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('basic_salary')" prop="salary" :error="serverErrors.salary && serverErrors.salary[0]">
                            <el-input-number v-model="form.salary" :min="0" :precision="2" :step="100" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('bonus')" prop="bonus">
                            <el-input-number v-model="form.bonus" :min="0" :precision="2" :step="50" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('commission_rate')" prop="commission_rate">
                            <el-input-number v-model="form.commission_rate" :min="0" :max="100" :precision="2" :step="0.5" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('monthly_sales_target')" prop="monthly_sales_target">
                            <el-input-number v-model="form.monthly_sales_target" :min="0" :precision="2" :step="1000" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('bank_name')" prop="bank_name">
                            <el-input v-model="form.bank_name" :placeholder="$t('bank_name')" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('bank_account_number')" prop="bank_account_number">
                            <el-input v-model="form.bank_account_number" :placeholder="$t('bank_account_number')" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <!-- Section 4: Emergency Contact -->
                <el-divider />
                <div class="form-section-title">
                    <el-icon><Phone /></el-icon>
                    <span>{{ $t('contact_emergency') }}</span>
                </div>

                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('emergency_contact_name')" prop="emergency_contact_name">
                            <el-input v-model="form.emergency_contact_name" :placeholder="$t('emergency_contact_name')" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('emergency_contact_phone')" prop="emergency_contact_phone">
                            <el-input v-model="form.emergency_contact_phone" :placeholder="$t('emergency_contact_phone')" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <!-- Section 5: System & Field App Access -->
                <el-divider />
                <div class="form-section-title">
                    <el-icon><Key /></el-icon>
                    <span>{{ $t('system_access') }}</span>
                </div>

                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('password')" prop="password" :error="serverErrors.password && serverErrors.password[0]">
                            <el-input v-model="form.password" type="password" show-password :placeholder="$t('password_optional')" autocomplete="new-password" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('confirm_password')" prop="confirm_password">
                            <el-input v-model="form.confirm_password" type="password" show-password :placeholder="$t('confirm_password')" autocomplete="new-password" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('linked_warehouse')" prop="warehouse_id" :error="serverErrors.warehouse_id && serverErrors.warehouse_id[0]">
                            <el-select
                                v-model="form.warehouse_id"
                                :placeholder="$t('choose_linked_warehouse')"
                                :loading="warehousesLoading"
                                clearable
                                filterable
                                style="width: 100%;"
                            >
                                <el-option
                                    v-for="warehouse in warehouses"
                                    :key="warehouse.id"
                                    :label="warehouse.code ? `${warehouse.name} (${warehouse.code})` : warehouse.name"
                                    :value="warehouse.id"
                                />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>

                <p v-if="isEdit" class="password-hint">{{ $t('leave_blank_to_keep') }}</p>

                <el-alert
                    v-if="isEdit && currentHasLogin === false && !form.password"
                    :title="$t('employee_has_no_login')"
                    :description="$t('employee_has_no_login_hint')"
                    type="info"
                    show-icon
                    :closable="false"
                    class="access-alert"
                />

                <el-alert
                    v-if="grantsUnrestrictedAccess"
                    :title="$t('warehouse_unrestricted_warning')"
                    :description="$t('warehouse_unrestricted_hint')"
                    type="warning"
                    show-icon
                    :closable="false"
                    class="access-alert"
                />

                <el-alert
                    v-if="warehouseWithoutLogin"
                    :title="$t('warehouse_without_login_warning')"
                    :description="$t('warehouse_without_login_hint')"
                    type="info"
                    show-icon
                    :closable="false"
                    class="access-alert"
                />

                <!-- Section 6: Internal Notes -->
                <el-divider />
                <div class="form-section-title">
                    <el-icon><Document /></el-icon>
                    <span>{{ $t('notes') }}</span>
                </div>

                <el-form-item :label="$t('comments')" prop="notes">
                    <el-input type="textarea" v-model="form.notes" :placeholder="$t('add_notes')" rows="3" />
                </el-form-item>

                <!-- Actions -->
                <div class="form-actions">
                    <el-button type="primary" @click="submitForm" :loading="store.loading">
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
import { useEmployeesStore } from '@/stores/employees';
import { inventoryApi } from '@/api/inventory';
import {
    User,
    Briefcase,
    Money,
    Phone,
    Key,
    Document
} from '@element-plus/icons-vue';

const { t } = useI18n();
const router = useRouter();
const route = useRoute();
const store = useEmployeesStore();
const employeeForm = ref(null);

const warehouses = ref([]);
const warehousesLoading = ref(false);
const currentHasLogin = ref(null);

const form = ref({
    name: '',
    department: '',
    position: '',
    job_title: '',
    status: 'نشط',
    email: '',
    phone: '',
    national_id: '',
    nationality: '',
    hire_date: '',
    employment_type: '',
    contract_type: '',
    contract_start: '',
    contract_end: '',
    salary: 0,
    bonus: 0,
    commission_rate: 0,
    monthly_sales_target: 0,
    bank_name: '',
    bank_account_number: '',
    emergency_contact_name: '',
    emergency_contact_phone: '',
    avatar: '',
    warehouse_id: null,
    password: '',
    confirm_password: '',
    notes: ''
});

const rules = {
    name: [{ required: true, message: t('name_required') || 'الاسم مطلوب', trigger: 'blur' }],
    department: [{ required: true, message: t('section_is_required') || 'القسم مطلوب', trigger: 'blur' }],
    position: [{ required: true, message: t('position_required') || 'المسمى الوظيفي مطلوب', trigger: 'blur' }],
    email: [
        { required: true, message: t('postage_is_required') || 'البريد الإلكتروني مطلوب', trigger: 'blur' },
        { type: 'email', message: t('the_mail_must_be_valid') || 'صيغة البريد الإلكتروني غير صحيحة', trigger: ['blur', 'change'] }
    ],
    phone: [{ required: true, message: t('phone_required') || 'رقم الهاتف مطلوب', trigger: 'blur' }],
    hire_date: [{ required: true, message: t('appointment_date_required') || 'تاريخ التعيين مطلوب', trigger: 'change' }],
    password: [
        {
            validator: (rule, value, callback) => {
                if (value && value.length < 8) {
                    callback(new Error(t('password_min') || 'كلمة المرور يجب أن لا تقل عن 8 أحرف'));
                } else {
                    callback();
                }
            },
            trigger: 'blur'
        }
    ],
    confirm_password: [
        {
            validator: (rule, value, callback) => {
                if (value !== form.value.password) {
                    callback(new Error(t('password_mismatch') || 'كلمتا المرور غير متطابقتين'));
                } else {
                    callback();
                }
            },
            trigger: ['blur', 'change']
        }
    ]
};

const isEdit = computed(() => !!route.params.id);
const formTitle = computed(() => (isEdit.value ? t('modify_an_employee') || 'تعديل بيانات موظف' : t('add_an_employee') || 'إضافة موظف'));
const submitLabel = computed(() => (isEdit.value ? t('update') || 'تحديث' : t('save') || 'حفظ'));

const willHaveLogin = computed(() => !!form.value.password || currentHasLogin.value === true);
const grantsUnrestrictedAccess = computed(() => willHaveLogin.value && !form.value.warehouse_id);
const warehouseWithoutLogin = computed(() => !!form.value.warehouse_id && !willHaveLogin.value);
const serverErrors = computed(() => store.validationErrors || {});

const loadWarehouses = async () => {
    warehousesLoading.value = true;
    try {
        const res = await inventoryApi.getWarehouses({ per_page: 500, is_active: true });
        warehouses.value = res.data?.data || [];
    } catch (err) {
        console.error('Warehouses load error:', err);
    } finally {
        warehousesLoading.value = false;
    }
};

const loadEmployee = async () => {
    if (!isEdit.value) return;
    const employee = await store.fetchEmployee(route.params.id).catch(() => null);
    if (!employee) {
        ElMessage.error(t('no_employee_data_found') || 'لم يتم العثور على بيانات الموظف');
        router.push({ name: 'admin.hr.employees' });
        return;
    }
    currentHasLogin.value = employee.has_login ?? (employee.user_id != null);
    form.value = {
        name: employee.name || '',
        department: employee.department || '',
        position: employee.position || '',
        job_title: employee.job_title || '',
        status: employee.status || 'نشط',
        email: employee.email || '',
        phone: employee.phone || '',
        national_id: employee.national_id || '',
        nationality: employee.nationality || '',
        hire_date: employee.hire_date ? String(employee.hire_date).slice(0, 10) : '',
        employment_type: employee.employment_type || '',
        contract_type: employee.contract_type || '',
        contract_start: employee.contract_start ? String(employee.contract_start).slice(0, 10) : '',
        contract_end: employee.contract_end ? String(employee.contract_end).slice(0, 10) : '',
        salary: Number(employee.salary) || 0,
        bonus: Number(employee.bonus) || 0,
        commission_rate: Number(employee.commission_rate) || 0,
        monthly_sales_target: Number(employee.monthly_sales_target) || 0,
        bank_name: employee.bank_name || '',
        bank_account_number: employee.bank_account_number || '',
        emergency_contact_name: employee.emergency_contact_name || '',
        emergency_contact_phone: employee.emergency_contact_phone || '',
        avatar: employee.avatar || '',
        warehouse_id: employee.warehouse_id ?? null,
        password: '',
        confirm_password: '',
        notes: employee.notes || ''
    };
};

const buildPayload = () => {
    const payload = { ...form.value };
    delete payload.confirm_password;
    if (!payload.password) delete payload.password;
    payload.warehouse_id = payload.warehouse_id || null;
    return payload;
};

const submitForm = () => {
    employeeForm.value.validate(async (valid) => {
        if (!valid) return;
        try {
            const payload = buildPayload();
            if (isEdit.value) {
                await store.updateEmployee(route.params.id, payload);
                ElMessage.success(t('employee_data_has_been_updated') || 'تم تحديث بيانات الموظف بنجاح');
            } else {
                await store.createEmployee(payload);
                ElMessage.success(t('the_employee_has_been_added_successfully') || 'تمت إضافة الموظف بنجاح');
            }
            router.push({ name: 'admin.hr.employees' });
        } catch (err) {
            ElMessage.error(err.response?.data?.message || err.message || t('an_error_occurred_while_saving_data') || 'حدث خطأ أثناء حفظ البيانات');
        }
    });
};

const cancel = () => {
    router.push({ name: 'admin.hr.employees' });
};

onMounted(() => {
    loadWarehouses();
    loadEmployee();
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

.form-section-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.05rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 1.25rem;
}

.form-section-title .el-icon {
    font-size: 1.2rem;
    color: #3b82f6;
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
    padding-top: 1.25rem;
    border-top: 1px solid #f1f5f9;
}

.password-hint {
    margin: -0.25rem 0 0.75rem;
    font-size: 0.85rem;
    color: #8a94a6;
}

.access-alert {
    margin-bottom: 0.75rem;
}
</style>
