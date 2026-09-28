<template>
    <div class="inventory-index">
        <AdminPageHeader
            icon="fas fa-warehouse"
            :title="$t('inventory_dashboard')"
            :subtitle="$t('inventory_dashboard_subtitle')"
        >
            <template #actions>
                <el-tooltip :content="$t('refresh')" placement="bottom" :enterable="false">
                    <el-button :icon="Refresh" :loading="refreshing" circle :aria-label="$t('refresh')" @click="refreshAll" />
                </el-tooltip>
                <el-dropdown trigger="click" @command="onToolsCommand">
                    <el-button :loading="exporting || importing">
                        <el-icon><Files /></el-icon>
                        <span>{{ $t('prod_admin_excel') }}</span>
                        <el-icon class="el-icon--right"><ArrowDown /></el-icon>
                    </el-button>
                    <template #dropdown>
                        <el-dropdown-menu>
                            <el-dropdown-item command="export" :icon="Download">
                                {{ activeFilterCount ? $t('inv_admin_export_filtered') : $t('export_balances') }}
                            </el-dropdown-item>
                            <el-dropdown-item command="import" :icon="Upload">
                                {{ $t('import_balances') }}
                            </el-dropdown-item>
                        </el-dropdown-menu>
                    </template>
                </el-dropdown>
                <router-link :to="{ name: 'admin.inventory.movements' }" custom v-slot="{ navigate }">
                    <el-button :icon="Tickets" @click="navigate">{{ $t('movement_log') }}</el-button>
                </router-link>
                <el-button :icon="Switch" @click="openMovement('transfer')">{{ $t('transfer_stock') }}</el-button>
                <el-button type="primary" :icon="Plus" @click="openMovement('in')">{{ $t('inv_admin_record_movement') }}</el-button>
            </template>
        </AdminPageHeader>

        <input ref="fileInput" type="file" accept=".xlsx" class="visually-hidden" @change="onFileSelected" />

        <!-- Stock health. The three status cards are also the table's status
             filter, so the figure and the rows behind it are one click apart. -->
        <AdminStatGrid :min="180">
            <el-card shadow="never" class="stat-card">
                <div class="stat-card-inner">
                    <div class="stat-icon-box value"><el-icon><Coin /></el-icon></div>
                    <div class="stat-details">
                        <h3>{{ summary ? formatMoney(summary.total_value) : '—' }}</h3>
                        <p>{{ $t('total_stock_value') }}</p>
                    </div>
                </div>
            </el-card>
            <el-card shadow="never" class="stat-card">
                <div class="stat-card-inner">
                    <div class="stat-icon-box units"><el-icon><Box /></el-icon></div>
                    <div class="stat-details">
                        <h3>{{ summary ? formatNumber(summary.total_available) : '—' }}</h3>
                        <p>
                            {{ $t('units_available_for_sale') }}
                            <span v-if="summary && heldUnits > 0" class="stat-note">
                                · {{ $t('inv_admin_units_held', { count: formatNumber(heldUnits) }) }}
                            </span>
                        </p>
                    </div>
                </div>
            </el-card>
            <el-card
                v-for="card in statusCards"
                :key="card.key"
                shadow="never"
                class="stat-card is-clickable"
                :class="{ 'is-selected': filters.status === card.key }"
                role="button"
                tabindex="0"
                :aria-pressed="filters.status === card.key"
                @click="toggleStatus(card.key)"
                @keydown.enter.prevent="toggleStatus(card.key)"
                @keydown.space.prevent="toggleStatus(card.key)"
            >
                <div class="stat-card-inner">
                    <div class="stat-icon-box" :class="card.key"><el-icon><component :is="card.icon" /></el-icon></div>
                    <div class="stat-details">
                        <h3>{{ summary ? formatNumber(card.value) : '—' }}</h3>
                        <p>{{ card.title }}</p>
                    </div>
                </div>
            </el-card>
        </AdminStatGrid>

        <div class="overview-grid">
            <!-- Warehouses: each one filters the table -->
            <section class="panel-card">
                <header class="panel-head">
                    <h2><el-icon><OfficeBuilding /></el-icon> {{ $t('stock_across_warehouses') }}</h2>
                    <span v-if="warehouses.length" class="panel-hint">{{ $t('inv_admin_click_to_filter') }}</span>
                </header>

                <div v-if="summaryLoading && !warehouses.length" class="skeleton-list">
                    <el-skeleton :rows="3" animated />
                </div>
                <div v-else-if="warehouses.length" class="warehouse-list">
                    <button
                        v-for="w in warehouses"
                        :key="w.id"
                        type="button"
                        class="warehouse-row"
                        :class="{ 'is-selected': filters.warehouse_id === w.id, 'is-inactive': !w.is_active }"
                        :aria-pressed="filters.warehouse_id === w.id"
                        @click="toggleWarehouse(w.id)"
                    >
                        <span class="warehouse-top">
                            <span class="warehouse-name">
                                <strong>{{ w.name }}</strong>
                                <span v-if="w.code" class="wh-code" dir="ltr">{{ w.code }}</span>
                                <el-tag v-if="!w.is_active" type="info" size="small" effect="plain">{{ $t('inactive') }}</el-tag>
                            </span>
                            <span class="wh-qty">
                                {{ $t('inv_admin_available_of', { available: formatNumber(w.total_available), total: formatNumber(w.total_quantity) }) }}
                            </span>
                        </span>
                        <span class="wh-bar" aria-hidden="true">
                            <span class="wh-bar-fill" :style="{ width: warehouseShare(w) + '%' }" />
                        </span>
                        <span class="wh-share">{{ $t('inv_admin_share', { value: warehouseShare(w, true) }) }}</span>
                    </button>
                </div>
                <el-empty v-else :description="$t('no_warehouses')" :image-size="70" />
            </section>

            <!-- Today and what needs reordering -->
            <section class="panel-card">
                <header class="panel-head">
                    <h2><el-icon><Calendar /></el-icon> {{ $t('today_s_activity') }}</h2>
                    <router-link :to="{ name: 'admin.inventory.movements' }" class="panel-link">{{ $t('movement_log') }}</router-link>
                </header>

                <div class="today-stats">
                    <div class="today-item">
                        <span class="today-icon neutral"><el-icon><Sort /></el-icon></span>
                        <div>
                            <strong>{{ formatNumber(today?.movements) }}</strong>
                            <span>{{ $t('total_movements') }}</span>
                        </div>
                    </div>
                    <div class="today-item">
                        <span class="today-icon in"><el-icon><Bottom /></el-icon></span>
                        <div>
                            <strong>{{ formatNumber(today?.received) }}</strong>
                            <span>{{ $t('inbound_receipt') }}</span>
                        </div>
                    </div>
                    <div class="today-item">
                        <span class="today-icon out"><el-icon><Top /></el-icon></span>
                        <div>
                            <strong>{{ formatNumber(today?.issued) }}</strong>
                            <span>{{ $t('outbound_issue') }}</span>
                        </div>
                    </div>
                </div>

                <div class="reorder-head">
                    <h3>{{ $t('inv_admin_reorder_title') }}</h3>
                    <el-button v-if="(summary?.low_stock_rows || 0) > attention.length" link type="primary" @click="toggleStatus('low', true)">
                        {{ $t('inv_admin_view_all', { count: formatNumber(summary.low_stock_rows) }) }}
                    </el-button>
                </div>
                <div v-if="attentionLoading && !attention.length" class="skeleton-list">
                    <el-skeleton :rows="2" animated />
                </div>
                <ul v-else-if="attention.length" class="reorder-list">
                    <li v-for="row in attention" :key="row.id" class="reorder-item">
                        <div class="reorder-text">
                            <strong>{{ productName(row) }}</strong>
                            <span>{{ row.warehouse?.name }} · {{ $t('inv_admin_reorder_at', { value: formatNumber(row.reorder_point) }) }}</span>
                        </div>
                        <span class="stock-pill low"><span class="stock-dot" />{{ formatNumber(row.available) }}</span>
                        <el-tooltip :content="$t('inv_admin_receive')" placement="top" :enterable="false">
                            <el-button size="small" circle :icon="Plus" :aria-label="$t('inv_admin_receive')" @click="openMovement('in', row)" />
                        </el-tooltip>
                    </li>
                </ul>
                <div v-else class="reorder-empty">
                    <el-icon><CircleCheck /></el-icon>
                    {{ $t('no_low_stock_items') }}
                </div>
            </section>
        </div>

        <!-- Balances table -->
        <section class="panel-card">
            <header class="panel-head">
                <h2><el-icon><Grid /></el-icon> {{ $t('item_balances_by_warehouse') }}</h2>
            </header>

            <div class="filters">
                <el-input
                    v-model="filters.search"
                    class="filter-search"
                    :placeholder="$t('inv_admin_search_placeholder')"
                    :prefix-icon="Search"
                    clearable
                    @input="onSearchInput"
                />
                <el-select
                    v-model="filters.warehouse_id"
                    class="filter-select"
                    :placeholder="$t('all_warehouses')"
                    clearable
                    @change="applyFilters"
                >
                    <el-option v-for="w in warehouses" :key="w.id" :label="w.name" :value="w.id" />
                </el-select>
                <el-radio-group v-model="filters.status" class="filter-status" @change="applyFilters">
                    <el-radio-button value="">{{ $t('cat_admin_all') }}</el-radio-button>
                    <el-radio-button value="ok">{{ $t('available') }}</el-radio-button>
                    <el-radio-button value="low">{{ $t('low') }}</el-radio-button>
                    <el-radio-button value="out">{{ $t('out_of_stock_short') }}</el-radio-button>
                </el-radio-group>
                <el-button v-if="activeFilterCount" :icon="RefreshLeft" @click="resetFilters">
                    {{ $t('prod_admin_clear_filters', { count: activeFilterCount }) }}
                </el-button>
            </div>

            <el-result v-if="loadError && !rows.length" icon="error" :title="$t('failed_to_load_stock_data')">
                <template #extra>
                    <el-button type="primary" :icon="Refresh" @click="loadStock">{{ $t('cat_admin_retry') }}</el-button>
                </template>
            </el-result>

            <div v-else class="table-wrapper">
                <el-table
                    v-loading="stockLoading"
                    :data="rows"
                    row-key="id"
                    style="width: 100%"
                    :row-class-name="rowClassName"
                    :default-sort="defaultSort"
                    @sort-change="onSortChange"
                >
                    <template #empty>
                        <el-empty v-if="!stockLoading && activeFilterCount" :description="$t('inv_admin_no_matches')" :image-size="90">
                            <el-button @click="resetFilters">{{ $t('cat_admin_clear_filters') }}</el-button>
                        </el-empty>
                        <el-empty v-else-if="!stockLoading" :description="$t('no_stock_data')" :image-size="90">
                            <el-button type="primary" :icon="Plus" @click="openMovement('in')">{{ $t('inv_admin_record_movement') }}</el-button>
                        </el-empty>
                        <span v-else />
                    </template>

                    <el-table-column :label="$t('product')" min-width="230">
                        <template #default="{ row }">
                            <div class="product-cell">
                                <EntityImage :src="row.product?.image_main || ''" type="product" :size="44" />
                                <div class="cell-stack">
                                    <router-link
                                        v-if="row.product?.id"
                                        :to="{ name: 'admin.products.show', params: { id: row.product.id } }"
                                        class="product-name"
                                    >
                                        {{ productName(row) }}
                                    </router-link>
                                    <span v-else class="product-name">{{ productName(row) }}</span>
                                    <span class="cell-meta">
                                        <el-tooltip v-if="row.product?.sku" :content="$t('prod_admin_copy_sku')" placement="top" :enterable="false">
                                            <button type="button" class="sku-chip" dir="ltr" @click="copySku(row.product.sku)">
                                                {{ row.product.sku }}
                                                <el-icon :size="11"><DocumentCopy /></el-icon>
                                            </button>
                                        </el-tooltip>
                                    </span>
                                </div>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('warehouse')" min-width="130">
                        <template #default="{ row }">
                            <button
                                v-if="row.warehouse"
                                type="button"
                                class="chip-button"
                                :title="$t('inv_admin_filter_by_warehouse')"
                                @click="toggleWarehouse(row.warehouse.id)"
                            >
                                {{ row.warehouse.name }}
                            </button>
                            <span v-else class="cell-empty">—</span>
                            <span v-if="row.bin?.code" class="cell-secondary" dir="ltr">{{ row.bin.code }}</span>
                        </template>
                    </el-table-column>

                    <!-- On hand, reserved and available side by side: without them a
                         balance that dropped because another order reserved it looks
                         like stock that went missing. -->
                    <el-table-column :label="$t('inv_admin_on_hand')" prop="quantity" sortable="custom" width="105" align="center">
                        <template #default="{ row }">
                            <span class="num-muted">{{ formatNumber(row.quantity) }}</span>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('reserved')" prop="reserved_quantity" sortable="custom" width="100" align="center">
                        <template #default="{ row }">
                            <span v-if="Number(row.reserved_quantity) > 0" class="num-reserved">{{ formatNumber(row.reserved_quantity) }}</span>
                            <span v-else class="cell-empty">—</span>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('available_amount')" prop="available" sortable="custom" width="130" align="center">
                        <template #default="{ row }">
                            <el-tooltip :content="stockHint(row)" placement="top" :enterable="false">
                                <span class="stock-pill" :class="stockLevel(row)">
                                    <span class="stock-dot" />
                                    {{ formatNumber(row.available) }}
                                </span>
                            </el-tooltip>
                            <span v-if="unsellable(row) > 0" class="cell-secondary cell-warn">
                                {{ $t('inv_admin_unsellable', { count: formatNumber(unsellable(row)) }) }}
                            </span>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('cost')" width="110" align="center">
                        <template #default="{ row }">
                            <span v-if="Number(row.product?.cost_price) > 0">{{ formatMoney(row.product.cost_price) }}</span>
                            <el-tooltip v-else :content="$t('inv_admin_no_cost_hint')" placement="top" :enterable="false">
                                <span class="cell-empty cell-warn-soft">{{ $t('inv_admin_no_cost') }}</span>
                            </el-tooltip>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('value')" width="120" align="center">
                        <template #default="{ row }">
                            <strong v-if="rowValue(row) > 0">{{ formatMoney(rowValue(row)) }}</strong>
                            <span v-else class="cell-empty">—</span>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('latest_update')" prop="updated_at" sortable="custom" width="130" align="center">
                        <template #default="{ row }">
                            <el-tooltip :content="formatDate(row.updated_at)" placement="top" :enterable="false">
                                <span class="cell-secondary">{{ relativeDate(row.updated_at) }}</span>
                            </el-tooltip>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('procedures')" width="170" align="center">
                        <template #default="{ row }">
                            <div class="row-actions">
                                <el-tooltip :content="$t('inv_admin_receive')" placement="top" :enterable="false">
                                    <el-button size="small" circle :icon="Plus" :aria-label="$t('inv_admin_receive')" @click="openMovement('in', row)" />
                                </el-tooltip>
                                <el-tooltip :content="$t('inv_admin_count')" placement="top" :enterable="false">
                                    <el-button size="small" circle :icon="EditPen" :aria-label="$t('inv_admin_count')" @click="openMovement('count', row)" />
                                </el-tooltip>
                                <el-tooltip :content="$t('transfer_stock')" placement="top" :enterable="false">
                                    <el-button size="small" circle :icon="Switch" :aria-label="$t('transfer_stock')" @click="openMovement('transfer', row)" />
                                </el-tooltip>
                                <el-dropdown trigger="click" @command="(cmd) => onRowCommand(cmd, row)">
                                    <el-button size="small" circle :icon="MoreFilled" :aria-label="$t('inv_admin_more')" />
                                    <template #dropdown>
                                        <el-dropdown-menu>
                                            <el-dropdown-item command="out" :icon="Top">{{ $t('inv_admin_issue') }}</el-dropdown-item>
                                            <el-dropdown-item command="history" :icon="Tickets">{{ $t('inv_admin_history') }}</el-dropdown-item>
                                            <el-dropdown-item v-if="row.product?.id" command="edit" :icon="Edit" divided>{{ $t('inv_admin_edit_product') }}</el-dropdown-item>
                                        </el-dropdown-menu>
                                    </template>
                                </el-dropdown>
                            </div>
                        </template>
                    </el-table-column>
                </el-table>
            </div>

            <div v-if="total > 0" class="pagination-row">
                <span class="pagination-summary">
                    {{ $t('prod_admin_range', { from: rangeFrom, to: rangeTo, total: formatNumber(total) }) }}
                </span>
                <el-pagination
                    v-model:current-page="currentPage"
                    v-model:page-size="pageSize"
                    :total="total"
                    :page-sizes="[10, 20, 50, 100]"
                    :layout="isNarrow ? 'prev, pager, next' : 'sizes, prev, pager, next, jumper'"
                    :pager-count="isNarrow ? 5 : 7"
                    background
                    @size-change="onSizeChange"
                    @current-change="onPageChange"
                />
            </div>
        </section>

        <!-- One drawer for every kind of movement: the product, the warehouse and
             the balance before and after stay in view whichever kind it is. -->
        <el-drawer
            v-model="drawerVisible"
            :size="isNarrow ? '100%' : '500px'"
            direction="rtl"
            class="movement-drawer"
            :close-on-click-modal="!submitting"
            @closed="onDrawerClosed"
        >
            <template #header>
                <div class="drawer-title">
                    <span class="drawer-icon" :class="mv.mode"><el-icon><component :is="modeMeta[mv.mode].icon" /></el-icon></span>
                    <div>
                        <h3>{{ modeMeta[mv.mode].title }}</h3>
                        <p>{{ modeMeta[mv.mode].hint }}</p>
                    </div>
                </div>
            </template>

            <el-form label-position="top" class="movement-form" @submit.prevent="submitMovement">
                <el-form-item>
                    <el-segmented v-model="mv.mode" :options="modeOptions" block class="mode-switch" @change="onModeChange" />
                </el-form-item>

                <el-form-item :label="$t('product')" required>
                    <el-select
                        v-model="mv.product_id"
                        :placeholder="$t('search_product_placeholder')"
                        filterable
                        remote
                        reserve-keyword
                        :remote-method="searchProducts"
                        :loading="productSearchLoading"
                        style="width: 100%"
                        @change="onProductChange"
                    >
                        <el-option v-for="p in productOptions" :key="p.id" :value="p.id" :label="p.name_ar || p.name_en || p.name || p.sku">
                            <div class="product-option">
                                <span class="product-option-name">{{ p.name_ar || p.name_en || p.name }}</span>
                                <span class="product-option-sku" dir="ltr">{{ p.sku }}</span>
                            </div>
                        </el-option>
                    </el-select>
                </el-form-item>

                <template v-if="mv.mode === 'transfer'">
                    <div class="transfer-grid">
                        <el-form-item :label="$t('from_warehouse')" required>
                            <el-select v-model="mv.from_warehouse_id" :placeholder="$t('select_warehouse')" style="width: 100%" @change="onFromChange">
                                <el-option v-for="w in warehouses" :key="w.id" :label="w.name" :value="w.id">
                                    <span class="wh-option">
                                        <span>{{ w.name }}</span>
                                        <span v-if="mv.product_id" class="wh-option-qty" :class="{ 'is-zero': balanceIn(w.id).available <= 0 }">
                                            {{ formatNumber(balanceIn(w.id).available) }}
                                        </span>
                                    </span>
                                </el-option>
                            </el-select>
                        </el-form-item>
                        <el-button
                            class="swap-button"
                            circle
                            :icon="Sort"
                            :aria-label="$t('inv_admin_swap')"
                            :disabled="!mv.from_warehouse_id && !mv.to_warehouse_id"
                            @click="swapWarehouses"
                        />
                        <el-form-item :label="$t('to_warehouse')" required>
                            <el-select v-model="mv.to_warehouse_id" :placeholder="$t('select_warehouse')" style="width: 100%">
                                <el-option
                                    v-for="w in warehouses"
                                    :key="w.id"
                                    :label="w.name"
                                    :value="w.id"
                                    :disabled="w.id === mv.from_warehouse_id"
                                >
                                    <span class="wh-option">
                                        <span>{{ w.name }}</span>
                                        <span v-if="mv.product_id" class="wh-option-qty">{{ formatNumber(balanceIn(w.id).available) }}</span>
                                    </span>
                                </el-option>
                            </el-select>
                        </el-form-item>
                    </div>
                </template>
                <el-form-item v-else :label="$t('warehouse')" required>
                    <el-select v-model="mv.warehouse_id" :placeholder="$t('select_warehouse')" style="width: 100%" @change="onWarehouseChange">
                        <el-option v-for="w in warehouses" :key="w.id" :label="w.name" :value="w.id">
                            <span class="wh-option">
                                <span>{{ w.name }}</span>
                                <span v-if="mv.product_id" class="wh-option-qty" :class="{ 'is-zero': balanceIn(w.id).available <= 0 }">
                                    {{ formatNumber(balanceIn(w.id).available) }}
                                </span>
                            </span>
                        </el-option>
                    </el-select>
                </el-form-item>

                <!-- The balance this movement starts from, and where it leaves it -->
                <div v-if="mv.product_id && sourceWarehouseId" class="balance-card" :class="{ 'is-loading': balanceLoading }">
                    <div class="balance-col">
                        <span class="balance-label">{{ $t('inv_admin_on_hand') }}</span>
                        <strong>{{ formatNumber(sourceBalance.quantity) }}</strong>
                    </div>
                    <div class="balance-col">
                        <span class="balance-label">{{ $t('reserved') }}</span>
                        <strong>{{ formatNumber(sourceBalance.reserved) }}</strong>
                    </div>
                    <div class="balance-col">
                        <span class="balance-label">{{ $t('available_amount') }}</span>
                        <strong>{{ formatNumber(sourceBalance.available) }}</strong>
                    </div>
                    <div class="balance-col balance-after" :class="afterTone">
                        <span class="balance-label">{{ $t('inv_admin_after') }}</span>
                        <strong>{{ formatNumber(onHandAfter) }}</strong>
                    </div>
                </div>

                <el-form-item v-if="mv.mode === 'count'" :label="$t('inv_admin_counted_quantity')" required>
                    <el-input-number v-model="mv.counted" :min="0" :step="1" step-strictly controls-position="right" style="width: 100%" />
                    <span class="field-hint">
                        <template v-if="countDelta === 0">{{ $t('inv_admin_count_matches') }}</template>
                        <template v-else>
                            {{ $t('inv_admin_count_delta') }}
                            <strong :class="countDelta > 0 ? 'delta-up' : 'delta-down'" dir="ltr">{{ countDelta > 0 ? '+' : '' }}{{ formatNumber(countDelta) }}</strong>
                        </template>
                    </span>
                </el-form-item>
                <el-form-item v-else :label="$t('quantity')" required>
                    <el-input-number v-model="mv.quantity" :min="1" :step="1" step-strictly controls-position="right" style="width: 100%" />
                    <span v-if="(mv.mode === 'out' || mv.mode === 'transfer') && sourceWarehouseId && mv.product_id" class="field-hint">
                        {{ $t('inv_admin_max_available', { count: formatNumber(sourceBalance.available) }) }}
                        <el-button
                            v-if="sourceBalance.available > 0 && mv.quantity !== sourceBalance.available"
                            link
                            type="primary"
                            size="small"
                            @click="mv.quantity = sourceBalance.available"
                        >{{ $t('inv_admin_use_all') }}</el-button>
                    </span>
                </el-form-item>

                <el-alert v-if="formWarning" :title="formWarning" type="warning" :closable="false" show-icon class="form-alert" />

                <div class="two-cols">
                    <el-form-item :label="$t('reference_code')">
                        <el-input v-model="mv.reference" :placeholder="$t('reference_example')" maxlength="255" />
                    </el-form-item>
                </div>

                <el-form-item :label="$t('notes')">
                    <el-input
                        v-model="mv.notes"
                        type="textarea"
                        :rows="3"
                        maxlength="1000"
                        :placeholder="mv.mode === 'count' ? $t('inv_admin_count_notes') : $t('movement_reason_placeholder')"
                    />
                </el-form-item>
            </el-form>

            <template #footer>
                <div class="drawer-footer">
                    <el-button :disabled="submitting" @click="drawerVisible = false">{{ $t('cancel') }}</el-button>
                    <el-button type="primary" :loading="submitting" :disabled="!!blockReason" @click="submitMovement">
                        {{ modeMeta[mv.mode].submit }}
                    </el-button>
                </div>
            </template>
        </el-drawer>
    </div>
