<template>
    <table
        class="offer-table"
        :class="{ 'is-print': printMode, 'has-fixed-rows': hasFixedRows, 'is-resizing': resizingKey !== null }"
        :style="tableVars"
    >
        <colgroup>
            <col v-for="key in activeColumns" :key="key" :class="`col-${key}`" :style="colWidth(key)">
        </colgroup>
        <thead>
            <tr>
                <th v-for="(key, idx) in activeColumns" :key="key">
                    <span class="th-label">{{ $t(COLUMN_LABELS[key]) }}</span>
                    <!-- The edge between two headers is draggable: pulling it
                         re-shares the table's width between this column and the
                         rest, and a double-click puts this one back to its
                         default share. The last column owns no edge of its own —
                         there is nothing on its far side to give width to. -->
                    <span
                        v-if="!printMode && idx < activeColumns.length - 1"
                        class="col-resizer"
                        role="separator"
                        aria-orientation="vertical"
                        :title="$t('drag_to_resize_column')"
                        :aria-label="$t('drag_to_resize_column')"
                        tabindex="0"
                        @mousedown.prevent="startColumnResize($event, key)"
                        @touchstart.prevent="startColumnResize($event, key)"
                        @dblclick.stop="resetColumnWidth(key)"
                        @keydown.left.prevent="nudgeColumn(key, -1)"
                        @keydown.right.prevent="nudgeColumn(key, 1)"
                        @keydown.enter.prevent="resetColumnWidth(key)"
                    ></span>
                </th>
            </tr>
        </thead>
        <tbody>
            <template v-for="(group, gIdx) in groups" :key="group.key">
                <!-- Classification heading. It belongs to the group that opens
                     the section so the PDF's page-break walk, which counts rows
                     group by group, keeps its place. -->
                <tr v-if="group.sectionLabel" class="section-row">
                    <th :colspan="visibleColumnCount" scope="colgroup">
                        <span class="section-name">{{ group.sectionLabel }}</span>
                    </th>
                </tr>
                <tr
                    v-for="(item, iIdx) in group.items"
                    :key="item.id"
                    :class="{ first: iIdx === 0 }"
                    :style="[{ background: gIdx % 2 === 0 ? '#ffffff' : '#f8fafc' }, rowStyle(group)]"
                    :id="!printMode && iIdx === 0 ? `item-${group.product.id}` : undefined"
                >
                    <td v-if="visibleColumns.image && iIdx === 0" :rowspan="group.items.length" class="cell-image">
                        <div class="cell-image-inner">
                            <div class="cell-image-frame">
                                <EntityImage
                                    :src="group.product.image_main"
                                    type="product"
                                    :size="160"
                                    shape="square"
                                    fit="contain"
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
                                    @click="$emit('edit-item', group, group.items[0])"
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
                            <button
                                v-if="!printMode && !visibleColumns.product"
                                type="button"
                                class="remove-item-btn"
                                :title="$t('remove_item')"
                                @click="$emit('remove-item', group)"
                            >
                                <el-icon><Delete /></el-icon>
                                {{ $t('remove_item') }}
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
                                    @click="$emit('edit-item', group, group.items[0])"
                                >
                                    <el-icon><EditPen /></el-icon>
                                </button>
                            </div>
                            <div v-if="group.product.name_en && group.product.name_ar" class="cell-product-name-en">
                                {{ group.product.name_en }}
                            </div>
                            <div v-if="group.product.sku" class="cell-product-sku">SKU: {{ group.product.sku }}</div>
                            <div v-if="group.product.brand" class="cell-product-brand">{{ group.product.brand }}</div>
                            <!-- Which subcategory the row came from. Only on screen:
                                 with a parent section selected the table mixes items
                                 from 27 subcategories, and the printed offer has no
                                 room (or need) for the internal taxonomy. -->
                            <div v-if="!printMode && categoryLabel(group.product)" class="cell-product-category">
                                {{ categoryLabel(group.product) }}
                            </div>
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
                            <button
                                v-if="!printMode && visibleColumns.product"
                                type="button"
                                class="add-item-variant-icon remove-item-icon"
                                :title="$t('remove_item')"
                                :aria-label="$t('remove_item')"
                                @click="$emit('remove-item', group)"
                            >
                                <el-icon><Delete /></el-icon>
                            </button>
                        </div>
                    </td>
                    <td
                        v-if="visibleColumns.details"
                        class="cell-detail"
                        :class="{ 'has-two-actions': !printMode && isVariantRow(item) }"
                    >
                        <div class="cell-detail-view">
                            <div v-if="item.size" class="detail-size">{{ item.size }}</div>
                            <div v-if="item.color" class="detail-color">{{ item.color }}</div>
                            <div v-if="item.unit" class="detail-unit">{{ item.unit }}</div>
                            <div v-if="!item.size && !item.color && !item.unit" class="detail-na">&mdash;</div>
                        </div>
                        <!-- Both row actions live in one strip. They used to be
                             two absolutely-positioned buttons claiming the same
                             corner, so the delete sat exactly on top of the edit
                             and the edit could not be clicked at all. -->
                        <div v-if="!printMode" class="cell-row-actions">
                            <el-tooltip :content="$t('edit')" placement="top" effect="dark">
                                <button
                                    type="button"
                                    class="cell-edit-btn"
                                    :aria-label="$t('edit')"
                                    @click="$emit('edit-item', group, item)"
                                >
                                    <el-icon><EditPen /></el-icon>
                                </button>
                            </el-tooltip>
                            <!-- Only a variant row can be removed on its own. The
                                 base row is the product itself, and taking it away
                                 is a different act with different consequences —
                                 offered on the product cell, not here. -->
                            <el-tooltip
                                v-if="isVariantRow(item)"
                                :content="$t('remove_variant')"
                                placement="top"
                                effect="dark"
                            >
                                <button
                                    type="button"
                                    class="cell-edit-btn cell-remove-btn"
                                    :aria-label="$t('remove_variant')"
                                    @click="$emit('remove-variant', group, item)"
                                >
                                    <el-icon><Delete /></el-icon>
                                </button>
                            </el-tooltip>
                        </div>
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
                            <!-- Struck-through pre-division price is a screen-only
                                 reference: the printed offer and the PDF must show
                                 the customer one price, not the one it came from. -->
                            <span v-if="!printMode && item.originalPrice !== item.displayPrice" class="price-original">
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
import { ref, computed, watch, onBeforeUnmount } from 'vue';
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
    // Per-column width shares that override the defaults below. Shares, not
    // percentages: they are re-normalised over the visible columns, so one
    // saved set of numbers keeps working when a column is hidden.
    columnWidths: { type: Object, default: () => ({}) },
    // Height of one product group, in CSS pixels of the PDF capture. Null
    // leaves the rows to the content (on screen) and to the default fifth of
    // a page (in print / PDF).
    rowHeight: { type: Number, default: null },
});

