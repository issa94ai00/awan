<template>
    <div class="hr-page hr-employees">
        <div class="page-header">
            <div class="page-title">
                <h1>الموظفون</h1>
                <p>إدارة بيانات الموظفين، عرض الحالة، والتحكم في السجل الوظيفي.</p>
            </div>
            <div class="page-actions">
                <el-button type="primary" :icon="Plus" @click="createEmployee">
                    إضافة موظف
                </el-button>
            </div>
        </div>

        <el-card shadow="hover" class="filter-panel">
            <el-row :gutter="20">
                <el-col :xs="24" :sm="12" :md="8">
                    <el-input
                        v-model="searchQuery"
                        placeholder="ابحث بالاسم أو القسم أو الوظيفة"
                        :prefix-icon="Search"
                        clearable
                    />
                </el-col>
                <el-col :xs="24" :sm="12" :md="8">
                    <el-select v-model="selectedStatus" placeholder="الحالة" clearable>
                        <el-option label="نشط" value="نشط" />
                        <el-option label="غير نشط" value="غير نشط" />
                    </el-select>
                </el-col>
                <el-col :xs="24" :sm="12" :md="8">
                    <el-button type="primary" :icon="Refresh" @click="fetchEmployees">
                        تحديث
                    </el-button>
                </el-col>
            </el-row>
        </el-card>

        <el-card shadow="hover" class="table-card">
            <template #header>
                <div class="card-header">
                    <span>قائمة الموظفين</span>
                    <span class="employee-count">{{ filteredEmployees.length }} موظف</span>
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
                <el-table-column label="الصورة" width="90">
                    <template #default="{ row }">
                        <el-avatar :src="row.avatar || '/placeholder.jpg'" size="40" />
                    </template>
                </el-table-column>
                <el-table-column prop="name" label="الموظف" />
                <el-table-column prop="department" label="القسم" />
                <el-table-column prop="position" label="الوظيفة" />
                <el-table-column prop="email" label="البريد" />
                <el-table-column prop="phone" label="الهاتف" width="140" />
                <el-table-column prop="status" label="الحالة" width="120">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 'نشط' ? 'success' : 'info'">
                            {{ row.status }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="الإجراءات" width="150" fixed="right">
                    <template #default="{ row }">
                        <el-button-group>
                            <el-button :icon="Edit" size="small" @click="editEmployee(row)" />
                            <el-button :icon="Delete" size="small" type="danger" @click="deleteEmployee(row)" />
                        </el-button-group>
                    </template>
                </el-table-column>
            </el-table>

            <div v-if="!store.loading && !filteredEmployees.length" class="empty-state">
                لا يوجد موظفين تطابق البحث.
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
        ElMessage.error('فشل في تحميل بيانات الموظفين');
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
            `هل تود حذف الموظف ${employee.name}؟`,
            'تأكيد الحذف',
            {
                confirmButtonText: 'نعم',
                cancelButtonText: 'لا',
                type: 'warning'
            }
        );

        await store.deleteEmployee(employee.id);
        ElMessage.success('تم حذف الموظف بنجاح');
    } catch (error) {
        if (error !== 'cancel') {
            ElMessage.error('فشل في حذف الموظف');
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
