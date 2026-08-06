<template>
    <div class="crm-page crm-ticket-form">
        <div class="page-header">
            <div class="page-title">
                <h1>{{ formTitle }}</h1>
                <p>{{ $t('create_or_edit_a_support') }}</p>
            </div>
        </div>

        <el-card shadow="hover" class="form-card">
            <el-form :model="form" :rules="rules" ref="ticketForm" label-position="top" status-icon>
                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('client')" prop="customer_id" :error="serverErrors.customer_id && serverErrors.customer_id[0]">
                            <el-select v-model="form.customer_id" :placeholder="$t('select_the_client')" filterable clearable>
                                <el-option
                                    v-for="customer in customers"
                                    :key="customer.id"
                                    :label="customer.name"
                                    :value="customer.id"
                                />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('ticket_priority')" prop="priority" :error="serverErrors.priority && serverErrors.priority[0]">
                            <el-select v-model="form.priority" :placeholder="$t('choose_ticket_priority')">
                                <el-option :label="$t('high')" value="high" />
                                <el-option :label="$t('medium')" value="medium" />
                                <el-option :label="$t('low')" value="low" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('the_topic')" prop="subject" :error="serverErrors.subject && serverErrors.subject[0]">
                            <el-input v-model="form.subject" :placeholder="$t('ticket_address')" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('status')" prop="status" :error="serverErrors.status && serverErrors.status[0]">
                            <el-select v-model="form.status" :placeholder="$t('choose_your_ticket_status')">
                                <el-option :label="$t('open')" value="open" />
                                <el-option :label="$t('on_hold')" value="pending" />
                                <el-option :label="$t('closed')" value="closed" />
                                <el-option :label="$t('it_has_been_solved')" value="resolved" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-form-item :label="$t('message')" prop="message" :error="serverErrors.message && serverErrors.message[0]">
                    <el-input type="textarea" v-model="form.message" :placeholder="$t('type_a_description_of_the_ticket')" rows="4" />
                </el-form-item>

                <el-form-item :label="$t('additional_notes')" prop="notes" :error="serverErrors.notes && serverErrors.notes[0]">
                    <el-input type="textarea" v-model="form.notes" :placeholder="$t('internal_comments')" rows="3" />
                </el-form-item>

                <div class="form-actions">
                    <el-button type="primary" @click="submitForm" :loading="ticketsStore.loading">{{ submitLabel }}</el-button>
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
import { useTicketsStore } from '@/stores/tickets';
import { useCustomersStore } from '@/stores/customers';

const router = useRouter();
const route = useRoute();
const ticketsStore = useTicketsStore();
const customersStore = useCustomersStore();
const ticketForm = ref(null);

const form = ref({
    customer_id: null,
    subject: '',
    message: '',
    priority: 'medium',
    status: 'open',
    notes: ''
});

const rules = {
    customer_id: [{ required: true, message: window.t('client_required'), trigger: 'change' }],
    subject: [{ required: true, message: window.t('subject_is_required'), trigger: 'blur' }],
    message: [{ required: true, message: window.t('description_required'), trigger: 'blur' }],
    priority: [{ required: true, message: window.t('priority_is_required'), trigger: 'change' }],
    status: [{ required: true, message: window.t('status_is_required'), trigger: 'change' }]
};

const isEdit = computed(() => !!route.params.id);
const formTitle = computed(() => (isEdit.value ? window.t('edit_ticket') : window.t('new_ticket')));
const submitLabel = computed(() => (isEdit.value ? window.t('update') : window.t('save')));
const customers = computed(() => customersStore.customers || []);
const serverErrors = computed(() => ticketsStore.validationErrors || {});

const loadCustomers = async () => {
    await customersStore.fetchCustomers().catch(() => {});
};

const loadTicket = async () => {
    if (!isEdit.value) return;
    const ticket = await ticketsStore.fetchTicket(route.params.id).catch(() => null);
    if (!ticket) {
        ElMessage.error(window.t('ticket_not_found'));
        router.push({ name: 'admin.crm.tickets' });
        return;
    }
    form.value = {
        customer_id: ticket.customer_id,
        subject: ticket.subject || '',
        message: ticket.message || '',
        priority: ticket.priority || 'medium',
        status: ticket.status || 'open',
        notes: ticket.notes || ''
    };
};

const submitForm = () => {
    ticketForm.value.validate(async (valid) => {
        if (!valid) return;

        try {
            if (isEdit.value) {
                await ticketsStore.updateTicket(route.params.id, form.value);
                ElMessage.success(window.t('the_ticket_has_been_updated_successfully'));
            } else {
                await ticketsStore.createTicket(form.value);
                ElMessage.success(window.t('the_ticket_was_created_successfully'));
            }
            router.push({ name: 'admin.crm.tickets' });
        } catch (err) {
            ElMessage.error(err.response?.data?.message || err.message || window.t('an_error_occurred_while_saving'));
        }
    });
};

const cancel = () => {
    router.push({ name: 'admin.crm.tickets' });
};

onMounted(async () => {
    await loadCustomers();
    await loadTicket();
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
