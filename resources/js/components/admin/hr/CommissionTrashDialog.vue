<template>
    <el-dialog
        :model-value="modelValue"
        @update:model-value="(value) => emit('update:modelValue', value)"
        :title="$t('trashed_records')"
        width="820px"
        destroy-on-close
        @open="load"
    >
        <p class="hint">{{ $t('trashed_records_hint') }}</p>

        <div v-if="loading" class="loading-state">{{ $t('loading') }}</div>
        <div v-else>
            <el-table :data="records" size="small" stripe style="width:100%" max-height="420">
                <el-table-column :label="$t('employee')" min-width="140">
                    <template #default="{ row }">{{ row.employee?.name }}</template>
                </el-table-column>
                <el-table-column :label="$t('month')" width="100">
                    <template #default="{ row }">{{ String(row.month).slice(0, 7) }}</template>
                </el-table-column>
                <el-table-column :label="$t('current_balance')" width="130">
                    <template #default="{ row }">{{ formatMoney(row.balance) }}</template>
                </el-table-column>
                <el-table-column :label="$t('deleted_at')" width="150">
                    <template #default="{ row }">{{ formatDateTime(row.deleted_at) }}</template>
                </el-table-column>
                <el-table-column :label="$t('deleted_by')" min-width="120">
                    <template #default="{ row }">{{ row.deleter?.name || '—' }}</template>
                </el-table-column>
                <el-table-column :label="$t('actions')" width="100" align="center">
                    <template #default="{ row }">
                        <el-button size="small" type="primary" text :loading="restoringId === row.id" @click="restore(row)">
                            {{ $t('restore') }}
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>
            <div v-if="!records.length" class="empty-state">{{ $t('no_trashed_records') }}</div>
        </div>

        <template #footer>
            <el-button type="primary" @click="emit('update:modelValue', false)">{{ $t('close') }}</el-button>
        </template>
    </el-dialog>
</template>

<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useEmployeeCommissionsStore } from '@/stores/employeeCommissions';
import { useCurrency } from '@/Composables/useCurrency';

const props = defineProps({
    modelValue: { type: Boolean, default: false }
});
const emit = defineEmits(['update:modelValue', 'restored']);

const { t } = useI18n();
const store = useEmployeeCommissionsStore();
const { formatMoney } = useCurrency();

const loading = ref(false);
const restoringId = ref(null);
const records = ref([]);

const formatDateTime = (value) => String(value || '').replace('T', ' ').slice(0, 16);

const load = async () => {
    loading.value = true;
    try {
        records.value = await store.fetchTrashed();
    } catch (error) {
        ElMessage.error(error.response?.data?.message || t('failed_to_restore_commission_record'));
    } finally {
        loading.value = false;
    }
};

const restore = async (row) => {
    try {
        await ElMessageBox.confirm(t('confirm_restore_record'), t('warning'), { type: 'warning' });
    } catch {
        return;
    }
    restoringId.value = row.id;
    try {
        await store.restoreRecord(row.id);
        ElMessage.success(t('commission_record_restored'));
        emit('restored');
        await load();
    } catch (error) {
        ElMessage.error(error.response?.data?.message || t('failed_to_restore_commission_record'));
    } finally {
        restoringId.value = null;
    }
};
</script>

<style scoped>
.hint {
    margin: 0 0 1rem;
    color: #6b7c98;
    font-size: 0.9rem;
}

.loading-state,
.empty-state {
    padding: 1.25rem;
    text-align: center;
    color: #6b7c98;
}
</style>