</template>

<script setup>
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';
import EntityImage from '@/components/admin/EntityImage.vue';
import { formatMoney as formatBaseMoney, formatNumber as formatCount, numberLocale } from '@/utils/currency';
import { useI18n } from 'vue-i18n';
import { ref, reactive, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useInventoryStore } from '@/stores/inventory';
import { inventoryApi } from '@/api/inventory';
import { productsApi } from '@/api/products';
import {
    Plus, Search, Refresh, RefreshLeft, Edit, EditPen, Switch, Tickets, Download, Upload, Files, ArrowDown,
    DocumentCopy, Coin, Box, CircleCheck, Warning, RemoveFilled, OfficeBuilding, Calendar, Sort, Bottom, Top,
    Grid, MoreFilled,
} from '@element-plus/icons-vue';

const { t } = useI18n();
const router = useRouter();
const route = useRoute();
const store = useInventoryStore();

const formatNumber = (n) => formatCount(n);
const formatMoney = (n) => formatBaseMoney(n);
const formatDate = (d) => (d ? String(d).replace('T', ' ').substring(0, 16) : '-');

const relativeDate = (d) => {
    if (!d) return '—';
    const then = new Date(d);
    if (Number.isNaN(then.getTime())) return formatDate(d);
    const minutes = Math.round((then.getTime() - Date.now()) / 60000);
    const rtf = new Intl.RelativeTimeFormat(numberLocale(), { numeric: 'auto' });
    if (Math.abs(minutes) < 60) return rtf.format(minutes, 'minute');
    const hours = Math.round(minutes / 60);
    if (Math.abs(hours) < 24) return rtf.format(hours, 'hour');
    const days = Math.round(hours / 24);
    if (Math.abs(days) < 30) return rtf.format(days, 'day');
    return formatDate(d).substring(0, 10);
};

