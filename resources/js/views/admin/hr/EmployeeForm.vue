<template>
    <div class="hr-page hr-employee-form">
        <AdminPageHeader :title="formTitle" :subtitle="$t('ef_subtitle')" icon="fas fa-user-tie">
            <template #actions>
                <el-button @click="cancel">{{ $t('cancel') }}</el-button>
                <el-button type="primary" :loading="saving" @click="submitForm()">
                    {{ submitLabel }}
                </el-button>
            </template>
        </AdminPageHeader>

        <el-form
            ref="employeeForm"
            v-loading="loadingEmployee"
            :model="form"
            :rules="rules"
            label-position="top"
            class="ef-form"
            @submit.prevent="submitForm()"
        >
            <p class="ef-required-note">{{ $t('ef_required_note') }}</p>

            <!-- Personal -->
            <el-card shadow="never" class="ef-card">
                <template #header>
                    <div class="ef-card-head">
                        <span class="ef-card-icon tone-blue"><el-icon><User /></el-icon></span>
                        <div>
                            <h3>{{ $t('personal_info') }}</h3>
                            <p>{{ $t('ef_personal_hint') }}</p>
                        </div>
                    </div>
                </template>

                <div class="ef-identity">
                    <el-avatar :size="72" :src="form.avatar || undefined" class="ef-avatar">
                        {{ initials }}
                    </el-avatar>
                    <div class="ef-grid ef-grow">
                        <el-form-item :label="$t('name')" prop="name" :error="serverError('name')">
                            <el-input v-model="form.name" :placeholder="$t('enter_the_employee_s_name')" maxlength="255" />
                        </el-form-item>
                        <el-form-item :label="$t('status')" prop="status" :error="serverError('status')">
                            <el-radio-group v-model="form.status" class="ef-segmented">
                                <el-radio-button value="نشط">{{ $t('active') }}</el-radio-button>
                                <el-radio-button value="غير نشط">{{ $t('inactive') }}</el-radio-button>
                            </el-radio-group>
                        </el-form-item>
                    </div>
                </div>

                <div class="ef-grid">
                    <el-form-item :label="$t('email')" prop="email" :error="serverError('email')">
                        <el-input v-model="form.email" type="email" placeholder="name@example.com" dir="ltr" autocomplete="off" />
                    </el-form-item>
                    <el-form-item :label="$t('phone')" prop="phone" :error="serverError('phone')">
                        <el-input v-model="form.phone" type="tel" placeholder="+963 9xx xxx xxx" dir="ltr" maxlength="50" />
                    </el-form-item>
                    <el-form-item :label="$t('national_id')" prop="national_id" :error="serverError('national_id')">
                        <el-input v-model="form.national_id" :placeholder="$t('national_id')" maxlength="100" />
                    </el-form-item>
                    <el-form-item :label="$t('nationality')" prop="nationality" :error="serverError('nationality')">
                        <el-input v-model="form.nationality" :placeholder="$t('nationality')" maxlength="100" />
                    </el-form-item>
                    <el-form-item :label="$t('ef_photo_url')" prop="avatar" :error="serverError('avatar')" class="ef-span-2">
                        <el-input v-model="form.avatar" placeholder="https://…" dir="ltr" clearable />
                        <div class="ef-field-hint">{{ $t('ef_photo_url_hint') }}</div>
                    </el-form-item>
                </div>
            </el-card>

            <!-- Job -->
            <el-card shadow="never" class="ef-card">
                <template #header>
                    <div class="ef-card-head">
                        <span class="ef-card-icon tone-violet"><el-icon><Briefcase /></el-icon></span>
                        <div>
                            <h3>{{ $t('job_info') }}</h3>
                            <p>{{ $t('ef_job_hint') }}</p>
                        </div>
                    </div>
                </template>

                <div class="ef-grid">
                    <el-form-item :label="$t('department')" prop="department" :error="serverError('department')">
                        <el-autocomplete
                            v-model="form.department"
                            :fetch-suggestions="suggest('departments')"
                            :placeholder="$t('ef_pick_or_type')"
                            clearable
                            style="width: 100%"
                        />
                    </el-form-item>
                    <el-form-item :label="$t('position')" prop="position" :error="serverError('position')">
                        <el-autocomplete
                            v-model="form.position"
                            :fetch-suggestions="suggest('positions')"
                            :placeholder="$t('ef_pick_or_type')"
                            clearable
                            style="width: 100%"
                        />
                    </el-form-item>
                    <el-form-item :label="$t('job_title')" prop="job_title" :error="serverError('job_title')">
                        <el-autocomplete
                            v-model="form.job_title"
                            :fetch-suggestions="suggest('job_titles')"
                            :placeholder="$t('ef_pick_or_type')"
                            clearable
                            style="width: 100%"
                        />
                    </el-form-item>
                    <el-form-item :label="$t('appointment_date')" prop="hire_date" :error="serverError('hire_date')">
                        <el-date-picker
                            v-model="form.hire_date"
                            type="date"
                            :placeholder="$t('choose_appointment_date')"
                            format="YYYY-MM-DD"
                            value-format="YYYY-MM-DD"
                            style="width: 100%"
                        />
                    </el-form-item>
                    <el-form-item :label="$t('employment_type')" prop="employment_type" class="ef-span-2">
                        <el-radio-group v-model="form.employment_type" class="ef-segmented">
                            <el-radio-button value="دوام كامل">{{ $t('full_time') }}</el-radio-button>
                            <el-radio-button value="دوام جزئي">{{ $t('part_time') }}</el-radio-button>
                            <el-radio-button value="عقد محدد">{{ $t('contract_val') }}</el-radio-button>
                        </el-radio-group>
                    </el-form-item>
                </div>

                <!-- A fixed-term contract has dates; other kinds only when already filled in. -->
                <el-collapse-transition>
                    <div v-if="showContract" class="ef-subblock">
                        <h4>{{ $t('ef_contract_block') }}</h4>
                        <div class="ef-grid ef-grid-3">
                            <el-form-item :label="$t('contract_type')" prop="contract_type">
                                <el-input v-model="form.contract_type" :placeholder="$t('contract_type')" maxlength="100" />
                            </el-form-item>
                            <el-form-item :label="$t('contract_start')" prop="contract_start">
                                <el-date-picker
                                    v-model="form.contract_start"
                                    type="date"
                                    format="YYYY-MM-DD"
                                    value-format="YYYY-MM-DD"
                                    style="width: 100%"
                                />
                            </el-form-item>
                            <el-form-item :label="$t('contract_end')" prop="contract_end" :error="serverError('contract_end')">
                                <el-date-picker
                                    v-model="form.contract_end"
                                    type="date"
                                    format="YYYY-MM-DD"
                                    value-format="YYYY-MM-DD"
                                    :disabled-date="beforeContractStart"
                                    style="width: 100%"
                                />
                            </el-form-item>
                        </div>
                    </div>
                </el-collapse-transition>
            </el-card>

            <!-- Pay -->
            <el-card shadow="never" class="ef-card">
                <template #header>
                    <div class="ef-card-head">
                        <span class="ef-card-icon tone-green"><el-icon><Money /></el-icon></span>
                        <div>
                            <h3>{{ $t('financial_info') }}</h3>
                            <p>{{ $t('ef_pay_hint') }}</p>
                        </div>
                    </div>
                </template>

                <div class="ef-grid">
                    <el-form-item :label="$t('basic_salary')" prop="salary" :error="serverError('salary')">
                        <el-input-number v-model="form.salary" :min="0" :precision="2" :step="100" controls-position="right" style="width: 100%" />
                    </el-form-item>
                    <el-form-item :label="$t('bonus')" prop="bonus">
                        <el-input-number v-model="form.bonus" :min="0" :precision="2" :step="50" controls-position="right" style="width: 100%" />
                    </el-form-item>
                </div>

                <div class="ef-subblock">
                    <h4>{{ $t('ef_sales_block') }}</h4>
                    <div class="ef-grid">
                        <el-form-item :label="$t('commission_rate')" prop="commission_rate">
                            <el-input-number v-model="form.commission_rate" :min="0" :max="100" :precision="2" :step="0.5" controls-position="right" style="width: 100%" />
                        </el-form-item>
                        <el-form-item :label="$t('monthly_sales_target')" prop="monthly_sales_target">
                            <el-input-number v-model="form.monthly_sales_target" :min="0" :precision="2" :step="1000" controls-position="right" style="width: 100%" />
                        </el-form-item>
                    </div>
                </div>

                <div class="ef-subblock">
                    <h4>{{ $t('ef_bank_block') }}</h4>
                    <div class="ef-grid">
                        <el-form-item :label="$t('bank_name')" prop="bank_name">
                            <el-input v-model="form.bank_name" :placeholder="$t('bank_name')" maxlength="255" />
                        </el-form-item>
                        <el-form-item :label="$t('bank_account_number')" prop="bank_account_number">
                            <el-input v-model="form.bank_account_number" :placeholder="$t('bank_account_number')" dir="ltr" maxlength="100" />
                        </el-form-item>
                    </div>
                </div>
            </el-card>

            <!-- Access -->
            <el-card shadow="never" class="ef-card">
                <template #header>
                    <div class="ef-card-head">
                        <span class="ef-card-icon tone-orange"><el-icon><Key /></el-icon></span>
                        <div>
                            <h3>{{ $t('system_access') }}</h3>
                            <p>{{ $t('ef_access_hint') }}</p>
                        </div>
                    </div>
                </template>

                <el-alert
                    v-if="currentHasLogin"
                    :title="$t('ef_has_login', { email: loadedEmail })"
                    type="success"
                    show-icon
                    :closable="false"
                    class="access-alert"
                />

                <div class="ef-switch-row">
                    <el-switch v-model="passwordOn" />
                    <div>
                        <strong>{{ currentHasLogin ? $t('ef_change_password') : $t('ef_allow_login') }}</strong>
                        <p v-if="!currentHasLogin">{{ canAssignRoles ? $t('ef_allow_login_hint_short') : $t('ef_allow_login_hint') }}</p>
                    </div>
                </div>

                <el-collapse-transition>
                    <div v-if="passwordOn" class="ef-grid">
                        <el-form-item :label="$t('password')" prop="password" :error="serverError('password')">
                            <el-input
                                v-model="form.password"
                                type="password"
                                show-password
                                autocomplete="new-password"
                                dir="ltr"
                            >
                                <template #append>
                                    <el-button @click="generatePassword">{{ $t('ef_generate_password') }}</el-button>
                                </template>
                            </el-input>
                        </el-form-item>
                        <el-form-item :label="$t('confirm_password')" prop="confirm_password">
                            <el-input v-model="form.confirm_password" type="password" show-password autocomplete="new-password" dir="ltr" />
                        </el-form-item>
                    </div>
                </el-collapse-transition>

                <el-collapse-transition>
                    <div v-if="showRolePicker" class="ef-role">
                        <el-form-item :label="$t('ef_login_role')" prop="role_id" :error="serverError('role_id')">
                            <el-select v-model="form.role_id" :loading="rolesLoading" filterable style="width: 100%">
                                <el-option v-for="role in roleOptions" :key="role.id" :label="role.display_name" :value="role.id">
                                    <span>{{ role.display_name }}</span>
                                    <el-tag v-if="role.is_super" size="small" type="danger" effect="plain" class="ef-role-tag">
                                        {{ $t('ef_role_full_access') }}
                                    </el-tag>
                                </el-option>
                            </el-select>
                            <div class="ef-field-hint">{{ $t('ef_login_role_hint') }}</div>
                            <div v-if="otherRoles.length" class="ef-field-hint">
                                {{ $t('ef_other_roles', { roles: otherRoles.join('، ') }) }}
                            </div>
                        </el-form-item>
                        <el-alert
                            v-if="pickedRole?.is_super"
                            :title="$t('ef_role_full_access_warning')"
                            type="warning"
                            show-icon
                            :closable="false"
                            class="access-alert"
                        />
                    </div>
                </el-collapse-transition>

                <el-form-item :label="$t('linked_warehouse')" prop="warehouse_id" :error="serverError('warehouse_id')" class="ef-warehouse">
                    <div class="ef-inline">
                        <el-select
                            v-model="form.warehouse_id"
                            :placeholder="$t('choose_linked_warehouse')"
                            :loading="warehousesLoading"
                            :no-data-text="$t('ef_no_warehouses')"
                            clearable
                            filterable
                            class="ef-grow"
                        >
                            <el-option
                                v-for="warehouse in warehouses"
                                :key="warehouse.id"
                                :label="warehouse.code ? `${warehouse.name} (${warehouse.code})` : warehouse.name"
                                :value="warehouse.id"
                            />
                        </el-select>
                        <el-button v-if="canAddWarehouse" :icon="Plus" @click="warehouseDialog = true">
                            {{ $t('ef_quick_add_warehouse') }}
                        </el-button>
                    </div>
                </el-form-item>

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
            </el-card>

            <!-- Emergency contact & notes -->
            <el-card shadow="never" class="ef-card">
                <template #header>
                    <div class="ef-card-head">
                        <span class="ef-card-icon tone-red"><el-icon><Phone /></el-icon></span>
                        <div>
                            <h3>{{ $t('contact_emergency') }}</h3>
                            <p>{{ $t('ef_emergency_hint') }}</p>
                        </div>
                    </div>
                </template>

                <div class="ef-grid">
                    <el-form-item :label="$t('emergency_contact_name')" prop="emergency_contact_name">
                        <el-input v-model="form.emergency_contact_name" :placeholder="$t('emergency_contact_name')" maxlength="255" />
                    </el-form-item>
                    <el-form-item :label="$t('emergency_contact_phone')" prop="emergency_contact_phone">
                        <el-input v-model="form.emergency_contact_phone" type="tel" dir="ltr" maxlength="50" />
                    </el-form-item>
                </div>

                <el-form-item :label="$t('notes')" prop="notes" :error="serverError('notes')">
                    <el-input v-model="form.notes" type="textarea" :placeholder="$t('add_notes')" :rows="3" maxlength="1000" show-word-limit />
                    <div class="ef-field-hint">{{ $t('ef_notes_hint') }}</div>
                </el-form-item>
            </el-card>

            <!-- Sticky actions -->
            <div class="ef-actions">
                <el-button @click="cancel">{{ $t('cancel') }}</el-button>
                <el-button v-if="!isEdit" :loading="saving" @click="submitForm({ another: true })">
                    {{ $t('ef_save_and_new') }}
                </el-button>
                <el-button type="primary" :loading="saving" native-type="submit">
                    {{ submitLabel }}
                </el-button>
            </div>
        </el-form>

        <WarehouseQuickAdd
            v-model="warehouseDialog"
            :existing-codes="warehouses.map((w) => w.code)"
            @created="onWarehouseCreated"
        />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import { useRouter, useRoute, onBeforeRouteLeave } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import WarehouseQuickAdd from '@/components/admin/WarehouseQuickAdd.vue';
