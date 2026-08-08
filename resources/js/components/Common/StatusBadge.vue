<!-- resources/js/Components/Common/StatusBadge.vue -->
<script setup>
const props = defineProps({
    status: {
        type: String,
        required: true,
    },
    type: {
        type: String,
        default: 'default',
        validator: (value) => ['default', 'success', 'warning', 'error', 'info'].includes(value),
    },
});

const statusConfig = {
    // Product status
    active: { type: 'success', label: 'نشط', icon: '✓' },
    inactive: { type: 'default', label: 'غير نشط', icon: '○' },
    featured: { type: 'info', label: 'مميز', icon: '★' },
    out_of_stock: { type: 'error', label: 'نفذ', icon: '✕' },
    low_stock: { type: 'warning', label: 'منخفض', icon: '⚠' },
    
    // Warehouse status
    primary: { type: 'success', label: 'رئيسي', icon: '★' },
    secondary: { type: 'info', label: 'ثانوي', icon: '○' },
    
    // Assignment status
    linked: { type: 'success', label: 'مرتبط', icon: '✓' },
    unlinked: { type: 'default', label: 'غير مرتبط', icon: '○' },
    pending: { type: 'warning', label: 'معلق', icon: '⏳' },
    
    // Movement status
    inbound: { type: 'success', label: 'إيداع', icon: '↓' },
    outbound: { type: 'error', label: 'صرف', icon: '↑' },
    adjustment: { type: 'warning', label: 'تسوية', icon: '≈' },
    transfer: { type: 'info', label: 'تحويل', icon: '↔' },
};

const config = computed(() => {
    return statusConfig[props.status] || {
        type: props.type,
        label: props.status,
        icon: '○',
    };
});

const typeClasses = {
    success: 'bg-green-100 text-green-800 border-green-200',
    warning: 'bg-yellow-100 text-yellow-800 border-yellow-200',
    error: 'bg-red-100 text-red-800 border-red-200',
    info: 'bg-blue-100 text-blue-800 border-blue-200',
    default: 'bg-gray-100 text-gray-800 border-gray-200',
};
</script>

<template>
    <span 
        class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium border"
        :class="typeClasses[config.type]"
    >
        <span>{{ config.icon }}</span>
        <span>{{ config.label }}</span>
    </span>
</template>
