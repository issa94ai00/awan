<template>
    <div class="crm-page crm-customer-form">
        <div class="page-header">
            <div class="page-title">
                <h1>{{ formTitle }}</h1>
                <p>{{ $t('add_or_edit_customer_data') }}</p>
            </div>
        </div>

        <el-card shadow="hover" class="form-card">
            <el-form :model="form" :rules="rules" ref="customerForm" label-position="top" status-icon>
                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('name')" prop="name" :error="serverErrors.name && serverErrors.name[0]">
                            <el-input v-model="form.name" :placeholder="$t('customer_name')" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('company')" prop="company" :error="serverErrors.company && serverErrors.company[0]">
                            <el-input v-model="form.company" :placeholder="$t('company_name')" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('email')" prop="email" :error="serverErrors.email && serverErrors.email[0]">
                            <el-input v-model="form.email" placeholder="example@domain.com" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('phone')" prop="phone" :error="serverErrors.phone && serverErrors.phone[0]">
                            <el-input v-model="form.phone" :placeholder="$t('phone')" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('address')" prop="address" :error="serverErrors.address && serverErrors.address[0]">
                            <el-input v-model="form.address" :placeholder="$t('customer_address')" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('status')" prop="status" :error="serverErrors.status && serverErrors.status[0]">
                            <el-select v-model="form.status" :placeholder="$t('choose_the_status')">
                                <el-option :label="$t('active')" value="active" />
                                <el-option :label="$t('inactive')" value="inactive" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-form-item :label="$t('notes')" prop="notes" :error="serverErrors.notes && serverErrors.notes[0]">
                    <el-input type="textarea" v-model="form.notes" :placeholder="$t('additional_notes')" rows="4" />
                </el-form-item>

                <div class="form-actions">
                    <el-button type="primary" @click="submitForm" :loading="customersStore.loading">{{ submitLabel }}</el-button>
                    <el-button type="text" @click="cancel">{{ $t('cancel') }}</el-button>
                </div>
            </el-form>
        </el-card>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { ElMessage } from 'element-plus';
import { useCustomersStore } from '@/stores/customers';

const router = useRouter();
const route = useRoute();
const customersStore = useCustomersStore();
const customerForm = ref(null);

const form = ref({
    name: '',
    email: '',
    phone: '',
    company: '',
    address: '',
    status: 'active',
    notes: ''
});

const rules = {
    name: [{ required: true, message: window.t('name_required'), trigger: 'blur' }],
    email: [{ type: 'email', message: window.t('email_is_invalid'), trigger: 'blur' }],
    status: [{ required: true, message: window.t('status_is_required'), trigger: 'change' }]
};

const isEdit = computed(() => !!route.params.id);
const formTitle = computed(() => (isEdit.value ? window.t('client_modification') : window.t('new_client')));
const submitLabel = computed(() => (isEdit.value ? window.t('update') : window.t('save')));
const serverErrors = computed(() => customersStore.validationErrors || {});

const loadCustomer = async () => {
    if (!isEdit.value) return;
    const customer = await customersStore.fetchCustomer(route.params.id).catch(() => null);
    if (!customer) {
        ElMessage.error(window.t('client_not_found'));
        router.push({ name: 'admin.crm.customers' });
        return;
    }
    form.value = {
        name: customer.name || '',
        email: customer.email || '',
        phone: customer.phone || '',
        company: customer.company || '',
        address: customer.address || '',
        status: customer.status || 'active',
        notes: customer.notes || ''
    };
};

const submitForm = () => {
    customerForm.value.validate(async (valid) => {
        if (!valid) return;

        try {
            if (isEdit.value) {
                await customersStore.updateCustomer(route.params.id, form.value);
                ElMessage.success(window.t('the_client_has_been_updated_successfully'));
            } else {
                await customersStore.createCustomer(form.value);
                ElMessage.success(window.t('the_client_has_been_created_successfully'));
            }
            router.push({ name: 'admin.crm.customers' });
        } catch (err) {
            ElMessage.error(err.response?.data?.message || err.message || window.t('an_error_occurred_while_saving'));
        }
    });
};

const cancel = () => {
    router.push({ name: 'admin.crm.customers' });
};

onMounted(async () => {
    await loadCustomer();
});
</script>

<style scoped>
.crm-page { padding: 0; }

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
}

.form-card {
    border-radius: 1rem;
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
}
</style>
