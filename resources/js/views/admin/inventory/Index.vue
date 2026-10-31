<template>
    <div class="inventory-index" :class="{ 'is-compact-mode': isCompact }">
        <AdminPageHeader
            icon="fas fa-warehouse"
            :title="$t('inventory_dashboard')"
            :subtitle="$t('inventory_dashboard_subtitle')"
        >
            <template #actions>
                <el-tooltip :content="$t('refresh')" placement="bottom" :enterable="false">
                    <el-button :icon="Refresh" :loading="refreshing" circle :aria-label="$t('refresh')" @click="refreshAll" />
                </el-tooltip>

                <!-- Print inventory sheet / physical count sheet -->
                <el-button :icon="Printer" @click="openPrintDialog()">
                    {{ $t('inv_admin_print_sheet') }}
                </el-button>

                <el-dropdown v-if="!readOnly" trigger="click" @command="onToolsCommand">
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

                <router-link v-if="!readOnly" :to="{ name: 'admin.inventory.movements' }" custom v-slot="{ navigate }">
                    <el-button :icon="Tickets" @click="navigate">{{ $t('movement_log') }}</el-button>
                </router-link>

                <template v-if="!readOnly">
                    <el-button :icon="Operation" @click="costMethodModalVisible = true">
                        {{ $t('cost_calculation_method') }}
                        <span class="header-method-badge">{{ activeCostMethod }}</span>
                    </el-button>
                    <el-button :icon="Switch" @click="openMovement('transfer')">{{ $t('transfer_stock') }}</el-button>
                    <el-button type="primary" :icon="Plus" class="cta-btn" @click="openMovement('in')">{{ $t('inv_admin_record_movement') }}</el-button>
                </template>
            </template>
        </AdminPageHeader>

        <input ref="fileInput" type="file" accept=".xlsx" class="visually-hidden" @change="onFileSelected" />

        <!-- Stock Health Ratio Visual Bar -->
        <div v-if="summary" class="health-index-card">
            <div class="health-header">
                <div class="health-title">
                    <span class="health-pulse"></span>
                    <strong>{{ $t('inv_admin_health_ratio') }}</strong>
                    <span class="health-hint">({{ formatNumber(totalRowsCount) }} {{ $t('total_items') || 'أصناف' }})</span>
                </div>
                <div class="health-legend">
                    <button type="button" class="legend-chip ok" :class="{ 'is-active': filters.status === 'ok' }" @click="toggleStatus('ok')">
                        <span class="dot"></span>
                        <span>{{ $t('inv_admin_health_healthy') }}: {{ healthShares.ok }}%</span>
                    </button>
                    <button type="button" class="legend-chip low" :class="{ 'is-active': filters.status === 'low' }" @click="toggleStatus('low')">
                        <span class="dot"></span>
                        <span>{{ $t('inv_admin_health_low') }}: {{ healthShares.low }}%</span>
                    </button>
                    <button type="button" class="legend-chip out" :class="{ 'is-active': filters.status === 'out' }" @click="toggleStatus('out')">
                        <span class="dot"></span>
                        <span>{{ $t('inv_admin_health_out') }}: {{ healthShares.out }}%</span>
                    </button>
                </div>
            </div>
            <div class="health-progress-bar" role="progressbar" aria-label="Inventory health distribution">
                <div
                    class="bar-segment ok"
                    :style="{ width: healthShares.ok + '%' }"
                    :title="`${$t('inv_admin_health_healthy')}: ${healthShares.ok}%`"
                    @click="toggleStatus('ok')"
                />
                <div
                    class="bar-segment low"
                    :style="{ width: healthShares.low + '%' }"
                    :title="`${$t('inv_admin_health_low')}: ${healthShares.low}%`"
                    @click="toggleStatus('low')"
                />
                <div
                    class="bar-segment out"
                    :style="{ width: healthShares.out + '%' }"
                    :title="`${$t('inv_admin_health_out')}: ${healthShares.out}%`"
                    @click="toggleStatus('out')"
                />
            </div>
        </div>

        <!-- Stock health KPI cards -->
        <AdminStatGrid :min="175">
            <!-- Total Stock Value -->
            <el-card v-if="!readOnly" shadow="never" class="stat-card">
                <div class="stat-card-inner">
                    <div class="stat-icon-box value"><el-icon><Coin /></el-icon></div>
                    <div class="stat-details">
                        <h3>
                            <template v-if="hideFinancials">••••••••</template>
                            <template v-else>{{ summary ? formatMoney(summary.total_value) : '—' }}</template>
                        </h3>
                        <p>
                            {{ $t('total_stock_value') }}
                            <el-tooltip :content="$t('cost_calculation_method_management')" placement="top" :enterable="false">
                                <button type="button" class="cost-chip-btn" @click.stop="costMethodModalVisible = true">
                                    {{ activeCostMethod }}
                                </button>
                            </el-tooltip>
                        </p>
                    </div>
                </div>
            </el-card>

            <!-- Available Units for Sale -->
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

            <!-- Active Products (SKUs) -->
            <el-card shadow="never" class="stat-card">
                <div class="stat-card-inner">
                    <div class="stat-icon-box skus"><el-icon><Grid /></el-icon></div>
                    <div class="stat-details">
                        <h3>{{ summary?.products_with_stock ? formatNumber(summary.products_with_stock) : '—' }}</h3>
                        <p>{{ $t('inv_admin_active_skus') }}</p>
                    </div>
                </div>
            </el-card>

            <!-- Status Filter Cards -->
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
                    <div class="stat-icon-box" :class="card.key">
                        <el-icon><component :is="card.icon" /></el-icon>
                    </div>
                    <div class="stat-details">
                        <div class="stat-val-row">
                            <h3>{{ summary ? formatNumber(card.value) : '—' }}</h3>
                            <span v-if="filters.status === card.key" class="filter-active-tag">
                                {{ $t('inv_admin_filter_active') }}
                            </span>
                        </div>
                        <p>{{ card.title }}</p>
                    </div>
                </div>
            </el-card>
        </AdminStatGrid>

        <!-- Warehouses distribution & Today's Activity / Reorder Watchlist -->
        <div class="overview-grid">
            <!-- Warehouses List -->
            <section class="panel-card">
                <header class="panel-head">
                    <div class="panel-title-group">
                        <h2><el-icon><OfficeBuilding /></el-icon> {{ $t('stock_across_warehouses') }}</h2>
                        <span v-if="warehouses.length" class="panel-hint">{{ $t('inv_admin_click_to_filter') }}</span>
                    </div>
                    <el-button
                        v-if="filters.warehouse_id"
                        size="small"
                        type="primary"
                        link
                        :icon="RefreshLeft"
                        @click="filters.warehouse_id = null; applyFilters()"
                    >
                        {{ $t('all_warehouses') }}
                    </el-button>
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
                                <span class="wh-pin" :class="{ 'is-active': filters.warehouse_id === w.id }"></span>
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
                        <span class="wh-meta-line">
                            <span class="wh-share">{{ $t('inv_admin_share', { value: warehouseShare(w, true) }) }}</span>
                            <span v-if="filters.warehouse_id === w.id" class="wh-selected-indicator">
                                <el-icon><Check /></el-icon> {{ $t('inv_admin_filter_active') }}
                            </span>
                        </span>
                    </button>
                </div>
                <el-empty v-else :description="$t('no_warehouses')" :image-size="70" />
            </section>

            <!-- Today's Activity and Needs Reordering -->
            <section class="panel-card">
                <header class="panel-head">
                    <h2><el-icon><Calendar /></el-icon> {{ $t('today_s_activity') }}</h2>
                    <router-link :to="{ name: 'admin.inventory.movements' }" class="panel-link">
                        {{ $t('movement_log') }} &rarr;
                    </router-link>
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
                    <div class="reorder-title-box">
                        <span class="reorder-alert-icon"><el-icon><Warning /></el-icon></span>
                        <h3>{{ $t('inv_admin_reorder_title') }}</h3>
                    </div>
                    <el-button v-if="(summary?.low_stock_rows || 0) > 0" link type="primary" @click="toggleStatus('low', true)">
                        {{ $t('inv_admin_view_all', { count: formatNumber(summary.low_stock_rows) }) }}
                    </el-button>
                </div>

                <div v-if="attentionLoading && !attention.length" class="skeleton-list">
                    <el-skeleton :rows="2" animated />
                </div>
                <ul v-else-if="attention.length" class="reorder-list">
                    <li v-for="row in attention" :key="row.id" class="reorder-item">
                        <div class="reorder-img">
                            <EntityImage :src="row.product?.image_main || ''" type="product" :size="36" />
                        </div>
                        <div class="reorder-text">
                            <strong>{{ productName(row) }}</strong>
                            <span>{{ row.warehouse?.name }} · {{ $t('inv_admin_reorder_at', { value: formatNumber(row.reorder_point) }) }}</span>
                        </div>
                        <div class="reorder-qty-pill">
                            <span class="stock-pill low">
                                <span class="stock-dot" />{{ formatNumber(row.available) }}
                            </span>
                        </div>
                        <el-tooltip v-if="!readOnly" :content="$t('inv_admin_receive')" placement="top" :enterable="false">
                            <el-button size="small" type="primary" plain circle :icon="Plus" :aria-label="$t('inv_admin_receive')" @click="openMovement('in', row)" />
                        </el-tooltip>
                    </li>
                </ul>
                <div v-else class="reorder-empty">
                    <el-icon><CircleCheck /></el-icon>
                    {{ $t('no_low_stock_items') }}
                </div>
            </section>
        </div>

        <!-- Balances Table Panel -->
        <section class="panel-card table-panel-card">
            <header class="panel-head table-head">
                <div class="table-head-title">
                    <h2><el-icon><Grid /></el-icon> {{ $t('item_balances_by_warehouse') }}</h2>
                    <span v-if="total > 0" class="table-head-badge">{{ formatNumber(total) }} {{ $t('total_items') || 'أرصدة' }}</span>
                </div>

                <div class="table-view-controls">
                    <!-- Hide/Show financial values -->
                    <el-tooltip v-if="!readOnly" :content="hideFinancials ? $t('inv_admin_show_financials') : $t('inv_admin_hide_financials')" placement="top" :enterable="false">
                        <el-button
                            circle
                            size="small"
                            :type="hideFinancials ? 'warning' : 'default'"
                            @click="hideFinancials = !hideFinancials"
                        >
                            <el-icon><component :is="hideFinancials ? Hide : View" /></el-icon>
                        </el-button>
                    </el-tooltip>

                    <!-- Table density toggle (Compact / Normal) -->
                    <el-tooltip :content="isCompact ? $t('inv_admin_density_normal') : $t('inv_admin_density_compact')" placement="top" :enterable="false">
                        <el-button
                            circle
                            size="small"
                            :type="isCompact ? 'primary' : 'default'"
                            @click="isCompact = !isCompact"
                        >
                            <el-icon><Operation /></el-icon>
                        </el-button>
                    </el-tooltip>
                </div>
            </header>

            <!-- Filters Bar -->
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
                    filterable
                    @change="applyFilters"
                >
                    <el-option v-for="w in warehouses" :key="w.id" :label="w.name" :value="w.id" />
                </el-select>

                <el-radio-group v-model="filters.status" class="filter-status" @change="applyFilters">
                    <el-radio-button value="">{{ $t('cat_admin_all') }}</el-radio-button>
                    <el-radio-button value="ok">
                        <span class="status-tab-item">
                            <span class="status-circle ok"></span>
                            {{ $t('available') }}
                        </span>
                    </el-radio-button>
                    <el-radio-button value="low">
                        <span class="status-tab-item">
                            <span class="status-circle low"></span>
                            {{ $t('low') }}
                        </span>
                    </el-radio-button>
                    <el-radio-button value="out">
                        <span class="status-tab-item">
                            <span class="status-circle out"></span>
                            {{ $t('out_of_stock_short') }}
                        </span>
                    </el-radio-button>
                </el-radio-group>

                <el-button v-if="activeFilterCount" :icon="RefreshLeft" @click="resetFilters">
                    {{ $t('prod_admin_clear_filters', { count: activeFilterCount }) }}
                </el-button>
            </div>

            <!-- Active Filter Badges Bar -->
            <div v-if="activeFilterCount" class="active-chips-bar">
                <span class="chips-title">{{ $t('filtered_by') || 'التصفية النشطة:' }}</span>
                <el-tag v-if="filters.search" closable size="small" type="info" @close="filters.search = ''; applyFilters()">
                    {{ $t('search') }}: "{{ filters.search }}"
                </el-tag>
                <el-tag v-if="selectedWarehouseObj" closable size="small" type="primary" @close="filters.warehouse_id = null; applyFilters()">
                    {{ $t('warehouse') }}: {{ selectedWarehouseObj.name }}
                </el-tag>
                <el-tag v-if="filters.status" closable size="small" :type="statusTagType(filters.status)" @close="filters.status = ''; applyFilters()">
                    {{ $t('status') }}: {{ statusLabel(filters.status) }}
                </el-tag>
                <el-button link type="danger" size="small" @click="resetFilters">
                    {{ $t('clear_all') || 'مسح الكل' }}
                </el-button>
            </div>

            <!-- Batch Selection Action Bar -->
            <transition name="el-zoom-in-top">
                <div v-if="selectedRows.length" class="batch-bar">
                    <div class="batch-count">
                        <el-icon><Check /></el-icon>
                        <strong>{{ $t('inv_admin_selected_count', { count: selectedRows.length }) }}</strong>
                    </div>
                    <div class="batch-actions">
                        <el-button size="small" :icon="Printer" type="primary" plain @click="openPrintDialog(true)">
                            {{ $t('inv_admin_print_selected') }}
                        </el-button>
                        <el-button size="small" :icon="Download" plain @click="exportSelected">
                            {{ $t('inv_admin_export_selected') }}
                        </el-button>
                        <el-button size="small" link @click="clearSelection">
                            {{ $t('inv_admin_clear_selection') }}
                        </el-button>
                    </div>
                </div>
            </transition>

            <el-result v-if="loadError && !rows.length" icon="error" :title="$t('failed_to_load_stock_data')">
                <template #extra>
                    <el-button type="primary" :icon="Refresh" @click="loadStock">{{ $t('cat_admin_retry') }}</el-button>
                </template>
            </el-result>

            <div v-else class="table-wrapper">
                <el-table
                    ref="tableRef"
                    v-loading="stockLoading"
                    :data="rows"
                    row-key="id"
                    style="width: 100%"
                    :size="isCompact ? 'small' : 'default'"
                    :row-class-name="rowClassName"
                    :default-sort="defaultSort"
                    @selection-change="onSelectionChange"
                    @sort-change="onSortChange"
                >
                    <template #empty>
                        <el-empty v-if="!stockLoading && activeFilterCount" :description="$t('inv_admin_no_matches')" :image-size="90">
                            <el-button @click="resetFilters">{{ $t('cat_admin_clear_filters') }}</el-button>
                        </el-empty>
                        <el-empty v-else-if="!stockLoading" :description="$t('no_stock_data')" :image-size="90">
                            <el-button v-if="!readOnly" type="primary" :icon="Plus" @click="openMovement('in')">{{ $t('inv_admin_record_movement') }}</el-button>
                        </el-empty>
                        <span v-else />
                    </template>

                    <!-- Selection column for batch actions -->
                    <el-table-column type="selection" width="46" align="center" />

                    <!-- Product -->
                    <el-table-column :label="$t('product')" min-width="250">
                        <template #default="{ row }">
                            <div class="product-cell">
                                <EntityImage :src="row.product?.image_main || ''" type="product" :size="isCompact ? 36 : 44" class="p-thumb" />
                                <div class="cell-stack">
                                    <router-link
                                        v-if="row.product?.id"
                                        :to="{ name: 'admin.products.show', params: { id: row.product.id } }"
                                        class="product-name"
                                    >
                                        {{ productName(row) }}
                                    </router-link>
                                    <span v-else class="product-name">{{ productName(row) }}</span>
                                    <div class="cell-meta">
                                        <el-tooltip v-if="row.product?.sku" :content="$t('prod_admin_copy_sku')" placement="top" :enterable="false">
                                            <button type="button" class="sku-chip" dir="ltr" @click="copySku(row.product.sku)">
                                                <span class="sku-txt">{{ row.product.sku }}</span>
                                                <el-icon :size="11"><DocumentCopy /></el-icon>
                                            </button>
                                        </el-tooltip>
                                        <span v-if="row.product?.barcode" class="barcode-tag" dir="ltr">
                                            {{ row.product.barcode }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </el-table-column>

                    <!-- Warehouse & Bin -->
                    <el-table-column :label="$t('warehouse')" min-width="140">
                        <template #default="{ row }">
                            <div class="warehouse-cell">
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
                                <span v-if="row.bin?.code" class="bin-badge" dir="ltr">{{ row.bin.code }}</span>
                            </div>
                        </template>
                    </el-table-column>

                    <!-- On hand -->
                    <el-table-column :label="$t('inv_admin_on_hand')" prop="quantity" sortable="custom" width="105" align="center">
                        <template #default="{ row }">
                            <span class="num-on-hand">{{ formatNumber(row.quantity) }}</span>
                        </template>
                    </el-table-column>

                    <!-- Reserved -->
                    <el-table-column :label="$t('reserved')" prop="reserved_quantity" sortable="custom" width="100" align="center">
                        <template #default="{ row }">
                            <span v-if="Number(row.reserved_quantity) > 0" class="num-reserved" :title="`${formatNumber(row.reserved_quantity)} units reserved for active orders`">
                                {{ formatNumber(row.reserved_quantity) }}
                            </span>
                            <span v-else class="cell-empty">—</span>
                        </template>
                    </el-table-column>

                    <!-- Available with Mini Level Bar -->
                    <el-table-column :label="$t('available_amount')" prop="available" sortable="custom" width="145" align="center">
                        <template #default="{ row }">
                            <div class="available-cell">
                                <el-tooltip :content="stockHint(row)" placement="top" :enterable="false">
                                    <span class="stock-pill" :class="stockLevel(row)">
                                        <span class="stock-dot" />
                                        {{ formatNumber(row.available) }}
                                    </span>
                                </el-tooltip>

                                <!-- Mini stock level gauge relative to reorder point -->
                                <div v-if="Number(row.reorder_point) > 0" class="mini-gauge" :title="`Reorder: ${formatNumber(row.reorder_point)}`">
                                    <div
                                        class="mini-gauge-fill"
                                        :class="stockLevel(row)"
                                        :style="{ width: Math.min(100, Math.round((Number(row.available) / Number(row.reorder_point)) * 100)) + '%' }"
                                    />
                                </div>

                                <span v-if="unsellable(row) > 0" class="cell-secondary cell-warn">
                                    {{ $t('inv_admin_unsellable', { count: formatNumber(unsellable(row)) }) }}
                                </span>
                            </div>
                        </template>
                    </el-table-column>

                    <!-- Cost (respects hideFinancials) -->
                    <el-table-column v-if="!readOnly" :label="$t('cost')" width="115" align="center">
                        <template #default="{ row }">
                            <template v-if="hideFinancials">
                                <span class="masked-num">••••••</span>
                            </template>
                            <template v-else>
                                <span v-if="Number(row.product?.cost_price) > 0" class="cost-num">{{ formatMoney(row.product.cost_price) }}</span>
                                <el-tooltip v-else :content="$t('inv_admin_no_cost_hint')" placement="top" :enterable="false">
                                    <span class="cell-empty cell-warn-soft">{{ $t('inv_admin_no_cost') }}</span>
                                </el-tooltip>
                            </template>
                        </template>
                    </el-table-column>

                    <!-- Total Value (respects hideFinancials) -->
                    <el-table-column v-if="!readOnly" :label="$t('value')" width="130" align="center">
                        <template #default="{ row }">
                            <template v-if="hideFinancials">
                                <span class="masked-num">••••••••</span>
                            </template>
                            <template v-else>
                                <strong v-if="rowValue(row) > 0" class="value-num">{{ formatMoney(rowValue(row)) }}</strong>
                                <span v-else class="cell-empty">—</span>
                            </template>
                        </template>
                    </el-table-column>

                    <!-- Latest update -->
                    <el-table-column :label="$t('latest_update')" prop="updated_at" sortable="custom" width="130" align="center">
                        <template #default="{ row }">
                            <el-tooltip :content="formatDate(row.updated_at)" placement="top" :enterable="false">
                                <span class="cell-secondary">{{ relativeDate(row.updated_at) }}</span>
                            </el-tooltip>
                        </template>
                    </el-table-column>

                    <!-- Procedures / Actions -->
                    <el-table-column v-if="!readOnly" :label="$t('procedures')" width="180" align="center" fixed="right">
                        <template #default="{ row }">
                            <div class="row-actions">
                                <el-tooltip :content="$t('inv_admin_receive')" placement="top" :enterable="false">
                                    <el-button size="small" circle :icon="Plus" class="act-btn receive" :aria-label="$t('inv_admin_receive')" @click="openMovement('in', row)" />
                                </el-tooltip>
                                <el-tooltip :content="$t('inv_admin_count')" placement="top" :enterable="false">
                                    <el-button size="small" circle :icon="EditPen" class="act-btn count" :aria-label="$t('inv_admin_count')" @click="openMovement('count', row)" />
                                </el-tooltip>
                                <el-tooltip :content="$t('transfer_stock')" placement="top" :enterable="false">
                                    <el-button size="small" circle :icon="Switch" class="act-btn transfer" :aria-label="$t('transfer_stock')" @click="openMovement('transfer', row)" />
                                </el-tooltip>
                                <el-dropdown trigger="click" @command="(cmd) => onRowCommand(cmd, row)">
                                    <el-button size="small" circle :icon="MoreFilled" class="act-btn more" :aria-label="$t('inv_admin_more')" />
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

        <!-- Movement Drawer (In, Out, Count, Transfer) -->
        <el-drawer
            v-model="drawerVisible"
            :size="isNarrow ? '100%' : '520px'"
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

                <!-- Product Selection -->
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

                <!-- Warehouses -->
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

                <!-- Live Dynamic Balance Impact Card -->
                <div v-if="mv.product_id && sourceWarehouseId" class="balance-card" :class="{ 'is-loading': balanceLoading }">
                    <div class="balance-col">
                        <span class="balance-label">{{ $t('inv_admin_on_hand') }}</span>
                        <strong>{{ formatNumber(sourceBalance.quantity) }}</strong>
                    </div>
                    <div class="balance-col">
                        <span class="balance-label">{{ $t('reserved') }}</span>
                        <strong class="text-warn">{{ formatNumber(sourceBalance.reserved) }}</strong>
                    </div>
                    <div class="balance-col">
                        <span class="balance-label">{{ $t('available_amount') }}</span>
                        <strong class="text-ok">{{ formatNumber(sourceBalance.available) }}</strong>
                    </div>
                    <div class="balance-col balance-after" :class="afterTone">
                        <span class="balance-label">{{ $t('inv_admin_after') }}</span>
                        <strong>{{ formatNumber(onHandAfter) }}</strong>
                    </div>
                </div>

                <!-- Quantity Controls -->
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

                    <!-- Quick quantity increment pills -->
                    <div class="quick-qty-strip">
                        <span class="quick-qty-label">{{ $t('inv_admin_quick_qty') }}:</span>
                        <button type="button" class="quick-qty-btn" @click="mv.quantity = 1">1</button>
                        <button type="button" class="quick-qty-btn" @click="mv.quantity = 5">5</button>
                        <button type="button" class="quick-qty-btn" @click="mv.quantity = 10">10</button>
                        <button type="button" class="quick-qty-btn" @click="mv.quantity = 50">50</button>
                        <button
                            v-if="(mv.mode === 'out' || mv.mode === 'transfer') && sourceBalance.available > 0"
                            type="button"
                            class="quick-qty-btn use-all"
                            @click="mv.quantity = sourceBalance.available"
                        >
                            {{ $t('inv_admin_use_all') }} ({{ formatNumber(sourceBalance.available) }})
                        </button>
                    </div>

                    <span v-if="(mv.mode === 'out' || mv.mode === 'transfer') && sourceWarehouseId && mv.product_id" class="field-hint">
                        {{ $t('inv_admin_max_available', { count: formatNumber(sourceBalance.available) }) }}
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

        <!-- Cost Calculation Method Management Modal -->
        <CostCalculationMethodModal
            v-model="costMethodModalVisible"
            @saved="onCostMethodSaved"
        />

        <!-- Inventory Balance & Stock-Take Print Dialog -->
        <InventoryBalancePrintDialog
            v-model="printDialogVisible"
            :filters="filters"
            :warehouses="warehouses"
            :selected-rows="printSelectedOnly ? selectedRows : []"
            :read-only="readOnly"
        />
    </div>
</template>

<script setup>
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';
import EntityImage from '@/components/admin/EntityImage.vue';
import CostCalculationMethodModal from '@/components/admin/inventory/CostCalculationMethodModal.vue';
import InventoryBalancePrintDialog from '@/components/admin/inventory/InventoryBalancePrintDialog.vue';
import { formatMoney as formatBaseMoney, formatNumber as formatCount, numberLocale } from '@/utils/currency';
import { useI18n } from 'vue-i18n';
import { ref, reactive, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useInventoryStore } from '@/stores/inventory';
import { useAuthStore } from '@/stores/auth';
import { inventoryApi } from '@/api/inventory';
import { productsApi } from '@/api/products';
import {
    Plus, Search, Refresh, RefreshLeft, Edit, EditPen, Switch, Tickets, Download, Upload, Files, ArrowDown,
    DocumentCopy, Coin, Box, CircleCheck, Warning, RemoveFilled, OfficeBuilding, Calendar, Sort, Bottom, Top,
    Grid, MoreFilled, Operation, Printer, View, Hide, Check
} from '@element-plus/icons-vue';

const { t } = useI18n();
const router = useRouter();
const route = useRoute();
const store = useInventoryStore();
const authStore = useAuthStore();

// A sales rep sees the stock and nothing else: no movements, transfers,
// imports, cost figures or costing method.
const readOnly = computed(() => !!authStore.user?.is_sales_scoped);

const costMethodModalVisible = ref(false);
const activeCostMethod = ref('FIFO');

// Financial privacy toggle (hide costs and valuations)
const hideFinancials = ref(false);

// View density mode (compact vs comfortable)
const isCompact = ref(false);

// Selection state
const tableRef = ref(null);
const selectedRows = ref([]);

const onSelectionChange = (selection) => {
    selectedRows.value = selection;
};

const clearSelection = () => {
    tableRef.value?.clearSelection();
    selectedRows.value = [];
};

// Print dialog state
const printDialogVisible = ref(false);
const printSelectedOnly = ref(false);

const openPrintDialog = (selectedOnly = false) => {
    printSelectedOnly.value = selectedOnly;
    printDialogVisible.value = true;
};

const exportSelected = () => {
    if (!selectedRows.value.length) return;
    // Export selected items directly as CSV/XLSX
    const headers = ['Product', 'SKU', 'Warehouse', 'On Hand', 'Reserved', 'Available'];
    const rowsData = selectedRows.value.map((r) => [
        `"${productName(r).replace(/"/g, '""')}"`,
        `"${r.product?.sku || ''}"`,
        `"${r.warehouse?.name || ''}"`,
        r.quantity || 0,
        r.reserved_quantity || 0,
        r.available || 0,
    ]);
    const csvContent = 'data:text/csv;charset=utf-8,\uFEFF' + [headers.join(','), ...rowsData.map((e) => e.join(','))].join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `inventory-selected-${new Date().toISOString().slice(0, 10)}.csv`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    ElMessage.success(t('balances_exported'));
};

const fetchCostMethod = async () => {
    try {
        const res = await inventoryApi.getCostingMethod();
        if (res.data?.data?.current_method) {
            activeCostMethod.value = res.data.data.current_method;
        }
    } catch {
        // silent fallback to default
    }
};

const onCostMethodSaved = (data) => {
    if (data?.current_method) {
        activeCostMethod.value = data.current_method;
    }
    refreshAll();
};

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

// ── Summary & Health Ratio ───────────────────────────────────────────────
const summary = computed(() => store.summary);
const today = computed(() => store.today);
const warehouses = computed(() => store.warehouses);
const summaryLoading = ref(false);

const heldUnits = computed(() => Math.max(0, (summary.value?.total_quantity || 0) - (summary.value?.total_available || 0)));

const totalRowsCount = computed(() => {
    if (!summary.value) return 0;
    const inStock = summary.value.in_stock_rows || 0;
    const outStock = summary.value.out_of_stock_rows || 0;
    return inStock + outStock;
});

const healthShares = computed(() => {
    const s = summary.value;
    if (!s) return { ok: 0, low: 0, out: 0 };
    const low = Math.max(0, Number(s.low_stock_rows) || 0);
    const inStock = Math.max(0, Number(s.in_stock_rows) || 0);
    const ok = Math.max(0, inStock - low);
    const out = Math.max(0, Number(s.out_of_stock_rows) || 0);
    const total = ok + low + out;
    if (!total) return { ok: 0, low: 0, out: 0 };
    return {
        ok: Math.round((ok / total) * 100),
        low: Math.round((low / total) * 100),
        out: Math.round((out / total) * 100),
    };
});

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

const statusLabel = (st) => ({
    ok: t('available'),
    low: t('low'),
    out: t('out_of_stock_short'),
}[st] || st);

const statusTagType = (st) => ({
    ok: 'success',
    low: 'warning',
    out: 'danger',
}[st] || 'info');

const warehouseShare = (w, exact = false) => {
    const all = warehouses.value.reduce((sum, x) => sum + Math.max(0, Number(x.total_available) || 0), 0);
    if (!all) return 0;
    const share = (Math.max(0, Number(w.total_available) || 0) / all) * 100;
    return exact ? Math.round(share) : Math.max(share > 0 ? 2 : 0, share);
};

const selectedWarehouseObj = computed(() => {
    if (!filters.warehouse_id) return null;
    return warehouses.value.find((w) => w.id === filters.warehouse_id) || null;
});

const loadSummary = async () => {
    summaryLoading.value = true;
    try {
        await store.fetchSummary();
    } finally {
        summaryLoading.value = false;
    }
};

const attention = ref([]);
const attentionLoading = ref(false);
const loadAttention = async () => {
    attentionLoading.value = true;
    try {
        const res = await inventoryApi.getStock({ status: 'low', sort_by: 'available', sort_dir: 'asc', per_page: 6 });
        attention.value = res.data?.data?.stock || [];
    } catch {
        attention.value = [];
    } finally {
        attentionLoading.value = false;
    }
};

// ── Filters, sort and paging ─────────────────────────────────────────────
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
        if (seq !== stockRequest) return;
        const data = res.data?.data || {};
        rows.value = data.stock || [];
        total.value = data.pagination?.total || 0;
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

const newKey = () => `manual:${globalThis.crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(36).slice(2)}`}`;

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
            const chosen = productOptions.value.find((p) => p.id === mv.product_id);
            productOptions.value = chosen && !found.some((p) => p.id === chosen.id) ? [chosen, ...found] : found;
        } catch {
            // keep existing list
        } finally {
            productSearchLoading.value = false;
        }
    }, 300);
};

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
    if (readOnly.value) return;
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

        if (ignored.length) {
            html += `<p class="import-note">${escapeHtml(t('computed_columns_not_imported', { columns: ignored.join(t('list_separator')) }))}</p>`;
        }

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
    if (!readOnly.value) fetchCostMethod();
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
    --teal: #0d9488;
    --teal-soft: #f0fdfa;
    --gold: #b45309;
    --gold-soft: #fef3c7;

    color: var(--ink);
}

.visually-hidden { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }

/* Header badges and buttons */
.header-method-badge {
    display: inline-block;
    padding: 1px 6px;
    margin-inline-start: 6px;
    font-size: 0.72rem;
    font-weight: 800;
    color: #4f46e5;
    background: #eef2ff;
    border-radius: 4px;
}
.cta-btn {
    font-weight: 700;
    box-shadow: 0 2px 8px rgba(37, 99, 235, 0.25);
}

/* ── Health Ratio Visual Bar ────────────────────────────────────────── */
.health-index-card {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 14px;
    padding: 0.85rem 1.15rem;
    margin-bottom: 1rem;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}
.health-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-bottom: 0.65rem;
}
.health-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.92rem;
}
.health-pulse {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--ok);
    box-shadow: 0 0 0 0 rgba(22, 163, 74, 0.7);
    animation: pulse 2s infinite;
}
@keyframes pulse {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(22, 163, 74, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(22, 163, 74, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(22, 163, 74, 0); }
}
.health-hint {
    color: var(--ink-mute);
    font-size: 0.8rem;
    font-weight: 500;
}
.health-legend {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.legend-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 2px 8px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    border: 1px solid transparent;
    background: transparent;
    cursor: pointer;
    transition: all 0.15s ease;
}
.legend-chip .dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
}
.legend-chip.ok .dot { background: var(--ok); }
.legend-chip.low .dot { background: var(--warn); }
.legend-chip.out .dot { background: var(--danger); }
.legend-chip:hover { background: #f8fafc; border-color: var(--line); }
.legend-chip.is-active {
    background: #f1f5f9;
    border-color: #cbd5e1;
    font-weight: 700;
}

.health-progress-bar {
    display: flex;
    height: 10px;
    border-radius: 999px;
    background: #f1f5f9;
    overflow: hidden;
    gap: 2px;
}
.bar-segment {
    height: 100%;
    cursor: pointer;
    transition: width 0.4s ease, opacity 0.2s ease;
}
.bar-segment:hover { opacity: 0.85; }
.bar-segment.ok { background: linear-gradient(90deg, #22c55e, #16a34a); }
.bar-segment.low { background: linear-gradient(90deg, #f59e0b, #d97706); }
.bar-segment.out { background: linear-gradient(90deg, #ef4444, #dc2626); }

/* ── Stat cards ─────────────────────────────────────────────────────── */
.stat-card {
    border-radius: 14px;
    border: 1px solid var(--line);
    transition: border-color 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;
}
.stat-card.is-clickable { cursor: pointer; }
.stat-card.is-clickable:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08); }
.stat-card.is-clickable:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }
.stat-card.is-selected {
    border-color: var(--primary);
    box-shadow: 0 0 0 1.5px var(--primary) inset, 0 4px 12px rgba(37, 99, 235, 0.12);
}

.stat-card-inner { display: flex; align-items: center; gap: 0.9rem; }
.stat-icon-box {
    width: 48px;
    height: 48px;
    flex-shrink: 0;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
}
.stat-icon-box.value { background: var(--gold-soft); color: var(--gold); }
.stat-icon-box.units { background: var(--purple-soft); color: var(--purple); }
.stat-icon-box.skus { background: var(--teal-soft); color: var(--teal); }
.stat-icon-box.ok { background: var(--ok-soft); color: var(--ok); }
.stat-icon-box.low { background: var(--warn-soft); color: var(--warn); }
.stat-icon-box.out { background: var(--danger-soft); color: var(--danger); }

.stat-details { min-width: 0; flex: 1; }
.stat-val-row { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; }
.stat-details h3 { margin: 0; font-size: 1.35rem; font-weight: 800; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.stat-details p { margin: 0.2rem 0 0; color: var(--ink-mute); font-size: 0.8rem; font-weight: 600; }
.stat-note { font-weight: 500; color: #94a3b8; }
.filter-active-tag {
    font-size: 0.68rem;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 999px;
    background: var(--primary-soft);
    color: var(--primary);
}

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
.panel-title-group {
    display: flex;
    align-items: center;
    gap: 0.6rem;
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
    padding: 0.65rem 0.85rem;
    border: 1px solid var(--line);
    border-radius: 10px;
    background: #fbfcfe;
    font: inherit;
    color: inherit;
    text-align: start;
    cursor: pointer;
    transition: all 0.15s ease;
}
.warehouse-row:hover { background: #f8fafc; border-color: #cbd5e1; }
.warehouse-row:focus-visible { outline: 2px solid var(--primary); outline-offset: 1px; }
.warehouse-row.is-selected {
    background: var(--primary-soft);
    border-color: #93c5fd;
    box-shadow: 0 0 0 1px #93c5fd;
}
.warehouse-row.is-inactive { opacity: 0.7; }
.warehouse-top { display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; }
.warehouse-name { display: flex; align-items: center; gap: 0.4rem; min-width: 0; flex-wrap: wrap; }
.wh-pin {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #94a3b8;
}
.wh-pin.is-active { background: var(--primary); }
.wh-code { color: var(--ink-mute); font-size: 0.72rem; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
.wh-qty { font-weight: 700; font-size: 0.85rem; white-space: nowrap; }
.wh-bar { position: relative; display: block; height: 8px; border-radius: 999px; background: #eef2f7; overflow: hidden; }
.wh-bar-fill { position: absolute; inset-block: 0; inset-inline-start: 0; border-radius: inherit; background: linear-gradient(90deg, #3b82f6, #6366f1); transition: width 0.3s ease; }
.warehouse-row.is-selected .wh-bar-fill { background: var(--primary); }
.wh-meta-line { display: flex; justify-content: space-between; align-items: center; }
.wh-share { font-size: 0.72rem; color: #94a3b8; }
.wh-selected-indicator { font-size: 0.72rem; font-weight: 700; color: var(--primary); display: flex; align-items: center; gap: 2px; }

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
    padding: 0.65rem 0.75rem;
    border-radius: 10px;
    background: #f8fafc;
    border: 1px solid var(--line);
    min-width: 0;
}
.today-item div { display: flex; flex-direction: column; min-width: 0; }
.today-item strong { font-size: 1.15rem; font-weight: 800; line-height: 1.2; }
.today-item div span { font-size: 0.72rem; color: var(--ink-mute); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.today-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 1.1rem; }
.today-icon.neutral { background: var(--primary-soft); color: var(--primary); }
.today-icon.in { background: var(--ok-soft); color: var(--ok); }
.today-icon.out { background: var(--danger-soft); color: var(--danger); }
@media (max-width: 480px) {
    .today-stats { grid-template-columns: minmax(0, 1fr); }
}

.reorder-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.6rem; }
.reorder-title-box { display: flex; align-items: center; gap: 0.4rem; }
.reorder-alert-icon { color: var(--warn); display: flex; align-items: center; }
.reorder-head h3 { margin: 0; font-size: 0.88rem; font-weight: 700; color: #334155; }
.reorder-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.45rem; }
.reorder-item {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.5rem 0.75rem;
    border: 1px solid #fde68a;
    border-radius: 10px;
    background: var(--warn-soft);
    transition: background 0.15s ease;
}
.reorder-item:hover { background: #fef3c7; }
.reorder-img { flex-shrink: 0; }
.reorder-text { display: flex; flex-direction: column; min-width: 0; flex: 1 1 auto; }
.reorder-text strong { font-size: 0.85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #1e293b; }
.reorder-text span { font-size: 0.72rem; color: var(--ink-mute); }
.reorder-qty-pill { flex-shrink: 0; }
.reorder-item .el-button { margin: 0; flex-shrink: 0; }
.reorder-empty {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.85rem;
    border-radius: 10px;
    background: var(--ok-soft);
    color: #15803d;
    font-size: 0.85rem;
    font-weight: 600;
}

/* ── Table Panel & Toolbar ───────────────────────────────────────────── */
.table-panel-card { padding-bottom: 1.25rem; }
.table-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}
.table-head-title {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}
.table-head-badge {
    font-size: 0.75rem;
    font-weight: 700;
    color: #475569;
    background: #f1f5f9;
    padding: 2px 8px;
    border-radius: 999px;
}
.table-view-controls {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.filters { display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem; }
.filter-search { flex: 1 1 260px; max-width: 380px; }
.filter-select { width: 190px; }
.status-tab-item { display: inline-flex; align-items: center; gap: 0.35rem; }
.status-circle {
    width: 6px;
    height: 6px;
    border-radius: 50%;
}
.status-circle.ok { background: var(--ok); }
.status-circle.low { background: var(--warn); }
.status-circle.out { background: var(--danger); }

/* Active chips */
.active-chips-bar {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
    padding: 0.45rem 0.75rem;
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    border-radius: 8px;
    margin-bottom: 0.75rem;
    font-size: 0.8rem;
}
.chips-title { color: var(--ink-mute); font-weight: 600; }

/* Batch actions bar */
.batch-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.75rem;
    padding: 0.6rem 0.9rem;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 8px;
    margin-bottom: 0.75rem;
}
.batch-count {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    color: var(--primary);
    font-size: 0.85rem;
}
.batch-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* ── Table ──────────────────────────────────────────────────────────── */
.table-wrapper { border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
.table-wrapper :deep(.row-out td.el-table__cell:first-child) { box-shadow: inset -3px 0 0 var(--danger); }
.table-wrapper :deep(.row-low td.el-table__cell:first-child) { box-shadow: inset -3px 0 0 var(--warn); }
[dir="ltr"] .table-wrapper :deep(.row-out td.el-table__cell:first-child) { box-shadow: inset 3px 0 0 var(--danger); }
[dir="ltr"] .table-wrapper :deep(.row-low td.el-table__cell:first-child) { box-shadow: inset 3px 0 0 var(--warn); }

.product-cell { display: flex; align-items: center; gap: 0.75rem; min-width: 0; }
.p-thumb { flex-shrink: 0; border-radius: 8px; }
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
.cell-meta { display: flex; flex-wrap: wrap; gap: 0.35rem; align-items: center; }
.cell-secondary { display: block; font-size: 0.74rem; color: var(--ink-mute); margin-top: 0.15rem; }
.cell-warn { color: var(--danger); }
.cell-empty { color: #94a3b8; font-size: 0.8rem; }
.cell-warn-soft { color: var(--warn); cursor: help; }

.sku-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0 0.45rem;
    border: 1px solid var(--line);
    border-radius: 6px;
    background: #f8fafc;
    color: #475569;
    font: 600 0.72rem/1.6 ui-monospace, SFMono-Regular, Menlo, monospace;
    cursor: pointer;
    transition: all 0.15s ease;
}
.sku-chip:hover { border-color: var(--primary); color: var(--primary); background: #eff6ff; }
.barcode-tag {
    font-size: 0.68rem;
    color: #64748b;
    font-family: ui-monospace, monospace;
    background: #f1f5f9;
    padding: 0 4px;
    border-radius: 4px;
}

.warehouse-cell { display: flex; flex-direction: column; align-items: flex-start; gap: 0.2rem; }
.chip-button {
    max-width: 100%;
    padding: 0.15rem 0.6rem;
    border: 1px solid var(--line);
    border-radius: 8px;
    background: #fff;
    color: #334155;
    font: inherit;
    font-size: 0.78rem;
    line-height: 1.4;
    text-align: start;
    cursor: pointer;
    transition: all 0.15s ease;
}
.chip-button:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-soft); }
.bin-badge {
    display: inline-block;
    font-size: 0.7rem;
    color: #475569;
    background: #f1f5f9;
    padding: 1px 5px;
    border-radius: 4px;
    font-family: ui-monospace, monospace;
}

.num-on-hand { color: #1e293b; font-weight: 700; font-size: 0.9rem; }
.num-reserved { color: var(--warn); font-weight: 800; background: var(--warn-soft); padding: 1px 6px; border-radius: 6px; }

.available-cell { display: flex; flex-direction: column; align-items: center; gap: 0.25rem; }
.stock-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    min-width: 60px;
    justify-content: center;
    padding: 0.18rem 0.65rem;
    border-radius: 999px;
    font-weight: 800;
    font-size: 0.85rem;
    cursor: default;
}
.stock-dot { width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
.stock-pill.ok { color: #15803d; background: var(--ok-soft); }
.stock-pill.low { color: #b45309; background: var(--warn-soft); }
.stock-pill.out { color: #b91c1c; background: var(--danger-soft); }

/* Mini gauge */
.mini-gauge {
    width: 54px;
    height: 4px;
    border-radius: 999px;
    background: #e2e8f0;
    overflow: hidden;
}
.mini-gauge-fill {
    height: 100%;
    border-radius: inherit;
    transition: width 0.3s ease;
}
.mini-gauge-fill.ok { background: var(--ok); }
.mini-gauge-fill.low { background: var(--warn); }
.mini-gauge-fill.out { background: var(--danger); }

.cost-num { font-size: 0.85rem; color: #475569; }
.value-num { font-size: 0.9rem; color: #0f172a; }
.masked-num { color: #94a3b8; font-size: 0.85rem; letter-spacing: 2px; }

.row-actions { display: flex; flex-wrap: nowrap; gap: 0.3rem; justify-content: center; }
.act-btn { transition: transform 0.15s ease; }
.act-btn:hover { transform: scale(1.1); }
.act-btn.receive:hover { color: var(--ok); border-color: var(--ok); }
.act-btn.count:hover { color: var(--purple); border-color: var(--purple); }
.act-btn.transfer:hover { color: var(--primary); border-color: var(--primary); }

.pagination-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding-top: 1rem;
}
.pagination-summary { font-size: 0.82rem; color: var(--ink-mute); }

/* ── Compact Density Mode ───────────────────────────────────────────── */
.is-compact-mode :deep(.el-table__cell) { padding: 4px 0 !important; }
.is-compact-mode .product-cell { gap: 0.45rem; }
.is-compact-mode .product-name { font-size: 0.82rem; }
.is-compact-mode .stock-pill { font-size: 0.78rem; padding: 0.1rem 0.5rem; }

/* ── Movement Drawer ────────────────────────────────────────────────── */
.drawer-title { display: flex; align-items: center; gap: 0.75rem; }
.drawer-title h3 { margin: 0; font-size: 1.05rem; font-weight: 800; color: var(--ink); }
.drawer-title p { margin: 0.15rem 0 0; font-size: 0.78rem; color: var(--ink-mute); }
.drawer-icon { width: 40px; height: 40px; border-radius: 11px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; flex-shrink: 0; }
.drawer-icon.in { background: var(--ok-soft); color: var(--ok); }
.drawer-icon.out { background: var(--danger-soft); color: var(--danger); }
.drawer-icon.count { background: var(--purple-soft); color: var(--purple); }
.drawer-icon.transfer { background: var(--primary-soft); color: var(--primary); }

.mode-switch { width: 100%; margin-bottom: 0.5rem; }

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
    padding: 0.85rem 0.75rem;
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

/* Quick quantity buttons */
.quick-qty-strip {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
    margin-top: 0.4rem;
}
.quick-qty-label { font-size: 0.75rem; color: var(--ink-mute); margin-inline-end: 2px; }
.quick-qty-btn {
    padding: 2px 8px;
    font-size: 0.75rem;
    font-weight: 600;
    border: 1px solid var(--line);
    border-radius: 6px;
    background: #fff;
    color: #334155;
    cursor: pointer;
    transition: all 0.15s ease;
}
.quick-qty-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: var(--primary-soft);
}
.quick-qty-btn.use-all {
    color: #4f46e5;
    border-color: #c7d2fe;
    background: #eef2ff;
    font-weight: 700;
}

.field-hint { display: flex; align-items: center; gap: 0.35rem; width: 100%; margin-top: 0.3rem; font-size: 0.78rem; color: var(--ink-mute); line-height: 1.4; }
.delta-up { color: var(--ok); }
.delta-down { color: var(--danger); }
.text-ok { color: var(--ok); }
.text-warn { color: var(--warn); }

.form-alert { margin-bottom: 1rem; }

.cost-chip-btn {
    display: inline-flex;
    align-items: center;
    padding: 1px 7px;
    font-size: 11px;
    font-weight: 700;
    color: #4f46e5;
    background: rgba(79, 70, 229, 0.08);
    border: 1px solid rgba(79, 70, 229, 0.22);
    border-radius: 4px;
    cursor: pointer;
    margin-inline-start: 6px;
    transition: all 0.15s ease;
}
.cost-chip-btn:hover {
    background: rgba(79, 70, 229, 0.18);
    border-color: #4f46e5;
}

.drawer-footer { display: flex; justify-content: flex-end; gap: 0.75rem; }
</style>

<style>
/* Modal import result rendered by ElMessageBox */
.inventory-import-result { max-width: 460px; width: calc(100% - 32px); }
.inventory-import-result .import-summary { list-style: none; margin: 0; padding: 0; }
.inventory-import-result .import-summary li { display: flex; justify-content: space-between; padding: 0.3rem 0; border-bottom: 1px solid #f1f5f9; }
.inventory-import-result .import-note { margin: 0.75rem 0 0; font-size: 0.82rem; color: #64748b; }
.inventory-import-result .import-errors-title { margin: 0.9rem 0 0.35rem; font-weight: 700; color: #b91c1c; }
.inventory-import-result .import-errors { margin: 0; padding-inline-start: 1.1rem; max-height: 220px; overflow-y: auto; font-size: 0.82rem; }
</style>
