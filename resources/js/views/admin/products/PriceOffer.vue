<template>
    <div class="price-offer-page">
        <div class="print-cover"><img :src="'/cover.jpeg'" alt=""></div>
        <header class="offer-toolbar">
            <div class="toolbar-head">
                <div class="toolbar-title">
                    <span class="dot"></span>
                    <h2>{{ $t('price_offer') }}</h2>
                    <span v-if="total > 0" class="badge">{{ total }} {{ $t('product') }}</span>
                    <span v-if="printReadyCount > 0" class="badge selection-badge" :class="{ 'is-empty': printReadyCount === 0 }">
                        {{ $t('selected_products_count', { count: printReadyCount }) }}
                    </span>
                </div>
                <Transition name="status-fade">
                    <span v-if="importMsg" class="saved-msg">{{ importMsg }}</span>
                </Transition>
            </div>

            <div class="toolbar-body">
                <div class="tools-group tools-group-filter">
                    <div class="qbox">
                        <el-icon class="qicon"><Search /></el-icon>
                        <input
                            v-model="searchQuery"
                            type="search"
                            class="qinput"
                            :placeholder="$t('search_for_a_product')"
                            autocomplete="off"
                            @input="onSearchInput"
                        />
                    </div>
                    <el-popover
                        v-model:visible="categoryPopoverVisible"
                        placement="bottom-start"
                        trigger="click"
                        width="260"
                        popper-class="categories-popover"
                    >
                        <template #reference>
                            <button
                                type="button"
                                class="btn-ghost btn-categories"
                                :class="{ active: selectedCategoryIds.length > 0 }"
                            >
                                {{ categoryButtonLabel }}
                            </button>
                        </template>
                        <div class="categories-menu">
                            <div class="categories-menu-header">
                                <p class="categories-menu-title">{{ $t('choose_classifications_to_print') }}</p>
                                <div class="categories-menu-actions">
                                    <button type="button" class="link-btn" @click="selectAllCategories">{{ $t('common.select_all') }}</button>
                                    <button type="button" class="link-btn" @click="clearCategories">{{ $t('clear') }}</button>
                                </div>
                            </div>
                            <div class="cat-search">
                                <el-icon class="cat-search-icon"><Search /></el-icon>
                                <input
                                    v-model="categorySearch"
                                    type="search"
                                    class="cat-search-input"
                                    :placeholder="$t('search_classifications')"
                                    autocomplete="off"
                                />
                            </div>
                            <div class="categories-list">
                                <label v-for="cat in filteredCategoryOptions" :key="cat.id" class="categories-menu-item">
                                    <el-checkbox
                                        :model-value="selectedCategoryIds.includes(cat.id)"
                                        @update:model-value="(val) => toggleCategory(cat.id, val)"
                                    />
                                    <span class="cat-name">{{ cat.name_ar || cat.name }}</span>
                                    <span v-if="cat.product_count !== undefined && cat.product_count !== null" class="cat-count">{{ cat.product_count }}</span>
                                </label>
                                <p v-if="filteredCategoryOptions.length === 0" class="cat-empty">{{ $t('no_matching_classifications') }}</p>
                            </div>
                        </div>
                    </el-popover>
                </div>

                <div class="tools-actions">
                    <div class="tools-group tools-group-edit">
                        <div class="divide-tool">
                            <div class="divwrap">
                                <el-icon class="divwrap-icon"><Operation /></el-icon>
                                <input
                                    v-model="divideValue"
                                    type="number"
                                    min="0.0001"
                                    step="any"
                                    :placeholder="$t('divide_all_prices')"
                                    @keyup.enter="divideAllPrices"
                                />
                                <button type="button" class="btn-divide" @click="divideAllPrices">{{ $t('divide') }}</button>
                            </div>
                            <span v-if="divideOverrideCount > 0" class="divide-applied">
                                {{ $t('divides_applied', { divisor: divideValueApplied }) }}
                            </span>
                            <el-tooltip v-if="divideOverrideCount > 0" :content="$t('clear_divides')" placement="bottom" effect="dark">
                                <button type="button" class="btn-ghost btn-icon divide-clear" @click="clearDivides">
                                    <el-icon><Close /></el-icon>
                                </button>
                            </el-tooltip>
                        </div>
                        <el-tooltip :content="$t('reset_toolbar_tooltip')" placement="bottom" effect="dark">
                            <button type="button" class="btn-ghost btn-icon" @click="resetFilters">
                                <el-icon><Refresh /></el-icon>
                                {{ $t('reset') }}
                            </button>
                        </el-tooltip>
                        <div class="selection-actions">
                            <el-tooltip :content="$t('select_products_for_print')" placement="bottom" effect="dark">
                                <button
                                    type="button"
                                    class="btn-ghost btn-icon"
                                    :class="{ active: deselectedKeys.length === 0 }"
                                    @click="selectAllProducts"
                                >
                                    <el-icon><Select /></el-icon>
                                    {{ $t('common.select_all') }}
                                </button>
                            </el-tooltip>
                            <button v-if="deselectedKeys.length > 0" type="button" class="btn-ghost btn-icon" @click="clearSelection">
                                <el-icon><Close /></el-icon>
                                {{ $t('clear') }}
                            </button>
                            <el-popover
                                v-model:visible="listsPopoverVisible"
                                placement="bottom-end"
                                trigger="click"
                                width="320"
                                popper-class="lists-popover"
                            >
                                <template #reference>
                                    <button type="button" class="btn-ghost btn-icon" :class="{ active: savedLists.length > 0 }" :title="$t('saved_lists_tooltip')" @click="loadLists">
                                        <el-icon><Collection /></el-icon>
                                        {{ $t('saved_lists') }}
                                        <span v-if="savedLists.length > 0" class="lists-badge">{{ savedLists.length }}</span>
                                    </button>
                                </template>
                                <div class="lists-menu" @click.stop>
                                    <p class="lists-menu-title">{{ $t('saved_lists') }}</p>

                                    <div class="lists-create">
                                        <input
                                            v-model="listName"
                                            type="text"
                                            class="lists-name-input"
                                            :placeholder="$t('save_current_selection')"
                                            autocomplete="off"
                                            @keyup.enter="saveCurrentSelection"
                                        />
                                        <button
                                            type="button"
                                            class="btn-list-save"
                                            :disabled="savingList || selectedCount === 0"
                                            @click="saveCurrentSelection"
                                        >
                                            <el-icon v-if="savingList" class="is-loading"><Loading /></el-icon>
                                            <el-icon v-else><Plus /></el-icon>
                                        </button>
                                    </div>

                                    <el-empty
                                        v-if="!loadingLists && savedLists.length === 0"
                                        :image-size="52"
                                        :description="$t('no_saved_lists')"
                                    />
                                    <div v-loading="loadingLists" class="lists-scroll">
                                        <div v-for="lst in savedLists" :key="lst.id" class="lists-item">
                                            <span class="lists-item-name">{{ lst.name_ar || lst.name_en }}</span>
                                            <span class="lists-item-count">{{ lst.items_count }} {{ $t('product') }}</span>
                                            <span class="lists-item-actions">
                                                <el-tooltip :content="$t('load_selection')" placement="top" effect="dark">
                                                    <button type="button" class="lists-act" @click="loadSavedList(lst.id)">
                                                        <el-icon><Download /></el-icon>
                                                    </button>
                                                </el-tooltip>
                                                <el-popconfirm :title="$t('confirm_delete_list')" :confirm-button-text="$t('delete')" :cancel-button-text="$t('cancel')" @confirm="deleteSavedList(lst.id)">
                                                    <template #reference>
                                                        <button type="button" class="lists-act lists-act-danger">
                                                            <el-icon><Delete /></el-icon>
                                                        </button>
                                                    </template>
                                                </el-popconfirm>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </el-popover>
                        </div>
                    </div>

                    <div class="tools-group tools-group-export">
                        <el-popover placement="bottom-end" trigger="click" width="230" popper-class="columns-popover">
                            <template #reference>
                                <button type="button" class="btn-ghost btn-columns btn-icon">
                                    <el-icon><Grid /></el-icon>
                                    {{ $t('print_columns') }}
                                </button>
                            </template>
                            <div class="columns-menu">
                                <p class="columns-menu-title">{{ $t('choose_columns_to_print') }}</p>
                                <label v-for="col in columnOptions" :key="col.key" class="columns-menu-item">
                                    <el-checkbox
                                        :model-value="visibleColumns[col.key]"
                                        :disabled="visibleColumns[col.key] && selectedColumnCount === 1"
                                        @update:model-value="(val) => toggleColumn(col.key, val)"
                                    />
                                    <span>{{ $t(col.label) }}</span>
                                </label>
                            </div>
                        </el-popover>
                        <div class="export-actions">
                            <button type="button" class="btn-pdf" :disabled="printLoading || printReadyCount === 0" @click="printPage">
                                <el-icon v-if="printLoading && prepMode === 'print'" class="is-loading"><Loading /></el-icon>
                                {{ printLoading && prepMode === 'print' ? $t('loading') : $t('print') }}
                            </button>
                            <button type="button" class="btn-pdf-download" :disabled="printLoading || printReadyCount === 0" @click="downloadPdf">
                                <el-icon v-if="printLoading && prepMode === 'pdf'" class="is-loading"><Loading /></el-icon>
                                <el-icon v-else><Download /></el-icon>
                                {{ printLoading && prepMode === 'pdf' ? $t('loading') : $t('download_pdf') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Offline / pending-sync banner: appears only when the connection is
             down or queued offline edits have not been pushed yet. -->
        <div v-if="!isOnline || pendingCount > 0" class="sync-banner screen-only" :class="syncBannerClass">
            <div class="sync-banner-main">
                <span class="sync-banner-icon">
                    <el-icon v-if="!isOnline" :size="18"><Connection /></el-icon>
                    <el-icon v-else-if="syncing" :size="18" class="is-loading"><Loading /></el-icon>
                    <el-icon v-else :size="18"><CircleCheck /></el-icon>
                </span>
                <div class="sync-banner-text">
                    <strong>{{ bannerTitle }}</strong>
                    <span>{{ bannerDetail }}</span>
                </div>
            </div>
            <div class="sync-banner-actions">
                <button v-if="pendingCount > 0" type="button" class="sync-btn" :disabled="syncing" @click="retrySync">
                    <el-icon v-if="!syncing" :size="13"><Refresh /></el-icon>
                    <el-icon v-else :size="13" class="is-loading"><Loading /></el-icon>
                    {{ syncing ? $t('syncing') : $t('sync_now') }}
                </button>
                <button v-if="pendingCount > 0" type="button" class="sync-btn sync-btn-danger" @click="confirmDiscardPending">
                    <el-icon :size="13"><Delete /></el-icon>
                    {{ $t('discard_pending_changes') }}
                </button>
            </div>
        </div>

        <div v-if="total > 0" class="summary-strip screen-only">
            <div class="summary-item">
                <span class="summary-value">{{ total.toLocaleString('en-US') }}</span>
                <span class="summary-label">{{ $t('in_table') }}</span>
            </div>
            <div class="summary-divider"></div>
            <div class="summary-item" :class="{ 'is-muted': printReadyCount === 0 }">
                <span class="summary-value">{{ printReadyCount.toLocaleString('en-US') }}</span>
                <span class="summary-label">{{ $t('selected_totals') }}</span>
            </div>
            <div class="summary-divider"></div>
            <div class="summary-item is-price" :class="{ 'is-muted': printReadyCount === 0 }">
                <span v-if="printReadyCount > 0" class="summary-value">{{ formatSummaryPrice(printReadySum) }}</span>
                <span v-else class="summary-value">—</span>
                <span class="summary-label">{{ $t('sum_of_selected') }}</span>
            </div>
            <p v-if="deselectedKeys.length > 0" class="summary-note">
                {{ $t('selected_count_note') }}
            </p>
        </div>

        <div v-if="selectedCategoryIds.length" class="active-filters screen-only">
            <span class="active-filters-label">{{ $t('classifications') }}:</span>
            <span v-for="id in selectedCategoryIds" :key="id" class="filter-chip">
                {{ categoryNameById(id) }}
                <button type="button" class="filter-chip-remove" :aria-label="$t('clear')" @click="toggleCategory(id, false)">×</button>
            </span>
            <button type="button" class="filter-clear-all" @click="clearCategories">{{ $t('clear') }}</button>
        </div>

        <div v-loading="loading" class="offer-table-wrap screen-only">
            <ProductOfferTable :groups="groupedProducts" :loading="loading" :editing-id="editingId" :edit-value="editValue" :visible-columns="visibleColumns"
                :editing-stock-id="editingStockId" :edit-stock-value="editStockValue" :item-status="itemStatus"
                :selected-keys="deselectedKeys"
                :all-selected="allSelected"
                :some-selected="someSelected"
                @toggle-all="toggleSelectAll"
                @toggle-select="toggleSelected"
                @start-edit="startEdit" @commit-edit="commitEdit" @cancel-edit="cancelEdit"
                @start-edit-stock="startEditStock" @commit-edit-stock="commitEditStock" @cancel-edit-stock="cancelEditStock"
                @edit-item="openEditItemDialog"
                @update-image="updateItemImage" @clear-image="clearItemImage"
                @add-variant="openAddVariantDialog" />
            <div v-if="selectedCount === 0" class="selection-empty screen-only">
                <el-icon><WarningFilled /></el-icon>
                {{ $t('no_products_selected_for_print') }}
            </div>
        </div>

        <!-- Print/PDF-only table: holds every product across every page, grouped by product name -->
        <div class="offer-table-wrap print-only" :class="{ 'pdf-render': pdfRendering }">
            <ProductOfferTable ref="printTableRef" :groups="printGroups" :loading="false" :editing-id="null" :edit-value="''" print-mode :visible-columns="visibleColumns" />
        </div>

        <!-- Print/PDF-prep overlay: shows progress while every page is pulled, images are loaded, and (for PDF) pages are rendered -->
        <Teleport to="body">
            <Transition name="print-prep-fade">
                <div v-if="printLoading" class="print-prep-overlay screen-only" role="alert" aria-live="assertive">
                    <div class="print-prep-card">
                        <el-icon class="print-prep-spinner is-loading"><Loading /></el-icon>
                        <h3>{{ prepMode === 'pdf' ? $t('preparing_pdf') : $t('preparing_print') }}</h3>
                        <el-progress
                            :percentage="printProgressPercent"
                            :stroke-width="10"
                            :show-text="false"
                            color="#c00000"
                        />
                        <p class="print-prep-count">
                            <template v-if="exportPhase === 'images'">
                                {{ $t('loading_images_progress', { loaded: printProgress.loaded, total: printProgress.total }) }}
                            </template>
                            <template v-else-if="exportPhase === 'render'">
                                {{ $t('rendering_pdf_progress', { loaded: printProgress.loaded, total: printProgress.total }) }}
                            </template>
                            <template v-else>
                                {{ printProgress.total > 0
                                    ? $t('preparing_print_progress', { loaded: printProgress.loaded, total: printProgress.total })
                                    : $t('preparing_print_progress_unknown') }}
                            </template>
                        </p>
                        <button v-if="exportPhase !== 'render'" type="button" class="print-prep-cancel" @click="cancelPrintPrep">
                            <el-icon><Close /></el-icon>
                            {{ $t('cancel') }}
                        </button>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <el-dialog
            v-model="addVariantVisible"
            :title="$t('add_variant')"
            width="640px"
            :close-on-click-modal="false"
            append-to-body
        >
            <div class="add-variant-subject">
                <EntityImage
                    :src="addVariantTarget?.product?.image_main"
                    type="product"
                    :size="42"
                    shape="square"
                    :lazy="false"
                />
                <span class="add-variant-subject-name">
                    {{ addVariantTarget?.product?.name_ar || addVariantTarget?.product?.name_en }}
                </span>
            </div>
            <el-form ref="addVariantFormRef" :model="addVariantForm" :rules="addVariantRules" label-position="top">
                <el-row :gutter="16">
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('product_code_sku')" prop="sku">
                            <el-input v-model="addVariantForm.sku" placeholder="SKU-001" clearable>
                                <template #append>
                                    <el-button :icon="MagicStick" :loading="variantSkuLoading" @click="generateVariantSku" />
                                </template>
                            </el-input>
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('barcode')">
                            <el-input v-model="addVariantForm.barcode" placeholder="Barcode" clearable />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :xs="24" :sm="8">
                        <el-form-item :label="$t('size')">
                            <el-input v-model="addVariantForm.size" :placeholder="$t('size')" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="8">
                        <el-form-item :label="$t('the_color')">
                            <el-input v-model="addVariantForm.color" :placeholder="$t('the_color')" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="8">
                        <el-form-item :label="$t('material')">
                            <el-input v-model="addVariantForm.material" :placeholder="$t('material')" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :xs="24" :sm="8">
                        <el-form-item :label="$t('the_price')" prop="price">
                            <el-input-number v-model="addVariantForm.price" :min="0" :precision="5" :step="0.01" style="width:100%" controls-position="right" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="8">
                        <el-form-item :label="$t('cost_price')">
                            <el-input-number v-model="addVariantForm.cost_price" :min="0" :precision="5" :step="0.01" style="width:100%" controls-position="right" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="8">
                        <el-form-item :label="$t('stock_quantity')" prop="stock_quantity">
                            <el-input-number v-model="addVariantForm.stock_quantity" :min="0" :precision="0" style="width:100%" controls-position="right" />
                        </el-form-item>
                    </el-col>
                </el-row>
            </el-form>
            <template #footer>
                <el-button @click="addVariantVisible = false">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" :loading="addVariantSaving" :icon="Check" @click="saveAddVariant">
                    {{ $t('add_variant') }}
                </el-button>
            </template>
        </el-dialog>

        <el-dialog
            v-model="editItemVisible"
            :title="$t('edit_item')"
            width="640px"
            :close-on-click-modal="false"
            :before-close="handleEditItemClose"
            append-to-body
        >
            <div class="add-variant-subject">
                <EntityImage
                    :src="editItemTarget?.group?.product?.image_main"
                    type="product"
                    :size="42"
                    shape="square"
                    :lazy="false"
                />
                <span class="add-variant-subject-name">
                    {{ editItemTarget?.group?.product?.name_ar || editItemTarget?.group?.product?.name_en }}
                </span>
                <span v-if="editItemIsVariant" class="edit-item-kind">{{ $t('variant') }}</span>
            </div>
            <el-alert
                v-if="editItemIsVariant"
                :title="$t('item_name_applies_to_all_lines')"
                type="info"
                :closable="false"
                show-icon
                class="edit-item-hint"
            />
            <el-form ref="editItemFormRef" :model="editItemForm" :rules="editItemRules" label-position="top">
                <el-row :gutter="16">
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('product_name_ar')" prop="name_ar" :error="editItemServerErrors.name_ar">
                            <el-input v-model="editItemForm.name_ar" :placeholder="$t('product_name_ar')" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('product_name_en')" prop="name_en" :error="editItemServerErrors.name_en">
                            <el-input v-model="editItemForm.name_en" :placeholder="$t('product_name_en')" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('product_code_sku')" prop="sku" :error="editItemServerErrors.sku">
                            <el-input v-model="editItemForm.sku" placeholder="SKU-001" clearable>
                                <template #append>
                                    <el-button :icon="MagicStick" :loading="variantSkuLoading" @click="generateEditVariantSku" />
                                </template>
                            </el-input>
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item :label="$t('barcode')" :error="editItemServerErrors.barcode">
                            <el-input v-model="editItemForm.barcode" placeholder="Barcode" clearable />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :xs="24" :sm="8">
                        <el-form-item :label="$t('size')" :error="editItemServerErrors.size">
                            <el-input v-model="editItemForm.size" :placeholder="$t('size')" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="8">
                        <el-form-item :label="$t('the_color')" :error="editItemServerErrors.color">
                            <el-input v-model="editItemForm.color" :placeholder="$t('the_color')" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="8">
                        <el-form-item :label="editItemIsVariant ? $t('material') : $t('unit')" :error="editItemServerErrors.unit">
                            <el-input v-model="editItemForm.unit" :placeholder="editItemIsVariant ? $t('material') : $t('unit')" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :xs="24" :sm="8">
                        <el-form-item :label="$t('the_price')" prop="price" :error="editItemServerErrors.price">
                            <el-input-number v-model="editItemForm.price" :min="0" :precision="5" :step="0.01" style="width:100%" controls-position="right" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="8">
                        <el-form-item :label="$t('cost_price')" :error="editItemServerErrors.cost_price">
                            <el-input-number v-model="editItemForm.cost_price" :min="0" :precision="5" :step="0.01" style="width:100%" controls-position="right" />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="8">
                        <el-form-item :label="$t('stock_quantity')" prop="stock_quantity" :error="editItemServerErrors.stock_quantity">
                            <el-input-number v-model="editItemForm.stock_quantity" :min="0" :precision="0" style="width:100%" controls-position="right" />
                        </el-form-item>
                    </el-col>
                </el-row>
            </el-form>
            <template #footer>
                <el-button @click="closeEditItemDialog">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" :loading="editItemSaving" :icon="Check" @click="saveEditItem">
                    {{ $t('common.save') }}
                </el-button>
            </template>
        </el-dialog>

        <div v-if="total > 0" class="pagination-wrapper screen-only">
            <el-pagination
                v-model:current-page="currentPage"
                v-model:page-size="pageSize"
                :total="total"
                :page-sizes="[20, 50, 100, 200]"
                layout="total, sizes, prev, pager, next, jumper"
                background
                @size-change="onSizeChange"
                @current-change="onPageChange"
            />
        </div>
    </div>
</template>

<script setup>
import ProductOfferTable from '@/components/admin/products/ProductOfferTable.vue';
import EntityImage from '@/components/admin/EntityImage.vue';
import { useI18n } from 'vue-i18n';
import { ref, computed, reactive, onMounted, nextTick, watch } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useProductsStore } from '@/stores/products';
import { productsApi, priceOfferListsApi } from '@/api/products';
import { waitForImages, renderTableToPdf } from '@/utils/pdfExport';
import { useOfflineSync } from '@/Composables/useOfflineSync';
import { Search, Loading, Close, Download, Refresh, Operation, Grid, Select, WarningFilled, Collection, Plus, Delete, Connection, Clock, CircleCheck, MagicStick, Check } from '@element-plus/icons-vue';

const { t } = useI18n();
const store = useProductsStore();

const searchQuery = ref('');
// Multi-classification filter — empty means every classification is included.
const selectedCategoryIds = ref([]);
const categorySearch = ref('');
const categoryPopoverVisible = ref(false);
// Per-product print selection. Tracks excluded group keys (see the selection
// helpers below); empty means every product is selected/included. The last
// used selection is remembered across reloads via localStorage.
const SELECTION_STORAGE_KEY = 'price_offer_selection';
function loadStoredSelection() {
    try {
        const raw = localStorage.getItem(SELECTION_STORAGE_KEY);
        if (!raw) return [];
        const parsed = JSON.parse(raw);
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}
const deselectedKeys = ref(loadStoredSelection());
const currentPage = ref(1);
const pageSize = ref(50);
const divideValue = ref(null);

// Saved print-selection lists. A list is a named set of item ids
// (`p-{productId}` / `v-{variantId}`) the admin can reload to re-apply a
// product selection instead of picking rows from scratch each time.
const savedLists = ref([]);
const listsPopoverVisible = ref(false);
const loadingLists = ref(false);
const savingList = ref(false);
const listName = ref('');

// Price/stock overrides keyed by row id (`p-{productId}` or `v-{variantId}`).
// They keep the screen table, the print/PDF table and the CSV export all
// showing the same edited value regardless of which one was fetched most
// recently, while `saveField` below pushes the same edit to the database in
// the background.
const overrides = ref({});
const stockOverrides = ref({});

const editingId = ref(null);
const editValue = ref('');

const editingStockId = ref(null);
const editStockValue = ref('');

// Per-row autosave status for the inline editors: 'saving' | 'saved' | 'error'
// | 'pending' (queued offline, awaiting sync).
const itemStatus = ref({});

function flashStatus(id, state) {
    itemStatus.value = { ...itemStatus.value, [id]: state };
    if (state === 'saved') {
        setTimeout(() => {
            if (itemStatus.value[id] === 'saved') {
                const next = { ...itemStatus.value };
                delete next[id];
                itemStatus.value = next;
            }
        }, 2000);
    }
}

// ---- Offline-aware autosave -----------------------------------------------
// Edits made while the connection is down (or a request fails on the way out)
// are queued in localStorage and replayed automatically once the browser
// reports back online — prices stock and details are never silently dropped.

// Sends one queued row change to the right endpoint, then reflects the server's
// saved record back into the store so the strikethrough "original" value stays
// honest. Reused by both the live editor (saveField) and the offline replay.
async function sendChange(entry) {
    const [type, rawId] = String(entry.id).split('-');
    const res = type === 'v'
        ? await productsApi.updateVariant(rawId, entry.patch)
        : await productsApi.update(rawId, entry.patch);
    applyServerRecord(type, rawId, res.data.data);
}

const {
    isOnline,
    queue: syncQueue,
    pendingCount,
    syncing,
    enqueue,
    clear,
    syncPending,
    isNetworkError,
} = useOfflineSync({
    send: sendChange,
    onSynced: (count) => flashMsg(t('offline_changes_synced', { count })),
    onPermanentError: (entry, err) => {
        ElMessage.error(err.response?.data?.message || t('sync_failed'));
    },
});

// Keep the cell status badges in step with the pending queue: rows with queued
// edits show a persistent amber "waiting" indicator until they actually sync.
const pendingIds = computed(() => new Set(syncQueue.value.map((e) => String(e.id))));

function applyPendingStatuses() {
    const ids = pendingIds.value;
    const next = { ...itemStatus.value };
    Object.keys(next).forEach((k) => {
        if (next[k] === 'pending' && !ids.has(k)) delete next[k];
    });
    ids.forEach((id) => { next[id] = 'pending'; });
    itemStatus.value = next;
}

watch(pendingIds, applyPendingStatuses);
watch(isOnline, (online) => {
    if (online && pendingCount.value > 0) syncPending();
});

// Persists a single row's edited field(s) straight to the product or variant
// record behind it — `id` is `p-{productId}` or `v-{variantId}`, so the right
// endpoint is picked from its prefix. Fired only from the per-cell editors
// (price, count, size/color/unit) when the cell is committed — never from the
// bulk "divide all prices" tool, which stays a local print-preview action.
//
// Returns what actually happened, because "queued offline" and "rejected by
// the server" are different answers and the edit dialog has to tell them
// apart: it used to close and report success on all three.
//
// @returns {Promise<{ok: boolean, queued: boolean, error: unknown}>}
async function saveField(id, patch) {
    // Offline edits go straight to the queue instead of attempting a request
    // that is guaranteed to fail.
    if (!isOnline.value) {
        enqueue(id, patch);
        applyPendingStatuses();
        flashMsg(t('change_queued_offline'));
        return { ok: true, queued: true, error: null };
    }
    flashStatus(id, 'saving');
    try {
        const [type, rawId] = String(id).split('-');
        const res = type === 'v'
            ? await productsApi.updateVariant(rawId, patch)
            : await productsApi.update(rawId, patch);
        applyServerRecord(type, rawId, res.data.data);
        flashStatus(id, 'saved');
        return { ok: true, queued: false, error: null };
    } catch (err) {
        // A request that fails without an HTTP response means the connection
        // dropped mid-flight — keep the change, sync it when we're back online.
        if (isNetworkError(err)) {
            enqueue(id, patch);
            applyPendingStatuses();
            flashMsg(t('change_queued_offline'));
            return { ok: true, queued: true, error: null };
        }
        flashStatus(id, 'error');
        ElMessage.error(fieldErrorMessage(err) || t('failed_to_save'));
        return { ok: false, queued: false, error: err };
    }
}

// The two endpoints report a rejected field differently: the product route
// returns Laravel's standard 422 body, the variant route wraps it in its own
// envelope whose `message` is the generic "خطأ في التحقق من البيانات". Either
// way the useful sentence is the first field error, so prefer it over the
// summary.
function serverFieldErrors(error) {
    const errors = error?.response?.data?.errors;

    if (!errors || typeof errors !== 'object') return {};

    return Object.fromEntries(
        Object.entries(errors).map(([field, messages]) => [
            field,
            Array.isArray(messages) ? String(messages[0]) : String(messages),
        ])
    );
}

function fieldErrorMessage(error) {
    const first = Object.values(serverFieldErrors(error))[0];

    return first || error?.response?.data?.message || '';
}

// The image cell's uploader swaps the owning product's main picture. The
// group carries its base product, so both change and removal write straight
// to that product — through the same save path as the other inline editors,
// which keeps the save/offline badges and the store copy in step.
async function updateItemImage(group, path) {
    await saveField(`p-${group.product.id}`, { image_main: path || null });
}

async function clearItemImage(group) {
    await saveField(`p-${group.product.id}`, { image_main: null });
}

// ---- Add a variant from the price list -----------------------------------
// The row editors only touch existing records; a brand-new line item (another
// size/color under the same product) needs its own small form. Creation has to
// reach the server — it owns the new variant's id — so unlike field edits it
// is refused while offline instead of being silently queued.
const addVariantVisible = ref(false);
const addVariantSaving = ref(false);
const variantSkuLoading = ref(false);
const addVariantFormRef = ref(null);
const addVariantTarget = ref(null); // the product group being extended

const emptyVariantForm = () => ({
    sku: '',
    barcode: '',
    size: '',
    color: '',
    material: '',
    price: 0,
    cost_price: null,
    stock_quantity: 0,
});
const addVariantForm = reactive(emptyVariantForm());
const addVariantRules = {
    sku: [{ required: true, message: t('sku_required'), trigger: 'blur' }],
    price: [{ required: true, message: t('price_required'), trigger: 'blur' }],
    stock_quantity: [{ required: true, message: t('quantity_of_inventory_required'), trigger: 'blur' }],
};

// Suggest a code that keeps the product's own SKU family together (PROD-1 →
// PROD-2) instead of inventing an unrelated one.
function suggestVariantSku(product) {
    const base = (product.sku || '').trim() || 'variant';
    const prefix = base.includes('-') ? base.replace(/-\d+$/, '') : base;
    const count = Array.isArray(product.variants) ? product.variants.length : 0;
    return `${prefix}-${count + 1}`;
}

function openAddVariantDialog(group) {
    if (!isOnline.value) {
        ElMessage.warning(t('add_variant_requires_connection'));
        return;
    }
    addVariantTarget.value = group;
    Object.assign(addVariantForm, {
        ...emptyVariantForm(),
        sku: suggestVariantSku(group.product),
        price: parseFloat(group.product.price) || 0,
        cost_price: group.product.cost_price != null ? parseFloat(group.product.cost_price) : null,
        stock_quantity: group.product.stock_quantity ?? 0,
    });
    nextTick(() => addVariantFormRef.value?.clearValidate());
    addVariantVisible.value = true;
}

const generateVariantSku = async () => {
    variantSkuLoading.value = true;
    try {
        const { data } = await productsApi.nextSku();
        addVariantForm.sku = data?.data?.sku || addVariantForm.sku;
    } catch {
        ElMessage.error(t('failed_to_generate_a_code'));
    } finally {
        variantSkuLoading.value = false;
    }
};

// The printed table merges rows that share size/color/unit, so a variant that
// duplicates the owning group's existing details would silently disappear on
// paper — refuse it up front instead of letting it confuse people later.
function unitDetailKey() {
    const v = addVariantForm;
    return [v.size, v.color, v.material].map((s) => String(s || '').trim()).join('|');
}

async function saveAddVariant() {
    if (!addVariantFormRef.value || !addVariantTarget.value) return;
    const group = addVariantTarget.value;
    const already = group.items.some((item) =>
        [item.size, item.color, item.unit].map((s) => String(s || '').trim()).join('|') === unitDetailKey()
    );
    if (already) {
        ElMessage.warning(t('duplicate_variant_warning'));
        return;
    }
    try {
        await addVariantFormRef.value.validate();
    } catch {
        return; // el-form already highlights the offending fields
    }

    addVariantSaving.value = true;
    const payload = {
        product_id: group.product.id,
        sku: String(addVariantForm.sku).trim(),
        barcode: String(addVariantForm.barcode).trim() || null,
        size: String(addVariantForm.size).trim(),
        color: String(addVariantForm.color).trim(),
        material: String(addVariantForm.material).trim(),
        price: addVariantForm.price ?? 0,
        cost_price: addVariantForm.cost_price || null,
        stock_quantity: addVariantForm.stock_quantity ?? 0,
    };
    try {
        const res = await productsApi.createVariant(payload);
        const variant = res.data?.data;
        // Drop the created record straight into the store so the screen table
        // (computed off store.products) shows the new row instantly. The print
        // catalog re-fetches everything on its own, so no separate sync needed.
        const product = store.products.find((p) => String(p.id) === String(group.product.id));
        if (product) {
            if (!Array.isArray(product.variants)) product.variants = [];
            product.variants.push(variant);
        }
        addVariantVisible.value = false;
        flashMsg(t('variant_added_successfully'));
    } catch (err) {
        const errs = err?.response?.data?.errors;
        if (errs && Object.keys(errs).length) {
            const [field, messages] = Object.entries(errs)[0];
            ElMessage.error(`${field}: ${messages[0]}`);
        } else {
            ElMessage.error(err?.response?.data?.message || t('failed_to_save_variant'));
        }
    } finally {
        addVariantSaving.value = false;
    }
}

// ---- Edit an item (names + variant/product details) ----------------------
// One dialog replaces the old three-field size/color/unit popover. A row is
// either a variant line (`v-{id}`, backed by a ProductVariant) or the base
// product line (`p-{id}`). The names always belong to the owning product, so
// the dialog edits them on the product while the numeric/label fields go to
// whichever record the row maps to — through the same saveField pipeline the
// inline editors use, so online/offline/status handling stays consistent.
const editItemVisible = ref(false);
const editItemSaving = ref(false);
const editItemFormRef = ref(null);
const editItemTarget = ref(null); // { group, item }
const editItemIsVariant = ref(false);
// Field errors the server rejected the last save with, shown under the input
// that caused them instead of as one anonymous toast.
const editItemServerErrors = ref({});
// The form as it was opened, so an accidental Escape can be caught before it
// throws away typed work.
const editItemBaseline = ref(null);

const emptyEditItemForm = () => ({
    name_ar: '',
    name_en: '',
    sku: '',
    barcode: '',
    size: '',
    color: '',
    unit: '',
    price: 0,
    cost_price: null,
    stock_quantity: 0,
});
const editItemForm = reactive(emptyEditItemForm());
const editItemRules = computed(() => ({
    name_ar: [{ required: true, message: t('name_required'), trigger: 'blur' }],
    price: [{ required: true, message: t('price_required'), trigger: 'blur' }],
    stock_quantity: [{ required: true, message: t('quantity_of_inventory_required'), trigger: 'blur' }],
    ...(editItemIsVariant.value
        ? { sku: [{ required: true, message: t('sku_required'), trigger: 'blur' }] }
        : {}),
}));

// The row object the table hands us carries only what the cells display, so
// the missing fields are pulled from the real product/variant record.
//
// Read out of the store rather than out of the captured group: after a save
// that only half succeeded the dialog stays open, and the baseline for "what
// changed" has to be what the server now holds — otherwise pressing save again
// re-sends the fields that already went through.
function editRowSource(group, item) {
    const product = store.products.find((p) => String(p.id) === String(group.product.id))
        || group.product;

    if (String(item.id).startsWith('v-')) {
        const vid = Number(String(item.id).slice(2));
        const variants = Array.isArray(product.variants) ? product.variants : [];
        return variants.find((v) => Number(v.id) === vid) || {};
    }

    return product;
}

// The product the names belong to — the same live lookup, since a variant row
// edits its parent's names.
function editRowProduct(group) {
    return store.products.find((p) => String(p.id) === String(group.product.id)) || group.product;
}

function openEditItemDialog(group, item) {
    editItemTarget.value = { group, item };
    editItemIsVariant.value = String(item.id).startsWith('v-');
    const source = editRowSource(group, item);
    const product = editRowProduct(group);
    Object.assign(editItemForm, {
        ...emptyEditItemForm(),
        name_ar: product.name_ar || '',
        name_en: product.name_en || '',
        sku: source.sku || '',
        barcode: source.barcode || '',
        size: source.size || (editItemIsVariant.value ? '' : product.size) || '',
        color: source.color || (editItemIsVariant.value ? '' : product.color) || '',
        unit: source.unit || source.material || (editItemIsVariant.value ? '' : product.unit) || '',
        price: parseFloat(source.price ?? product.price) || 0,
        cost_price: source.cost_price != null ? parseFloat(source.cost_price) : null,
        stock_quantity: source.stock_quantity ?? product.stock_quantity ?? 0,
    });
    editItemServerErrors.value = {};
    editItemBaseline.value = { ...editItemForm };
    nextTick(() => editItemFormRef.value?.clearValidate());
    editItemVisible.value = true;
}

// A typed value and the value it started as, compared the way the user sees
// them: 0 and '0' are the same answer, and trailing spaces are not a change.
const sameFieldValue = (a, b) => {
    if (a == null && b == null) return true;

    return String(a ?? '').trim() === String(b ?? '').trim();
};

const editItemDirty = computed(() => {
    const baseline = editItemBaseline.value;
    if (!baseline) return false;

    return Object.keys(editItemForm).some((key) => !sameFieldValue(editItemForm[key], baseline[key]));
});

// Any keystroke clears the standing server errors: they describe the values
// that were rejected, not the ones now on screen.
watch(editItemForm, () => {
    if (Object.keys(editItemServerErrors.value).length) editItemServerErrors.value = {};
}, { deep: true });

async function handleEditItemClose(done) {
    if (!editItemDirty.value || editItemSaving.value) {
        done();
        return;
    }

    try {
        await ElMessageBox.confirm(
            t('confirm_discard_item_changes_message'),
            t('confirm_discard_item_changes_title'),
            {
                confirmButtonText: t('discard_pending_changes'),
                cancelButtonText: t('common.cancel'),
                type: 'warning',
                confirmButtonClass: 'el-button--danger',
            }
        );
        done();
    } catch {
        // Cancelled — the dialog stays open with the typed values intact.
    }
}

function closeEditItemDialog() {
    handleEditItemClose(() => { editItemVisible.value = false; });
}

const generateEditVariantSku = async () => {
    variantSkuLoading.value = true;
    try {
        const { data } = await productsApi.nextSku();
        editItemForm.sku = data?.data?.sku || editItemForm.sku;
    } catch {
        ElMessage.error(t('failed_to_generate_a_code'));
    } finally {
        variantSkuLoading.value = false;
    }
};

// Only changed fields are sent, so a renamed product doesn't also rebalance
// its stock (stock_quantity on a product is booked as a warehouse adjustment)
// or re-validate unchanged skus.
function changedPatch(source) {
    const patch = {};
    const f = editItemForm;
    const compare = {
        sku: source.sku || '',
        barcode: source.barcode || '',
        size: source.size || '',
        color: source.color || '',
        unit: source.unit || source.material || '',
        price: parseFloat(source.price) || 0,
        cost_price: source.cost_price != null ? parseFloat(source.cost_price) : null,
        // The API hands counts back as strings, so comparing them to the
        // number the stepper holds made every save look like a recount — and a
        // product's stock_quantity is not a field edit but a warehouse
        // adjustment, which is not something a rename should trigger.
        stock_quantity: Number(source.stock_quantity ?? 0),
    };
    if (String(f.sku).trim() !== compare.sku) patch.sku = String(f.sku).trim() || null;
    if (String(f.barcode).trim() !== compare.barcode) patch.barcode = String(f.barcode).trim() || null;
    if (String(f.size).trim() !== compare.size) patch.size = String(f.size).trim();
    if (String(f.color).trim() !== compare.color) patch.color = String(f.color).trim();
    if (String(f.unit).trim() !== compare.unit) patch.unit = String(f.unit).trim();
    if ((parseFloat(f.price) || 0) !== compare.price) patch.price = parseFloat(f.price) || 0;
    if ((f.cost_price != null ? parseFloat(f.cost_price) : null) !== compare.cost_price) {
        patch.cost_price = f.cost_price != null ? parseFloat(f.cost_price) : null;
    }
    if (Number(f.stock_quantity ?? 0) !== compare.stock_quantity) {
        patch.stock_quantity = Number(f.stock_quantity ?? 0);
    }
    return patch;
}

async function saveEditItem() {
    if (!editItemFormRef.value || !editItemTarget.value) return;
    const { group, item } = editItemTarget.value;
    const isVariant = editItemIsVariant.value;
    const rowId = String(item.id);

    // Same guard as "add variant": a change that makes this row duplicate a
    // sibling's size/color/unit would silently vanish on the printed table.
    const newKey = [editItemForm.size, editItemForm.color, editItemForm.unit]
        .map((s) => String(s || '').trim()).join('|');
    const collides = group.items.some((other) => {
        if (String(other.id) === rowId) return false;
        return [other.size, other.color, other.unit]
            .map((s) => String(s || '').trim()).join('|') === newKey && newKey !== '||';
    });
    if (collides) {
        ElMessage.warning(t('duplicate_variant_warning'));
        return;
    }

    try {
        await editItemFormRef.value.validate();
    } catch {
        return; // el-form already highlights the offending fields
    }

    editItemSaving.value = true;
    editItemServerErrors.value = {};

    const product = editRowProduct(group);
    const productPatch = {};
    if (String(editItemForm.name_ar).trim() !== (product.name_ar || '')) {
        productPatch.name_ar = String(editItemForm.name_ar).trim();
    }
    const newNameEn = String(editItemForm.name_en).trim();
    if (newNameEn !== (product.name_en || '')) {
        productPatch.name_en = newNameEn || null;
    }

    const source = editRowSource(group, item);
    const rowPatch = changedPatch(source);
    if (isVariant && rowPatch.unit !== undefined) {
        rowPatch.material = rowPatch.unit;
        delete rowPatch.unit;
    }

    try {
        const results = [];

        if (isVariant) {
            // Two records, two requests: the names live on the parent product,
            // the rest on the variant line. Either can be rejected on its own.
            if (Object.keys(productPatch).length) {
                results.push(await saveField(`p-${product.id}`, productPatch));
            }
            if (Object.keys(rowPatch).length) {
                results.push(await saveField(rowId, rowPatch));
            }
        } else {
            const merged = { ...productPatch, ...rowPatch };
            if (Object.keys(merged).length) {
                results.push(await saveField(rowId, merged));
            }
        }

        if (results.length === 0) {
            // Opened, looked at, closed. Nothing to report.
            editItemVisible.value = false;
            return;
        }

        const failed = results.filter((result) => !result.ok);

        if (failed.length) {
            // The dialog stays open with the typed values: closing it here is
            // what used to lose a rejected SKU or an out-of-range count, and
            // the success message that followed said the opposite of the truth.
            editItemServerErrors.value = mapServerErrorsToForm(failed[0].error, isVariant);

            // A variant edit is two requests, so one of them may already have
            // gone through; say so rather than implying nothing was saved.
            if (results.length > failed.length) flashMsg(t('item_update_partially_saved'));
            return;
        }

        editItemVisible.value = false;

        // Queued offline: saveField has already said so, and claiming the item
        // was updated on top of that would be one message too many.
        if (!results.some((result) => result.queued)) {
            flashMsg(t('item_updated_successfully'));
        }
    } finally {
        editItemSaving.value = false;
    }
}

// Server field names to form field names. Only `material` differs: the variant
// endpoint calls it that, the dialog shows it in the shared "unit" input.
function mapServerErrorsToForm(error, isVariant) {
    const errors = serverFieldErrors(error);

    if (isVariant && errors.material) {
        const { material, ...rest } = errors;

        return { ...rest, unit: material };
    }

    return errors;
}

const retrySync = async () => {
    if (syncing.value) return;
    const { sent, failed } = await syncPending(true);
    if (sent === 0 && failed === 0) return; // nothing queued / nothing done
    if (sent > 0) return; // onSynced already reported the success
    flashMsg(t('offline_sync_attempted'));
};

const confirmDiscardPending = () => {
    ElMessageBox.confirm(
        t('confirm_discard_pending_message', { count: pendingCount.value }),
        t('confirm_discard_pending_title'),
        {
            confirmButtonText: t('common.delete'),
            cancelButtonText: t('common.cancel'),
            type: 'warning',
            confirmButtonClass: 'el-button--danger',
        }
    )
        .then(() => {
            clear();
            applyPendingStatuses();
            flashMsg(t('pending_changes_discarded'));
        })
        .catch(() => {});
};

// Banner copy/state: three situations — fully offline (with or without
// unsaved edits) and back-online-but-syncing.
const syncBannerClass = computed(() => {
    if (!isOnline.value) return 'is-offline';
    if (syncing.value) return 'is-syncing';
    return 'is-pending';
});

const bannerTitle = computed(() => {
    if (!isOnline.value) return t('offline_mode_title');
    if (syncing.value) return t('syncing_title');
    return t('pending_sync_title');
});

const bannerDetail = computed(() => {
    if (!isOnline.value) {
        return pendingCount.value > 0
            ? t('offline_pending_detail', { count: pendingCount.value })
            : t('offline_ready_detail');
    }
    if (syncing.value) {
        return t('syncing_detail', { count: pendingCount.value });
    }
    return t('pending_sync_detail', { count: pendingCount.value });
});

// Merges the server's saved record back into the store's product list so the
// "original" value (used for the edited/strikethrough display) matches what
// is now actually in the database instead of the stale fetched copy.
function applyServerRecord(type, rawId, record) {
    if (!record) return;
    if (type === 'v') {
        for (const p of store.products) {
            if (!Array.isArray(p.variants)) continue;
            const idx = p.variants.findIndex((v) => String(v.id) === String(rawId));
            if (idx !== -1) { p.variants[idx] = { ...p.variants[idx], ...record }; return; }
        }
    } else {
        const idx = store.products.findIndex((p) => String(p.id) === String(rawId));
        if (idx !== -1) store.products[idx] = { ...store.products[idx], ...record };
    }
}

const importMsg = ref('');
let importMsgTimeout = null;

// Which columns appear on the printed/exported price list — persisted per
// browser so the choice sticks across visits.
const COLUMNS_STORAGE_KEY = 'price_offer_visible_columns';
const defaultColumns = { image: true, product: false, details: true, price: true, inventory: true };
const columnOptions = [
    { key: 'image', label: 'image' },
    { key: 'product', label: 'product' },
    { key: 'details', label: 'details' },
    { key: 'price', label: 'the_price' },
    { key: 'inventory', label: 'inventory' },
];

function loadVisibleColumns() {
    try {
        const raw = localStorage.getItem(COLUMNS_STORAGE_KEY);
        if (!raw) return { ...defaultColumns };
        return { ...defaultColumns, ...JSON.parse(raw) };
    } catch {
        return { ...defaultColumns };
    }
}

const visibleColumns = ref(loadVisibleColumns());
const selectedColumnCount = computed(() => Object.values(visibleColumns.value).filter(Boolean).length);

function toggleColumn(key, val) {
    if (!val && selectedColumnCount.value <= 1) return;
    visibleColumns.value[key] = val;
    try {
        localStorage.setItem(COLUMNS_STORAGE_KEY, JSON.stringify(visibleColumns.value));
    } catch {
        // Private mode / quota exceeded — selection just won't persist.
    }
}

const products = computed(() => store.products);
const categories = computed(() => store.categories);
const loading = computed(() => store.loading);
const total = computed(() => store.pagination.total);

const categoryButtonLabel = computed(() => {
    const n = selectedCategoryIds.value.length;
    if (n === 0) return t('all_classifications');
    if (n === 1) return categoryNameById(selectedCategoryIds.value[0]) || t('classifications_selected', { n });
    return t('classifications_selected', { n });
});

const filteredCategoryOptions = computed(() => {
    const q = categorySearch.value.trim().toLowerCase();
    if (!q) return categories.value;
    return categories.value.filter((c) =>
        (c.name_ar || '').toLowerCase().includes(q) || (c.name_en || '').toLowerCase().includes(q)
    );
});

function categoryNameById(id) {
    const cat = categories.value.find((c) => c.id === id);
    return cat ? (cat.name_ar || cat.name_en || '') : '';
}

function toggleCategory(id, checked) {
    if (checked) {
        if (!selectedCategoryIds.value.includes(id)) selectedCategoryIds.value = [...selectedCategoryIds.value, id];
    } else {
        selectedCategoryIds.value = selectedCategoryIds.value.filter((x) => x !== id);
    }
    onCategoryChange();
}

function selectAllCategories() {
    selectedCategoryIds.value = filteredCategoryOptions.value.map((c) => c.id);
    onCategoryChange();
}

function clearCategories() {
    selectedCategoryIds.value = [];
    onCategoryChange();
}

// --- Per-product print selection ---
// Tracks the products the user has *excluded* from printing, keyed by the
// same product-name group key `buildGroups` produces. Empty means every
// product is included/selected (the default).

function toggleSelected(key, checked) {
    const list = [...deselectedKeys.value];
    if (!checked) {
        if (!list.includes(key)) list.push(key);
    } else {
        const idx = list.indexOf(key);
        if (idx !== -1) list.splice(idx, 1);
    }
    deselectedKeys.value = list;
}

function selectAllProducts() {
    deselectedKeys.value = [];
    flashMsg(t('reset'));
}

function clearSelection() {
    const all = allGroupKeys();
    deselectedKeys.value = all;
}

function allGroupKeys() {
    const keys = new Set();
    for (const g of buildGroups(products.value)) keys.add(g.key);
    return Array.from(keys);
}

// Remember the current print selection so it survives a page reload.
watch(deselectedKeys, (val) => {
    try {
        localStorage.setItem(SELECTION_STORAGE_KEY, JSON.stringify(val));
    } catch {
        // Private mode / quota exceeded — the selection just won't persist.
    }
});

// --- Saved print-selection lists ---

// The item ids (`p-{id}` / `v-{id}`) of the rows currently selected for print.
function currentSelectedItemIds() {
    const ids = new Set();
    for (const g of groupedProducts.value) {
        for (const item of g.items) ids.add(item.id);
    }
    return Array.from(ids);
}

async function loadLists() {
    loadingLists.value = true;
    try {
        const res = await priceOfferListsApi.getAll();
        savedLists.value = res.data?.data ?? [];
    } catch {
        ElMessage.error(t('failed_to_load_saved_lists'));
    } finally {
        loadingLists.value = false;
    }
}

async function saveCurrentSelection() {
    const key = t('default_list_name');
    const useName = (listName.value || '').trim();
    const name = useName || `${key} ${savedLists.value.length + 1}`;
    const items = currentSelectedItemIds();
    savingList.value = true;
    try {
        const res = await priceOfferListsApi.create({ name, items });
        savedLists.value.unshift(res.data?.data ?? { name_ar: name, name_en: name, items_count: items.length });
        listName.value = '';
        flashMsg(t('list_saved'));
    } catch {
        ElMessage.error(t('failed_to_save_list'));
    } finally {
        savingList.value = false;
    }
}

// Applies a saved list to the current view: a group stays selected only when
// every one of its rows is present in the saved list.
async function loadSavedList(id) {
    try {
        const res = await priceOfferListsApi.show(id);
        const itemKeys = res.data?.data?.item_keys ?? [];
        const set = new Set(itemKeys);
        const exclude = [];
        for (const g of buildGroups(products.value)) {
            const selected = g.items.every((item) => set.has(item.id));
            if (!selected) exclude.push(g.key);
        }
        deselectedKeys.value = exclude;
        flashMsg(t('selection_loaded'));
    } catch {
        ElMessage.error(t('failed_to_load_selection'));
    }
}

async function deleteSavedList(id) {
    try {
        await priceOfferListsApi.remove(id);
        const idx = savedLists.value.findIndex((l) => l.id === id);
        if (idx !== -1) savedLists.value.splice(idx, 1);
        flashMsg(t('list_deleted'));
    } catch {
        ElMessage.error(t('failed_to_delete_list'));
    }
}

const selectedCount = computed(() => {
    if (deselectedKeys.value.length === 0) return groupedProducts.value.length;
    return groupedProducts.value.filter((g) => !deselectedKeys.value.includes(g.key)).length;
});

// Whether every on-screen group is selected (used by the header checkbox).
const someSelected = computed(
    () => deselectedKeys.value.length > 0 && selectedCount.value > 0
);
const allSelected = computed(
    () => groupedProducts.value.length > 0 && deselectedKeys.value.length === 0
);

// Toggles the whole on-screen table: checking every group or clearing them.
function toggleSelectAll(checked) {
    if (checked) {
        selectAllProducts();
    } else {
        deselectedKeys.value = allGroupKeys();
    }
}

const formatSummaryPrice = (v) => Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// --- Counts/sums that reflect what will *actually* print ---
// These use the full filtered catalog (all pages across the active category
// filter) so the selected count matches the number of printed groups, instead
// of only counting the page currently on screen. Falls back to the on-screen
// figures only while the full catalog is still being hydrated.
const printReadyCount = computed(() => {
    if (fullPrintGroups.value.length) {
        return filterSelected(fullPrintGroups.value).length;
    }
    return selectedCount.value;
});
const printReadySum = computed(() => {
    const groups = fullPrintGroups.value.length ? fullPrintGroups.value : groupedProducts.value;
    let sum = 0;
    for (const group of groups) {
        if (deselectedKeys.value.length > 0 && deselectedKeys.value.includes(group.key)) continue;
        for (const item of group.items) {
            const p = Number(item.displayPrice);
            if (Number.isFinite(p)) sum += p;
        }
    }
    return sum;
});

// Filters built groups down to just the products the user wants printed.
function filterSelected(groups) {
    if (deselectedKeys.value.length === 0) return groups;
    return groups.filter((g) => !deselectedKeys.value.includes(g.key));
}

// Full catalog snapshot for printing/PDF — every page, grouped by product name.
const printGroups = ref([]);
// The same full-catalog groups BEFORE deselection, so the "selected for print"
// count/sum can reflect every row that would actually print (all pages across
// the active category filter), not just the page on screen.
const fullPrintGroups = ref([]);
const printTableRef = ref(null);
const printLoading = ref(false);
const printCancelled = ref(false);
const printProgress = ref({ loaded: 0, total: 0 });
const printProgressPercent = computed(() => {
    const { loaded, total: totalCount } = printProgress.value;
    return totalCount > 0 ? Math.min(100, Math.round((loaded / totalCount) * 100)) : 0;
});
// 'print' | 'pdf' — which action the prep overlay is running for.
const prepMode = ref('print');
// 'fetch' | 'images' | 'render' — drives the overlay's progress copy.
const exportPhase = ref('fetch');
// Rendered off-screen (not display:none) only while html2canvas needs to see it.
const pdfRendering = ref(false);

// Groups rows by product name (not just product id), so products stored as
// separate rows sharing a name (e.g. color variants) print as one entry.
function buildGroups(list) {
    const map = new Map();
    const seenDetails = new Map();
    const order = [];
    for (const p of list) {
        const name = (p.name_ar || p.name_en || '').trim();
        const key = name || `id-${p.id}`;
        if (!map.has(key)) {
            map.set(key, { key, product: p, items: [] });
            seenDetails.set(key, new Set());
            order.push(key);
        }
        const variants = Array.isArray(p.variants) ? p.variants : [];
        const items = variants.length
            ? variants.map((v) => makeItem(`v-${v.id}`, {
                size: v.size || '',
                color: v.color || '',
                unit: v.material || '',
                price: parseFloat(v.price) || 0,
                stock_quantity: v.stock_quantity ?? 0,
            }))
            : [makeItem(`p-${p.id}`, {
                size: p.size || '',
                color: p.color || '',
                unit: p.unit || '',
                price: parseFloat(p.price) || 0,
                stock_quantity: p.stock_quantity ?? 0,
            })];
        // Two variants with the same size/color/unit print as the same row to
        // a reader, so collapse them even when they come from separate variant
        // records (or the same product/variant surfaces twice in `list`
        // because it's joined in via more than one category).
        const seen = seenDetails.get(key);
        const group = map.get(key);
        for (const item of items) {
            const detailKey = `${item.size}|${item.color}|${item.unit}`;
            if (seen.has(detailKey)) continue;
            seen.add(detailKey);
            group.items.push(item);
        }
    }
    return order.map((k) => map.get(k));
}

const groupedProducts = computed(() => filterSelected(buildGroups(products.value)));

function makeItem(id, base) {
    const override = overrides.value[id];
    const stockOverride = stockOverrides.value[id];
    return {
        id,
        ...base,
        stock_quantity: stockOverride !== undefined ? stockOverride : base.stock_quantity,
        originalPrice: base.price,
        displayPrice: override !== undefined ? override : base.price,
    };
}

let searchTimeout = null;
const onSearchInput = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        currentPage.value = 1;
        fetchProducts();
        hydratePrintCatalog();
    }, 400);
};