/**
 * Anything that identifies a row passes the group first, then the row within
 * it — 'edit-item' once had them the other way round, and because this list
 * validates nothing the parent read the item as the group and threw on the
 * first click. Row-only events ('start-edit', ...) pass just the item.
 */
const emit = defineEmits([
    'start-edit', 'commit-edit', 'cancel-edit',
    'start-edit-stock', 'commit-edit-stock', 'cancel-edit-stock',
    'update-image', 'clear-image',
    'add-variant',
    'edit-item',
    // Removal is split by what is being removed, because the two are not the
    // same act: a variant is one line of a product, an item is the product.
    'remove-variant',
    'remove-item',
    // A header edge was dragged: carries the full share map, ready to persist.
    'update:column-widths',
]);

/**
 * A row backed by a ProductVariant rather than the product itself.
 *
 * The table keys variant rows `v-{id}` and the base row `p-{id}`, which is the
 * same test openEditItemDialog makes to decide which record an edit belongs to.
 */
const isVariantRow = (item) => String(item?.id ?? '').startsWith('v-');

/** Display name of the classification a product is filed under, if it came through. */
const categoryLabel = (product) => {
    const cat = product?.category;
    if (!cat) return '';
    return cat.name_ar || cat.name_en || '';
};

const localEditValue = ref(props.editValue);
watch(() => props.editValue, (v) => { localEditValue.value = v; });

