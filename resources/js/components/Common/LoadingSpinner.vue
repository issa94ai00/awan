<!-- resources/js/Components/Common/LoadingSpinner.vue -->
<script setup>
const props = defineProps({
    size: {
        type: String,
        default: 'md',
        validator: (value) => ['sm', 'md', 'lg', 'xl'].includes(value),
    },
    color: {
        type: String,
        default: 'blue',
        validator: (value) => ['blue', 'green', 'red', 'yellow', 'gray'].includes(value),
    },
});

const sizeClasses = {
    sm: 'w-4 h-4',
    md: 'w-8 h-8',
    lg: 'w-12 h-12',
    xl: 'w-16 h-16',
};

const colorClasses = {
    blue: 'border-blue-500 border-t-transparent',
    green: 'border-green-500 border-t-transparent',
    red: 'border-red-500 border-t-transparent',
    yellow: 'border-yellow-500 border-t-transparent',
    gray: 'border-gray-500 border-t-transparent',
};
</script>

<template>
    <div 
        class="rounded-full border-4 animate-spin"
        :class="[sizeClasses[size], colorClasses[color]]"
    ></div>
</template>
