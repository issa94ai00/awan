<template>
    <div class="reports-sales">
        <el-card shadow="hover">
            <template #header>
                <span>تقرير المبيعات</span>
            </template>
            <div v-if="loading" style="padding:1rem">جاري التحميل...</div>
            <div v-else>
                <el-row gutter="20">
                    <el-col :span="8" v-for="(value, key) in summary.revenue" :key="key">
                        <el-card>
                            <h3>{{ formatLabel(key) }}</h3>
                            <p>{{ formatCurrency(value) }}</p>
                        </el-card>
                    </el-col>
                </el-row>
                <el-row gutter="20" style="margin-top:1rem">
                    <el-col :span="8" v-for="(value, key) in summary.count" :key="key">
                        <el-card>
                            <h3>{{ formatLabel(key) }}</h3>
                            <p>{{ value }}</p>
                        </el-card>
                    </el-col>
                </el-row>
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { invoicesApi } from '@/api/invoices';

const loading = ref(true);
const summary = ref({ revenue: {}, count: {} });

function formatLabel(key) {
    return {
        today: 'اليوم',
        week: 'هذا الأسبوع',
        month: 'هذا الشهر',
        total: 'إجمالي',
    }[key] || key;
}

function formatCurrency(value) {
    return new Intl.NumberFormat('ar-EG', { style: 'currency', currency: 'EGP' }).format(value);
}

async function loadSummary() {
    loading.value = true;
    try {
        const res = await invoicesApi.summary();
        summary.value = res.data.data || {};
    } catch (e) {
        console.error('Failed to load sales summary', e);
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    loadSummary();
});
</script>

<style scoped>
.reports-sales {
    padding: 0;
}
</style>
