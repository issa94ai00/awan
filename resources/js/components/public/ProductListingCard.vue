<template>
    <!-- A storefront product card: category pages and search results draw
         the same thing, so they share it. -->
    <div v-if="skeleton" class="product-card skeleton-card" aria-hidden="true">
        <div class="skeleton-image"></div>
        <div class="product-info">
            <span class="skeleton-line wide"></span>
            <span class="skeleton-line"></span>
            <span class="skeleton-line short"></span>
        </div>
    </div>
    <div v-else class="product-card">
        <div class="product-image">
            <div class="badges-container">
                <span v-if="!inStock" class="badge badge-out">{{ t('out_of_stock') }}</span>
                <span v-else class="badge badge-in">{{ t('in_stock') }}</span>
            </div>
            <img :src="getImageUrl(product.image_main)" :alt="$p(product, 'name')" loading="lazy" decoding="async">
            <router-link :to="link" class="product-overlay" :aria-label="$p(product, 'name')">
                <span class="view-btn"><i class="fas fa-eye"></i></span>
            </router-link>
        </div>
        <div class="product-info">
            <div class="product-title-row">
                <h3 class="product-title">
                    <router-link :to="link">{{ $p(product, 'name') }}</router-link>
                </h3>
            </div>
            <div class="product-details-row">
                <div class="product-category">{{ $p(product.category, 'name') || $p(fallbackCategory, 'name') }}</div>
                <div v-if="product.brand || product.model" class="product-meta-info">
                    <span v-if="product.brand">{{ product.brand }}</span>
                    <span v-if="product.model">{{ product.model }}</span>
                </div>
                <div v-if="showPrice" class="product-price">
                    <template v-if="priceRange.min !== priceRange.max">
                        <span class="price-from">{{ t('from') }}</span>
                        <span>{{ formatPrice(priceRange.min) }}</span>
                    </template>
                    <span v-else>{{ formatPrice(priceRange.min) }}</span>
                </div>
                <div v-if="options.length > 1" class="product-options-count">
                    <i class="fas fa-layer-group"></i>
                    <span>{{ t('card_options_count', { n: options.length }) }}</span>
                </div>
            </div>
            <div class="product-actions-row">
                <!-- A product sold in several sizes is chosen on its page. -->
                <router-link v-if="options.length > 1" :to="link" class="btn-add-to-cart">
                    <i class="fas fa-sliders-h"></i>
                    <span>{{ t('pd_choose_option') }}</span>
                </router-link>
                <button v-else type="button" class="btn-add-to-cart" :disabled="adding" @click="addToCart">
                    <i :class="adding ? 'fas fa-spinner fa-spin' : 'fas fa-cart-plus'"></i>
                    <span>{{ t('add_to_cart') }}</span>
                </button>
                <a :href="whatsappLink" class="btn-whatsapp" target="_blank" rel="noopener">
                    <i class="fab fa-whatsapp"></i>
                    <span>WhatsApp</span>
                </a>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useSettingsStore } from '@/stores/settings';
import { useCartStore } from '@/stores/cart';
import { getImageUrl } from '@/utils/imageUrl';

const props = defineProps({
    product: { type: Object, default: null },
    /** Named on the card when the product arrives without its category. */
    fallbackCategory: { type: Object, default: null },
    skeleton: { type: Boolean, default: false },
});

/** `added` with the product's display name; `add-failed` when the cart refused. */
const emit = defineEmits(['added', 'add-failed']);

const { t, locale } = useI18n();
const settingsStore = useSettingsStore();
const cartStore = useCartStore();

const link = computed(() => ({
    path: `/product/${props.product.slug}`,
    query: props.product.variant_id ? { variant: props.product.variant_id } : {},
}));

// A product is listed once for all its sizes: the card shows the lowest
// price it sells at ("from" when the sizes differ), and is in stock while
// any size is. A row that already stands for one size has no variants.
// Sizes are its variants, or else the products sharing its name in its
// category (a catalogue imported as one product per size).
const variants = computed(() => (props.product.variant_id ? [] : props.product.variants || []));
const siblings = computed(() => (variants.value.length ? [] : props.product.siblings || []));
const options = computed(() => (variants.value.length ? variants.value : siblings.value));

