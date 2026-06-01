<template>
    <div class="categories-index">
        <el-card shadow="hover">
            <template #header>
                <div class="card-header">
                    <span>إدارة الفئات</span>
                    <el-button type="primary" :icon="Plus" @click="goToCreate">
                        إضافة فئة
                    </el-button>
                </div>
            </template>

            <el-table
                v-loading="loading"
                :data="categories"
                style="width: 100%"
                stripe
            >
                <el-table-column prop="id" label="#" width="60" />
                <el-table-column prop="name_ar" label="الاسم (عربي)" />
                <el-table-column prop="name_en" label="الاسم (إنجليزي)" />
                <el-table-column prop="slug" label="Slug" />
                <el-table-column prop="products_count" label="عدد المنتجات" width="120">
                    <template #default="{ row }">
                        <el-tag>{{ row.products_count || 0 }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="is_active" label="الحالة" width="100">
                    <template #default="{ row }">
                        <el-switch
                            v-model="row.is_active"
                            @change="toggleStatus(row)"
                        />
                    </template>
                </el-table-column>
                <el-table-column label="الإجراءات" width="150" fixed="right">
                    <template #default="{ row }">
                        <el-button-group>
                            <el-button :icon="Edit" size="small" @click="editCategory(row)" />
                            <el-button :icon="Delete" size="small" type="danger" @click="deleteCategory(row)" />
                        </el-button-group>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Edit, Delete } from '@element-plus/icons-vue';

const router = useRouter();
const loading = ref(false);
const categories = ref([
    { id: 1, name_ar: 'إلكترونيات', name_en: 'Electronics', slug: 'electronics', products_count: 45, is_active: true },
    { id: 2, name_ar: 'ملابس', name_en: 'Clothing', slug: 'clothing', products_count: 32, is_active: true },
    { id: 3, name_ar: 'أثاث', name_en: 'Furniture', slug: 'furniture', products_count: 18, is_active: false }
]);

const fetchCategories = async () => {
    loading.value = true;
    // Fetch from API
    setTimeout(() => {
        loading.value = false;
    }, 500);
};

const goToCreate = () => {
    router.push('/admin/categories/create');
};

const editCategory = (category) => {
    router.push(`/admin/categories/${category.id}/edit`);
};

const deleteCategory = async (category) => {
    try {
        await ElMessageBox.confirm(
            `هل أنت متأكد من حذف الفئة "${category.name_ar}"؟`,
            'تأكيد الحذف',
            {
                confirmButtonText: 'نعم',
                cancelButtonText: 'لا',
                type: 'warning'
            }
        );
        
        // Delete from API
        ElMessage.success('تم حذف الفئة بنجاح');
        fetchCategories();
    } catch {
        // User cancelled
    }
};

const toggleStatus = async (category) => {
    // Update status via API
    ElMessage.success('تم تحديث الحالة بنجاح');
};

onMounted(() => {
    fetchCategories();
});
</script>

<style scoped>
.categories-index {
    padding: 0;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
</style>
