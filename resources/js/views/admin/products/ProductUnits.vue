<template>
    <div class="product-units-page">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-icon"><el-icon><ScaleToOriginal /></el-icon></div>
            <div class="page-title">
                <h1>{{ t('product_units_management') }}</h1>
                <p>{{ t('pu_subtitle') }}</p>
            </div>
        </div>

        <!-- Product picker -->
        <section class="panel picker-panel">
            <label class="picker-label" for="pu-product-select">{{ t('pu_pick_title') }}</label>
            <el-select
                id="pu-product-select"
                ref="productSelectRef"
                v-model="selectedProductId"
                filterable
                remote
                clearable
                :remote-method="searchProducts"
                :loading="productSearchLoading"
                :placeholder="t('pu_pick_hint')"
                size="large"
                class="product-select"
                popper-class="pu-product-popper"
                @change="selectProduct"
            >
                <template #prefix><el-icon><Search /></el-icon></template>
                <el-option
                    v-for="product in products"
                    :key="product.id"
                    :label="productName(product)"
                    :value="product.id"
                >
                    <div class="product-option">
                        <img :src="productImage(product)" alt="" class="option-thumb" loading="lazy" />
                        <div class="option-text">
                            <span class="option-name">{{ productName(product) }}</span>
                            <span class="option-meta">
                                <span v-if="product.sku" dir="ltr">{{ product.sku }}</span>
                                <span v-if="product.price != null">{{ formatMoney(product.price) }}</span>
                            </span>
                        </div>
                    </div>
                </el-option>
            </el-select>
        </section>

        <!-- Nothing picked yet -->
        <section v-if="!selectedProduct && !productLoading" class="panel intro">
            <div class="intro-art" aria-hidden="true">
                <span class="intro-piece">1</span>
                <el-icon class="intro-arrow"><Right /></el-icon>
                <span class="intro-box">×12</span>
            </div>
            <h2>{{ t('pu_empty_title') }}</h2>
            <p>{{ t('pu_empty_example') }}</p>
        </section>

        <section v-else-if="productLoading" class="panel">
            <el-skeleton :rows="4" animated />
        </section>

        <template v-else>
            <!-- Product summary -->
            <section class="panel product-summary">
                <img :src="productImage(selectedProduct)" alt="" class="summary-thumb" />
                <div class="summary-main">
                    <h2>{{ productName(selectedProduct) }}</h2>
                    <div class="summary-facts">
                        <span v-if="selectedProduct.sku" class="fact">
                            <span class="fact-label">SKU</span>
                            <span dir="ltr">{{ selectedProduct.sku }}</span>
                        </span>
                        <span class="fact">
                            <span class="fact-label">{{ t('pu_base_unit') }}</span>
                            <strong>{{ baseUnitName }}</strong>
                        </span>
                        <span class="fact">
                            <span class="fact-label">{{ t('pu_price') }}</span>
                            <strong>{{ formatMoney(basePrice) }}</strong>
                        </span>
                        <span v-if="selectedProduct.stock_quantity != null" class="fact">
                            <span class="fact-label">{{ t('pu_stock') }}</span>
                            <strong>{{ formatNumber(selectedProduct.stock_quantity) }} {{ baseUnitName }}</strong>
                        </span>
                    </div>
                </div>
                <el-button type="primary" :icon="Plus" size="large" @click="openAddUnitDialog">
                    {{ t('add_unit') }}
                </el-button>
            </section>

            <!-- Units -->
            <section class="panel units-panel" v-loading="unitsLoading">
                <div class="units-head">
                    <h3>{{ t('pu_units_title') }}</h3>
                    <span v-if="units.length" class="count-pill">{{ units.length }}</span>
                </div>

                <div v-if="units.length" class="units-grid">
                    <article
                        v-for="unit in units"
                        :key="unit.id"
                        class="unit-card"
                        :class="{ 'is-default': unit.is_default }"
                    >
                        <header class="unit-card-head">
                            <div class="unit-title">
                                <h4>{{ unitName(unit) }}</h4>
                                <span v-if="unitAltName(unit)" class="unit-alt">{{ unitAltName(unit) }}</span>
                            </div>
                            <el-tooltip v-if="unit.is_default" :content="t('pu_default_hint')" placement="top">
                                <el-tag type="success" effect="dark" round size="small">
                                    <el-icon><StarFilled /></el-icon>
                                    {{ t('pu_default') }}
                                </el-tag>
                            </el-tooltip>
                        </header>

                        <div class="unit-equation">
                            <span class="eq-one">1 {{ unitName(unit) }}</span>
                            <span class="eq-sign">=</span>
                            <span class="eq-qty">{{ formatQty(unit.base_unit_multiplier) }} {{ baseUnitName }}</span>
                        </div>

                        <dl class="unit-facts">
                            <div>
                                <dt>{{ t('pu_unit_price') }}</dt>
                                <dd>
                                    <strong>{{ formatMoney(unitPrice(unit)) }}</strong>
                                    <span class="multiplier" dir="ltr">×{{ formatQty(unit.price_multiplier) }}</span>
                                </dd>
                            </div>
                            <div v-if="priceDelta(unit) !== 0">
                                <dt></dt>
                                <dd>
                                    <span :class="['delta', priceDelta(unit) > 0 ? 'delta-up' : 'delta-down']">
                                        {{ priceDelta(unit) < 0
                                            ? t('pu_saves', { pct: Math.abs(priceDelta(unit)) })
                                            : t('pu_costs_more', { pct: priceDelta(unit) }) }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt>{{ t('barcode') }}</dt>
                                <dd>
                                    <button
                                        v-if="unit.barcode"
                                        type="button"
                                        class="barcode-chip"
                                        :title="t('pu_copy_barcode')"
                                        @click="copyBarcode(unit.barcode)"
                                    >
                                        <el-icon><Ticket /></el-icon>
                                        <span dir="ltr">{{ unit.barcode }}</span>
                                        <el-icon class="copy-icon"><CopyDocument /></el-icon>
                                    </button>
                                    <span v-else class="muted">{{ t('pu_no_barcode') }}</span>
                                </dd>
                            </div>
                        </dl>

                        <footer class="unit-actions">
                            <el-button
                                v-if="!unit.is_default"
                                text
                                size="small"
                                :icon="Star"
                                :loading="defaultingId === unit.id"
                                @click="makeDefault(unit)"
                            >
                                {{ t('pu_make_default') }}
                            </el-button>
                            <span v-else></span>
                            <div class="action-group">
                                <el-tooltip :content="t('edit_unit')" placement="top">
                                    <el-button :icon="Edit" circle size="small" :aria-label="t('edit_unit')" @click="openEditUnitDialog(unit)" />
                                </el-tooltip>
                                <el-tooltip :content="unit.is_default ? t('pu_delete_default_blocked') : t('pu_delete_title')" placement="top">
                                    <span>
                                        <el-button
                                            :icon="Delete"
                                            circle
                                            size="small"
                                            type="danger"
                                            plain
                                            :disabled="unit.is_default"
                                            :aria-label="t('pu_delete_title')"
                                            @click="confirmDeleteUnit(unit)"
                                        />
                                    </span>
                                </el-tooltip>
                            </div>
                        </footer>
                    </article>

                    <button type="button" class="unit-card add-card" @click="openAddUnitDialog">
                        <el-icon :size="26"><Plus /></el-icon>
                        <span>{{ t('add_unit') }}</span>
                    </button>
                </div>

                <div v-else-if="!unitsLoading" class="empty-state">
                    <el-icon :size="44"><Box /></el-icon>
                    <h4>{{ t('pu_none_title') }}</h4>
                    <p>{{ t('pu_none_hint', { unit: baseUnitName }) }}</p>
                    <el-button type="primary" :icon="Plus" @click="openAddUnitDialog">
                        {{ t('add_first_unit') }}
                    </el-button>
                </div>
            </section>
        </template>

        <!-- Add/Edit Unit Dialog -->
        <el-dialog
            v-model="unitDialogVisible"
            :title="isEditMode ? t('edit_unit') : t('add_unit')"
            width="560px"
            class="pu-dialog"
            :close-on-click-modal="false"
            @opened="focusName"
        >
            <el-form
                ref="unitFormRef"
                :model="unitForm"
                :rules="unitRules"
                label-position="top"
                @submit.prevent="saveUnit"
            >
                <div v-if="!isEditMode" class="presets">
                    <span class="presets-label">{{ t('pu_quick_names') }}</span>
                    <button
                        v-for="preset in presets"
                        :key="preset.key"
                        type="button"
                        class="preset-chip"
                        :class="{ active: unitForm.name === preset.en }"
                        @click="applyPreset(preset)"
                    >
                        {{ locale === 'ar' ? preset.ar : preset.en }}
                    </button>
                </div>

                <el-row :gutter="16">
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="t('unit_name')" prop="name" :error="serverErrors.name">
                            <el-input ref="nameInputRef" v-model="unitForm.name" :placeholder="t('enter_unit_name')" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="t('unit_name_arabic')" prop="name_ar" :error="serverErrors.name_ar">
                            <el-input v-model="unitForm.name_ar" :placeholder="t('enter_unit_name_arabic')" dir="rtl" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="16">
                    <el-col :xs="24" :sm="12">
                        <el-form-item
                            :label="t('pu_qty_label', { unit: baseUnitName })"
                            prop="base_unit_multiplier"
                            :error="serverErrors.base_unit_multiplier"
                        >
                            <el-input-number
                                v-model="unitForm.base_unit_multiplier"
                                :min="0.01"
                                :precision="2"
                                :step="1"
                                controls-position="right"
                                class="full-width"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="t('price_multiplier')" prop="price_multiplier" :error="serverErrors.price_multiplier">
                            <el-input-number
                                v-model="unitForm.price_multiplier"
                                :min="0.01"
                                :precision="2"
                                :step="1"
                                :disabled="priceFollowsQty"
                                controls-position="right"
                                class="full-width"
                            />
                        </el-form-item>
                    </el-col>
                </el-row>

                <div class="follow-row">
                    <el-switch v-model="priceFollowsQty" />
                    <div>
                        <div class="follow-title">{{ t('pu_price_follows') }}</div>
                        <div class="form-hint">{{ t('pu_price_follows_hint') }}</div>
                    </div>
                </div>

                <el-form-item :label="t('barcode')" prop="barcode" :error="barcodeError">
                    <el-input v-model="unitForm.barcode" :placeholder="t('enter_barcode')" dir="ltr" clearable>
                        <template #prefix><el-icon><Ticket /></el-icon></template>
                    </el-input>
                    <div class="form-hint">{{ t('pu_scan_hint') }}</div>
                </el-form-item>

                <el-form-item>
                    <el-checkbox v-model="unitForm.is_default">
                        {{ t('set_as_default_unit') }}
                        <span class="form-hint inline">— {{ t('pu_default_hint') }}</span>
                    </el-checkbox>
                </el-form-item>

                <!-- Live preview -->
                <div class="preview">
                    <div class="preview-label">{{ t('pu_preview') }}</div>
                    <div class="preview-line">
                        {{ t('pu_preview_line', {
                            name: formPreviewName,
                            qty: formatQty(unitForm.base_unit_multiplier),
                            unit: baseUnitName,
                        }) }}
                    </div>
                    <div class="preview-price">
                        <strong>{{ formatMoney(formPrice) }}</strong>
                        <span
                            v-if="formDelta !== 0"
                            :class="['delta', formDelta > 0 ? 'delta-up' : 'delta-down']"
                        >
                            {{ formDelta < 0
                                ? t('pu_saves', { pct: Math.abs(formDelta) })
                                : t('pu_costs_more', { pct: formDelta }) }}
                        </span>
                    </div>
                </div>

                <!-- lets Enter submit the form -->
                <button type="submit" hidden></button>
            </el-form>

            <template #footer>
                <el-button @click="unitDialogVisible = false">{{ t('cancel') }}</el-button>
                <el-button type="primary" @click="saveUnit" :loading="savingUnit">
                    {{ isEditMode ? t('update') : t('save') }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { productsApi, productUnitsApi } from '@/api/products';
import { posApi } from '@/api/pos';
import { formatMoney, formatNumber } from '@/utils/currency';
import { getImageUrl } from '@/utils/imageUrl';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
    ScaleToOriginal, Box, Plus, Edit, Delete, Ticket, Search, Right,
    Star, StarFilled, CopyDocument,
} from '@element-plus/icons-vue';

const { t, locale } = useI18n();
const route = useRoute();
const router = useRouter();

// Product picker
const products = ref([]);
const initialProducts = ref([]);
const selectedProductId = ref(null);
const selectedProduct = ref(null);
const productSearchLoading = ref(false);
const productLoading = ref(false);

// Units
const units = ref([]);
const unitsLoading = ref(false);
const defaultingId = ref(null);

// Dialog
const unitDialogVisible = ref(false);
const isEditMode = ref(false);
const editingUnitId = ref(null);
const unitFormRef = ref(null);
const nameInputRef = ref(null);
const savingUnit = ref(false);
const priceFollowsQty = ref(true);
const serverErrors = reactive({});

const unitForm = reactive({
    name: '',
    name_ar: '',
    barcode: '',
    base_unit_multiplier: 1,
    price_multiplier: 1,
    is_default: false,
});

// The usual ways sanitary ware is packed.
const presets = [
    { key: 'box', en: 'Box', ar: 'علبة' },
    { key: 'carton', en: 'Carton', ar: 'كرتونة' },
    { key: 'dozen', en: 'Dozen', ar: 'دزينة', qty: 12 },
    { key: 'pack', en: 'Pack', ar: 'طرد' },
    { key: 'set', en: 'Set', ar: 'طقم' },
    { key: 'roll', en: 'Roll', ar: 'لفة' },
];

const unitRules = {
    name: [{ required: true, message: t('unit_name_required'), trigger: 'blur' }],
    base_unit_multiplier: [
        { required: true, message: t('conversion_factor_required'), trigger: 'blur' },
        { type: 'number', min: 0.01, message: t('conversion_factor_min'), trigger: 'blur' }
    ],
    price_multiplier: [
        { required: true, message: t('price_multiplier_required'), trigger: 'blur' },
        { type: 'number', min: 0.01, message: t('price_multiplier_min'), trigger: 'blur' }
    ],
};

const toNumber = (value) => {
    const parsed = parseFloat(value);
    return Number.isFinite(parsed) ? parsed : 0;
};

// 12.00 reads as 12; 1.50 stays 1.5.
const formatQty = (value) => toNumber(value).toLocaleString(locale.value === 'en' ? 'en-US' : 'ar-SY', {
    maximumFractionDigits: 2,
});

const productName = (product) => (locale.value === 'en'
    ? (product.name_en || product.name_ar)
    : (product.name_ar || product.name_en)) || '';

const productImage = (product) => getImageUrl(product?.image_main);

const unitName = (unit) => (locale.value === 'ar' ? (unit.name_ar || unit.name) : (unit.name || unit.name_ar));
const unitAltName = (unit) => {
    const alt = locale.value === 'ar' ? unit.name : unit.name_ar;
    return alt && alt !== unitName(unit) ? alt : '';
};

const baseUnitName = computed(() => selectedProduct.value?.unit || t('piece'));
const basePrice = computed(() => toNumber(selectedProduct.value?.price));

const unitPrice = (unit) => basePrice.value * toNumber(unit.price_multiplier);

// How far the unit's price strays from buying the same quantity singly, in %.
const deltaPct = (qty, priceMultiplier) => {
    const q = toNumber(qty);
    if (q <= 0) return 0;
    return Math.round(((toNumber(priceMultiplier) - q) / q) * 100);
};
const priceDelta = (unit) => deltaPct(unit.base_unit_multiplier, unit.price_multiplier);

const formPrice = computed(() => basePrice.value * toNumber(unitForm.price_multiplier));
const formDelta = computed(() => deltaPct(unitForm.base_unit_multiplier, unitForm.price_multiplier));
const formPreviewName = computed(() => (locale.value === 'ar'
    ? (unitForm.name_ar || unitForm.name)
    : (unitForm.name || unitForm.name_ar)) || t('pu_new_unit'));

// A barcode the till would resolve to two units of the same product.
const barcodeError = computed(() => {
    if (serverErrors.barcode) return serverErrors.barcode;
    const code = unitForm.barcode?.trim();
    if (!code) return '';
    const clash = units.value.some(u => u.id !== editingUnitId.value && u.barcode === code);
    return clash ? t('pu_barcode_taken') : '';
});

watch(() => unitForm.base_unit_multiplier, (qty) => {
    if (priceFollowsQty.value) unitForm.price_multiplier = qty;
});
watch(priceFollowsQty, (on) => {
    if (on) unitForm.price_multiplier = unitForm.base_unit_multiplier;
});

const listFrom = (res) => {
    const data = res.data?.data || res.data || [];
    return Array.isArray(data) ? data : [];
};

const searchProducts = async (query) => {
    if (!query) {
        products.value = initialProducts.value;
        return;
    }
    productSearchLoading.value = true;
    try {
        products.value = listFrom(await posApi.productLookup({ q: query }));
    } catch (error) {
        console.error('Search error:', error);
    } finally {
        productSearchLoading.value = false;
    }
};

const loadUnits = async () => {
    if (!selectedProductId.value) return;
    unitsLoading.value = true;
    try {
        const res = await productUnitsApi.list(selectedProductId.value);
        units.value = res.data?.data || [];
    } catch (error) {
        console.error('Failed to load units:', error);
        ElMessage.error(t('failed_to_load_units'));
    } finally {
        unitsLoading.value = false;
    }
};

const selectProduct = async (productId) => {
    units.value = [];
    if (!productId) {
        selectedProduct.value = null;
        router.replace({ query: { ...route.query, product: undefined } });
        return;
    }
    selectedProduct.value = products.value.find(p => p.id === productId) || null;
    router.replace({ query: { ...route.query, product: productId } });

    if (!selectedProduct.value) {
        // Opened from a link: the product isn't in the picker list yet.
        productLoading.value = true;
        try {
            const res = await productsApi.getById(productId);
            const product = res.data?.data || res.data;
            selectedProduct.value = product;
            if (product && !products.value.some(p => p.id === product.id)) {
                products.value = [product, ...products.value];
            }
        } catch (error) {
            console.error('Failed to load product:', error);
            selectedProductId.value = null;
            router.replace({ query: { ...route.query, product: undefined } });
            return;
        } finally {
            productLoading.value = false;
        }
    }
    await loadUnits();
};

const clearServerErrors = () => {
    Object.keys(serverErrors).forEach(key => delete serverErrors[key]);
};

const resetForm = () => {
    unitForm.name = '';
    unitForm.name_ar = '';
    unitForm.barcode = '';
    unitForm.base_unit_multiplier = 1;
    unitForm.price_multiplier = 1;
    unitForm.is_default = false;
    editingUnitId.value = null;
    clearServerErrors();
    unitFormRef.value?.clearValidate();
};

const openAddUnitDialog = () => {
    resetForm();
    isEditMode.value = false;
    priceFollowsQty.value = true;
    // The first unit a product gets is the one sales should pick.
    unitForm.is_default = units.value.length === 0;
    unitDialogVisible.value = true;
};

const openEditUnitDialog = (unit) => {
    resetForm();
    isEditMode.value = true;
    editingUnitId.value = unit.id;
    unitForm.name = unit.name;
    unitForm.name_ar = unit.name_ar || '';
    unitForm.barcode = unit.barcode || '';
    unitForm.is_default = unit.is_default;
    priceFollowsQty.value = toNumber(unit.base_unit_multiplier) === toNumber(unit.price_multiplier);
    unitForm.base_unit_multiplier = toNumber(unit.base_unit_multiplier);
    unitForm.price_multiplier = toNumber(unit.price_multiplier);
    unitDialogVisible.value = true;
};

const focusName = () => nameInputRef.value?.focus();

const applyPreset = (preset) => {
    unitForm.name = preset.en;
    unitForm.name_ar = preset.ar;
    if (preset.qty) unitForm.base_unit_multiplier = preset.qty;
};

const errorMessage = (error, fallback) => error.response?.data?.message || fallback;

const saveUnit = async () => {
    if (!unitFormRef.value || savingUnit.value) return;
    try {
        await unitFormRef.value.validate();
    } catch {
        return;
    }
    if (barcodeError.value) return;

    clearServerErrors();
    savingUnit.value = true;
    const payload = { ...unitForm, barcode: unitForm.barcode?.trim() || null };
    try {
        const res = isEditMode.value
            ? await productUnitsApi.update(selectedProductId.value, editingUnitId.value, payload)
            : await productUnitsApi.create(selectedProductId.value, payload);
        ElMessage.success(res.data?.message);
        unitDialogVisible.value = false;
        await loadUnits();
    } catch (error) {
        const errors = error.response?.data?.errors || {};
        Object.entries(errors).forEach(([field, messages]) => {
            serverErrors[field] = Array.isArray(messages) ? messages[0] : messages;
        });
        ElMessage.error(errorMessage(error, t('failed_to_save_unit')));
    } finally {
        savingUnit.value = false;
    }
};

const makeDefault = async (unit) => {
    defaultingId.value = unit.id;
    try {
        await productUnitsApi.update(selectedProductId.value, unit.id, {
            name: unit.name,
            name_ar: unit.name_ar,
            barcode: unit.barcode,
            base_unit_multiplier: unit.base_unit_multiplier,
            price_multiplier: unit.price_multiplier,
            is_default: true,
        });
        ElMessage.success(t('pu_made_default', { name: unitName(unit) }));
        await loadUnits();
    } catch (error) {
        ElMessage.error(errorMessage(error, t('failed_to_save_unit')));
    } finally {
        defaultingId.value = null;
    }
};

const copyBarcode = async (barcode) => {
    try {
        await navigator.clipboard.writeText(barcode);
        ElMessage.success(t('pu_barcode_copied'));
    } catch {
        ElMessage.info(barcode);
    }
};

const confirmDeleteUnit = (unit) => {
    ElMessageBox.confirm(
        t('confirm_delete_unit', { name: unitName(unit) }),
        t('pu_delete_title'),
        {
            confirmButtonText: t('delete'),
            cancelButtonText: t('cancel'),
            confirmButtonClass: 'el-button--danger',
            type: 'warning',
        }
    ).then(() => deleteUnit(unit)).catch(() => {});
};

const deleteUnit = async (unit) => {
    try {
        const res = await productUnitsApi.remove(selectedProductId.value, unit.id);
        ElMessage.success(res.data?.message);
        await loadUnits();
    } catch (error) {
        ElMessage.error(errorMessage(error, t('failed_to_delete_unit')));
    }
};

onMounted(async () => {
    const fromLink = Number(route.query.product) || null;
    try {
        initialProducts.value = listFrom(await posApi.productLookup({ q: '' }));
        products.value = initialProducts.value;
    } catch (error) {
        console.error('Failed to load products:', error);
    }
    if (fromLink) {
        selectedProductId.value = fromLink;
        await selectProduct(fromLink);
    }
});
</script>

<style scoped>
.product-units-page {
    padding: 0;
    max-width: 1200px;
}

.page-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.25rem;
}

