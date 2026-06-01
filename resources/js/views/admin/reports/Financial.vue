<template>
    <div class="reports-financial">
        <el-card shadow="hover">
            <template #header>
                <span>التقرير المالي</span>
            </template>
            <div v-if="loading" style="padding:1rem">جاري التحميل...</div>
            <div v-else>
                <el-row gutter="20">
                    <el-col :span="8">
                        <el-card>
                            <h3>إجمالي المدفوعات</h3>
                            <p>{{ summary.payments.amounts.completed }}</p>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card>
                            <h3>مدفوعات معلقة</h3>
                            <p>{{ summary.payments.amounts.pending }}</p>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card>
                            <h3>إجمالي الفواتير</h3>
                            <p>{{ summary.invoices.total }}</p>
                        </el-card>
                    </el-col>
                </el-row>
                <el-card style="margin-top:1rem">
                    <template #header>
                        <span>الحالة الحالية</span>
                    </template>
                    <el-row gutter="20">
                        <el-col :span="6" v-for="(value, key) in statusCards" :key="key">
                            <el-card>
                                <h4>{{ key }}</h4>
                                <p>{{ value }}</p>
                            </el-card>
                        </el-col>
                    </el-row>
                </el-card>
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { dashboardApi } from '@/api/dashboard';

const loading = ref(true);
const summary = ref({ invoices: {}, payments: {} });

const statusCards = computed(() => ({
    مدفوعة: summary.value.invoices.paid || 0,
    معلقة: summary.value.invoices.pending || 0,
    ملغاة: summary.value.invoices.cancelled || 0,
    مدفوعة_جزئيًا: summary.value.payments.pending || 0,
}));

async function loadSummary() {
    loading.value = true;
    try {
        const res = await dashboardApi.getOverviewStats();
        summary.value = res.data.data || summary.value;
    } catch (e) {
        console.error('Failed to load financial summary', e);
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    loadSummary();
});
</script>

<style scoped>
.reports-financial {
    padding: 0;
}
</style>
