<template>
    <table class="offer-table">
        <colgroup>
            <col style="width:180px;">
            <col style="width:320px;">
            <col style="width:120px;">
            <col style="width:120px;">
            <col style="width:100px;">
        </colgroup>
        <thead>
            <tr>
                <th>{{ $t('image') }}</th>
                <th>{{ $t('product') }}</th>
                <th>{{ $t('details') }}</th>
                <th>{{ $t('the_price') }}</th>
                <th>{{ $t('inventory') }}</th>
            </tr>
        </thead>
        <tbody>
            <template v-for="(group, gIdx) in groups" :key="group.key">
                <tr
                    v-for="(item, iIdx) in group.items"
                    :key="item.id"
                    :class="{ first: iIdx === 0 }"
                    :style="{ background: gIdx % 2 === 0 ? '#ffffff' : '#f8fafc' }"
                    :id="!printMode && iIdx === 0 ? `item-${group.product.id}` : undefined"
                >
                    <td v-if="iIdx === 0" :rowspan="group.items.length" class="cell-image">
                        <div class="cell-image-inner">
                            <EntityImage
                                :src="group.product.image_main"
                                type="product"
                                :size="160"
                                shape="square"
                                :lazy="!printMode"
                                :preview-src-list="printMode ? [] : getPreviewList(group.product)"
                            />
                            <span class="cell-image-name">
                                {{ group.product.name_ar || group.product.name_en }}
                            </span>
                        </div>
                    </td>
                    <td v-if="iIdx === 0" :rowspan="group.items.length" class="cell-product">
                        <div class="cell-product-inner">
                            <div class="cell-product-name">{{ group.product.name_ar || group.product.name_en }}</div>
                            <div v-if="group.product.name_en && group.product.name_ar" class="cell-product-name-en">
                                {{ group.product.name_en }}
                            </div>
                            <div v-if="group.product.sku" class="cell-product-sku">SKU: {{ group.product.sku }}</div>
                            <div v-if="group.product.brand" class="cell-product-brand">{{ group.product.brand }}</div>
                        </div>
                    </td>
                    <td class="cell-detail">
                        <div v-if="item.size" class="detail-size">{{ item.size }}</div>
                        <div v-if="item.color" class="detail-color">{{ item.color }}</div>
                        <div v-if="item.unit" class="detail-unit">{{ item.unit }}</div>
                        <div v-if="!item.size && !item.color && !item.unit" class="detail-na">&mdash;</div>
                    </td>
                    <td
                        class="cell-price"
                        :class="{ editing: !printMode && editingId === item.id }"
                        @dblclick="!printMode && $emit('start-edit', item)"
                    >
                        <input
                            v-if="!printMode && editingId === item.id"
                            v-model="localEditValue"
                            type="text"
                            inputmode="decimal"
                            class="price-edit-input"
                            autocomplete="off"
                            @keydown.enter.prevent="$emit('commit-edit', localEditValue)"
                            @keydown.escape.prevent="$emit('cancel-edit')"
                            @blur="$emit('commit-edit', localEditValue)"
                        />
                        <template v-else>
                            <span class="price-value">{{ formatPrice(item.displayPrice) }}</span>
                            <span v-if="item.originalPrice !== item.displayPrice" class="price-original">
                                {{ formatPrice(item.originalPrice) }}
                            </span>
                        </template>
                    </td>
                    <td class="cell-stock">{{ item.stock_quantity ?? 0 }}</td>
                </tr>
            </template>
            <tr v-if="!loading && groups.length === 0">
                <td colspan="5" class="empty-cell">
                    <el-empty :description="$t('there_are_no_products')" />
                </td>
            </tr>
        </tbody>
    </table>
</template>

<script setup>
import EntityImage from '@/components/admin/EntityImage.vue';
import { ref, watch } from 'vue';

const props = defineProps({
    groups: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    editingId: { type: [String, Number, null], default: null },
    editValue: { type: String, default: '' },
    printMode: { type: Boolean, default: false },
});

defineEmits(['start-edit', 'commit-edit', 'cancel-edit']);

const localEditValue = ref(props.editValue);
watch(() => props.editValue, (v) => { localEditValue.value = v; });