const localStockValue = ref(props.editStockValue);
watch(() => props.editStockValue, (v) => { localStockValue.value = v; });

const visibleColumnCount = computed(() => Object.values(props.visibleColumns).filter(Boolean).length || 1);

/**
 * How the table's width is split between the columns that are showing: half of
 * it to the picture, then the details, then the price. The numbers are shares,
 * not percentages — they are re-normalised over whatever subset is visible, so
 * hiding a column widens the others instead of leaving the table short.
 */
const COLUMN_SHARES = { image: 50, product: 35, details: 35, price: 15, inventory: 15 };
const COLUMN_LABELS = { image: 'image', product: 'product', details: 'details', price: 'the_price', inventory: 'inventory' };

/** The showing columns, in table order — drives the colgroup and the header. */
const activeColumns = computed(() => Object.keys(COLUMN_SHARES).filter((key) => props.visibleColumns[key]));

/** A column's share: what the user set for it, else the built-in default. */
const shareFor = (key) => (Number(props.columnWidths?.[key]) > 0
    ? Number(props.columnWidths[key])
    : COLUMN_SHARES[key]);

const visibleShareTotal = computed(() => activeColumns.value.reduce((sum, key) => sum + shareFor(key), 0));
const colWidth = (key) => ({
    width: `${(shareFor(key) / (visibleShareTotal.value || 1) * 100).toFixed(4)}%`,
});

// ---- Row height ---------------------------------------------------------
/**
 * Millimetres of real paper per CSS pixel of the PDF capture, taken from the
 * pair the two output paths were tuned to: a fifth of A4 is 59.4mm printed and
 * 295px captured. One setting therefore feeds both paths, in the units each of
 * them measures in (see the note on `.offer-table.is-print`).
 */
const MM_PER_ROW_PX = 59.4 / 295;

/** Rows stand at a set height, rather than being however tall their content is. */
const hasFixedRows = computed(() => props.printMode || props.rowHeight > 0);

const tableVars = computed(() => (props.rowHeight > 0
    ? {
        '--offer-row-h': `${props.rowHeight}px`,
        '--offer-row-h-print': `${(props.rowHeight * MM_PER_ROW_PX).toFixed(2)}mm`,
    }
    : {}));

/**
 * A set row height normally lands on the image or product cell, which spans the
 * whole product group. With both of those hidden no cell spans anything, so the
 * height is dealt out to the group's own rows instead. It is written as a
 * fraction of the same variable rather than of the pixel figure, because in a
 * native print that variable is millimetres of paper.
 */
const rowStyle = (group) => {
    if (!hasFixedRows.value || props.visibleColumns.image || props.visibleColumns.product) return null;
    return { height: `calc(var(--offer-image-cell-height) / ${Math.max(group.items.length, 1)})` };
};

// ---- Column resizing (screen only) --------------------------------------
// The header edges are drag handles. A drag reports where the boundary now
// stands as a percentage of the table, and the parent owns (and persists) the
// resulting shares — the table itself stays a pure view of them.

const MIN_COLUMN_PCT = 6;
const MAX_COLUMN_PCT = 80;

const resizingKey = ref(null);
let stopResize = null;

const pointerX = (event) => (event.touches?.[0]?.clientX ?? event.clientX ?? 0);

/**
 * Re-shares the table so `key` takes `pct` of it. The other visible columns
 * keep their proportions to one another and absorb the rest, which is what
 * makes the drag read as one boundary moving rather than the whole header
 * shuffling.
 */
