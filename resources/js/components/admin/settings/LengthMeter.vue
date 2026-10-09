<template>
    <div class="length-meter" :class="state">
        <span class="bar"><span :style="{ width: percent + '%' }"></span></span>
        <small>{{ length }} / {{ ideal }} · {{ label }}</small>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

// Search engines cut titles and descriptions off around these lengths; this is
// guidance, not a limit, so nothing is truncated.
const props = defineProps({
    value: { type: String, default: '' },
    ideal: { type: Number, required: true },
});

const { t } = useI18n();
const length = computed(() => (props.value || '').trim().length);
const percent = computed(() => Math.min(100, Math.round((length.value / props.ideal) * 100)));
const state = computed(() => {
    if (length.value === 0) return 'empty';
    if (length.value > props.ideal) return 'long';
    if (length.value < props.ideal * 0.5) return 'short';
    return 'good';
});
const label = computed(() => ({
    empty: t('settings_len_empty'),
    short: t('settings_len_short'),
    good: t('settings_len_good'),
    long: t('settings_len_long'),
}[state.value]));
</script>

<style scoped>
.length-meter {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    width: 100%;
    margin-top: 0.35rem;
}

.bar {
    flex: 1;
    height: 4px;
    border-radius: 999px;
    background: #eef2f7;
    overflow: hidden;
}

.bar span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: #94a3b8;
    transition: width 0.2s ease, background 0.2s ease;
}

small {
    flex-shrink: 0;
    font-size: 0.72rem;
    color: #64748b;
    font-variant-numeric: tabular-nums;
}

.good .bar span { background: #10b981; }
.good small { color: #047857; }
.short .bar span { background: #f59e0b; }
.long .bar span { background: #ef4444; }
.long small { color: #b91c1c; }
</style>
