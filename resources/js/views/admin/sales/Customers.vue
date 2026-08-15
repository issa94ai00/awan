<template>
    <div class="sales-page sales-customers">
        <div class="page-header">
            <div class="page-title">
                <h1><i class="fas fa-users"></i> {{ $t('customers') || 'العملاء' }}</h1>
                <p>{{ $t('customer_database_with_clear_font') || 'قاعدة عملاء المبيعات مع الأرصدة وإجراءات سريعة.' }}</p>
            </div>
            <div class="header-actions">
                <el-input
                    v-model="searchQuery"
                    :placeholder="$t('search_by_customer_name_email_or_phone') || 'ابحث بالاسم أو البريد أو الهاتف...'"
                    clearable
                    class="search-input"
                    :prefix-icon="Search"
                />
                <el-button :icon="Refresh" :loading="store.loading" @click="reload" />
                <el-button type="primary" :icon="Plus" @click="router.push('/admin/crm/customers/create')">
                    {{ $t('add_customer') || 'عميل جديد' }}
                </el-button>
            </div>
        </div>

        <el-row :gutter="16" class="overview-cards">
            <el-col v-for="card in summaryCards" :key="card.key" :xs="12" :sm="12" :md="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-inner">
                        <div class="stat-icon" :class="card.tone">
                            <i class="fas" :class="card.icon"></i>
                        </div>
                        <div class="stat-details">
                            <h3>{{ card.value }}</h3>
                            <p>{{ card.label }}</p>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="hover" class="table-panel">
            <template #header>
                <div class="card-header">
                    <span><i class="fas fa-address-book"></i> {{ $t('customer_list') || 'قائمة العملاء' }}</span>
                    <span class="result-count">{{ filteredCustomers.length }} / {{ store.customers.length }}</span>
                </div>
            </template>

            <el-skeleton v-if="store.loading" :rows="6" animated />
            <el-alert v-else-if="store.error" type="error" show-icon :closable="false" :title="store.error" />

            <template v-else>
                <el-table v-if="filteredCustomers.length" :data="filteredCustomers" style="width:100%" stripe>
                    <el-table-column :label="$t('name') || 'الاسم'" min-width="170">
                        <template #default="{ row }">
                            <button type="button" class="record-link" @click="viewCustomer(row)">
                                {{ row.name }}
                            </button>
                        </template>
                    </el-table-column>
                    <el-table-column prop="company" :label="$t('company') || 'الشركة'" min-width="150">
                        <template #default="{ row }">{{ row.company || '—' }}</template>
                    </el-table-column>
                    <el-table-column :label="$t('email') || 'البريد'" min-width="200">
                        <template #default="{ row }">
                            <a v-if="row.email" :href="`mailto:${row.email}`" class="contact-link">{{ row.email }}</a>
                            <span v-else class="muted">—</span>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('phone') || 'الهاتف'" width="160">
                        <template #default="{ row }">
                            <a v-if="row.phone" :href="`tel:${row.phone}`" class="contact-link" dir="ltr">{{ row.phone }}</a>
                            <span v-else class="muted">—</span>
                        </template>
                    </el-table-column>

                    <!-- Balance is what makes this a sales screen rather than a
                         contact list: it shows who owes money. -->
                    <el-table-column :label="$t('balance') || 'الرصيد'" width="150">
                        <template #default="{ row }">
                            <strong class="amount" :class="balanceTone(row)">
                                {{ formatCurrency(row.balance) }}
                            </strong>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('actions') || 'الإجراءات'" width="150" align="center" fixed="right">
                        <template #default="{ row }">
                            <el-button-group>
                                <el-tooltip :content="$t('view_details') || 'عرض الملف'" placement="top">
                                    <el-button size="small" plain @click="viewCustomer(row)">
                                        <i class="fas fa-eye"></i>
                                    </el-button>
                                </el-tooltip>
                                <el-tooltip :content="$t('record_payment') || 'تسجيل دفعة'" placement="top">
                                    <el-button size="small" type="success" plain @click="openPaymentFor(row)">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </el-button>
                                </el-tooltip>
                            </el-button-group>
                        </template>
                    </el-table-column>
                </el-table>

                <el-empty
                    v-else
                    :description="store.customers.length
                        ? ($t('there_are_no_clients_matching') || 'لا يوجد عملاء مطابقون للبحث')
                        : ($t('no_customers_yet') || 'لا يوجد عملاء بعد')"
                />

                <div v-if="store.pagination.total > store.pagination.per_page" class="pagination-bar">
                    <el-pagination
                        layout="prev, pager, next, total"
                        :total="store.pagination.total"
                        :page-size="store.pagination.per_page"
                        :current-page="store.pagination.current_page"
                        @current-change="changePage"
                    />
                </div>
            </template>
        </el-card>

        <QuickPaymentDialog
            v-model="paymentDialogVisible"
            :customer-id="paymentCustomerId"
            @saved="reload"
        />
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { Plus, Search, Refresh } from '@element-plus/icons-vue';
import { useCustomersStore } from '@/stores/customers';
import QuickPaymentDialog from '@/components/admin/sales/QuickPaymentDialog.vue';
import { formatCurrency, sumBy } from '@/utils/sales';