const setColumnPct = (key, pct) => {
    const others = activeColumns.value
        .filter((other) => other !== key)
        .reduce((sum, other) => sum + shareFor(other), 0);
    if (others <= 0) return;
    const share = Math.min(Math.max(pct, MIN_COLUMN_PCT), MAX_COLUMN_PCT) / 100;
    emit('update:column-widths', {
        ...COLUMN_SHARES,
        ...props.columnWidths,
        [key]: Number((share * others / (1 - share)).toFixed(3)),
    });
};

const startColumnResize = (event, key) => {
    const th = event.currentTarget?.closest('th');
    const table = th?.closest('table');
    if (!th || !table) return;

    // In an Arabic (RTL) table the columns run the other way, so a pointer
    // moving right is making the column narrower, not wider.
    const rtl = getComputedStyle(table).direction === 'rtl';
    const startX = pointerX(event);
    const startWidth = th.getBoundingClientRect().width;
    const tableWidth = table.getBoundingClientRect().width || 1;
    resizingKey.value = key;

    const onMove = (moveEvent) => {
        if (moveEvent.cancelable) moveEvent.preventDefault();
        const dx = (pointerX(moveEvent) - startX) * (rtl ? -1 : 1);
        setColumnPct(key, (startWidth + dx) / tableWidth * 100);
    };
    const onUp = () => {
        resizingKey.value = null;
        window.removeEventListener('mousemove', onMove);
        window.removeEventListener('mouseup', onUp);
        window.removeEventListener('touchmove', onMove);
        window.removeEventListener('touchend', onUp);
        stopResize = null;
    };
    stopResize = onUp;

    window.addEventListener('mousemove', onMove);
    window.addEventListener('mouseup', onUp);
    window.addEventListener('touchmove', onMove, { passive: false });
    window.addEventListener('touchend', onUp);
};

/** Keyboard equivalent of the drag: a step of the boundary either way. */
const nudgeColumn = (key, direction) => {
    const rtl = getComputedStyle(document.documentElement).direction === 'rtl';
    const pct = shareFor(key) / (visibleShareTotal.value || 1) * 100;
    setColumnPct(key, pct + (rtl ? -direction : direction) * 2);
};

const resetColumnWidth = (key) => {
    emit('update:column-widths', { ...COLUMN_SHARES, ...props.columnWidths, [key]: COLUMN_SHARES[key] });
};

// A drag that is still live when the table goes away would otherwise leave its
// listeners on the window.
onBeforeUnmount(() => stopResize?.());

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
    /* Fixed layout so the shares written on the <col>s are the widths, rather
       than a starting point auto-layout re-negotiates against the longest
       product name in the page. */
    table-layout: fixed;
    --offer-image-pad: 10px;
    /* The picture now tracks its column instead of the other way round: it
       spans the image cell edge to edge. On screen it is held to this cap, or
       a wide monitor would turn the editing table into a wall of posters; the
       printed and exported list drops the cap below so the picture really does
       take its half of the page. */
    --offer-image-max: 340px;
    /* The tallest a picture may grow. On screen it matches the width cap, so a
       portrait photo takes the same square of the page a landscape one does
       across; a set row height replaces this with what the cell actually has
       (see `.has-fixed-rows`). */
    --offer-image-cap: 340px;
    /* What the box stands at while the picture is still on its way — never
       more than the cell has to give, so it reserves a place without pushing
       a short row open. */
    --offer-image-min: min(120px, var(--offer-image-cap));
}
/* The printed / exported list lets the picture off its on-screen cap so it
   really does take its share of the page. */
