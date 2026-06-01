<template>
    <div class="reports-inventory">
        <el-card shadow="hover">
            <template #header>
                <span>تقرير المخزون</span>
            </template>
            <div v-if="loading" style="padding:1rem">جاري التحميل...</div>
            <div v-else>
                <el-row gutter="20">
                    <el-col :span="8">
                        <el-card>
                            <h3>إجمالي المنتجات</h3>
                            <p>{{ overview.products.total }}</p>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card>
                            <h3>المنتجات في المخزن</h3>
                            <p>{{ overview.products.in_stock }}</p>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card>
                            <h3>المنتجات المميزة</h3>
                            <p>{{ overview.products.featured }}</p>
                        </el-card>
                    </el-col>
                </el-row>

                <el-card style="margin-top:1rem">
                    <template #header>
                        <span>أعلى المنتجات من حيث الكمية</span>
                    </template>
                    <el-table :data="overview.top_products" style="width:100%">
                        <el-table-column prop="name_ar" label="الاسم" />
                        <el-table-column prop="stock_quantity" label="الكمية" width="120" />
                        <el-table-column prop="price" label="السعر" width="140" />
                    </el-table>
                </el-card>
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { dashboardApi } from '@/api/dashboard';

const loading = ref(true);
const overview = ref({ products: { total: 0, in_stock: 0, featured: 0 }, top_products: [] });

async function loadOverview() {
    loading.value = true;
    try {
        const res = await dashboardApi.getOverviewStats();
        overview.value = res.data.data || overview.value;
    } catch (e) {
        console.error('Failed to load inventory overview', e);
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    loadOverview();
});
</script>

<style scoped>
.reports-inventory {
    padding: 0;
}
</style>
