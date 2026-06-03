<template>
    <div class="crm-page crm-customer-form">
        <div class="page-header">
            <div class="page-title">
                <h1>{{ formTitle }}</h1>
                <p>أضف أو حرر بيانات عميل لتبسيط إدارة العلاقات.</p>
            </div>
        </div>

        <el-card shadow="hover" class="form-card">
            <el-form :model="form" :rules="rules" ref="customerForm" label-position="top" status-icon>
                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item label="الاسم" prop="name" :error="serverErrors.name && serverErrors.name[0]">
                            <el-input v-model="form.name" placeholder="اسم العميل" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item label="الشركة" prop="company" :error="serverErrors.company && serverErrors.company[0]">
                            <el-input v-model="form.company" placeholder="اسم الشركة" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item label="البريد الإلكتروني" prop="email" :error="serverErrors.email && serverErrors.email[0]">
                            <el-input v-model="form.email" placeholder="example@domain.com" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item label="الهاتف" prop="phone" :error="serverErrors.phone && serverErrors.phone[0]">
                            <el-input v-model="form.phone" placeholder="رقم الهاتف" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item label="العنوان" prop="address" :error="serverErrors.address && serverErrors.address[0]">
                            <el-input v-model="form.address" placeholder="عنوان العميل" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item label="الحالة" prop="status" :error="serverErrors.status && serverErrors.status[0]">
                            <el-select v-model="form.status" placeholder="اختر الحالة">
                                <el-option label="نشط" value="active" />
                                <el-option label="غير نشط" value="inactive" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-form-item label="الملاحظات" prop="notes" :error="serverErrors.notes && serverErrors.notes[0]">
                    <el-input type="textarea" v-model="form.notes" placeholder="ملاحظات إضافية" rows="4" />
                </el-form-item>

                <div class="form-actions">
                    <el-button type="primary" @click="submitForm" :loading="customersStore.loading">{{ submitLabel }}</el-button>
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
    name: [{ required: true, message: 'الاسم مطلوب', trigger: 'blur' }],
    email: [{ type: 'email', message: 'البريد الإلكتروني غير صالح', trigger: 'blur' }],
    status: [{ required: true, message: 'الحالة مطلوبة', trigger: 'change' }]
};

const isEdit = computed(() => !!route.params.id);
const formTitle = computed(() => (isEdit.value ? 'تعديل العميل' : 'عميل جديد'));
const submitLabel = computed(() => (isEdit.value ? 'تحديث' : 'حفظ'));
const serverErrors = computed(() => customersStore.validationErrors || {});

const loadCustomer = async () => {
    if (!isEdit.value) return;
    const customer = await customersStore.fetchCustomer(route.params.id).catch(() => null);
    if (!customer) {
        ElMessage.error('لم يتم العثور على العميل');
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
                ElMessage.success('تم تحديث العميل بنجاح');
            } else {
                await customersStore.createCustomer(form.value);
                ElMessage.success('تم إنشاء العميل بنجاح');
            }
            router.push({ name: 'admin.crm.customers' });
        } catch (err) {
            ElMessage.error(err.response?.data?.message || err.message || 'حدث خطأ أثناء الحفظ');
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
