<template>
    <div class="price-offer-page">
        <header class="offer-toolbar">
            <div class="toolbar-head">
                <div class="toolbar-title">
                    <span class="dot"></span>
                    <h2>{{ $t('price_offer') }}</h2>
                    <span v-if="total > 0" class="badge">{{ total }} {{ $t('product') }}</span>
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
                            :disabled="arrangeMode"
                            :title="arrangeMode ? $t('finish_arranging_first') : ''"
                            @input="onSearchInput"
                        />
                    </div>
                    <el-popover
                        v-model:visible="categoryPopoverVisible"
                        placement="bottom-start"
                        trigger="click"
                        width="330"
                        popper-class="categories-popover"
                    >
                        <template #reference>
                            <button
                                type="button"
                                class="btn-ghost btn-categories"
                                :class="{ active: selectedCategoryIds.length > 0 }"
                                :disabled="arrangeMode"
                                :title="arrangeMode ? $t('finish_arranging_first') : ''"
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
                            <div class="cat-subtools">
                                <label class="cat-toggle">
                                    <el-checkbox
                                        :model-value="hideEmptyCategories"
                                        @update:model-value="toggleHideEmptyCategories"
                                    />
                                    <span>{{ $t('hide_empty_classifications') }}</span>
                                </label>
                                <button
                                    v-if="parentCategoryCount > 0"
                                    type="button"
                                    class="link-btn"
                                    @click="toggleAllCategoryBranches"
                                >
                                    {{ allBranchesExpanded ? $t('collapse_all_classifications') : $t('expand_all_classifications') }}
                                </button>
                            </div>
                            <div class="categories-list">
                                <div
                                    v-for="node in categoryTree"
                                    :key="node.id"
                                    class="cat-node"
                                    :class="{ 'has-children': node.children.length > 0, 'is-open': isBranchExpanded(node.id) }"
                                >
                                    <div class="categories-menu-item cat-row cat-row-parent" :class="{ 'is-on': isCategoryChecked(node.id) }">
                                        <button
                                            v-if="node.children.length"
                                            type="button"
                                            class="cat-twisty"
                                            :aria-expanded="isBranchExpanded(node.id)"
                                            :aria-label="$t('show_subcategories')"
                                            :title="$t('show_subcategories')"
                                            @click="toggleBranch(node.id)"
                                        >
                                            <el-icon><ArrowDown /></el-icon>
                                        </button>
                                        <span v-else class="cat-twisty is-leaf" aria-hidden="true"></span>
                                        <el-checkbox
                                            :model-value="isCategoryChecked(node.id)"
                                            :indeterminate="isCategoryPartiallyChecked(node)"
                                            @update:model-value="(val) => toggleCategory(node.id, val)"
                                        />
                                        <button
                                            type="button"
                                            class="cat-name cat-name-btn"
                                            @click="node.children.length ? toggleBranch(node.id) : toggleCategory(node.id, !isCategoryChecked(node.id))"
                                        >
                                            {{ categoryLabel(node) }}
                                        </button>
                                        <span v-if="node.children.length" class="cat-sub-count">
                                            {{ $t('subcategories_count', { n: node.children.length }) }}
                                        </span>
                                        <span v-if="node.product_count !== undefined && node.product_count !== null" class="cat-count">{{ node.product_count }}</span>
                                    </div>
                                    <div v-if="node.children.length && isBranchExpanded(node.id)" class="cat-children">
                                        <label
                                            v-for="child in node.children"
                                            :key="child.id"
                                            class="categories-menu-item cat-row cat-row-child"
                                            :class="{ 'is-covered': isCoveredByParent(child), 'is-on': isCategoryChecked(child.id) }"
                                            :title="isCoveredByParent(child) ? $t('included_via_parent_classification') : ''"
                                        >
                                            <el-checkbox
                                                :model-value="isCategoryChecked(child.id)"
                                                :disabled="isCoveredByParent(child)"
                                                @update:model-value="(val) => toggleCategory(child.id, val)"
                                            />
                                            <span class="cat-name">{{ categoryLabel(child) }}</span>
                                            <span v-if="child.product_count !== undefined && child.product_count !== null" class="cat-count">{{ child.product_count }}</span>
                                        </label>
                                    </div>
                                </div>
                                <p v-if="categoryTree.length === 0" class="cat-empty">{{ $t('no_matching_classifications') }}</p>
                            </div>
                            <p class="categories-menu-footer">
                                {{ selectedCategoryIds.length === 0
                                    ? $t('all_classifications_included')
                                    : $t('classifications_filter_summary', { cats: effectiveCategoryCount, items: selectedCategoryProductCount }) }}
                            </p>
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
                        <el-tooltip :content="arrangeTooltip" placement="bottom" effect="dark">
                            <span class="arrange-toggle-wrap">
                                <button
                                    type="button"
                                    class="btn-ghost btn-icon"
                                    :class="{ active: arrangeMode }"
                                    :disabled="!arrangeMode && !canArrange"
                                    @click="toggleArrangeMode"
                                >
                                    <el-icon><Sort /></el-icon>
                                    {{ arrangeMode ? $t('done') : $t('arrange_order') }}
                                </button>
                            </span>
                        </el-tooltip>
                        <el-tooltip :content="$t('reset_toolbar_tooltip')" placement="bottom" effect="dark">
                            <button type="button" class="btn-ghost btn-icon" @click="resetFilters">
                                <el-icon><Refresh /></el-icon>
                                {{ $t('reset') }}
                            </button>
                        </el-tooltip>
                    </div>

                    <div class="tools-group tools-group-export">
                        <el-popover placement="bottom-end" trigger="click" width="336" popper-class="columns-popover">
                            <template #reference>
                                <button type="button" class="btn-ghost btn-columns btn-icon">
                                    <el-icon><Grid /></el-icon>
                                    {{ $t('columns_and_sizes') }}
                                </button>
                            </template>
                            <div class="columns-menu">
                                <div class="columns-menu-head">
                                    <p class="columns-menu-title">{{ $t('choose_columns_to_print') }}</p>
                                    <button
                                        type="button"
                                        class="columns-menu-reset"
                                        :disabled="!sizesCustomised"
                                        :title="$t('reset_sizes')"
                                        @click="resetSizes"
                                    >
                                        <el-icon><RefreshLeft /></el-icon>
                                        {{ $t('reset_sizes') }}
                                    </button>
                                </div>

                                <!-- One row per column: whether it prints, and how
                                     much of the table's width it takes. The width
                                     is a share of whatever columns are showing, so
                                     a hidden column's slider would mean nothing. -->
                                <div v-for="col in columnOptions" :key="col.key" class="columns-menu-item">
                                    <div class="columns-menu-item-head">
                                        <!-- The width readout sits outside the
                                             label, or reading the number would
                                             switch the column off. -->
                                        <label class="columns-menu-toggle">
                                            <el-checkbox
                                                :model-value="visibleColumns[col.key]"
                                                :disabled="visibleColumns[col.key] && selectedColumnCount === 1"
                                                @update:model-value="(val) => toggleColumn(col.key, val)"
                                            />
                                            <span class="columns-menu-label">{{ $t(col.label) }}</span>
                                        </label>
                                        <span v-if="visibleColumns[col.key]" class="columns-menu-pct">{{ columnPct(col.key) }}%</span>
                                    </div>
                                    <el-slider
                                        v-if="visibleColumns[col.key]"
                                        class="columns-menu-slider"
                                        :model-value="columnPct(col.key)"
                                        :min="MIN_COLUMN_PCT"
                                        :max="MAX_COLUMN_PCT"
                                        :step="1"
                                        :disabled="selectedColumnCount === 1"
                                        :show-tooltip="false"
                                        size="small"
                                        @input="(val) => setColumnPct(col.key, val)"
                                    />
                                </div>
                                <p class="columns-menu-hint">{{ $t('drag_column_edge_hint') }}</p>

                                <div class="columns-menu-section">
                                    <div class="columns-menu-item-head">
                                        <span class="columns-menu-label">{{ $t('row_height') }}</span>
                                        <span class="columns-menu-pct">{{ effectiveRowHeight }} px</span>
                                    </div>
                                    <el-slider
                                        class="columns-menu-slider"
                                        :model-value="effectiveRowHeight"
                                        :min="MIN_ROW_HEIGHT"
                                        :max="MAX_ROW_HEIGHT"
                                        :step="5"
                                        :show-tooltip="false"
                                        size="small"
                                        @input="setRowHeight"
                                    />
                                    <!-- Height is really a question of how many
                                         products land on a printed page, so the
                                         panel answers in those terms and offers
                                         the round numbers as one click. -->
                                    <div class="rows-per-page">
                                        <span class="rows-per-page-label">{{ $t('rows_per_page_estimate', { n: rowsPerPage }) }}</span>
                                        <span class="rows-per-page-chips">
                                            <button
                                                v-for="n in [3, 4, 5, 6]"
                                                :key="n"
                                                type="button"
                                                class="rows-per-page-chip"
                                                :class="{ active: rowsPerPage === n }"
                                                @click="setRowsPerPage(n)"
                                            >{{ n }}</button>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </el-popover>
                        <div class="export-actions">
                            <button type="button" class="btn-ghost btn-print-settings" :title="$t('print_settings')" @click="openPrintSettings">
                                <el-icon><Operation /></el-icon>
                                <span>{{ $t('print_settings') }}</span>
                            </button>
                            <button type="button" class="btn-ghost btn-print-preview" :title="$t('print_preview')" @click="openPrintPreview">
                                <el-icon><View /></el-icon>
                                <span>{{ $t('print_preview') }}</span>
                            </button>
                            <button type="button" class="btn-pdf" :disabled="printLoading || total === 0" @click="printPage">
                                <el-icon v-if="printLoading && prepMode === 'print'" class="is-loading"><Loading /></el-icon>
                                <el-icon v-else><Printer /></el-icon>
                                <span>{{ printLoading && prepMode === 'print' ? $t('loading') : $t('print') }}</span>
                            </button>
                            <button type="button" class="btn-pdf-download" :disabled="printLoading || total === 0" @click="downloadPdf">
                                <el-icon v-if="printLoading && prepMode === 'pdf'" class="is-loading"><Loading /></el-icon>
                                <el-icon v-else><Download /></el-icon>
                                <span>{{ printLoading && prepMode === 'pdf' ? $t('loading') : $t('download_pdf') }}</span>
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
            <div class="summary-item is-price">
                <span class="summary-value">{{ formatSummaryPrice(printableSum) }}</span>
                <span class="summary-label">{{ $t('sum_of_prices') }}</span>
            </div>
        </div>

        <div v-if="selectedCategoryChips.length" class="active-filters screen-only">
            <span class="active-filters-label">{{ $t('classifications') }}:</span>
            <span v-for="chip in selectedCategoryChips" :key="chip.id" class="filter-chip" :class="{ 'is-branch': chip.subCount > 0 }">
                <!-- A subcategory carries its section name so "Mixers" reads as
                     "Bahsas > Mixers" instead of floating free of its parent. -->
                <span v-if="chip.parentName" class="filter-chip-parent">{{ chip.parentName }}</span>
                {{ chip.name }}
                <span v-if="chip.subCount > 0" class="filter-chip-sub">{{ $t('subcategories_count', { n: chip.subCount }) }}</span>
                <button type="button" class="filter-chip-remove" :aria-label="$t('clear')" @click="toggleCategory(chip.id, false)">&times;</button>
            </span>
            <button type="button" class="filter-clear-all" @click="clearCategories">{{ $t('clear') }}</button>
        </div>

        <!-- Arrange mode: the order the printed sheet reads in, set by dragging.
             It works off the whole filtered catalogue rather than the page on
             screen, so a product can be moved past a page boundary, and it drops
             the price/stock columns because none of that is being decided here. -->
        <div v-if="arrangeMode" class="arrange-panel screen-only">
            <div class="arrange-head">
                <div class="arrange-head-text">
                    <h3>{{ $t('arrange_print_order') }}</h3>
                    <p>{{ $t('arrange_print_order_hint') }}</p>
                </div>
                <button type="button" class="btn-arrange-done" @click="exitArrangeMode">
                    <el-icon><Check /></el-icon>
                    {{ $t('done') }}
                </button>
            </div>

            <div v-if="arrangeLoading" class="arrange-loading">
                <el-icon class="is-loading"><Loading /></el-icon>
                {{ $t('loading_full_catalogue') }}
            </div>

            <el-empty
                v-else-if="arrangeSections.length === 0"
                :image-size="60"
                :description="$t('no_products_to_arrange')"
            />

            <template v-else>
            <div
                v-for="section in arrangeSections"
                :key="sectionKey(section)"
                class="arrange-section"
                :class="{ 'is-collapsed': !isSectionOpen(section) }"
            >
                <div class="arrange-section-head">
                    <button
                        type="button"
                        class="arrange-twisty"
                        :aria-expanded="isSectionOpen(section)"
                        :aria-label="section.name"
                        @click="toggleSection(section)"
                    >
                        <el-icon><ArrowDown /></el-icon>
                    </button>
                    <span class="arrange-section-name">{{ section.name }}</span>
                    <span class="arrange-section-count">
                        {{ $t('section_products_count', { n: section.groups.length }) }}
                    </span>
                    <span class="arrange-section-status" :class="`is-${sectionStatus[sectionKey(section)] || 'idle'}`">
                        <template v-if="sectionStatus[sectionKey(section)] === 'saving'">
                            <el-icon class="is-loading"><Loading /></el-icon>{{ $t('saving') }}
                        </template>
                        <template v-else-if="sectionStatus[sectionKey(section)] === 'saved'">
                            <el-icon><CircleCheck /></el-icon>{{ $t('order_saved') }}
                        </template>
                        <template v-else-if="sectionStatus[sectionKey(section)] === 'error'">
                            <el-icon><WarningFilled /></el-icon>{{ $t('failed_to_save_order') }}
                        </template>
                    </span>
                    <el-tooltip :content="$t('sort_alphabetically')" placement="top" effect="dark">
                        <button type="button" class="arrange-section-btn" @click="sortSectionAlphabetically(section)">
                            {{ $t('a_to_z') }}
                        </button>
                    </el-tooltip>
                </div>

                <draggable
                    v-if="isSectionOpen(section)"
                    v-model="section.groups"
                    item-key="key"
                    handle=".arrange-handle"
                    ghost-class="arrange-ghost"
                    :animation="150"
                    class="arrange-list"
                    @end="saveSectionOrder(section)"
                >
                    <template #item="{ element, index }">
                        <div class="arrange-row">
                            <span class="arrange-handle" :title="$t('drag_to_reorder')">
                                <el-icon><Rank /></el-icon>
                            </span>
                            <span class="arrange-pos">{{ index + 1 }}</span>
                            <EntityImage
                                :src="element.product.image_main"
                                type="product"
                                :size="34"
                                shape="square"
                                :lazy="false"
                            />
                            <span class="arrange-name">
                                {{ element.product.name_ar || element.product.name_en }}
                            </span>
                            <span v-if="element.items.length > 1" class="arrange-variants">
                                {{ $t('section_variants_count', { n: element.items.length }) }}
                            </span>
                            <span class="arrange-price">
                                {{ formatSummaryPrice(element.items[0]?.displayPrice ?? 0) }}
                            </span>
                        </div>
                    </template>
                </draggable>
            </div>
            </template>
        </div>

        <div v-else v-loading="loading" class="offer-table-wrap screen-only">
            <ProductOfferTable :groups="groupedProducts" :loading="loading" :editing-id="editingId" :edit-value="editValue" :visible-columns="visibleColumns"
                :column-widths="columnWidths" :row-height="rowHeight" @update:column-widths="applyColumnWidths"
                :editing-stock-id="editingStockId" :edit-stock-value="editStockValue" :item-status="itemStatus"
                @start-edit="startEdit" @commit-edit="commitEdit" @cancel-edit="cancelEdit"
                @start-edit-stock="startEditStock" @commit-edit-stock="commitEditStock" @cancel-edit-stock="cancelEditStock"
                @edit-item="openEditItemDialog"
                @edit-specs="openSpecsDialog"
                @remove-variant="removeVariant"
                @remove-item="removeItem"
                @update-image="updateItemImage" @clear-image="clearItemImage"
                @add-variant="openAddVariantDialog" />
        </div>

        <!-- Print/PDF-only layout: holds cover, official header banner, metadata, full catalogue table and footer -->
        <div class="print-sheet-wrap print-only" :class="{ 'pdf-render': pdfRendering }">
            <!-- Optional Full-page Cover -->
            <div v-if="printSettings.showCover" class="print-cover">
                <img :src="'/cover.jpeg'" alt="Catalog Cover">
            </div>

            <div class="print-document-content">
                <!-- Official Header Banner and Document Metadata -->
                <div v-if="printSettings.showHeader" ref="printHeaderRef" class="print-document-header-box">
                    <PrintDocumentHeader
                        :header-style="printSettings.headerStyle || 'official'"
                        :title="printSettings.title || $t('price_offer')"
                        :subtitle="'OFFICIAL PRICE QUOTATION'"
                        :customer-name="printSettings.customerName"
                        :date="printSettings.date"
                        :notes="printSettings.notes"
                        :show-contacts="printSettings.showContacts !== false"
                        :banner-src="'/Header.jpeg'"
                    />
                </div>

                <div class="offer-table-wrap">
                    <ProductOfferTable
                        ref="printTableRef"
                        :groups="printGroups"
                        :loading="false"
                        :editing-id="null"
                        :edit-value="''"
                        print-mode
                        :visible-columns="effectivePrintColumns"
                        :column-widths="columnWidths"
                        :row-height="rowHeight"
                    />
                </div>

                <div v-if="printSettings.showFooter" class="print-document-footer">
                    <div class="footer-company-info">
                        <span class="f-comp">{{ $t('site_fallback_name') }}</span>
                        <span class="f-sep">·</span>
                        <span class="f-cr">س.ت: 5048</span>
                        <span class="f-sep">·</span>
                        <span class="f-addr">ريف دمشق - معربا</span>
                        <span class="f-sep">·</span>
                        <span class="f-phone" dir="ltr">0980831477 - 0962889577</span>
                    </div>
                </div>
            </div>
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
                <VariantSpecsEditor
                    v-model="addVariantForm.specs"
                    :label-suggestions="specLabelSuggestions"
                    :copy-sources="specCopySources(addVariantTarget?.product?.id)"
                />
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
                <template v-if="editItemIsVariant">
                    <VariantSpecsEditor
                        v-model="editItemSpecs"
                        :label-suggestions="specLabelSuggestions"
                        :copy-sources="specCopySources(editItemTarget?.group?.product?.id, editItemTarget?.item?.id)"
                    />
                    <div v-if="editItemSiblingCount > 0" class="specs-apply-siblings">
                        <el-checkbox v-model="applySpecsToSiblings">
                            {{ $t('vs_apply_to_siblings', { n: editItemSiblingCount }) }}
                        </el-checkbox>
                    </div>
                    <p v-if="editItemServerErrors.specs" class="specs-server-error">{{ editItemServerErrors.specs }}</p>
                </template>
            </el-form>
            <template #footer>
                <el-button @click="closeEditItemDialog">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" :loading="editItemSaving" :icon="Check" @click="saveEditItem">
                    {{ $t('common.save') }}
                </el-button>
            </template>
        </el-dialog>

        <SpecsEditDialog
            v-model="specsDialogVisible"
            :product-name="specsDialog.productName"
            :variant-label="specsDialog.variantLabel"
            :is-variant="specsDialog.isVariant"
            :description="specsDialog.description"
            :own-specs="specsDialog.ownSpecs"
            :option-count="specsDialog.optionCount"
            :sibling-count="specsDialog.siblingIds.length"
            :label-suggestions="specLabelSuggestions"
            :copy-sources="specsDialog.copySources"
            :saving="specsDialogSaving"
            :server-error="specsDialogError"
            @save="saveSpecsDialog"
        />

        <!-- Print & PDF Settings Dialog -->
        <el-dialog
            v-model="printSettingsVisible"
            :title="$t('print_settings')"
            width="820px"
            class="print-settings-dialog"
            :close-on-click-modal="true"
            append-to-body
        >
            <div class="print-settings-content">
                <!-- Scope Summary Banner -->
                <div class="print-summary-card">
                    <div class="summary-stat">
                        <span class="stat-num">{{ printableCount }}</span>
                        <span class="stat-lbl">{{ $t('product') }}</span>
                    </div>
                    <div class="summary-sep"></div>
                    <div class="summary-stat">
                        <span class="stat-num">{{ catalogueSections.length }}</span>
                        <span class="stat-lbl">{{ $t('classifications') }}</span>
                    </div>
                    <div class="summary-sep"></div>
                    <div class="summary-stat is-price">
                        <span class="stat-num">{{ formatSummaryPrice(printableSum) }}</span>
                        <span class="stat-lbl">{{ $t('sum_of_prices') }}</span>
                    </div>
                    <div v-if="divideOverrideCount > 0" class="summary-badge">
                        {{ $t('divides_applied', { divisor: divideValueApplied }) }}
                    </div>
                </div>

                <!-- Quick Presets Row -->
                <div class="presets-row">
                    <span class="presets-title">{{ $t('common.quick_actions') || 'قوالب سريعة' }}:</span>
                    <button type="button" class="preset-chip" @click="applyClientPreset">
                        <el-icon><User /></el-icon>
                        {{ $t('client_preset') }}
                    </button>
                    <button type="button" class="preset-chip" @click="applyFullCatalogPreset">
                        <el-icon><Collection /></el-icon>
                        {{ $t('full_catalog_preset') }}
                    </button>
                    <button type="button" class="preset-chip" @click="applyInkSaverPreset">
                        <i class="fas fa-leaf"></i>
                        {{ $t('ink_saver_preset') || 'موفر للحبر (نص فقط)' }}
                    </button>
                    <button type="button" class="preset-chip" @click="applyInternalPreset">
                        <i class="fas fa-warehouse"></i>
                        {{ $t('internal_preset') || 'مخزون داخلي' }}
                    </button>
                </div>

                <!-- Settings Tabs -->
                <el-tabs v-model="activePrintSettingsTab" class="print-settings-tabs">
                    <!-- Tab 1: Design, Header & Structure -->
                    <el-tab-pane :label="$t('print_options') || 'قالب ونمط الطباعة'" name="design">
                        <div class="tab-pane-content">
                            <!-- Orientation Selector -->
                            <div class="setting-item-box">
                                <div class="setting-item-head">
                                    <div class="setting-info">
                                        <div class="setting-title">{{ $t('page_orientation') || 'اتجاه الصفحة' }}</div>
                                        <div class="setting-desc">{{ (printSettings.orientation || 'portrait') === 'portrait' ? 'A4 عمودي - مناسب للعروض القياسية' : 'A4 أفقي - مناسب للجداول العريضة وتعدد الأعمدة' }}</div>
                                    </div>
                                    <div class="orient-pill-selector">
                                        <button
                                            type="button"
                                            class="style-pill-btn"
                                            :class="{ active: (printSettings.orientation || 'portrait') === 'portrait' }"
                                            @click="setOrientation('portrait')"
                                        >
                                            <i class="fas fa-file-alt"></i>
                                            <span>{{ $t('portrait') || 'عمودي' }}</span>
                                        </button>
                                        <button
                                            type="button"
                                            class="style-pill-btn"
                                            :class="{ active: printSettings.orientation === 'landscape' }"
                                            @click="setOrientation('landscape')"
                                        >
                                            <i class="fas fa-file-alt fa-rotate-90"></i>
                                            <span>{{ $t('landscape') || 'أفقي' }}</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Header Style Box -->
                            <div class="setting-item-box" :class="{ active: printSettings.showHeader }">
                                <div class="setting-item-head">
                                    <div class="setting-info">
                                        <div class="setting-title">{{ $t('include_company_header') }}</div>
                                        <div class="setting-desc">{{ $t('include_company_header_desc') }}</div>
                                    </div>
                                    <el-switch v-model="printSettings.showHeader" @change="savePrintSettings" />
                                </div>
                                <div v-if="printSettings.showHeader" class="setting-header-preview-box">
                                    <div class="header-style-pill-selector">
                                        <button
                                            type="button"
                                            class="style-pill-btn"
                                            :class="{ active: (printSettings.headerStyle || 'official') === 'official' }"
                                            @click="setHeaderStyle('official')"
                                        >
                                            <i class="fas fa-certificate"></i>
                                            <span>{{ $t('official_header') || 'ترويسة رسمية بالشعار' }}</span>
                                        </button>
                                        <button
                                            type="button"
                                            class="style-pill-btn"
                                            :class="{ active: printSettings.headerStyle === 'compact' }"
                                            @click="setHeaderStyle('compact')"
                                        >
                                            <i class="fas fa-compress-alt"></i>
                                            <span>{{ $t('compact_header') || 'مدمجة' }}</span>
                                        </button>
                                        <button
                                            type="button"
                                            class="style-pill-btn"
                                            :class="{ active: printSettings.headerStyle === 'banner' }"
                                            @click="setHeaderStyle('banner')"
                                        >
                                            <i class="fas fa-image"></i>
                                            <span>{{ $t('banner_header') || 'بانر' }}</span>
                                        </button>
                                    </div>

                                    <div class="live-header-preview-card">
                                        <PrintDocumentHeader
                                            preview-mode
                                            :header-style="printSettings.headerStyle || 'official'"
                                            :title="printSettings.title || $t('price_offer')"
                                            :subtitle="'OFFICIAL PRICE QUOTATION'"
                                            :customer-name="printSettings.customerName"
                                            :date="printSettings.date"
                                            :notes="printSettings.notes"
                                            :show-contacts="printSettings.showContacts !== false"
                                            :banner-src="'/Header.jpeg'"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Cover Page Box -->
                            <div class="setting-item-box" :class="{ active: printSettings.showCover }">
                                <div class="setting-item-head">
                                    <div class="setting-info">
                                        <div class="setting-title">{{ $t('include_cover_page') }}</div>
                                        <div class="setting-desc">{{ $t('include_cover_page_desc') }}</div>
                                    </div>
                                    <el-switch v-model="printSettings.showCover" @change="savePrintSettings" />
                                </div>
                                <div v-if="printSettings.showCover" class="setting-thumb-preview cover-thumb">
                                    <img :src="'/cover.jpeg'" alt="Cover Preview" />
                                </div>
                            </div>

                            <!-- Document Structure Toggles -->
                            <div class="settings-toggles-row">
                                <label class="toggle-checkbox-label">
                                    <el-checkbox v-model="printSettings.showContacts" @change="savePrintSettings" />
                                    <span>{{ $t('show_contacts_in_header') || 'إظهار بيانات التواصل في الترويسة' }}</span>
                                </label>
                                <label class="toggle-checkbox-label">
                                    <el-checkbox v-model="printSettings.showPageNumbers" @change="savePrintSettings" />
                                    <span>{{ $t('show_page_numbers') }}</span>
                                </label>
                                <label class="toggle-checkbox-label">
                                    <el-checkbox v-model="printSettings.showFooter" @change="savePrintSettings" />
                                    <span>{{ $t('show_print_footer') }}</span>
                                </label>
                                <label class="toggle-checkbox-label is-warning">
                                    <el-checkbox v-model="printSettings.hideInventoryInPrint" @change="savePrintSettings" />
                                    <span>{{ $t('hide_stock_for_clients') }}</span>
                                </label>
                            </div>
                        </div>
                    </el-tab-pane>

                    <!-- Tab 2: Offer Info & Client Details -->
                    <el-tab-pane :label="$t('offer_title') + ' & ' + $t('client_name')" name="info">
                        <div class="tab-pane-content">
                            <div class="fields-grid">
                                <div class="field-group">
                                    <label class="field-label">{{ $t('offer_title') }}</label>
                                    <el-input
                                        v-model="printSettings.title"
                                        :placeholder="$t('price_offer')"
                                        clearable
                                        @input="savePrintSettings"
                                    />
                                </div>
                                <div class="field-group">
                                    <label class="field-label">{{ $t('client_name') }}</label>
                                    <el-input
                                        v-model="printSettings.customerName"
                                        :placeholder="$t('client_name_placeholder')"
                                        clearable
                                        @input="savePrintSettings"
                                    />
                                </div>
                                <div class="field-group">
                                    <label class="field-label">{{ $t('offer_date') }}</label>
                                    <el-input
                                        v-model="printSettings.date"
                                        type="date"
                                        @change="savePrintSettings"
                                    />
                                </div>
                                <div class="field-group">
                                    <label class="field-label">{{ $t('offer_notes') }}</label>
                                    <el-input
                                        v-model="printSettings.notes"
                                        :placeholder="$t('offer_notes_placeholder')"
                                        clearable
                                        @input="savePrintSettings"
                                    />
                                </div>
                            </div>

                            <!-- Quick Note Chips -->
                            <div class="quick-notes-section">
                                <div class="quick-notes-title">
                                    <i class="fas fa-magic"></i>
                                    <span>{{ $t('quick_terms') || 'شروط وملاحظات سريعة (انقر للإضافة)' }}:</span>
                                </div>
                                <div class="quick-notes-chips">
                                    <button
                                        v-for="(n, nIdx) in quickNoteSuggestions"
                                        :key="nIdx"
                                        type="button"
                                        class="quick-note-chip"
                                        @click="appendQuickNote(n)"
                                    >
                                        + {{ n }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </el-tab-pane>

                    <!-- Tab 3: Columns & Products -->
                    <el-tab-pane :label="$t('print_columns_options') || 'الأعمدة والمنتجات'" name="columns">
                        <div class="tab-pane-content">
                            <div class="columns-toggles-grid">
                                <div class="column-toggle-card">
                                    <div class="column-toggle-info">
                                        <strong>{{ $t('include_images_in_print') || 'تضمين صور المنتجات' }}</strong>
                                        <small>{{ printSettings.showImages !== false ? 'يتم عرض صور المنتجات بجودة عالية' : 'نص فقط - يوفر حبر الطابعة' }}</small>
                                    </div>
                                    <el-switch v-model="printSettings.showImages" @change="savePrintSettings" />
                                </div>
                                <div class="column-toggle-card">
                                    <div class="column-toggle-info">
                                        <strong>{{ $t('include_details_in_print') || 'تضمين تفاصيل المنتج' }}</strong>
                                        <small>{{ $t('details') }} (المقاس واللون والوحدة)</small>
                                    </div>
                                    <el-switch v-model="printSettings.showDetails" @change="savePrintSettings" />
                                </div>
                                <div class="column-toggle-card">
                                    <div class="column-toggle-info">
                                        <strong>{{ $t('include_specs_in_print') || 'تضمين المواصفات الفنية' }}</strong>
                                        <small>{{ $t('specifications') }} والجداول التفصيلية</small>
                                    </div>
                                    <el-switch v-model="printSettings.showSpecs" @change="savePrintSettings" />
                                </div>
                                <div class="column-toggle-card">
                                    <div class="column-toggle-info">
                                        <strong>{{ $t('include_offer_price_in_print') || 'تضمين سعر العرض' }}</strong>
                                        <small>{{ $t('offer_price') }} وسعر التخفيض الموحد</small>
                                    </div>
                                    <el-switch v-model="printSettings.showOfferPrice" @change="savePrintSettings" />
                                </div>
                                <div class="column-toggle-card is-warn">
                                    <div class="column-toggle-info">
                                        <strong>{{ $t('hide_stock_for_clients') }}</strong>
                                        <small>{{ $t('hide_stock_for_clients_hint') }}</small>
                                    </div>
                                    <el-switch v-model="printSettings.hideInventoryInPrint" @change="savePrintSettings" />
                                </div>
                            </div>

                            <div class="rows-estimate-card">
                                <span class="rows-label">{{ $t('rows_per_page_estimate', { n: rowsPerPage }) }}</span>
                                <div class="rows-chips">
                                    <button
                                        v-for="n in [3, 4, 5, 6]"
                                        :key="n"
                                        type="button"
                                        class="rows-per-page-chip"
                                        :class="{ active: rowsPerPage === n }"
                                        @click="setRowsPerPage(n)"
                                    >{{ n }}</button>
                                </div>
                            </div>
                        </div>
                    </el-tab-pane>
                </el-tabs>
            </div>

            <template #footer>
                <div class="print-dialog-footer">
                    <el-button @click="printSettingsVisible = false">{{ $t('common.cancel') }}</el-button>
                    <div class="footer-primary-actions">
                        <el-button
                            :icon="View"
                            @click="openPrintPreview"
                        >
                            {{ $t('print_preview') }}
                        </el-button>
                        <el-button
                            type="primary"
                            :loading="printLoading && prepMode === 'pdf'"
                            :disabled="printLoading || total === 0"
                            :icon="Download"
                            @click="downloadPdf"
                        >
                            {{ $t('download_pdf_now') }}
                        </el-button>
                        <el-button
                            type="danger"
                            :loading="printLoading && prepMode === 'print'"
                            :disabled="printLoading || total === 0"
                            :icon="Printer"
                            @click="printPage"
                        >
                            {{ $t('print_now') }}
                        </el-button>
                    </div>
                </div>
            </template>
        </el-dialog>

        <!-- High-Fidelity Print Preview Dialog -->
        <el-dialog
            v-model="previewModalVisible"
            :title="$t('print_preview_title') || 'معاينة عرض الأسعار قبل الطباعة'"
            fullscreen
            class="print-preview-dialog"
            append-to-body
            destroy-on-close
        >
            <div class="preview-dialog-body">
                <!-- Preview Top Control Bar -->
                <div class="preview-control-bar">
                    <div class="preview-stats">
                        <span class="preview-stat-item">
                            <strong>{{ printableCount }}</strong> {{ $t('product') }}
                        </span>
                        <span class="preview-sep">·</span>
                        <span class="preview-stat-item is-price">
                            <strong>{{ formatSummaryPrice(printableSum) }}</strong>
                        </span>
                        <span v-if="printSettings.customerName" class="preview-client-badge">
                            <el-icon><User /></el-icon> {{ printSettings.customerName }}
                        </span>
                    </div>

                    <div class="preview-options-quick">
                        <div class="preview-orient-toggle">
                            <button
                                type="button"
                                class="orient-btn"
                                :class="{ active: (printSettings.orientation || 'portrait') === 'portrait' }"
                                @click="setOrientation('portrait')"
                            >
                                <i class="fas fa-file-alt"></i>
                                <span>{{ $t('portrait') }}</span>
                            </button>
                            <button
                                type="button"
                                class="orient-btn"
                                :class="{ active: printSettings.orientation === 'landscape' }"
                                @click="setOrientation('landscape')"
                            >
                                <i class="fas fa-file-alt fa-rotate-90"></i>
                                <span>{{ $t('landscape') }}</span>
                            </button>
                        </div>
                    </div>

                    <div class="preview-main-actions">
                        <el-button
                            :icon="Operation"
                            @click="openPrintSettings"
                        >
                            {{ $t('print_settings') }}
                        </el-button>
                        <el-button
                            type="primary"
                            :loading="printLoading && prepMode === 'pdf'"
                            :disabled="printLoading || total === 0"
                            :icon="Download"
                            @click="downloadPdf"
                        >
                            {{ $t('download_pdf_now') }}
                        </el-button>
                        <el-button
                            type="danger"
                            :loading="printLoading && prepMode === 'print'"
                            :disabled="printLoading || total === 0"
                            :icon="Printer"
                            @click="printFromPreview"
                        >
                            {{ $t('print_now') }}
                        </el-button>
                    </div>
                </div>

                <!-- Realistic A4 Canvas Frame -->
                <div class="preview-scroll-viewport">
                    <!-- Full-bleed Cover Page Sheet (Page 1) -->
                    <div
                        v-if="printSettings.showCover"
                        class="preview-page-canvas preview-cover-canvas"
                        :class="`orient-${printSettings.orientation || 'portrait'}`"
                    >
                        <div class="preview-sheet-cover">
                            <img :src="'/cover.jpeg'" alt="Catalog Cover" />
                        </div>
                    </div>

                    <!-- Document Sheet (Page 2+) -->
                    <div
                        class="preview-page-canvas"
                        :class="`orient-${printSettings.orientation || 'portrait'}`"
                    >
                        <div class="preview-sheet-content">
                            <PrintDocumentHeader
                                v-if="printSettings.showHeader"
                                :header-style="printSettings.headerStyle || 'official'"
                                :title="printSettings.title || $t('price_offer')"
                                :subtitle="'OFFICIAL PRICE QUOTATION'"
                                :customer-name="printSettings.customerName"
                                :date="printSettings.date"
                                :notes="printSettings.notes"
                                :show-contacts="printSettings.showContacts !== false"
                                :banner-src="'/Header.jpeg'"
                            />

                            <div class="preview-table-container">
                                <ProductOfferTable
                                    :groups="printGroups.length ? printGroups : groupedProducts"
                                    :loading="false"
                                    :editing-id="null"
                                    :edit-value="''"
                                    print-mode
                                    :visible-columns="effectivePrintColumns"
                                    :column-widths="columnWidths"
                                    :row-height="rowHeight"
                                />
                            </div>

                            <div v-if="printSettings.showFooter" class="print-document-footer">
                                <div class="footer-company-info">
                                    <span class="f-comp">{{ $t('site_fallback_name') }}</span>
                                    <span class="f-sep">·</span>
                                    <span class="f-cr">س.ت: 5048</span>
                                    <span class="f-sep">·</span>
                                    <span class="f-addr">ريف دمشق - معربا</span>
                                    <span class="f-sep">·</span>
                                    <span class="f-phone" dir="ltr">0980831477 - 0962889577</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </el-dialog>

        <div v-if="total > 0 && !arrangeMode" class="pagination-wrapper screen-only">
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
import PrintDocumentHeader from '@/components/admin/PrintDocumentHeader.vue';
import VariantSpecsEditor from '@/components/admin/products/VariantSpecsEditor.vue';
import SpecsEditDialog from '@/components/admin/products/SpecsEditDialog.vue';
import { parseDescriptionLines, serializeDescriptionSpecs } from '@/utils/productSpecs';
import EntityImage from '@/components/admin/EntityImage.vue';
import { useI18n } from 'vue-i18n';
import { ref, computed, reactive, onMounted, onUnmounted, nextTick, watch, defineAsyncComponent } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useProductsStore } from '@/stores/products';
import { productsApi } from '@/api/products';
import { waitForImages, renderTableToPdf } from '@/utils/pdfExport';
import { matchesSearch } from '@/utils/search';
import { useOfflineSync } from '@/Composables/useOfflineSync';
import { Search, Loading, Close, Download, Refresh, RefreshLeft, Operation, Grid, Delete, Connection, CircleCheck, MagicStick, Check, ArrowDown, Sort, Rank, WarningFilled, Printer, User, Collection, Picture, Document, View } from '@element-plus/icons-vue';

