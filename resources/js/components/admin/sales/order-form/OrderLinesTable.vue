<template>
    <section class="lines-card" :class="{ 'has-error': showError }">
        <header class="lines-head">
            <h3><i class="fas fa-cart-shopping"></i> {{ t('selected_order_items') }}</h3>
            <span v-if="order.lines.value.length" class="lines-count">{{ t('items_count_label', { count: order.lines.value.length }) }}</span>
        </header>

        <div v-if="!order.lines.value.length" class="lines-empty">
            <i class="fas fa-barcode"></i>
            <div>
                <strong>{{ t('no_products_added_yet') }}</strong>
                <span>{{ t('sof_lines_empty_hint') }}</span>
            </div>
        </div>

        <div v-else class="lines" role="table">
            <div class="line line--head" role="row">
                <span role="columnheader">{{ t('product_item') }}</span>
                <span role="columnheader">{{ t('unity') }}</span>
                <span role="columnheader" class="center">{{ t('quantity') }}</span>
                <span role="columnheader">{{ t('unit_price') }}</span>
                <span role="columnheader" class="num">{{ t('grand_total') }}</span>
                <span role="columnheader"></span>
            </div>

            <div
                v-for="line in order.lines.value"
                :key="line.key"
                class="line"
                :class="{ 'just-added': flashKey === line.key }"
                role="row"
            >
                <div class="line-product" role="cell">
                    <span class="line-thumb">
                        <img v-if="line.image" :src="getImageUrl(line.image)" alt="" loading="lazy" />
                        <i v-else class="fas fa-box"></i>
                    </span>
                    <div class="line-product-text">
                        <strong :title="line.name">{{ line.name }}</strong>
                        <span class="line-sub">
                            <VariantChip v-if="line.product_variant_id" :label="line.variant_label" />
                            <span v-if="line.sku" class="mono">{{ line.sku }}</span>
                        </span>
                    </div>
                </div>

                <div class="line-field" role="cell">
                    <label class="mobile-label">{{ t('unity') }}</label>
                    <el-select
                        :model-value="line.unit"
                        value-key="id"
                        size="default"
                        :disabled="line.units.length < 2"
                        @update:model-value="(unit) => order.setUnit(line, unit)"
                    >
                        <el-option v-for="unit in line.units" :key="unit.id ?? unit.name" :value="unit" :label="unitLabel(unit)" />
                    </el-select>
                </div>

                <div class="line-field" role="cell">
                    <label class="mobile-label">{{ t('quantity') }}</label>
                    <div class="stepper" :class="{ 'is-invalid': !validQty(line) }">
                        <button type="button" :aria-label="t('sof_less')" :disabled="line.quantity <= 1" @click="line.quantity = Math.max(1, Number(line.quantity) - 1)">
                            <i class="fas fa-minus"></i>
                        </button>
                        <input
                            v-model.number="line.quantity"
                            type="number"
                            inputmode="numeric"
                            min="1"
                            step="1"
                            :aria-label="t('quantity')"
                            @focus="$event.target.select()"
                            @blur="tidyQty(line)"
                        />
                        <button type="button" :aria-label="t('sof_more')" @click="line.quantity = (Number(line.quantity) || 0) + 1">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <!-- Allowed (the routing step and confirmation deal with the
                         shortage), but said here, counted in pieces. -->
                    <span v-if="order.overStock(line)" class="line-note is-warn">
                        {{ line.stock > 0 ? t('so_qty_over_stock', { n: line.stock }) : t('out_of_stock') }}
                    </span>
                    <span v-else-if="line.unit?.base_unit_multiplier > 1" class="line-note">
                        {{ t('pieces_count', { count: order.linePieces(line) }) }}
                    </span>
                </div>

                <div class="line-field" role="cell">
                    <label class="mobile-label">{{ t('unit_price') }}</label>
                    <el-input-number
                        v-model="line.price"
                        :min="0"
                        :precision="2"
                        :step="1"
                        :controls="false"
                        class="price-input"
                        :class="{ 'is-warn': order.belowCost(line) }"
                    />
                    <button
                        v-if="order.priceEdited(line)"
                        type="button"
                        class="line-note list-price"
                        :title="t('so_reset_list_price')"
                        @click="line.price = order.listPrice(line)"
                    >
                        <i class="fas fa-rotate-left"></i> {{ formatCurrency(order.listPrice(line)) }}
                    </button>
                    <span v-if="order.belowCost(line)" class="line-note is-warn">
                        {{ t('sof_below_cost', { cost: formatCurrency(order.unitCost(line)) }) }}
                    </span>
                </div>

                <div class="line-total num" role="cell">
                    <label class="mobile-label">{{ t('grand_total') }}</label>
                    {{ formatCurrency(order.lineTotal(line)) }}
                </div>

                <div class="line-remove" role="cell">
                    <button type="button" class="icon-btn" :aria-label="t('delete')" :title="t('delete')" @click="remove(line)">
                        <i class="fas fa-trash-can"></i>
                    </button>
                </div>
            </div>

            <footer class="line line--foot">
                <span>{{ t('pieces_count', { count: order.pieces.value }) }}</span>
                <span class="num">{{ t('items_subtotal_amount') }}: <strong>{{ formatCurrency(order.subtotal.value) }}</strong></span>
            </footer>
        </div>

        <!-- A removed line can be put back for a few seconds: removal is one
             click with no question, so a slip costs nothing. -->
        <Transition name="undo">
            <div v-if="removed" class="undo-bar">
                <span>{{ t('sof_line_removed', { name: removed.line.name }) }}</span>
                <button type="button" @click="undo">{{ t('sof_undo') }}</button>
            </div>
        </Transition>
    </section>