.offer-table.is-print {
    --offer-image-max: none;
}
/*
 * A row of a set height: a share of the *page* rather than of the table. The
 * printed list always has one — a fifth of an A4 sheet, so five products fill
 * a page and the reader gets the same rhythm on every one of them — and the
 * on-screen table takes one as soon as the reader dials a height in, so what
 * they are arranging is what will come out of the printer.
 *
 * The height has to be said twice because the two output paths measure in
 * different units. A native Ctrl+P lays the table out on the real sheet, so
 * millimetres are literal there (@media print, below). The PDF path captures
 * this same table off-screen at a fixed 960px width (`.print-only.pdf-render`)
 * and scales that canvas to the page's 547.28pt content width — 0.5701pt per
 * CSS px — which makes a whole A4 page (841.89pt) 1477px of CSS, and a fifth
 * of it 295px. Change the export's page margin or capture width and these
 * numbers move with them. A custom height arrives as `--offer-row-h` (and its
 * millimetre twin) on the table, written there by the component.
 */
.offer-table.has-fixed-rows {
    --offer-image-cell-height: var(--offer-row-h, 295px);
    /* What the caption under the picture takes out of that height: the cell's
       padding top and bottom, the gap, and two lines of a 10pt product name. */
    --offer-image-caption: 66px;
    /* The picture may take everything the cell has under the caption. */
    --offer-image-cap: calc(var(--offer-image-cell-height) - var(--offer-image-caption));
}
/* Both identity cells span the whole product group, so the height lands on the
   group however the columns are arranged — including with the picture hidden,
   where the product cell is the one holding the group together. */
.offer-table.has-fixed-rows .cell-image,
.offer-table.has-fixed-rows .cell-product {
    height: var(--offer-image-cell-height);
}
/* Nothing more to say about the picture here: it reads `--offer-image-cap`
   above and grows to the cell on its own (see the screen rule below). What is
   left is the missing-picture box, which has no proportions of its own to
   stand on and so takes the cell's. */
.offer-table.has-fixed-rows .cell-image :deep(.entity-image--empty) {
    height: var(--offer-image-cap) !important;
    aspect-ratio: auto;
}
.offer-table th,
.offer-table td {
    border: 1px solid #cbd5e1;
}
/*
 * Where one product ends and the next begins, drawn heavier than the hairlines
 * inside it. A product with several variants is several rows, and at one
 * weight of line the reader has to count cells to see where its block stops;
 * this makes the boundary the strongest line in the body — under the
 * classification divider, above everything else. The first row of each group
 * carries it, and border collapsing puts it over the previous row's hairline.
 */
.offer-table tbody tr.first > td,
.offer-table tbody tr.first > th {
    border-top: 2px solid #64748b;
}
/* Widths come from the shares on each <col> (see COLUMN_SHARES); these only
   name the columns for the rules below. */
.offer-table thead th {
    position: sticky;
    top: 0;
    /* The resize handle hangs off the header's trailing edge. Sticky already
       establishes the positioning context the handle is placed against. */
    z-index: 5;
    /* A light, unmistakably blue band with near-black lettering on it. The
       header is read on paper as often as on screen, and a printer that drops
       background graphics would have left white type on white — so the ink is
       dark and the band is pale enough that the words stand whether the colour
       lands or not. `print-color-adjust` asks for it to land. */
    background: #bcdcfb;
    color: #000;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
    padding: 10px 12px;
    font-weight: 900;
    text-align: center;
    border-bottom: 2px solid #1d4ed8;
}
.offer-table tbody tr:hover td {
    background: #e2e9f2 !important;
}

/* ---- Column resize handles (screen only) ----
   A hit area straddling the boundary between two headers, wide enough to grab
   with a mouse but showing only a hairline until it is pointed at. */
.col-resizer {
    position: absolute;
    top: 0;
    bottom: 0;
    inset-inline-end: -5px;
    width: 11px;
    z-index: 6;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: col-resize;
    touch-action: none;
}
.col-resizer::after {
    content: '';
    width: 2px;
    height: 58%;
    border-radius: 2px;
    background: rgba(15, 23, 42, .25);
    transition: background .15s ease, height .15s ease;
}
.col-resizer:focus-visible {
    outline: none;
}
.col-resizer:hover::after,
.col-resizer:focus-visible::after {
    background: #1d4ed8;
    height: 78%;
}
/* While a drag is running the whole table follows the pointer, so the cursor
   and the highlight belong to the table rather than to the handle under it. */
