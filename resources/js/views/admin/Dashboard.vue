<template>
    <div class="dashboard">
        <el-row :gutter="20">
            <el-col :xs="24" :sm="12" :md="6" v-for="stat in stats" :key="stat.title">
                <el-card class="stat-card" shadow="hover">
                    <div class="stat-content">
                        <div class="stat-icon" :style="{ background: stat.color }">
                            <el-icon :size="28" color="white">
                                <component :is="stat.icon" />
                            </el-icon>
                        </div>
                        <div class="stat-info">
                            <h3>{{ stat.value }}</h3>
                            <p>{{ stat.title }}</p>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-row :gutter="20" class="mt-4">
            <el-col :xs="24" :lg="16">
                <el-card shadow="hover">
                    <template #header>
                        <div class="card-header">
                            <span>المبيعات الأخيرة</span>
                            <el-button type="primary" size="small">عرض الكل</el-button>
                        </div>
                    </template>
                    <el-table :data="recentSales" style="width: 100%">
                        <el-table-column prop="id" label="رقم الطلب" width="100" />
                        <el-table-column prop="customer" label="العميل" />
                        <el-table-column prop="amount" label="المبلغ" />
                        <el-table-column prop="status" label="الحالة">
                            <template #default="{ row }">
                                <el-tag :type="getStatusType(row.status)">
                                    {{ row.status }}
                                </el-tag>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-col>

            <el-col :xs="24" :lg="8">
                <el-card shadow="hover">
                    <template #header>
                        <span>المنتجات الأكثر مبيعاً</span>
                    </template>
                    <div class="top-products">
                        <div v-for="product in topProducts" :key="product.id" class="product-item">
                            <el-avatar :size="40" :src="product.image" />
                            <div class="product-info">
                                <h4>{{ product.name }}</h4>
                                <span>{{ product.sales }} مبيعات</span>
                            </div>
                            <span class="product-price">{{ product.price }}</span>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import {
    Box, ShoppingCart, User, TrendCharts
} from '@element-plus/icons-vue';

const stats = ref([
    { title: 'إجمالي المنتجات', value: '1,234', icon: Box, color: '#409eff' },
    { title: 'المبيعات', value: '45,678', icon: ShoppingCart, color: '#67c23a' },
    { title: 'العملاء', value: '892', icon: User, color: '#e6a23c' },
    { title: 'الإيرادات', value: '123,456', icon: TrendCharts, color: '#f56c6c' }
]);

const recentSales = ref([
    { id: '#1001', customer: 'أحمد محمد', amount: '1,500 ر.س', status: 'مكتمل' },
    { id: '#1002', customer: 'فاطمة علي', amount: '2,300 ر.س', status: 'قيد المعالجة' },
    { id: '#1003', customer: 'محمد خالد', amount: '950 ر.س', status: 'مكتمل' },
    { id: '#1004', customer: 'سارة أحمد', amount: '1,800 ر.س', status: 'ملغي' }
]);

const topProducts = ref([
    { id: 1, name: 'منتج أ', sales: 156, price: '150 ر.س', image: '' },
    { id: 2, name: 'منتج ب', sales: 142, price: '200 ر.س', image: '' },
    { id: 3, name: 'منتج ج', sales: 128, price: '180 ر.س', image: '' },
    { id: 4, name: 'منتج د', sales: 98, price: '250 ر.س', image: '' }
]);

const getStatusType = (status) => {
    const types = {
        'مكتمل': 'success',
        'قيد المعالجة': 'warning',
        'ملغي': 'danger'
    };
    return types[status] || 'info';
};

onMounted(() => {
    // Fetch dashboard data from API
});
</script>

<style scoped>
.dashboard {
    padding: 0;
}

.stat-card {
    margin-bottom: 1.5rem;
    border: none;
    border-radius: 12px;
}

.stat-content {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.stat-info h3 {
    margin: 0;
    font-size: 1.75rem;
    font-weight: 700;
    color: #333;
}

.stat-info p {
    margin: 0;
    color: #666;
    font-size: 0.875rem;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.mt-4 {
    margin-top: 1.5rem;
}

.top-products {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.product-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem;
    border-radius: 8px;
    transition: background 0.3s ease;
}

.product-item:hover {
    background: #f5f7fa;
}

.product-info {
    flex: 1;
}

.product-info h4 {
    margin: 0 0 0.25rem 0;
    font-size: 0.9375rem;
    color: #333;
}

.product-info span {
    font-size: 0.8125rem;
    color: #666;
}

.product-price {
    font-weight: 600;
    color: #409eff;
}
</style>