const fetchProducts = async () => {
    try {
        await store.fetchProducts({
            page: currentPage.value,
            per_page: pageSize.value,
            search: searchQuery.value || undefined,
            category_id: selectedCategoryIds.value.length ? selectedCategoryIds.value : undefined,
            is_active: true,
            with_variants: true,
        });
    } catch {
        ElMessage.error(t('failed_to_bring_products'));
    }
};

// Keeps the print/PDF table hydrated with the full, current-filter catalog in
// the background, so a native Ctrl+P (or any print trigger other than our own
// Print/Download PDF buttons) still shows real images and prices instead of
// an empty table. The buttons still do their own guaranteed, visible fetch on
// click — this is just a best-effort head start.
let hydrating = false;
async function hydratePrintCatalog() {
    if (hydrating) return;
    hydrating = true;
    printCancelled.value = false;
    try {
        const groups = buildGroups(await fetchAllProductsFlat());
        fullPrintGroups.value = groups;
        printGroups.value = filterSelected(groups);
    } catch {
        // Silent — Print/Download PDF still fetch fresh, visibly, on demand.
    } finally {
        hydrating = false;
    }
}

const resetFilters = () => {
    searchQuery.value = '';
    selectedCategoryIds.value = [];
    categorySearch.value = '';
    divideValue.value = null;
    currentPage.value = 1;
    deselectedKeys.value = [];
    overrides.value = {};
    divideValueApplied.value = null;
    stockOverrides.value = {};
    store.clearFilters();
    fetchProducts();
    hydratePrintCatalog();
    flashMsg(t('reset'));
};

