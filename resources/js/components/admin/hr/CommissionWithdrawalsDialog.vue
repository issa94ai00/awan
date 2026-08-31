<template>
    <el-dialog
        :model-value="modelValue"
        @update:model-value="(value) => emit('update:modelValue', value)"
        :title="`${$t('withdrawals')} — ${employeeName}`"
        width="720px"
        destroy-on-close
        @open="load"
    >
        <div v-if="loading" class="loading-state">{{ $t('loading') }}</div>
        <div v-else>
            <el-form :inline="true" class="add-form" @submit.prevent>
                <el-form-item :label="$t('date')" class="full-row">
                    <el-date-picker
                        v-model="form.withdrawn_at"
                        type="datetime"
                        format="YYYY-MM-DD HH:mm"
                        value-format="YYYY-MM-DD HH:mm:ss"
                        style="width:190px"
                    />
                </el-form-item>
                <el-form-item>
                    <el-select v-model="form.currency_code" style="width:120px">
                        <el-option v-for="option in currencies" :key="option.code" :label="option.code" :value="option.code" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-input-number v-model="form.amount" :min="0.01" :precision="2" style="width:140px" />
                </el-form-item>
                <el-form-item>
                    <el-select v-model="form.method" style="width:110px">
                        <el-option :label="$t('cash')" value="cash" />
                        <el-option :label="$t('bank_transfer')" value="bank" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-input v-model="form.reason" :placeholder="$t('reason')" style="width:150px" />
                </el-form-item>
                <el-form-item v-if="form.currency_code !== baseCode">
                    <el-input-number
                        v-model="form.exchange_rate"
                        :min="0"
                        :precision="8"
                        :placeholder="$t('exchange_rate')"
                        style="width:160px"
                    />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" :loading="saving" @click="add">{{ $t('add') }}</el-button>
                </el-form-item>
            </el-form>

            <el-table :data="transactions" size="small" stripe style="width:100%" max-height="260">
                <el-table-column :label="$t('date')" width="150">
                    <template #default="{ row }">{{ formatDateTime(row.withdrawn_at) }}</template>
                </el-table-column>
                <el-table-column :label="$t('currency_code')" width="90" prop="currency_code" />
                <el-table-column :label="$t('withdrawals')" width="130">
                    <template #default="{ row }">{{ formatMoney(row.amount, { code: row.currency_code }) }}</template>
                </el-table-column>
                <el-table-column :label="$t('current_balance')" width="130">
                    <template #default="{ row }">{{ formatMoney(row.base_amount) }}</template>
                </el-table-column>
                <el-table-column :label="$t('payment_method')" width="90">
                    <template #default="{ row }">{{ row.method === 'bank' ? $t('bank_transfer') : $t('cash') }}</template>
                </el-table-column>
                <el-table-column :label="$t('reason')" min-width="120" prop="reason" />
                <el-table-column :label="$t('actions')" width="80" align="center">
                    <template #default="{ row }">
                        <el-button size="small" type="danger" text @click="remove(row)">{{ $t('delete') }}</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <div v-if="!transactions.length" class="empty-state">{{ $t('no_commission_records_yet') }}</div>

            <div v-if="breakdown.length" class="breakdown">
                <h4>{{ $t('cumulative_balance') }} — {{ $t('currency_code') }}</h4>
                <el-table :data="breakdown" size="small" style="width:100%">
                    <el-table-column :label="$t('currency_code')" prop="currency_code" width="100" />
                    <el-table-column :label="$t('invoice_count')" prop="count" width="90" />
                    <el-table-column :label="$t('withdrawals')" width="140">
                        <template #default="{ row }">{{ formatMoney(row.total_amount, { code: row.currency_code }) }}</template>
                    </el-table-column>
                    <el-table-column :label="$t('current_balance')" width="140">
                        <template #default="{ row }">{{ formatMoney(row.total_base_amount) }}</template>
                    </el-table-column>
                </el-table>
            </div>

            <div class="total-line">
                {{ $t('total_withdrawals') }}: <strong>{{ formatMoney(totalBase) }}</strong>
            </div>

            <div v-if="authStore.user?.is_admin" class="trash-toggle">
                <el-button size="small" text @click="toggleTrashed">
                    {{ $t('show_trashed') }} ({{ trashed.length || '' }})
                </el-button>
            </div>
            <div v-if="showTrashed" class="trash-panel">
                <el-table :data="trashed" size="small" style="width:100%" max-height="200">
                    <el-table-column :label="$t('date')" width="150">
                        <template #default="{ row }">{{ formatDateTime(row.withdrawn_at) }}</template>
                    </el-table-column>
                    <el-table-column :label="$t('withdrawals')" width="130">
                        <template #default="{ row }">{{ formatMoney(row.amount, { code: row.currency_code }) }}</template>
                    </el-table-column>
                    <el-table-column :label="$t('deleted_at')" width="150">
                        <template #default="{ row }">{{ formatDateTime(row.deleted_at) }}</template>
                    </el-table-column>
                    <el-table-column :label="$t('actions')" width="90" align="center">
                        <template #default="{ row }">
                            <el-button size="small" type="primary" text :loading="restoringId === row.id" @click="restoreWithdrawal(row)">
                                {{ $t('restore') }}
                            </el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <div v-if="!trashed.length" class="empty-state">{{ $t('no_trashed_records') }}</div>
            </div>
        </div>

        <template #footer>
            <el-button type="primary" @click="emit('update:modelValue', false)">{{ $t('close') }}</el-button>
        </template>
    </el-dialog>
