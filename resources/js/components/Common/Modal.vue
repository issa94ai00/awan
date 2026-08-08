<!-- resources/js/Components/Common/Modal.vue -->
<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { Portal } from 'portal-vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    maxWidth: {
        type: String,
        default: '2xl',
    },
    closeable: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['close']);

const close = () => {
    if (props.closeable) {
        emit('close');
    }
};

// إغلاق عند الضغط على Escape
const closeOnEscape = (e) => {
    if (e.key === 'Escape' && props.show) {
        close();
    }
};

watch(() => props.show, (show) => {
    if (show) {
        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', closeOnEscape);
    } else {
        document.body.style.overflow = '';
        document.removeEventListener('keydown', closeOnEscape);
    }
});

onUnmounted(() => {
    document.body.style.overflow = '';
    document.removeEventListener('keydown', closeOnEscape);
});
</script>

<template>
    <Portal to="modal">
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex min-h-screen items-center justify-center p-4">
                    <!-- الخلفية -->
                    <div 
                        class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"
                        @click="close"
                    ></div>

                    <!-- المحتوى -->
                    <div 
                        class="relative bg-white rounded-lg shadow-xl w-full"
                        :class="{
                            'max-w-sm': maxWidth === 'sm',
                            'max-w-md': maxWidth === 'md',
                            'max-w-lg': maxWidth === 'lg',
                            'max-w-2xl': maxWidth === '2xl',
                            'max-w-3xl': maxWidth === '3xl',
                            'max-w-4xl': maxWidth === '4xl',
                            'max-w-5xl': maxWidth === '5xl',
                            'max-w-6xl': maxWidth === '6xl',
                            'max-w-full': maxWidth === 'full',
                        }[maxWidth] || 'max-w-2xl'"
                    >
                        <slot></slot>
                    </div>
                </div>
            </div>
        </Transition>
    </Portal>
</template>