.page-icon {
    flex: 0 0 auto;
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: grid;
    place-items: center;
    font-size: 26px;
    color: #fff;
    background: linear-gradient(135deg, #293344 0%, #3d4d63 100%);
    box-shadow: 0 6px 16px rgba(15, 23, 42, .2);
}

.page-title h1 {
    margin: 0;
    font-size: 1.6rem;
    font-weight: 700;
    color: #1f2d3d;
}

.page-title p {
    margin: 0.3rem 0 0;
    color: #5f6d85;
    max-width: 62ch;
}

.panel {
    background: #fff;
    border: 1px solid #e6eaf0;
    border-radius: 14px;
    padding: 1.25rem;
    margin-bottom: 1rem;
    box-shadow: 0 1px 3px rgba(15, 23, 42, .04);
}

/* Picker */
.picker-label {
    display: block;
    font-weight: 600;
    color: #1f2d3d;
    margin-bottom: 0.6rem;
}

.product-select {
    width: 100%;
    max-width: 640px;
}

.product-option {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    line-height: 1.25;
    padding: 4px 0;
}

.option-thumb {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    object-fit: cover;
    background: #f3f5f8;
    flex: 0 0 auto;
}

.option-text {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.option-name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.option-meta {
    display: flex;
    gap: 0.75rem;
    font-size: 0.78rem;
    color: #8492a6;
}

/* Intro */
.intro {
    text-align: center;
    padding: 2.5rem 1.25rem;
}

.intro h2 {
    margin: 1rem 0 0.4rem;
    font-size: 1.15rem;
    color: #1f2d3d;
}

.intro p {
    margin: 0 auto;
    color: #6b7c98;
    max-width: 52ch;
}

.intro-art {
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    color: #94a3b8;
}

.intro-piece,
.intro-box {
    display: grid;
    place-items: center;
    font-weight: 700;
    border-radius: 10px;
    color: #3d4d63;
}

.intro-piece {
    width: 40px;
    height: 40px;
    background: #eef2f7;
}

.intro-box {
    width: 64px;
    height: 56px;
    background: #e3ecfa;
    border: 2px dashed #8fb0e8;
    color: #2f5fb3;
}

.intro-arrow {
    font-size: 22px;
}

[dir='rtl'] .intro-arrow {
    transform: scaleX(-1);
}

/* Product summary */
.product-summary {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.summary-thumb {
    width: 64px;
    height: 64px;
    border-radius: 12px;
    object-fit: cover;
    background: #f3f5f8;
    flex: 0 0 auto;
}

.summary-main {
    flex: 1 1 260px;
    min-width: 0;
}

.summary-main h2 {
    margin: 0 0 0.5rem;
    font-size: 1.15rem;
    color: #1f2d3d;
}

.summary-facts {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.fact {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.85rem;
    padding: 0.3rem 0.65rem;
    background: #f5f7fa;
    border-radius: 999px;
    color: #1f2d3d;
}

.fact-label {
    color: #8492a6;
}

/* Units */
.units-head {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.units-head h3 {
    margin: 0;
    font-size: 1rem;
    color: #1f2d3d;
}

.count-pill {
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.1rem 0.55rem;
    border-radius: 999px;
    background: #eef2f7;
    color: #3d4d63;
}

.units-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
    gap: 1rem;
}

.unit-card {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    padding: 1rem;
    border: 1px solid #e6eaf0;
    border-radius: 12px;
    background: #fff;
    transition: border-color .15s, box-shadow .15s;
}

.unit-card:hover {
    border-color: #c7d2e0;
    box-shadow: 0 4px 14px rgba(15, 23, 42, .06);
}

.unit-card.is-default {
    border-color: #95d475;
    background: linear-gradient(180deg, #f6fbf2 0%, #fff 60%);
}

.unit-card-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.5rem;
}

.unit-title h4 {
    margin: 0;
    font-size: 1.05rem;
    color: #1f2d3d;
}

.unit-alt {
    font-size: 0.8rem;
    color: #8492a6;
}

.unit-equation {
    display: flex;
    align-items: baseline;
    flex-wrap: wrap;
    gap: 0.4rem;
    padding: 0.55rem 0.75rem;
    border-radius: 10px;
    background: #f5f7fa;
    font-size: 0.92rem;
    color: #3d4d63;
}

.eq-sign {
    color: #a0aec0;
}

.eq-qty {
    font-weight: 700;
    color: #1f2d3d;
}

.unit-facts {
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.unit-facts > div {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
}

.unit-facts dt {
    font-size: 0.82rem;
    color: #8492a6;
}

.unit-facts dd {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    min-width: 0;
}

.multiplier {
    font-size: 0.75rem;
    color: #8492a6;
}

.delta {
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.1rem 0.5rem;
    border-radius: 999px;
}

.delta-down {
    background: #ecf8e6;
    color: #3f8a1f;
}

.delta-up {
    background: #fdf2e3;
    color: #b26a00;
}

.barcode-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    max-width: 100%;
    padding: 0.2rem 0.55rem;
    border: 1px dashed #c7d2e0;
    border-radius: 6px;
    background: #fff;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.85rem;
    color: #253358;
    cursor: pointer;
}

.barcode-chip span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.barcode-chip:hover {
    border-color: #409eff;
    color: #409eff;
}

.copy-icon {
    opacity: .5;
}

.muted {
    font-size: 0.85rem;
    color: #a0aec0;
}

.unit-actions {
    margin-top: auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding-top: 0.75rem;
    border-top: 1px solid #f0f2f5;
}

.action-group {
    display: flex;
    gap: 0.4rem;
}

.action-group .el-button + .el-button {
    margin: 0;
}

.add-card {
    align-items: center;
    justify-content: center;
    min-height: 200px;
    border-style: dashed;
    border-width: 2px;
    color: #8492a6;
    font: inherit;
    font-weight: 600;
    cursor: pointer;
    background: #fafbfc;
}

.add-card:hover {
    border-color: #409eff;
    color: #409eff;
    background: #f4f9ff;
}

.empty-state {
    text-align: center;
    padding: 2.5rem 1rem;
    color: #8492a6;
}

.empty-state .el-icon {
    color: #c7d2e0;
}

.empty-state h4 {
    margin: 0.75rem 0 0.35rem;
    font-size: 1rem;
    color: #1f2d3d;
}

.empty-state p {
    margin: 0 auto 1.1rem;
    max-width: 46ch;
}

/* Dialog */
.full-width {
    width: 100%;
}

.presets {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.4rem;
    margin-bottom: 1rem;
}

.presets-label {
    font-size: 0.8rem;
    color: #8492a6;
    margin-inline-end: 0.25rem;
}

.preset-chip {
    font: inherit;
    font-size: 0.82rem;
    padding: 0.25rem 0.75rem;
    border-radius: 999px;
    border: 1px solid #dcdfe6;
    background: #fff;
    color: #3d4d63;
    cursor: pointer;
}

.preset-chip:hover,
.preset-chip.active {
    border-color: #409eff;
    color: #409eff;
    background: #f4f9ff;
}

.follow-row {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    margin: -0.25rem 0 1.1rem;
}

.follow-title {
    font-size: 0.88rem;
    color: #1f2d3d;
}

.form-hint {
    font-size: 0.75rem;
    color: #8492a6;
    line-height: 1.4;
    margin-top: 0.2rem;
}

.form-hint.inline {
    display: inline;
}

.preview {
    border-radius: 10px;
    padding: 0.85rem 1rem;
    background: #f5f7fa;
    border: 1px solid #e6eaf0;
}

.preview-label {
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #8492a6;
    margin-bottom: 0.25rem;
}

.preview-line {
    font-weight: 600;
    color: #1f2d3d;
}

.preview-price {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.25rem;
    color: #3d4d63;
}

@media (max-width: 768px) {
    .page-icon {
        width: 44px;
        height: 44px;
        font-size: 22px;
    }

    .page-title h1 {
        font-size: 1.3rem;
    }

    .panel {
        padding: 1rem;
    }

    .product-summary > .el-button {
        width: 100%;
    }

    .units-grid {
        grid-template-columns: 1fr;
    }

    .add-card {
        min-height: 72px;
        flex-direction: row;
    }
}
</style>

<style>
/* The dialog is teleported to <body>, outside the scoped styles. */
.pu-dialog {
    max-width: calc(100vw - 32px);
}
</style>