const onCategoryChange = () => {
    currentPage.value = 1;
    fetchProducts();
    hydratePrintCatalog();
};

const onSizeChange = () => {
    currentPage.value = 1;
    fetchProducts();
};

const onPageChange = () => {
    fetchProducts();
};

const startEdit = (item) => {
    editingId.value = item.id;
    editValue.value = String(item.displayPrice ?? '');
    nextTick(() => {
        const input = document.querySelector('.price-edit-input');
        if (input) { input.focus(); input.select(); }
    });
};

// An override that the server refused has to come back off the row: the same
// map feeds the screen table, the printed price list and the CSV export, so a
// rejected price would otherwise be the number that goes out to a customer.
function rollbackOverride(map, id, previous) {
    const next = { ...map.value };
    if (previous === undefined) delete next[id];
    else next[id] = previous;
    map.value = next;
}

const commitEdit = (val) => {
    if (!editingId.value) return;
    const id = editingId.value;
    editingId.value = null;
    val = String(val ?? editValue.value).replace(/[^\d.\-]/g, '');
    const num = parseFloat(val);
    if (isNaN(num) || num < 0) return;
    const rounded = Math.round(num * 10000) / 10000;
    const previous = overrides.value[id];
    overrides.value = { ...overrides.value, [id]: rounded };
    saveField(id, { price: rounded }).then((result) => {
        if (!result.ok) rollbackOverride(overrides, id, previous);
    });
};

