<template>
    <el-card
        ref="cardRef"
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
 *
 * It also says when its body is actually in front of someone — scrolled near
 * the viewport, on the visible tab, and not folded — through `active-change`.
 * A section whose data is expensive loads on that instead of with the page,
 * so a table nobody scrolls to, or keeps folded, never costs a request.
 */
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { ArrowDown } from '@element-plus/icons-vue';
import { isCollapsed, setCollapsed, registerSection, unregisterSection } from '@/utils/collapsedSections';

const props = defineProps({
    /** Stable name for the section; the remembered state is keyed by it. */
    id: { type: String, required: true },
    title: { type: String, default: '' },
    /** Row count shown beside the title; null for none. */
    count: { type: Number, default: null },
});

const emit = defineEmits(['active-change']);

const bodyId = computed(() => `section-${props.id}`);
const collapsed = computed(() => isCollapsed(props.id));

const toggle = () => setCollapsed(props.id, !collapsed.value);

const formatCount = (value) => Number(value || 0).toLocaleString();

const cardRef = ref(null);
const inView = ref(false);
let observer = null;

// A pane hidden by its tab measures nothing and never intersects, so a card on
// the other tab reads as out of view without the parent having to say so.
const active = computed(() => inView.value && !collapsed.value);
watch(active, (value) => emit('active-change', value));

onMounted(() => {
    registerSection(props.id);

    const element = cardRef.value?.$el;
    if (!element || typeof IntersectionObserver === 'undefined') {
        inView.value = true;
        return;
    }
    // Starts a little before the card scrolls in, so its rows are usually
    // there by the time it is.
    observer = new IntersectionObserver(
        ([entry]) => { inView.value = entry.isIntersecting; },
        { rootMargin: '300px 0px' },
    );
    observer.observe(element);
});

onBeforeUnmount(() => {
    unregisterSection(props.id);
    observer?.disconnect();
});
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

/* A plain descendant selector, scoped only at the chevron. Written as
   :global([dir='rtl']) … it compiled to a bare [dir='rtl'] rule and turned
   the whole page on its side. */
[dir='rtl'] .is-collapsed .cc-chevron {
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
