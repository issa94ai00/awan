<!-- resources/js/Components/Common/FormValidator.vue -->
<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
    rules: {
        type: Object,
        default: () => ({}),
    },
    data: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['validate']);

const errors = ref({});
const isValid = computed(() => Object.keys(errors.value).length === 0);

// قواعد التحقق المدمجة
const validators = {
    required: (value) => {
        if (value === null || value === undefined || value === '') {
            return 'هذا الحقل مطلوب';
        }
        return null;
    },
    email: (value) => {
        if (value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
            return 'يرجى إدخال بريد إلكتروني صحيح';
        }
        return null;
    },
    numeric: (value) => {
        if (value && isNaN(Number(value))) {
            return 'يرجى إدخال رقم صحيح';
        }
        return null;
    },
    min: (value, min) => {
        if (value !== null && value !== undefined && value !== '' && Number(value) < min) {
            return `يجب أن تكون القيمة على الأقل ${min}`;
        }
        return null;
    },
    max: (value, max) => {
        if (value !== null && value !== undefined && value !== '' && Number(value) > max) {
            return `يجب أن تكون القيمة على الأكثر ${max}`;
        }
        return null;
    },
    minLength: (value, min) => {
        if (value && value.length < min) {
            return `يجب أن يكون طول النص على الأقل ${min} أحرف`;
        }
        return null;
    },
    maxLength: (value, max) => {
        if (value && value.length > max) {
            return `يجب أن يكون طول النص على الأكثر ${max} أحرف`;
        }
        return null;
    },
    positive: (value) => {
        if (value !== null && value !== undefined && value !== '' && Number(value) <= 0) {
            return 'يجب أن تكون القيمة موجبة';
        }
        return null;
    },
};

function validateField(fieldName, value) {
    const fieldRules = props.rules[fieldName];
    if (!fieldRules) {
        return null;
    }

    for (const rule of fieldRules) {
        let error = null;
        
        if (typeof rule === 'function') {
            error = rule(value);
        } else if (typeof rule === 'string') {
            error = validators[rule]?.(value);
        } else if (typeof rule === 'object') {
            const validator = validators[rule.type];
            if (validator) {
                error = validator(value, rule.value);
            }
        }

        if (error) {
            return error;
        }
    }

    return null;
}

function validateAll() {
    const newErrors = {};
    
    for (const fieldName in props.rules) {
        const error = validateField(fieldName, props.data[fieldName]);
        if (error) {
            newErrors[fieldName] = error;
        }
    }
    
    errors.value = newErrors;
    emit('validate', isValid.value);
    return isValid.value;
}

function clearErrors() {
    errors.value = {};
}

function clearFieldError(fieldName) {
    delete errors.value[fieldName];
}

// مراقبة التغييرات في البيانات
watch(() => props.data, (newData) => {
    for (const fieldName in newData) {
        if (props.rules[fieldName]) {
            const error = validateField(fieldName, newData[fieldName]);
            if (error) {
                errors.value[fieldName] = error;
            } else {
                delete errors.value[fieldName];
            }
        }
    }
    emit('validate', isValid.value);
}, { deep: true });

defineExpose({
    validateAll,
    clearErrors,
    clearFieldError,
    errors,
    isValid,
});
</script>

<template>
    <slot :errors="errors" :isValid="isValid"></slot>
</template>
