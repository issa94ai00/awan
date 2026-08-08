<!-- resources/js/Components/Common/ResponsiveContainer.vue -->
<script setup>
const props = defineProps({
    breakpoint: {
        type: String,
        default: 'md',
        validator: (value) => ['sm', 'md', 'lg', 'xl', '2xl'].includes(value),
    },
});

const breakpointClasses = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
    xl: 'max-w-xl',
    '2xl': 'max-w-2xl',
};
</script>

<template>
    <div class="mx-auto px-4 sm:px-6 lg:px-8" :class="breakpointClasses[breakpoint]">
        <slot></slot>
    </div>
</template>
