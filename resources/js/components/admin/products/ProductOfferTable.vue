<template>
    <table class="offer-table">
        <colgroup>
            <col v-if="!printMode" class="col-select">
            <col v-if="visibleColumns.image" class="col-image">
            <col v-if="visibleColumns.product" class="col-product">
            <col v-if="visibleColumns.details" class="col-details">
            <col v-if="visibleColumns.price" class="col-price">
            <col v-if="visibleColumns.inventory" class="col-inventory">
        </colgroup>
        <thead>
            <tr>
                <th v-if="!printMode" class="col-select">
                    <span class="select-head" :class="{ 'is-mixed': someSelected }">
                        <span class="select-head-label">{{ $t('select_for_print') }}</span>
                        <el-checkbox
                            :model-value="allSelected"
                            :indeterminate="someSelected"
                            @update:model-value="(val) => $emit('toggle-all', val)"
                        />
                    </span>
                </th>
                <th v-if="visibleColumns.image">{{ $t('image') }}</th>
                <th v-if="visibleColumns.product">{{ $t('product') }}</th>
                <th v-if="visibleColumns.details">{{ $t('details') }}</th>
                <th v-if="visibleColumns.price">{{ $t('the_price') }}</th>
                <th v-if="visibleColumns.inventory">{{ $t('inventory') }}</th>
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
                    <td v-if="!printMode && iIdx === 0" :rowspan="group.items.length" class="cell-select">
                        <el-checkbox
                            :model-value="isSelected(group.key)"
                            @update:model-value="(val) => $emit('toggle-select', group.key, val)"
                        />
                    </td>
                    <td v-if="visibleColumns.image && iIdx === 0" :rowspan="group.items.length" class="cell-image">
                        <div class="cell-image-inner">
                            <div class="cell-image-frame">
                                <EntityImage
                                    :src="group.product.image_main"
                                    type="product"
                                    :size="160"
                                    shape="square"
                                    :lazy="!printMode"
                                    :preview-src-list="printMode ? [] : getPreviewList(group.product)"
                                />
                                <el-popover
                                    v-if="!printMode"
                                    placement="top"
                                    :width="228"
                                    trigger="click"
                                    :visible="imageEditorVisible[group.key] === true"
                                    popper-class="image-editor-popover"
                                    @update:visible="(val) => setImageEditorVisible(group, val)"
                                >
                                    <div class="image-editor">
                                        <p class="image-editor-title">{{ $t('change_the_picture') }}</p>
                                        <div class="image-editor-preview">
                                            <EntityImage
                                                :src="group.product.image_main"
                                                type="product"
                                                :size="104"
                                                shape="square"
                                                :lazy="false"
                                            />
                                        </div>
                                        <el-upload
                                            class="image-cell-uploader"
                                            :action="uploadUrl"
                                            :data="{ slug: imageSlug(group) }"
                                            :show-file-list="false"
                                            :headers="uploadHeaders"
                                            :before-upload="beforeImageUpload"
                                            :on-progress="() => setImageBusy(group, true)"
                                            :on-success="(response) => onImageUploadSuccess(group, response)"
                                            :on-error="() => onImageUploadError(group)"
                                            accept="image/*"
                                            name="file"
                                        >
                                            <div class="image-uploader-box">
                                                <el-icon v-if="imageEditorBusy[group.key]" class="is-loading"><Loading /></el-icon>
                                                <el-icon v-else><UploadFilled /></el-icon>
                                                <span>{{ imageEditorBusy[group.key] ? $t('uploading') : $t('upload_the_main_image') }}</span>
                                                <small>{{ $t('image_upload_limits_hint') }}</small>
                                            </div>
                                        </el-upload>
                                        <p v-if="imageEditorError[group.key]" class="image-editor-error">{{ imageEditorError[group.key] }}</p>
                                        <div class="image-editor-actions">
                                            <button v-if="group.product.image_main" type="button" class="image-editor-remove" @click="removeItemImage(group)">
                                                <el-icon><Delete /></el-icon>
                                                {{ $t('delete_the_image') }}
                                            </button>
                                            <button type="button" class="image-editor-cancel" @click="setImageEditorVisible(group, false)">
                                                {{ $t('common.cancel') }}
                                            </button>
                                        </div>
                                    </div>
                                    <template #reference>
                                        <span
                                            class="cell-image-edit"
                                            role="button"
                                            tabindex="0"
                                            :title="$t('click_to_change_image')"
                                            :aria-label="$t('click_to_change_image')"
                                        >
                                            <el-icon><EditPen /></el-icon>
                                        </span>
                                    </template>
                                </el-popover>
                                <span v-if="!printMode && itemStatus[imageRowId(group)]" class="save-status corner image-save-status" :class="itemStatus[imageRowId(group)]">
                                    <el-icon v-if="itemStatus[imageRowId(group)] === 'saving'" class="is-loading"><Loading /></el-icon>
                                    <el-icon v-else-if="itemStatus[imageRowId(group)] === 'saved'"><Check /></el-icon>
                                    <el-icon v-else-if="itemStatus[imageRowId(group)] === 'pending'"><Clock /></el-icon>
                                    <el-icon v-else><WarningFilled /></el-icon>
                                </span>
                            </div>
                            <div class="cell-image-name-row">
                                <span class="cell-image-name">
                                    {{ group.product.name_ar || group.product.name_en }}
                                </span>
                                <button
                                    v-if="!printMode && !visibleColumns.product"
                                    type="button"
                                    class="cell-name-edit"
                                    :title="$t('edit')"
                                    :aria-label="$t('edit')"
                                    @click="$emit('edit-item', group.items[0], group)"
                                >
                                    <el-icon><EditPen /></el-icon>
                                </button>
                            </div>
                            <button
                                v-if="!printMode && !visibleColumns.product"
                                type="button"
                                class="add-item-variant-btn"
                                :title="$t('add_variant')"
                                @click="$emit('add-variant', group)"
                            >
                                <el-icon><Plus /></el-icon>
                                {{ $t('add_variant') }}
                            </button>
                        </div>
                    </td>
                    <td v-if="visibleColumns.product && iIdx === 0" :rowspan="group.items.length" class="cell-product">
                        <div class="cell-product-inner">
                            <div class="cell-product-name-row">
                                <div class="cell-product-name">{{ group.product.name_ar || group.product.name_en }}</div>
                                <button
                                    v-if="!printMode"
                                    type="button"
                                    class="cell-name-edit"
                                    :title="$t('edit')"
                                    :aria-label="$t('edit')"
                                    @click="$emit('edit-item', group.items[0], group)"
                                >
                                    <el-icon><EditPen /></el-icon>
                                </button>
                            </div>
                            <div v-if="group.product.name_en && group.product.name_ar" class="cell-product-name-en">
                                {{ group.product.name_en }}
                            </div>
                            <div v-if="group.product.sku" class="cell-product-sku">SKU: {{ group.product.sku }}</div>
                            <div v-if="group.product.brand" class="cell-product-brand">{{ group.product.brand }}</div>
                            <button
                                v-if="!printMode && visibleColumns.product"
                                type="button"
                                class="add-item-variant-icon"
                                :title="$t('add_variant')"
                                :aria-label="$t('add_variant')"
                                @click="$emit('add-variant', group)"
                            >
                                <el-icon><Plus /></el-icon>
                            </button>
                        </div>
                    </td>
                    <td v-if="visibleColumns.details" class="cell-detail">
                        <div class="cell-detail-view">
                            <div v-if="item.size" class="detail-size">{{ item.size }}</div>
                            <div v-if="item.color" class="detail-color">{{ item.color }}</div>
                            <div v-if="item.unit" class="detail-unit">{{ item.unit }}</div>
                            <div v-if="!item.size && !item.color && !item.unit" class="detail-na">&mdash;</div>
                        </div>
                        <el-tooltip v-if="!printMode" :content="$t('edit')" placement="top" effect="dark">
                            <button
                                type="button"
                                class="cell-edit-btn"
                                :title="$t('edit')"
                                @click="$emit('edit-item', item, group)"
                            >
                                <el-icon><EditPen /></el-icon>
                            </button>
                        </el-tooltip>
                    </td>
                    <td
                        v-if="visibleColumns.price"
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
                        <el-tooltip v-if="!printMode" :content="$t('click_to_edit_price')" placement="top" effect="dark">
                            <button type="button" class="cell-edit-btn" @click="$emit('start-edit', item)">
                                <el-icon><EditPen /></el-icon>
                            </button>
                        </el-tooltip>
                        <span v-if="!printMode && itemStatus[item.id]" class="save-status corner" :class="itemStatus[item.id]">
                            <el-icon v-if="itemStatus[item.id] === 'saving'" class="is-loading"><Loading /></el-icon>
                            <el-icon v-else-if="itemStatus[item.id] === 'saved'"><Check /></el-icon>
                            <el-icon v-else-if="itemStatus[item.id] === 'pending'"><Clock /></el-icon>
                            <el-icon v-else><WarningFilled /></el-icon>
                        </span>
                    </td>
                    <td
                        v-if="visibleColumns.inventory"
                        class="cell-stock"
                        :class="{ editing: !printMode && editingStockId === item.id }"
                        @dblclick="!printMode && $emit('start-edit-stock', item)"
                    >
                        <input
                            v-if="!printMode && editingStockId === item.id"
                            v-model="localStockValue"
                            type="text"
                            inputmode="numeric"
                            class="stock-edit-input"
                            autocomplete="off"
                            @keydown.enter.prevent="$emit('commit-edit-stock', localStockValue)"
                            @keydown.escape.prevent="$emit('cancel-edit-stock')"
                            @blur="$emit('commit-edit-stock', localStockValue)"
                        />
                        <span v-else>{{ item.stock_quantity ?? 0 }}</span>
                        <el-tooltip v-if="!printMode" :content="$t('click_to_edit_stock')" placement="top" effect="dark">
                            <button type="button" class="cell-edit-btn" @click="$emit('start-edit-stock', item)">
                                <el-icon><EditPen /></el-icon>
                            </button>
                        </el-tooltip>
                        <span v-if="!printMode && itemStatus[item.id]" class="save-status corner" :class="itemStatus[item.id]">
                            <el-icon v-if="itemStatus[item.id] === 'saving'" class="is-loading"><Loading /></el-icon>
                            <el-icon v-else-if="itemStatus[item.id] === 'saved'"><Check /></el-icon>
                            <el-icon v-else-if="itemStatus[item.id] === 'pending'"><Clock /></el-icon>
                            <el-icon v-else><WarningFilled /></el-icon>
                        </span>
                    </td>
                </tr>
            </template>
            <tr v-if="!loading && groups.length === 0">
                <td :colspan="visibleColumnCount" class="empty-cell">
                    <el-empty :description="$t('there_are_no_products')" />
                </td>
            </tr>
        </tbody>
    </table>