const cancelEdit = () => {
    editingId.value = null;
};

const startEditStock = (item) => {
    editingStockId.value = item.id;
    editStockValue.value = String(item.stock_quantity ?? '');
    nextTick(() => {
        const input = document.querySelector('.stock-edit-input');
        if (input) { input.focus(); input.select(); }
    });
};

const commitEditStock = (val) => {
    if (!editingStockId.value) return;
    const id = editingStockId.value;
    editingStockId.value = null;
    val = String(val ?? editStockValue.value).replace(/[^\d]/g, '');
    const num = parseInt(val, 10);
    if (isNaN(num) || num < 0) return;
    const previous = stockOverrides.value[id];
    stockOverrides.value = { ...stockOverrides.value, [id]: num };
    // A count the warehouse refuses — below what outstanding orders have
    // reserved — comes back with a 422 explaining why; the row must not keep
    // showing the number that was turned down.
    saveField(id, { stock_quantity: num }).then((result) => {
        if (!result.ok) rollbackOverride(stockOverrides, id, previous);
    });
};

const cancelEditStock = () => {
    editingStockId.value = null;
};

// Local print-preview only — divides the shown prices without writing
// anything to the database. Only the per-cell editors (price, count,
// size/color/unit) autosave; a bulk rewrite of every price needs a deliberate
// separate action, not a side effect of building a custom print list.
const divideAllPrices = () => {
    const v = parseFloat(divideValue.value);
    if (!(v > 0)) {
        ElMessage.warning(t('please_enter_positive_number'));
        return;
    }
    for (const group of groupedProducts.value) {
        for (const item of group.items) {
            overrides.value[item.id] = Math.round((item.originalPrice / v) * 10) / 10;
        }
    }
    divideValueApplied.value = v;
    flashMsg(t('prices_divided'));
};