const priceOf = (v) => (parseFloat(v.price) > 0 ? parseFloat(v.price) : parseFloat(props.product.price) || 0);
const priceRange = computed(() => {
    const prices = variants.value.length
        ? variants.value.map(priceOf).filter(p => p > 0)
        : siblings.value.length
            ? siblings.value.filter(s => s.show_price).map(s => parseFloat(s.price) || 0).filter(p => p > 0)
            : [parseFloat(props.product.price) || 0];
    if (!prices.length) return { min: 0, max: 0 };
    return { min: Math.min(...prices), max: Math.max(...prices) };
});

const inStock = computed(() => {
    if (siblings.value.length) return siblings.value.some(s => s.in_stock);
    return !!props.product.in_stock && (!variants.value.length
        || variants.value.some(v => v.stock_quantity === null || parseInt(v.stock_quantity) > 0));
});

const settings = computed(() => settingsStore.data || {});
const showPrice = computed(() => settings.value.show_product_price === '1'
    && props.product.show_price
    && priceRange.value.min > 0);

// Dollars, as everywhere else in the shop, with thousands grouped.
const formatPrice = (value) => `$${Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const displayName = computed(() => (locale.value === 'en'
    ? (props.product.name_en || props.product.name_ar)
    : props.product.name_ar) || '');

const whatsappLink = computed(() => {
    const number = settings.value.contact_whatsapp || '963900000000';
    return `https://wa.me/${number}?text=${encodeURIComponent(t('catp_whatsapp_text', { name: displayName.value }))}`;
});

const adding = ref(false);
const addToCart = async () => {
    adding.value = true;
    try {
        // A product with a single size adds that size.
        await cartStore.addToCart(props.product.id, 1, props.product.variant_id || variants.value[0]?.id || null);
        emit('added', displayName.value);
    } catch (e) {
        emit('add-failed');
    } finally {
        adding.value = false;
    }
};
</script>

<style scoped>
.product-card {
    background: rgba(255, 255, 255, 0.7) !important;
    backdrop-filter: blur(20px) saturate(160%);
    -webkit-backdrop-filter: blur(20px) saturate(160%);
    border: 1px solid rgba(255, 255, 255, 0.5) !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.03) !important;
    border-radius: 24px !important;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    height: 100%;
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1) !important;
}

.product-card:hover {
    transform: translateY(-8px) scale(1.01) !important;
    background: rgba(255, 255, 255, 0.85) !important;
    box-shadow: 0 20px 40px color-mix(in srgb, var(--mobile-primary) 8%, transparent), 0 15px 30px rgba(0, 0, 0, 0.04) !important;
    border-color: color-mix(in srgb, var(--mobile-primary) 25%, transparent) !important;
}

[data-theme="dark"] .product-card {
    background: rgba(30, 41, 59, 0.45) !important;
    border-color: rgba(255, 255, 255, 0.08) !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2) !important;
}

[data-theme="dark"] .product-card:hover {
    background: rgba(30, 41, 59, 0.6) !important;
    border-color: color-mix(in srgb, var(--mobile-primary) 25%, transparent) !important;
    box-shadow: 0 20px 40px color-mix(in srgb, var(--mobile-primary) 10%, transparent), 0 15px 30px rgba(0, 0, 0, 0.3) !important;
}

.product-image {
    position: relative;
    height: 250px;
    background: #f8fafc;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}

[data-theme="dark"] .product-image {
    background: #1e293b;
}

.product-image img {
    max-height: 100%;
    max-width: 100%;
    object-fit: contain;
    transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1) !important;
}

.product-card:hover .product-image img {
    transform: scale(1.06);
}

.badges-container {
    position: absolute;
    top: 12px;
    right: 12px;
    z-index: 10;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.badge {
    padding: 6px 12px;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.3px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
}

.badge-in {
    background: #e6f4ea;
    color: #137333;
}

[data-theme="dark"] .badge-in {
    background: rgba(19, 115, 51, 0.2);
    color: #81c995;
    border: 1px solid rgba(129, 201, 149, 0.2);
}

.badge-out {
    background: #fce8e6;
    color: #c5221f;
}

[data-theme="dark"] .badge-out {
    background: rgba(197, 34, 31, 0.2);
    color: #f28b82;
    border: 1px solid rgba(242, 139, 130, 0.2);
}

.product-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: color-mix(in srgb, var(--mobile-primary) 20%, transparent);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: all 0.4s ease;
    z-index: 5;
}

