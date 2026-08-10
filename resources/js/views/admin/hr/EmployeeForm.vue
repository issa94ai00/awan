<template>
    <div class="hr-page hr-employee-form">
        <div class="page-header">
            <div class="page-title">
                <h1>{{ formTitle }}</h1>
                <p>{{ $t('add_or_edit_employee_data') }}</p>
            </div>
        </div>

        <el-card shadow="hover" class="form-card">
            <el-form :model="form" :rules="rules" ref="employeeForm" label-position="top" status-icon>
                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('name')" prop="name" :error="serverErrors.name && serverErrors.name[0]">
                                    <el-input v-model="form.name" :placeholder="$t('enter_the_employee_s_name')" />
                                </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('department')" prop="department" :error="serverErrors.department && serverErrors.department[0]">
                            <el-input v-model="form.department" :placeholder="$t('enter_the_section')" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('position')" prop="position" :error="serverErrors.position && serverErrors.position[0]">
                            <el-input v-model="form.position" :placeholder="$t('enter_the_job')" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('status')" prop="status" :error="serverErrors.status && serverErrors.status[0]">
                            <el-select v-model="form.status" :placeholder="$t('choose_the_status')">
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
                        <el-form-item :label="$t('photo')" prop="avatar" :error="serverErrors.avatar && serverErrors.avatar[0]">
                            <el-input v-model="form.avatar" :placeholder="$t('employee_photo_link')" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <!--
                    Login and warehouse belong together: they are the two halves
                    of a field-app account. A password with no warehouse creates
                    an unrestricted account, and a warehouse with no password
                    creates someone who cannot sign in — so the form states both
                    consequences rather than leaving the admin to discover them.
                -->
                <el-divider content-position="left">{{ $t('field_app_access') }}</el-divider>

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

                <!--
                    The rule this warns about lives in App\Services\Field\FieldScope:
                    an employee with no warehouse is treated as unconfined, so the
                    account can act on every warehouse in the company. That is right
                    for back office and wrong for a branch seller, and it is not
                    something anyone should learn by accident.
                -->
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

                <div class="form-actions">
                    <el-button type="primary" @click="submitForm" :loading="store.loading">
                        {{ submitLabel }}
                    </el-button>
                    <el-button @click="cancel" type="text">{{ $t('cancel') }}</el-button>
                </div>
            </el-form>
        </el-card>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { ElMessage } from 'element-plus';
import { useEmployeesStore } from '@/stores/employees';
import { inventoryApi } from '@/api/inventory';

const router = useRouter();
const route = useRoute();
const store = useEmployeesStore();
const employeeForm = ref(null);

const warehouses = ref([]);
const warehousesLoading = ref(false);

/** Whether the employee being edited already has a login. Null while unknown. */
const currentHasLogin = ref(null);

const form = ref({
    name: '',
    department: '',
    position: '',
    status: window.t('active'),
    email: '',
    phone: '',
    hire_date: '',
    avatar: '/placeholder.jpg',
    warehouse_id: null,
    password: '',
    confirm_password: ''
});

const rules = {
    name: [{ required: true, message: window.t('name_required'), trigger: 'blur' }],
    department: [{ required: true, message: window.t('section_is_required'), trigger: 'blur' }],
    position: [{ required: true, message: window.t('position_required'), trigger: 'blur' }],
    email: [
        { required: true, message: window.t('postage_is_required'), trigger: 'blur' },
        { type: 'email', message: window.t('the_mail_must_be_valid'), trigger: ['blur', 'change'] }
    ],
    phone: [{ required: true, message: window.t('phone_required'), trigger: 'blur' }],
    hire_date: [{ required: true, message: window.t('appointment_date_required'), trigger: 'change' }],
    password: [
        {
            validator: (rule, value, callback) => {
                if (value && value.length < 8) {
                    callback(new Error(window.t('password_min')));
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
                    callback(new Error(window.t('password_mismatch')));
                } else {
                    callback();
                }
            },
            trigger: ['blur', 'change']
        }
    ]
};

const isEdit = computed(() => !!route.params.id);
const formTitle = computed(() => (isEdit.value ? window.t('modify_an_employee') : window.t('add_an_employee')));
const submitLabel = computed(() => (isEdit.value ? window.t('update') : window.t('save')));

/** Will this employee be able to sign in once saved? */
const willHaveLogin = computed(() => !!form.value.password || currentHasLogin.value === true);

/**
 * A login with no warehouse. FieldScope treats such an account as unconfined,
 * so it can act on every warehouse in the company — correct for back office,
 * wrong for a branch seller.
 */
const grantsUnrestrictedAccess = computed(() => willHaveLogin.value && !form.value.warehouse_id);

/** A warehouse assigned to someone who cannot sign in — the app is unreachable. */
const warehouseWithoutLogin = computed(() => !!form.value.warehouse_id && !willHaveLogin.value);

const loadWarehouses = async () => {
    warehousesLoading.value = true;
    try {
        const res = await inventoryApi.getWarehouses({ per_page: 500, is_active: true });
        warehouses.value = res.data?.data || [];
    } catch (err) {
        // A warehouse list that fails to load must not block editing a phone
        // number. The field is simply left empty and the rest of the form works.
        console.error('Warehouses load error:', err);
        ElMessage.warning(window.t('warehouses_load_failed'));
    } finally {
        warehousesLoading.value = false;
    }
};

const loadEmployee = async () => {
    if (!isEdit.value) return;
    const employee = await store.fetchEmployee(route.params.id).catch(() => null);
    if (!employee) {
        ElMessage.error(window.t('no_employee_data_found'));
        router.push({ name: 'admin.hr.employees' });
        return;
    }
    currentHasLogin.value = employee.has_login ?? (employee.user_id != null);
    form.value = {
        name: employee.name || '',
        department: employee.department || '',
        position: employee.position || '',
        status: employee.status || window.t('active'),
        email: employee.email || '',
        phone: employee.phone || '',
        hire_date: employee.hire_date || '',
        avatar: employee.avatar || '/placeholder.jpg',
        warehouse_id: employee.warehouse_id ?? null,
        password: '',
        confirm_password: ''
    };
};

const buildPayload = () => {
    const payload = { ...form.value };
    delete payload.confirm_password;
    if (!payload.password) delete payload.password;
    // Sent even when null, so clearing the selector actually unlinks the
    // warehouse. Dropping empty values instead would make the field one-way:
    // assignable but never removable.
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
                ElMessage.success(window.t('employee_data_has_been_updated'));
            } else {
                await store.createEmployee(payload);
                ElMessage.success(window.t('the_employee_has_been_added_successfully'));
            }
            router.push({ name: 'admin.hr.employees' });
        } catch (err) {
            // errors handled via store.validationErrors; show top-level message if present
            ElMessage.error(err.response?.data?.message || err.message || window.t('an_error_occurred_while_saving_data'));
        }
    });
};

const serverErrors = computed(() => store.validationErrors || {});

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
    color: #1f2d3d;
}

.page-title p {
    margin: 0.35rem 0 0;
    color: #5f6d85;
}

.form-card {
    border-radius: 1rem;
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
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