// How many rows currently carry a local (divide) override — lets the toolbar
// show a live "division applied" state and a one-click undo.
const divideOverrideCount = computed(() => Object.keys(overrides.value).length);
const divideValueApplied = ref(null);

function clearDivides() {
    overrides.value = {};
    divideValueApplied.value = null;
    flashMsg(t('clear_divides'));
}

// Pulls every page of the current filter (not just the on-screen page) so
// printing/PDF export covers the full price list, grouped by product name.
// Reports progress as it goes and stops early if the user cancels.
async function fetchAllProductsFlat(onProgress) {
    const baseParams = {
        search: searchQuery.value || undefined,
        category_id: selectedCategoryIds.value.length ? selectedCategoryIds.value : undefined,
        is_active: true,
        with_variants: true,
        per_page: 100,
    };
    const all = [];
    let page = 1;
    let lastPage = 1;
    do {
        const res = await productsApi.getAll({ ...baseParams, page });
        const data = res.data;
        all.push(...(data.data || (Array.isArray(data) ? data : [])));
        const pagination = data.pagination || data;
        lastPage = pagination.last_page || 1;
        onProgress?.(all.length, pagination.total ?? all.length);
        page += 1;
    } while (page <= lastPage && !printCancelled.value);
    return all;
}

// Shared prep: fetch every page, build the merged groups, mount the print
// table, then block until every product image has actually loaded — a lazy
// or still-fetching <img> would otherwise print/export as a blank box.
// Returns the mounted <table> element, or null if the user cancelled.
async function prepareFullCatalog() {
    printCancelled.value = false;
    printProgress.value = { loaded: 0, total: 0 };
    exportPhase.value = 'fetch';
    const all = await fetchAllProductsFlat((loaded, total) => {
        printProgress.value = { loaded, total };
    });
    if (printCancelled.value) return null;

    const groups = buildGroups(all);
    fullPrintGroups.value = groups;
    printGroups.value = filterSelected(groups);
    await nextTick();

    const tableEl = printTableRef.value?.$el;
    if (tableEl) {
        exportPhase.value = 'images';
        printProgress.value = { loaded: 0, total: 0 };
        await waitForImages(tableEl, (loaded, total) => {
            printProgress.value = { loaded, total };
        });
    }
    return printCancelled.value ? null : tableEl;
}

