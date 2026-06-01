<template>
    <div class="inventory-index">
        <el-card shadow="hover">
            <template #header>
                <span>المخزون</span>
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
                            <h3>المنتجات في المخزون</h3>
                            <p>{{ overview.products.in_stock }}</p>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card>
                            <h3>حركات المخزون</h3>
                            <p>{{ overview.purchase_receipts.total }}</p>
                        </el-card>
                    </el-col>
                </el-row>
                <div style="margin-top:1rem">
                    <router-link to="/admin/inventory/movements">
                        <el-button type="primary">عرض الحركات</el-button>
                    </router-link>
                </div>
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { dashboardApi } from '@/api/dashboard';

const loading = ref(true);
const overview = ref({ products: { total: 0, in_stock: 0 }, purchase_receipts: { total: 0 } });

async function loadOverview() {
    loading.value = true;
    try {
        const res = await dashboardApi.getOverviewStats();
        const data = res.data.data || {};
        overview.value = {
            products: {
                total: data.products?.total ?? 0,
                in_stock: data.products?.in_stock ?? 0,
            },
            purchase_receipts: {
                total: data.purchase_receipts?.total ?? 0,
            }
        };
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
.inventory-index {
    padding: 0;
}
</style>
