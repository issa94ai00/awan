<template>
    <el-card
        shadow="hover"
        class="collapsible-card"
        :class="{ 'is-collapsed': collapsed }"
        :body-style="collapsed ? { padding: '0' } : undefined"
    >
        <template #header>
            <div class="cc-header">
                <!-- The title is the toggle, so the target is the whole label
                     rather than a small chevron at its edge. -->
                <button
                    type="button"
                    class="cc-toggle"
                    :aria-expanded="!collapsed"
                    :aria-controls="bodyId"
                    :title="collapsed ? $t('expand') : $t('collapse')"
                    @click="toggle"
                >
                    <el-icon class="cc-chevron"><ArrowDown /></el-icon>
                    <span class="cc-title"><slot name="title">{{ title }}</slot></span>
                    <!-- Still there when folded: a folded table should say
                         how much it is hiding. -->
                    <span v-if="count !== null" class="cc-count">{{ formatCount(count) }}</span>
                </button>
                <div v-if="$slots.extra" class="cc-extra">
                    <slot name="extra" />
                </div>
            </div>
        </template>

        <el-collapse-transition>
            <div v-show="!collapsed" :id="bodyId">
                <slot />
            </div>
        </el-collapse-transition>
    </el-card>
</template>

<script setup>
/**
 * A report card whose body folds away under its header. The choice is kept
 * per section id (see utils/collapsedSections), so it survives a reload and a
 * tab switch.
 */
import { computed, onMounted, onBeforeUnmount } from 'vue';
import { ArrowDown } from '@element-plus/icons-vue';
import { isCollapsed, setCollapsed, registerSection, unregisterSection } from '@/utils/collapsedSections';

const props = defineProps({
    /** Stable name for the section; the remembered state is keyed by it. */
    id: { type: String, required: true },
    title: { type: String, default: '' },
    /** Row count shown beside the title; null for none. */
    count: { type: Number, default: null },
});

const bodyId = computed(() => `section-${props.id}`);
const collapsed = computed(() => isCollapsed(props.id));

const toggle = () => setCollapsed(props.id, !collapsed.value);

const formatCount = (value) => Number(value || 0).toLocaleString();

onMounted(() => registerSection(props.id));
onBeforeUnmount(() => unregisterSection(props.id));
</script>

<style scoped>
.collapsible-card {
    border-radius: 1rem;
}

.cc-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
}

.cc-toggle {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    flex: 1 1 auto;
    min-width: 0;
    padding: 0;
    border: none;
    background: none;
    font: inherit;
    font-weight: 600;
    color: inherit;
    text-align: start;
    cursor: pointer;
}

.cc-toggle:focus-visible {
    outline: 2px solid var(--el-color-primary);
    outline-offset: 3px;
    border-radius: 4px;
}

.cc-chevron {
    flex: none;
    color: #64748b;
    transition: transform 0.2s ease;
}

/* Pointing along the reading direction when folded, down when open. */
.is-collapsed .cc-chevron {
    transform: rotate(-90deg);
}

:global([dir='rtl']) .is-collapsed .cc-chevron {
    transform: rotate(90deg);
}

.cc-title {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.cc-count {
    flex: none;
    padding: 0 8px;
    border-radius: 10px;
    font-size: 0.78rem;
    font-weight: 600;
    background: #eef2ff;
    color: #4338ca;
}

.cc-extra {
    flex: none;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

/* Folded, the header is the whole card: no rule under it. */
.is-collapsed :deep(.el-card__header) {
    border-bottom: none;
}
</style>