</template>

<script setup>
import EntityImage from '@/components/admin/EntityImage.vue';
import { productImages, toImagePath } from '@/utils/productImages';
import { EditPen, Loading, Check, Clock, WarningFilled, UploadFilled, Delete, Plus } from '@element-plus/icons-vue';
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';

const { t } = useI18n();

const props = defineProps({
    groups: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    editingId: { type: [String, Number, null], default: null },
    editValue: { type: String, default: '' },
    editingStockId: { type: [String, Number, null], default: null },
    editStockValue: { type: String, default: '' },
    itemStatus: { type: Object, default: () => ({}) },
    printMode: { type: Boolean, default: false },
    visibleColumns: {
        type: Object,
        default: () => ({ image: true, product: true, details: true, price: true, inventory: true }),
    },
    selectedKeys: { type: Array, default: () => [] },
    allSelected: { type: Boolean, default: false },
    someSelected: { type: Boolean, default: false },
});

const emit = defineEmits([
    'start-edit', 'commit-edit', 'cancel-edit',
    'start-edit-stock', 'commit-edit-stock', 'cancel-edit-stock',
    'toggle-select', 'toggle-all',
    'update-image', 'clear-image',
    'add-variant',
    'edit-item',
]);

const localEditValue = ref(props.editValue);
watch(() => props.editValue, (v) => { localEditValue.value = v; });

