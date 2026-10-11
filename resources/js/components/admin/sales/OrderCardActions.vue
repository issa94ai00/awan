<template>
    <!-- The controls on a product row of the sales order's search panel. The
         parent owns the quantities; this only draws them and reports clicks. -->
    <div class="card-actions" :class="{ 'is-compact': compact }">
        <!-- Not in the order yet: pick a quantity, then add in one click -->
        <div v-if="count <= 0" class="qty-add-group" @click.stop>
            <div class="item-stepper is-pending" :class="{ 'is-over': overPending }">
                <button
                    type="button"
                    class="stepper-btn stepper-minus"
                    :title="$t('decrease')"
                    :disabled="pending <= 1"
                    @click.stop.prevent="$emit('bump-pending', -1)"
                >
                    <i class="fas fa-minus"></i>
                </button>
                <input
                    class="stepper-input"
                    type="number"
                    min="1"
                    step="1"
                    inputmode="numeric"
                    dir="ltr"
                    :value="pending"
                    :title="$t('quantity')"
                    :aria-label="$t('quantity')"
                    @focus="$event.target.select()"
                    @input="$emit('set-pending', $event.target.value)"
                    @blur="$emit('blur-pending', $event.target)"
                    @keydown.enter.prevent="$emit('add', pending)"
                />
                <button
                    type="button"
                    class="stepper-btn stepper-plus"
                    :title="$t('increase')"
                    @click.stop.prevent="$emit('bump-pending', 1)"
                >
                    <i class="fas fa-plus"></i>
                </button>
            </div>
            <button type="button" class="btn-add-product" @click.stop.prevent="$emit('add', pending)">
                <i class="fas fa-plus"></i>
                <span>{{ addLabel }}</span>
                <span v-if="pending > 1" class="qty-chip" dir="ltr">×{{ pending }}</span>
            </button>
        </div>

        <!-- Already in the order: the count is typed directly; 0 removes the line -->
        <div v-else class="item-stepper" :class="{ 'is-over': overCount }" @click.stop>
            <button type="button" class="stepper-btn stepper-minus" :title="$t('decrease')" @click.stop.prevent="$emit('decrement')">
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
                @input="$emit('set', $event.target.value)"
                @blur="$emit('commit', $event.target)"
                @keydown.enter.prevent="$event.target.blur()"
            />
            <button type="button" class="stepper-btn stepper-plus" :title="$t('increase')" @click.stop.prevent="$emit('add', 1)">
                <i class="fas fa-plus"></i>
            </button>
        </div>

        <!-- Raise a purchase for it; loud when there is none on the shelf -->
        <button
            v-if="showPurchase"
            type="button"
            class="btn-add-purchase"
            :class="{ 'is-highlight': purchaseHighlight }"
            :title="purchaseLabel"
            @click.stop.prevent="$emit('purchase')"
        >
            <i class="fas fa-cart-flatbed"></i>
            <span v-if="!compact">{{ purchaseLabel }}</span>
        </button>
    </div>
</template>

<script setup>
defineProps({
    /** Pieces of this row already on the order; 0 shows the add controls. */
    count: { type: Number, default: 0 },
    /** Quantity waiting to be added, shown while the row is not on the order. */
    pending: { type: Number, default: 1 },
    overPending: { type: Boolean, default: false },
    overCount: { type: Boolean, default: false },
    /** Tighter controls for a size tile inside a product card. */
    compact: { type: Boolean, default: false },
    addLabel: { type: String, default: '' },
    purchaseLabel: { type: String, default: '' },
    purchaseHighlight: { type: Boolean, default: false },
    showPurchase: { type: Boolean, default: true },
});

defineEmits(['add', 'bump-pending', 'set-pending', 'blur-pending', 'decrement', 'set', 'commit', 'purchase']);
</script>

<style scoped>
.card-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

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
    font-weight: 600;
    transition: all 0.15s ease;
    box-shadow: 0 1px 2px rgba(37, 99, 235, 0.2);
    white-space: nowrap;
}

.btn-add-product:hover {
    background: #1d4ed8;
}

.btn-add-product:active {
    transform: scale(0.98);
}

.item-stepper {
    display: inline-flex;
    align-items: center;
    border: 1px solid #93c5fd;
    background: #eff6ff;
    border-radius: 6px;
    overflow: hidden;
}

.stepper-btn {
    all: unset;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    color: #1d4ed8;
    font-size: 0.78rem;
    transition: background 0.15s ease;
}

.stepper-btn:hover {
    background: #dbeafe;
}

.stepper-minus {
    border-inline-end: 1px solid #bfdbfe;
}

.stepper-plus {
    border-inline-start: 1px solid #bfdbfe;
}

.stepper-btn:disabled {
    cursor: default;
    color: #94a3b8;
    background: transparent;
}

/* The count is an input, not a label: type the quantity instead of clicking it in */
.stepper-input {
    all: unset;
    box-sizing: border-box;
    width: 46px;
    height: 28px;
    padding: 0 0.25rem;
    font-size: 0.86rem;
    font-weight: 700;
    color: #1e3a8a;
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

.item-stepper.is-pending {
    border-color: #cbd5e1;
    background: #f8fafc;
}

.item-stepper.is-pending .stepper-btn {
    color: #475569;
}

.item-stepper.is-pending .stepper-btn:hover:not(:disabled) {
    background: #e2e8f0;
}

.item-stepper.is-pending .stepper-input {
    color: #0f172a;
}

.item-stepper.is-over {
    border-color: #fca5a5;
}

.item-stepper.is-over .stepper-input {
    color: #b91c1c;
}

.qty-add-group {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.qty-chip {
    display: inline-block;
    padding: 0 0.35rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.22);
    font-size: 0.72rem;
    font-weight: 700;
    line-height: 1.5;
}

.btn-add-purchase {
    all: unset;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #cbd5e1;
    padding: 0.45rem 0.75rem;
    border-radius: 6px;
    font-size: 0.78rem;
    font-weight: 600;
    transition: all 0.15s ease;
    white-space: nowrap;
}

.btn-add-purchase:hover {
    background: #e0f2fe;
    border-color: #0284c7;
    color: #0369a1;
}

.btn-add-purchase.is-highlight {
    background: #fef2f2;
    border-color: #fca5a5;
    color: #dc2626;
}

.btn-add-purchase.is-highlight:hover {
    background: #fee2e2;
    border-color: #ef4444;
    color: #b91c1c;
}

/* Compact: a size tile's row */
.is-compact {
    gap: 0.35rem;
}

.is-compact .btn-add-product {
    padding: 0.3rem 0.6rem;
    font-size: 0.74rem;
}

.is-compact .stepper-btn {
    width: 24px;
    height: 24px;
    font-size: 0.7rem;
}

.is-compact .stepper-input {
    width: 42px;
    height: 24px;
    font-size: 0.8rem;
}

.is-compact .btn-add-purchase {
    padding: 0.3rem 0.5rem;
}
</style>