import { useEmployeesStore } from '@/stores/employees';
import { useAuthStore } from '@/stores/auth';
import { inventoryApi } from '@/api/inventory';
import api from '@/api';
import { User, Briefcase, Money, Phone, Key, Plus } from '@element-plus/icons-vue';

const { t } = useI18n();
const router = useRouter();
const route = useRoute();
const store = useEmployeesStore();
const auth = useAuthStore();
const employeeForm = ref(null);

const warehouses = ref([]);
const warehousesLoading = ref(false);
const warehouseDialog = ref(false);
const options = ref({ departments: [], positions: [], job_titles: [] });

const loadingEmployee = ref(false);
const saving = ref(false);
const currentHasLogin = ref(false);
const loadedEmail = ref('');
const roles = ref([]);
const rolesLoading = ref(false);
const loadedRoleId = ref(null);
const otherRoles = ref([]);
// Create: "can sign in". Edit with a login: "change password". Either way the
// password fields only appear (and only count) while this is on.
const passwordOn = ref(false);

const today = () => new Date().toISOString().slice(0, 10);

const blankForm = () => ({
    name: '',
    department: '',
    position: '',
    job_title: '',
    status: 'نشط',
    email: '',
    phone: '',
    national_id: '',
    nationality: '',
    hire_date: today(),
    employment_type: 'دوام كامل',
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
    role_id: null,
    password: '',
    confirm_password: '',
    notes: '',
});

