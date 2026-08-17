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

        <AdminFilterBar>
            <el-input
                v-model="searchQuery"
                :placeholder="$t('search_by_name_department_or_position')"
                :prefix-icon="Search"
                clearable
            />
            <el-select v-model="selectedStatus" :placeholder="$t('status')" clearable>
                <el-option :label="$t('active')" value="نشط" />
                <el-option :label="$t('inactive')" value="غير نشط" />
            </el-select>
            <el-button type="primary" :icon="Refresh" @click="fetchEmployees">
                {{ $t('update') }}
            </el-button>
        </AdminFilterBar>

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
                <el-table-column :label="$t('photo')" width="90">
                    <template #default="{ row }">
                        <EntityImage :src="row.avatar" type="employee" :size="40" shape="circle" />
                    </template>
                </el-table-column>
                <el-table-column prop="name" :label="$t('employee')" />
                <el-table-column prop="department" :label="$t('department')" />
                <el-table-column prop="position" :label="$t('position')" />
                <el-table-column prop="email" :label="$t('mail')" />
                <el-table-column prop="phone" :label="$t('phone')" width="140" />
                <!--
                    Where the person works, and whether they can sign in. Both
                    are needed for the field app, and an admin previously had to
                    open each employee in turn to find out.
                -->
                <el-table-column :label="$t('linked_warehouse')" width="170">
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
                <el-table-column :label="$t('login_account')" width="120">
                    <template #default="{ row }">
                        <el-tag :type="row.user_id ? 'success' : 'info'" effect="plain" size="small">
                            {{ row.user_id ? $t('enabled') : $t('none') }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="status" :label="$t('status')" width="120">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 'نشط' ? 'success' : 'info'">
                            {{ row.status }}
                        </el-tag>
                    </template>
                </el-table-column>
                <!-- What has built up towards the benefit, on the person it
                     belongs to rather than in a report elsewhere. -->
                <el-table-column :label="$t('end_of_service_accrued')" width="150" align="right">
                    <template #default="{ row }">
                        <span v-if="Number(row.end_of_service_accrued) > 0">
                            {{ money(row.end_of_service_accrued) }}
                        </span>
                        <span v-else class="muted">—</span>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('procedures')" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button-group>
                            <el-button :icon="Edit" size="small" @click="editEmployee(row)" />
                            <el-button
                                v-if="Number(row.end_of_service_accrued) > 0"
                                size="small"
                                type="success"
                                plain
                                @click="openSettlement(row)"
                            >
                                {{ $t('settle_end_of_service') }}
                            </el-button>
                            <el-button :icon="Delete" size="small" type="danger" @click="deleteEmployee(row)" />
                        </el-button-group>
                    </template>
                </el-table-column>
            </el-table>

            <!-- Paying out what the monthly accruals built up. -->
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

            <div v-if="!store.loading && !filteredEmployees.length" class="empty-state">
                {{ $t('there_are_no_employees_matching') }}
            </div>
        </el-card>
    </div>
</template>

<script setup>
import EntityImage from '@/components/admin/EntityImage.vue';
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useEmployeesStore } from '@/stores/employees';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminFilterBar from '@/components/admin/AdminFilterBar.vue';
import {
    Plus,
    Search,
    Refresh,
    Edit,
    Delete
} from '@element-plus/icons-vue';

const { t } = useI18n();
const router = useRouter();
const store = useEmployeesStore();

/* ------------------------------------------------------------------ *
 * End-of-service settlement
 *
 * Paying out what the monthly accruals built up. It lives here rather than in
 * payroll because it is the last thing that happens to a person, not a step in
 * a monthly run — and the figure it settles is carried on their own record.
 * ------------------------------------------------------------------ */

const settlementVisible = ref(false);
const settling = ref(false);
const settlementForm = reactive({
    employee_id: null,
    accrued: 0,
    amount: 0,
    settlement: 'cash',
    paid_on: new Date().toISOString().slice(0, 10),
});

const money = (value) => Number(value || 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

// Paying more than was accrued would debit a liability that was never raised.
// The server refuses it too; catching it here saves a round trip and explains
// the refusal next to the field that caused it.
const exceedsAccrued = computed(() =>
    Number(settlementForm.amount) - Number(settlementForm.accrued) > 0.009
);

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
        ElMessage.success(t('end_of_service_settled'));
        await store.fetchEmployees();
    } catch (error) {
        ElMessage.error(error.response?.data?.message || t('failed_to_settle_end_of_service'));
    } finally {
        settling.value = false;
    }
};
const searchQuery = ref('');
const selectedStatus = ref('');

const filteredEmployees = computed(() => {
    return store.employees.filter((employee) => {
        const query = searchQuery.value.trim().toLowerCase();
        const matchesSearch = [
            employee.name,
            employee.department,
            employee.position,
            employee.email,
            employee.phone
        ].some((field) => String(field || '').toLowerCase().includes(query));

        const matchesStatus = !selectedStatus.value || employee.status === selectedStatus.value;
        return matchesSearch && matchesStatus;
    });
});

const fetchEmployees = async () => {
    await store.fetchEmployees().catch(() => {
        ElMessage.error(window.t('failed_to_load_employee_data'));
    });
};

const createEmployee = () => {
    router.push({ name: 'admin.hr.employees.create' });
};

const editEmployee = (employee) => {
    router.push({ name: 'admin.hr.employees.edit', params: { id: employee.id } });
};

const deleteEmployee = async (employee) => {
    try {
        await ElMessageBox.confirm(
            window.t('delete_employee_confirm', { name: employee.name }),
            window.t('confirm_deletion'),
            {
                confirmButtonText: window.t('yes'),
                cancelButtonText: window.t('no'),
                type: 'warning'
            }
        );

        await store.deleteEmployee(employee.id);
        ElMessage.success(window.t('the_employee_has_been_deleted'));
    } catch (error) {
        if (error !== 'cancel') {
            ElMessage.error(window.t('failed_to_delete_employee'));
        }
    }
};

onMounted(fetchEmployees);
</script>

<style scoped>
.hr-page {
    padding: 0;
}

.page-header {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
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

.page-actions {
    display: flex;
    gap: 0.75rem;
}

.filter-panel {
    margin-bottom: 1.5rem;
    border-radius: 1rem;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
}

.employee-count {
    color: #6b7c98;
    font-size: 0.95rem;
}

.table-card {
    border-radius: 1rem;
}

.error-state,
.empty-state {
    padding: 1.25rem;
    text-align: center;
    color: #6b7c98;
}
</style>

<style scoped>
.hr-page {
    padding: 0;
}

.page-header {
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

.overview-cards {
    margin-bottom: 1.5rem;
}

.summary-card {
    min-height: 110px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 0.4rem;
    border-radius: 1rem;
}

.summary-card p {
    margin: 0;
    color: #6b7c98;
    font-size: 0.95rem;
}

.summary-card h3 {
    margin: 0;
    font-size: 2rem;
    color: #253358;
}

.content-card {
    border-radius: 1rem;
}

.empty-state {
    padding: 1.5rem;
    color: #58657e;
}
</style>
