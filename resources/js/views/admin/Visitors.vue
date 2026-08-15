<template>
    <div class="visitors-index">
        <el-card shadow="hover">
            <template #header>
                <span>{{ $t('visitors') }}</span>
            </template>
            <el-empty :description="$t('visitors_page_under_development')" />
        </el-card>
    </div>
</template>

<script setup>
</script>

<style scoped>
.visitors-index {
    padding: 0;
}
</style>
