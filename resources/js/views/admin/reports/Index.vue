<template>
    <div class="reports-index">
        <el-row gutter="20">
            <el-col :span="12">
                <el-card shadow="hover">
                    <template #header>
                        <span>لوحة تقارير الأعمال</span>
                    </template>
                    <p>اختر تقريرًا للاطلاع على الأرقام الرئيسية واتجاهات الأداء.</p>
                    <router-link :to="{ name: 'admin.reports.sales' }">
                        <el-button type="primary">تقرير المبيعات</el-button>
                    </router-link>
                    <router-link :to="{ name: 'admin.reports.inventory' }">
                        <el-button type="success">تقرير المخزون</el-button>
                    </router-link>
                    <router-link :to="{ name: 'admin.reports.financial' }">
                        <el-button type="warning">التقرير المالي</el-button>
                    </router-link>
                    <router-link :to="{ name: 'admin.reports.payroll' }">
                        <el-button type="info">تقرير الرواتب</el-button>
                    </router-link>
                </el-card>
            </el-col>
            <el-col :span="12">
                <el-card shadow="hover">
                    <template #header>
                        <span>نظرة عامة سريعة</span>
                    </template>
                    <div style="padding: 1rem 0;">
                        <p>• الصفحات: المبيعات، المخزون، المالية، الرواتب</p>
                        <p>• تُستخدم بيانات لوحة القيادة الحالية وموجز الفواتير</p>
                        <p>• يمكنك التكامل مع المزيد من نقاط النهاية لاحقًا</p>
                    </div>
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
</script>

<style scoped>
.reports-index {
    padding: 0;
}
</style>