// vuedraggable 4.1.0 points its `module` field at an unminified UMD build, so
// bundling it statically costs every visit to this screen ~200 kB for a control
// only used when someone deliberately rearranges the sheet. Loaded on demand.
const draggable = defineAsyncComponent(() => import('vuedraggable'));

const { t } = useI18n();
const store = useProductsStore();

const searchQuery = ref('');
// Multi-classification filter — empty means every classification is included.
// Only *explicitly* picked ids live here: ticking a parent section stores the
// parent alone and the API expands it to the subcategories underneath, so the
// filter stays readable ("Bahsas") instead of listing 27 child ids.
const selectedCategoryIds = ref([]);
const categorySearch = ref('');
const categoryPopoverVisible = ref(false);
// Which parent sections are expanded in the picker. A search temporarily
// expands every branch that has a match, without disturbing this set.
const expandedCategoryIds = ref([]);
const HIDE_EMPTY_CATEGORIES_KEY = 'price_offer_hide_empty_categories';
function loadHideEmptyCategories() {
    try {
        return localStorage.getItem(HIDE_EMPTY_CATEGORIES_KEY) === '1';
    } catch {
        return false;
    }
}
// Roughly a third of the classifications hold no products at all; hiding them
// keeps the list short without losing the ability to see them again.
const hideEmptyCategories = ref(loadHideEmptyCategories());
const currentPage = ref(1);
const pageSize = ref(50);
const divideValue = ref(null);

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
    specs: [],
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

