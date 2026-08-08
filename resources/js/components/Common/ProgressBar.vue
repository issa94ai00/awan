<!-- resources/js/Components/Common/ProgressBar.vue -->
<script setup>
const props = defineProps({
    value: {
        type: Number,
        default: 0,
    },
    max: {
        type: Number,
        default: 100,
    },
    color: {
        type: String,
        default: 'blue',
        validator: (value) => ['blue', 'green', 'red', 'yellow', 'purple', 'orange'].includes(value),
    },
    showPercentage: {
        type: Boolean,
        default: true,
    },
    height: {
        type: String,
        default: 'h-2',
    },
});

const percentage = computed(() => {
    if (props.max === 0) return 0;
    return Math.min((props.value / props.max) * 100, 100);
});

const colorClasses = {
    blue: 'bg-blue-500',
    green: 'bg-green-500',
    red: 'bg-red-500',
    yellow: 'bg-yellow-500',
    purple: 'bg-purple-500',
    orange: 'bg-orange-500',
};
</script>

<template>
    <div class="w-full">
        <div v-if="showPercentage" class="flex justify-between mb-1">
            <span class="text-xs font-medium text-gray-700">
                <slot name="label"></slot>
            </span>
            <span class="text-xs font-medium text-gray-700">
                {{ Math.round(percentage) }}%
            </span>
        </div>
        <div class="w-full bg-gray-200 rounded-full overflow-hidden" :class="height">
            <div 
                class="transition-all duration-500 ease-out"
                :class="[colorClasses[color], height]"
                :style="{ width: percentage + '%' }"
            ></div>
        </div>
    </div>
</template>