const { t } = useI18n();

const router = useRouter();
const store = useCustomersStore();

const searchQuery = ref('');
const paymentDialogVisible = ref(false);

const toNumber = (value) => {
    const n = parseFloat(value);
    return Number.isFinite(n) ? n : 0;
};

const withBalance = computed(() => store.customers.filter((c) => toNumber(c.balance) > 0));

const summaryCards = computed(() => [
    {
        key: 'total',
        label: window.t?.('total_customers') || t('total_customers'),
        value: store.customers.length,
        icon: 'fa-users',
        tone: 'blue',
    },
    {
        key: 'active',
        label: window.t?.('active_customers') || t('active_customers'),
        value: store.customers.filter((c) => c.is_active !== false && c.status !== 'inactive').length,
        icon: 'fa-user-check',
        tone: 'green',
    },
    {
        // Replaces a placeholder that always displayed Math.min(count, 5).
        key: 'indebted',
        label: window.t?.('customers_with_balance') || t('customers_with_balance'),
        value: withBalance.value.length,
        icon: 'fa-user-clock',
        tone: 'orange',
    },
    {
        key: 'balance',
        label: window.t?.('total_balance') || t('total_balance'),
        value: formatCurrency(sumBy(withBalance.value, 'balance')),
        icon: 'fa-scale-balanced',
        tone: 'red',
    },
]);

const filteredCustomers = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();
    if (!query) return store.customers;
    return store.customers.filter((customer) =>
        [customer.name, customer.company, customer.email, customer.phone]
            .some((field) => String(field || '').toLowerCase().includes(query))
    );
});

const balanceTone = (customer) => {
    const balance = toNumber(customer.balance);
    if (balance > 0) return 'due';
    if (balance < 0) return 'paid';
    return '';
};

// Customer profiles live in the CRM module; link there instead of duplicating.
const viewCustomer = (customer) => router.push(`/admin/crm/customers/${customer.id}`);

const paymentCustomerId = ref(null);

const openPaymentFor = (customer) => {
    paymentCustomerId.value = customer?.id ?? null;
    paymentDialogVisible.value = true;
};

const reload = () => store.fetchCustomers({ page: store.pagination.current_page }).catch(() => {});
const changePage = (page) => store.fetchCustomers({ page }).catch(() => {});

onMounted(() => {
    store.fetchCustomers().catch(() => {});
});
</script>

<style scoped>
.contact-link {
    color: var(--el-text-color-regular, #5f6d85);
    text-decoration: none;
}

.contact-link:hover {
    color: var(--el-color-primary, #409eff);
    text-decoration: underline;
}

.muted {
    color: var(--el-text-color-placeholder, #c0c4cc);
}
</style>