const printPage = async () => {
    prepMode.value = 'print';
    printLoading.value = true;
    try {
        const tableEl = await prepareFullCatalog();
        if (!tableEl) return;
        printLoading.value = false;
        window.print();
    } catch {
        if (!printCancelled.value) ElMessage.error(t('failed_to_bring_products'));
    } finally {
        printLoading.value = false;
    }
};

const downloadPdf = async () => {
    prepMode.value = 'pdf';
    printLoading.value = true;
    try {
        const tableEl = await prepareFullCatalog();
        if (!tableEl) return;

        exportPhase.value = 'render';
        printProgress.value = { loaded: 0, total: 0 };
        pdfRendering.value = true;
        await nextTick();
        await renderTableToPdf({
            table: tableEl,
            groups: printGroups.value,
            filename: `price-offer-${new Date().toISOString().slice(0, 10)}.pdf`,
            coverSrc: '/cover.jpeg',
            onPageProgress: (loaded, total) => {
                printProgress.value = { loaded, total };
            },
        });
        flashMsg(t('pdf_ready'));
    } catch {
        if (!printCancelled.value) ElMessage.error(t('failed_to_generate_pdf'));
    } finally {
        pdfRendering.value = false;
        printLoading.value = false;
        exportPhase.value = 'fetch';
    }
};

const cancelPrintPrep = () => {
    printCancelled.value = true;
    printLoading.value = false;
    flashMsg(t('print_prep_cancelled'));
};

function flashMsg(msg) {
    importMsg.value = msg;
    clearTimeout(importMsgTimeout);
    importMsgTimeout = setTimeout(() => { importMsg.value = ''; }, 2500);
}

onMounted(async () => {
    await store.fetchCategories();
    await fetchProducts();
    hydratePrintCatalog();
    // Restore any edits queued before a reload / while offline, and push them
    // if the connection has already returned.
    applyPendingStatuses();
    if (isOnline.value && pendingCount.value > 0) syncPending();
});
</script>

<style scoped>
.price-offer-page {
    padding: 0;
}

/* Cover page — only rendered when printing, matches public/file_with_images.html */
.print-cover {
    display: none;
}

/* Toolbar — matches public/file_with_images.html. Two stacked rows: an
   identity row (title/count/status) and a tools row, so the action controls
   read as their own layer instead of competing with the title for space on
   one wrapping line. */