const form = ref(blankForm());
// What the form held when it was opened or last saved; leaving with anything
// else asks first.
const snapshot = ref(JSON.stringify(form.value));
const isDirty = computed(() => JSON.stringify(form.value) !== snapshot.value);
const markClean = () => {
    snapshot.value = JSON.stringify(form.value);
};

const required = (key, trigger = 'blur') => ({ required: true, message: t(key), trigger });

const rules = {
    name: [required('name_required')],
    department: [required('section_is_required')],
    position: [required('position_required')],
    email: [
        required('postage_is_required'),
        { type: 'email', message: t('the_mail_must_be_valid'), trigger: ['blur', 'change'] },
    ],
    phone: [required('phone_required')],
    hire_date: [required('appointment_date_required', 'change')],
    contract_end: [{
        validator: (rule, value, callback) => {
            const start = form.value.contract_start;
            callback(value && start && value < start ? new Error(t('ef_contract_end_before_start')) : undefined);
        },
        trigger: 'change',
    }],
    password: [{
        validator: (rule, value, callback) => {
            if (!passwordOn.value) return callback();
            if (!value) return callback(new Error(t('ef_password_required')));
            if (value.length < 8) return callback(new Error(t('password_min')));
            callback();
        },
        trigger: 'blur',
    }],
    confirm_password: [{
        validator: (rule, value, callback) => {
            callback(passwordOn.value && value !== form.value.password ? new Error(t('password_mismatch')) : undefined);
        },
        trigger: ['blur', 'change'],
    }],
};

