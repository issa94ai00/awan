<template>
    <!--
        No source at all: draw the icon straight away.

        The screens used to write `row.image || '/placeholder.jpg'`, which meant
        a record with no picture still requested a file — and because that file
        loads perfectly well, `el-image`'s `#error` slot never fired and the
        icon written underneath it was unreachable. Every empty record showed
        the same grey stock photo instead.
    -->
    <div
        v-if="!src"
        class="entity-image entity-image--empty"
        :class="shapeClass"
        :style="boxStyle"
        role="img"
        :aria-label="fallbackLabel"
    >
        <el-icon :size="iconSize"><component :is="icon" /></el-icon>
        <span v-if="showLabel" class="entity-image__label">{{ fallbackLabel }}</span>
    </div>

    <!-- Has a source: the same icon stands in if it fails to load. -->
    <el-image
        v-else
        :src="src"
        :fit="fit"
        :lazy="lazy"
        :preview-src-list="previewSrcList"
        :preview-teleported="true"
        class="entity-image"
        :class="shapeClass"
        :style="boxStyle"
    >
        <template #error>
            <div class="entity-image entity-image--empty" :class="shapeClass" :style="boxStyle">
                <el-icon :size="iconSize"><component :is="icon" /></el-icon>
                <span v-if="showLabel" class="entity-image__label">{{ fallbackLabel }}</span>
            </div>
        </template>
        <template #placeholder>
            <div class="entity-image entity-image--loading" :class="shapeClass" :style="boxStyle"></div>
        </template>
    </el-image>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    Goods, User, UserFilled, PriceTag, OfficeBuilding, Files, Picture, Box,
} from '@element-plus/icons-vue';

/**
 * A picture, or — when there is none — an icon that says what the record *is*.
 *
 * The point of the per-type icon is that a missing photo should still tell the
 * reader something. A row with a person's outline reads as an employee whose
 * photo was never uploaded; a row with a generic camera reads as a broken
 * image. Same absent data, two different impressions, and only one of them
 * is true.
 */

const props = defineProps({
    src: { type: String, default: '' },
    /** Decides which icon stands in. Unknown values fall back to a generic picture. */
    type: { type: String, default: 'generic' },
    /** Square side, or the width when `height` is given separately. */
    size: { type: [Number, String], default: 70 },
    height: { type: [Number, String], default: null },
    shape: { type: String, default: 'square' },
    fit: { type: String, default: 'cover' },
    lazy: { type: Boolean, default: true },
    previewSrcList: { type: Array, default: () => [] },
    /** Shows the reason in words as well — for large panels, not table cells. */
    showLabel: { type: Boolean, default: false },
});

const { t } = useI18n();

const ICONS = {
    product: Goods,
    employee: User,
    customer: UserFilled,
    offer: PriceTag,
    warehouse: OfficeBuilding,
    category: Files,
    inventory: Box,
    generic: Picture,
};

/** What the icon is standing in for, said plainly for screen readers and labels. */
const LABEL_KEYS = {
    product: 'no_product_image',
    employee: 'no_employee_photo',
    customer: 'no_customer_photo',
    offer: 'no_offer_image',
    generic: 'there_is_no_photo',
};

const icon = computed(() => ICONS[props.type] || ICONS.generic);

const fallbackLabel = computed(() => t(LABEL_KEYS[props.type] || LABEL_KEYS.generic));

const toCss = (value) => (typeof value === 'number' ? `${value}px` : value);

const boxStyle = computed(() => ({
    width: toCss(props.size),
    height: toCss(props.height ?? props.size),
}));

const shapeClass = computed(() => `entity-image--${props.shape}`);

/** Scaled off the box so one component serves a 40px cell and a 300px panel. */
const iconSize = computed(() => {
    const box = parseInt(String(props.height ?? props.size), 10);
    if (!Number.isFinite(box)) return 24;

    return Math.max(14, Math.min(56, Math.round(box * 0.42)));
});
</script>

<style scoped>
.entity-image {
    display: block;
    overflow: hidden;
    background: #f5f7fa;
}

.entity-image--square {
    border-radius: 10px;
}

.entity-image--circle {
    border-radius: 50%;
}

.entity-image--empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    /* Readable against the tint behind it without competing with real content. */
    color: #a8b1c2;
    border: 1px dashed #dfe4ed;
    text-align: center;
    padding: 0.25rem;
    box-sizing: border-box;
}

.entity-image--loading {
    background: linear-gradient(90deg, #f5f7fa 25%, #eef1f6 37%, #f5f7fa 63%);
    background-size: 400% 100%;
    animation: entity-image-shimmer 1.4s ease infinite;
}

.entity-image__label {
    font-size: 0.78rem;
    line-height: 1.3;
    color: #98a2b3;
}

@keyframes entity-image-shimmer {
    0% { background-position: 100% 50%; }
    100% { background-position: 0 50%; }
}

@media (prefers-reduced-motion: reduce) {
    .entity-image--loading {
        animation: none;
    }
}
</style>