.product-card:hover .product-overlay {
    opacity: 1;
}

.view-btn {
    width: 50px;
    height: 50px;
    background: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--el-color-primary);
    font-size: 1.2rem;
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    transform: translateY(20px);
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

.product-card:hover .view-btn {
    transform: translateY(0);
}

.product-info {
    padding: 20px;
    display: flex;
    flex-direction: column;
    flex-grow: 1;
    gap: 12px;
}

.product-title-row {
    margin-bottom: 4px;
}

.product-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 4px 0;
    line-height: 1.4;
    transition: color 0.3s;
}

[data-theme="dark"] .product-title {
    color: #f1f5f9;
}

.product-subtitle {
    font-size: 0.85rem;
    color: #64748b;
    display: block;
}

.product-details-row {
    display: flex;
    flex-direction: column;
    gap: 6px;
    border-top: 1px dashed rgba(0, 0, 0, 0.08);
    padding-top: 12px;
    margin-bottom: 6px;
}

[data-theme="dark"] .product-details-row {
    border-color: rgba(255, 255, 255, 0.08);
}

.product-category {
    font-size: 0.8rem;
    color: var(--mobile-primary);
    font-weight: 600;
}

.product-meta-info {
    font-size: 0.8rem;
    color: #64748b;
    display: flex;
    gap: 8px;
}

.product-meta-info span {
    background: rgba(0, 0, 0, 0.04);
    padding: 2px 8px;
    border-radius: 4px;
}

[data-theme="dark"] .product-meta-info span {
    background: rgba(255, 255, 255, 0.05);
    color: #94a3b8;
}

.product-price {
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--mobile-primary);
    margin-top: 4px;
}

[data-theme="dark"] .product-price {
    color: var(--mobile-primary);
}

.price-from {
    font-size: 0.8rem;
    font-weight: 600;
    color: #64748b;
    margin-inline-end: 4px;
}

.product-options-count {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    color: #64748b;
}

[data-theme="dark"] .price-from,
[data-theme="dark"] .product-options-count {
    color: #94a3b8;
}

.product-actions-row {
    display: flex;
    gap: 10px;
    margin-top: auto;
}

.btn-add-to-cart, .btn-whatsapp {
    padding: 10px 14px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 0.88rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.3s ease;
    cursor: pointer;
    flex: 1;
    border: none;
}

.btn-add-to-cart {
    background: var(--mobile-primary);
    color: white;
}

.btn-add-to-cart:hover {
    background: var(--el-color-primary-light-3);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px color-mix(in srgb, var(--mobile-primary) 20%, transparent);
}

a.btn-add-to-cart {
    text-decoration: none;
}

.btn-whatsapp {
    background: #25d366;
    color: white;
    text-decoration: none;
}

.btn-whatsapp:hover {
    background: #20ba5a;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(37, 211, 102, 0.2);
}

/* Titles are links, so they keep the card's colour until hovered. */
.product-title a {
    color: inherit;
    text-decoration: none;
}

.product-title a:hover {
    color: var(--mobile-primary);
}

.btn-add-to-cart:disabled {
    opacity: 0.7;
    cursor: wait;
    transform: none;
}

/* ── Placeholder while the first page loads ── */
.skeleton-card {
    pointer-events: none;
}

.skeleton-image,
.skeleton-line {
    background: linear-gradient(90deg, rgba(148, 163, 184, 0.14) 25%, rgba(148, 163, 184, 0.26) 37%, rgba(148, 163, 184, 0.14) 63%);
    background-size: 400% 100%;
    animation: skeleton-shimmer 1.4s ease infinite;
}

.skeleton-image {
    height: 250px;
}

.skeleton-line {
    display: block;
    height: 14px;
    width: 60%;
    border-radius: 6px;
}

.skeleton-line.wide { width: 85%; height: 18px; }
.skeleton-line.short { width: 35%; }

@keyframes skeleton-shimmer {
    0% { background-position: 100% 50%; }
    100% { background-position: 0 50%; }
}

@media (prefers-reduced-motion: reduce) {
    .skeleton-image,
    .skeleton-line {
        animation: none;
    }
}
</style>
