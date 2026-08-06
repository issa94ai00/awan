<template>
    <div class="hr-page hr-employees">
        <div class="page-header">
            <div class="page-title">
                <h1>{{ $t('employees') }}</h1>
                <p>{{ $t('manage_employee_data_view_status') }}</p>
            </div>
            <div class="page-actions">
                <el-button type="primary" :icon="Plus" @click="createEmployee">
                    {{ $t('add_an_employee') }}
                </el-button>
            </div>
        </div>

        <el-card shadow="hover" class="filter-panel">
            <el-row :gutter="20">
                <el-col :xs="24" :sm="12" :md="8">
                    <el-input
                        v-model="searchQuery"
                        :placeholder="$t('search_by_name_department_or_position')"
                        :prefix-icon="Search"
                        clearable
                    />
                </el-col>
                <el-col :xs="24" :sm="12" :md="8">
                    <el-select v-model="selectedStatus" :placeholder="$t('status')" clearable>
                        <el-option :label="$t('active')" value="نشط" />
                        <el-option :label="$t('inactive')" value="غير نشط" />
                    </el-select>
                </el-col>
                <el-col :xs="24" :sm="12" :md="8">
                    <el-button type="primary" :icon="Refresh" @click="fetchEmployees">
                        {{ $t('update') }}
                    </el-button>
                </el-col>
            </el-row>
        </el-card>

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
                        <el-avatar :src="row.avatar || '/placeholder.jpg'" size="40" />
                    </template>
                </el-table-column>
                <el-table-column prop="name" :label="$t('employee')" />
                <el-table-column prop="department" :label="$t('department')" />
                <el-table-column prop="position" :label="$t('position')" />
                <el-table-column prop="email" :label="$t('mail')" />
                <el-table-column prop="phone" :label="$t('phone')" width="140" />
                <el-table-column prop="status" :label="$t('status')" width="120">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 'نشط' ? 'success' : 'info'">
                            {{ row.status }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('procedures')" width="150" fixed="right">
                    <template #default="{ row }">
                        <el-button-group>
                            <el-button :icon="Edit" size="small" @click="editEmployee(row)" />
                            <el-button :icon="Delete" size="small" type="danger" @click="deleteEmployee(row)" />
                        </el-button-group>
                    </template>
                </el-table-column>
            </el-table>

            <div v-if="!store.loading && !filteredEmployees.length" class="empty-state">
                {{ $t('there_are_no_employees_matching') }}
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useEmployeesStore } from '@/stores/employees';
import {
    Plus,
    Search,
    Refresh,
    Edit,
    Delete
} from '@element-plus/icons-vue';

const router = useRouter();
const store = useEmployeesStore();
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