// ── Summary ──────────────────────────────────────────────────────────────
const summary = computed(() => store.summary);
const today = computed(() => store.today);
const warehouses = computed(() => store.warehouses);
const summaryLoading = ref(false);

/** Units on the shelf that are reserved, damaged or held — on hand, not for sale. */
const heldUnits = computed(() => Math.max(0, (summary.value?.total_quantity || 0) - (summary.value?.total_available || 0)));

const statusCards = computed(() => [
    {
        key: 'ok',
        title: t('inv_admin_healthy_rows'),
        value: Math.max(0, (summary.value?.in_stock_rows || 0) - (summary.value?.low_stock_rows || 0)),
        icon: CircleCheck,
    },
    { key: 'low', title: t('items_at_reorder_point'), value: summary.value?.low_stock_rows || 0, icon: Warning },
    { key: 'out', title: t('inv_admin_out_rows'), value: summary.value?.out_of_stock_rows || 0, icon: RemoveFilled },
]);

const warehouseShare = (w, exact = false) => {
    const all = warehouses.value.reduce((sum, x) => sum + Math.max(0, Number(x.total_available) || 0), 0);
    if (!all) return 0;
    const share = (Math.max(0, Number(w.total_available) || 0) / all) * 100;
    return exact ? Math.round(share) : Math.max(share > 0 ? 2 : 0, share);
};