.offer-toolbar {
    display: flex;
    flex-direction: column;
    gap: 14px;
    background: linear-gradient(135deg, #293344 0%, #3d4d63 100%);
    color: #fff;
    padding: 16px 18px;
    border-radius: 14px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, .25);
    margin-bottom: 1rem;
    position: sticky;
    top: 0;
    z-index: 30;
}
.toolbar-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
}
.toolbar-title {
    display: flex;
    align-items: center;
    gap: 10px;
}
.toolbar-title h2 {
    margin: 0;
    font-size: 17px;
    font-weight: 700;
}
.toolbar-title .dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #c00000;
    box-shadow: 0 0 0 4px rgba(192, 0, 0, .25);
    flex: 0 0 auto;
}
.badge {
    font-size: 11px;
    background: rgba(255, 255, 255, .14);
    padding: 5px 11px;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, .28);
    white-space: nowrap;
}

/* Tools row: the filter cluster anchors the start side and can grow to fill
   space; the edit/export clusters stay grouped together at the end so they
   move as one block instead of drifting apart when the row wraps. */
.toolbar-body {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px 16px;
    flex-wrap: wrap;
}
.tools-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
/* Each cluster sits in its own soft panel instead of being separated by thin
   divider lines — reads as distinct "cards" of related actions at a glance,
   and each keeps its shape when clusters wrap onto their own row. */
