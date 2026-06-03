<template>
    <div class="crm-page crm-ticket-form">
        <div class="page-header">
            <div class="page-title">
                <h1>{{ formTitle }}</h1>
                <p>إنشاء أو تعديل تذكرة دعم مع اختيار العميل والأولوية.</p>
            </div>
        </div>

        <el-card shadow="hover" class="form-card">
            <el-form :model="form" :rules="rules" ref="ticketForm" label-position="top" status-icon>
                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item label="العميل" prop="customer_id" :error="serverErrors.customer_id && serverErrors.customer_id[0]">
                            <el-select v-model="form.customer_id" placeholder="اختر العميل" filterable clearable>
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
                        <el-form-item label="أولوية التذكرة" prop="priority" :error="serverErrors.priority && serverErrors.priority[0]">
                            <el-select v-model="form.priority" placeholder="اختر أولوية التذكرة">
                                <el-option label="عالية" value="high" />
                                <el-option label="متوسطة" value="medium" />
                                <el-option label="منخفضة" value="low" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item label="الموضوع" prop="subject" :error="serverErrors.subject && serverErrors.subject[0]">
                            <el-input v-model="form.subject" placeholder="عنوان التذكرة" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item label="الحالة" prop="status" :error="serverErrors.status && serverErrors.status[0]">
                            <el-select v-model="form.status" placeholder="اختر حالة التذكرة">
                                <el-option label="مفتوحة" value="open" />
                                <el-option label="قيد الانتظار" value="pending" />
                                <el-option label="مغلقة" value="closed" />
                                <el-option label="تم حلها" value="resolved" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-form-item label="الرسالة" prop="message" :error="serverErrors.message && serverErrors.message[0]">
                    <el-input type="textarea" v-model="form.message" placeholder="اكتب وصف التذكرة" rows="4" />
                </el-form-item>

                <el-form-item label="ملاحظات إضافية" prop="notes" :error="serverErrors.notes && serverErrors.notes[0]">
                    <el-input type="textarea" v-model="form.notes" placeholder="تعليقات داخلية" rows="3" />
                </el-form-item>

                <div class="form-actions">
                    <el-button type="primary" @click="submitForm" :loading="ticketsStore.loading">{{ submitLabel }}</el-button>
                    <el-button type="text" @click="cancel">إلغاء</el-button>
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
    customer_id: [{ required: true, message: 'العميل مطلوب', trigger: 'change' }],
    subject: [{ required: true, message: 'الموضوع مطلوب', trigger: 'blur' }],
    message: [{ required: true, message: 'الوصف مطلوب', trigger: 'blur' }],
    priority: [{ required: true, message: 'الأولوية مطلوبة', trigger: 'change' }],
    status: [{ required: true, message: 'الحالة مطلوبة', trigger: 'change' }]
};

const isEdit = computed(() => !!route.params.id);
const formTitle = computed(() => (isEdit.value ? 'تعديل التذكرة' : 'تذكرة جديدة'));
const submitLabel = computed(() => (isEdit.value ? 'تحديث' : 'حفظ'));
const customers = computed(() => customersStore.customers || []);
const serverErrors = computed(() => ticketsStore.validationErrors || {});

const loadCustomers = async () => {
    await customersStore.fetchCustomers().catch(() => {});
};

const loadTicket = async () => {
    if (!isEdit.value) return;
    const ticket = await ticketsStore.fetchTicket(route.params.id).catch(() => null);
    if (!ticket) {
        ElMessage.error('لم يتم العثور على التذكرة');
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
                ElMessage.success('تم تحديث التذكرة بنجاح');
            } else {
                await ticketsStore.createTicket(form.value);
                ElMessage.success('تم إنشاء التذكرة بنجاح');
            }
            router.push({ name: 'admin.crm.tickets' });
        } catch (err) {
            ElMessage.error(err.response?.data?.message || err.message || 'حدث خطأ أثناء الحفظ');
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
