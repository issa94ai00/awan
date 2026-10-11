<template>
    <!-- Not in the order yet: one click adds one. -->
    <button
        v-if="count <= 0"
        type="button"
        class="btn-add-product"
        :class="{ 'is-sm': size === 'sm' }"
        @click.stop.prevent="$emit('add', 1)"
    >
        <i class="fas fa-cart-plus"></i>
        <span>{{ label }}</span>
    </button>

    <!-- In the order: the count is typed or stepped in place; 0 removes the line. -->
    <div v-else class="item-stepper" :class="{ 'is-sm': size === 'sm' }" @click.stop>
        <button type="button" class="stepper-btn" :title="$t('decrease')" @click.stop.prevent="$emit('add', -1)">
            <i class="fas fa-minus"></i>
        </button>
        <input
            class="stepper-input"
            type="number"
            min="0"
            step="1"
            inputmode="numeric"
            dir="ltr"
            :value="count"
            :title="$t('quantity')"
            :aria-label="$t('quantity')"
            @focus="$event.target.select()"
            @input="onInput"
            @blur="onBlur"
            @keydown.enter.prevent="$event.target.blur()"
        />
        <button type="button" class="stepper-btn" :title="$t('increase')" @click.stop.prevent="$emit('add', 1)">
            <i class="fas fa-plus"></i>
        </button>
    </div>
</template>

<script setup>
/**
 * The add button / quantity stepper on a product row of the order form's
 * search panel. The count is an input rather than a label so an order for
 * fifty of something is typed, not clicked in.
 */
const props = defineProps({
    count: { type: Number, default: 0 },
    label: { type: String, default: '' },
    size: { type: String, default: 'md' },
});

const emit = defineEmits(['add', 'set', 'commit']);

const toInt = (raw) => {
    const n = Math.floor(Number(raw));
    return Number.isFinite(n) ? n : NaN;
};

// While typing, only a usable number is pushed; an emptied field waits for blur.
const onInput = (event) => {
    const n = toInt(event.target.value);
    if (n >= 1) emit('set', n);
};

const onBlur = (event) => {
    const n = toInt(event.target.value);
    const next = n >= 0 ? n : props.count;
    emit('commit', next);
    if (next >= 1 && String(event.target.value) !== String(next)) event.target.value = next;
};
</script>

<style scoped>
.btn-add-product {
    all: unset;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: #2563eb;
    color: #ffffff;
    padding: 0.45rem 0.85rem;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 700;
    transition: all 0.15s ease;
    white-space: nowrap;
    box-shadow: 0 1px 2px rgba(37, 99, 235, 0.2);
}

.btn-add-product:hover {
    background: #1d4ed8;
    transform: translateY(-1px);
    box-shadow: 0 2px 5px rgba(37, 99, 235, 0.3);
}

.btn-add-product.is-sm {
    padding: 0.3rem 0.6rem;
    font-size: 0.74rem;
}

.item-stepper {
    display: inline-flex;
    align-items: center;
    background: #ffffff;
    border: 1px solid #16a34a;
    border-radius: 6px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(22, 163, 74, 0.15);
}

.stepper-btn {
    all: unset;
    cursor: pointer;
    padding: 0.35rem 0.65rem;
    font-size: 0.75rem;
    color: #16a34a;
    transition: all 0.15s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.stepper-btn:hover {
    background: #dcfce7;
}

.stepper-input {
    all: unset;
    box-sizing: border-box;
    width: 48px;
    height: 28px;
    padding: 0 0.25rem;
    font-size: 0.84rem;
    font-weight: 700;
    color: #15803d;
    text-align: center;
    background: #ffffff;
    -moz-appearance: textfield;
    appearance: textfield;
}

.stepper-input::-webkit-outer-spin-button,
.stepper-input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.stepper-input:focus {
    background: #fffbeb;
    box-shadow: inset 0 0 0 1px #fbbf24;
}

.item-stepper.is-sm .stepper-btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.7rem;
}

.item-stepper.is-sm .stepper-input {
    width: 42px;
    height: 24px;
    font-size: 0.78rem;
}
</style>
