<!-- resources/js/Components/Common/Card.vue -->
<script setup>
const props = defineProps({
    title: {
        type: String,
        default: '',
    },
    icon: {
        type: String,
        default: '',
    },
    borderColor: {
        type: String,
        default: '',
        validator: (value) => ['', 'blue', 'green', 'red', 'yellow', 'purple', 'orange'].includes(value),
    },
    padding: {
        type: String,
        default: 'p-6',
    },
});

const borderClasses = {
    '': '',
    blue: 'border-l-4 border-blue-500',
    green: 'border-l-4 border-green-500',
    red: 'border-l-4 border-red-500',
    yellow: 'border-l-4 border-yellow-500',
    purple: 'border-l-4 border-purple-500',
    orange: 'border-l-4 border-orange-500',
};
</script>

<template>
    <div 
        class="bg-white rounded-lg shadow-lg"
        :class="[borderClasses[borderColor], padding]"
    >
        <div v-if="title || icon" class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-800">{{ title }}</h3>
            <div v-if="icon" class="text-3xl">{{ icon }}</div>
        </div>
        <slot></slot>
    </div>
</template>
