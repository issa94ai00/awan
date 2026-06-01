<template>
    <div class="products-index">
        <el-card shadow="hover">
            <template #header>
                <div class="card-header">
                    <span>إدارة المنتجات</span>
                    <el-button type="primary" :icon="Plus" @click="goToCreate">
                        إضافة منتج
                    </el-button>
                </div>
            </template>

            <div class="filters">
                <el-row :gutter="20">
                    <el-col :xs="24" :sm="12" :md="6">
                        <el-input
                            v-model="searchQuery"
                            placeholder="بحث..."
                            :prefix-icon="Search"
                            clearable
                        />
                    </el-col>
                    <el-col :xs="24" :sm="12" :md="6">
                        <el-select v-model="selectedCategory" placeholder="الفئة" clearable>
                            <el-option
                                v-for="cat in categories"
                                :key="cat.id"
                                :label="cat.name"
                                :value="cat.id"
                            />
                        </el-select>
                    </el-col>
                    <el-col :xs="24" :sm="12" :md="6">
                        <el-select v-model="selectedStatus" placeholder="الحالة" clearable>
                            <el-option label="نشط" value="active" />
                            <el-option label="غير نشط" value="inactive" />
                        </el-select>
                    </el-col>
                    <el-col :xs="24" :sm="12" :md="6">
                        <el-button :icon="Refresh" @click="fetchProducts">
                            تحديث
                        </el-button>
                    </el-col>
                </el-row>
            </div>

            <el-table
                v-loading="loading"
                :data="filteredProducts"
                style="width: 100%"
                stripe
            >
                <el-table-column label="صورة" width="85">
                    <template #default="{ row }">
                        <el-image
                            :src="row.image_main || '/placeholder.jpg'"
                            :preview-src-list="[row.image_main]"
                            fit="cover"
                            style="width: 75px; height: 75px; border-radius: 8px;"
                        />
                    </template>
                </el-table-column>
                <el-table-column prop="name_ar" label="المنتج" />
                <el-table-column label="الفئة">
                    <template #default="{ row }">
                        <el-tag type="info">{{ row.category?.name_ar || 'بدون فئة' }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="price" label="السعر" width="100">
                    <template #default="{ row }">
                        <span class="price-tag">{{ row.price }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="stock_quantity" label="المخزون" width="100">
                    <template #default="{ row }">
                        <el-tag :type="getStockType(row.stock_quantity)">
                            {{ row.stock_quantity }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="الحالة" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.is_active ? 'success' : 'danger'">
                            {{ row.is_active ? 'نشط' : 'غير نشط' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="مميز" width="80">
                    <template #default="{ row }">
                        <el-icon v-if="row.is_featured" color="#f6ad55" :size="20">
                            <Star />
                        </el-icon>
                    </template>
                </el-table-column>
                <el-table-column label="الإجراءات" width="150" fixed="right">
                    <template #default="{ row }">
                        <el-button-group>
                            <el-button :icon="View" size="small" @click="viewProduct(row)" />
                            <el-button :icon="Edit" size="small" @click="editProduct(row)" />
                            <el-button :icon="Delete" size="small" type="danger" @click="deleteProduct(row)" />
                        </el-button-group>
                    </template>
                </el-table-column>
            </el-table>

            <el-pagination
                v-model:current-page="currentPage"
                v-model:page-size="pageSize"
                :total="total"
                :page-sizes="[10, 20, 50, 100]"
                layout="total, sizes, prev, pager, next, jumper"
                @size-change="fetchProducts"
                @current-change="fetchProducts"
                class="mt-4"
            />
        </el-card>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useProductsStore } from '@/stores/products';
import {
    Plus, Search, Refresh, View, Edit, Delete, Star
} from '@element-plus/icons-vue';

const router = useRouter();
const productsStore = useProductsStore();
const searchQuery = ref('');
const selectedCategory = ref('');
const selectedStatus = ref('');
const currentPage = ref(1);
const pageSize = ref(10);

const products = computed(() => productsStore.products);
const loading = computed(() => productsStore.loading);
const total = computed(() => productsStore.pagination.total);

const categories = ref([
    { id: 1, name: 'إلكترونيات' },
    { id: 2, name: 'ملابس' },
    { id: 3, name: 'أثاث' }
]);

const filteredProducts = computed(() => {
    return products.value.filter(product => {
        const name = product.name_ar || product.name_en || '';
        const matchSearch = name.toLowerCase().includes(searchQuery.value.toLowerCase());
        const categoryName = product.category?.name_ar || product.category?.name_en || '';
        const matchCategory = !selectedCategory.value || categoryName === categories.value.find(c => c.id === selectedCategory.value)?.name;
        const statusValue = product.is_active ? 'active' : 'inactive';
        const matchStatus = !selectedStatus.value || statusValue === selectedStatus.value;
        return matchSearch && matchCategory && matchStatus;
    });
});

const getStockType = (stock) => {
    if (stock === 0) return 'danger';
    if (stock <= 10) return 'warning';
    return 'success';
};

const fetchProducts = async () => {
    try {
        await productsStore.fetchProducts({
            page: currentPage.value,
            per_page: pageSize.value
        });
    } catch (error) {
        ElMessage.error('فشل في جلب المنتجات');
    }
};

const goToCreate = () => {
    router.push('/admin/products/create');
};

const viewProduct = (product) => {
    router.push({ name: 'admin.products.show', params: { id: product.id } });
};

const editProduct = (product) => {
    router.push({ name: 'admin.products.edit', params: { id: product.id } });
};

const deleteProduct = async (product) => {
    try {
        await ElMessageBox.confirm(
            `هل أنت متأكد من حذف المنتج "${product.name}"؟`,
            'تأكيد الحذف',
            {
                confirmButtonText: 'نعم',
                cancelButtonText: 'لا',
                type: 'warning'
            }
        );
        
        await productsStore.deleteProduct(product.id);
        ElMessage.success('تم حذف المنتج بنجاح');
        fetchProducts();
    } catch (error) {
        if (error !== 'cancel') {
            ElMessage.error('فشل في حذف المنتج');
        }
    }
};

onMounted(() => {
    fetchProducts();
});
</script>

<style scoped>
.products-index {
    padding: 0;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.filters {
    margin-bottom: 1.5rem;
}

.price-tag {
    font-weight: 600;
    color: #409eff;
}

.mt-4 {
    margin-top: 1.5rem;
}
</style>
