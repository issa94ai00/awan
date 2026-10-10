<template>
    <!--
        Inline "new client" panel for the sales forms (quotes, sales orders).
        A walk-in asking for a price should not send the seller to the CRM
        first: the name alone is enough, phone and company are optional, and
        the profile can be completed later from the Customers page.
    -->
    <div class="quick-customer" @keydown.enter="onEnter" @keydown.esc.stop.prevent="emit('close')">
        <div class="quick-customer-head">
            <span class="quick-customer-title">
                <i class="fas fa-user-plus"></i>&nbsp;{{ $t('qt_quick_customer_title') }}
            </span>
            <span class="quick-customer-hint">{{ $t('qt_quick_customer_hint') }}</span>
        </div>

        <div class="quick-customer-grid">
            <div class="qc-field qc-field--name">
                <label class="qc-label">{{ $t('name') }} <span class="qc-req">*</span></label>
                <el-input
                    ref="nameRef"
                    v-model="form.name"
                    :placeholder="$t('customer_name')"
                    maxlength="255"
                    :class="{ 'is-error': errors.name }"
                    @input="clearError('name')"
                />
                <small v-if="errors.name" class="qc-error">{{ errors.name }}</small>
            </div>
            <div class="qc-field">
                <label class="qc-label">{{ $t('phone') }} <span class="qc-opt">{{ $t('optional') }}</span></label>
                <el-input
                    v-model="form.phone"
                    dir="ltr"
                    inputmode="tel"
                    placeholder="09xxxxxxxx"
                    maxlength="20"
                    :class="{ 'is-error': errors.phone }"
                    @input="onPhoneInput"
                >
                    <template #suffix>
                        <i v-if="checkingPhone" class="fas fa-spinner fa-spin qc-suffix"></i>
                        <i v-else-if="duplicate" class="fas fa-exclamation-circle qc-suffix warn"></i>
                        <i v-else-if="phoneChecked" class="fas fa-check-circle qc-suffix ok"></i>
                    </template>
                </el-input>
                <small v-if="errors.phone" class="qc-error">{{ errors.phone }}</small>
            </div>
            <div class="qc-field">
                <label class="qc-label">{{ $t('company') }} <span class="qc-opt">{{ $t('optional') }}</span></label>
                <el-input v-model="form.company" :placeholder="$t('company_name')" maxlength="255" />
            </div>
        </div>

        <!-- The API upserts by phone, so a known number must pick the existing client instead of renaming them -->
        <div v-if="duplicate" class="qc-duplicate">
            <i class="fas fa-exclamation-triangle"></i>
            <span class="qc-duplicate-text">{{ $t('qt_customer_exists_phone', { name: duplicate.name }) }}</span>
            <button type="button" class="qc-link" @click="emit('select', duplicate, { created: false })">
                <i class="fas fa-user-check"></i>&nbsp;{{ $t('qt_use_existing_customer') }}
            </button>
        </div>

        <div class="quick-customer-foot">
            <span class="qc-kbd-hint"><kbd>Enter</kbd> {{ $t('qt_add_and_select') }} &bull; <kbd>Esc</kbd> {{ $t('cancel') }}</span>
            <div class="qc-actions">
                <el-button size="small" @click="emit('close')">{{ $t('cancel') }}</el-button>
                <el-button
                    size="small"
                    type="primary"
                    :loading="saving"
                    :disabled="!!duplicate || !form.name.trim()"
                    @click="save"
                >
                    <i v-if="!saving" class="fas fa-check"></i>&nbsp;{{ $t('qt_add_and_select') }}
                </el-button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { nextTick, onMounted, onUnmounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { posApi } from '@/api/pos';
import { apiErrorMessage } from '@/utils/sales';

const props = defineProps({
    /** What the seller typed into the picker before nothing matched: digits seed the phone, text the name. */
    seed: { type: String, default: '' },
});
const emit = defineEmits(['select', 'close']);

const { t } = useI18n();

const nameRef = ref(null);
const saving = ref(false);
const checkingPhone = ref(false);
const phoneChecked = ref(false);
const duplicate = ref(null);
const errors = reactive({});
const form = reactive({ name: '', phone: '', company: '' });
let phoneTimer = null;

const normalizePhone = (p) => String(p || '').replace(/\D/g, '');
const looksLikePhone = (text) => /^[\d\s+\-()]{6,}$/.test(text || '');
const clearError = (key) => delete errors[key];

// The POS endpoint upserts by phone: posting a known number would silently
// rename that customer. Look the number up as it is typed and steer the
// seller to the existing record instead.
const checkDuplicatePhone = async () => {
    const phone = form.phone.trim();
    if (normalizePhone(phone).length < 6) return;
    checkingPhone.value = true;
    try {
        const res = await posApi.customers({ search: phone, per_page: 10 });
        const found = res.data?.data?.customers || [];
        const match = found.find((c) => normalizePhone(c.phone) === normalizePhone(phone));
        if (form.phone.trim() === phone) {
            duplicate.value = match || null;
            phoneChecked.value = true;
        }
    } catch {
        // Lookup is a courtesy; saving still goes through validation.
    } finally {
        checkingPhone.value = false;
    }
};

const onPhoneInput = () => {
    clearError('phone');
    duplicate.value = null;
    phoneChecked.value = false;
    clearTimeout(phoneTimer);
    if (normalizePhone(form.phone).length < 6) return;
    phoneTimer = setTimeout(checkDuplicatePhone, 350);
};

/** Enter inside one of the three inputs saves; on a button it keeps its own meaning. */
const onEnter = (event) => {
    if (event.target?.tagName !== 'INPUT') return;
    event.preventDefault();
    save();
};

const save = async () => {
    if (saving.value || duplicate.value) return;
    Object.keys(errors).forEach((k) => delete errors[k]);
    const name = form.name.trim();
    if (!name) {
        errors.name = t('qt_customer_name_required');
        nameRef.value?.focus();
        return;
    }
    saving.value = true;
    try {
        const res = await posApi.customerStore({
            name,
            phone: form.phone.trim() || null,
            company: form.company.trim() || null,
            status: 'active',
        });
        const customer = res.data?.data || res.data;
        ElMessage.success(t('qt_customer_added', { name: customer?.name || name }));
        emit('select', customer, { created: true });
    } catch (error) {
        const fieldErrors = error.response?.data?.errors || {};
        Object.entries(fieldErrors).forEach(([key, messages]) => {
            errors[key] = Array.isArray(messages) ? messages[0] : String(messages);
        });
        if (!Object.keys(fieldErrors).length) ElMessage.error(apiErrorMessage(error, t('qt_customer_add_failed')));
    } finally {
        saving.value = false;
    }
};

onMounted(() => {
    const text = String(props.seed || '').trim();
    if (looksLikePhone(text)) {
        form.phone = text;
        checkDuplicatePhone();
    } else {
        form.name = text;
    }
    nextTick(() => nameRef.value?.focus());
});

onUnmounted(() => clearTimeout(phoneTimer));
</script>

<style scoped>
.quick-customer {
    width: 100%;
    margin-top: 0.5rem;
    padding: 0.75rem 0.85rem;
    border: 1px solid #bfdbfe;
    border-radius: 8px;
    background: linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
    box-shadow: 0 1px 2px rgba(37, 99, 235, 0.06);
    font-family: 'Cairo', sans-serif;
}

.quick-customer-head {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
    margin-bottom: 0.6rem;
}

.quick-customer-title {
    font-size: 0.85rem;
    font-weight: 700;
    color: #1e3a8a;
}

.quick-customer-hint {
    font-size: 0.74rem;
    color: #64748b;
}

.quick-customer-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr) minmax(0, 1fr);
    gap: 0.6rem;
}

