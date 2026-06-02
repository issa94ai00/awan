<template>
    <div class="hr-page hr-employees">
        <div class="page-header">
            <div class="page-title">
                <h1>الموظفون</h1>
                <p>تصميم جديد لصفحة الموظفين يساعد على عرض البيانات الأساسية والتحديث السريع.</p>
            </div>
        </div>

        <el-row :gutter="16" class="overview-cards">
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>عدد الموظفين</p>
                    <h3>قريباً</h3>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>التقييمات</p>
                    <h3>قريباً</h3>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="8">
                <el-card shadow="hover" class="summary-card">
                    <p>الدوام</p>
                    <h3>قريباً</h3>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="hover" class="content-card">
            <template #header>
                <span>حالة الموارد البشرية</span>
            </template>
            <div class="empty-state">
                <p>هذه الصفحة في مرحلة البناء لتوفير واجهة متكاملة لإدارة بيانات الموظفين.</p>
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
