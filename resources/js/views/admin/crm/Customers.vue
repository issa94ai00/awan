<template>
    <div class="crm-page crm-customers">
        <div class="page-header">
            <div class="page-title">
                <h1>{{ $t('customers') }}</h1>
                <p>{{ $t('customer_database_with_quick_search') }}</p>
            </div>
            <div class="page-actions">
                <el-input v-model="searchQuery" :placeholder="$t('search_by_customer_name_email_or_phone')" clearable class="search-input" />
                <el-button type="primary" @click="createCustomer">{{ $t('new_client') }}</el-button>
            </div>
        </div>

        <el-row :gutter="16" class="overview-cards">
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>{{ $t('total_customers') }}</p>
                    <h3>{{ store.pagination.total }}</h3>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>{{ $t('latest_clients') }}</p>
                    <h3>{{ recentCount }}</h3>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="hover" class="table-panel">
            <template #header>
                <div class="card-header">
                    <span>{{ $t('customer_list') }}</span>
                </div>
            </template>

            <div v-if="store.loading" class="loading-state">{{ $t('loading') }}</div>
            <div v-else>
                <el-table v-if="filteredCustomers.length" :data="filteredCustomers" style="width:100%" stripe highlight-current-row>
                    <el-table-column prop="name" :label="$t('name')" width="200" />
                    <el-table-column prop="company" :label="$t('company')" width="180" />
                    <el-table-column prop="email" :label="$t('email')" width="220" />
                    <el-table-column prop="phone" :label="$t('phone')" width="160" />
                    <el-table-column :label="$t('status')" width="120">
                        <template #default="{ row }">
                            <el-tag :type="row.status === 'active' ? 'success' : 'info'">{{ row.status || $t('undefined') }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('procedures')" width="200">
                        <template #default="{ row }">
                            <el-button type="text" size="small" @click="editCustomer(row)">{{ $t('edit') }}</el-button>
                            <el-button type="danger" size="small" @click="deleteCustomer(row)">{{ $t('delete') }}</el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <div v-if="!filteredCustomers.length" class="empty-state">{{ $t('there_are_no_clients_matching') }}</div>
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useCustomersStore } from '@/stores/customers';

const router = useRouter();
const store = useCustomersStore();
const searchQuery = ref('');

const filteredCustomers = computed(() => {
    if (!searchQuery.value.trim()) return store.customers;
    const query = searchQuery.value.toLowerCase();
    return store.customers.filter((customer) => {
        return [
            customer.name,
            customer.company,
            customer.email,
            customer.phone,
            customer.status
        ].some((field) => String(field || '').toLowerCase().includes(query));
    });
});

const recentCount = computed(() => Math.min(store.customers.length, 5));

const createCustomer = () => {
    router.push({ name: 'admin.crm.customers.create' });
};

const editCustomer = (customer) => {
    if (!customer?.id) return;
    router.push({ name: 'admin.crm.customers.edit', params: { id: customer.id } });
};

const deleteCustomer = async (customer) => {
    if (!customer?.id) return;

    try {
        await ElMessageBox.confirm(
            window.t('are_you_sure_you_want'),
            window.t('confirm_deletion'),
            {
                confirmButtonText: window.t('yes'),
                cancelButtonText: window.t('no'),
                type: 'warning'
            }
        );

        await store.deleteCustomer(customer.id);
        ElMessage.success(window.t('the_client_has_been_deleted_successfully'));
    } catch (error) {
        if (error !== 'cancel') {
            ElMessage.error(window.t('failed_to_delete_client'));
        }
    }
};

onMounted(() => {
    store.fetchCustomers().catch(() => {});
});
</script>

<style scoped>
.crm-page { padding: 0 }
.page-header { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1.25rem }
.page-actions { display:flex; flex-wrap:wrap; align-items:center; gap:1rem }
.page-title h1 { margin:0; font-size:1.6rem; color:#1f2d3d }
.search-input { width: min(100%, 320px) }
.summary-card { min-height:110px; border-radius:10px }
.table-panel { border-radius:10px }
.loading-state, .empty-state { padding:1rem; color:#6b7c98; text-align:center }
</style>
