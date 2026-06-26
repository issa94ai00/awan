<template>
    <div class="categories-index">
        <el-card shadow="hover">
            <template #header>
                <div class="card-header">
                    <span>{{ $t('category_management') }}</span>
                    <el-button type="primary" :icon="Plus" @click="goToCreate">
                        {{ $t('add_category') }}
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
                <el-table-column prop="name_ar" :label="$t('name_arabic')" />
                <el-table-column prop="name_en" :label="$t('name_english')" />
                <el-table-column prop="slug" label="Slug" />
                <el-table-column prop="products_count" :label="$t('number_of_products')" width="120">
                    <template #default="{ row }">
                        <el-tag>{{ row.products_count || 0 }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="is_active" :label="$t('status')" width="100">
                    <template #default="{ row }">
                        <el-switch
                            v-model="row.is_active"
                            @change="toggleStatus(row)"
                        />
                    </template>
                </el-table-column>
                <el-table-column :label="$t('procedures')" width="150" fixed="right">
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
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useCategoriesStore } from '@/stores/categories';
import { Plus, Edit, Delete } from '@element-plus/icons-vue';

const router = useRouter();
const categoriesStore = useCategoriesStore();
const loading = computed(() => categoriesStore.loading);
const categories = computed(() => categoriesStore.categories);

const fetchCategories = async () => {
    try {
        await categoriesStore.fetchCategories();
    } catch (error) {
        ElMessage.error(window.t('failed_to_fetch_categories'));
    }
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
            window.t('delete_category_confirm', { name: category.name_ar }),
            window.t('confirm_deletion'),
            {
                confirmButtonText: window.t('yes'),
                cancelButtonText: window.t('no'),
                type: 'warning'
            }
        );
        
        await categoriesStore.deleteCategory(category.id);
        ElMessage.success(window.t('the_category_has_been_successfully'));
        fetchCategories();
    } catch (error) {
        if (error !== 'cancel') {
            ElMessage.error(window.t('failed_to_delete_category'));
        }
    }
};

const toggleStatus = async (category) => {
    try {
        await categoriesStore.updateCategory(category.id, { is_active: category.is_active });
        ElMessage.success(window.t('status_updated_successfully'));
    } catch (error) {
        ElMessage.error(window.t('failed_to_update_status'));
        category.is_active = !category.is_active;
    }
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
