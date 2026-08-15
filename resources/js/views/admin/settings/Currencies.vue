<template>
    <div class="currencies-page">
        <div class="page-head">
            <div>
                <h1><i class="fas fa-coins"></i> {{ $t('manage_currencies') }}</h1>
                <p class="muted">
                    {{ $t('currency_display_only_notice') }}
                </p>
            </div>
            <el-button type="primary" @click="openCreate">
                <i class="fas fa-plus"></i> {{ $t('add_currency') }}
            </el-button>
        </div>

        <el-alert
            v-if="baseCode"
            :title="`عملة الأساس الحالية: ${baseCode}`"
            type="info"
            :closable="false"
            show-icon
            class="mb-4"
        />

        <el-card shadow="never" v-loading="loading">
            <el-table :data="currencies" stripe :empty-text="$t('no_currencies')">
                <el-table-column :label="$t('currency')" min-width="180">
                    <template #default="{ row }">
                        <div class="cur-name">
                            <strong>{{ row.name_ar || row.code }}</strong>
                            <el-tag v-if="row.is_base" type="primary" size="small" effect="dark">{{ $t('base_currency_tag') }}</el-tag>
                            <el-tag v-if="!row.is_active" type="info" size="small">{{ $t('disabled_female') }}</el-tag>
                        </div>
                        <span class="muted mono">{{ row.code }} · {{ row.symbol }}</span>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('exchange_rate')" min-width="200">
                    <template #default="{ row }">
                        <div v-if="row.is_base" class="muted">{{ $t('one_base_currency') }}</div>
                        <div v-else-if="row.rate">
                            <span class="mono">1 {{ baseCode }} = {{ row.rate }} {{ row.code }}</span>
                            <p class="muted tiny">{{ formatDate(row.rate_effective_at) }}</p>
                        </div>
                        <!-- No rate means the clients show base prices rather than
                             converting by a number nobody supplied. -->
                        <el-tag v-else type="warning" size="small">{{ $t('no_rate_entered_yet') }}</el-tag>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('formatting')" width="150">
                    <template #default="{ row }">
                        <span class="muted tiny">
                            {{ row.decimal_places }} منزلة
                            <template v-if="row.rounding_step > 0"> · تقريب لـ {{ row.rounding_step }}</template>
                        </span>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('procedures')" width="280" align="center">
                    <template #default="{ row }">
                        <el-button v-if="!row.is_base" size="small" text type="primary" @click="openRate(row)">
                            {{ $t('new_rate') }}
                        </el-button>
                        <el-button size="small" text @click="openEdit(row)">{{ $t('edit') }}</el-button>
                        <el-button v-if="!row.is_base" size="small" text type="warning" @click="makeBase(row)">
                            {{ $t('make_it_the_base') }}
                        </el-button>
                    </template>
                </el-table-column>

                <el-table-column type="expand">
                    <template #default="{ row }">
                        <div class="history">
                            <p class="muted tiny">{{ $t('rate_history_newest_first') }}</p>
                            <el-empty v-if="!row.rate_history?.length" :description="$t('no_history')" :image-size="40" />
                            <ul v-else>
                                <li v-for="entry in row.rate_history" :key="entry.id">
                                    <span class="mono">{{ entry.rate }}</span>
                                    <span class="muted"> — {{ formatDate(entry.effective_at) }}</span>
                                    <span v-if="entry.created_by" class="muted"> · {{ entry.created_by }}</span>
                                    <span v-if="entry.note" class="muted"> · {{ entry.note }}</span>
                                </li>
                            </ul>
                        </div>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- Add / edit -->
        <el-dialog v-model="formVisible" :title="form.id ? $t('edit_currency') : $t('add_currency')" width="520px">
            <el-form label-position="top">
                <el-form-item :label="$t('iso_code')" v-if="!form.id">
                    <el-input v-model="form.code" placeholder="USD" maxlength="8" />
                    <!-- The code is what every stored amount's currency column
                         refers to, so it is set once and never edited. -->
                    <p class="muted tiny">{{ $t('code_saved_with_amounts') }}</p>
                </el-form-item>
                <el-form-item :label="$t('name_arabic')">
                    <el-input v-model="form.name_ar" />
                </el-form-item>
                <el-form-item :label="$t('name_english')">
                    <el-input v-model="form.name_en" dir="ltr" />
                </el-form-item>
                <el-form-item :label="$t('written_symbol')">
                    <el-input v-model="form.symbol" :placeholder="$t('symbol_example')" />
                </el-form-item>
                <el-form-item :label="$t('decimal_places')">
                    <el-input-number v-model="form.decimal_places" :min="0" :max="4" />
                </el-form-item>
                <el-form-item :label="$t('rounding_step')">
                    <el-input-number v-model="form.rounding_step" :min="0" :step="100" />
                    <p class="muted tiny">
                        {{ $t('rounding_step_hint') }}
                    </p>
                </el-form-item>
                <el-form-item v-if="!form.id" :label="$t('initial_exchange_rate')">
                    <el-input-number v-model="form.rate" :min="0" :precision="4" :step="0.01" />
                    <p class="muted tiny">{{ $t('how_many_of_this_currency', { base: baseCode }) }}</p>
                </el-form-item>
                <el-form-item v-if="form.id">
                    <el-checkbox v-model="form.is_active">{{ $t('enabled_female') }}</el-checkbox>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="formVisible = false">{{ $t('cancel') }}</el-button>
                <el-button type="primary" :loading="saving" @click="save">{{ $t('save') }}</el-button>
            </template>
        </el-dialog>

        <!-- New rate -->
        <el-dialog v-model="rateVisible" :title="`سعر جديد لـ ${rateTarget?.code || ''}`" width="460px">
            <el-form label-position="top">
                <el-form-item :label="$t('price')">
                    <el-input-number v-model="rateForm.rate" :min="0" :precision="4" :step="0.01" style="width: 100%" />
                    <p class="muted tiny">1 {{ baseCode }} = كم من {{ rateTarget?.code }}؟</p>
                </el-form-item>
                <el-form-item :label="$t('effective_from')">
                    <el-date-picker v-model="rateForm.effective_at" type="datetime" style="width: 100%" />
                    <p class="muted tiny">{{ $t('leave_empty_to_start_now') }}</p>
                </el-form-item>
                <el-form-item :label="$t('note')">
                    <el-input v-model="rateForm.note" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="rateVisible = false">{{ $t('cancel') }}</el-button>
                <el-button type="primary" :loading="saving" @click="saveRate">{{ $t('save_rate') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import { onMounted, reactive, ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import currenciesApi from '@/api/currencies';

const { t } = useI18n();

const currencies = ref([]);
const baseCode = ref('');
const loading = ref(false);
const saving = ref(false);

const formVisible = ref(false);
const form = reactive({
    id: null,
    code: '',
    name_ar: '',
    name_en: '',
    symbol: '',
    decimal_places: 2,
    rounding_step: 0,
    rate: null,
    is_active: true,
});

const rateVisible = ref(false);
const rateTarget = ref(null);
const rateForm = reactive({ rate: null, effective_at: null, note: '' });

const errorMessage = (e, fallback) => e?.response?.data?.message || fallback;

const load = async () => {
    loading.value = true;
    try {
        const res = await currenciesApi.list();
        currencies.value = res.data?.data?.currencies || [];
        baseCode.value = res.data?.data?.base || '';
    } catch (e) {
        ElMessage.error(errorMessage(e, t('failed_to_load_currencies')));
    } finally {
        loading.value = false;
    }
};

const openCreate = () => {
    Object.assign(form, {
        id: null, code: '', name_ar: '', name_en: '', symbol: '',
        decimal_places: 2, rounding_step: 0, rate: null, is_active: true,
    });
    formVisible.value = true;
};

const openEdit = (row) => {
    Object.assign(form, {
        id: row.id,
        code: row.code,
        name_ar: row.name_ar || '',
        name_en: row.name_en || '',
        symbol: row.symbol || '',
        decimal_places: row.decimal_places ?? 2,
        rounding_step: row.rounding_step ?? 0,
        rate: null,
        is_active: row.is_active !== false,
    });
    formVisible.value = true;
};

const save = async () => {
    saving.value = true;
    try {
        if (form.id) {
            await currenciesApi.update(form.id, {
                name_ar: form.name_ar,
                name_en: form.name_en,
                symbol: form.symbol,
                decimal_places: form.decimal_places,
                rounding_step: form.rounding_step,
                is_active: form.is_active,
            });
        } else {
            await currenciesApi.create({ ...form, id: undefined });
        }
        formVisible.value = false;
        ElMessage.success(t('saved'));
        await load();
    } catch (e) {
        ElMessage.error(errorMessage(e, t('failed_to_save_currency')));
    } finally {
        saving.value = false;
    }
};

const openRate = (row) => {
    rateTarget.value = row;
    Object.assign(rateForm, { rate: Number(row.rate) || null, effective_at: null, note: '' });
    rateVisible.value = true;
};

const saveRate = async () => {
    saving.value = true;
    try {
        await currenciesApi.addRate(rateTarget.value.id, {
            rate: rateForm.rate,
            effective_at: rateForm.effective_at || undefined,
            note: rateForm.note || undefined,
        });
        rateVisible.value = false;
        ElMessage.success(t('exchange_rate_saved'));
        await load();
    } catch (e) {
        ElMessage.error(errorMessage(e, t('failed_to_save_rate')));
    } finally {
        saving.value = false;
    }
};

const makeBase = async (row) => {
    // Spelled out because it is not reversible by another click: the stored
    // numbers are not re-expressed, so every rate has to be re-entered against
    // the new base or they mean nothing.
    try {
        await ElMessageBox.confirm(
            t('confirm_set_base_currency', { code: row.code }),
            t('base_currency_change_title'),
            { type: 'warning', confirmButtonText: t('tracking'), cancelButtonText: t('cancel') },
        );
    } catch {
        return;
    }

    try {
        const res = await currenciesApi.setBase(row.id);
        ElMessage.warning(res.data?.message || t('base_currency_changed'));
        const base = res.data?.data?.base || row.code;
        if (window.systemData) {
            window.systemData.settings = {
                ...(window.systemData.settings || {}),
                default_currency: base,
            };
            window.systemData.currencies = {
                ...(window.systemData.currencies || {}),
                base,
            };
        }
        await load();
    } catch (e) {
        ElMessage.error(errorMessage(e, t('failed_to_change_base_currency')));
    }
};

const formatDate = (value) => (value ? new Date(value).toLocaleString('en-GB') : '—');

onMounted(load);
</script>

<style scoped>
.currencies-page { padding: 1rem; }

.page-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    margin-block-end: 1rem;
}

.page-head h1 { margin: 0; font-size: 1.25rem; font-weight: 800; }

.muted { color: var(--text-muted, #94a3b8); }
.tiny { font-size: 0.75rem; margin: 0.15rem 0 0; }
.mono { font-family: ui-monospace, monospace; direction: ltr; display: inline-block; }

.cur-name { display: flex; align-items: center; gap: 0.4rem; }

.mb-4 { margin-block-end: 1rem; }

.history ul { margin: 0.4rem 0 0; padding-inline-start: 1.1rem; }
.history li { font-size: 0.8rem; line-height: 1.9; }
</style>
