<template>
    <div class="hr-page hr-attendance">
        <div class="page-header">
            <div class="page-title">
                <h1>الحضور والانصراف</h1>
                <p>تصميم حديث لصفحة الحضور، مع نظرة سريعة على الحالة اليومية للموظفين.</p>
            </div>
        </div>

        <el-row :gutter="16" class="overview-cards">
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>الحضور اليوم</p>
                    <h3>قريباً</h3>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>الموظفون المتأخرون</p>
                    <h3>قريباً</h3>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>تسجيلات اليوم</p>
                    <h3>قريباً</h3>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="hover" class="content-card">
            <template #header>
                <span>الوضع الحالي</span>
            </template>
            <div class="empty-state">
                <p>هذه الصفحة قيد التطوير حاليًا لتوفير عرض أكثر تفاعلية لإدارة الحضور والانصراف.</p>
            </div>
        </el-card>
    </div>
</template>

<script setup>
</script>

<style scoped>
.hr-page {
    padding: 0;
}

.page-header {
    margin-bottom: 1.5rem;
}

.page-title h1 {
    margin: 0;
    font-size: 1.8rem;
    font-weight: 700;
    color: #1f2d3d;
}

.page-title p {
    margin: 0.35rem 0 0;
    color: #5f6d85;
}

.overview-cards {
    margin-bottom: 1.5rem;
}

.summary-card {
    min-height: 110px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 0.4rem;
    border-radius: 1rem;
}

.summary-card p {
    margin: 0;
    color: #6b7c98;
    font-size: 0.95rem;
}

.summary-card h3 {
    margin: 0;
    font-size: 2rem;
    color: #253358;
}

.content-card {
    border-radius: 1rem;
}

.empty-state {
    padding: 1.5rem;
    color: #58657e;
}
</style>
