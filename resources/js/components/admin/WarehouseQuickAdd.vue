<template>
    <el-dialog
        :model-value="modelValue"
        :title="$t('wq_title')"
        width="480px"
        class="warehouse-quick-add"
        append-to-body
        destroy-on-close
        @update:model-value="(v) => emit('update:modelValue', v)"
        @open="reset"
    >
        <p class="wq-hint">{{ $t('wq_hint') }}</p>

        <el-form ref="formRef" :model="form" :rules="rules" label-position="top" @submit.prevent="submit">
            <el-form-item :label="$t('wms.name')" prop="name" :error="errors.name">
                <el-input ref="nameInput" v-model="form.name" :placeholder="$t('wms.name_placeholder')" maxlength="255" />
            </el-form-item>

            <div class="wq-row">
                <el-form-item :label="$t('wms.code')" prop="code" :error="errors.code" class="wq-grow">
                    <el-input v-model="form.code" :placeholder="$t('wms.code_placeholder')" maxlength="50" dir="ltr">
                        <template #append>
                            <el-button @click="suggestCode">{{ $t('wq_suggest_code') }}</el-button>
                        </template>
                    </el-input>
                </el-form-item>
                <el-form-item :label="$t('wms.location_type')" prop="location_type" class="wq-grow">
                    <el-select v-model="form.location_type" style="width: 100%">
                        <el-option value="warehouse" :label="$t('wms.warehouse')" />
                        <el-option value="branch" :label="$t('wms.branch')" />
                        <el-option value="distribution_center" :label="$t('wms.distribution_center')" />
                        <el-option value="3pl" :label="$t('wms.3pl')" />
                    </el-select>
                </el-form-item>
            </div>

            <el-form-item :label="$t('wms.city')" prop="city">
                <el-input v-model="form.city" :placeholder="$t('wms.city_placeholder')" maxlength="255" />
            </el-form-item>
            <el-form-item :label="$t('wms.address')" prop="address">
                <el-input v-model="form.address" :placeholder="$t('wms.address_placeholder')" maxlength="1000" />
            </el-form-item>
        </el-form>

        <template #footer>
            <el-button @click="emit('update:modelValue', false)">{{ $t('cancel') }}</el-button>
            <el-button type="primary" :loading="saving" @click="submit">{{ $t('wq_add') }}</el-button>
        </template>
    </el-dialog>
</template>

<script setup>
import { ref, reactive, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import api from '@/api';

/**
 * Adds a warehouse without leaving the current form. Only what the server
 * requires (name, code, type) plus where it is; the rest is completed from
 * the Warehouses screen. Emits `created` with the new warehouse.
 */
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    // Codes already taken, so the suggestion skips them.
    existingCodes: { type: Array, default: () => [] },
});
const emit = defineEmits(['update:modelValue', 'created']);

const { t } = useI18n();
const formRef = ref(null);
const nameInput = ref(null);
const saving = ref(false);
const errors = reactive({ name: '', code: '' });

const blank = () => ({ name: '', code: '', location_type: 'warehouse', city: '', address: '' });
const form = reactive(blank());

const rules = {
    name: [{ required: true, message: t('wms.name_required'), trigger: 'blur' }],
    code: [{ required: true, message: t('wms.code_required'), trigger: 'blur' }],
};

const reset = () => {
    Object.assign(form, blank());
    errors.name = '';
    errors.code = '';
    suggestCode();
    nextTick(() => {
        formRef.value?.clearValidate();
        nameInput.value?.focus();
    });
};

// WH-001, WH-002… the first number no warehouse uses yet.
const suggestCode = () => {
    const taken = new Set(props.existingCodes.map((c) => String(c || '').toUpperCase()));
    let n = taken.size + 1;
    const code = (i) => `WH-${String(i).padStart(3, '0')}`;
    for (let i = 1; i <= n; i++) {
        if (!taken.has(code(i))) {
            n = i;
            break;
        }
    }
    form.code = code(n);
};

const submit = () => {
    formRef.value.validate(async (valid) => {
        if (!valid) return;
        saving.value = true;
        errors.name = '';
        errors.code = '';
        try {
            // The admin route: it admits the admin role as well as is_admin
            // accounts, the same people who may open this employee form.
            const res = await api.post('/admin/wms/warehouses', { ...form, code: form.code.trim(), is_active: true });
            const warehouse = res.data?.data || res.data;
            ElMessage.success(t('wq_created', { name: warehouse.name }));
            emit('created', warehouse);
            emit('update:modelValue', false);
        } catch (err) {
            const fieldErrors = err.response?.data?.errors || {};
            errors.name = fieldErrors.name?.[0] || '';
            errors.code = fieldErrors.code?.[0] || '';
            if (!errors.name && !errors.code) {
                ElMessage.error(err.response?.data?.message || err.message);
            }
        } finally {
            saving.value = false;
        }
    });
};
</script>

<style scoped>
.wq-hint {
    margin: -0.5rem 0 1rem;
    font-size: 0.85rem;
    color: var(--el-text-color-secondary, #8a94a6);
}

.wq-row {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.wq-grow {
    flex: 1 1 180px;
}
</style>