const isEdit = computed(() => !!route.params.id);
const formTitle = computed(() => (isEdit.value ? t('modify_an_employee') : t('add_an_employee')));
const submitLabel = computed(() => (isEdit.value ? t('update') : t('save')));

const initials = computed(() => {
    const words = form.value.name.trim().split(/\s+/).filter(Boolean);
    return words.slice(0, 2).map((w) => w[0]).join('') || '؟';
});

const showContract = computed(() => form.value.employment_type === 'عقد محدد'
    || !!(form.value.contract_type || form.value.contract_start || form.value.contract_end));

const willHaveLogin = computed(() => currentHasLogin.value || (passwordOn.value && !!form.value.password));
const grantsUnrestrictedAccess = computed(() => willHaveLogin.value && !form.value.warehouse_id);
const warehouseWithoutLogin = computed(() => !!form.value.warehouse_id && !willHaveLogin.value && !passwordOn.value);

// Creating a warehouse is admin-only and needs the warehouse module on.
const canAddWarehouse = computed(() => (auth.isSuperAdmin || auth.hasRole('admin')) && auth.canAccessFeature('wms'));

// Picking the login's role follows the Users & access rules: it needs the
// users.assign_roles permission, and the server refuses what the actor could
// not grant themselves.
const canAssignRoles = computed(() => auth.can('users.assign_roles'));
const showRolePicker = computed(() => canAssignRoles.value && (currentHasLogin.value || passwordOn.value));
// Active roles, the master role only for masters, and whatever the login holds
// now even if it has since been switched off.
const roleOptions = computed(() => roles.value.filter((role) => role.id === loadedRoleId.value
    || (role.is_active && (role.name !== 'master' || auth.isMaster))));
