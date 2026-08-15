<template>
    <div class="hr-page hr-employee-customers">
        <AdminPageHeader
            :title="$t('employee_customer_relationship')"
            :subtitle="$t('manage_employee_customer_relationships')"
        >
            <template #actions>
                <el-button :icon="ArrowLeft" @click="goBack">
                    {{ $t('back') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <AdminFilterBar>
            <el-select 
                v-model="selectedEmployeeId" 
                :placeholder="$t('select_employee')" 
                clearable
                @change="handleEmployeeChange"
            >
                <el-option
                    v-for="employee in employees"
                    :key="employee.id"
                    :label="employee.name"
                    :value="employee.id"
                />
            </el-select>
        </AdminFilterBar>

        <el-card v-if="selectedEmployeeId" shadow="hover" class="table-card">
            <template #header>
                <div class="card-header">
                    <span>{{ $t('linked_customers') }}</span>
                    <el-button type="primary" :icon="Plus" @click="openAttachModal">
                        {{ $t('add_customers') }}
                    </el-button>
                </div>
            </template>

            <el-table
                v-loading="loading"
                :data="employeeCustomers"
                style="width: 100%"
                stripe
                highlight-current-row
            >
                <el-table-column prop="name" :label="$t('customer_name')" />
                <el-table-column prop="email" :label="$t('email')" />
                <el-table-column prop="phone" :label="$t('phone')" />
                <el-table-column prop="company" :label="$t('company')" />
                <el-table-column :label="$t('total_purchases')">
                    <template #default="{ row }">
                        {{ formatCurrency(row.total_purchases || 0) }}
                    </template>
                </el-table-column>
                <el-table-column :label="$t('last_purchase')">
                    <template #default="{ row }">
                        {{ formatDate(row.last_purchase_at) }}
                    </template>
                </el-table-column>
                <el-table-column :label="$t('actions')" width="150" fixed="right">
                    <template #default="{ row }">
                        <el-button 
                            type="danger" 
                            :icon="Link" 
                            size="small"
                            @click="detachCustomer(row)"
                        >
                            {{ $t('unlink') }}
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div v-if="!loading && !employeeCustomers.length" class="empty-state">
                {{ $t('no_linked_customers') }}
            </div>
        </el-card>

        <!-- Attach Customers Modal -->
        <el-dialog
            v-model="attachModalVisible"
            :title="$t('add_customers_to_employee')"
            width="600px"
        >
            <el-input
                v-model="customerSearchQuery"
                :placeholder="$t('search_customers')"
                :prefix-icon="Search"
                clearable
                @input="handleCustomerSearch"
            />
            
            <div class="customers-list">
                <el-checkbox-group v-model="selectedCustomerIds">
                    <div 
                        v-for="customer in availableCustomers" 
                        :key="customer.id"
                        class="customer-item"
                    >
                        <el-checkbox :label="customer.id">
                            <div class="customer-info">
                                <div class="customer-name">{{ customer.name }}</div>
                                <div class="customer-email">{{ customer.email || $t('no_email') }}</div>
                            </div>
                        </el-checkbox>
                    </div>
                </el-checkbox-group>
                
                <div v-if="!availableCustomers.length && !searching" class="empty-state">
                    {{ $t('no_customers_found') }}
                </div>
            </div>

            <div class="selected-customers">
                <div class="section-title">{{ $t('selected_customers') }}</div>
                <el-tag
                    v-for="id in selectedCustomerIds"
                    :key="id"
                    closable
                    @close="removeSelectedCustomer(id)"
                    style="margin: 4px"
                >
                    {{ getCustomerName(id) }}
                </el-tag>
            </div>

            <template #footer>
                <el-button @click="attachModalVisible = false">
                    {{ $t('cancel') }}
                </el-button>
                <el-button type="primary" @click="attachCustomers" :loading="attaching">
                    {{ $t('add') }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import { ArrowLeft, Plus, Search, Link } from '@element-plus/icons-vue';
import api from '@/api';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminFilterBar from '@/components/admin/AdminFilterBar.vue';

const router = useRouter();
const selectedEmployeeId = ref(null);
const employees = ref([]);
const employeeCustomers = ref([]);
const loading = ref(false);
const attachModalVisible = ref(false);
const customerSearchQuery = ref('');
const availableCustomers = ref([]);
const selectedCustomerIds = ref([]);
const searching = ref(false);
const attaching = ref(false);

const goBack = () => {
    router.push({ name: 'admin.hr.index' });
};

const loadEmployees = async () => {
    try {
        const response = await api.get('/admin/employees');
        if (response.data.success && response.data.data) {
            employees.value = response.data.data.employees || [];
        } else {
            employees.value = [];
        }
    } catch (error) {
        console.error('Failed to load employees:', error);
        ElMessage.error('Failed to load employees');
        employees.value = [];
    }
};

const handleEmployeeChange = () => {
    if (selectedEmployeeId.value) {
        loadEmployeeCustomers();
    } else {
        employeeCustomers.value = [];
    }
};

const loadEmployeeCustomers = async () => {
    if (!selectedEmployeeId.value) return;

    loading.value = true;
    try {
        const response = await api.get(`/admin/employees/${selectedEmployeeId.value}/customers`);
        if (response.data.success && response.data.data) {
            employeeCustomers.value = Array.isArray(response.data.data) ? response.data.data : [];
        } else {
            employeeCustomers.value = [];
        }
    } catch (error) {
        console.error('Failed to load customers:', error);
        ElMessage.error('Failed to load customers');
        employeeCustomers.value = [];
    } finally {
        loading.value = false;
    }
};

const openAttachModal = () => {
    selectedCustomerIds.value = [];
    customerSearchQuery.value = '';
    availableCustomers.value = [];
    attachModalVisible.value = true;
    searchCustomers('');
};

const handleCustomerSearch = () => {
    searchCustomers(customerSearchQuery.value);
};

const searchCustomers = async (query) => {
    searching.value = true;
    try {
        const response = await api.get(`/admin/sales/customers?search=${query}`);
        if (response.data.success && response.data.data) {
            availableCustomers.value = response.data.data.customers || [];
        } else {
            availableCustomers.value = [];
        }
    } catch (error) {
        console.error('Failed to search customers:', error);
        availableCustomers.value = [];
    } finally {
        searching.value = false;
    }
};

const removeSelectedCustomer = (id) => {
    const index = selectedCustomerIds.value.indexOf(id);
    if (index > -1) {
        selectedCustomerIds.value.splice(index, 1);
    }
};

const getCustomerName = (id) => {
    const customer = availableCustomers.value.find(c => c.id === id);
    return customer ? customer.name : 'Unknown';
};

const attachCustomers = async () => {
    if (selectedCustomerIds.value.length === 0) {
        ElMessage.warning('Please select at least one customer');
        return;
    }

    attaching.value = true;
    try {
        const response = await api.post(
            `/api/v1/admin/employees/${selectedEmployeeId.value}/customers/attach`,
            { customer_ids: selectedCustomerIds.value }
        );
        
        if (response.data.success) {
            ElMessage.success('Customers attached successfully');
            attachModalVisible.value = false;
            loadEmployeeCustomers();
        }
    } catch (error) {
        ElMessage.error('Failed to attach customers');
    } finally {
        attaching.value = false;
    }
};

const detachCustomer = async (customer) => {
    try {
        await ElMessage.confirm(
            `Are you sure you want to unlink ${customer.name}?`,
            'Confirm Unlink',
            {
                confirmButtonText: 'Yes',
                cancelButtonText: 'No',
                type: 'warning'
            }
        );

        const response = await api.post(
            `/api/v1/admin/employees/${selectedEmployeeId.value}/customers/detach`,
            { customer_ids: [customer.id] }
        );

        if (response.data.success) {
            ElMessage.success('Customer unlinked successfully');
            loadEmployeeCustomers();
        }
    } catch (error) {
        if (error !== 'cancel') {
            ElMessage.error('Failed to unlink customer');
        }
    }
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('ar-SA', { 
        style: 'currency', 
        currency: 'SAR' 
    }).format(value);
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('ar-SA');
};

onMounted(() => {
    loadEmployees();
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

.table-card {
    border-radius: 1rem;
}

.empty-state {
    padding: 1.25rem;
    text-align: center;
    color: #6b7c98;
}

.customers-list {
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid #dcdfe6;
    border-radius: 4px;
    padding: 10px;
    margin: 15px 0;
}

.customer-item {
    padding: 8px;
    border-bottom: 1px solid #f5f5f5;
}

.customer-item:last-child {
    border-bottom: none;
}

.customer-info {
    margin-right: 10px;
}

.customer-name {
    font-weight: 600;
    margin-bottom: 2px;
}

.customer-email {
    font-size: 12px;
    color: #909399;
}

.selected-customers {
    margin-top: 15px;
}

.section-title {
    font-weight: 600;
    margin-bottom: 8px;
    color: #303133;
}
</style>