</template>

<script setup>
import { ref, reactive, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useEmployeeCommissionsStore } from '@/stores/employeeCommissions';
import { useAuthStore } from '@/stores/auth';
import { useCurrency } from '@/Composables/useCurrency';
import { currencyOptions } from '@/utils/currency';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    commissionId: { type: [Number, String], default: null },
    employeeName: { type: String, default: '' }
});
const emit = defineEmits(['update:modelValue', 'changed']);

const { t } = useI18n();
const store = useEmployeeCommissionsStore();
const authStore = useAuthStore();
const { formatMoney, baseCode } = useCurrency();

const currencies = computed(() => currencyOptions().map((option) => ({ code: option.code })));

const loading = ref(false);
const saving = ref(false);
const transactions = ref([]);
const breakdown = ref([]);
const totalBase = ref(0);
const showTrashed = ref(false);
const trashed = ref([]);
const restoringId = ref(null);

const emptyForm = () => ({
    withdrawn_at: new Date().toISOString().slice(0, 19).replace('T', ' '),
    currency_code: baseCode.value,
    amount: null,
    method: 'cash',
    reason: '',
    exchange_rate: null
});

const form = reactive(emptyForm());

const formatDateTime = (value) => String(value || '').replace('T', ' ').slice(0, 16);

const load = async () => {
    if (!props.commissionId) return;
    loading.value = true;
    showTrashed.value = false;
    trashed.value = [];
    try {
        const data = await store.fetchWithdrawals(props.commissionId);
        transactions.value = data.transactions || [];
        breakdown.value = data.breakdown || [];
        totalBase.value = data.total_base_amount || 0;
        Object.assign(form, emptyForm());
    } catch (error) {
        ElMessage.error(error.response?.data?.message || t('failed_to_save_commission_record'));
    } finally {
        loading.value = false;
    }
};

const toggleTrashed = async () => {
    showTrashed.value = !showTrashed.value;
    if (showTrashed.value && !trashed.value.length) {
        try {
            trashed.value = await store.fetchTrashedWithdrawals(props.commissionId);
        } catch (error) {
            ElMessage.error(error.response?.data?.message || t('failed_to_restore_commission_record'));
        }
    }
};

const restoreWithdrawal = async (row) => {
    try {
        await ElMessageBox.confirm(t('confirm_restore_record'), t('warning'), { type: 'warning' });
    } catch {
        return;
    }
    restoringId.value = row.id;
    try {
        await store.restoreWithdrawal(props.commissionId, row.id);
        ElMessage.success(t('commission_record_restored'));
        emit('changed');
        trashed.value = await store.fetchTrashedWithdrawals(props.commissionId);
        await load();
        showTrashed.value = true;
    } catch (error) {
        ElMessage.error(error.response?.data?.message || t('failed_to_restore_commission_record'));
    } finally {
        restoringId.value = null;
    }
};

const add = async () => {
    if (!form.amount || form.amount <= 0) {
        ElMessage.error(t('please_enter_valid_amount'));
        return;
    }
    saving.value = true;
    try {
        await store.createWithdrawal(props.commissionId, {
            withdrawn_at: form.withdrawn_at,
            currency_code: form.currency_code,
            amount: form.amount,
            method: form.method,
            reason: form.reason || null,
            exchange_rate: form.exchange_rate || null
        });
        ElMessage.success(t('commission_record_saved'));
        emit('changed');
        await load();
    } catch (error) {
        ElMessage.error(error.response?.data?.message || t('failed_to_save_commission_record'));
    } finally {
        saving.value = false;
    }
};

const remove = async (row) => {
    try {
        await ElMessageBox.confirm(t('confirm_delete_commission_record'), t('warning'), { type: 'warning' });
    } catch {
        return;
    }
    try {
        await store.deleteWithdrawal(props.commissionId, row.id);
        ElMessage.success(t('commission_record_deleted'));
        emit('changed');
        await load();
    } catch (error) {
        ElMessage.error(error.response?.data?.message || t('failed_to_delete_commission_record'));
    }
};
</script>

<style scoped>
.add-form {
    margin-bottom: 1rem;
}

.full-row {
    flex-basis: 100%;
}

.breakdown {
    margin-top: 1.25rem;
}

.breakdown h4 {
    margin: 0 0 0.5rem;
    font-size: 0.95rem;
    color: #5f6d85;
}

.total-line {
    margin-top: 1rem;
    text-align: end;
    font-size: 1.05rem;
}

.loading-state,
.empty-state {
    padding: 1.25rem;
    text-align: center;
    color: #6b7c98;
}

.trash-toggle {
    margin-top: 0.5rem;
    text-align: end;
}

.trash-panel {
    margin-top: 0.5rem;
    border-top: 1px dashed #dcdfe6;
    padding-top: 0.5rem;
}
</style>