const pickedRole = computed(() => roles.value.find((role) => role.id === form.value.role_id) || null);

const serverError = (field) => store.validationErrors?.[field]?.[0] || '';

const beforeContractStart = (date) => {
    const start = form.value.contract_start;
    return !!start && date < new Date(`${start}T00:00:00`);
};

// el-autocomplete source: the values already in use, filtered by what is typed.
const suggest = (key) => (query, cb) => {
    const q = String(query || '').trim().toLowerCase();
    const list = options.value[key] || [];
    cb(list.filter((v) => !q || String(v).toLowerCase().includes(q)).map((value) => ({ value })));
};

// Switching sign-in off drops whatever was typed, so it is never sent.
watch(passwordOn, (on) => {
    if (!on) {
        form.value.password = '';
        form.value.confirm_password = '';
        employeeForm.value?.clearValidate(['password', 'confirm_password']);
    }
});

watch(() => form.value.contract_start, () => {
    if (form.value.contract_end) employeeForm.value?.validateField('contract_end').catch(() => {});
});

const generatePassword = async () => {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    const bytes = new Uint32Array(12);
    crypto.getRandomValues(bytes);
    const password = Array.from(bytes, (n) => chars[n % chars.length]).join('');
    form.value.password = password;
    form.value.confirm_password = password;
    employeeForm.value?.clearValidate(['password', 'confirm_password']);
    try {
        await navigator.clipboard.writeText(password);
        ElMessage.success(t('ef_password_copied'));
    } catch {
        ElMessage.info(t('ef_password_generated'));
    }
};

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

const loadOptions = async () => {
    try {
        const res = await api.get('/admin/employees/form-options');
        options.value = { ...options.value, ...(res.data?.data || {}) };
    } catch {
        // Suggestions are a convenience; the fields still take free text.
    }
};

