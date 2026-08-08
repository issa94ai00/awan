<!-- resources/js/Components/Common/ConfirmDialog.vue -->
<script setup>
import { ref } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: 'تأكيد',
    },
    message: {
        type: String,
        required: true,
    },
    confirmText: {
        type: String,
        default: 'تأكيد',
    },
    cancelText: {
        type: String,
        default: 'إلغاء',
    },
    type: {
        type: String,
        default: 'danger',
        validator: (value) => ['danger', 'warning', 'info'].includes(value),
    },
});

const emit = defineEmits(['confirm', 'cancel']);

function confirm() {
    emit('confirm');
}

function cancel() {
    emit('cancel');
}

const typeClasses = {
    danger: 'bg-red-600 hover:bg-red-700',
    warning: 'bg-yellow-600 hover:bg-yellow-700',
    info: 'bg-blue-600 hover:bg-blue-700',
};

const iconClasses = {
    danger: 'text-red-600',
    warning: 'text-yellow-600',
    info: 'text-blue-600',
};

const icons = {
    danger: '⚠',
    warning: '⚠',
    info: 'ℹ',
};
</script>

<template>
    <Transition
        enter-active-class="transition ease-out duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div 
            v-if="show"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
        >
            <!-- الخلفية -->
            <div 
                class="fixed inset-0 bg-black bg-opacity-50"
                @click="cancel"
            ></div>

            <!-- المحتوى -->
            <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                <div class="flex items-start gap-4">
                    <div :class="iconClasses[type]" class="text-4xl">
                        {{ icons[type] }}
                    </div>
                    <div class="flex-1">
                        <h3 class="text-lg font-bold mb-2">{{ title }}</h3>
                        <p class="text-gray-600">{{ message }}</p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-4">
                    <button 
                        @click="cancel"
                        class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400"
                    >
                        {{ cancelText }}
                    </button>
                    <button 
                        @click="confirm"
                        :class="typeClasses[type]"
                        class="text-white px-4 py-2 rounded-lg"
                    >
                        {{ confirmText }}
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>