const loadSummary = async () => {
    summaryLoading.value = true;
    try {
        await store.fetchSummary();
    } finally {
        summaryLoading.value = false;
    }
};

// Rows at their reorder point across the whole stock, lowest first — not the
// first few of whatever page the table happens to be showing.
const attention = ref([]);
const attentionLoading = ref(false);
const loadAttention = async () => {
    attentionLoading.value = true;
    try {
        const res = await inventoryApi.getStock({ status: 'low', sort_by: 'available', sort_dir: 'asc', per_page: 5 });
        attention.value = res.data?.data?.stock || [];
    } catch {
        attention.value = [];
    } finally {
        attentionLoading.value = false;
    }
};

// ── Filters, sort and paging (kept in the URL) ───────────────────────────
const filters = reactive({ search: '', warehouse_id: null, status: '' });
const sort = reactive({ by: 'updated_at', order: 'desc' });
const currentPage = ref(1);
const pageSize = ref(20);
const SORTABLE = ['quantity', 'reserved_quantity', 'available', 'updated_at'];

const readQuery = () => {
    const q = route.query;
    filters.search = q.search ? String(q.search) : '';
    filters.warehouse_id = q.warehouse_id ? Number(q.warehouse_id) || null : null;
    filters.status = ['ok', 'low', 'out'].includes(q.status) ? q.status : '';
    sort.by = SORTABLE.includes(q.sort) ? q.sort : 'updated_at';
    sort.order = q.order === 'asc' ? 'asc' : 'desc';
    currentPage.value = Math.max(1, Number(q.page) || 1);
    pageSize.value = [10, 20, 50, 100].includes(Number(q.per_page)) ? Number(q.per_page) : 20;
};

const queryKey = (query) => Object.entries(query).map(([k, v]) => `${k}=${v}`).sort().join('&');
let lastQueryKey = null;

const writeQuery = () => {
    const isDefaultSort = sort.by === 'updated_at' && sort.order === 'desc';
    const query = {
        search: filters.search || undefined,
        warehouse_id: filters.warehouse_id || undefined,
        status: filters.status || undefined,
        sort: isDefaultSort ? undefined : sort.by,
        order: isDefaultSort ? undefined : sort.order,
        page: currentPage.value > 1 ? currentPage.value : undefined,
        per_page: pageSize.value !== 20 ? pageSize.value : undefined,
    };
    Object.keys(query).forEach((k) => query[k] === undefined && delete query[k]);
    lastQueryKey = queryKey(query);
    router.replace({ query });
};

const activeFilterCount = computed(() => [filters.search, filters.warehouse_id, filters.status].filter(Boolean).length);

const defaultSort = computed(() => ({ prop: sort.by, order: sort.order === 'asc' ? 'ascending' : 'descending' }));