const defaultRoleId = () => roles.value.find((role) => role.name === 'employee')?.id ?? null;

const loadRoles = async () => {
    if (!canAssignRoles.value) return;
    rolesLoading.value = true;
    try {
        const res = await api.get('/admin/access/roles');
        roles.value = res.data?.data?.roles || [];
        // A new login defaults to "employee", as it would without a picker.
        if (!isEdit.value && !form.value.role_id) {
            const wasDirty = isDirty.value;
            form.value.role_id = defaultRoleId();
            if (!wasDirty) markClean();
        }
    } catch {
        roles.value = [];
    } finally {
        rolesLoading.value = false;
    }
};

const onWarehouseCreated = (warehouse) => {
    if (!warehouse?.id) return;
    warehouses.value = [...warehouses.value.filter((w) => w.id !== warehouse.id), warehouse]
        .sort((a, b) => String(a.name).localeCompare(String(b.name)));
    form.value.warehouse_id = warehouse.id;
};

const dateOnly = (value) => (value ? String(value).slice(0, 10) : '');

const loadEmployee = async () => {
    if (!isEdit.value) return;
    loadingEmployee.value = true;
    const employee = await store.fetchEmployee(route.params.id).catch(() => null);
    loadingEmployee.value = false;
    if (!employee) {
        ElMessage.error(t('no_employee_data_found'));
        markClean();
        router.push({ name: 'admin.hr.employees' });
        return;
    }
    currentHasLogin.value = employee.has_login ?? employee.user_id != null;
    loadedEmail.value = employee.email || '';
    loadedRoleId.value = employee.login_role_id ?? null;
    otherRoles.value = employee.login_other_roles || [];
    const base = blankForm();
    form.value = {
        ...base,
        ...Object.fromEntries(Object.keys(base).map((key) => [key, employee[key] ?? base[key]])),
        hire_date: dateOnly(employee.hire_date),
        contract_start: dateOnly(employee.contract_start),
        contract_end: dateOnly(employee.contract_end),
        employment_type: employee.employment_type || '',
        salary: Number(employee.salary) || 0,
        bonus: Number(employee.bonus) || 0,
        commission_rate: Number(employee.commission_rate) || 0,
        monthly_sales_target: Number(employee.monthly_sales_target) || 0,
        warehouse_id: employee.warehouse_id ?? null,
        role_id: employee.login_role_id ?? defaultRoleId(),
        password: '',
        confirm_password: '',
    };
    markClean();
};

const buildPayload = () => {
    const payload = { ...form.value };
    delete payload.confirm_password;
    if (!passwordOn.value || !payload.password) delete payload.password;
    payload.warehouse_id = payload.warehouse_id || null;
    if (!showRolePicker.value || !payload.role_id) delete payload.role_id;
    return payload;
};

const scrollToFirstError = () => nextTick(() => {
    document.querySelector('.hr-employee-form .is-error')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
});