const localStockValue = ref(props.editStockValue);
watch(() => props.editStockValue, (v) => { localStockValue.value = v; });

const visibleColumnCount = computed(() => Object.values(props.visibleColumns).filter(Boolean).length || 1);

// ---- Inline image editor ------------------------------------------------
// The image cell only swaps in one picture per group (the owning product's
// main image), so the save goes to that product, never to a variant row.

const uploadUrl = '/api/v1/upload';
const uploadHeaders = {
    'Authorization': `Bearer ${localStorage.getItem('token') || ''}`,
    'Accept': 'application/json',
};

const imageEditorVisible = ref({});
const imageEditorBusy = ref({});
const imageEditorError = ref({});

const setImageEditorVisible = (group, val) => {
    imageEditorVisible.value = { ...imageEditorVisible.value, [group.key]: val };
};

const setImageBusy = (group, val) => {
    imageEditorBusy.value = { ...imageEditorBusy.value, [group.key]: val };
};

// The item id the parent's per-row status badges are keyed by.
const imageRowId = (group) => `p-${group.product.id}`;

const imageSlug = (group) => group.product?.slug || group.product?.name_ar || 'product';

const beforeImageUpload = (file) => {
    if (!file.type.startsWith('image/')) {
        ElMessage.error(t('only_photos_can_be_uploaded'));
        return false;
    }
    if (file.size / 1024 / 1024 >= 5) {
        ElMessage.error(t('image_size_must_be_less_than_5mb'));
        return false;
    }
    return true;
};