.tools-group {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    background: rgba(255, 255, 255, .07);
    border: 1px solid rgba(255, 255, 255, .12);
    border-radius: 14px;
    padding: 6px 8px;
}
.tools-group-filter {
    flex: 1 1 320px;
}
.export-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.btn-icon {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.qbox {
    position: relative;
    flex: 1 1 200px;
    max-width: 260px;
}
.qicon {
    position: absolute;
    inset-inline-start: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: rgba(255, 255, 255, .75);
    font-size: 13px;
    pointer-events: none;
}
.qinput {
    width: 100%;
    border: 1px solid rgba(255, 255, 255, .35);
    background: rgba(255, 255, 255, .12);
    color: #fff;
    border-radius: 999px;
    padding: 8px 14px 8px 32px;
    font-size: 12.5px;
    outline: none;
}
.qinput::placeholder {
    color: rgba(255, 255, 255, .65);
}
.btn-categories {
    position: relative;
}
.btn-categories::after {
    content: '';
    display: inline-block;
    width: 6px;
    height: 6px;
    margin-inline-start: 6px;
    border-inline-end: 1.5px solid currentColor;
    border-block-end: 1.5px solid currentColor;
    transform: rotate(45deg) translateY(-2px);
}
.btn-categories.active {
    background: rgba(192, 0, 0, .28);
    border-color: rgba(192, 0, 0, .55);
}

.divwrap {
    display: flex;
    align-items: center;
    gap: 6px;
}
.divwrap-icon {
    color: rgba(255, 255, 255, .6);
    font-size: 14px;
    flex: 0 0 auto;
}
.divwrap input[type=number] {
    width: 110px;
    border: 1px solid rgba(255, 255, 255, .35);
    background: rgba(255, 255, 255, .12);
    color: #fff;
    border-radius: 999px;
    padding: 8px 12px;
    font-size: 12.5px;
    outline: none;
}
.divwrap input[type=number]::placeholder {
    color: rgba(255, 255, 255, .65);
}
.divide-tool {
    display: flex;
    align-items: center;
    gap: 8px;
}
.divide-applied {
    font-size: 11.5px;
    font-weight: 600;
    color: #fde68a;
    background: rgba(192, 0, 0, .28);
    border: 1px solid rgba(252, 211, 77, .4);
    padding: 4px 10px;
    border-radius: 999px;
    white-space: nowrap;
}
.divide-clear.btn-ghost.btn-icon {
    padding: 8px;
    width: 32px;
    height: 32px;
}
.btn-columns {
    position: relative;
}
.btn-columns::after {
    content: '';
    display: inline-block;
    width: 6px;
    height: 6px;
    margin-inline-start: 6px;
    border-inline-end: 1.5px solid currentColor;
    border-block-end: 1.5px solid currentColor;
    transform: rotate(45deg) translateY(-2px);
}

.btn-divide,
.btn-ghost,
.btn-pdf,
.btn-pdf-download {
    border: none;
    cursor: pointer;
    font-size: 12.5px;
    font-weight: 600;
    color: #fff;
    padding: 8px 16px;
    border-radius: 999px;
    transition: transform .15s ease, box-shadow .15s ease, background .15s ease, border-color .15s ease;
    white-space: nowrap;
}
.btn-divide {
    background: linear-gradient(135deg, #0f766e, #14b8a6);
    box-shadow: 0 3px 10px rgba(15, 118, 110, .35);
}
.btn-ghost {
    background: transparent;
    border: 1px solid rgba(255, 255, 255, .4);
}
.btn-ghost:hover {
    background: rgba(255, 255, 255, .12);
    border-color: rgba(255, 255, 255, .6);
}
.btn-pdf,
.btn-pdf-download {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.btn-pdf {
    background: linear-gradient(135deg, #c00000, #e02424);
    box-shadow: 0 3px 10px rgba(192, 0, 0, .35);
}
.btn-pdf-download {
    background: linear-gradient(135deg, #6d28d9, #9333ea);
    box-shadow: 0 3px 10px rgba(109, 40, 217, .35);
}
.btn-divide:hover,
.btn-ghost:hover,
.btn-pdf:hover:not(:disabled),
.btn-pdf-download:hover:not(:disabled) {
    transform: translateY(-1px);
}
.btn-divide:focus-visible,
.btn-ghost:focus-visible,
.btn-pdf:focus-visible,
.btn-pdf-download:focus-visible {
    outline: 2px solid rgba(255, 255, 255, .8);
    outline-offset: 2px;
}
.btn-pdf:disabled,
.btn-pdf-download:disabled {
    opacity: .75;
    cursor: wait;
}
.saved-msg {
    font-size: 11.5px;
    color: #8fe3ab;
    font-weight: 600;
    white-space: nowrap;
}
.status-fade-enter-active,
.status-fade-leave-active {
    transition: opacity .2s ease;
}
.status-fade-enter-from,
.status-fade-leave-to {
    opacity: 0;
}

/* Offline / pending-sync banner — a slim, high-visibility strip under the
   toolbar that appears whenever the connection is down or queued edits have
   not yet reached the server. Color shifts by state: red while fully offline,
   indigo/amber while syncing or waiting. */
.sync-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px 16px;
    flex-wrap: wrap;
    margin-bottom: 1rem;
    padding: 10px 16px;
    border-radius: 12px;
    font-size: 12.5px;
}
.sync-banner-main {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1 1 auto;
    min-width: 0;
}
.sync-banner-icon {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
}
.sync-banner-text {
    display: flex;
    flex-direction: column;
    gap: 1px;
    min-width: 0;
}
.sync-banner-text strong {
    font-size: 13px;
    font-weight: 700;
    line-height: 1.2;
}
.sync-banner-text span {
    font-size: 12px;
    opacity: .85;
    line-height: 1.35;
}
.sync-banner-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 0 0 auto;
}
.sync-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border: 1px solid;
    border-radius: 999px;
    padding: 6px 14px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: transform .15s ease, box-shadow .15s ease, opacity .15s ease;
}
.sync-btn:hover:not(:disabled) {
    transform: translateY(-1px);
}
.sync-btn:disabled {
    opacity: .6;
    cursor: not-allowed;
}

.sync-banner.is-offline {
    background: linear-gradient(135deg, #7f1d1d, #991b1b);
    color: #fecaca;
    box-shadow: 0 6px 16px rgba(153, 27, 27, .25);
}
.sync-banner.is-offline .sync-banner-icon {
    background: rgba(254, 226, 226, .18);
    color: #fecaca;
}
.sync-banner.is-offline .sync-btn {
    border-color: rgba(254, 226, 226, .4);
    background: rgba(254, 226, 226, .12);
    color: #fee2e2;
}
.sync-banner.is-offline .sync-btn-danger {
    border-color: rgba(254, 226, 226, .4);
    background: transparent;
    color: #fecaca;
}
.sync-banner.is-offline .sync-btn:hover:not(:disabled) {
    background: rgba(254, 226, 226, .2);
}

.sync-banner.is-pending {
    background: linear-gradient(135deg, #78350f, #b45309);
    color: #fde68a;
    box-shadow: 0 6px 16px rgba(180, 83, 9, .25);
}
.sync-banner.is-pending .sync-banner-icon {
    background: rgba(254, 243, 199, .16);
    color: #fde68a;
}
.sync-banner.is-pending .sync-btn {
    border-color: rgba(254, 243, 199, .4);
    background: rgba(254, 243, 199, .12);
    color: #fef3c7;
}
.sync-banner.is-pending .sync-btn-danger {
    border-color: rgba(254, 243, 199, .4);
    background: transparent;
    color: #fde68a;
}
.sync-banner.is-pending .sync-btn:hover:not(:disabled) {
    background: rgba(254, 243, 199, .22);
}

.sync-banner.is-syncing {
    background: linear-gradient(135deg, #3730a3, #4f46e5);
    color: #e0e7ff;
    box-shadow: 0 6px 16px rgba(79, 70, 229, .28);
}
.sync-banner.is-syncing .sync-banner-icon {
    background: rgba(224, 231, 255, .16);
    color: #e0e7ff;
}
.sync-banner.is-syncing .sync-btn {
    border-color: rgba(224, 231, 255, .4);
    background: rgba(224, 231, 255, .12);
    color: #e0e7ff;
}
.sync-banner.is-syncing .sync-btn-danger {
    border-color: rgba(224, 231, 255, .4);
    background: transparent;
    color: #e0e7ff;
}
.sync-banner.is-syncing .sync-btn:hover:not(:disabled) {
    background: rgba(224, 231, 255, .22);
}

/* Below the tablet break the banner's actions wrap under the message. */
@media (max-width: 640px) {
    .sync-banner {
        align-items: flex-start;
        flex-direction: column;
    }
    .sync-banner-actions {
        width: 100%;
        justify-content: flex-end;
    }
}

/* Below the tablet break the tools row stacks: the filter cluster first,
   then the edit/export clusters as a full-width block underneath, each
   still keeping its own panel instead of one undifferentiated column. */
@media (max-width: 860px) {
    .toolbar-body {
        flex-direction: column;
        align-items: stretch;
    }
    .tools-actions {
        flex-direction: column;
        align-items: stretch;
    }
    .tools-group {
        width: 100%;
    }
    .qbox {
        max-width: none;
        flex: 1 1 100%;
    }
}

/* Below phone width, controls that only fit two-to-a-row on tablet get
   stacked and stretched to a full-width tap target instead of shrinking. */
@media (max-width: 520px) {
    .tools-group-filter,
    .tools-group-edit,
    .tools-group-export {
        flex-direction: column;
        align-items: stretch;
    }
    .btn-categories,
    .divwrap,
    .tools-group-edit > .btn-icon,
    .export-actions,
    .tools-group-export > .btn-columns {
        width: 100%;
    }
    .divide-tool {
        width: 100%;
        flex-wrap: wrap;
    }
    .divide-applied {
        order: 2;
    }
    .divwrap input[type=number] {
        flex: 1 1 auto;
        width: auto;
    }
    .export-actions > button {
        flex: 1 1 auto;
    }
    .btn-divide,
    .tools-group-edit > .btn-icon,
    .btn-columns,
    .btn-pdf,
    .btn-pdf-download {
        justify-content: center;
    }
    .toolbar-head {
        align-items: flex-start;
    }
    .saved-msg {
        width: 100%;
        white-space: normal;
        text-align: start;
    }
}

/* Per-product print selection in the toolbar. */
.selection-actions {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.selection-actions .btn-ghost.active {
    background: rgba(16, 185, 129, .3);
    border-color: rgba(16, 185, 129, .6);
}
.selection-badge.is-empty {
    background: rgba(220, 38, 38, .4);
    border-color: rgba(220, 38, 38, .7);
}

/* Saved print-selection lists popover. */
.lists-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    margin-inline-start: 4px;
    font-size: 10px;
    font-weight: 700;
    color: #fff;
    background: #c00000;
    border-radius: 999px;
}
.lists-menu {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.lists-menu-title {
    margin: 0;
    font-size: 13px;
    font-weight: 700;
    color: #111c2c;
}
.lists-create {
    display: flex;
    gap: 6px;
}
.lists-name-input {
    flex: 1;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 7px 10px;
    font-size: 12.5px;
    outline: none;
}
.lists-name-input:focus {
    border-color: #2563eb;
}
.btn-list-save {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border: none;
    border-radius: 8px;
    background: linear-gradient(135deg, #0f766e, #14b8a6);
    color: #fff;
    cursor: pointer;
    font-size: 15px;
    transition: transform .15s ease, box-shadow .15s ease, opacity .15s ease;
}
.btn-list-save:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(15, 118, 110, .35);
}
.btn-list-save:disabled {
    opacity: .5;
    cursor: not-allowed;
}
.lists-scroll {
    max-height: 240px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-height: 40px;
}
.lists-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 7px 8px;
    border: 1px solid rgba(15, 23, 42, .08);
    background: #f8fafc;
    border-radius: 8px;
}
.lists-item-name {
    flex: 1;
    font-size: 12.5px;
    font-weight: 600;
    color: #111c2c;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.lists-item-count {
    font-size: 11px;
    color: #64748b;
    white-space: nowrap;
}
.lists-item-actions {
    display: inline-flex;
    gap: 4px;
}
.lists-act {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    padding: 0;
    border: none;
    border-radius: 6px;
    background: transparent;
    color: #475569;
    cursor: pointer;
    transition: background .15s ease, color .15s ease;
}
.lists-act:hover {
    background: #e0e7ff;
    color: #2563eb;
}
.lists-act-danger:hover {
    background: #fee2e2;
    color: #dc2626;
}
.selection-empty {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 14px 16px;
    color: #b00e0e;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 600;
    margin: 12px 0 0;
}

.columns-menu-title {
    margin: 0 0 8px;
    font-size: 12px;
    font-weight: 700;
    color: #64748b;
}
.columns-menu-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 2px;
    font-size: 13px;
    color: #1e293b;
    cursor: pointer;
}

/* Classification (category) multi-select popover */
.categories-menu-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 8px;
}
.categories-menu-title {
    margin: 0;
    font-size: 12px;
    font-weight: 700;
    color: #64748b;
}
.categories-menu-actions {
    display: flex;
    gap: 10px;
    flex: 0 0 auto;
}
.link-btn {
    border: none;
    background: none;
    padding: 0;
    font-size: 11.5px;
    font-weight: 700;
    color: #2563eb;
    cursor: pointer;
}
.link-btn:hover {
    text-decoration: underline;
}
.cat-search {
    position: relative;
    margin-bottom: 8px;
}
.cat-search-icon {
    position: absolute;
    inset-inline-start: 8px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 12px;
    pointer-events: none;
}
.cat-search-input {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #1e293b;
    border-radius: 999px;
    padding: 7px 10px 7px 28px;
    font-size: 12.5px;
    outline: none;
}
.cat-search-input:focus {
    border-color: #2563eb;
}
.categories-list {
    max-height: 260px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}
.categories-menu-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 2px;
    font-size: 13px;
    color: #1e293b;
    cursor: pointer;
    border-radius: 6px;
}
.categories-menu-item:hover {
    background: #f1f5f9;
}
.cat-name {
    flex: 1 1 auto;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.cat-count {
    flex: 0 0 auto;
    font-size: 11px;
    font-weight: 700;
    color: #94a3b8;
    background: #f1f5f9;
    border-radius: 999px;
    padding: 1px 7px;
}
.cat-empty {
    margin: 10px 0 2px;
    font-size: 12.5px;
    color: #94a3b8;
    text-align: center;
}

/* Summary statistics strip shown under the toolbar */
.summary-strip {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 1rem;
    padding: 12px 18px;
    background: #fff;
    border: 1px solid rgba(15, 23, 42, .08);
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(15, 23, 42, .04);
}
.summary-item {
    display: flex;
    flex-direction: column;
    gap: 2px;
    line-height: 1.1;
}
.summary-item.is-price .summary-value {
    color: #b00e0e;
}
.summary-item.is-muted .summary-value,
.summary-item.is-muted .summary-label {
    color: #b6c2d0;
}
.summary-value {
    font-size: 19px;
    font-weight: 800;
    color: #111c2c;
    font-variant-numeric: tabular-nums;
}
.summary-label {
    font-size: 11.5px;
    font-weight: 500;
    color: #64748b;
}
.summary-divider {
    width: 1px;
    align-self: stretch;
    background: rgba(15, 23, 42, .1);
    margin: 2px 2px;
}
.summary-note {
    margin: 0 4px 0 auto;
    font-size: 11.5px;
    color: #b45309;
    background: #fffbeb;
    border: 1px solid #fde68a;
    padding: 4px 10px;
    border-radius: 999px;
}

/* Active classification chips shown under the toolbar */
.active-filters {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 1rem;
    padding: 8px 12px;
    background: #fff;
    border: 1px solid rgba(15, 23, 42, .08);
    border-radius: 12px;
}
.active-filters-label {
    font-size: 12px;
    font-weight: 700;
    color: #64748b;
}
.filter-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #eef2ff;
    color: #3730a3;
    font-size: 12px;
    font-weight: 600;
    padding: 4px 6px 4px 10px;
    border-radius: 999px;
}
.filter-chip-remove {
    border: none;
    background: rgba(55, 48, 163, .12);
    color: #3730a3;
    width: 16px;
    height: 16px;
    line-height: 1;
    border-radius: 50%;
    cursor: pointer;
    font-size: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.filter-chip-remove:hover {
    background: rgba(55, 48, 163, .25);
}
.filter-clear-all {
    margin-inline-start: auto;
    border: none;
    background: none;
    font-size: 11.5px;
    font-weight: 700;
    color: #b00e0e;
    cursor: pointer;
}
.filter-clear-all:hover {
    text-decoration: underline;
}

.offer-table-wrap {
    overflow: auto;
    max-height: calc(100vh - 240px);
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 12px 30px rgba(15, 23, 42, .14);
    border: 1px solid rgba(15, 23, 42, .08);
}

.pagination-wrapper {
    margin-top: 1.5rem;
    display: flex;
    justify-content: center;
}

/* Add-variant dialog: a small identity strip so it's obvious the new line is
   going under this particular product before committing. */
.add-variant-subject {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: -2px 0 16px;
    padding: 8px 10px;
    background: #f8fafc;
    border: 1px solid rgba(15, 23, 42, .06);
    border-radius: 10px;
}
.add-variant-subject :deep(.entity-image) {
    flex: 0 0 auto;
}
.add-variant-subject-name {
    font-size: 13px;
    font-weight: 700;
    color: #111c2c;
    direction: rtl;
    text-align: right;
}
.edit-item-kind {
    margin-inline-start: auto;
    flex: 0 0 auto;
    font-size: 10.5px;
    font-weight: 700;
    color: #6366f1;
    background: #eef2ff;
    border: 1px solid #e0e7ff;
    padding: 2px 10px;
    border-radius: 999px;
}
.edit-item-hint {
    margin-bottom: 14px;
}
.edit-item-hint :deep(.el-alert__title) {
    font-size: 12px;
}

/* The print-only table (full catalog, all pages) stays out of the way on screen. */
.print-only {
    display: none;
}
/* While a PDF is being captured, the table needs a real (if invisible) box for
   html2canvas to read — display:none has no layout, so it's parked off-screen
   with a fixed width instead of hidden outright. */
.print-only.pdf-render {
    display: block;
    position: fixed;
    top: 0;
    left: -99999px;
    width: 960px;
    max-height: none;
    overflow: visible;
    border: none;
    box-shadow: none;
    background: #fff;
    z-index: -1;
}

/* Print-prep overlay — shown while every page is fetched for a full print */
.print-prep-overlay {
    position: fixed;
    inset: 0;
    z-index: 3000;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(15, 23, 42, .55);
    backdrop-filter: blur(3px);
}
.print-prep-card {
    background: #fff;
    border-radius: 18px;
    padding: 28px 32px;
    width: min(360px, 88vw);
    box-shadow: 0 24px 60px rgba(15, 23, 42, .35);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 14px;
    text-align: center;
}
.print-prep-spinner {
    font-size: 30px;
    color: #c00000;
}
.print-prep-card h3 {
    margin: 0;
    font-size: 15px;
    font-weight: 700;
    color: #111c2c;
}
.print-prep-card :deep(.el-progress) {
    width: 100%;
}
.print-prep-count {
    margin: 0;
    font-size: 12.5px;
    color: #64748b;
    font-weight: 600;
}
.print-prep-cancel {
    margin-top: 4px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #475569;
    font-size: 12px;
    font-weight: 600;
    padding: 7px 16px;
    border-radius: 999px;
    cursor: pointer;
    transition: background .15s ease, color .15s ease;
}
.print-prep-cancel:hover {
    background: #fee2e2;
    color: #b00e0e;
}

.print-prep-fade-enter-active,
.print-prep-fade-leave-active {
    transition: opacity .18s ease;
}
.print-prep-fade-enter-from,
.print-prep-fade-leave-to {
    opacity: 0;
}

/* Zero page margin so the browser has no room to draw its own URL/date
   header and footer line — same trick used by public/file_with_images.html. */
@page {
    margin: 0;
}

/* Print styles */
@media print {
    .offer-toolbar,
    .screen-only {
        display: none !important;
    }
    .print-only {
        display: block !important;
    }
    .print-cover {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100vh;
        /* Chromium resolves 100vh against the screen viewport, not the @page
           print box, so the cover renders a hair taller than one physical
           page. That sliver used to spill onto a blank page 2 before this
           break was reached — clipping it here keeps everything on page 1. */
        overflow: hidden;
        page-break-after: always;
        break-after: page;
    }
    .print-cover img {
        max-width: 100%;
        max-height: 100vh;
        width: auto;
        height: auto;
        object-fit: contain;
    }
    body {
        background: #fff;
        margin: 0;
    }
    .price-offer-page {
        /* Zero here (not on .print-cover) — any padding on this wrapper adds
           to the cover's height too, pushing it past one full page and
           spilling a near-blank page behind it before the real content. */
        padding: 0;
    }
    .print-only {
        padding: 8px;
    }
    .offer-table-wrap {
        max-height: none;
        overflow: visible;
        border: none;
        box-shadow: none;
    }
    :global(.columns-popover),
    :global(.categories-popover) {
        display: none !important;
    }
}
</style>