const getPreviewList = (product) => {
    const list = [];
    if (product.image_main) list.push(product.image_main);
    if (product.image_gallery) {
        const gallery = Array.isArray(product.image_gallery)
            ? product.image_gallery
            : (() => { try { return JSON.parse(product.image_gallery); } catch { return []; } })();
        list.push(...gallery);
    }
    return list;
};

const formatPrice = (price) => {
    if (price === null || price === undefined) return '—';
    return Number(price).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
</script>

<style scoped>
.offer-table {
    border-collapse: collapse;
    width: 100%;
    font-size: 10pt;
    min-width: 840px;
}
.offer-table th,
.offer-table td {
    border: 1px solid #cbd5e1;
}
.offer-table thead th {
    position: sticky;
    top: 0;
    z-index: 5;
    background: #293344;
    color: #fff;
    padding: 10px 12px;
    font-weight: 700;
    text-align: center;
}
.offer-table tbody tr:hover td {
    background: #e2e9f2 !important;
}

.cell-image {
    text-align: center;
    vertical-align: middle;
    padding: 10px;
}
.cell-image-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}
.cell-image-name {
    display: block;
    font-weight: 600;
    color: #111c2c;
    font-size: 10pt;
    line-height: 1.3;
    direction: rtl;
    text-align: center;
    white-space: normal;
    word-break: break-word;
    max-width: 200px;
}
.cell-product {
    vertical-align: top;
    padding: 10px 12px;
}
.cell-product-inner {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.cell-product-name {
    font-weight: 700;
    color: #111c2c;
    font-size: 11pt;
    line-height: 1.4;
    direction: rtl;
    text-align: right;
}
.cell-product-name-en {
    font-weight: 500;
    color: #475569;
    font-size: 9.5pt;
    line-height: 1.3;
}
.cell-product-sku {
    font-size: 8.5pt;
    color: #94a3b8;
}
.cell-product-brand {
    font-size: 8.5pt;
    color: #64748b;
    font-weight: 600;
}

.cell-detail {
    color: #334155;
    font-size: 9.5pt;
    font-weight: 700;
    white-space: pre-wrap;
    padding: 5px 8px;
    text-align: center;
    vertical-align: middle;
}
.detail-size { color: #1e293b; }
.detail-color { color: #6366f1; font-size: 8.5pt; }
.detail-unit { color: #0f766e; font-size: 8.5pt; }
.detail-na { color: #94a3b8; }

.cell-price {
    text-align: center;
    font-weight: 700;
    color: #b00e0e;
    white-space: nowrap;
    padding: 5px 8px;
    position: relative;
    cursor: pointer;
    vertical-align: middle;
}
.cell-price:hover:not(.editing)::after {
    content: '✎';
    position: absolute;
    top: 1px;
    right: 3px;
    font-size: 10px;
    color: #2563eb;
    line-height: 1;
    pointer-events: none;
}
.cell-price.editing {
    padding: 0;
    overflow: visible;
}
.price-value {
    font-size: 11pt;
}
.price-original {
    display: block;
    font-size: 8pt;
    color: #94a3b8;
    text-decoration: line-through;
    font-weight: 400;
}
.price-edit-input {
    width: 100%;
    height: 100%;
    min-height: 28px;
    box-sizing: border-box;
    border: 1px solid #2563eb;
    border-radius: 6px;
    text-align: center;
    font-weight: 700;
    color: #b00e0e;
    font-size: 10pt;
    outline: none;
    background: #fff;
    padding: 2px 4px;
}

.cell-stock {
    text-align: center;
    font-weight: 600;
    color: #0f766e;
    padding: 5px 8px;
    vertical-align: middle;
}

.empty-cell {
    padding: 40px 20px;
    text-align: center;
}

@media print {
    .offer-table {
        width: 100%;
        /* The screen table forces a horizontal scrollbar with min-width:840px
           (see the base rule above); on a printed A4 page that's wider than the
           printable area, so it clipped the last columns (price, inventory)
           instead of shrinking to fit. */
        min-width: 0;
        font-size: 8pt;
    }
    .offer-table thead th {
        position: static;
    }
    .cell-image :deep(.entity-image) {
        width: 112px !important;
        height: 112px !important;
        cursor: default;
    }
    .offer-table tr {
        break-inside: avoid;
        page-break-inside: avoid;
    }
}
</style>