const onImageUploadSuccess = (group, response) => {
    setImageBusy(group, false);
    const path = response?.data?.url || response?.data?.path || response?.url || '';
    if (!path) {
        imageEditorError.value = { ...imageEditorError.value, [group.key]: t('failed_to_upload_image') };
        return;
    }
    imageEditorVisible.value = { ...imageEditorVisible.value, [group.key]: false };
    emit('update-image', group, toImagePath(path));
};

const onImageUploadError = (group) => {
    setImageBusy(group, false);
    imageEditorError.value = { ...imageEditorError.value, [group.key]: t('failed_to_upload_image') };
};

const removeItemImage = (group) => {
    imageEditorVisible.value = { ...imageEditorVisible.value, [group.key]: false };
    emit('clear-image', group);
};

// A product is selected for printing when nothing has been explicitly
// unselected yet (empty list) or when its group key is in the selection.
// The print/PDF table never shows selection, so it always counts everything.
const isSelected = (key) => {
    if (props.printMode) return true;
    if (!props.selectedKeys || props.selectedKeys.length === 0) return true;
    return props.selectedKeys.includes(key);
};

const getPreviewList = (product) => productImages(product);

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
    min-width: 720px;
}
.offer-table th,
.offer-table td {
    border: 1px solid #cbd5e1;
}
.col-image { width: 180px; }
.col-select { width: 46px; }
.col-product { width: 200px; }
.col-details { width: 120px; }
.col-price { width: 120px; }
.col-inventory { width: 100px; }
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
.cell-select {
    text-align: center;
    vertical-align: middle;
    padding: 6px;
}
.select-head {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
}
.select-head-label {
    font-size: 8pt;
    font-weight: 500;
    color: rgba(255,255,255,.85);
}
.select-head :deep(.el-checkbox__inner) {
    background: rgba(255,255,255,.12);
    border-color: rgba(255,255,255,.55);
}
.select-head :deep(.el-checkbox__input.is-checked .el-checkbox__inner) {
    background: #fff;
    border-color: #fff;
}
.select-head :deep(.el-checkbox__inner::after) {
    border-color: #293344;
}
.cell-image-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}
.cell-image-frame {
    position: relative;
    display: inline-block;
    line-height: 0;
}
/* Edit affordance over the picture, matching the pencil in the other cells
   but on an image background instead of a bare table cell. */