const filterParams = () => ({
    warehouse_id: filters.warehouse_id || undefined,
    status: filters.status || undefined,
    search: filters.search.trim() || undefined,
});

// ── Stock rows ───────────────────────────────────────────────────────────
const rows = ref([]);
const total = ref(0);
const stockLoading = ref(false);
const loadError = ref(false);
let stockRequest = 0;

const loadStock = async () => {
    const seq = ++stockRequest;
    stockLoading.value = true;
    loadError.value = false;
    try {
        const res = await inventoryApi.getStock({
            ...filterParams(),
            page: currentPage.value,
            per_page: pageSize.value,
            sort_by: sort.by,
            sort_dir: sort.order,
        });
        // A slower, older response must not overwrite the rows of a newer one.
        if (seq !== stockRequest) return;
        const data = res.data?.data || {};
        rows.value = data.stock || [];
        total.value = data.pagination?.total || 0;
        // Past the last page after a filter shrank the list: go to the last one.
        const last = data.pagination?.last_page || 1;
        if (!rows.value.length && currentPage.value > last) {
            currentPage.value = last;
            writeQuery();
            loadStock();
        }
    } catch {
        if (seq !== stockRequest) return;
        loadError.value = true;
        ElMessage.error(t('failed_to_load_stock_data'));
    } finally {
        if (seq === stockRequest) stockLoading.value = false;
    }
};

const rangeFrom = computed(() => (total.value ? (currentPage.value - 1) * pageSize.value + 1 : 0));
const rangeTo = computed(() => Math.min(currentPage.value * pageSize.value, total.value));

let searchTimeout = null;
const applyFilters = () => {
    clearTimeout(searchTimeout);
    currentPage.value = 1;
    writeQuery();
    loadStock();
};
const onSearchInput = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 350);
};
const resetFilters = () => {
    filters.search = '';
    filters.warehouse_id = null;
    filters.status = '';
    applyFilters();
};
const toggleStatus = (status, force = false) => {
    filters.status = !force && filters.status === status ? '' : status;
    applyFilters();
};
const toggleWarehouse = (id) => {
    filters.warehouse_id = filters.warehouse_id === id ? null : id;
    applyFilters();
};
const onSortChange = ({ prop, order }) => {
    sort.by = order && SORTABLE.includes(prop) ? prop : 'updated_at';
    sort.order = order === 'ascending' ? 'asc' : 'desc';
    currentPage.value = 1;
    writeQuery();
    loadStock();
};
const onPageChange = () => {
    writeQuery();
    loadStock();
};
const onSizeChange = () => {
    currentPage.value = 1;
    writeQuery();
    loadStock();
};

// ── Row helpers ──────────────────────────────────────────────────────────
const productName = (row) => row.product?.name_ar || row.product?.name_en || row.product?.name || row.product?.sku || '—';

/** Units on the shelf that cannot be sold at all — not merely spoken for. */
const unsellable = (row) => (Number(row.damaged_quantity) || 0) + (Number(row.quarantined_quantity) || 0);

const stockLevel = (row) => {
    const available = Number(row.available ?? 0);
    if (available <= 0) return 'out';
    if (available <= Number(row.reorder_point ?? 0)) return 'low';
    return 'ok';
};

const stockHint = (row) => {
    const level = stockLevel(row);
    const reorder = formatNumber(row.reorder_point ?? 0);
    if (level === 'out') return t('inv_admin_hint_out');
    if (level === 'low') return t('inv_admin_hint_low', { value: reorder });
    return Number(row.reorder_point) > 0 ? t('inv_admin_hint_ok', { value: reorder }) : t('inv_admin_hint_no_reorder');
};

const rowValue = (row) => Math.max(0, Number(row.available) || 0) * (Number(row.product?.cost_price) || 0);

const rowClassName = ({ row }) => `row-${stockLevel(row)}`;

const copySku = async (sku) => {
    try {
        await navigator.clipboard.writeText(sku);
        ElMessage.success(t('prod_admin_sku_copied'));
    } catch {
        ElMessage.info(sku);
    }
};

const onRowCommand = (command, row) => {
    if (command === 'out') openMovement('out', row);
    if (command === 'history') {
        router.push({
            name: 'admin.inventory.movements',
            query: { product_id: row.product_id, warehouse_id: row.warehouse_id },
        });
    }
    if (command === 'edit' && row.product?.id) {
        router.push({ name: 'admin.products.edit', params: { id: row.product.id } });
    }
};

// ── Movement drawer ──────────────────────────────────────────────────────
const drawerVisible = ref(false);
const submitting = ref(false);

const mv = reactive({
    mode: 'in',
    product_id: null,
    warehouse_id: null,
    from_warehouse_id: null,
    to_warehouse_id: null,
    quantity: 1,
    counted: 0,
    reference: '',
    notes: '',
    key: '',
});

const modeMeta = computed(() => ({
    in: { title: t('inv_admin_mode_in_title'), hint: t('inv_admin_mode_in_hint'), submit: t('inv_admin_mode_in_submit'), icon: Bottom },
    out: { title: t('inv_admin_mode_out_title'), hint: t('inv_admin_mode_out_hint'), submit: t('inv_admin_mode_out_submit'), icon: Top },
    count: { title: t('inv_admin_mode_count_title'), hint: t('inv_admin_mode_count_hint'), submit: t('inv_admin_mode_count_submit'), icon: EditPen },
    transfer: { title: t('transfer_between_warehouses'), hint: t('inv_admin_mode_transfer_hint'), submit: t('confirm_transfer'), icon: Switch },
}));

const modeOptions = computed(() => [
    { label: t('inv_admin_receive'), value: 'in' },
    { label: t('inv_admin_issue'), value: 'out' },
    { label: t('inv_admin_count'), value: 'count' },
    { label: t('inv_admin_transfer_short'), value: 'transfer' },
]);

