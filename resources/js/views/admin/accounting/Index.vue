<template>
    <div class="accounting-index">
        <el-card shadow="hover">
            <template #header>
                <span>المحاسبة</span>
            </template>
            <div v-if="loading" style="padding:1rem">جاري التحميل...</div>
            <div v-else>
                <el-row gutter="20">
                    <el-col :span="8">
                        <el-card>
                            <h3>إجمالي الحسابات</h3>
                            <p>{{ overview.accounts }}</p>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card>
                            <h3>إجمالي قيود اليومية</h3>
                            <p>{{ overview.journal_entries }}</p>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card>
                            <h3>مدفوعات مكتملة</h3>
                            <p>{{ overview.payments_completed }}</p>
                        </el-card>
                    </el-col>
                </el-row>
                <div style="margin-top:1rem">
                    <router-link to="/admin/accounting/journal">
                        <el-button type="primary">الذهاب إلى دفتر اليومية</el-button>
                    </router-link>
                    <router-link to="/admin/accounting/ledger">
                        <el-button type="success">الذهاب إلى دفتر الأستاذ</el-button>
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
const overview = ref({ accounts: 0, journal_entries: 0, payments_completed: 0 });

async function loadOverview() {
    loading.value = true;
    try {
        const res = await dashboardApi.getOverviewStats();
        const data = res.data.data || {};
        overview.value = {
            accounts: data.products?.total ?? 0,
            journal_entries: data.sales_orders?.total ?? 0,
            payments_completed: data.payments?.completed ?? 0,
        };
    } catch (e) {
        console.error('Failed to load accounting overview', e);
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    loadOverview();
});
</script>

<style scoped>
.accounting-index {
    padding: 0;
}
</style>
