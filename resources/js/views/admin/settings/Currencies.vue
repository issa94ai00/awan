<template>
    <div class="currencies-page">
        <div class="page-head">
            <div>
                <h1><i class="fas fa-coins"></i> إدارة العملات</h1>
                <p class="muted">
                    كل المبالغ تُخزَّن وتُرحَّل بعملة الأساس. أسعار الصرف هنا تُستخدم لعرض الأسعار
                    للزبون بعملته فقط، ولا تغيّر المبلغ المُحصَّل.
                </p>
            </div>
            <el-button type="primary" @click="openCreate">
                <i class="fas fa-plus"></i> إضافة عملة
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
            <el-table :data="currencies" stripe empty-text="لا توجد عملات">
                <el-table-column label="العملة" min-width="180">
                    <template #default="{ row }">
                        <div class="cur-name">
                            <strong>{{ row.name_ar || row.code }}</strong>
                            <el-tag v-if="row.is_base" type="primary" size="small" effect="dark">الأساس</el-tag>
                            <el-tag v-if="!row.is_active" type="info" size="small">معطّلة</el-tag>
                        </div>
                        <span class="muted mono">{{ row.code }} · {{ row.symbol }}</span>
                    </template>
                </el-table-column>

                <el-table-column label="سعر الصرف" min-width="200">
                    <template #default="{ row }">
                        <div v-if="row.is_base" class="muted">1 (عملة الأساس)</div>
                        <div v-else-if="row.rate">
                            <span class="mono">1 {{ baseCode }} = {{ row.rate }} {{ row.code }}</span>
                            <p class="muted tiny">{{ formatDate(row.rate_effective_at) }}</p>
                        </div>
                        <!-- No rate means the clients show base prices rather than
                             converting by a number nobody supplied. -->
                        <el-tag v-else type="warning" size="small">لم يُدخل سعر بعد</el-tag>
                    </template>
                </el-table-column>

                <el-table-column label="التنسيق" width="150">
                    <template #default="{ row }">
                        <span class="muted tiny">
                            {{ row.decimal_places }} منزلة
                            <template v-if="row.rounding_step > 0"> · تقريب لـ {{ row.rounding_step }}</template>
                        </span>
                    </template>
                </el-table-column>

                <el-table-column label="إجراءات" width="280" align="center">
                    <template #default="{ row }">
                        <el-button v-if="!row.is_base" size="small" text type="primary" @click="openRate(row)">
                            سعر جديد
                        </el-button>
                        <el-button size="small" text @click="openEdit(row)">تعديل</el-button>
                        <el-button v-if="!row.is_base" size="small" text type="warning" @click="makeBase(row)">
                            اجعلها الأساس
                        </el-button>
                    </template>
                </el-table-column>

                <el-table-column type="expand">
                    <template #default="{ row }">
                        <div class="history">
                            <p class="muted tiny">سجل الأسعار (الأحدث أولاً)</p>
                            <el-empty v-if="!row.rate_history?.length" description="لا يوجد سجل" :image-size="40" />
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
        <el-dialog v-model="formVisible" :title="form.id ? 'تعديل العملة' : 'إضافة عملة'" width="520px">
            <el-form label-position="top">
                <el-form-item label="الرمز (ISO)" v-if="!form.id">
                    <el-input v-model="form.code" placeholder="USD" maxlength="8" />
                    <!-- The code is what every stored amount's currency column
                         refers to, so it is set once and never edited. -->
                    <p class="muted tiny">يُحفظ مع كل مبلغ ولا يمكن تغييره لاحقاً.</p>
                </el-form-item>
                <el-form-item label="الاسم بالعربية">
                    <el-input v-model="form.name_ar" />
                </el-form-item>
                <el-form-item label="الاسم بالإنجليزية">
                    <el-input v-model="form.name_en" dir="ltr" />
                </el-form-item>
                <el-form-item label="الرمز الكتابي">
                    <el-input v-model="form.symbol" placeholder="ل.س" />
                </el-form-item>
                <el-form-item label="المنازل العشرية">
                    <el-input-number v-model="form.decimal_places" :min="0" :max="4" />
                </el-form-item>
                <el-form-item label="خطوة التقريب">
                    <el-input-number v-model="form.rounding_step" :min="0" :step="100" />
                    <p class="muted tiny">
                        صفر = بلا تقريب. استخدمها حين تكون أصغر فئة نقدية كبيرة (مثلاً 500).
                    </p>
                </el-form-item>
                <el-form-item v-if="!form.id" label="سعر الصرف الابتدائي">
                    <el-input-number v-model="form.rate" :min="0" :precision="4" :step="0.01" />
                    <p class="muted tiny">1 {{ baseCode }} = كم من هذه العملة؟</p>
                </el-form-item>
                <el-form-item v-if="form.id">
                    <el-checkbox v-model="form.is_active">مفعّلة</el-checkbox>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="formVisible = false">إلغاء</el-button>
                <el-button type="primary" :loading="saving" @click="save">حفظ</el-button>
            </template>
        </el-dialog>

        <!-- New rate -->
        <el-dialog v-model="rateVisible" :title="`سعر جديد لـ ${rateTarget?.code || ''}`" width="460px">
            <el-form label-position="top">
                <el-form-item label="السعر">
                    <el-input-number v-model="rateForm.rate" :min="0" :precision="4" :step="0.01" style="width: 100%" />
                    <p class="muted tiny">1 {{ baseCode }} = كم من {{ rateTarget?.code }}؟</p>
                </el-form-item>
                <el-form-item label="يبدأ من">
                    <el-date-picker v-model="rateForm.effective_at" type="datetime" style="width: 100%" />
                    <p class="muted tiny">اتركه فارغاً ليبدأ الآن. السعر السابق يبقى محفوظاً.</p>
                </el-form-item>
                <el-form-item label="ملاحظة">
                    <el-input v-model="rateForm.note" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="rateVisible = false">إلغاء</el-button>
                <el-button type="primary" :loading="saving" @click="saveRate">حفظ السعر</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import currenciesApi from '@/api/currencies';

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
        ElMessage.error(errorMessage(e, 'تعذّر تحميل العملات.'));
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
        ElMessage.success('تم الحفظ.');
        await load();
    } catch (e) {
        ElMessage.error(errorMessage(e, 'تعذّر حفظ العملة.'));
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
        ElMessage.success('تم حفظ سعر الصرف.');
        await load();
    } catch (e) {
        ElMessage.error(errorMessage(e, 'تعذّر حفظ السعر.'));
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
            `سيتم اعتماد ${row.code} كعملة أساس. المبالغ المخزّنة لن تُحوَّل، ويجب إعادة إدخال أسعار الصرف مقابل العملة الجديدة. متابعة؟`,
            'تغيير عملة الأساس',
            { type: 'warning', confirmButtonText: 'متابعة', cancelButtonText: 'إلغاء' },
        );
    } catch {
        return;
    }

    try {
        const res = await currenciesApi.setBase(row.id);
        ElMessage.warning(res.data?.message || 'تم تغيير عملة الأساس.');
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
        ElMessage.error(errorMessage(e, 'تعذّر تغيير عملة الأساس.'));
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