.offer-table.is-resizing {
    cursor: col-resize;
    user-select: none;
}
.offer-table.is-resizing .col-resizer::after {
    background: #1d4ed8;
    height: 100%;
}

.cell-image {
    text-align: center;
    vertical-align: middle;
    padding: var(--offer-image-pad);
}
/*
 * EntityImage writes its box as an inline style off the `size` prop, so a box
 * that tracks the column has to win on specificity.
 *
 * The box used to be a square the picture was fitted inside, which left every
 * photo that is not itself square sitting in a band of empty cell. Now the box
 * takes its shape from the photo: the picture spans the column edge to edge,
 * and `max-height` pulls it back — proportionally, the way a replaced element
 * is clamped — when that would make it taller than the cell has room for. So
 * it grows until it meets the cell on one side or the other, and what is left
 * over is cell rather than a plate around the picture. Nothing is stretched or
 * cropped on the way: `contain` (set on the component) stays as the guarantee
 * that the goods are shown whole — a price list is read for them, and cropping
 * cuts the ends off a tap or a length of pipe.
 */
.cell-image :deep(.entity-image) {
    width: 100% !important;
    height: auto !important;
    aspect-ratio: auto;
}
/*
 * A picture that has not arrived yet leaves nothing in the box to give it a
 * height — and a box of no height is one the lazy loader may never see come
 * into view, so the picture would never be asked for. The wrapper el-image
 * puts round its placeholder says exactly when that is, and it goes away the
 * moment the photo lands.
 */
.cell-image :deep(.el-image:has(.el-image__wrapper)) {
    min-height: var(--offer-image-min);
}
.cell-image :deep(.el-image__inner) {
    display: block;
    width: 100%;
    height: auto;
    max-height: var(--offer-image-cap, none);
    /* Clamping the height narrows the picture; this keeps it in the middle of
       the column rather than against its leading edge. */
    margin-inline: auto;
}
/* The stand-in icon has no picture to take its shape from, so it keeps the
   square the box used to be. */
.cell-image :deep(.entity-image--empty) {
    aspect-ratio: 1 / 1;
    max-height: var(--offer-image-cap, none);
}
/* The shimmer stands in the reserved box above, which el-image gives it in
   full — it has no shape of its own to fall back on. */
.cell-image :deep(.entity-image--loading) {
    height: 100% !important;
}
.cell-image :deep(.el-image) {
    background: transparent;
}
/* Picture and caption share one column-wide stack, centred in the cell and
   held to the cap so the two stay the same width as each other whatever the
   column happens to be. */
