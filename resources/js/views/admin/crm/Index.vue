<template>
    <div class="crm-index">
        <div class="hero-panel">
            <div class="hero-copy">
                <p class="eyebrow">CRM</p>
                <h1>إدارة علاقات العملاء</h1>
                <p>لوحة تحكم حديثة لإدارة العملاء، التذاكر والاتصالات في مكان واحد.</p>
            </div>
            <div class="hero-actions">
                <el-button type="primary" @click="refreshData">تحديث البيانات</el-button>
            </div>
        </div>

        <el-row :gutter="20" class="overview-cards">
            <el-col :xs="24" :sm="12" :md="6" v-for="item in stats" :key="item.title">
                <el-card shadow="hover" class="overview-card">
                    <div class="card-meta">
                        <span>{{ item.title }}</span>
                    </div>
                    <h2>{{ item.value }}</h2>
                </el-card>
            </el-col>
        </el-row>

        <el-row :gutter="20" class="mt-4">
            <el-col :xs="24" :lg="16">
                <el-card shadow="hover" class="activity-card">
                    <template #header>
                        <div class="card-header">
                            <span>أحدث العملاء</span>
                        </div>
                    </template>
                    <el-table :data="customersStore.customers.slice(0,6)" style="width: 100%" stripe>
                        <el-table-column prop="name" label="الاسم" />
                        <el-table-column prop="company" label="الشركة" />
                        <el-table-column prop="email" label="البريد" />
                        <el-table-column prop="phone" label="الهاتف" />
                    </el-table>
                    <div v-if="!customersStore.customers.length" class="empty-state">لا يوجد عملاء بعد.</div>
                </el-card>
            </el-col>

            <el-col :xs="24" :lg="8">
                <el-card shadow="hover" class="insight-card">
                    <template #header>
                        <span>ملخص سريع</span>
                    </template>
                    <div class="insight-list">
                        <div class="insight-item">
                            <span>العملاء</span>
                            <strong>{{ customersStore.customers.length }}</strong>
                        </div>
                        <div class="insight-item">
                            <span>التذاكر</span>
                            <strong>قريباً</strong>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { useCustomersStore } from '@/stores/customers';

const customersStore = useCustomersStore();

const stats = computed(() => [
    { title: 'إجمالي العملاء', value: customersStore.customers.length },
    { title: 'التذاكر', value: 'قريباً' },
    { title: 'مكالمات', value: 'قريباً' },
    { title: 'نشاط اليوم', value: 'قريباً' }
]);

const refreshData = async () => {
    await customersStore.fetchCustomers().catch(() => {});
};

onMounted(refreshData);
</script>

<style scoped>
.crm-index { padding: 0; }
.hero-panel { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:flex-start; gap:1.5rem; padding:1.5rem; border-radius:12px; background:#f5f8ff; margin-bottom:1.5rem }
.eyebrow { margin:0 0 0.5rem; color:#409eff; font-weight:700 }
.hero-copy h1 { margin:0; font-size:1.9rem; color:#1f2d3d }
.hero-copy p { margin-top:0.5rem; color:#5f6d85 }
.overview-card { min-height:120px; display:flex; flex-direction:column; justify-content:center; border-radius:12px }
.insight-item { display:flex; justify-content:space-between; padding:0.8rem; border-radius:8px; background:#f8fbff }
.mt-4 { margin-top:1.5rem }
.empty-state { padding:1rem; text-align:center; color:#6b7c98 }
</style>
