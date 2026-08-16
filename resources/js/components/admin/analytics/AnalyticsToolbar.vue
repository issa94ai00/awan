<template>
    <AdminFilterBar>
        <div class="filter-field">
            <label>{{ $t('analytics.period') }}</label>
            <el-date-picker
                :model-value="modelValue"
                type="daterange"
                unlink-panels
                value-format="YYYY-MM-DD"
                :shortcuts="presets"
                :start-placeholder="$t('date_from')"
                :end-placeholder="$t('date_to')"
                :range-separator="$t('to_me')"
                @update:model-value="$emit('update:modelValue', $event)"
                @change="$emit('apply')"
            />
        </div>

        <slot />

        <template #actions>
            <span v-if="lastUpdatedLabel" class="analytics-toolbar__updated">
                {{ $t('last_updated_at', { time: lastUpdatedLabel }) }}
            </span>

            <el-button :loading="refreshing" @click="$emit('refresh')">
                <el-icon class="mr-1"><Refresh /></el-icon>
                {{ $t('refresh') }}
            </el-button>

            <!--
                Only offered when the screen actually wires an export. The old
                buttons announced "export started" and sent no request at all.
            -->
            <el-button v-if="canExport" type="primary" plain :loading="exporting" @click="$emit('export')">
                <el-icon class="mr-1"><Download /></el-icon>
                {{ $t('export_to_excel') }}
            </el-button>
        </template>
    </AdminFilterBar>
</template>

<script setup>
import AdminFilterBar from '@/components/admin/AdminFilterBar.vue';

/**
 * The period control and actions that open every analytics screen.
 *
 * Each screen previously chose its own filter layout and its own default range,
 * so the same question asked on two pages covered two different windows.
 */
defineProps({
    /** `[fromIso, toIso]`, matching `useAnalyticsPanel().range`. */
    modelValue: { type: Array, default: () => [] },
    presets: { type: Array, default: () => [] },
    refreshing: { type: Boolean, default: false },
    exporting: { type: Boolean, default: false },
    canExport: { type: Boolean, default: false },
    lastUpdatedLabel: { type: String, default: '' },
});

defineEmits(['update:modelValue', 'apply', 'refresh', 'export']);
</script>

<style scoped>
.analytics-toolbar__updated {
    font-size: 0.8rem;
    color: #94a3b8;
    margin-inline-end: auto;
}

.mr-1 {
    margin-inline-end: 0.25rem;
}
</style>