.cell-image-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    width: 100%;
    max-width: var(--offer-image-max);
    margin-inline: auto;
}
.cell-image-frame {
    position: relative;
    display: block;
    width: 100%;
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
    width: 100%;
    max-width: 100%;
    padding-inline: var(--offer-image-pad);
    box-sizing: border-box;
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
.cell-product-category {
    display: inline-flex;
    align-items: center;
    align-self: flex-start;
    margin-top: 3px;
    max-width: 100%;
    font-size: 8pt;
    font-weight: 700;
    color: #4338ca;
    background: #eef2ff;
    border-radius: 999px;
    padding: 1px 8px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.cell-detail {
    position: relative;
    color: #334155;
    font-size: 9.5pt;
    font-weight: 700;
    white-space: pre-wrap;
    /* Logical, to match the action strip's `inset-inline-end`. The gutter was
       reserved with a physical `padding-right` while the button anchored to the
       inline end — the same side only in a left-to-right document, so in Arabic
       the space was held open on one side and the button sat on the other. */
    padding: 5px 8px;
    padding-inline-end: 26px;
    text-align: center;
    vertical-align: middle;
}

/* A variant row carries edit *and* remove, so it needs a wider gutter than a
   row that only carries edit. Reserved per row rather than for the whole
   column, which is the narrower share of the table and cannot spare it
   everywhere. */
.cell-detail.has-two-actions {
    padding-inline-end: 50px;
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

/* The details cell's action strip.
   `.cell-edit-btn` positions itself absolutely in the cell corner, which is
   right while there is one of them — the price and stock cells still work that
   way. Two of them claimed the same corner and stacked, hiding the edit under
   the delete. Inside the strip they go back to normal flow and sit side by
   side; the strip takes the corner instead. */
.cell-row-actions {
    position: absolute;
    top: 2px;
    inset-inline-end: 2px;
    display: inline-flex;
    align-items: center;
    gap: 2px;
}

.cell-row-actions .cell-edit-btn {
    position: static;
    /* Bigger than the lone 18px button: two adjacent targets, one of them
       destructive, need room not to be hit by accident. */
    width: 22px;
    height: 22px;
}

/* Removal shares the edit button's shape, so they read as one family — but it
   only turns red on hover. A destructive control that is red at rest drags the
   eye to the one action on the row that cannot be undone. */
.cell-remove-btn:hover {
    background: #fee2e2;
    color: #dc2626;
}

/* Keyboard users get the same affordance the mouse gets: the strip is
   hover-revealed, which would otherwise leave a focused button invisible. */
.cell-row-actions .cell-edit-btn:focus-visible {
    opacity: 1;
    outline: 2px solid #2563eb;
    outline-offset: 1px;
}

.cell-row-actions .cell-remove-btn:focus-visible {
    outline-color: #dc2626;
}

.remove-item-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-top: 4px;
    padding: 3px 8px;
    border: 1px dashed #e2c8c8;
    border-radius: 999px;
    background: #fff;
    color: #94a3b8;
    cursor: pointer;
    font: inherit;
    font-size: 11px;
    transition: background .15s ease, color .15s ease, border-color .15s ease;
}

.remove-item-btn:hover {
    border-color: #fca5a5;
    background: #fef2f2;
    color: #dc2626;
}

.remove-item-icon:hover {
    border-color: #fca5a5;
    background: #fef2f2;
    color: #dc2626;
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
        /* The column shares are percentages of the table, so they already scale
           to the actual printable width — A4 (210mm) or US Letter (216mm),
           whichever the print dialog picks. Nothing to re-state here. */
        --offer-image-max: none;
    }
    /* Real millimetres on a real sheet: a fifth of A4's 297mm by default, or
       the custom height converted to paper by the component. */
    .offer-table.is-print {
        --offer-image-cell-height: var(--offer-row-h-print, 59.4mm);
        --offer-image-caption: 17mm;
    }
    .col-resizer {
        display: none;
    }
    .offer-table thead th {
        position: static;
        background: #bcdcfb;
        color: #000;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    /* The box itself is sized by the screen rule above, which reads the same
       variable this medium has just overridden. */
    .cell-image :deep(.entity-image) {
        cursor: default;
    }
    .offer-table tr {
        break-inside: avoid;
        page-break-inside: avoid;
    }
    /* Never leave a classification heading alone at the foot of a page. The
       PDF path already keeps it with its first group; this is for a native
       Ctrl+P, where the browser chooses the breaks. */
    .offer-table tbody tr.section-row {
        break-after: avoid;
        page-break-after: avoid;
    }
}


/* Classification heading inside the table body. Reads as a divider rather than
   another column header, so it is never mistaken for the sticky thead. */
.offer-table tbody tr.section-row th {
    position: static;
    text-align: start;
    padding: 9px 12px;
    background: #eef2f7;
    color: #1f2937;
    font-size: 11pt;
    font-weight: 800;
    letter-spacing: .01em;
    border-top: 2px solid #293344;
    border-bottom: 1px solid #cbd5e1;
}
.offer-table tbody tr.section-row:hover th {
    background: #eef2f7;
}
.section-name {
    display: inline-block;
}
</style>