const submitForm = async ({ another = false } = {}) => {
    if (saving.value) return;
    const valid = await employeeForm.value.validate().catch(() => false);
    if (!valid) {
        ElMessage.warning(t('ef_fix_errors'));
        scrollToFirstError();
        return;
    }

    saving.value = true;
    try {
        const payload = buildPayload();
        if (isEdit.value) {
            await store.updateEmployee(route.params.id, payload);
            ElMessage.success(t('employee_data_has_been_updated'));
        } else {
            await store.createEmployee(payload);
            ElMessage.success(t('the_employee_has_been_added_successfully'));
        }

        if (another) {
            form.value = { ...blankForm(), role_id: defaultRoleId() };
            passwordOn.value = false;
            markClean();
            loadOptions();
            nextTick(() => {
                employeeForm.value?.clearValidate();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
            return;
        }

        markClean();
        router.push({ name: 'admin.hr.employees' });
    } catch (err) {
        if (err.response?.status === 422) {
            ElMessage.error(t('ef_fix_errors'));
            scrollToFirstError();
        } else {
            ElMessage.error(err.response?.data?.message || err.message || t('an_error_occurred_while_saving_data'));
        }
    } finally {
        saving.value = false;
    }
};

const cancel = () => {
    router.push({ name: 'admin.hr.employees' });
};

onBeforeRouteLeave(async () => {
    if (!isDirty.value || saving.value) return true;
    try {
        await ElMessageBox.confirm(t('ef_unsaved_body'), t('ef_unsaved_title'), {
            confirmButtonText: t('ef_leave'),
            cancelButtonText: t('ef_stay'),
            type: 'warning',
        });
        return true;
    } catch {
        return false;
    }
});

onMounted(() => {
    store.validationErrors = {};
    loadWarehouses();
    loadOptions();
    // Roles first, so an edit can fall back to the default role once loaded.
    loadRoles().finally(loadEmployee);
});
</script>

<style scoped>
.hr-page {
    padding: 0;
}

.ef-form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    max-width: 1080px;
}

.ef-required-note {
    margin: 0;
    font-size: 0.85rem;
    color: var(--el-text-color-secondary, #8a94a6);
}

.ef-card {
    border-radius: 1rem;
    border: 1px solid var(--el-border-color-lighter, #eef2f6);
}

.ef-card :deep(.el-card__header) {
    padding: 1rem 1.25rem;
}

.ef-card-head {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.ef-card-head h3 {
    margin: 0;
    font-size: 1.02rem;
    font-weight: 700;
    color: var(--el-text-color-primary, #1e293b);
}

.ef-card-head p {
    margin: 0.15rem 0 0;
    font-size: 0.84rem;
    color: var(--el-text-color-secondary, #8a94a6);
}

.ef-card-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 0.65rem;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.tone-blue { background: #eff6ff; color: #2563eb; }
.tone-violet { background: #f5f3ff; color: #7c3aed; }
.tone-green { background: #ecfdf5; color: #059669; }
.tone-orange { background: #fff7ed; color: #ea580c; }
.tone-red { background: #fef2f2; color: #dc2626; }

.ef-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    column-gap: 1.25rem;
}

.ef-grid-3 {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.ef-span-2 {
    grid-column: span 2;
}

.ef-identity {
    display: flex;
    gap: 1.25rem;
    align-items: flex-start;
}

.ef-avatar {
    flex-shrink: 0;
    margin-top: 1.6rem;
    font-size: 1.5rem;
    font-weight: 700;
    background: #e0e7ff;
    color: #3730a3;
}

.ef-grow {
    flex: 1 1 auto;
    min-width: 0;
}

.ef-segmented {
    flex-wrap: wrap;
}

.ef-subblock {
    margin-top: 0.5rem;
    padding-top: 1rem;
    border-top: 1px dashed var(--el-border-color, #e5e7eb);
}

.ef-subblock h4 {
    margin: 0 0 0.75rem;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--el-text-color-regular, #475569);
}

.ef-field-hint {
    width: 100%;
    margin-top: 0.25rem;
    font-size: 0.8rem;
    line-height: 1.4;
    color: var(--el-text-color-secondary, #8a94a6);
}

.ef-switch-row {
    display: flex;
    gap: 0.75rem;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.ef-switch-row p {
    margin: 0.2rem 0 0;
    font-size: 0.84rem;
    color: var(--el-text-color-secondary, #8a94a6);
}

.ef-inline {
    display: flex;
    gap: 0.5rem;
    width: 100%;
}

.ef-role-tag {
    margin-inline-start: 0.5rem;
}

.ef-warehouse {
    margin-top: 0.5rem;
}

.access-alert {
    margin-bottom: 0.75rem;
}

.ef-actions {
    position: sticky;
    bottom: 0;
    z-index: 5;
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding: 0.85rem 1.25rem;
    border-radius: 1rem;
    border: 1px solid var(--el-border-color-lighter, #eef2f6);
    background: var(--el-bg-color, #fff);
    box-shadow: 0 -6px 18px rgba(15, 23, 42, 0.06);
}

.ef-actions .el-button + .el-button {
    margin-inline-start: 0;
}

@media (max-width: 768px) {
    .ef-grid,
    .ef-grid-3 {
        grid-template-columns: minmax(0, 1fr);
    }

    .ef-span-2 {
        grid-column: auto;
    }

    .ef-identity {
        flex-direction: column;
        align-items: center;
        gap: 0;
    }

    .ef-identity .ef-grow {
        width: 100%;
    }

    .ef-avatar {
        margin-top: 0;
    }

    .ef-inline {
        flex-direction: column;
    }

    .ef-actions {
        flex-wrap: wrap;
        padding: 0.75rem;
    }

    .ef-actions .el-button {
        flex: 1 1 auto;
    }
}
</style>