/* ---- Removing a row -----------------------------------------------------
 *
 * Neither of these is reversible: the catalogue has no soft deletes, so a
 * removed record is gone. Both therefore confirm, name the thing by name, and
 * say what else goes with it — and the server refuses the cases that would
 * take something else down quietly (a variant still on a shelf, a product
 * whose stock-movement history would cascade away with it).
 * ----------------------------------------------------------------------- */

/** The product record as the store currently holds it, so the row can be dropped in place. */
const storedProduct = (group) => store.products.find((p) => String(p.id) === String(group.product.id));

async function removeVariant(group, item) {
    const variantId = Number(String(item.id).slice(2));
    const label = [item.size, item.color, item.unit].filter(Boolean).join(' · ') || `#${variantId}`;

    try {
        await ElMessageBox.confirm(
            t('confirm_remove_variant_message', { variant: label }),
            t('remove_variant'),
            {
                type: 'warning',
                confirmButtonText: t('remove_variant'),
                cancelButtonText: t('cancel'),
                confirmButtonClass: 'el-button--danger',
            }
        );
    } catch {
        return; // dismissed
    }

    try {
        await productsApi.deleteVariant(variantId);

        // Drop it from the store so the row goes without a full refetch.
        const product = storedProduct(group);
        if (product && Array.isArray(product.variants)) {
            product.variants = product.variants.filter((v) => Number(v.id) !== variantId);
        }

        flashMsg(t('variant_removed'));
    } catch (error) {
        // The server refuses a variant still in stock or held by other
        // records, and says which; that sentence is more use than a generic
        // failure, so it is shown as-is.
        ElMessage.error(error?.response?.data?.message || t('failed_to_remove_variant'));
    }
}

