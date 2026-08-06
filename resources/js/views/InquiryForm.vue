<template>
    <div class="inquiry-form">
        <el-card shadow="hover">
            <template #header>
                <span>إرسال استفسار جديد</span>
            </template>

            <el-form 
                ref="formRef" 
                :model="form" 
                :rules="rules" 
                label-width="120px"
                v-loading="loading"
            >
                <el-form-item label="الاسم" prop="name">
                    <el-input v-model="form.name" placeholder="أدخل اسمك الكامل"/>
                </el-form-item>

                <el-form-item label="البريد الإلكتروني" prop="email">
                    <el-input v-model="form.email" placeholder="أدخل بريدك الإلكتروني"/>
                </el-form-item>

                <el-form-item label="رقم الهاتف" prop="phone">
                    <el-input v-model="form.phone" placeholder="أدخل رقم هاتفك"/>
                </el-form-item>

                <el-form-item label="نوع الاستفسار" prop="subject">
                    <el-select v-model="form.subject" placeholder="اختر نوع الاستفسار" style="width:100%">
                        <el-option 
                            v-for="opt in subjectOptions" 
                            :key="opt.value" 
                            :label="opt.label" 
                            :value="opt.value"
                        />
                    </el-select>
                </el-form-item>

                <el-form-item label="الأولوية" prop="priority">
                    <el-select v-model="form.priority" placeholder="اختر الأولوية" style="width:100%">
                        <el-option label="منخفض" value="low"/>
                        <el-option label="متوسط" value="medium"/>
                        <el-option label="عالي" value="high"/>
                        <el-option label="عاجل" value="urgent"/>
                    </el-select>
                </el-form-item>

                <el-form-item label="المنتج" prop="product_id">
                    <el-select 
                        v-model="form.product_id" 
                        placeholder="اختر المنتج (اختياري)" 
                        filterable 
                        clearable
                        style="width:100%"
                    >
                        <el-option 
                            v-for="product in products" 
                            :key="product.id" 
                            :label="product.name_ar" 
                            :value="product.id"
                        />
                    </el-select>
                </el-form-item>

                <el-form-item label="الرسالة" prop="message">
                    <el-input 
                        v-model="form.message" 
                        type="textarea" 
                        :rows="6"
                        placeholder="اكتب استفسارك هنا..."
                        maxlength="5000"
                        show-word-limit
                    />
                </el-form-item>

                <el-form-item>
                    <el-button type="primary" @click="submit" :loading="submitting" size="large">
                        إرسال الاستفسار
                    </el-button>
                    <el-button @click="resetForm" size="large">إعادة تعيين</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <el-dialog v-model="successDialog" title="تم الإرسال بنجاح" width="400px" center>
            <div style="text-align:center; padding:1rem;">
                <el-icon :size="60" color="#67c23a" style="margin-bottom:1rem">
                    <CircleCheck />
                </el-icon>
                <p style="font-size:1.1rem;">تم إرسال استفسارك بنجاح</p>
                <p style="color:#666; margin-top:0.5rem;">سنتواصل معك في أقرب وقت ممكن</p>
            </div>
            <template #footer>
                <div style="text-align:center;">
                    <el-button type="primary" @click="successDialog = false">حسناً</el-button>
                </div>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useInquiriesStore } from '@/stores/inquiries';
import { useProductsStore } from '@/stores/products';
import { ElMessage } from 'element-plus';
import { CircleCheck } from '@element-plus/icons-vue';

const inquiriesStore = useInquiriesStore();
const productsStore = useProductsStore();

const formRef = ref(null);
const loading = ref(false);
const submitting = ref(false);
const successDialog = ref(false);
const products = ref([]);

const form = ref({
    name: '',
    email: '',
    phone: '',
    subject: '',
    priority: 'medium',
    product_id: null,
    message: ''
});

const subjectOptions = ref([
    { value: 'product_inquiry', label: 'استفسار عن منتج' },
    { value: 'price_quote', label: 'طلب عرض سعر' },
    { value: 'delivery', label: 'التوصيل والشحن' },
    { value: 'technical_support', label: 'الدعم الفني' },
    { value: 'partnership', label: 'شراكة تجارية' },
    { value: 'other', label: 'أخرى' },
]);

const rules = {
    name: [
        { required: true, message: 'الاسم مطلوب', trigger: 'blur' },
        { min: 2, max: 255, message: 'الاسم يجب أن يكون بين 2 و 255 حرف', trigger: 'blur' }
    ],
    email: [
        { type: 'email', message: 'البريد الإلكتروني غير صحيح', trigger: 'blur' }
    ],
    phone: [
        { required: true, message: 'رقم الهاتف مطلوب', trigger: 'blur' },
        { min: 5, max: 50, message: 'رقم الهاتف غير صحيح', trigger: 'blur' }
    ],
    subject: [
        { required: true, message: 'نوع الاستفسار مطلوب', trigger: 'change' }
    ],
    message: [
        { required: true, message: 'الرسالة مطلوبة', trigger: 'blur' },
        { min: 10, max: 5000, message: 'الرسالة يجب أن تكون بين 10 و 5000 حرف', trigger: 'blur' }
    ]
};

async function loadProducts() {
    loading.value = true;
    try {
        await productsStore.fetchProducts();
        products.value = productsStore.products;
    } catch (error) {
        console.error('Failed to load products:', error);
    } finally {
        loading.value = false;
    }
}

async function submit() {
    if (!formRef.value) return;
    
    await formRef.value.validate(async (valid) => {
        if (!valid) return;
        
        submitting.value = true;
        try {
            await inquiriesStore.create(form.value);
            successDialog.value = true;
            resetForm();
        } catch (error) {
            ElMessage.error('فشل في إرسال الاستفسار');
            console.error('Failed to submit inquiry:', error);
        } finally {
            submitting.value = false;
        }
    });
}

function resetForm() {
    if (!formRef.value) return;
    formRef.value.resetFields();
    form.value = {
        name: '',
        email: '',
        phone: '',
        subject: '',
        priority: 'medium',
        product_id: null,
        message: ''
    };
}

onMounted(() => loadProducts());
</script>

<style scoped>
.inquiry-form { 
    max-width: 800px; 
    margin: 0 auto; 
    padding: 2rem 1rem;
}
</style>
