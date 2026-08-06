<template>
    <div class="dashboard-skeleton">
        <el-row :gutter="20">
            <el-col :xs="24" :sm="12" :md="24 / cards" v-for="n in cards" :key="n">
                <el-skeleton animated>
                    <template #template>
                        <el-skeleton-item variant="rect" class="skeleton-card" />
                    </template>
                </el-skeleton>
            </el-col>
        </el-row>
        <el-row :gutter="20" class="mt-4">
            <el-col :xs="24" :lg="16">
                <el-skeleton animated :rows="6" />
            </el-col>
            <el-col :xs="24" :lg="8">
                <el-skeleton animated :rows="4" />
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
defineProps({
    cards: {
        type: Number,
        default: 4
    }
});
</script>

<style scoped>
.skeleton-card {
    display: block;
    height: 96px;
    border-radius: 14px;
    margin-bottom: 1rem;
}

.mt-4 {
    margin-top: 1.5rem;
}
</style>