.cell-image-edit {
    position: absolute;
    top: 6px;
    inset-inline-end: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: rgba(15, 23, 42, .55);
    color: #fff;
    cursor: pointer;
    font-size: 13px;
    z-index: 2;
    opacity: .6;
    transition: opacity .15s ease, background .15s ease, color .15s ease;
}
.cell-image-frame:hover .cell-image-edit {
    opacity: 1;
}
.cell-image-edit:hover {
    background: #2563eb;
    color: #fff;
}
.image-save-status {
    position: absolute;
    bottom: 6px;
    inset-inline-start: 6px;
    z-index: 2;
    background: rgba(255, 255, 255, .9);
    border-radius: 4px;
    padding: 1px 3px;
}
.cell-image-name-row {
    display: flex;
    align-items: flex-start;
    justify-content: center;
    gap: 4px;
    max-width: 200px;
}
.cell-image-name {
    font-weight: 600;
    color: #111c2c;
    font-size: 10pt;
    line-height: 1.3;
    direction: rtl;
    text-align: center;
    white-space: normal;
    word-break: break-word;
    min-width: 0;
}
/* Name edit pencils — a subtle inline pencil beside the product/item name in
   the image and product cells, plus the row's own "edit" on the details cell.
   Same hover treatment as `.cell-edit-btn` so they read as one family. */
.cell-name-edit {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    padding: 0;
    margin-top: 1px;
    border: none;
    border-radius: 4px;
    background: transparent;
    color: #94a3b8;
    cursor: pointer;
    font-size: 11px;
    opacity: .45;
    transition: opacity .15s ease, background .15s ease, color .15s ease;
}
.cell-image-name-row:hover .cell-name-edit,
.cell-product-name-row:hover .cell-name-edit {
    opacity: 1;
}
.cell-name-edit:hover {
    background: #eef2ff;
    color: #2563eb;
}
/* "Add variant" affordances — one per product group: a labeled pill under the
   name in the image cell, and a compact "+" chip in the product cell. Only one
   of the two renders (they key off which identity column is visible). */
