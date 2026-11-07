<template>
    <div class="pdisc">
        <div class="pdisc-head">
            <span class="pdisc-label"><i class="fas fa-tag"></i> {{ $t('discount') }}</span>
            <div v-if="!disabled" class="pdisc-mode" role="radiogroup" :aria-label="$t('discount')">
                <button type="button" :class="{ 'is-active': form.discount_mode === 'percent' }" @click="ctl.setMode('percent')">%</button>
                <button type="button" :class="{ 'is-active': form.discount_mode === 'amount' }" @click="ctl.setMode('amount')">{{ currency }}</button>
            </div>
        </div>
        <div class="pdisc-input" :class="{ 'is-invalid': invalid }">
            <input
                v-if="form.discount_mode === 'percent'"
                v-model="form.discount_percent"
                type="number"
                inputmode="decimal"
                min="0"
                max="100"
                step="any"
                dir="ltr"
                placeholder="0"
                :disabled="disabled"
                @input="ctl.fromPercent"
            />
            <input
                v-else
                v-model="form.discount"
                type="number"
                inputmode="decimal"
                min="0"
                step="0.01"
                dir="ltr"
                placeholder="0.00"
                :disabled="disabled"
                @input="ctl.fromAmount"
            />
            <span class="pdisc-unit">{{ form.discount_mode === 'percent' ? '%' : currency }}</span>
        </div>
        <div v-if="!disabled" class="pdisc-foot">
            <div class="pdisc-presets">
                <button
                    v-for="p in presets"
                    :key="p"
                    type="button"
                    :class="{ 'is-active': form.discount_mode === 'percent' && num(form.discount_percent) === p }"
                    @click="ctl.applyPreset(p)"
                >{{ p }}%</button>
            </div>
            <span v-if="num(form.discount) > 0" class="pdisc-live">
                <template v-if="form.discount_mode === 'percent'">− {{ money(form.discount) }}</template>
                <template v-else-if="num(form.discount_percent) > 0">≈ {{ form.discount_percent }}%</template>
            </span>
        </div>
        <small v-if="invalid && invalidText" class="pdisc-error">{{ invalidText }}</small>
    </div>
</template>

<script setup>
import { formatMoney } from '@/utils/currency';

defineProps({
    // The document form (discount, discount_percent, discount_mode).
    form: { type: Object, required: true },
    // useDocumentDiscount() for that form.
    ctl: { type: Object, required: true },
    currency: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    invalid: { type: Boolean, default: false },
    invalidText: { type: String, default: '' },
    presets: { type: Array, default: () => [5, 10, 15, 20] },
});

const num = (v) => {
    const n = parseFloat(v);
    return Number.isFinite(n) ? n : 0;
};
const money = (v) => formatMoney(v);
</script>

<style scoped>
.pdisc {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.pdisc-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.pdisc-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--el-text-color-regular);
    display: inline-flex;
    gap: 0.35rem;
    align-items: center;
}

.pdisc-label i {
    color: var(--el-color-primary);
    font-size: 0.75rem;
}

.pdisc-mode {
    display: inline-flex;
    padding: 2px;
    background: var(--el-fill-color, #f0f2f5);
    border-radius: 7px;
}

.pdisc-mode button {
    all: unset;
    cursor: pointer;
    min-width: 30px;
    text-align: center;
    padding: 0.1rem 0.45rem;
    font-size: 0.75rem;
    font-weight: 700;
    border-radius: 5px;
    color: var(--el-text-color-secondary);
}

.pdisc-mode button.is-active {
    background: #fff;
    color: var(--el-color-primary);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
}

.pdisc-input {
    display: flex;
    align-items: center;
    height: 38px;
    padding-inline: 0.7rem;
    border: 1.5px solid var(--el-border-color, #dcdfe6);
    border-radius: 9px;
    background: #fff;
}

.pdisc-input:focus-within {
    border-color: var(--el-color-primary);
}

.pdisc-input.is-invalid {
    border-color: #ef4444;
    background: #fef2f2;
}

.pdisc-input input {
    all: unset;
    flex: 1;
    min-width: 0;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    -moz-appearance: textfield;
}

.pdisc-input input::-webkit-outer-spin-button,
.pdisc-input input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.pdisc-unit {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--el-text-color-secondary);
}

.pdisc-foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.pdisc-presets {
    display: flex;
    gap: 0.3rem;
    flex-wrap: wrap;
}

.pdisc-presets button {
    all: unset;
    cursor: pointer;
    font-size: 0.72rem;
    font-weight: 600;
    padding: 0.1rem 0.5rem;
    border-radius: 999px;
    border: 1px solid var(--el-border-color, #dcdfe6);
    color: var(--el-text-color-regular);
}

.pdisc-presets button:hover {
    border-color: var(--el-color-primary);
    color: var(--el-color-primary);
}

.pdisc-presets button.is-active {
    background: var(--el-color-primary);
    border-color: var(--el-color-primary);
    color: #fff;
}

.pdisc-live {
    font-size: 0.78rem;
    font-weight: 700;
    color: #b91c1c;
    font-variant-numeric: tabular-nums;
}

.pdisc-error {
    color: #b91c1c;
    font-size: 0.75rem;
}
</style>
