<!-- resources/js/Components/Common/ErrorHandler.vue -->
<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    error: {
        type: [Error, Object, String],
        default: null,
    },
});

const emit = defineEmits(['close', 'retry']);

const errorMessage = computed(() => {
    if (!props.error) return '';
    
    if (typeof props.error === 'string') {
        return props.error;
    }
    
    if (props.error instanceof Error) {
        return props.error.message;
    }
    
    if (props.error.response) {
        // خطأ من API
        if (props.error.response.data?.message) {
            return props.error.response.data.message;
        }
        if (props.error.response.data?.error) {
            return props.error.response.data.error;
        }
        return `خطأ ${props.error.response.status}: ${props.error.response.statusText}`;
    }
    
    if (props.error.message) {
        return props.error.message;
    }
    
    return 'حدث خطأ غير متوقع';
});

const errorDetails = computed(() => {
    if (!props.error) return null;
    
    if (props.error.response?.data?.errors) {
        return props.error.response.data.errors;
    }
    
    if (props.error.errors) {
        return props.error.errors;
    }
    
    return null;
});

function close() {
    emit('close');
}

function retry() {
    emit('retry');
}
</script>

<template>
    <Transition
        enter-active-class="transition ease-out duration-300"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition ease-in duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black bg-opacity-50" @click="close"></div>
            
            <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                        <span class="text-red-600 text-xl">✕</span>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800">خطأ</h3>
                </div>
                
                <p class="text-gray-700 mb-4">{{ errorMessage }}</p>
                
                <div v-if="errorDetails" class="mb-4">
                    <h4 class="text-sm font-medium text-gray-600 mb-2">التفاصيل:</h4>
                    <ul class="text-sm text-red-600 space-y-1">
                        <li v-for="(detail, key) in errorDetails" :key="key">
                            {{ Array.isArray(detail) ? detail[0] : detail }}
                        </li>
                    </ul>
                </div>
                
                <div class="flex justify-end gap-3">
                    <button
                        @click="close"
                        class="px-4 py-2 text-gray-700 hover:text-gray-900 transition-colors"
                    >
                        إغلاق
                    </button>
                    <button
                        @click="retry"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                    >
                        إعادة المحاولة
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>
