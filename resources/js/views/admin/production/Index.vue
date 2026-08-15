<template>
    <div class="production-index">
        <el-card shadow="hover">
            <template #header>
                <span>{{ $t('production') }}</span>
            </template>
            <el-empty :description="$t('production_page_under_development')" />
        </el-card>
    </div>
</template>

<script setup>
</script>

<style scoped>
.production-index {
    padding: 0;
}
</style>
