<!-- resources/js/Components/Common/ResponsiveGrid.vue -->
<script setup>
const props = defineProps({
    cols: {
        type: [Number, Object],
        default: 1,
    },
    gap: {
        type: String,
        default: '4',
    },
});

const gridCols = computed(() => {
    if (typeof props.cols === 'number') {
        return `grid-cols-${props.cols}`;
    }
    
    // إذا كان كائنًا يحتوي على نقاط توقف مختلفة
    let classes = '';
    for (const breakpoint in props.cols) {
        const value = props.cols[breakpoint];
        if (breakpoint === 'default') {
            classes += `grid-cols-${value} `;
        } else {
            classes += `${breakpoint}:grid-cols-${value} `;
        }
    }
    return classes.trim();
});
</script>

<template>
    <div class="grid" :class="[gridCols, `gap-${gap}`]">
        <slot></slot>
    </div>
</template>
