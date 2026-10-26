<template>
    <div class="hr-page hr-employees">
        <AdminPageHeader
            :title="$t('employees')"
            :subtitle="$t('manage_employee_data_view_status')"
        >
            <template #actions>
                <el-button type="primary" :icon="Plus" @click="createEmployee">
                    {{ $t('add_an_employee') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <!-- Stat Cards Grid -->
        <AdminStatGrid class="mb-4">
            <el-card shadow="hover" class="stat-card">
                <span class="stat-title">{{ $t('staff') }}</span>
                <h3 class="stat-value">{{ store.employees.length }}</h3>
                <span class="stat-sub">{{ $t('number_of_registered_employees') }}</span>
            </el-card>

            <el-card shadow="hover" class="stat-card">
                <span class="stat-title">{{ $t('active_employees') }}</span>
                <h3 class="stat-value text-success">{{ activeCount }}</h3>
                <span class="stat-sub">{{ inactiveCount }} {{ $t('inactive_employees') }}</span>
            </el-card>

            <el-card shadow="hover" class="stat-card">
                <span class="stat-title">{{ $t('total_salaries_liability') }}</span>
                <h3 class="stat-value text-primary">{{ money(totalSalaries) }}</h3>
                <span class="stat-sub">{{ $t('basic_salary') }}</span>
            </el-card>

            <el-card shadow="hover" class="stat-card">
                <span class="stat-title">{{ $t('end_of_service_accrued') }}</span>
                <h3 class="stat-value text-warning">{{ money(totalEosAccrued) }}</h3>
                <span class="stat-sub">{{ $t('settle_end_of_service') }}</span>
            </el-card>
        </AdminStatGrid>

        <!-- Filters Bar -->
        <AdminFilterBar>
            <el-input
                v-model="searchQuery"
                :placeholder="$t('search_by_name_department_or_position')"
                :prefix-icon="Search"
                clearable
                style="max-width: 280px;"
            />
            <el-select v-model="selectedDepartment" :placeholder="$t('department')" clearable style="min-width: 170px;">
                <el-option :label="$t('all_departments')" value="" />
                <el-option v-for="dept in departments" :key="dept" :label="dept" :value="dept" />
            </el-select>
            <el-select v-model="selectedStatus" :placeholder="$t('status')" clearable style="min-width: 130px;">
                <el-option :label="$t('active')" value="نشط" />
                <el-option :label="$t('inactive')" value="غير نشط" />
            </el-select>
            <el-button type="primary" :icon="Refresh" @click="fetchEmployees">
                {{ $t('update') }}
            </el-button>
        </AdminFilterBar>

        <!-- Employees Table Card -->
        <el-card shadow="hover" class="table-card">
            <template #header>
                <div class="card-header">
                    <span>{{ $t('list_of_employees') }}</span>
                    <span class="employee-count">{{ filteredEmployees.length }} {{ $t('employee') }}</span>
                </div>
            </template>

            <div v-if="store.error" class="error-state">
                {{ store.error }}
            </div>

            <el-table
                v-loading="store.loading"
                :data="filteredEmployees"
                style="width: 100%"
                stripe
                highlight-current-row
            >
                <el-table-column :label="$t('photo')" width="75">
                    <template #default="{ row }">
                        <EntityImage :src="row.avatar" type="employee" :size="40" shape="circle" />
                    </template>
                </el-table-column>

                <el-table-column :label="$t('employee')" min-width="160">
                    <template #default="{ row }">
                        <div class="emp-profile-col">
                            <span class="emp-name cursor-pointer" @click="openProfile(row)">{{ row.name }}</span>
                            <span v-if="row.job_title || row.position" class="emp-job text-muted">
                                {{ row.job_title || row.position }}
                            </span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column prop="department" :label="$t('department')" width="130" />

                <el-table-column :label="$t('mail')" min-width="160">
                    <template #default="{ row }">
                        <div class="contact-col">
                            <span>{{ row.email || '—' }}</span>
                            <small class="text-muted">{{ row.phone || '' }}</small>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('linked_warehouse')" width="150">
                    <template #default="{ row }">
                        <el-tag v-if="row.warehouse" type="success" effect="plain" size="small">
                            {{ row.warehouse.name }}
                        </el-tag>
                        <el-tooltip v-else :content="$t('warehouse_unrestricted_hint')" placement="top">
                            <el-tag type="warning" effect="plain" size="small">
                                {{ $t('not_linked') }}
                            </el-tag>
                        </el-tooltip>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('login_account')" width="110">
                    <template #default="{ row }">
                        <el-tag :type="row.user_id ? 'success' : 'info'" effect="plain" size="small">
                            {{ row.user_id ? $t('enabled') : $t('none') }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column prop="status" :label="$t('status')" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 'نشط' || String(row.status).toLowerCase() === 'active' ? 'success' : 'info'">
                            {{ row.status }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('basic_salary')" width="130" align="right">
                    <template #default="{ row }">
                        <span v-if="Number(row.salary) > 0" class="font-medium">
                            {{ money(row.salary) }}
                        </span>
                        <span v-else class="muted">—</span>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('end_of_service_accrued')" width="140" align="right">
                    <template #default="{ row }">
                        <span v-if="Number(row.end_of_service_accrued) > 0" class="text-warning font-medium">
                            {{ money(row.end_of_service_accrued) }}
                        </span>
                        <span v-else class="muted">—</span>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('procedures')" width="210" fixed="right" align="center">
                    <template #default="{ row }">
                        <el-button-group>
                            <el-tooltip :content="$t('view_profile')" placement="top">
                                <el-button :icon="View" size="small" @click="openProfile(row)" />
                            </el-tooltip>
                            <el-tooltip :content="$t('edit')" placement="top">
                                <el-button :icon="Edit" size="small" @click="editEmployee(row)" />
                            </el-tooltip>
                            <el-tooltip v-if="Number(row.end_of_service_accrued) > 0" :content="$t('settle_end_of_service')" placement="top">
                                <el-button
                                    size="small"
                                    type="success"
                                    plain
                                    :icon="Money"
                                    @click="openSettlement(row)"
                                />
                            </el-tooltip>
                            <el-tooltip :content="$t('delete')" placement="top">
                                <el-button :icon="Delete" size="small" type="danger" @click="deleteEmployee(row)" />
                            </el-tooltip>
                        </el-button-group>
                    </template>
                </el-table-column>
            </el-table>

            <div v-if="!store.loading && !filteredEmployees.length" class="empty-state">
                {{ $t('there_are_no_employees_matching') }}
            </div>
        </el-card>

        <!-- Employee Profile Drawer -->
        <el-drawer
            v-model="profileDrawerVisible"
            :title="selectedEmployee ? selectedEmployee.name : $t('employee_details')"
            size="520px"
            destroy-on-close
        >
            <div v-if="selectedEmployee" class="profile-drawer-content">
                <!-- Profile Header Card -->
                <div class="drawer-header-card">
                    <EntityImage :src="selectedEmployee.avatar" type="employee" :size="64" shape="circle" />
                    <div class="drawer-header-meta">
                        <h3>{{ selectedEmployee.name }}</h3>
                        <p class="text-muted">{{ selectedEmployee.job_title || selectedEmployee.position }} · {{ selectedEmployee.department }}</p>
                        <div class="drawer-tags">
                            <el-tag size="small" :type="selectedEmployee.status === 'نشط' ? 'success' : 'info'">
                                {{ selectedEmployee.status }}
                            </el-tag>
                            <el-tag v-if="selectedEmployee.warehouse" size="small" type="success" effect="plain">
                                {{ selectedEmployee.warehouse.name }}
                            </el-tag>
                            <el-tag v-if="selectedEmployee.employment_type" size="small" type="info" effect="plain">
                                {{ selectedEmployee.employment_type }}
                            </el-tag>
                        </div>
                    </div>
                </div>

                <!-- Tabs Info -->
                <el-tabs v-model="activeTab" class="profile-tabs">
                    <el-tab-pane :label="$t('personal_info')" name="personal">
                        <el-descriptions :column="1" border size="small" class="detail-desc">
                            <el-descriptions-item :label="$t('mail')">{{ selectedEmployee.email || '—' }}</el-descriptions-item>
                            <el-descriptions-item :label="$t('phone')">{{ selectedEmployee.phone || '—' }}</el-descriptions-item>
                            <el-descriptions-item :label="$t('national_id')">{{ selectedEmployee.national_id || '—' }}</el-descriptions-item>
                            <el-descriptions-item :label="$t('nationality')">{{ selectedEmployee.nationality || '—' }}</el-descriptions-item>
                            <el-descriptions-item :label="$t('contact_emergency')">
                                {{ selectedEmployee.emergency_contact_name || '—' }}
                                <span v-if="selectedEmployee.emergency_contact_phone">({{ selectedEmployee.emergency_contact_phone }})</span>
                            </el-descriptions-item>
                        </el-descriptions>
                    </el-tab-pane>

                    <el-tab-pane :label="$t('job_info')" name="job">
                        <el-descriptions :column="1" border size="small" class="detail-desc">
                            <el-descriptions-item :label="$t('department')">{{ selectedEmployee.department }}</el-descriptions-item>
                            <el-descriptions-item :label="$t('position')">{{ selectedEmployee.position }}</el-descriptions-item>
                            <el-descriptions-item :label="$t('job_title')">{{ selectedEmployee.job_title || '—' }}</el-descriptions-item>
                            <el-descriptions-item :label="$t('appointment_date')">{{ formatDate(selectedEmployee.hire_date) }}</el-descriptions-item>
                            <el-descriptions-item :label="$t('contract_type')">{{ selectedEmployee.contract_type || '—' }}</el-descriptions-item>
                            <el-descriptions-item :label="$t('contract_start')">{{ formatDate(selectedEmployee.contract_start) }}</el-descriptions-item>
                            <el-descriptions-item :label="$t('contract_end')">{{ formatDate(selectedEmployee.contract_end) }}</el-descriptions-item>
                            <el-descriptions-item :label="$t('linked_warehouse')">{{ selectedEmployee.warehouse?.name || $t('not_linked') }}</el-descriptions-item>
                            <el-descriptions-item :label="$t('login_account')">
                                {{ selectedEmployee.user_id ? $t('enabled') : $t('none') }}
                            </el-descriptions-item>
                        </el-descriptions>
                    </el-tab-pane>

                    <el-tab-pane :label="$t('financial_info')" name="financial">
                        <el-descriptions :column="1" border size="small" class="detail-desc">
                            <el-descriptions-item :label="$t('basic_salary')">{{ money(selectedEmployee.salary) }}</el-descriptions-item>
                            <el-descriptions-item :label="$t('bonus')">{{ money(selectedEmployee.bonus) }}</el-descriptions-item>
                            <el-descriptions-item :label="$t('commission_rate')">
                                {{ selectedEmployee.commission_rate ? `${selectedEmployee.commission_rate}%` : '—' }}
                            </el-descriptions-item>
                            <el-descriptions-item :label="$t('monthly_sales_target')">
                                {{ Number(selectedEmployee.monthly_sales_target) > 0 ? money(selectedEmployee.monthly_sales_target) : '—' }}
                            </el-descriptions-item>
                            <el-descriptions-item :label="$t('bank_name')">{{ selectedEmployee.bank_name || '—' }}</el-descriptions-item>
                            <el-descriptions-item :label="$t('bank_account_number')">{{ selectedEmployee.bank_account_number || '—' }}</el-descriptions-item>
                            <el-descriptions-item :label="$t('end_of_service_accrued')">
                                <span class="text-warning font-bold">{{ money(selectedEmployee.end_of_service_accrued) }}</span>
                            </el-descriptions-item>
                        </el-descriptions>
                    </el-tab-pane>

                    <el-tab-pane :label="$t('notes')" name="notes">
                        <p class="notes-content">{{ selectedEmployee.notes || $t('no_notes_available') || 'لا توجد ملاحظات مسجلة.' }}</p>
                    </el-tab-pane>
                </el-tabs>

                <div class="drawer-actions">
                    <el-button type="primary" :icon="Edit" @click="editEmployee(selectedEmployee)">
                        {{ $t('edit') }}
                    </el-button>
                    <el-button :icon="Connection" @click="goToCustomers(selectedEmployee)">
                        {{ $t('employee_customer_relationship') }}
                    </el-button>
                </div>
            </div>
        </el-drawer>

        <!-- Settlement Modal (Existing logic preserved) -->
        <el-dialog v-model="settlementVisible" :title="$t('settle_end_of_service')" width="440px" destroy-on-close>
            <p class="settlement-note">{{ $t('settle_end_of_service_hint') }}</p>

            <el-form label-position="top">
                <el-form-item :label="$t('accrued_so_far')">
                    <el-input :model-value="money(settlementForm.accrued)" disabled />
                </el-form-item>
                <el-form-item :label="$t('amount')">
                    <el-input v-model="settlementForm.amount" type="number" min="0" step="0.01" />
                    <small v-if="exceedsAccrued" class="settlement-warn">
                        {{ $t('cannot_pay_more_than_accrued') }}
                    </small>
                </el-form-item>
                <el-form-item :label="$t('payment_method')">
                    <el-select v-model="settlementForm.settlement" style="width:100%">
                        <el-option :label="$t('cash')" value="cash" />
                        <el-option :label="$t('bank_transfer')" value="bank" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="$t('payment_date')">
                    <el-date-picker
                        v-model="settlementForm.paid_on"
                        type="date"
                        format="YYYY-MM-DD"
                        value-format="YYYY-MM-DD"
                        style="width:100%"
                    />
                </el-form-item>
            </el-form>

            <template #footer>
                <el-button @click="settlementVisible = false">{{ $t('cancel') }}</el-button>
                <el-button
                    type="primary"
                    :loading="settling"
                    :disabled="exceedsAccrued || !(Number(settlementForm.amount) > 0)"
                    @click="confirmSettlement"
                >
                    {{ $t('settle_end_of_service') }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useEmployeesStore } from '@/stores/employees';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminFilterBar from '@/components/admin/AdminFilterBar.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';
import EntityImage from '@/components/admin/EntityImage.vue';
import { matchesSearch } from '@/utils/search';
import { formatMoney } from '@/utils/currency';
import {
    Plus,
    Search,
    Refresh,
    Edit,
    Delete,
    View,
    Money,
    Connection
} from '@element-plus/icons-vue';

const { t } = useI18n();
const router = useRouter();
const store = useEmployeesStore();

const searchQuery = ref('');
const selectedStatus = ref('');
const selectedDepartment = ref('');

// Profile Drawer
const profileDrawerVisible = ref(false);
const selectedEmployee = ref(null);
const activeTab = ref('personal');

// Settlement Form
const settlementVisible = ref(false);
const settling = ref(false);
const settlementForm = reactive({
    employee_id: null,
    accrued: 0,
    amount: 0,
    settlement: 'cash',
    paid_on: new Date().toISOString().slice(0, 10),
});

const money = (value) => formatMoney(value || 0);

const formatDate = (val) => {
    if (!val) return '—';
    return String(val).slice(0, 10);
};

// Summary stats
const activeCount = computed(() => store.employees.filter(e => e.status === 'نشط' || String(e.status).toLowerCase() === 'active').length);
const inactiveCount = computed(() => store.employees.length - activeCount.value);
const totalSalaries = computed(() => store.employees.reduce((acc, e) => acc + (Number(e.salary) || 0), 0));
const totalEosAccrued = computed(() => store.employees.reduce((acc, e) => acc + (Number(e.end_of_service_accrued) || 0), 0));

// Departments list
const departments = computed(() => {
    const set = new Set();
    store.employees.forEach(e => {
        if (e.department) set.add(e.department.trim());
    });
    return Array.from(set).sort();
});

const exceedsAccrued = computed(() =>
    Number(settlementForm.amount) - Number(settlementForm.accrued) > 0.009
);

const filteredEmployees = computed(() => {
    return store.employees.filter((employee) => {
        const matchesQuery = matchesSearch([
            employee.name,
            employee.department,
            employee.position,
            employee.job_title,
            employee.email,
            employee.phone,
            employee.national_id
        ], searchQuery.value);

        const matchesStatus = !selectedStatus.value || employee.status === selectedStatus.value;
        const matchesDept = !selectedDepartment.value || employee.department === selectedDepartment.value;

        return matchesQuery && matchesStatus && matchesDept;
    });
});

const fetchEmployees = async () => {
    await store.fetchEmployees().catch(() => {
        ElMessage.error(t('failed_to_load_employee_data') || 'تعذر تحميل بيانات الموظفين');
    });
};

const createEmployee = () => {
    router.push({ name: 'admin.hr.employees.create' });
};

const editEmployee = (employee) => {
    router.push({ name: 'admin.hr.employees.edit', params: { id: employee.id } });
};

const openProfile = (employee) => {
    selectedEmployee.value = employee;
    activeTab.value = 'personal';
    profileDrawerVisible.value = true;
};

const goToCustomers = (employee) => {
    router.push({ name: 'admin.hr.employee-customers', query: { employee_id: employee.id } });
};

const openSettlement = (employee) => {
    settlementForm.employee_id = employee.id;
    settlementForm.accrued = Number(employee.end_of_service_accrued) || 0;
    settlementForm.amount = settlementForm.accrued;
    settlementForm.settlement = 'cash';
    settlementForm.paid_on = new Date().toISOString().slice(0, 10);
    settlementVisible.value = true;
};

const confirmSettlement = async () => {
    settling.value = true;
    try {
        await axios.post(`/api/v1/employees/${settlementForm.employee_id}/end-of-service`, {
            amount: Number(settlementForm.amount),
            settlement: settlementForm.settlement,
            paid_on: settlementForm.paid_on,
        });

        settlementVisible.value = false;
        ElMessage.success(t('end_of_service_settled') || 'تم صرف مكافأة نهاية الخدمة بنجاح');
        await store.fetchEmployees();
    } catch (error) {
        ElMessage.error(error.response?.data?.message || t('failed_to_settle_end_of_service'));
    } finally {
        settling.value = false;
    }
};

const deleteEmployee = async (employee) => {
    try {
        await ElMessageBox.confirm(
            t('delete_employee_confirm', { name: employee.name }) || `هل أنت متأكد من حذف الموظف ${employee.name}؟`,
            t('confirm_deletion') || 'تأكيد الحذف',
            {
                confirmButtonText: t('yes') || 'نعم',
                cancelButtonText: t('no') || 'إلغاء',
                type: 'warning'
            }
        );

        await store.deleteEmployee(employee.id);
        ElMessage.success(t('the_employee_has_been_deleted') || 'تم حذف الموظف بنجاح');
    } catch (error) {
        if (error !== 'cancel') {
            ElMessage.error(t('failed_to_delete_employee') || 'تعذر حذف الموظف');
        }
    }
};

onMounted(fetchEmployees);
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
    color: #1e293b;
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

.employee-count {
    color: #6b7c98;
    font-size: 0.9rem;
}

.emp-profile-col {
    display: flex;
    flex-direction: column;
}

.emp-name {
    font-weight: 600;
    color: #1e293b;
    transition: color 0.15s ease;
}

.emp-name:hover {
    color: #3b82f6;
}

.emp-job {
    font-size: 0.75rem;
}

.contact-col {
    display: flex;
    flex-direction: column;
    font-size: 0.85rem;
}

.cursor-pointer {
    cursor: pointer;
}

.font-medium {
    font-weight: 600;
}

.font-bold {
    font-weight: 700;
}

.text-success { color: #16a34a; }
.text-primary { color: #2563eb; }
.text-warning { color: #d97706; }
.text-muted { color: #64748b; }
.muted { color: #94a3b8; }

.error-state,
.empty-state {
    padding: 2rem 1rem;
    text-align: center;
    color: #64748b;
}

/* Profile Drawer Styling */
.profile-drawer-content {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.drawer-header-card {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    padding: 1.25rem;
    background: #f8fafc;
    border-radius: 1rem;
    border: 1px solid #e2e8f0;
}

.drawer-header-meta h3 {
    margin: 0 0 0.25rem;
    font-size: 1.25rem;
    color: #1e293b;
}

.drawer-header-meta p {
    margin: 0 0 0.5rem;
    font-size: 0.85rem;
}

.drawer-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.profile-tabs {
    margin-top: 0.5rem;
}

.detail-desc {
    margin-top: 0.5rem;
}

.notes-content {
    background: #f8fafc;
    border-radius: 0.75rem;
    padding: 1rem;
    font-size: 0.9rem;
    color: #334155;
    line-height: 1.6;
    border: 1px solid #e2e8f0;
}

.drawer-actions {
    display: flex;
    gap: 0.75rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #f1f5f9;
}

.settlement-note {
    font-size: 0.85rem;
    color: #64748b;
    margin-bottom: 1rem;
}

.settlement-warn {
    color: #ef4444;
    font-size: 0.75rem;
    margin-top: 0.25rem;
    display: block;
}
</style>