.qc-field {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 0;
}

.qc-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: #475569;
    line-height: 1.2;
}

.qc-req {
    color: #dc2626;
}

.qc-opt {
    font-weight: 400;
    color: #94a3b8;
    font-size: 0.7rem;
}

.qc-error {
    font-size: 0.72rem;
    color: #dc2626;
}

.qc-field :deep(.is-error .el-input__wrapper) {
    box-shadow: 0 0 0 1px #f87171 inset;
}

.qc-suffix {
    font-size: 0.85rem;
    color: #94a3b8;
}
.qc-suffix.ok { color: #16a34a; }
.qc-suffix.warn { color: #d97706; }

.qc-duplicate {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-top: 0.6rem;
    padding: 0.45rem 0.65rem;
    border-radius: 6px;
    background: #fffbeb;
    border: 1px solid #fde68a;
    color: #92400e;
    font-size: 0.78rem;
}

.qc-duplicate-text {
    flex: 1 1 auto;
    min-width: 0;
}

.qc-link {
    all: unset;
    cursor: pointer;
    font-weight: 700;
    color: #1d4ed8;
    white-space: nowrap;
}
.qc-link:hover {
    text-decoration: underline;
}

.quick-customer-foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
    margin-top: 0.7rem;
}

.qc-kbd-hint {
    font-size: 0.72rem;
    color: #94a3b8;
}
.qc-kbd-hint kbd {
    font-family: inherit;
    font-size: 0.68rem;
    padding: 0 0.3rem;
    border: 1px solid #e2e8f0;
    border-bottom-width: 2px;
    border-radius: 4px;
    background: #fff;
    color: #475569;
}

.qc-actions {
    display: flex;
    gap: 0.4rem;
    margin-inline-start: auto;
}

@media (max-width: 860px) {
    .quick-customer-grid {
        grid-template-columns: 1fr;
    }

    .qc-kbd-hint {
        display: none;
    }

    .qc-actions {
        width: 100%;
    }

    .qc-actions .el-button {
        flex: 1;
    }
}
</style>
