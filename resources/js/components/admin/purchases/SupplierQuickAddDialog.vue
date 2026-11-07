<template>
    <!-- Creates a missing supplier and hands it back to be selected, so a
         first-time supplier does not mean abandoning the document. -->
    <el-dialog
        :model-value="modelValue"
        :title="$t('quick_add_supplier')"
        width="440px"
        append-to-body
        destroy-on-close
        @update:model-value="$emit('update:modelValue', $event)"
        @opened="nameRef?.focus()"
    >
        <p class="sqa-hint">{{ $t('quick_add_supplier_hint') }}</p>
        <el-form label-position="top" @submit.prevent="submit">
            <el-form-item :label="$t('supplier_name_label')" required>
                <el-input ref="nameRef" v-model="form.name" maxlength="255" @keydown.enter.prevent="submit" />
            </el-form-item>
            <el-row :gutter="16">
                <el-col :span="12">
                    <el-form-item :label="$t('phone_label')">
                        <el-input v-model="form.phone" dir="ltr" />
                    </el-form-item>
                </el-col>
                <el-col :span="12">
                    <el-form-item :label="$t('company_label')">
                        <el-input v-model="form.company" />
                    </el-form-item>
                </el-col>
            </el-row>
        </el-form>
        <template #footer>
            <el-button @click="$emit('update:modelValue', false)">{{ $t('cancel') }}</el-button>
            <el-button type="primary" :loading="saving" @click="submit">{{ $t('save') }}</el-button>
        </template>
    </el-dialog>
</template>

<script setup>
import { reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { useSuppliersStore } from '@/stores/suppliers';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    seed: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue', 'created']);

const { t } = useI18n();
const suppliersStore = useSuppliersStore();
const nameRef = ref(null);
const saving = ref(false);
const form = reactive({ name: '', phone: '', company: '' });

watch(() => props.modelValue, (open) => {
    if (!open) return;
    form.name = props.seed || '';
    form.phone = '';
    form.company = '';
});

const submit = async () => {
    if (saving.value) return;
    if (!form.name.trim()) {
        ElMessage.warning(t('please_fill_supplier_name'));
        return;
    }
    saving.value = true;
    try {
        const supplier = await suppliersStore.createSupplier({
            name: form.name.trim(),
            phone: form.phone || null,
            company: form.company || null,
        });
        ElMessage.success(t('supplier_created_and_selected'));
        emit('created', supplier);
        emit('update:modelValue', false);
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('failed_to_create_supplier'));
    } finally {
        saving.value = false;
    }
};
</script>

<style scoped>
.sqa-hint {
    margin: 0 0 0.75rem;
    color: var(--el-text-color-secondary);
    font-size: 0.85rem;
}
</style>
