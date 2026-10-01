<template>
    <div class="customer-step">
        <!-- Who is buying -->
        <section class="panel" :class="{ 'has-error': order.attempted[1] && !order.form.customer_id }">
            <header class="panel-head">
                <h3><i class="fas fa-user"></i> {{ t('choose_the_buying_customer') }}</h3>
                <!-- Untyped: the admin theme fills "plain primary" with the
                     colour of its own text, which left this one unreadable. -->
                <el-button @click="openNewCustomer">
                    <i class="fas fa-user-plus"></i>&nbsp;{{ t('so_new_customer') }}
                </el-button>
            </header>

            <!-- Searched on the server as it is typed. It used to load one page
                 of customers, so anyone after the twentieth could not be found. -->
            <el-select
                ref="customerSelectRef"
                :model-value="order.form.customer_id"
                filterable
                remote
                clearable
                size="large"
                class="w-full"
                :remote-method="searchCustomers"
                :loading="customersLoading"
                :placeholder="t('search_and_choose_customer')"
                @update:model-value="chooseCustomer"
                @visible-change="(visible) => visible && !customerOptions.length && searchCustomers('')"
            >
                <el-option v-for="c in customerOptions" :key="c.id" :value="c.id" :label="c.name">
                    <div class="customer-option">
                        <span>{{ c.name }}</span>
                        <small dir="ltr">{{ c.phone || c.email || '' }}</small>
                    </div>
                </el-option>
            </el-select>
            <p v-if="order.attempted[1] && !order.form.customer_id" class="field-error">{{ t('choose_customer_before_final_step') }}</p>

            <div v-if="customer" class="customer-card">
                <span class="avatar">{{ (customer.name || '?').trim().charAt(0) }}</span>
                <div class="customer-info">
                    <strong>{{ customer.name }}</strong>
                    <span v-if="customer.company" class="muted">{{ customer.company }}</span>
                    <div class="customer-contacts">
                        <span v-if="customer.phone" dir="ltr"><i class="fas fa-phone"></i> {{ customer.phone }}</span>
                        <span v-if="customer.address"><i class="fas fa-location-dot"></i> {{ customer.address }}</span>
                    </div>
                </div>
                <!-- What they already owe, before this order is added to it. -->
                <dl class="customer-money">
                    <div>
                        <dt>{{ t('sof_owes_now') }}</dt>
                        <dd :class="{ 'is-due': order.creditCheck.value?.owes > 0 }">{{ formatCurrency(order.creditCheck.value?.owes || 0) }}</dd>
                    </div>
                    <div v-if="order.creditCheck.value?.limit > 0">
                        <dt>{{ t('sof_credit_limit') }}</dt>
                        <dd>{{ formatCurrency(order.creditCheck.value.limit) }}</dd>
                    </div>
                </dl>
            </div>
            <el-alert
                v-if="order.creditCheck.value?.over"
                type="warning"
                show-icon
                :closable="false"
                class="credit-alert"
                :title="t('sof_over_credit', { after: formatCurrency(order.creditCheck.value.after), limit: formatCurrency(order.creditCheck.value.limit) })"
            />
        </section>

        <!-- When and how it reaches them -->
        <section class="panel">
            <header class="panel-head">
                <h3><i class="fas fa-truck"></i> {{ t('shipping_schedule_and_addresses') }}</h3>
            </header>

            <div class="field">
                <label>{{ t('so_fulfillment_type') }}</label>
                <div class="segmented" role="radiogroup">
                    <button
                        v-for="option in fulfillmentOptions"
                        :key="option.value"
                        type="button"
                        role="radio"
                        :aria-checked="order.form.fulfillment_type === option.value"
                        :class="{ 'is-on': order.form.fulfillment_type === option.value }"
                        @click="order.form.fulfillment_type = option.value"
                    >
                        <i :class="option.icon"></i>
                        <span>{{ option.label }}</span>
                    </button>
                </div>
                <small v-if="order.form.fulfillment_type === 'pickup'" class="hint">{{ t('so_pickup_one_branch') }}</small>
            </div>

            <div class="grid-2">
                <div class="field">
                    <label>{{ t('order_date') }}</label>
                    <el-date-picker
                        v-model="order.form.order_date"
                        type="date"
                        format="YYYY-MM-DD"
                        value-format="YYYY-MM-DD"
                        :clearable="false"
                        class="w-full"
                    />
                </div>
                <div class="field">
                    <label>{{ t('expected_delivery') }} <span class="optional">{{ t('sof_optional') }}</span></label>
                    <el-date-picker
                        v-model="order.form.expected_delivery"
                        type="date"
                        format="YYYY-MM-DD"
                        value-format="YYYY-MM-DD"
                        class="w-full"
                        :class="{ 'is-invalid': deliveryInvalid }"
                        :placeholder="t('expected_delivery_date')"
                        :disabled-date="(d) => !!order.form.order_date && toISO(d) <= order.form.order_date"
                    />
                    <!-- The days people actually say: tomorrow, in three, in a week. -->
                    <div class="chips">
                        <button v-for="days in [1, 3, 7]" :key="days" type="button" class="chip" :class="{ 'is-on': order.form.expected_delivery === plusDays(days) }" @click="order.form.expected_delivery = plusDays(days)">
                            {{ t('sof_in_days', { n: days }) }}
                        </button>
                    </div>
                    <small v-if="deliveryInvalid" class="field-error">{{ t('sof_delivery_after_order') }}</small>
                </div>
            </div>

            <div v-if="order.form.fulfillment_type !== 'pickup'" class="field">
                <label>{{ t('delivery_and_shipping_address') }}</label>
                <el-input v-model="order.form.shipping_address" type="textarea" :rows="2" :placeholder="t('enter_shipping_details')" maxlength="500" />
                <button
                    v-if="customer?.address && order.form.shipping_address !== customer.address"
                    type="button"
                    class="link-btn"
                    @click="order.form.shipping_address = customer.address"
                >
                    <i class="fas fa-location-dot"></i> {{ t('sof_use_customer_address') }}
                </button>
            </div>
        </section>

        <!-- What the customer is charged beyond the goods -->
        <section class="panel">
            <header class="panel-head">
                <h3><i class="fas fa-percent"></i> {{ t('accounts_and_finance') }}</h3>
            </header>

            <div class="grid-3">
                <div class="field">
                    <label>{{ t('discount') }}</label>
                    <div class="money-field" :class="{ 'is-invalid': discountInvalid }">
                        <el-input-number v-model="order.form.discount" :min="0" :max="order.form.discount_mode === 'percent' ? 100 : undefined" :precision="2" :controls="false" class="w-full" />
                        <ModeSwitch v-model="order.form.discount_mode" />
                    </div>
                    <small v-if="order.form.discount_mode === 'percent' && order.discountAmount.value" class="hint">= {{ formatCurrency(order.discountAmount.value) }}</small>
                    <small v-if="discountInvalid" class="field-error">{{ t('sof_discount_too_big') }}</small>
                </div>
                <div class="field">
                    <label>{{ t('tax') }}</label>
                    <div class="money-field">
                        <el-input-number v-model="order.form.tax" :min="0" :precision="2" :controls="false" class="w-full" />
                        <ModeSwitch v-model="order.form.tax_mode" />
                    </div>
                    <small v-if="order.form.tax_mode === 'percent' && order.taxAmount.value" class="hint">= {{ formatCurrency(order.taxAmount.value) }}</small>
                </div>
                <div class="field">
                    <label>{{ t('sof_shipping_charge') }}</label>
                    <div class="money-field">
                        <el-input-number v-model="order.form.shipping_cost" :min="0" :precision="2" :controls="false" class="w-full" />
                        <span class="currency-tag">{{ currencyCode }}</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- A customer who is not on the books yet -->
        <el-dialog v-model="newCustomer.visible" :title="t('so_new_customer')" width="440px" append-to-body>
            <el-form label-position="top" @submit.prevent="saveNewCustomer">
                <el-form-item :label="t('name')" required>
                    <el-input v-model="newCustomer.name" autofocus />
                </el-form-item>
                <el-form-item :label="t('phone')">
                    <el-input v-model="newCustomer.phone" dir="ltr" />
                </el-form-item>
                <el-form-item :label="t('address_label')">
                    <el-input v-model="newCustomer.address" type="textarea" :rows="2" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="newCustomer.visible = false">{{ t('cancel') }}</el-button>
                <el-button type="primary" :loading="newCustomer.saving" :disabled="!newCustomer.name.trim()" @click="saveNewCustomer">{{ t('save') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { computed, defineComponent, h, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { posApi } from '@/api/pos';
import { formatCurrency } from '@/utils/sales';
import { baseCurrencyCode } from '@/utils/currency';
import { useSalesOrderForm } from '@/Composables/useSalesOrderForm';

const { t } = useI18n();
const order = useSalesOrderForm();
const currencyCode = computed(() => baseCurrencyCode());

/** Amount or rate, beside the field it changes. */
const ModeSwitch = defineComponent({
    props: { modelValue: { type: String, default: 'amount' } },
    emits: ['update:modelValue'],
    setup(props, { emit }) {
        return () => h('div', { class: 'mode-switch', role: 'radiogroup' }, [
            ['amount', currencyCode.value],
            ['percent', '%'],
        ].map(([value, label]) => h('button', {
            type: 'button',
            role: 'radio',
            'aria-checked': props.modelValue === value,
            class: { 'is-on': props.modelValue === value },
            onClick: () => emit('update:modelValue', value),
        }, label)));
    },
});

const customer = computed(() => order.form.customer);

/* Customers ------------------------------------------------------- */

const customerSelectRef = ref(null);
const customerResults = ref([]);
const customersLoading = ref(false);
let customerSeq = 0;

// The chosen customer stays in the list whatever the last search found, so
// the field never falls back to showing a bare id.
const customerOptions = computed(() => {
    const list = customerResults.value;
    return customer.value && !list.some((c) => c.id === customer.value.id) ? [customer.value, ...list] : list;
});

const searchCustomers = async (text) => {
    const mine = ++customerSeq;
    customersLoading.value = true;
    try {
        const { data } = await posApi.customers({ search: (text || '').trim() || undefined, per_page: 20 });
        if (mine === customerSeq) customerResults.value = data?.data?.customers || [];
    } catch {
        if (mine === customerSeq) customerResults.value = [];
    } finally {
        if (mine === customerSeq) customersLoading.value = false;
    }
};

const chooseCustomer = (id) => {
    order.setCustomer(customerOptions.value.find((c) => c.id === id) || null);
};

/* New customer ---------------------------------------------------- */

const newCustomer = reactive({ visible: false, saving: false, name: '', phone: '', address: '' });

const openNewCustomer = () => {
    Object.assign(newCustomer, { visible: true, saving: false, name: '', phone: '', address: '' });
};

const saveNewCustomer = async () => {
    if (!newCustomer.name.trim() || newCustomer.saving) return;
    newCustomer.saving = true;
    try {
        // The store endpoint matches on phone and overwrites whoever has it,
        // so a number already on the books selects that customer instead of
        // renaming them to what was typed here.
        const phone = newCustomer.phone.trim();
        if (phone) {
            const { data } = await posApi.customers({ search: phone, per_page: 5 });
            const existing = (data?.data?.customers || []).find((c) => c.phone === phone);
            if (existing) {
                order.setCustomer(existing);
                newCustomer.visible = false;
                ElMessage.info(t('so_customer_exists', { name: existing.name }));
                return;
            }
        }
        const { data } = await posApi.customerStore({
            name: newCustomer.name.trim(),
            phone: phone || null,
            address: newCustomer.address.trim() || null,
            status: 'active',
        });
        const created = data?.data?.customer || data?.data;
        if (created?.id) order.setCustomer(created);
        newCustomer.visible = false;
        ElMessage.success(t('so_customer_added'));
    } catch (error) {
        const errors = error.response?.data?.errors;
        ElMessage.error((errors && Object.values(errors).flat()[0]) || error.response?.data?.message || t('so_customer_add_failed'));
    } finally {
        newCustomer.saving = false;
    }
};

/* Delivery -------------------------------------------------------- */

const fulfillmentOptions = computed(() => [
    { value: 'ship', icon: 'fas fa-truck', label: t('so_fulfillment_ship') },
    { value: 'delivery', icon: 'fas fa-motorcycle', label: t('so_fulfillment_delivery') },
    { value: 'pickup', icon: 'fas fa-store', label: t('so_fulfillment_pickup') },
]);

const toISO = (date) => {
    const d = new Date(date);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};
const plusDays = (days) => {
    const base = order.form.order_date ? new Date(`${order.form.order_date}T00:00:00`) : new Date();
    base.setDate(base.getDate() + days);
    return toISO(base);
};

const deliveryInvalid = computed(() => !!order.form.expected_delivery && !!order.form.order_date && order.form.expected_delivery <= order.form.order_date);
const discountInvalid = computed(() => order.discountAmount.value > order.subtotal.value + order.taxAmount.value + order.shipping.value);

defineExpose({ focusCustomer: () => customerSelectRef.value?.focus?.() });
</script>

<style scoped>
.customer-step { display: flex; flex-direction: column; gap: 1rem; }

.panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem; }
.panel.has-error { border-color: #f87171; }
.panel-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 0.85rem; flex-wrap: wrap; }
.panel-head h3 { margin: 0; font-size: 0.98rem; font-weight: 700; color: #1e293b; display: flex; gap: 0.5rem; align-items: center; }
.panel-head h3 i { color: #64748b; }

.w-full { width: 100%; }
.muted { color: #64748b; font-size: 0.8rem; }
.field { display: flex; flex-direction: column; gap: 0.35rem; margin-bottom: 0.85rem; }
.field:last-child { margin-bottom: 0; }
.field > label { font-size: 0.8rem; font-weight: 600; color: #475569; }
.optional { font-weight: 400; color: #94a3b8; }
.hint { color: #64748b; font-size: 0.75rem; }
.field-error { margin: 0.35rem 0 0; color: #dc2626; font-size: 0.78rem; }
.is-invalid :deep(.el-input__wrapper), .money-field.is-invalid :deep(.el-input__wrapper) { box-shadow: 0 0 0 1px #ef4444 inset; }

.grid-2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 1rem; }
.grid-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0 1rem; }

.customer-option { display: flex; justify-content: space-between; gap: 1rem; }
.customer-option small { color: #94a3b8; }

.customer-card {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    margin-top: 0.85rem;
    padding: 0.85rem;
    border-radius: 10px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
}
.avatar {
    flex: none;
    display: grid;
    place-items: center;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: #dbeafe;
    color: #1d4ed8;
    font-weight: 700;
    font-size: 1.1rem;
}
.customer-info { display: flex; flex-direction: column; gap: 0.15rem; min-width: 0; flex: 1; }
.customer-info strong { color: #1e293b; }
.customer-contacts { display: flex; flex-wrap: wrap; gap: 0.25rem 1rem; font-size: 0.8rem; color: #475569; }
.customer-contacts i { color: #94a3b8; margin-inline-end: 0.2rem; }
.customer-money { display: flex; gap: 1.25rem; margin: 0; }
.customer-money div { display: flex; flex-direction: column; align-items: flex-end; }
.customer-money dt { font-size: 0.72rem; color: #64748b; }
.customer-money dd { margin: 0; font-weight: 700; color: #1e293b; font-variant-numeric: tabular-nums; }
.customer-money dd.is-due { color: #b45309; }
.credit-alert { margin-top: 0.75rem; }

.segmented { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.5rem; }
.segmented button {
    all: unset;
    box-sizing: border-box;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.6rem 0.5rem;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    color: #475569;
    font-size: 0.86rem;
    cursor: pointer;
    text-align: center;
}
.segmented button:hover { border-color: #93c5fd; }
.segmented button.is-on { border-color: #2563eb; background: #eff6ff; color: #1d4ed8; font-weight: 600; }
.segmented button:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }

.chips { display: flex; gap: 0.35rem; flex-wrap: wrap; }
.chip {
    all: unset;
    cursor: pointer;
    font-size: 0.75rem;
    padding: 0.05rem 0.6rem;
    border-radius: 999px;
    border: 1px solid #e2e8f0;
    color: #475569;
    line-height: 1.7;
}
.chip:hover { border-color: #93c5fd; color: #2563eb; }
.chip.is-on { background: #eff6ff; border-color: #2563eb; color: #2563eb; font-weight: 600; }
.chip:focus-visible, .link-btn:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }

.link-btn { all: unset; cursor: pointer; align-self: flex-start; font-size: 0.78rem; color: #2563eb; }
.link-btn:hover { text-decoration: underline; }

.money-field { display: flex; align-items: stretch; gap: 0.35rem; }
.money-field :deep(.el-input-number) { flex: 1; }
.money-field :deep(.el-input__inner) { text-align: end; font-variant-numeric: tabular-nums; }
.currency-tag {
    display: grid;
    place-items: center;
    padding: 0 0.6rem;
    border-radius: 6px;
    background: #f1f5f9;
    color: #64748b;
    font-size: 0.75rem;
}
:deep(.mode-switch) { display: flex; border: 1px solid #dcdfe6; border-radius: 6px; overflow: hidden; }
:deep(.mode-switch button) {
    all: unset;
    cursor: pointer;
    padding: 0 0.55rem;
    font-size: 0.75rem;
    color: #64748b;
    display: grid;
    place-items: center;
}
:deep(.mode-switch button.is-on) { background: #2563eb; color: #fff; }
:deep(.mode-switch button:focus-visible) { outline: 2px solid #2563eb; outline-offset: -2px; }

@media (max-width: 992px) {
    .grid-3 { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
    .panel { padding: 0.85rem; }
    .grid-2 { grid-template-columns: 1fr; }
    .customer-card { flex-wrap: wrap; }
    .customer-money { width: 100%; justify-content: flex-start; }
    .customer-money div { align-items: flex-start; }
    .segmented button span { font-size: 0.78rem; }
}
</style>