async function removeItem(group) {
    const product = group.product;
    const name = product.name_ar || product.name_en || `#${product.id}`;
    const variantCount = Array.isArray(product.variants) ? product.variants.length : 0;

    try {
        await ElMessageBox.confirm(
            variantCount > 0
                ? t('confirm_remove_item_with_variants_message', { product: name, count: variantCount })
                : t('confirm_remove_item_message', { product: name }),
            t('remove_item'),
            {
                type: 'warning',
                confirmButtonText: t('remove_item'),
                cancelButtonText: t('cancel'),
                confirmButtonClass: 'el-button--danger',
            }
        );
    } catch {
        return;
    }

    try {
        await productsApi.delete(product.id);

        const index = store.products.findIndex((p) => String(p.id) === String(product.id));
        if (index !== -1) store.products.splice(index, 1);

        flashMsg(t('item_removed'));
    } catch (error) {
        const message = error?.response?.data?.message;
        const reason = error?.response?.data?.data?.reason;

        // A product with stock or a movement history is not deleted — it is
        // retired. Offering that here means the refusal ends in a way forward
        // rather than a dead end.
        if (reason === 'has_history' || reason === 'has_stock') {
            offerDeactivation(group, message);
            return;
        }

        ElMessage.error(message || t('failed_to_remove_item'));
    }
}

/**
 * What to do with a product that cannot be deleted without erasing its own
 * record: take it out of the catalogue and leave the history standing.
 */
async function offerDeactivation(group, reasonMessage) {
    try {
        await ElMessageBox.confirm(
            `${reasonMessage}\n\n${t('deactivate_instead_question')}`,
            t('remove_item'),
            {
                type: 'warning',
                confirmButtonText: t('deactivate_item'),
                cancelButtonText: t('cancel'),
            }
        );
    } catch {
        return;
    }

    await saveField(`p-${group.product.id}`, { is_active: false });

    const index = store.products.findIndex((p) => String(p.id) === String(group.product.id));
    if (index !== -1) store.products.splice(index, 1);

    flashMsg(t('item_deactivated'));
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
    return [v.size, v.color, v.material].map((s) => String(s || '').trim()).join('|') + '|' + specsKey(v.specs);
}