</template>

<script setup>
import { computed, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { getImageUrl } from '@/utils/imageUrl';
import { formatCurrency } from '@/utils/sales';
import VariantChip from '@/components/admin/products/VariantChip.vue';
import { useSalesOrderForm } from '@/Composables/useSalesOrderForm';

defineProps({
    flashKey: { type: String, default: null },
});

const { t } = useI18n();
const order = useSalesOrderForm();

const showError = computed(() => order.attempted[0] && !order.lines.value.length);

const unitLabel = (unit) => {
    const name = unit.name_ar || unit.name;
    return unit.base_unit_multiplier > 1 ? `${name} (${unit.base_unit_multiplier})` : name;
};

const validQty = (line) => Number.isInteger(Number(line.quantity)) && Number(line.quantity) >= 1;
const tidyQty = (line) => {
    const n = Math.floor(Number(line.quantity));
    line.quantity = Number.isFinite(n) && n >= 1 ? n : 1;
};

const removed = ref(null);
let undoTimer = null;

const remove = (line) => {
    const index = order.lines.value.indexOf(line);
    order.removeLine(line);
    removed.value = { line, index };
    clearTimeout(undoTimer);
    undoTimer = setTimeout(() => { removed.value = null; }, 6000);
};

const undo = () => {
    if (!removed.value) return;
    const { line, index } = removed.value;
    removed.value = null;
    // Picked again since: the quantities join on the one line.
    const again = order.lines.value.find((l) => l.key === line.key);
    if (again) {
        again.quantity = Number(again.quantity) + Number(line.quantity);
        return;
    }
    const next = [...order.lines.value];
    next.splice(Math.min(index, next.length), 0, line);
    order.lines.value = next;
    removed.value = null;
};

onUnmounted(() => clearTimeout(undoTimer));
</script>

<style scoped>
.lines-card {
    position: relative;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1rem;
}
.lines-card.has-error { border-color: #f87171; }

.lines-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; }
.lines-head h3 { margin: 0; font-size: 0.98rem; font-weight: 700; color: #1e293b; display: flex; gap: 0.5rem; align-items: center; }
.lines-head h3 i { color: #64748b; }
.lines-count { font-size: 0.78rem; color: #2563eb; background: #eff6ff; border-radius: 999px; padding: 0.1rem 0.6rem; }

.lines-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.9rem;
    padding: 2rem 1rem;
    border: 1px dashed #cbd5e1;
    border-radius: 10px;
    color: #64748b;
}
.lines-empty > i { font-size: 1.8rem; color: #94a3b8; }
.lines-empty div { display: flex; flex-direction: column; gap: 0.2rem; font-size: 0.86rem; }
.lines-empty strong { color: #334155; font-size: 0.92rem; }
.has-error .lines-empty { border-color: #f87171; color: #b91c1c; }

.lines { border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; }
.line {
    display: grid;
    grid-template-columns: minmax(180px, 1fr) 112px 120px 112px 100px 32px;
    gap: 0.6rem;
    align-items: start;
    padding: 0.65rem 0.85rem;
    border-bottom: 1px solid #f1f5f9;
}
.line > * { min-width: 0; }
.line--head {
    align-items: center;
    background: #f8fafc;
    font-size: 0.75rem;
    font-weight: 600;
    color: #64748b;
    padding-block: 0.5rem;
}
.line--foot {
    display: flex;
    justify-content: space-between;
    background: #f8fafc;
    border-bottom: none;
    font-size: 0.82rem;
    color: #64748b;
}
.line--foot strong { color: #1e293b; }
.line.just-added { animation: line-flash 1.4s ease-out; }
@keyframes line-flash {
    0%, 35% { background-color: #dbeafe; }
    100% { background-color: transparent; }
}

.center { text-align: center; }
.num { text-align: end; font-variant-numeric: tabular-nums; }
.mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; direction: ltr; }

.line-product { display: flex; gap: 0.65rem; align-items: center; }
.line-thumb {
    flex: none;
    display: grid;
    place-items: center;
    width: 40px;
    height: 40px;
    border-radius: 8px;
    background: #f1f5f9;
    color: #94a3b8;
    overflow: hidden;
}
.line-thumb img { width: 100%; height: 100%; object-fit: cover; }
.line-product-text { display: flex; flex-direction: column; gap: 0.2rem; min-width: 0; }
/* Two lines of a name before it is cut, not a third of one. */
.line-product-text strong {
    font-size: 0.88rem;
    color: #1e293b;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.line-sub { display: flex; gap: 0.4rem; align-items: center; flex-wrap: wrap; font-size: 0.74rem; color: #94a3b8; }

.line-field { display: flex; flex-direction: column; gap: 0.25rem; }
.line-field :deep(.el-select), .price-input { width: 100%; }
.price-input :deep(.el-input__inner) { text-align: end; font-variant-numeric: tabular-nums; }
.price-input.is-warn :deep(.el-input__wrapper) { box-shadow: 0 0 0 1px #f59e0b inset; }

.stepper {
    display: grid;
    grid-template-columns: 32px minmax(0, 1fr) 32px;
    height: 32px;
    border: 1px solid #dcdfe6;
    border-radius: 6px;
    overflow: hidden;
    background: #fff;
}
.stepper:focus-within { border-color: #2563eb; }
.stepper.is-invalid { border-color: #ef4444; }
.stepper button {
    all: unset;
    display: grid;
    place-items: center;
    cursor: pointer;
    color: #475569;
    background: #f8fafc;
    font-size: 0.7rem;
}
.stepper button:hover:not(:disabled) { background: #eff6ff; color: #2563eb; }
.stepper button:disabled { color: #cbd5e1; cursor: not-allowed; }
.stepper input {
    width: 100%;
    min-width: 0;
    border: 0;
    border-inline: 1px solid #e2e8f0;
    text-align: center;
    font: inherit;
    font-weight: 600;
    font-size: 0.9rem;
    color: #1e293b;
    font-variant-numeric: tabular-nums;
    -moz-appearance: textfield;
}
.stepper input:focus { outline: none; }
.stepper input::-webkit-outer-spin-button,
.stepper input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }

.line-note { font-size: 0.72rem; color: #64748b; line-height: 1.35; }
.line-note.is-warn { color: #b45309; }
.list-price {
    all: unset;
    cursor: pointer;
    font-size: 0.72rem;
    color: #94a3b8;
    text-decoration: line-through;
}
.list-price:hover { color: #2563eb; text-decoration: none; }

.line-total { font-weight: 700; color: #1e293b; padding-top: 0.35rem; }
.line-remove { padding-top: 0.15rem; }
.icon-btn {
    all: unset;
    display: grid;
    place-items: center;
    width: 30px;
    height: 30px;
    border-radius: 6px;
    color: #94a3b8;
    cursor: pointer;
}
.icon-btn:hover { background: #fef2f2; color: #dc2626; }
.icon-btn:focus-visible, .list-price:focus-visible, .stepper button:focus-visible { outline: 2px solid #2563eb; outline-offset: 1px; }

.mobile-label { display: none; }

.undo-bar {
    position: absolute;
    inset-inline: 1rem;
    bottom: 1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.6rem 0.9rem;
    border-radius: 8px;
    background: #1e293b;
    color: #fff;
    font-size: 0.85rem;
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.25);
}
.undo-bar button { all: unset; cursor: pointer; font-weight: 700; color: #93c5fd; }
.undo-enter-active, .undo-leave-active { transition: opacity 0.2s, transform 0.2s; }
.undo-enter-from, .undo-leave-to { opacity: 0; transform: translateY(6px); }

@media (max-width: 1200px) {
    .line { grid-template-columns: minmax(150px, 1fr) 100px 112px 100px 92px 30px; gap: 0.5rem; padding-inline: 0.6rem; }
}

/* A phone: each line is a card, its fields labelled two to a row. */
@media (max-width: 768px) {
    .lines-card { padding: 0.75rem; }
    .lines { border: 0; border-radius: 0; }
    .line--head { display: none; }
    .line {
        grid-template-columns: 1fr 1fr;
        gap: 0.6rem 0.75rem;
        padding: 0.75rem;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        margin-bottom: 0.6rem;
    }
    .line-product { grid-column: 1 / -1; padding-inline-end: 2rem; }
    .line-remove { position: absolute; inset-inline-end: 0.5rem; top: 0.5rem; padding: 0; }
    .line { position: relative; }
    .line-total { display: flex; flex-direction: column; justify-content: flex-end; text-align: start; padding-top: 0; }
    .mobile-label { display: block; font-size: 0.72rem; color: #64748b; font-weight: 600; }
    .line--foot { border: 0; border-radius: 8px; margin: 0; }
}
</style>