.add-item-variant-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    border: 1px dashed #cbd5e1;
    background: #fff;
    color: #64748b;
    font-size: 11px;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 999px;
    cursor: pointer;
    transition: border-color .15s ease, background .15s ease, color .15s ease, box-shadow .15s ease;
}
.add-item-variant-btn:hover {
    border-color: #2563eb;
    color: #2563eb;
    background: #eef2ff;
    box-shadow: 0 2px 6px rgba(37, 99, 235, .18);
}
.add-item-variant-btn .el-icon {
    font-size: 12px;
}
.add-item-variant-icon {
    align-self: flex-start;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    padding: 0;
    border: none;
    border-radius: 6px;
    background: #f1f5f9;
    color: #475569;
    cursor: pointer;
    font-size: 13px;
    transition: background .15s ease, color .15s ease, transform .15s ease;
}
.add-item-variant-icon:hover {
    background: #eef2ff;
    color: #2563eb;
    transform: translateY(-1px);
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
.cell-product-name-row {
    display: flex;
    align-items: flex-start;
    gap: 4px;
}
.cell-product-name {
    font-weight: 700;
    color: #111c2c;
    font-size: 11pt;
    line-height: 1.4;
    direction: rtl;
    text-align: right;
    min-width: 0;
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
    position: relative;
    color: #334155;
    font-size: 9.5pt;
    font-weight: 700;
    white-space: pre-wrap;
    padding: 5px 22px 5px 8px;
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
.cell-edit-btn {
    position: absolute;
    top: 2px;
    inset-inline-end: 2px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    padding: 0;
    border: none;
    border-radius: 4px;
    background: transparent;
    color: #94a3b8;
    cursor: pointer;
    font-size: 11px;
    opacity: .45;
    transition: opacity .15s ease, background .15s ease, color .15s ease;
}
.cell-detail:hover .cell-edit-btn,
.cell-price:hover .cell-edit-btn,
.cell-stock:hover .cell-edit-btn {
    opacity: 1;
}
.cell-edit-btn:hover {
    background: #e0e7ff;
    color: #2563eb;
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
    position: relative;
    cursor: pointer;
    vertical-align: middle;
}
.cell-stock:hover:not(.editing)::after {
    content: '✎';
    position: absolute;
    top: 1px;
    right: 3px;
    font-size: 10px;
    color: #2563eb;
    line-height: 1;
    pointer-events: none;
}
.cell-stock.editing {
    padding: 0;
    overflow: visible;
}
.stock-edit-input {
    width: 100%;
    height: 100%;
    min-height: 28px;
    box-sizing: border-box;
    border: 1px solid #2563eb;
    border-radius: 6px;
    text-align: center;
    font-weight: 600;
    color: #0f766e;
    font-size: 10pt;
    outline: none;
    background: #fff;
    padding: 2px 4px;
}

.save-status {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    line-height: 1;
}
.save-status.corner {
    position: absolute;
    bottom: 1px;
    left: 3px;
}
.save-status.saving { color: #64748b; }
.save-status.saved { color: #16a34a; }
.save-status.pending { color: #d97706; }
.save-status.error { color: #dc2626; }

/* ---- Inline image editor popover ---- */
.image-editor {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.image-editor-title {
    margin: 0;
    font-size: 12.5px;
    font-weight: 700;
    color: #111c2c;
}
.image-editor-preview {
    display: flex;
    justify-content: center;
}
.image-cell-uploader {
    width: 100%;
}
.image-cell-uploader :deep(.el-upload) {
    width: 100%;
}
.image-uploader-box {
    box-sizing: border-box;
    width: 100%;
    min-height: 64px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 10px;
    border: 1px dashed #cbd5e1;
    border-radius: 10px;
    background: #f8fafc;
    color: #334155;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    text-align: center;
    transition: border-color .15s ease, background .15s ease;
}
.image-uploader-box:hover {
    border-color: #2563eb;
    background: #eef2ff;
}
.image-uploader-box .el-icon {
    font-size: 20px;
    color: #2563eb;
}
.image-uploader-box small {
    display: block;
    font-size: 10.5px;
    font-weight: 400;
    color: #94a3b8;
    line-height: 1.4;
}
.image-editor-error {
    margin: 0;
    color: #dc2626;
    font-size: 11.5px;
    font-weight: 600;
}
.image-editor-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
.image-editor-remove {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    border: none;
    background: none;
    padding: 0;
    color: #dc2626;
    font-size: 11.5px;
    font-weight: 600;
    cursor: pointer;
}
.image-editor-remove:hover {
    text-decoration: underline;
}
.image-editor-cancel {
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #475569;
    font-size: 11.5px;
    font-weight: 600;
    padding: 4px 12px;
    border-radius: 999px;
    cursor: pointer;
    transition: background .15s ease, color .15s ease;
}
.image-editor-cancel:hover {
    background: #fee2e2;
    color: #b00e0e;
}

.empty-cell {
    padding: 40px 20px;
    text-align: center;
}

@media print {
    .offer-table {
        width: 100%;
        /* The screen table forces a horizontal scrollbar with min-width:720px
           (see the base rule above); on a printed A4 page that's wider than the
           printable area, so it clipped the last columns (price, inventory)
           instead of shrinking to fit. */
        min-width: 0;
        font-size: 8pt;
        /* Fixed layout + percentage columns so widths scale to the actual
           printable width instead of the browser's auto-layout guessing at
           content size — keeps every column on the page on both A4 (210mm)
           and US Letter (216mm), whichever the print dialog picks. */
        table-layout: fixed;
    }
    .col-image { width: 25%; }
    .col-product { width: 28%; }
    .col-details { width: 17%; }
    .col-price { width: 17%; }
    .col-inventory { width: 13%; }
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