async function saveAddVariant() {
    if (!addVariantFormRef.value || !addVariantTarget.value) return;
    const group = addVariantTarget.value;
    const already = group.items.some((item) =>
        [item.size, item.color, item.unit].map((s) => String(s || '').trim()).join('|') + '|' + specsKey(item.specs) === unitDetailKey()
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
        specs: cleanSpecs(addVariantForm.specs),
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
    editItemSpecs.value = editItemIsVariant.value ? cleanSpecs(source.specs) : [];
    editItemSpecsBaseline.value = specsKey(editItemSpecs.value);
    applySpecsToSiblings.value = false;
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

    return Object.keys(editItemForm).some((key) => !sameFieldValue(editItemForm[key], baseline[key]))
        || specsKey(editItemSpecs.value) !== editItemSpecsBaseline.value
        || applySpecsToSiblings.value;
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
    const newDetails = [editItemForm.size, editItemForm.color, editItemForm.unit]
        .map((s) => String(s || '').trim()).join('|');
    const newKey = newDetails + '|' + specsKey(isVariant ? editItemSpecs.value : []);
    const collides = group.items.some((other) => {
        if (String(other.id) === rowId) return false;
        return [other.size, other.color, other.unit]
            .map((s) => String(s || '').trim()).join('|') + '|' + specsKey(other.specs) === newKey && newDetails !== '||';
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
    const newSpecs = cleanSpecs(editItemSpecs.value);
    if (isVariant && specsKey(newSpecs) !== editItemSpecsBaseline.value) {
        rowPatch.specs = newSpecs;
    }
    // Every other variant under the same product, when the box is ticked.
    const siblingIds = isVariant && applySpecsToSiblings.value ? editItemSiblingIds() : [];

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
            for (const sid of siblingIds) {
                results.push(await saveField(`v-${sid}`, { specs: newSpecs }));
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

// ---- Specifications dialog ------------------------------------------------
// Opened from a row's specifications cell. It edits the two lists the cell
// merges: the variant's own details and the product's "Label: value" lines,
// which are stored in its Arabic description and read under every option.
const specsDialogVisible = ref(false);
const specsDialogSaving = ref(false);
const specsDialogError = ref('');
const specsDialog = reactive({
    productId: null,
    variantId: null,
    productName: '',
    variantLabel: '',
    isVariant: false,
    description: '',
    ownSpecs: [],
    optionCount: 0,
    siblingIds: [],
    copySources: [],
});

function openSpecsDialog(group, item) {
    const productId = item.productId ?? group.product.id;
    const product = store.products.find((p) => String(p.id) === String(productId)) || group.product;
    const isVariant = String(item.id).startsWith('v-');
    const variantId = isVariant ? Number(String(item.id).slice(2)) : null;
    const variants = Array.isArray(product.variants) ? product.variants : [];
    const variant = isVariant ? variants.find((v) => Number(v.id) === variantId) : null;
    Object.assign(specsDialog, {
        productId: product.id,
        variantId,
        productName: product.name_ar || product.name_en || '',
        variantLabel: isVariant
            ? [variant?.size, variant?.color, variant?.material].filter(Boolean).join(' · ') || variant?.sku || ''
            : '',
        isVariant,
        // The Arabic text is what the price list reads; an English-only
        // product is edited through its English text instead.
        description: String((product.description_ar || product.description_en) ?? ''),
        ownSpecs: isVariant ? cleanSpecs(variant?.specs) : [],
        optionCount: variants.length,
        siblingIds: isVariant ? variants.map((v) => Number(v.id)).filter((id) => id !== variantId) : [],
        copySources: isVariant ? specCopySources(product.id, item.id) : [],
    });
    specsDialogError.value = '';
    specsDialogVisible.value = true;
}

async function saveSpecsDialog({ productRows, ownRows, applyToSiblings }) {
    const product = store.products.find((p) => String(p.id) === String(specsDialog.productId));
    const field = product && !product.description_ar && product.description_en ? 'description_en' : 'description_ar';
    specsDialogSaving.value = true;
    specsDialogError.value = '';
    try {
        const results = [];
        if (productRows) {
            results.push(await saveField(`p-${specsDialog.productId}`, { [field]: serializeDescriptionSpecs(productRows) }));
        }
        if (ownRows && specsDialog.isVariant) {
            results.push(await saveField(`v-${specsDialog.variantId}`, { specs: ownRows }));
            if (applyToSiblings) {
                for (const sid of specsDialog.siblingIds) {
                    results.push(await saveField(`v-${sid}`, { specs: ownRows }));
                }
            }
        }
        const failed = results.find((r) => !r.ok);
        if (failed) {
            // The dialog stays open with what was typed; some of the requests
            // may already have gone through, and the cells show those.
            specsDialogError.value = fieldErrorMessage(failed.error) || t('failed_to_save');
            return;
        }
        specsDialogVisible.value = false;
        if (results.some((r) => !r.queued)) ElMessage.success(t('saved_successfully'));
    } finally {
        specsDialogSaving.value = false;
    }
}

// ---- Variant details (specs) ----------------------------------------------
// Each variant carries its own ordered [{label, value}] list — "Power: 750W".
// Blank rows are the editor's scratch space, so they never leave the page.
const editItemSpecs = ref([]);
const editItemSpecsBaseline = ref('');
const applySpecsToSiblings = ref(false);

function cleanSpecs(list) {
    return (Array.isArray(list) ? list : [])
        .map((r) => ({ label: String(r?.label ?? '').trim(), value: String(r?.value ?? '').trim() }))
        .filter((r) => r.value !== '');
}

function specsKey(list) {
    return cleanSpecs(list).map((r) => `${r.label}:${r.value}`).join('¦');
}

function variantsOf(productId) {
    const product = store.products.find((p) => String(p.id) === String(productId));
    return Array.isArray(product?.variants) ? product.variants : [];
}

// Sibling variants whose details can be copied in, the one being edited left out.
function specCopySources(productId, excludeRowId = null) {
    if (productId == null) return [];
    const exclude = excludeRowId && String(excludeRowId).startsWith('v-') ? Number(String(excludeRowId).slice(2)) : null;
    return variantsOf(productId)
        .filter((v) => Number(v.id) !== exclude)
        .map((v) => ({
            id: v.id,
            label: [v.size, v.color, v.material].filter(Boolean).join(' · ') || v.sku || `#${v.id}`,
            specs: cleanSpecs(v.specs),
        }));
}

function editItemSiblingIds() {
    const target = editItemTarget.value;
    if (!target || !editItemIsVariant.value) return [];
    const self = Number(String(target.item.id).slice(2));
    return variantsOf(target.group.product.id).map((v) => Number(v.id)).filter((id) => id !== self);
}

const editItemSiblingCount = computed(() => (editItemVisible.value ? editItemSiblingIds().length : 0));

// Labels already in use anywhere on the loaded list — variant details first,
// then the "Label: value • …" lines product descriptions are written in —
// most used first, so typing "Po" offers "Power" the same way every time.
const specLabelSuggestions = computed(() => {
    const counts = new Map();
    const bump = (label) => {
        const l = String(label || '').trim();
        if (l && l.length <= 100) counts.set(l, (counts.get(l) || 0) + 1);
    };
    for (const p of store.products) {
        for (const v of Array.isArray(p.variants) ? p.variants : []) {
            for (const row of Array.isArray(v.specs) ? v.specs : []) bump(row.label);
        }
        const desc = String(p.description_ar || '');
        if (desc.includes('•')) {
            for (const part of desc.split('•')) {
                const idx = part.indexOf(':');
                if (idx > 0) bump(part.slice(0, idx));
            }
        }
    }
    return [...counts.entries()].sort((a, b) => b[1] - a[1]).map(([label]) => label);
});

// Server field names to form field names. Only `material` differs: the variant
// endpoint calls it that, the dialog shows it in the shared "unit" input.
function mapServerErrorsToForm(error, isVariant) {
    const errors = serverFieldErrors(error);

    // A detail row's error arrives as `specs.2.value`; the dialog shows one
    // line under the details list for all of them.
    for (const key of Object.keys(errors)) {
        if (key.startsWith('specs.')) {
            errors.specs = errors.specs || errors[key];
            delete errors[key];
        }
    }

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
const defaultColumns = { code: true, image: true, product: false, illustration: true, details: true, specs: true, price: true, offer: true, pack: true, inventory: true };
const columnOptions = [
    { key: 'code', label: 'item_code' },
    { key: 'image', label: 'image' },
    { key: 'product', label: 'product' },
    { key: 'illustration', label: 'illustrative_image' },
    { key: 'details', label: 'details' },
    { key: 'specs', label: 'specifications' },
    { key: 'price', label: 'the_price' },
    { key: 'offer', label: 'offer_price' },
    { key: 'pack', label: 'pack_quantity' },
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

// ---- Column widths and row height --------------------------------------
// Widths are shares of the table, matching COLUMN_SHARES in ProductOfferTable:
// they are re-normalised over the columns that are showing, so one stored set
// keeps its meaning when a column is switched off. The row height is the
// height of one product group, in the CSS pixels the PDF capture measures in;
// null means "leave it to the default fifth of a page".
const SIZES_STORAGE_KEY = 'price_offer_column_sizes';
const defaultColumnWidths = { code: 15, image: 50, product: 35, illustration: 35, details: 25, specs: 35, price: 15, offer: 15, pack: 12, inventory: 15 };
const DEFAULT_ROW_HEIGHT = 295;
const MIN_ROW_HEIGHT = 150;
const MAX_ROW_HEIGHT = 520;
const MIN_COLUMN_PCT = 6;
const MAX_COLUMN_PCT = 80;
/**
 * Body height of one exported page, in those same CSS pixels: A4 less its 24pt
 * margins is 793.89pt, the capture is scaled 547.28pt / 960px = 0.5701pt per
 * pixel, and the header re-drawn on every page costs about 42px of what is
 * left. Only used to tell the user roughly how many products a page will hold.
 */
const PAGE_BODY_PX = 1350;

function loadSizes() {
    const fallback = { widths: { ...defaultColumnWidths }, rowHeight: null };
    try {
        const raw = localStorage.getItem(SIZES_STORAGE_KEY);
        if (!raw) return fallback;
        const saved = JSON.parse(raw) || {};
        return {
            widths: { ...defaultColumnWidths, ...(saved.widths || {}) },
            rowHeight: Number(saved.rowHeight) > 0 ? Number(saved.rowHeight) : null,
        };
    } catch {
        return fallback;
    }
}

const savedSizes = loadSizes();
const columnWidths = ref(savedSizes.widths);
const rowHeight = ref(savedSizes.rowHeight);

const effectiveRowHeight = computed(() => rowHeight.value || DEFAULT_ROW_HEIGHT);
const rowsPerPage = computed(() => Math.max(1, Math.floor(PAGE_BODY_PX / effectiveRowHeight.value)));
const sizesCustomised = computed(() => rowHeight.value !== null
    || Object.keys(defaultColumnWidths).some((key) => columnWidths.value[key] !== defaultColumnWidths[key]));

// A drag on a header edge (or on a slider) reports a new size on every pointer
// move, so the write to storage waits for the gesture to settle rather than
// running dozens of times a second.
let sizesPersistTimeout = null;
function persistSizes() {
    clearTimeout(sizesPersistTimeout);
    sizesPersistTimeout = setTimeout(() => {
        try {
            localStorage.setItem(SIZES_STORAGE_KEY, JSON.stringify({
                widths: columnWidths.value,
                rowHeight: rowHeight.value,
            }));
        } catch {
            // Private mode / quota exceeded — the sizes just won't outlive the visit.
        }
    }, 250);
}

/** What share of the visible table a column actually occupies, as a percentage. */
function columnPct(key) {
    const total = Object.keys(defaultColumnWidths)
        .filter((col) => visibleColumns.value[col])
        .reduce((sum, col) => sum + (columnWidths.value[col] || 0), 0);
    if (!total) return 0;
    return Math.round((columnWidths.value[key] || 0) / total * 100);
}

/**
 * Gives a column `pct` of the table. The others hold their proportions to each
 * other and share out what is left, which is the same arithmetic a drag on the
 * header edge performs — the slider and the handle are two ways into it.
 */
function setColumnPct(key, pct) {
    const others = Object.keys(defaultColumnWidths)
        .filter((col) => col !== key && visibleColumns.value[col])
        .reduce((sum, col) => sum + (columnWidths.value[col] || 0), 0);
    if (others <= 0) return;
    const share = Math.min(Math.max(Number(pct) || 0, MIN_COLUMN_PCT), MAX_COLUMN_PCT) / 100;
    columnWidths.value = {
        ...columnWidths.value,
        [key]: Number((share * others / (1 - share)).toFixed(3)),
    };
    persistSizes();
}

/** Applies a whole share map — what a drag on a header edge hands back. */
function applyColumnWidths(next) {
    columnWidths.value = { ...columnWidths.value, ...next };
    persistSizes();
}

function setRowHeight(px) {
    rowHeight.value = Math.min(Math.max(Math.round(Number(px) || 0), MIN_ROW_HEIGHT), MAX_ROW_HEIGHT);
    persistSizes();
}

function setRowsPerPage(n) {
    setRowHeight(Math.floor(PAGE_BODY_PX / n));
}

function resetSizes() {
    columnWidths.value = { ...defaultColumnWidths };
    rowHeight.value = null;
    persistSizes();
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

// --- Classification tree ---
// The taxonomy is two levels deep: sections at the top and the subcategories
// filed under them. The picker used to flatten both into one list, which made
// a section indistinguishable from a subcategory and — because a section like
// Bahsas holds no products directly — made ticking it look like an empty
// classification. Here the children hang under their parent instead.

const categoryById = computed(() => {
    const map = new Map();
    for (const c of categories.value) map.set(c.id, c);
    return map;
});

function categoryLabel(cat) {
    return cat ? (cat.name_ar || cat.name_en || cat.name || '') : '';
}

function categoryMatches(cat, q) {
    return matchesSearch([cat.name_ar, cat.name_en], q);
}

function categoryProductCount(cat) {
    const n = Number(cat?.product_count);
    return Number.isFinite(n) ? n : 0;
}

// Full tree, before search/empty filtering. A child whose parent is missing
// from the payload (inactive, or filtered out server-side) is promoted to the
// top level so it can never silently disappear from the picker.
const fullCategoryTree = computed(() => {
    const nodes = new Map();
    for (const c of categories.value) nodes.set(c.id, { ...c, children: [] });
    const roots = [];
    for (const node of nodes.values()) {
        const parent = node.parent_id != null ? nodes.get(node.parent_id) : null;
        if (parent && parent.id !== node.id) parent.children.push(node);
        else roots.push(node);
    }
    return roots;
});

const parentCategoryCount = computed(() => fullCategoryTree.value.filter((n) => n.children.length > 0).length);

// Search keeps a section visible when the section itself matches (all of its
// children come along) or when any child matches (only the matching children
// are shown), so a hit is always shown in the context it lives in.
const categoryTree = computed(() => {
    const q = categorySearch.value;
    const hideEmpty = hideEmptyCategories.value;
    const out = [];
    for (const node of fullCategoryTree.value) {
        const selfMatch = categoryMatches(node, q);
        let children = selfMatch ? node.children : node.children.filter((c) => categoryMatches(c, q));
        if (hideEmpty) {
            children = children.filter((c) => categoryProductCount(c) > 0 || isCategoryChecked(c.id));
        }
        if (!selfMatch && children.length === 0) continue;
        if (hideEmpty && children.length === 0 && categoryProductCount(node) === 0 && !isCategoryChecked(node.id)) continue;
        out.push({ ...node, children });
    }
    return out;
});

const isSearchingCategories = computed(() => categorySearch.value.trim().length > 0);

function isBranchExpanded(id) {
    // While searching, every branch left standing is one with a match in it.
    if (isSearchingCategories.value) return true;
    return expandedCategoryIds.value.includes(id);
}

function toggleBranch(id) {
    if (isSearchingCategories.value) return;
    expandedCategoryIds.value = expandedCategoryIds.value.includes(id)
        ? expandedCategoryIds.value.filter((x) => x !== id)
        : [...expandedCategoryIds.value, id];
}

const allBranchesExpanded = computed(() => {
    const branches = categoryTree.value.filter((n) => n.children.length > 0);
    return branches.length > 0 && branches.every((n) => isBranchExpanded(n.id));
});

function toggleAllCategoryBranches() {
    if (isSearchingCategories.value) return;
    expandedCategoryIds.value = allBranchesExpanded.value
        ? []
        : categoryTree.value.filter((n) => n.children.length > 0).map((n) => n.id);
}

function toggleHideEmptyCategories(val) {
    hideEmptyCategories.value = !!val;
    try {
        localStorage.setItem(HIDE_EMPTY_CATEGORIES_KEY, val ? '1' : '0');
    } catch {
        // Private mode / quota exceeded — the preference just won't persist.
    }
}

// A subcategory is included when it is picked itself *or* when its parent
// section is picked; the second case is shown ticked-but-locked so it is clear
// the products are in without implying the row can be removed on its own.
function isCoveredByParent(cat) {
    return cat?.parent_id != null && selectedCategoryIds.value.includes(cat.parent_id);
}

function isCategoryChecked(id) {
    if (selectedCategoryIds.value.includes(id)) return true;
    return isCoveredByParent(categoryById.value.get(id));
}

function isCategoryPartiallyChecked(node) {
    if (selectedCategoryIds.value.includes(node.id)) return false;
    return node.children.some((c) => selectedCategoryIds.value.includes(c.id));
}

function categoryNameById(id) {
    return categoryLabel(categoryById.value.get(id));
}

const selectedCategoryChips = computed(() => selectedCategoryIds.value.map((id) => {
    const cat = categoryById.value.get(id);
    const parent = cat?.parent_id != null ? categoryById.value.get(cat.parent_id) : null;
    const node = fullCategoryTree.value.find((n) => n.id === id);
    return {
        id,
        name: categoryLabel(cat) || `#${id}`,
        parentName: categoryLabel(parent),
        subCount: node ? node.children.length : 0,
    };
}));

// How many classifications the filter really covers (a picked section counts
// its subcategories too) and how many products that adds up to.
const effectiveCategoryCount = computed(() => {
    const ids = new Set();
    for (const id of selectedCategoryIds.value) {
        ids.add(id);
        const node = fullCategoryTree.value.find((n) => n.id === id);
        if (node) for (const c of node.children) ids.add(c.id);
    }
    return ids.size;
});

const selectedCategoryProductCount = computed(() => selectedCategoryIds.value.reduce((sum, id) => {
    // `product_count` on a section already covers its subcategories, so the
    // section's own number is the whole subtree — don't add the children again.
    const cat = categoryById.value.get(id);
    return sum + categoryProductCount(cat);
}, 0));

function toggleCategory(id, checked) {
    const node = fullCategoryTree.value.find((n) => n.id === id);
    const childIds = node ? node.children.map((c) => c.id) : [];
    if (checked) {
        // Picking a section supersedes any of its subcategories already picked.
        const next = selectedCategoryIds.value.filter((x) => !childIds.includes(x));
        if (!next.includes(id)) next.push(id);
        selectedCategoryIds.value = next;
        if (childIds.length && !expandedCategoryIds.value.includes(id)) {
            expandedCategoryIds.value = [...expandedCategoryIds.value, id];
        }
    } else {
        selectedCategoryIds.value = selectedCategoryIds.value.filter((x) => x !== id && !childIds.includes(x));
    }
    onCategoryChange();
}

function selectAllCategories() {
    // Top-level rows only: a section already pulls in everything beneath it.
    selectedCategoryIds.value = categoryTree.value.map((c) => c.id);
    onCategoryChange();
}

function clearCategories() {
    selectedCategoryIds.value = [];
    onCategoryChange();
}

const formatSummaryPrice = (v) => Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// --- Arrange mode: the order the printed sheet reads in ---------------------
// `products.sort_order` already existed and was editable one product at a time
// on the product form; nothing had ever sorted by it. The API now offers a
// `catalogue` ordering (classification, then this priority) and this screen is
// where the priority actually gets set — by dragging, over the whole filtered
// catalogue rather than the fifty rows that happen to be on screen.

const arrangeMode = ref(false);
const arrangeLoading = ref(false);
// A mutable copy of `catalogueSections`; vuedraggable reorders the array it is
// given, and a computed is not that array.
const arrangeSections = ref([]);
const collapsedSections = ref([]);
// Per-section save state: 'saving' | 'saved' | 'error'.
const sectionStatus = ref({});

const sectionKey = (section) => String(section.id ?? 'none');

// A search returns part of a classification, and renumbering part of one would
// hoist those products above every sibling the search filtered out. A
// classification filter is fine: it selects whole sections.
const canArrange = computed(() => searchQuery.value.trim() === '');
const arrangeTooltip = computed(() => (
    canArrange.value ? t('arrange_print_order_tooltip') : t('arrange_needs_no_search')
));

const isSectionOpen = (section) => !collapsedSections.value.includes(sectionKey(section));

function toggleSection(section) {
    const key = sectionKey(section);
    collapsedSections.value = collapsedSections.value.includes(key)
        ? collapsedSections.value.filter((k) => k !== key)
        : [...collapsedSections.value, key];
}

async function ensureCatalogue() {
    if (!fullPrintGroups.value.length) await hydratePrintCatalog();
}

function toggleArrangeMode() {
    if (arrangeMode.value) exitArrangeMode(); else enterArrangeMode();
}

async function enterArrangeMode() {
    arrangeMode.value = true;
    arrangeLoading.value = true;
    try {
        await ensureCatalogue();
        arrangeSections.value = catalogueSections.value.map((s) => ({ ...s, groups: [...s.groups] }));
    } catch {
        ElMessage.error(t('failed_to_bring_products'));
    } finally {
        arrangeLoading.value = false;
    }
}

async function exitArrangeMode() {
    arrangeMode.value = false;
    arrangeSections.value = [];
    sectionStatus.value = {};
    // Re-read so the table and the print sheet come back in the saved order
    // rather than the one they were fetched in.
    await fetchProducts();
    hydratePrintCatalog();
}

function flashSectionStatus(key, state) {
    sectionStatus.value = { ...sectionStatus.value, [key]: state };
    if (state === 'saved') {
        setTimeout(() => {
            if (sectionStatus.value[key] === 'saved') {
                const next = { ...sectionStatus.value };
                delete next[key];
                sectionStatus.value = next;
            }
        }, 2000);
    }
}

// Reflects the new order back into the catalogue snapshot, so the print table
// and the summary follow a drag without waiting for a refetch.
function syncCatalogueOrder() {
    const flat = arrangeSections.value.flatMap((sec) => sec.groups);
    fullPrintGroups.value = flat;
    printGroups.value = withSections(flat);
}

async function saveSectionOrder(section) {
    const key = sectionKey(section);
    // A group can cover several product rows merged under one name, and all of
    // them move together. Inactive products are not in this list and keep the
    // priority they had — they never print, so their position is moot.
    const productIds = section.groups.flatMap((g) => g.productIds);
    if (productIds.length === 0) return;
    flashSectionStatus(key, 'saving');
    try {
        await productsApi.reorder({ category_id: section.id, product_ids: productIds });
        syncCatalogueOrder();
        flashSectionStatus(key, 'saved');
    } catch {
        flashSectionStatus(key, 'error');
        ElMessage.error(t('failed_to_save_order'));
    }
}

function sortSectionAlphabetically(section) {
    const name = (g) => (g.product.name_ar || g.product.name_en || '');
    section.groups = [...section.groups].sort((a, b) => name(a).localeCompare(name(b), 'ar'));
    saveSectionOrder(section);
}

// --- Counts/sums that reflect what will *actually* print ---
// These use the full filtered catalog (all pages across the active category
// filter) rather than the page on screen, so the figures match the printed
// sheet. They fall back to the on-screen groups only while the full catalog is
// still being hydrated.
const printableSum = computed(() => {
    const groups = fullPrintGroups.value.length ? fullPrintGroups.value : groupedProducts.value;
    let sum = 0;
    for (const group of groups) {
        for (const item of group.items) {
            // fullPrintGroups is a snapshot taken at hydration, so read through
            // the live override map — otherwise dividing prices leaves the
            // total showing the undivided figures.
            const override = overrides.value[item.id];
            const p = Number(override !== undefined ? override : item.displayPrice);
            if (Number.isFinite(p)) sum += p;
        }
    }
    return sum;
});

// Full catalog snapshot for printing/PDF — every page, grouped by product name.
const printGroups = ref([]);
// The same groups, kept around so the summary total and the divide tool can
// work off every page in the active filter rather than the one on screen.
const fullPrintGroups = ref([]);
const printTableRef = ref(null);
const printHeaderRef = ref(null);
const printSettingsVisible = ref(false);
const previewModalVisible = ref(false);
const activePrintSettingsTab = ref('design');
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

const PRINT_SETTINGS_STORAGE_KEY = 'price_offer_print_settings';
const defaultPrintSettings = {
    showCover: false,
    showHeader: true,
    showFooter: false,
    showPageNumbers: false,
    headerStyle: 'official',
    showContacts: true,
    orientation: 'portrait',
    showImages: true,
    showSpecs: true,
    showDetails: true,
    showOfferPrice: true,
    title: '',
    customerName: '',
    date: new Date().toISOString().slice(0, 10),
    notes: '',
    hideInventoryInPrint: true,
};

function readStoredPrintSettings() {
    try {
        const raw = localStorage.getItem(PRINT_SETTINGS_STORAGE_KEY);
        if (!raw) return { ...defaultPrintSettings };
        const parsed = JSON.parse(raw);
        return { ...defaultPrintSettings, ...parsed };
    } catch {
        return { ...defaultPrintSettings };
    }
}

const printSettings = ref(readStoredPrintSettings());

function savePrintSettings() {
    try {
        localStorage.setItem(PRINT_SETTINGS_STORAGE_KEY, JSON.stringify(printSettings.value));
    } catch {
        // quota or private mode
    }
}

function setHeaderStyle(style) {
    printSettings.value.headerStyle = style;
    savePrintSettings();
}

function setOrientation(orient) {
    printSettings.value.orientation = orient;
    savePrintSettings();
    applyPrintPageStyle();
}

function applyPrintPageStyle() {
    let styleEl = document.getElementById('price-offer-print-page-style');
    if (!styleEl) {
        styleEl = document.createElement('style');
        styleEl.id = 'price-offer-print-page-style';
        document.head.appendChild(styleEl);
    }
    const isLandscape = printSettings.value.orientation === 'landscape';
    const sizeStr = isLandscape ? 'A4 landscape' : 'A4 portrait';

    styleEl.textContent = `
        @page {
            size: ${sizeStr};
            margin: 0 !important;
        }
        a[href]:after {
            content: none !important;
        }
    `;
}

function openPrintSettings() {
    previewModalVisible.value = false;
    printSettingsVisible.value = true;
}

async function openPrintPreview() {
    printSettingsVisible.value = false;
    if (!printGroups.value.length) {
        await hydratePrintCatalog();
    }
    previewModalVisible.value = true;
}

async function printFromPreview() {
    previewModalVisible.value = false;
    await nextTick();
    await printPage();
}

const quickNoteSuggestions = [
    'الأسعار صالحة لمدة 7 أيام من تاريخ صدورها',
    'الأسعار صالحة لمدة 15 يوماً من تاريخ صدورها',
    'الأسعار قابلة للتعديل دون إشعار مسبق',
    'الأسعار تشمل أجور التوصيل',
    'طريقة الدفع: نقداً عند الاستلام',
];

function appendQuickNote(note) {
    if (!printSettings.value.notes) {
        printSettings.value.notes = note;
    } else if (!printSettings.value.notes.includes(note)) {
        printSettings.value.notes = `${printSettings.value.notes} · ${note}`;
    }
    savePrintSettings();
}

function applyClientPreset() {
    printSettings.value.showCover = false;
    printSettings.value.showHeader = true;
    printSettings.value.showFooter = true;
    printSettings.value.showPageNumbers = true;
    printSettings.value.hideInventoryInPrint = true;
    printSettings.value.showImages = true;
    printSettings.value.showSpecs = true;
    printSettings.value.showDetails = true;
    printSettings.value.showOfferPrice = true;
    printSettings.value.headerStyle = 'official';
    printSettings.value.showContacts = true;
    printSettings.value.orientation = 'portrait';
    if (!printSettings.value.title) {
        printSettings.value.title = t('official_price_offer') || 'عرض أسعار رسمي';
    }
    savePrintSettings();
    applyPrintPageStyle();
    flashMsg(t('client_preset'));
}

function applyFullCatalogPreset() {
    printSettings.value.showCover = true;
    printSettings.value.showHeader = true;
    printSettings.value.showFooter = true;
    printSettings.value.showPageNumbers = true;
    printSettings.value.hideInventoryInPrint = false;
    printSettings.value.showImages = true;
    printSettings.value.showSpecs = true;
    printSettings.value.showDetails = true;
    printSettings.value.showOfferPrice = true;
    printSettings.value.headerStyle = 'official';
    printSettings.value.showContacts = true;
    printSettings.value.orientation = 'portrait';
    if (!printSettings.value.title) {
        printSettings.value.title = t('catalog') || 'كتالوج المنتجات';
    }
    savePrintSettings();
    applyPrintPageStyle();
    flashMsg(t('full_catalog_preset'));
}

function applyInkSaverPreset() {
    printSettings.value.showCover = false;
    printSettings.value.showHeader = true;
    printSettings.value.showFooter = true;
    printSettings.value.showPageNumbers = true;
    printSettings.value.hideInventoryInPrint = true;
    printSettings.value.showImages = false;
    printSettings.value.showSpecs = true;
    printSettings.value.showDetails = true;
    printSettings.value.showOfferPrice = true;
    printSettings.value.headerStyle = 'compact';
    printSettings.value.showContacts = true;
    printSettings.value.orientation = 'portrait';
    savePrintSettings();
    applyPrintPageStyle();
    flashMsg(t('ink_saver_preset') || 'تم تطبيق القالب الاقتصادي الموفر للحبر');
}

function applyInternalPreset() {
    printSettings.value.showCover = false;
    printSettings.value.showHeader = true;
    printSettings.value.showFooter = true;
    printSettings.value.showPageNumbers = true;
    printSettings.value.hideInventoryInPrint = false;
    printSettings.value.showImages = true;
    printSettings.value.showSpecs = true;
    printSettings.value.showDetails = true;
    printSettings.value.showOfferPrice = true;
    printSettings.value.headerStyle = 'compact';
    printSettings.value.showContacts = false;
    printSettings.value.orientation = 'portrait';
    printSettings.value.title = t('internal_preset') || 'قائمة الأسعار وجرد المستودع';
    savePrintSettings();
    applyPrintPageStyle();
    flashMsg(t('internal_preset') || 'تم تطبيق قالب المخزون الداخلي');
}

const hasPrintMeta = computed(() => !!(
    printSettings.value.title ||
    printSettings.value.customerName ||
    printSettings.value.date ||
    printSettings.value.notes
));

const printableCount = computed(() => (
    fullPrintGroups.value.length ? fullPrintGroups.value.length : groupedProducts.value.length
));

// Crucial: unwrapping visibleColumns.value so all columns are included in print/PDF
const effectivePrintColumns = computed(() => {
    const cols = { ...visibleColumns.value };
    if (printSettings.value.hideInventoryInPrint) {
        cols.inventory = false;
    }
    if (printSettings.value.showImages === false) {
        cols.image = false;
    }
    if (printSettings.value.showSpecs === false) {
        cols.specs = false;
    }
    if (printSettings.value.showDetails === false) {
        cols.details = false;
    }
    if (printSettings.value.showOfferPrice === false) {
        cols.offer = false;
    }
    return cols;
});

// Groups rows by product name (not just product id), so products stored as
// separate rows sharing a name (e.g. color variants) print as one entry.
function buildGroups(list) {
    const map = new Map();
    const seenDetails = new Map();
    const order = [];
    for (const p of list) {
        const name = (p.name_ar || p.name_en || '').trim();
        // Keyed by classification as well as name: the sheet reads in sections
        // now, so two products that happen to share a name in different
        // sections are two entries, not one merged row filed under whichever
        // section came first.
        const catId = p.category?.id ?? null;
        const key = `${catId ?? 'none'}|${name || `id-${p.id}`}`;
        if (!map.has(key)) {
            // `productIds` is every row merged under this name — dragging the
            // entry has to move all of them, not just the first.
            map.set(key, { key, product: p, category: p.category ?? null, productIds: [], items: [] });
            seenDetails.set(key, new Set());
            order.push(key);
        }
        if (!map.get(key).productIds.includes(p.id)) map.get(key).productIds.push(p.id);
        const variants = Array.isArray(p.variants) ? p.variants : [];
        // The product's "Label: value" description lines, which each row's
        // specifications cell shows under its own, as the storefront does.
        const baseSpecs = parseDescriptionLines(p.description_ar || p.description_en);
        const productId = p.id;
        const items = variants.length
            ? variants.map((v) => makeItem(`v-${v.id}`, {
                size: v.size || '',
                color: v.color || '',
                unit: v.material || '',
                specs: cleanSpecs(v.specs),
                baseSpecs,
                productId,
                sku: v.sku || p.sku || '',
                price: parseFloat(v.price) || 0,
                stock_quantity: v.stock_quantity ?? 0,
            }))
            : [makeItem(`p-${p.id}`, {
                size: p.size || '',
                color: p.color || '',
                unit: p.unit || '',
                baseSpecs,
                productId,
                sku: p.sku || '',
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
            const detailKey = `${item.size}|${item.color}|${item.unit}|${specsKey(item.specs)}`;
            if (seen.has(detailKey)) continue;
            seen.add(detailKey);
            group.items.push(item);
        }
    }
    for (const group of map.values()) {
        const p = group.product;
        group.offerItem = makeItem(`p-${p.id}`, {
            productId: p.id,
            price: parseFloat(p.price) || 0,
        });
    }
    return order.map((k) => map.get(k));
}

// Marks the first group of each classification run with the heading the table
// should print above it. `rowCount` is what that group occupies in the DOM —
// the PDF renderer walks rows group by group to choose its page breaks, so a
// heading row has to be declared or every break after the first lands short.
function withSections(groups) {
    let currentId;
    return groups.map((g) => {
        const id = g.category?.id ?? null;
        const opensSection = id !== currentId;
        currentId = id;
        return opensSection
            ? {
                ...g,
                sectionLabel: g.category
                    ? (g.category.name_ar || g.category.name_en)
                    : t('uncategorised'),
                rowCount: g.items.length + 1,
            }
            : { ...g, sectionLabel: null, rowCount: g.items.length };
    });
}

const groupedProducts = computed(() => withSections(buildGroups(products.value)));

// The full filtered catalogue split into classification sections — what the
// arrange screen drags and what the section counts are read from.
const catalogueSections = computed(() => {
    const source = fullPrintGroups.value.length ? fullPrintGroups.value : buildGroups(products.value);
    const sections = [];
    let current = null;
    for (const g of source) {
        const id = g.category?.id ?? null;
        if (!current || current.id !== id) {
            current = {
                id,
                name: g.category ? (g.category.name_ar || g.category.name_en) : t('uncategorised'),
                groups: [],
            };
            sections.push(current);
        }
        current.groups.push(g);
    }
    return sections;
});

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
            sort: 'catalogue',
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
// A second caller (saving or applying a list) joins the in-flight run instead
// of returning early to an empty catalogue.
let hydrating = null;
function hydratePrintCatalog() {
    if (hydrating) return hydrating;
    printCancelled.value = false;
    hydrating = (async () => {
        try {
            const groups = buildGroups(await fetchAllProductsFlat());
            fullPrintGroups.value = groups;
            printGroups.value = withSections(groups);
        } catch {
            // Silent — Print/Download PDF still fetch fresh, visibly, on demand.
        } finally {
            hydrating = null;
        }
    })();
    return hydrating;
}

const resetFilters = () => {
    if (arrangeMode.value) {
        arrangeMode.value = false;
        arrangeSections.value = [];
        sectionStatus.value = {};
    }
    searchQuery.value = '';
    selectedCategoryIds.value = [];
    categorySearch.value = '';
    expandedCategoryIds.value = [];
    divideValue.value = null;
    currentPage.value = 1;
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
    // Every row in the filtered catalogue, not just the page on screen —
    // otherwise pages 2+ print at their undivided price.
    const source = fullPrintGroups.value.length ? fullPrintGroups.value : groupedProducts.value;
    const isGroupWithVariants = (group) => (group.items?.length > 1) || String(group.items?.[0]?.id ?? '').startsWith('v-');
    for (const group of source) {
        if (group.offerItem && isGroupWithVariants(group) && group.offerItem.originalPrice > 0) {
            overrides.value[group.offerItem.id] = Math.round((group.offerItem.originalPrice / v) * 10) / 10;
        }
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
        sort: 'catalogue',
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
    printGroups.value = withSections(groups);
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
    printSettingsVisible.value = false;
    previewModalVisible.value = false;
    prepMode.value = 'print';
    printLoading.value = true;
    try {
        applyPrintPageStyle();
        const tableEl = await prepareFullCatalog();
        if (!tableEl) return;
        printLoading.value = false;

        // Force remove any popup lock class that Element Plus might have left
        document.body.classList.remove('el-popup-parent--hidden');
        document.body.style.removeProperty('overflow');
        document.documentElement.style.removeProperty('overflow');

        // Allow the DOM, transitions, and image paints to settle cleanly before opening the print dialog
        await nextTick();
        await new Promise((resolve) => setTimeout(resolve, 200));
        window.print();
    } catch (e) {
        console.error('Print error:', e);
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
        await new Promise((resolve) => setTimeout(resolve, 100));

        const baseTitle = printSettings.value.title || 'price-offer';
        const safeTitle = baseTitle.replace(/[/\\?%*:|"<>]/g, '-').trim();
        const dateStr = printSettings.value.date || new Date().toISOString().slice(0, 10);

        await renderTableToPdf({
            table: tableEl,
            groups: printGroups.value,
            filename: `${safeTitle}-${dateStr}.pdf`,
            coverSrc: printSettings.value.showCover ? '/cover.jpeg' : null,
            headerEl: printSettings.value.showHeader ? printHeaderRef.value : null,
            showPageNumbers: printSettings.value.showPageNumbers,
            orientation: printSettings.value.orientation || 'portrait',
            footerBrand: 'AWAAN AL-TAKADOM - Sanitary Ware & Building Materials',
            onPageProgress: (loaded, total) => {
                printProgress.value = { loaded, total };
            },
        });
        flashMsg(t('pdf_ready'));
    } catch (err) {
        console.error('PDF export error:', err);
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

const handlePrintKeydown = (e) => {
    if ((e.ctrlKey || e.metaKey) && (e.key === 'p' || e.key === 'P')) {
        e.preventDefault();
        printPage();
    }
};

onMounted(async () => {
    window.addEventListener('keydown', handlePrintKeydown);
    applyPrintPageStyle();
    await store.fetchCategories();
    await fetchProducts();
    hydratePrintCatalog();
    // Restore any edits queued before a reload / while offline, and push them
    // if the connection has already returned.
    applyPendingStatuses();
    if (isOnline.value && pendingCount.value > 0) syncPending();
});

onUnmounted(() => {
    window.removeEventListener('keydown', handlePrintKeydown);
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
.btn-print-settings {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(255, 255, 255, .1);
    border: 1px solid rgba(255, 255, 255, .35);
}
.btn-print-settings:hover {
    background: rgba(255, 255, 255, .2);
    border-color: rgba(255, 255, 255, .65);
    transform: translateY(-1px);
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

.columns-menu-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 6px;
}
.columns-menu-title {
    margin: 0;
    font-size: 12px;
    font-weight: 700;
    color: #64748b;
}
.columns-menu-reset {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    border: none;
    background: none;
    padding: 2px 4px;
    border-radius: 6px;
    color: #2563eb;
    font-size: 11.5px;
    font-weight: 600;
    cursor: pointer;
    transition: background .15s ease, color .15s ease;
}
.columns-menu-reset:hover:not(:disabled) {
    background: #eef2ff;
}
.columns-menu-reset:disabled {
    color: #cbd5e1;
    cursor: default;
}
/* One block per column: the print checkbox on top, its width underneath. The
   slider is indented under the label so the two read as one setting. */
.columns-menu-item {
    padding: 3px 2px;
    font-size: 13px;
    color: #1e293b;
}
.columns-menu-item-head {
    display: flex;
    align-items: center;
    gap: 8px;
}
.columns-menu-toggle {
    flex: 1 1 auto;
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
    cursor: pointer;
}
.columns-menu-label {
    flex: 1 1 auto;
    min-width: 0;
}
.columns-menu-pct {
    flex: 0 0 auto;
    font-size: 11.5px;
    font-weight: 700;
    color: #64748b;
    font-variant-numeric: tabular-nums;
}
.columns-menu-slider {
    margin: -2px 0 0;
    padding-inline-start: 24px;
}
.columns-menu-slider :deep(.el-slider__runway) {
    margin: 8px 0;
}
.columns-menu-hint {
    margin: 6px 0 0;
    font-size: 11px;
    color: #94a3b8;
    line-height: 1.4;
}
.columns-menu-section {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid #e2e8f0;
}
.rows-per-page {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-top: 2px;
}
.rows-per-page-label {
    font-size: 11.5px;
    color: #64748b;
}
.rows-per-page-chips {
    display: inline-flex;
    gap: 4px;
}
.rows-per-page-chip {
    width: 24px;
    height: 24px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    background: #fff;
    color: #475569;
    font-size: 11.5px;
    font-weight: 700;
    cursor: pointer;
    transition: border-color .15s ease, background .15s ease, color .15s ease;
}
.rows-per-page-chip:hover {
    border-color: #2563eb;
    color: #2563eb;
}
.rows-per-page-chip.active {
    background: #2563eb;
    border-color: #2563eb;
    color: #fff;
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

/* Classification tree: sections, their twisty, and the subcategories under it */
.cat-subtools {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin: 0 2px 6px;
    padding-bottom: 6px;
    border-bottom: 1px solid #f1f5f9;
}
.cat-toggle {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 11.5px;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
}
.categories-list {
    padding-inline-end: 2px;
}
.cat-node + .cat-node {
    border-top: 1px solid #f8fafc;
}
.cat-row {
    border-radius: 6px;
}
.cat-row.is-on {
    background: #eef2ff;
}
.cat-row.is-on:hover {
    background: #e0e7ff;
}
.cat-twisty {
    flex: 0 0 auto;
    width: 18px;
    height: 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: none;
    padding: 0;
    color: #94a3b8;
    cursor: pointer;
    border-radius: 4px;
    font-size: 12px;
    transition: transform .15s ease, color .15s ease;
}
.cat-twisty:hover {
    color: #2563eb;
    background: #e2e8f0;
}
.cat-twisty.is-leaf {
    cursor: default;
    pointer-events: none;
}
/* Collapsed sections point along the reading direction, open ones point down. */
.cat-node.has-children:not(.is-open) .cat-twisty {
    transform: rotate(-90deg);
}
[dir="rtl"] .cat-node.has-children:not(.is-open) .cat-twisty {
    transform: rotate(90deg);
}
.cat-name-btn {
    border: none;
    background: none;
    padding: 0;
    margin: 0;
    font: inherit;
    color: inherit;
    text-align: start;
    cursor: pointer;
}
.cat-row-parent .cat-name {
    font-weight: 700;
}
.cat-sub-count {
    flex: 0 0 auto;
    font-size: 10.5px;
    font-weight: 700;
    color: #4f46e5;
    background: #eef2ff;
    border-radius: 999px;
    padding: 1px 7px;
    white-space: nowrap;
}
.cat-children {
    /* A hairline rail down the group ties the subcategories to their section. */
    margin-inline-start: 12px;
    padding-inline-start: 12px;
    border-inline-start: 2px solid #e2e8f0;
}
.cat-row-child {
    font-size: 12.5px;
    color: #475569;
}
.cat-row-child.is-covered {
    opacity: .72;
}
.cat-row-child.is-covered .cat-name {
    color: #64748b;
}
.categories-menu-footer {
    margin: 8px 0 0;
    padding-top: 7px;
    border-top: 1px solid #f1f5f9;
    font-size: 11.5px;
    font-weight: 600;
    color: #64748b;
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

/* Arrange mode — the printed sheet's reading order, set by dragging. */
.arrange-toggle-wrap {
    display: inline-flex;
}
.arrange-panel {
    background: #fff;
    border: 1px solid rgba(15, 23, 42, .1);
    border-radius: 14px;
    padding: 14px 16px 18px;
    box-shadow: 0 6px 18px rgba(15, 23, 42, .06);
}
.arrange-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid rgba(15, 23, 42, .08);
    margin-bottom: 12px;
}
.arrange-head-text h3 {
    margin: 0 0 3px;
    font-size: 15px;
    font-weight: 800;
    color: #111c2c;
}
.arrange-head-text p {
    margin: 0;
    font-size: 12.5px;
    color: #64748b;
}
.btn-arrange-done {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: none;
    border-radius: 9px;
    padding: 8px 16px;
    font-size: 13px;
    font-weight: 700;
    color: #fff;
    background: linear-gradient(135deg, #0f766e, #14b8a6);
    cursor: pointer;
    transition: transform .15s ease, box-shadow .15s ease;
}
.btn-arrange-done:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(15, 118, 110, .35);
}
.arrange-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 40px 0;
    color: #64748b;
    font-size: 13px;
}
.arrange-section + .arrange-section {
    margin-top: 10px;
}
.arrange-section-head {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 10px;
    background: #eef2f7;
    border: 1px solid rgba(15, 23, 42, .08);
    border-radius: 9px;
}
.arrange-section.is-collapsed .arrange-section-head {
    background: #f8fafc;
}
.arrange-twisty {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border: none;
    border-radius: 6px;
    background: transparent;
    color: #475569;
    cursor: pointer;
    transition: transform .15s ease;
}
.arrange-section.is-collapsed .arrange-twisty {
    transform: rotate(-90deg);
}
.arrange-section-name {
    font-size: 13px;
    font-weight: 800;
    color: #111c2c;
}
.arrange-section-count {
    font-size: 11px;
    color: #64748b;
}
.arrange-section-status {
    margin-inline-start: auto;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    font-weight: 700;
}
.arrange-section-status.is-saving { color: #64748b; }
.arrange-section-status.is-saved { color: #0f766e; }
.arrange-section-status.is-error { color: #b00e0e; }
.arrange-section-btn {
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    background: #fff;
    color: #475569;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 8px;
    cursor: pointer;
}
.arrange-section-btn:hover {
    border-color: #2563eb;
    color: #2563eb;
}
.arrange-list {
    padding: 6px 0 2px;
}
.arrange-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 10px;
    margin-top: 4px;
    background: #fff;
    border: 1px solid rgba(15, 23, 42, .08);
    border-radius: 8px;
}
.arrange-row:hover {
    border-color: rgba(37, 99, 235, .35);
    background: #f8fafc;
}
/* The placeholder vuedraggable leaves where the row will land. */
.arrange-ghost {
    opacity: .45;
    background: #e0e7ff;
    border-style: dashed;
    border-color: #6366f1;
}
.arrange-handle {
    display: inline-flex;
    align-items: center;
    color: #94a3b8;
    cursor: grab;
    padding: 2px;
}
.arrange-handle:active {
    cursor: grabbing;
}
.arrange-pos {
    min-width: 24px;
    font-size: 11px;
    font-weight: 700;
    color: #94a3b8;
    font-variant-numeric: tabular-nums;
    text-align: center;
}
.arrange-name {
    flex: 1;
    min-width: 0;
    font-size: 13px;
    font-weight: 600;
    color: #111c2c;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.arrange-variants {
    font-size: 10.5px;
    font-weight: 700;
    color: #64748b;
    background: #f1f5f9;
    border-radius: 999px;
    padding: 2px 8px;
    white-space: nowrap;
}
.arrange-price {
    font-size: 12.5px;
    font-weight: 700;
    color: #b00e0e;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
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
.filter-chip.is-branch {
    background: #e0e7ff;
}
.filter-chip-parent {
    font-size: 10.5px;
    font-weight: 700;
    color: #6366f1;
    opacity: .85;
}
.filter-chip-parent::after {
    content: '\203A';
    margin-inline-start: 4px;
}
[dir="rtl"] .filter-chip-parent::after {
    content: '\2039';
}
.filter-chip-sub {
    font-size: 10px;
    font-weight: 700;
    color: #4338ca;
    background: rgba(255, 255, 255, .7);
    border-radius: 999px;
    padding: 0 6px;
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
.specs-apply-siblings {
    margin-top: 8px;
}

.specs-server-error {
    margin: 6px 0 0;
    font-size: 12px;
    color: var(--el-color-danger);
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

/* Print Settings Dialog Styles */
.print-settings-content {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.print-summary-card {
    display: flex;
    align-items: center;
    justify-content: space-around;
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    border-radius: 12px;
    padding: 12px 16px;
    flex-wrap: wrap;
    gap: 10px;
}
.summary-stat {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
}
.summary-stat .stat-num {
    font-size: 17px;
    font-weight: 800;
    color: #1e293b;
}
.summary-stat .stat-lbl {
    font-size: 11px;
    color: #64748b;
    font-weight: 600;
}
.summary-stat.is-price .stat-num {
    color: #b00e0e;
}
.summary-sep {
    width: 1px;
    height: 28px;
    background: #cbd5e1;
}
.summary-badge {
    background: #e0f2fe;
    color: #0284c7;
    border: 1px solid #bae6fd;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
}
.presets-row {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    font-size: 12px;
}
.presets-title {
    font-weight: 700;
    color: #475569;
}
.preset-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #fff;
    border: 1px solid #cbd5e1;
    padding: 5px 13px;
    border-radius: 999px;
    cursor: pointer;
    font-size: 11.5px;
    font-weight: 600;
    color: #334155;
    transition: all .15s ease;
}
.preset-chip:hover {
    background: #f8fafc;
    border-color: #3b82f6;
    color: #2563eb;
    transform: translateY(-1px);
}
.settings-sections {
    display: flex;
    flex-direction: column;
    gap: 14px;
}
.settings-section {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 14px 16px;
}
.section-title {
    display: flex;
    align-items: center;
    gap: 7px;
    margin: 0 0 12px 0;
    font-size: 13.5px;
    font-weight: 700;
    color: #1e293b;
}
.settings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 12px;
    margin-bottom: 12px;
}
.setting-item-box {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px;
    transition: border-color .15s ease, box-shadow .15s ease;
}
.setting-item-box.active {
    border-color: #93c5fd;
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.08);
}
.setting-item-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
}
.setting-info {
    flex: 1 1 auto;
}
.setting-title {
    font-size: 12.5px;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 3px;
}
.setting-desc {
    font-size: 11px;
    color: #64748b;
    line-height: 1.35;
}
.setting-thumb-preview {
    margin-top: 10px;
    border-radius: 6px;
    overflow: hidden;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 4px;
}
.cover-thumb img {
    max-height: 85px;
    width: auto;
    object-fit: contain;
}
.header-thumb img {
    max-height: 38px;
    width: 100%;
    object-fit: contain;
}
.settings-toggles-row {
    display: flex;
    flex-wrap: wrap;
    gap: 10px 20px;
    align-items: center;
    padding-top: 10px;
    border-top: 1px solid #e2e8f0;
}
.toggle-checkbox-label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 600;
    color: #334155;
    cursor: pointer;
}
.toggle-checkbox-label.is-warning {
    color: #b45309;
}
.fields-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap: 12px;
}
.field-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}
.field-label {
    font-size: 11.5px;
    font-weight: 600;
    color: #475569;
}
.print-dialog-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    gap: 10px;
}
.footer-primary-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Document Print/PDF Layout Elements */
.print-sheet-wrap {
    display: none;
}
.print-sheet-wrap.pdf-render {
    display: block;
    position: fixed;
    top: 0;
    left: -99999px;
    width: 960px;
    background: #fff;
    padding: 16px;
}
.print-document-header-box {
    margin-bottom: 12px;
    width: 100%;
}
.print-document-header {
    margin-bottom: 12px;
}
.setting-header-preview-box {
    margin-top: 10px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.header-style-pill-selector {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.style-pill-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #475569;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
}
.style-pill-btn:hover {
    background: #f1f5f9;
    border-color: #94a3b8;
    color: #0f172a;
}
.style-pill-btn.active {
    background: #eff6ff;
    border-color: #3b82f6;
    color: #1d4ed8;
    font-weight: 700;
    box-shadow: 0 1px 3px rgba(59, 130, 246, 0.15);
}
.style-pill-btn i {
    font-size: 10px;
}
.live-header-preview-card {
    border-radius: 8px;
    overflow: hidden;
}
.print-header-top {
    text-align: center;
    margin-bottom: 8px;
}
.print-header-img {
    max-width: 100%;
    height: auto;
    max-height: 85px;
    object-fit: contain;
    border-radius: 4px;
}
.print-meta-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 8px 14px;
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 7px 12px;
    font-size: 11px;
    margin-bottom: 10px;
}
.print-meta-bar .meta-title-item .meta-title {
    font-size: 14px;
    font-weight: 800;
    color: #0f172a;
}
.print-meta-bar .meta-item {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.print-meta-bar .meta-label {
    color: #64748b;
    font-weight: 600;
}
.print-meta-bar .meta-val {
    color: #0f172a;
    font-weight: 700;
}
.print-document-footer {
    margin-top: 14px;
    padding-top: 8px;
    border-top: 1px solid #cbd5e1;
}
.footer-company-info {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-size: 9.5pt;
    color: #64748b;
    font-weight: 600;
}
.footer-company-info .f-sep {
    color: #94a3b8;
}

.btn-print-preview {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(37, 99, 235, 0.22);
    border: 1px solid rgba(147, 197, 253, 0.45);
    color: #fff;
    cursor: pointer;
    font-size: 12.5px;
    font-weight: 600;
    padding: 8px 16px;
    border-radius: 999px;
    transition: all 0.15s ease;
    white-space: nowrap;
}
.btn-print-preview:hover {
    background: rgba(37, 99, 235, 0.38);
    border-color: rgba(147, 197, 253, 0.8);
    transform: translateY(-1px);
}

.print-settings-tabs :deep(.el-tabs__nav-wrap::after) {
    height: 1px;
    background-color: #e2e8f0;
}
.print-settings-tabs :deep(.el-tabs__item) {
    font-size: 13px;
    font-weight: 700;
}
.tab-pane-content {
    display: flex;
    flex-direction: column;
    gap: 14px;
    padding-top: 6px;
}
.orient-pill-selector {
    display: flex;
    align-items: center;
    gap: 8px;
}
.quick-notes-section {
    margin-top: 6px;
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    border-radius: 8px;
    padding: 10px 12px;
}
.quick-notes-title {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 11.5px;
    font-weight: 700;
    color: #475569;
    margin-bottom: 8px;
}
.quick-notes-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.quick-note-chip {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #334155;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
    text-align: right;
}
.quick-note-chip:hover {
    border-color: #3b82f6;
    background: #eff6ff;
    color: #1d4ed8;
    transform: translateY(-1px);
}
.columns-toggles-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap: 10px;
}
.column-toggle-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 10px 12px;
    gap: 10px;
}
.column-toggle-card.is-warn {
    background: #fffbeb;
    border-color: #fde68a;
}
.column-toggle-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.column-toggle-info strong {
    font-size: 12px;
    color: #1e293b;
}
.column-toggle-info small {
    font-size: 10.5px;
    color: #64748b;
}
.rows-estimate-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 10px 14px;
    margin-top: 6px;
}
.rows-estimate-card .rows-label {
    font-size: 12px;
    font-weight: 700;
    color: #475569;
}
.rows-estimate-card .rows-chips {
    display: flex;
    gap: 6px;
}

/* Print Preview Modal Styles */
.print-preview-dialog :deep(.el-dialog__header) {
    margin-right: 0;
    padding: 14px 20px;
    background: #1e293b;
    border-bottom: 1px solid #334155;
}
.print-preview-dialog :deep(.el-dialog__title) {
    color: #f8fafc;
    font-size: 15px;
    font-weight: 800;
}
.print-preview-dialog :deep(.el-dialog__headerbtn .el-dialog__close) {
    color: #94a3b8;
    font-size: 18px;
}
.print-preview-dialog :deep(.el-dialog__headerbtn:hover .el-dialog__close) {
    color: #f8fafc;
}
.print-preview-dialog :deep(.el-dialog__body) {
    padding: 0;
    background: #0f172a;
    height: calc(100vh - 56px);
    overflow: hidden;
}
.preview-dialog-body {
    display: flex;
    flex-direction: column;
    height: 100%;
}
.preview-control-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 10px 24px;
    background: #1e293b;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    flex-shrink: 0;
    z-index: 10;
    flex-wrap: wrap;
}
.preview-stats {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #cbd5e1;
    font-size: 13px;
}
.preview-stat-item strong {
    color: #ffffff;
    font-weight: 800;
}
.preview-stat-item.is-price strong {
    color: #f87171;
}
.preview-sep {
    color: #475569;
}
.preview-client-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: rgba(59, 130, 246, 0.2);
    color: #93c5fd;
    border: 1px solid rgba(59, 130, 246, 0.4);
    padding: 2px 10px;
    border-radius: 999px;
    font-size: 11.5px;
    font-weight: 700;
}
.preview-options-quick {
    display: flex;
    align-items: center;
    gap: 8px;
}
.preview-orient-toggle {
    display: flex;
    align-items: center;
    background: #0f172a;
    border: 1px solid #334155;
    border-radius: 8px;
    padding: 2px;
    gap: 2px;
}
.orient-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 6px;
    border: none;
    background: transparent;
    color: #94a3b8;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
}
.orient-btn:hover {
    color: #f8fafc;
}
.orient-btn.active {
    background: #3b82f6;
    color: #ffffff;
    font-weight: 700;
}
.preview-main-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}
.preview-scroll-viewport {
    flex: 1 1 auto;
    overflow-y: auto;
    overflow-x: auto;
    padding: 32px 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 32px;
    background: #0f172a;
}
.preview-page-canvas {
    background: #ffffff;
    color: #0f172a;
    box-shadow: 0 12px 36px rgba(0, 0, 0, 0.45);
    border-radius: 4px;
    padding: 12mm 14mm 14mm 14mm;
    box-sizing: border-box;
    transition: width 0.2s ease, max-width 0.2s ease;
    width: 100%;
}
.preview-page-canvas.orient-portrait {
    max-width: 960px;
    min-height: 1200px;
}
.preview-page-canvas.orient-landscape {
    max-width: 1240px;
    min-height: 850px;
}
.preview-cover-canvas {
    padding: 0 !important;
    overflow: hidden !important;
    border: none !important;
}
.preview-cover-canvas.orient-portrait {
    max-width: 960px;
    height: 1358px;
    min-height: 1358px;
}
.preview-cover-canvas.orient-landscape {
    max-width: 1240px;
    height: 876px;
    min-height: 876px;
}
.preview-cover-canvas .preview-sheet-cover {
    width: 100%;
    height: 100%;
    margin: 0 !important;
    padding: 0 !important;
    border: none !important;
    background: #000;
    display: flex;
}
.preview-cover-canvas .preview-sheet-cover img {
    width: 100%;
    height: 100%;
    max-height: none !important;
    object-fit: cover !important;
    display: block;
}
.preview-sheet-content {
    width: 100%;
}
.preview-table-container {
    width: 100%;
    margin-top: 8px;
}

/* Native Print Styles */
@media print {
    html, body {
        overflow: visible !important;
        height: auto !important;
        min-height: 0 !important;
        max-height: none !important;
        background: #fff !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    :global(.el-popup-parent--hidden) {
        overflow: visible !important;
    }
    :global(.admin-layout),
    :global(.admin-main-wrapper),
    :global(.admin-content) {
        overflow: visible !important;
        height: auto !important;
        min-height: 0 !important;
        max-height: none !important;
        margin: 0 !important;
        padding: 0 !important;
        display: block !important;
        position: static !important;
    }
    .offer-toolbar,
    .screen-only,
    :global(.admin-sidebar),
    :global(.admin-header),
    :global(.sidebar-overlay),
    :global(.el-overlay),
    :global(.el-dialog__wrapper),
    :global(.columns-popover),
    :global(.categories-popover),
    :global(.image-editor-popover),
    :global(.print-preview-dialog) {
        display: none !important;
    }
    .price-offer-page {
        padding: 0 !important;
        margin: 0 !important;
    }
    .print-sheet-wrap.print-only {
        display: block !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    .print-cover {
        display: block !important;
        position: relative !important;
        width: 100vw !important;
        height: 100vh !important;
        min-height: 100vh !important;
        max-height: 100vh !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: hidden !important;
        page-break-before: avoid !important;
        page-break-after: always !important;
        break-after: page !important;
        box-sizing: border-box !important;
    }
    .print-cover img {
        width: 100% !important;
        height: 100vh !important;
        min-height: 100vh !important;
        max-height: 100vh !important;
        object-fit: cover !important;
        display: block !important;
    }
    .print-document-content {
        page-break-before: auto !important;
        padding: 10mm 12mm !important;
        box-sizing: border-box !important;
    }
    a[href]:after {
        content: none !important;
    }
    .brand-domain-badge,
    .domain-badge {
        display: none !important;
    }
    .print-document-header-box {
        break-inside: avoid !important;
        page-break-inside: avoid !important;
        margin-bottom: 8px !important;
    }
    .print-document-footer {
        break-inside: avoid !important;
        page-break-inside: avoid !important;
        margin-top: 10px !important;
    }
    .offer-table-wrap {
        max-height: none !important;
        overflow: visible !important;
        border: none !important;
        box-shadow: none !important;
    }
}
</style>
