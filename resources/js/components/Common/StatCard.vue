<!-- resources/js/Components/Common/StatCard.vue -->
<script setup>
const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    value: {
        type: [String, Number],
        required: true,
    },
    icon: {
        type: String,
        default: '',
    },
    color: {
        type: String,
        default: 'blue',
        validator: (value) => ['blue', 'green', 'red', 'yellow', 'purple', 'orange'].includes(value),
    },
    trend: {
        type: String,
        default: '',
    },
});

const borderClasses = {
    blue: 'border-l-4 border-blue-500',
    green: 'border-l-4 border-green-500',
    red: 'border-l-4 border-red-500',
    yellow: 'border-l-4 border-yellow-500',
    purple: 'border-l-4 border-purple-500',
    orange: 'border-l-4 border-orange-500',
};

const trendClasses = {
    up: 'text-green-600',
    down: 'text-red-600',
    neutral: 'text-gray-600',
};
</script>

<template>
    <div 
        class="bg-white p-6 rounded-lg shadow-lg"
        :class="borderClasses[color]"
    >
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-sm font-medium text-gray-600 mb-1">{{ title }}</h3>
                <p class="text-3xl font-bold text-gray-800">{{ value }}</p>
                <p v-if="trend" class="text-xs mt-1" :class="trendClasses[trend]">
                    {{ trend === 'up' ? '↑' : trend === 'down' ? '↓' : '→' }}
                    <slot name="trend"></slot>
                </p>
            </div>
            <div v-if="icon" class="text-3xl">{{ icon }}</div>
        </div>
    </div>
</template>
