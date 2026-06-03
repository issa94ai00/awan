<template>
    <div class="hr-page hr-employee-form">
        <div class="page-header">
            <div class="page-title">
                <h1>{{ formTitle }}</h1>
                <p>أضف أو حرر بيانات موظف مع حفظ تلقائي للمعلومات الأساسية.</p>
            </div>
        </div>

        <el-card shadow="hover" class="form-card">
            <el-form :model="form" :rules="rules" ref="employeeForm" label-position="top" status-icon>
                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item label="الاسم" prop="name" :error="serverErrors.name && serverErrors.name[0]">
                                    <el-input v-model="form.name" placeholder="أدخل اسم الموظف" />
                                </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item label="القسم" prop="department" :error="serverErrors.department && serverErrors.department[0]">
                            <el-input v-model="form.department" placeholder="أدخل القسم" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item label="الوظيفة" prop="position" :error="serverErrors.position && serverErrors.position[0]">
                            <el-input v-model="form.position" placeholder="أدخل الوظيفة" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item label="الحالة" prop="status" :error="serverErrors.status && serverErrors.status[0]">
                            <el-select v-model="form.status" placeholder="اختر الحالة">
                                <el-option label="نشط" value="نشط" />
                                <el-option label="غير نشط" value="غير نشط" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item label="البريد الإلكتروني" prop="email" :error="serverErrors.email && serverErrors.email[0]">
                            <el-input type="email" v-model="form.email" placeholder="example@domain.com" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item label="الهاتف" prop="phone" :error="serverErrors.phone && serverErrors.phone[0]">
                            <el-input v-model="form.phone" placeholder="+966 50 000 0000" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12">
                        <el-form-item label="تاريخ التعيين" prop="hire_date" :error="serverErrors.hire_date && serverErrors.hire_date[0]">
                            <el-date-picker
                                v-model="form.hire_date"
                                type="date"
                                placeholder="اختر تاريخ التعيين"
                                format="YYYY-MM-DD"
                                value-format="YYYY-MM-DD"
                                style="width: 100%;"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item label="الصورة" prop="avatar" :error="serverErrors.avatar && serverErrors.avatar[0]">
                            <el-input v-model="form.avatar" placeholder="رابط صورة الموظف" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <div class="form-actions">
                    <el-button type="primary" @click="submitForm" :loading="store.loading">
                        {{ submitLabel }}
                    </el-button>
                    <el-button @click="cancel" type="text">إلغاء</el-button>
                </div>
            </el-form>
        </el-card>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { ElMessage } from 'element-plus';
import { useEmployeesStore } from '@/stores/employees';

const router = useRouter();
const route = useRoute();
const store = useEmployeesStore();
const employeeForm = ref(null);

const form = ref({
    name: '',
    department: '',
    position: '',
    status: 'نشط',
    email: '',
    phone: '',
    hire_date: '',
    avatar: '/placeholder.jpg'
});

const rules = {
    name: [{ required: true, message: 'الاسم مطلوب', trigger: 'blur' }],
    department: [{ required: true, message: 'القسم مطلوب', trigger: 'blur' }],
    position: [{ required: true, message: 'الوظيفة مطلوبة', trigger: 'blur' }],
    email: [
        { required: true, message: 'البريد مطلوب', trigger: 'blur' },
        { type: 'email', message: 'يجب أن يكون البريد صالحًا', trigger: ['blur', 'change'] }
    ],
    phone: [{ required: true, message: 'الهاتف مطلوب', trigger: 'blur' }],
    hire_date: [{ required: true, message: 'تاريخ التعيين مطلوب', trigger: 'change' }]
};

const isEdit = computed(() => !!route.params.id);
const formTitle = computed(() => (isEdit.value ? 'تعديل موظف' : 'إضافة موظف'));
const submitLabel = computed(() => (isEdit.value ? 'تحديث' : 'حفظ'));

const loadEmployee = async () => {
    if (!isEdit.value) return;
    const employee = await store.fetchEmployee(route.params.id).catch(() => null);
    if (!employee) {
        ElMessage.error('لم يتم العثور على بيانات الموظف');
        router.push({ name: 'admin.hr.employees' });
        return;
    }
    form.value = {
        name: employee.name || '',
        department: employee.department || '',
        position: employee.position || '',
        status: employee.status || 'نشط',
        email: employee.email || '',
        phone: employee.phone || '',
        hire_date: employee.hire_date || '',
        avatar: employee.avatar || '/placeholder.jpg'
    };
};

const submitForm = () => {
    employeeForm.value.validate(async (valid) => {
        if (!valid) return;
        try {
            if (isEdit.value) {
                await store.updateEmployee(route.params.id, form.value);
                ElMessage.success('تم تحديث بيانات الموظف بنجاح');
            } else {
                await store.createEmployee(form.value);
                ElMessage.success('تم إضافة الموظف بنجاح');
            }
            router.push({ name: 'admin.hr.employees' });
        } catch (err) {
            // errors handled via store.validationErrors; show top-level message if present
            ElMessage.error(err.response?.data?.message || err.message || 'حدث خطأ أثناء حفظ البيانات');
        }
    });
};

const serverErrors = computed(() => store.validationErrors || {});

const cancel = () => {
    router.push({ name: 'admin.hr.employees' });
};

onMounted(loadEmployee);
</script>

<style scoped>
.hr-page {
    padding: 0;
}

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
    color: #1f2d3d;
}

.page-title p {
    margin: 0.35rem 0 0;
    color: #5f6d85;
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
