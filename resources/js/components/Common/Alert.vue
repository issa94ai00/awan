<!-- resources/js/Components/Common/Alert.vue -->
<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    type: {
        type: String,
        default: 'info',
        validator: (value) => ['success', 'error', 'warning', 'info'].includes(value),
    },
    message: {
        type: String,
        required: true,
    },
    duration: {
        type: Number,
        default: 5000,
    },
    position: {
        type: String,
        default: 'top-right',
        validator: (value) => ['top-right', 'top-left', 'bottom-right', 'bottom-left', 'top-center', 'bottom-center'].includes(value),
    },
});

const emit = defineEmits(['close']);

let timeoutId = null;

const typeClasses = {
    success: 'bg-green-500 border-green-600',
    error: 'bg-red-500 border-red-600',
    warning: 'bg-yellow-500 border-yellow-600',
    info: 'bg-blue-500 border-blue-600',
};

const iconClasses = {
    success: '✓',
    error: '✕',
    warning: '⚠',
    info: 'ℹ',
};

const positionClasses = {
    'top-right': 'top-4 right-4',
    'top-left': 'top-4 left-4',
    'bottom-right': 'bottom-4 right-4',
    'bottom-left': 'bottom-4 left-4',
    'top-center': 'top-4 left-1/2 transform -translate-x-1/2',
    'bottom-center': 'bottom-4 left-1/2 transform -translate-x-1/2',
};

onMounted(() => {
    if (props.duration > 0) {
        timeoutId = setTimeout(() => {
            emit('close');
        }, props.duration);
    }
});

onUnmounted(() => {
    if (timeoutId) {
        clearTimeout(timeoutId);
    }
});

function close() {
    emit('close');
}
</script>

<template>
    <Transition
        enter-active-class="transition ease-out duration-300"
        enter-from-class="transform translate-x-full opacity-0"
        enter-to-class="transform translate-x-0 opacity-100"
        leave-active-class="transition ease-in duration-200"
        leave-from-class="transform translate-x-0 opacity-100"
        leave-to-class="transform translate-x-full opacity-0"
    >
        <div 
            v-if="show"
            :class="[
                'fixed z-50 p-4 rounded-lg shadow-lg text-white flex items-center gap-3',
                typeClasses[type],
                positionClasses[position]
            ]"
        >
            <span class="text-xl">{{ iconClasses[type] }}</span>
            <span class="flex-1">{{ message }}</span>
            <button 
                @click="close"
                class="hover:opacity-75 transition-opacity"
            >
                ✕
            </button>
        </div>
    </Transition>
</template>