// A fresh key per submission, so a double click or a retried request cannot
// move the stock twice. It used to be `manual:<reference>`, and the key is
// unique across all movements — a stock count entered for twenty products
// under one reference recorded the first and silently skipped the rest.
const newKey = () => `manual:${globalThis.crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(36).slice(2)}`}`;

// Product search
const productOptions = ref([]);
const productSearchLoading = ref(false);
let productSearchTimer = null;
const searchProducts = (query) => {
    clearTimeout(productSearchTimer);
    productSearchLoading.value = true;
    productSearchTimer = setTimeout(async () => {
        try {
            const res = await productsApi.getAll({ search: query || undefined, per_page: 30 });
            const found = res.data?.data || [];
            // Keep the chosen product in the list so its label does not turn into an id.
            const chosen = productOptions.value.find((p) => p.id === mv.product_id);
            productOptions.value = chosen && !found.some((p) => p.id === chosen.id) ? [chosen, ...found] : found;
        } catch {
            // Keep whatever was showing rather than blanking the list.
        } finally {
            productSearchLoading.value = false;
        }
    }, 300);
};

// Balances of the chosen product, per warehouse
const balances = ref({});
const balanceLoading = ref(false);
let balanceRequest = 0;

const loadBalances = async () => {
    const seq = ++balanceRequest;
    balances.value = {};
    if (!mv.product_id) return;
    balanceLoading.value = true;
    try {
        const res = await inventoryApi.getStock({ product_id: mv.product_id, per_page: 200 });
        if (seq !== balanceRequest) return;
        const map = {};
        (res.data?.data?.stock || [])
            // Manual movements land on the product itself, not on a variant.
            .filter((r) => !r.product_variant_id)
            .forEach((r) => {
                const b = map[r.warehouse_id] || (map[r.warehouse_id] = { quantity: 0, reserved: 0, available: 0 });
                b.quantity += Number(r.quantity) || 0;
                b.reserved += Number(r.reserved_quantity) || 0;
                b.available += Math.max(0, Number(r.available) || 0);
            });
        balances.value = map;
    } catch {
        if (seq === balanceRequest) balances.value = {};
    } finally {
        if (seq === balanceRequest) {
            balanceLoading.value = false;
            if (mv.mode === 'count') mv.counted = Math.max(0, sourceBalance.value.quantity);
        }
    }
};

const EMPTY_BALANCE = { quantity: 0, reserved: 0, available: 0 };
const balanceIn = (warehouseId) => balances.value[warehouseId] || EMPTY_BALANCE;

const sourceWarehouseId = computed(() => (mv.mode === 'transfer' ? mv.from_warehouse_id : mv.warehouse_id));
const sourceBalance = computed(() => balanceIn(sourceWarehouseId.value));

const countDelta = computed(() => (Number(mv.counted) || 0) - sourceBalance.value.quantity);

const onHandAfter = computed(() => {
    const q = Number(mv.quantity) || 0;
    if (mv.mode === 'in') return sourceBalance.value.quantity + q;
    if (mv.mode === 'count') return Number(mv.counted) || 0;
    return sourceBalance.value.quantity - q;
});

const afterTone = computed(() => {
    if (mv.mode === 'count') return countDelta.value > 0 ? 'up' : countDelta.value < 0 ? 'down' : '';
    return mv.mode === 'in' ? 'up' : 'down';
});

// Why the submit button is off; the first reason wins.
const blockReason = computed(() => {
    if (!mv.product_id) return 'product';
    if (mv.mode === 'transfer') {
        if (!mv.from_warehouse_id || !mv.to_warehouse_id) return 'warehouse';
        if (mv.from_warehouse_id === mv.to_warehouse_id) return 'same';
    } else if (!mv.warehouse_id) {
        return 'warehouse';
    }
    if (balanceLoading.value) return 'loading';
    if (mv.mode === 'count') return countDelta.value === 0 ? 'nochange' : '';
    if (!(Number(mv.quantity) >= 1)) return 'quantity';
    if ((mv.mode === 'out' || mv.mode === 'transfer') && mv.quantity > sourceBalance.value.available) return 'short';
    return '';
});

const formWarning = computed(() => {
    if (blockReason.value === 'short') {
        return t('inv_admin_short_warning', { count: formatNumber(sourceBalance.value.available) });
    }
    if (mv.mode === 'count' && countDelta.value < 0 && Number(mv.counted) < sourceBalance.value.reserved) {
        return t('inv_admin_count_below_reserved', { count: formatNumber(sourceBalance.value.reserved) });
    }
    return '';
});

const openMovement = (mode, row = null) => {
    Object.assign(mv, {
        mode,
        product_id: row?.product_id || null,
        warehouse_id: row?.warehouse_id || filters.warehouse_id || (warehouses.value.length === 1 ? warehouses.value[0].id : null),
        from_warehouse_id: row?.warehouse_id || filters.warehouse_id || null,
        to_warehouse_id: null,
        quantity: 1,
        counted: 0,
        reference: '',
        notes: '',
        key: newKey(),
    });
    productOptions.value = row?.product ? [row.product] : [];
    balances.value = {};
    drawerVisible.value = true;
    if (mv.product_id) loadBalances();
    else searchProducts('');
};

const onModeChange = () => {
    if (mv.mode === 'transfer' && !mv.from_warehouse_id) mv.from_warehouse_id = mv.warehouse_id;
    if (mv.mode !== 'transfer' && !mv.warehouse_id) mv.warehouse_id = mv.from_warehouse_id;
    if (mv.mode === 'count') mv.counted = Math.max(0, sourceBalance.value.quantity);
};
const onProductChange = () => loadBalances();
const onWarehouseChange = () => {
    if (mv.mode === 'count') mv.counted = Math.max(0, sourceBalance.value.quantity);
};
const onFromChange = () => {
    if (mv.to_warehouse_id === mv.from_warehouse_id) mv.to_warehouse_id = null;
};
const swapWarehouses = () => {
    [mv.from_warehouse_id, mv.to_warehouse_id] = [mv.to_warehouse_id, mv.from_warehouse_id];
};
const onDrawerClosed = () => {
    balances.value = {};
};

const submitMovement = async () => {
    if (blockReason.value || submitting.value) return;
    submitting.value = true;
    try {
        if (mv.mode === 'transfer') {
            await store.transferStock({
                product_id: mv.product_id,
                from_warehouse_id: mv.from_warehouse_id,
                to_warehouse_id: mv.to_warehouse_id,
                quantity: mv.quantity,
                reference: mv.reference || null,
                notes: mv.notes || null,
            });
            ElMessage.success(t('stock_transferred'));
        } else {
            await store.createMovement({
                product_id: mv.product_id,
                warehouse_id: mv.warehouse_id,
                movement_type: mv.mode === 'count' ? 'adjustment' : mv.mode,
                // A count is recorded as the difference from the books, signed.
                quantity: mv.mode === 'count' ? countDelta.value : mv.quantity,
                reference: mv.reference || null,
                notes: mv.notes || null,
                movement_key: mv.key,
            });
            ElMessage.success(t('stock_movement_recorded'));
        }
        drawerVisible.value = false;
        await Promise.all([loadSummary().catch(() => {}), loadStock(), loadAttention()]);
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t(mv.mode === 'transfer' ? 'failed_to_transfer_stock' : 'failed_to_save_stock_movement'));
    } finally {
        submitting.value = false;
    }
};

// ── Refresh, export and import ───────────────────────────────────────────
const refreshing = ref(false);
const refreshAll = async () => {
    refreshing.value = true;
    try {
        await Promise.all([loadSummary(), loadStock(), loadAttention()]);
    } catch {
        ElMessage.error(t('failed_to_update_data'));
    } finally {
        refreshing.value = false;
    }
};

const exporting = ref(false);
const importing = ref(false);
const fileInput = ref(null);

const onToolsCommand = (command) => {
    if (command === 'export') exportStock();
    if (command === 'import') fileInput.value?.click();
};

const exportStock = async () => {
    exporting.value = true;
    try {
        // The same rows the table shows — the متاح filter included, which used
        // to be dropped here and exported every row instead.
        const res = await inventoryApi.exportStock(filterParams());
        const blob = new Blob([res.data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `warehouse-stock-${new Date().toISOString().slice(0, 10)}.xlsx`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
        ElMessage.success(t('balances_exported'));
    } catch (e) {
        ElMessage.error(t('failed_to_export_balances'));
    } finally {
        exporting.value = false;
    }
};

const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

const onFileSelected = async (event) => {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;

    if (!/\.xlsx$/i.test(file.name)) {
        ElMessage.error(t('please_choose_xlsx_file'));
        return;
    }

    importing.value = true;
    try {
        const formData = new FormData();
        formData.append('file', file);
        const res = await inventoryApi.importStock(formData);
        const data = res.data?.data || {};
        const errors = data.errors || [];
        const ignored = data.ignored_columns || [];

        // New and updated balances are counted apart, so it is obvious when a
        // sheet added a product to another warehouse rather than editing its
        // existing balance.
        const stats = [
            [t('inv_admin_import_products_created'), data.products_created],
            [t('inv_admin_import_products_matched'), data.products_matched],
            [t('inv_admin_import_prices_updated'), data.prices_updated],
            [t('inv_admin_import_balances_created'), data.inventory_created],
            [t('inv_admin_import_balances_updated'), data.inventory_updated],
        ];

        let html = `<ul class="import-summary">${stats
            .map(([label, n]) => `<li><span>${escapeHtml(label)}</span><strong>${formatNumber(n ?? 0)}</strong></li>`)
            .join('')}</ul>`;

        // Computed columns the sheet carried but the importer does not apply.
        // Said out loud, because an operator who edited the reserved column and
        // saw a success message would otherwise assume it took.
        if (ignored.length) {
            html += `<p class="import-note">${escapeHtml(t('computed_columns_not_imported', { columns: ignored.join(t('list_separator')) }))}</p>`;
        }

        // Rows are listed rather than counted, and the numbers match the
        // spreadsheet's own gutter so each one can be found and fixed.
        if (errors.length) {
            html += `<p class="import-errors-title">${escapeHtml(t('rows_not_imported'))} (${formatNumber(errors.length)})</p><ul class="import-errors">${errors
                .slice(0, 20)
                .map((e) => `<li>${escapeHtml(t('inv_admin_import_row', { row: e.row }))}: ${escapeHtml(e.message)}</li>`)
                .join('')}</ul>`;
            if (errors.length > 20) {
                html += `<p class="import-note">${escapeHtml(t('inv_admin_import_more_rows', { count: errors.length - 20 }))}</p>`;
            }
        }

        ElMessageBox.alert(html, errors.length ? t('inv_admin_import_partial') : t('inv_admin_import_done'), {
            dangerouslyUseHTMLString: true,
            type: errors.length ? 'warning' : 'success',
            confirmButtonText: t('ok_button'),
            customClass: 'inventory-import-result',
        }).catch(() => {});

        await refreshAll();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('failed_to_import_balances'));
    } finally {
        importing.value = false;
    }
};

// ── Lifecycle ────────────────────────────────────────────────────────────
const isNarrow = ref(false);
const onResize = () => { isNarrow.value = window.innerWidth < 768; };

// Navigating to this screen while already on it (the sidebar link) reuses the
// component, so a new URL has to be read here.
watch(() => route.query, (query) => {
    if (route.name !== 'admin.inventory.index' || queryKey(query) === lastQueryKey) return;
    readQuery();
    lastQueryKey = queryKey(query);
    loadStock();
});

onMounted(() => {
    onResize();
    window.addEventListener('resize', onResize);
    readQuery();
    lastQueryKey = queryKey(route.query);
    loadSummary().catch(() => ElMessage.error(t('failed_to_update_data')));
    loadStock();
    loadAttention();
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', onResize);
    clearTimeout(searchTimeout);
    clearTimeout(productSearchTimer);
});
</script>

<style scoped>
.inventory-index {
    --surface: #ffffff;
    --line: #e2e8f0;
    --ink: #0f172a;
    --ink-mute: #64748b;
    --primary: #2563eb;
    --primary-soft: #eff6ff;
    --ok: #16a34a;
    --ok-soft: #f0fdf4;
    --warn: #d97706;
    --warn-soft: #fffbeb;
    --danger: #dc2626;
    --danger-soft: #fef2f2;
    --purple: #7c3aed;
    --purple-soft: #f5f3ff;
    --gold: #b45309;
    --gold-soft: #fef3c7;

    color: var(--ink);
}

.visually-hidden { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }

/* ── Stat cards ─────────────────────────────────────────────────────── */
.stat-card {
    border-radius: 14px;
    border: 1px solid var(--line);
    transition: border-color 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;
}
.stat-card.is-clickable { cursor: pointer; }
.stat-card.is-clickable:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(15, 23, 42, 0.06); }
.stat-card.is-clickable:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }
.stat-card.is-selected { border-color: var(--primary); box-shadow: 0 0 0 1px var(--primary) inset; }

.stat-card-inner { display: flex; align-items: center; gap: 0.9rem; }
.stat-icon-box {
    width: 46px;
    height: 46px;
    flex-shrink: 0;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
}
.stat-icon-box.value { background: var(--gold-soft); color: var(--gold); }
.stat-icon-box.units { background: var(--purple-soft); color: var(--purple); }
.stat-icon-box.ok { background: var(--ok-soft); color: var(--ok); }
.stat-icon-box.low { background: var(--warn-soft); color: var(--warn); }
.stat-icon-box.out { background: var(--danger-soft); color: var(--danger); }

.stat-details { min-width: 0; }
.stat-details h3 { margin: 0; font-size: 1.35rem; font-weight: 800; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.stat-details p { margin: 0.2rem 0 0; color: var(--ink-mute); font-size: 0.8rem; font-weight: 600; }
.stat-note { font-weight: 500; color: #94a3b8; }

/* ── Panels ─────────────────────────────────────────────────────────── */
.overview-grid {
    display: grid;
    grid-template-columns: minmax(0, 7fr) minmax(0, 5fr);
    gap: 1rem;
    margin-bottom: 1rem;
}
@media (max-width: 1100px) {
    .overview-grid { grid-template-columns: minmax(0, 1fr); }
}

.panel-card {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 14px;
    padding: 1.1rem 1.25rem;
    box-shadow: 0 1px 2px rgba(18, 28, 44, 0.04);
    min-width: 0;
}

.panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.9rem;
}
.panel-head h2 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1rem;
    font-weight: 800;
}
.panel-head h2 .el-icon { color: var(--primary); }
.panel-hint { font-size: 0.75rem; color: #94a3b8; }
.panel-link { font-size: 0.8rem; font-weight: 600; color: var(--primary); text-decoration: none; }
.panel-link:hover { text-decoration: underline; }

.skeleton-list { padding: 0.25rem 0; }

/* Warehouses */
.warehouse-list { display: flex; flex-direction: column; gap: 0.35rem; }
.warehouse-row {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    width: 100%;
    padding: 0.6rem 0.75rem;
    border: 1px solid transparent;
    border-radius: 10px;
    background: transparent;
    font: inherit;
    color: inherit;
    text-align: start;
    cursor: pointer;
    transition: background 0.15s ease, border-color 0.15s ease;
}
.warehouse-row:hover { background: #f8fafc; }
.warehouse-row:focus-visible { outline: 2px solid var(--primary); outline-offset: 1px; }
.warehouse-row.is-selected { background: var(--primary-soft); border-color: #bfdbfe; }
.warehouse-row.is-inactive { opacity: 0.7; }
.warehouse-top { display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; }
.warehouse-name { display: flex; align-items: center; gap: 0.4rem; min-width: 0; flex-wrap: wrap; }
.wh-code { color: var(--ink-mute); font-size: 0.72rem; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
.wh-qty { font-weight: 700; font-size: 0.85rem; white-space: nowrap; }
.wh-bar { position: relative; display: block; height: 8px; border-radius: 999px; background: #eef2f7; overflow: hidden; }
.wh-bar-fill { position: absolute; inset-block: 0; inset-inline-start: 0; border-radius: inherit; background: linear-gradient(90deg, #3b82f6, #6366f1); transition: width 0.3s ease; }
.warehouse-row.is-selected .wh-bar-fill { background: var(--primary); }
.wh-share { font-size: 0.72rem; color: #94a3b8; }

/* Today */
.today-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.6rem;
    margin-bottom: 1rem;
}
.today-item {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.6rem;
    border-radius: 10px;
    background: #f8fafc;
    min-width: 0;
}
.today-item div { display: flex; flex-direction: column; min-width: 0; }
.today-item strong { font-size: 1.15rem; font-weight: 800; line-height: 1.2; }
.today-item div span { font-size: 0.72rem; color: var(--ink-mute); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.today-icon { width: 34px; height: 34px; border-radius: 9px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.today-icon.neutral { background: var(--primary-soft); color: var(--primary); }
.today-icon.in { background: var(--ok-soft); color: var(--ok); }
.today-icon.out { background: var(--danger-soft); color: var(--danger); }
@media (max-width: 480px) {
    .today-stats { grid-template-columns: minmax(0, 1fr); }
}

.reorder-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; }
.reorder-head h3 { margin: 0; font-size: 0.85rem; font-weight: 700; color: #334155; }
.reorder-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.4rem; }
.reorder-item {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.5rem 0.65rem;
    border: 1px solid #fde68a;
    border-radius: 10px;
    background: var(--warn-soft);
}
.reorder-text { display: flex; flex-direction: column; min-width: 0; flex: 1 1 auto; }
.reorder-text strong { font-size: 0.85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.reorder-text span { font-size: 0.72rem; color: var(--ink-mute); }
.reorder-item .el-button { margin: 0; }
.reorder-empty {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem;
    border-radius: 10px;
    background: var(--ok-soft);
    color: #15803d;
    font-size: 0.85rem;
    font-weight: 600;
}

/* ── Filters ────────────────────────────────────────────────────────── */
.filters { display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem; margin-bottom: 1rem; }
.filter-search { flex: 1 1 260px; max-width: 380px; }
.filter-select { width: 190px; }

/* ── Table ──────────────────────────────────────────────────────────── */
.table-wrapper { border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
.table-wrapper :deep(.row-out td.el-table__cell:first-child) { box-shadow: inset -3px 0 0 var(--danger); }
.table-wrapper :deep(.row-low td.el-table__cell:first-child) { box-shadow: inset -3px 0 0 var(--warn); }
[dir="ltr"] .table-wrapper :deep(.row-out td.el-table__cell:first-child) { box-shadow: inset 3px 0 0 var(--danger); }
[dir="ltr"] .table-wrapper :deep(.row-low td.el-table__cell:first-child) { box-shadow: inset 3px 0 0 var(--warn); }

.product-cell { display: flex; align-items: center; gap: 0.7rem; min-width: 0; }
.cell-stack { display: flex; flex-direction: column; gap: 0.15rem; min-width: 0; }
.product-name {
    font-weight: 700;
    color: var(--ink);
    text-decoration: none;
    line-height: 1.35;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
a.product-name:hover { color: var(--primary); text-decoration: underline; }
.cell-meta { display: flex; flex-wrap: wrap; gap: 0.35rem; }
.cell-secondary { display: block; font-size: 0.74rem; color: var(--ink-mute); margin-top: 0.15rem; }
.cell-warn { color: var(--danger); }
.cell-empty { color: #94a3b8; font-size: 0.8rem; }
.cell-warn-soft { color: var(--warn); cursor: help; }

.sku-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0 0.4rem;
    border: 1px solid var(--line);
    border-radius: 6px;
    background: #f8fafc;
    color: #475569;
    font: 600 0.7rem/1.6 ui-monospace, SFMono-Regular, Menlo, monospace;
    cursor: pointer;
}
.sku-chip:hover { border-color: var(--primary); color: var(--primary); }

.chip-button {
    max-width: 100%;
    padding: 0.1rem 0.55rem;
    border: 1px solid var(--line);
    border-radius: 8px;
    background: #fff;
    color: #334155;
    font: inherit;
    font-size: 0.78rem;
    line-height: 1.5;
    text-align: start;
    cursor: pointer;
}
.chip-button:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-soft); }

.num-muted { color: #475569; font-weight: 600; }
.num-reserved { color: var(--warn); font-weight: 700; }

.stock-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    min-width: 56px;
    justify-content: center;
    padding: 0.15rem 0.6rem;
    border-radius: 999px;
    font-weight: 700;
    font-size: 0.85rem;
    cursor: default;
}
.stock-dot { width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
.stock-pill.ok { color: #15803d; background: var(--ok-soft); }
.stock-pill.low { color: #b45309; background: var(--warn-soft); }
.stock-pill.out { color: #b91c1c; background: var(--danger-soft); }

.row-actions { display: flex; flex-wrap: nowrap; gap: 0.3rem; justify-content: center; }
.row-actions .el-button + .el-button,
.row-actions :deep(.el-dropdown) .el-button { margin-inline-start: 0; }

.pagination-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding-top: 1rem;
}
.pagination-summary { font-size: 0.82rem; color: var(--ink-mute); }

/* ── Movement drawer ────────────────────────────────────────────────── */
.drawer-title { display: flex; align-items: center; gap: 0.75rem; }
.drawer-title h3 { margin: 0; font-size: 1.05rem; font-weight: 800; color: var(--ink); }
.drawer-title p { margin: 0.15rem 0 0; font-size: 0.78rem; color: var(--ink-mute); }
.drawer-icon { width: 40px; height: 40px; border-radius: 11px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; flex-shrink: 0; }
.drawer-icon.in { background: var(--ok-soft); color: var(--ok); }
.drawer-icon.out { background: var(--danger-soft); color: var(--danger); }
.drawer-icon.count { background: var(--purple-soft); color: var(--purple); }
.drawer-icon.transfer { background: var(--primary-soft); color: var(--primary); }

.mode-switch { width: 100%; }

.product-option { display: flex; justify-content: space-between; gap: 0.75rem; }
.product-option-name { overflow: hidden; text-overflow: ellipsis; }
.product-option-sku { color: #94a3b8; font-size: 0.75rem; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }

.wh-option { display: flex; justify-content: space-between; gap: 0.75rem; width: 100%; }
.wh-option-qty { color: var(--ok); font-weight: 700; font-size: 0.8rem; }
.wh-option-qty.is-zero { color: #94a3b8; }

.transfer-grid { display: grid; grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr); gap: 0 0.5rem; align-items: center; }
.swap-button { margin-top: 0.4rem; transform: rotate(90deg); }
@media (max-width: 480px) {
    .transfer-grid { grid-template-columns: minmax(0, 1fr); }
    .swap-button { justify-self: center; transform: none; margin: -0.5rem 0 0.5rem; }
}

.balance-card {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.5rem;
    padding: 0.75rem;
    margin-bottom: 1.1rem;
    border: 1px solid var(--line);
    border-radius: 12px;
    background: #f8fafc;
    transition: opacity 0.15s ease;
}
.balance-card.is-loading { opacity: 0.5; }
.balance-col { display: flex; flex-direction: column; align-items: center; gap: 0.15rem; min-width: 0; }
.balance-label { font-size: 0.7rem; color: var(--ink-mute); white-space: nowrap; }
.balance-col strong { font-size: 1.05rem; font-weight: 800; }
.balance-after { border-inline-start: 1px dashed #cbd5e1; }
.balance-after.up strong { color: var(--ok); }
.balance-after.down strong { color: var(--danger); }

.field-hint { display: flex; align-items: center; gap: 0.35rem; width: 100%; margin-top: 0.3rem; font-size: 0.78rem; color: var(--ink-mute); line-height: 1.4; }
.delta-up { color: var(--ok); }
.delta-down { color: var(--danger); }

.form-alert { margin-bottom: 1rem; }

.drawer-footer { display: flex; justify-content: flex-end; gap: 0.75rem; }
</style>

<style>
/* The import result is rendered by ElMessageBox outside this component. */
.inventory-import-result { max-width: 460px; width: calc(100% - 32px); }
.inventory-import-result .import-summary { list-style: none; margin: 0; padding: 0; }
.inventory-import-result .import-summary li { display: flex; justify-content: space-between; padding: 0.3rem 0; border-bottom: 1px solid #f1f5f9; }
.inventory-import-result .import-note { margin: 0.75rem 0 0; font-size: 0.82rem; color: #64748b; }
.inventory-import-result .import-errors-title { margin: 0.9rem 0 0.35rem; font-weight: 700; color: #b91c1c; }
.inventory-import-result .import-errors { margin: 0; padding-inline-start: 1.1rem; max-height: 220px; overflow-y: auto; font-size: 0.82rem; }
</style>
