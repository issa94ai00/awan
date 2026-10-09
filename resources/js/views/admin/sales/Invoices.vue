<template>
    <div class="invoices-page">
        <AdminPageHeader
            icon="fas fa-file-invoice-dollar"
            :title="$t('invoices')"
            :subtitle="$t('inv_subtitle')"
        >
            <template #actions>
                <el-tooltip :content="$t('refresh')" placement="bottom" :enterable="false">
                    <el-button :icon="Refresh" :loading="store.loading" :aria-label="$t('refresh')" @click="fetchInvoices" />
                </el-tooltip>
                <el-button type="primary" :icon="Plus" @click="goToCreate">{{ $t('create_invoice') }}</el-button>
            </template>
        </AdminPageHeader>

        <!-- ── Money, over the whole search ── -->
        <AdminStatGrid :min="200">
            <el-card shadow="hover" class="stat-card">
                <div class="stat-inner">
                    <div class="stat-icon blue"><i class="fas fa-file-invoice"></i></div>
                    <div class="stat-details">
                        <h3>{{ formatCurrency(summary.billed) }}</h3>
                        <p>{{ $t('inv_billed') }}</p>
                        <span class="stat-sub">{{ $t('inv_invoices_n', { count: liveCount }) }}</span>
                    </div>
                </div>
            </el-card>

            <el-card shadow="hover" class="stat-card is-clickable" :class="{ 'is-active': filters.payment === 'paid' }" @click="setPayment('paid')">
                <div class="stat-inner">
                    <div class="stat-icon green"><i class="fas fa-sack-dollar"></i></div>
                    <div class="stat-details">
                        <h3>{{ formatCurrency(summary.collected) }}</h3>
                        <p>{{ $t('inv_collected') }}</p>
                        <div class="collect-bar" :aria-label="`${collectedShare}%`"><span :style="{ width: `${collectedShare}%` }" /></div>
                        <span class="stat-sub">{{ $t('inv_collected_share', { pct: collectedShare }) }}</span>
                    </div>
                </div>
            </el-card>

            <el-card shadow="hover" class="stat-card is-clickable" :class="{ 'is-active': filters.payment === 'due' }" @click="setPayment('due')">
                <div class="stat-inner">
                    <div class="stat-icon red"><i class="fas fa-hand-holding-dollar"></i></div>
                    <div class="stat-details">
                        <h3>{{ formatCurrency(summary.outstanding) }}</h3>
                        <p>{{ $t('outstanding_amount') }}</p>
                        <span class="stat-sub">{{ $t('inv_on_invoices_n', { count: summary.outstanding_count || 0 }) }}</span>
                    </div>
                </div>
            </el-card>

            <el-card
                v-if="summary.credit_count"
                shadow="hover"
                class="stat-card is-clickable"
                :class="{ 'is-active': filters.payment === 'credit' }"
                @click="setPayment('credit')"
            >
                <div class="stat-inner">
                    <div class="stat-icon purple"><i class="fas fa-rotate-left"></i></div>
                    <div class="stat-details">
                        <h3>{{ formatCurrency(summary.credit) }}</h3>
                        <p>{{ $t('inv_customer_credit') }}</p>
                        <span class="stat-sub">{{ $t('inv_overpaid_n', { count: summary.credit_count }) }}</span>
                    </div>
                </div>
            </el-card>
        </AdminStatGrid>

        <!-- ── How long the outstanding money has been owed ── -->
        <section v-if="summary.outstanding > 0" class="aging">
            <div class="aging-head">
                <strong>{{ $t('inv_aging_title') }}</strong>
                <span class="stat-sub">{{ $t('inv_aging_hint') }}</span>
            </div>
            <div class="aging-bar" role="group" :aria-label="$t('inv_aging_title')">
                <button
                    v-for="bucket in agingBuckets"
                    v-show="bucket.amount > 0"
                    :key="bucket.key"
                    type="button"
                    class="aging-part"
                    :class="[`a-${bucket.key}`, { 'is-on': bucket.olderThan && filters.older_than === bucket.olderThan }]"
                    :style="{ flexGrow: bucket.amount }"
                    :title="`${bucket.label}: ${formatCurrency(bucket.amount)}`"
                    :disabled="!bucket.olderThan"
                    @click="setOlderThan(bucket.olderThan)"
                />
            </div>
            <div class="aging-legend">
                <button
                    v-for="bucket in agingBuckets"
                    :key="bucket.key"
                    type="button"
                    class="aging-key"
                    :class="{ 'is-on': bucket.olderThan && filters.older_than === bucket.olderThan }"
                    :disabled="!bucket.olderThan"
                    @click="setOlderThan(bucket.olderThan)"
                >
                    <span class="dot" :class="`a-${bucket.key}`" />
                    {{ bucket.label }}
                    <strong>{{ formatCurrency(bucket.amount) }}</strong>
                </button>
            </div>
        </section>

        <section class="panel-card">
            <div class="status-tabs" role="tablist">
                <button
                    v-for="tab in statusTabs"
                    :key="tab.value"
                    type="button"
                    role="tab"
                    class="status-tab"
                    :class="{ 'is-on': filters.status === tab.value }"
                    :aria-selected="filters.status === tab.value"
                    @click="setStatus(tab.value)"
                >
                    {{ tab.label }}
                    <span class="status-tab-count">{{ tab.count }}</span>
                </button>
            </div>

            <div class="filters">
                <el-input
                    v-model="filters.search"
                    class="filter-search"
                    :placeholder="$t('inv_search_placeholder')"
                    :prefix-icon="Search"
                    clearable
                    @input="onSearchInput"
                />
                <el-select v-model="filters.payment" class="filter-select" :placeholder="$t('inv_any_payment')" clearable @change="applyFilters">
                    <el-option v-for="p in PAYMENT_FILTERS" :key="p" :value="p" :label="$t(`inv_pay_filter_${p}`)" />
                </el-select>
                <el-select v-model="filters.source" class="filter-select" :placeholder="$t('inv_any_source')" clearable @change="applyFilters">
                    <el-option value="direct" :label="$t('inv_source_direct')" />
                    <el-option value="order" :label="$t('inv_source_order')" />
                </el-select>
                <el-date-picker
                    v-model="filters.range"
                    type="daterange"
                    class="filter-dates"
                    value-format="YYYY-MM-DD"
                    format="YYYY-MM-DD"
                    unlink-panels
                    :start-placeholder="$t('pret_from')"
                    :end-placeholder="$t('pret_to')"
                    :shortcuts="dateShortcuts"
                    @change="applyFilters"
                />
                <el-button v-if="activeFilterCount" text type="primary" :icon="RefreshLeft" @click="resetFilters">
                    {{ $t('prod_admin_clear_filters', { count: activeFilterCount }) }}
                </el-button>
            </div>

            <el-result v-if="store.error && !store.invoices.length" icon="error" :title="store.error">
                <template #extra>
                    <el-button type="primary" :icon="Refresh" @click="fetchInvoices">{{ $t('cat_admin_retry') }}</el-button>
                </template>
            </el-result>

            <el-table
                v-else
                v-loading="store.loading"
                :data="store.invoices"
                row-key="id"
                style="width: 100%"
                class="invoices-table"
                :row-class-name="({ row }) => (row.status === 'cancelled' ? 'is-cancelled' : '')"
                :default-sort="{ prop: sort.prop, order: sort.order }"
                @sort-change="onSortChange"
                @row-click="(row, column) => column?.property !== 'actions' && openInvoice(row)"
            >
                <template #empty>
                    <el-empty v-if="!store.loading && (activeFilterCount || filters.status)" :description="$t('there_are_no_invoices_matching')" :image-size="90">
                        <el-button @click="resetFilters(true)">{{ $t('cat_admin_clear_filters') }}</el-button>
                    </el-empty>
                    <el-empty v-else-if="!store.loading" :description="$t('no_invoices_yet')" :image-size="90">
                        <el-button type="primary" :icon="Plus" @click="goToCreate">{{ $t('create_invoice') }}</el-button>
                    </el-empty>
                    <span v-else />
                </template>

                <el-table-column prop="invoice_number" :label="$t('invoice_number')" min-width="170" sortable="custom">
                    <template #default="{ row }">
                        <div class="cell-stack">
                            <span class="mono" dir="ltr">{{ row.invoice_number }}</span>
                            <span class="cell-secondary">
                                {{ formatDate(row.created_at) }}
                                <template v-if="row.sales_order">
                                    ·
                                    <button type="button" class="link-button" @click.stop="goToOrder(row.sales_order)">
                                        <span dir="ltr">{{ row.sales_order.order_number }}</span>
                                    </button>
                                </template>
                            </span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('client')" min-width="170">
                    <template #default="{ row }">
                        <div class="cell-stack">
                            <span class="strong">{{ customerName(row) }}</span>
                            <span v-if="row.customer_phone" class="cell-secondary" dir="ltr">{{ row.customer_phone }}</span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column prop="total" :label="$t('total')" min-width="120" align="right" sortable="custom">
                    <template #default="{ row }">
                        <div class="cell-stack align-end">
                            <strong class="amount">{{ formatCurrency(row.total) }}</strong>
                            <span class="cell-secondary">{{ $t('inv_items_n', { count: row.items_count ?? 0 }) }}</span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column prop="due" :label="$t('payment_status')" min-width="180" sortable="custom">
                    <template #default="{ row }">
                        <div v-if="row.payment_state" class="pay-cell">
                            <div class="pay-line">
                                <span class="pay-state" :class="`p-${row.payment_state}`">{{ $t(`inv_pay_${row.payment_state}`) }}</span>
                                <span v-if="row.payment_state === 'unpaid' || row.payment_state === 'partial'" class="amount due">
                                    {{ formatCurrency(row.outstanding) }}
                                </span>
                                <span v-else-if="row.payment_state === 'credit'" class="amount credit">
                                    +{{ formatCurrency(-row.outstanding) }}
                                </span>
                            </div>
                            <el-progress
                                :percentage="paidPercentage(row)"
                                :status="paidPercentage(row) >= 100 ? 'success' : undefined"
                                :stroke-width="5"
                                :show-text="false"
                            />
                            <span v-if="row.outstanding > 0.009 && row.age_days >= 30" class="age-note" :class="{ bad: row.age_days >= 60 }">
                                {{ $t('inv_owed_days', { count: row.age_days }) }}
                            </span>
                        </div>
                        <span v-else class="cell-secondary">—</span>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('status')" min-width="140">
                    <template #default="{ row }">
                        <el-dropdown
                            v-if="forwardMoves(row).length"
                            trigger="click"
                            @command="(status) => changeStatus(row, status)"
                            @click.stop
                        >
                            <button type="button" class="status-pill clickable" :class="`s-${row.status}`" @click.stop>
                                <i class="fas" :class="statusIcon(row.status)"></i>
                                {{ statusLabel(row.status) }}
                                <i class="fas fa-chevron-down chevron"></i>
                            </button>
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item v-for="status in forwardMoves(row)" :key="status" :command="status">
                                        <i class="fas" :class="statusIcon(status)"></i> {{ $t('inv_move_to', { status: statusLabel(status) }) }}
                                    </el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>
                        <span v-else class="status-pill" :class="`s-${row.status}`" :title="row.sales_order ? $t('inv_order_drives_status') : ''">
                            <i class="fas" :class="statusIcon(row.status)"></i>
                            {{ statusLabel(row.status) }}
                        </span>
                    </template>
                </el-table-column>

                <el-table-column prop="actions" :label="$t('actions')" min-width="195" align="center">
                    <template #default="{ row }">
                        <div class="row-actions" @click.stop>
                            <el-button
                                v-if="canRecordPayment(row)"
                                size="small"
                                type="success"
                                plain
                                @click="openPaymentDialog(row)"
                            >
                                <i class="fas fa-money-bill-wave"></i>&nbsp;{{ $t('inv_collect') }}
                            </el-button>
                            <el-tooltip :content="$t('print') || 'طباعة'" placement="top" :enterable="false">
                                <el-button
                                    size="small"
                                    type="primary"
                                    plain
                                    @click="openPrintDialog(row)"
                                >
                                    <i class="fas fa-print"></i>
                                </el-button>
                            </el-tooltip>
                            <el-dropdown trigger="click" @command="(cmd) => runCommand(row, cmd)">
                                <el-button size="small" text circle :aria-label="$t('inv_more_actions')">
                                    <i class="fas fa-ellipsis-vertical"></i>
                                </el-button>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item command="print">
                                            <i class="fas fa-print"></i> {{ $t('print') || 'طباعة الفاتورة' }}
                                        </el-dropdown-item>
                                        <el-dropdown-item command="open">
                                            <i class="fas" :class="row.status === 'cancelled' ? 'fa-eye' : 'fa-pen'"></i>
                                            {{ row.status === 'cancelled' ? $t('view_details') : $t('edit') }}
                                        </el-dropdown-item>
                                        <el-dropdown-item v-if="row.sales_order" command="order">
                                            <i class="fas fa-cart-shopping"></i> {{ $t('inv_open_order') }}
                                        </el-dropdown-item>
                                        <el-dropdown-item command="payments">
                                            <i class="fas fa-receipt"></i> {{ $t('inv_view_payments') }}
                                        </el-dropdown-item>
                                        <el-dropdown-item v-if="canCancel(row)" command="cancel" divided class="danger-item">
                                            <i class="fas fa-ban"></i> {{ $t('inv_cancel') }}
                                        </el-dropdown-item>
                                        <el-dropdown-item v-if="row.status === 'cancelled'" command="delete" divided class="danger-item">
                                            <i class="fas fa-trash"></i> {{ $t('delete') }}
                                        </el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                        </div>
                    </template>
                </el-table-column>
            </el-table>

            <div v-if="store.pagination.total > 0" class="pagination-row">
                <span class="cell-secondary">
                    {{ $t('prod_admin_range', { from: rangeFrom, to: rangeTo, total: store.pagination.total }) }}
                </span>
                <el-pagination
                    v-model:current-page="currentPage"
                    v-model:page-size="pageSize"
                    :total="store.pagination.total"
                    :page-sizes="[15, 30, 50, 100]"
                    :layout="isNarrow ? 'prev, pager, next' : 'sizes, prev, pager, next'"
                    background
                    @size-change="onPageChange(true)"
                    @current-change="onPageChange(false)"
                />
            </div>
        </section>

        <QuickPaymentDialog
            v-model="paymentDialogVisible"
            :invoice="paymentTarget"
            @saved="onPaymentSaved"
        />

        <!-- Invoice Print Preview Dialog (A4 Simulation & Isolated Iframe Print) -->
        <el-dialog
            v-model="printInvoiceDialogVisible"
            :width="isFullScreenPreview ? '100%' : '1040px'"
            :fullscreen="isFullScreenPreview"
            class="inv-print-dialog"
            destroy-on-close
            append-to-body
            :top="isFullScreenPreview ? '0' : '2vh'"
        >
            <template #header>
                <div class="inv-dialog-header-custom inv-screen-only">
                    <div class="inv-dh-left">
                        <div class="inv-dh-icon">
                            <i class="fas fa-print"></i>
                        </div>
                        <div class="inv-dh-info">
                            <div class="inv-dh-main-line">
                                <span class="inv-dh-title-text">{{ $t('inv_print_preview') || 'معاينة وطباعة الفاتورة' }}</span>
                                <strong v-if="printInvoiceData" class="inv-dh-num" dir="ltr">{{ printInvoiceData.invoice_number }}</strong>
                            </div>
                            <div class="inv-dh-subline" v-if="printInvoiceData">
                                <span><i class="fas fa-calendar-day"></i> {{ formatDate(printInvoiceData.created_at) }}</span>
                                <span v-if="printInvoiceData.customer_name" class="inv-dh-customer-crumb">
                                    <i class="fas fa-user"></i> {{ printInvoiceData.customer_name }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div v-if="printInvoiceData" class="inv-dh-badges">
                        <span class="inv-status-pill" :class="`pill-${printInvoiceData.status}`">
                            <i class="fas" :class="statusIcon(printInvoiceData.status)"></i>
                            {{ statusLabel(printInvoiceData.status) }}
                        </span>
                        <span class="inv-pay-pill-dh" :class="`pill-pay-${printInvoiceData.payment_state || 'unpaid'}`">
                            {{ paymentStateLabel(printInvoiceData.payment_state) }}
                        </span>
                        <span class="inv-dh-count">
                            <i class="fas fa-box-open"></i> {{ printItemsCount }} {{ $t('items') || 'أصناف' }} ({{ printTotalQuantity }} قطعة)
                        </span>
                        <span class="inv-dh-pages-est">
                            <i class="fas fa-file-lines"></i> {{ estimatedPages === 1 ? 'صفحة A4 واحدة' : `${estimatedPages} صفحات A4 تقريباً` }}
                        </span>
                    </div>

                    <div class="inv-dh-actions">
                        <el-button
                            size="small"
                            class="inv-fs-toggle-btn"
                            :type="isFullScreenPreview ? 'primary' : 'default'"
                            plain
                            @click="isFullScreenPreview = !isFullScreenPreview"
                        >
                            <i class="fas" :class="isFullScreenPreview ? 'fa-compress' : 'fa-expand'"></i>
                            <span>{{ isFullScreenPreview ? ($t('exit_fullscreen') || 'تصغير') : ($t('fullscreen') || 'ملء الشاشة') }}</span>
                        </el-button>
                    </div>
                </div>
            </template>

            <!-- Print Settings Toolbar (Screen Only) -->
            <PrintSettingsToolbar
                v-model="printSettings"
                :zoom-level="zoomLevel"
                :is-exporting-pdf="isExportingPdf"
                @apply-preset="applyPreset"
                @save-default="saveAsDefault"
                @reset-default="resetToDefaults"
                @zoom-in="zoomIn"
                @zoom-out="zoomOut"
                @reset-zoom="resetZoom"
                @export-pdf="exportInvoicePdf"
                @print-now="triggerPrintInvoice"
            />

            <!-- Paper simulation stage with Zoom Container -->
            <div
                class="inv-paper-stage"
                :class="{ 'is-fullscreen': isFullScreenPreview }"
                v-loading="printInvoiceLoading"
                :element-loading-text="$t('loading') || 'جاري تجهيز الفاتورة للطباعة...'"
            >
                <div class="inv-paper-viewport" :style="zoomContainerStyle">
                    <div
                        v-if="printInvoiceData"
                        id="invoice-printable-doc"
                        class="inv-printable-sheet"
                        :class="[
                            `theme-${printSettings.theme}`,
                            { 'density-compact': printSettings.density === 'compact' },
                            { 'has-cover': printSettings.showCover }
                        ]"
                    >
                        <!-- Optional Full-page Cover with edge-to-edge fulfill -->
                        <div v-if="printSettings.showCover" class="inv-print-cover">
                            <img :src="coverImageUrl" alt="Invoice Cover" />
                        </div>

                        <!-- Main Printable Invoice Document Sheet -->
                        <div class="inv-document-body">
                            <!-- Diagonal Watermark Overlay if selected -->
                            <div v-if="printSettings.watermark && watermarkText" class="inv-sheet-watermark" :class="`wm-${printSettings.watermark}`">
                                <span>{{ watermarkText }}</span>
                            </div>

                            <!-- Official Print Header with Logo & Brand Details -->
                            <PrintDocumentHeader
                                :header-style="printSettings.headerStyle"
                                :show-logo="printSettings.showLogo"
                                :show-contacts="printSettings.showContacts"
                                :show-meta="false"
                                :title="$t('official_tax_invoice') || 'فاتورة ضريبية رسمية'"
                                :subtitle="'OFFICIAL TAX INVOICE'"
                                :document-number="printInvoiceData.invoice_number"
                                :banner-src="headerBannerUrl"
                            />

                            <!-- Document Executive Ribbon / Status, QR & Reference Bar -->
                            <div class="inv-doc-ribbon">
                                <div class="inv-ribbon-left">
                                    <span class="inv-ribbon-doc-type">
                                        <i class="fas fa-file-invoice-dollar"></i>
                                        {{ $t('official_tax_invoice') || 'فاتورة ضريبية معتمدة' }}
                                    </span>
                                    <span class="inv-ribbon-status" :class="`status-${printInvoiceData.status}`">
                                        <i class="fas" :class="statusIcon(printInvoiceData.status)"></i>
                                        {{ statusLabel(printInvoiceData.status) }}
                                    </span>
                                    <span class="inv-ribbon-pay" :class="`pay-${printInvoiceData.payment_state || 'unpaid'}`">
                                        <i class="fas fa-coins"></i>
                                        {{ paymentStateLabel(printInvoiceData.payment_state) }}
                                    </span>
                                    <span v-if="printInvoiceData.sales_order" class="inv-ribbon-order">
                                        <i class="fas fa-cart-shopping"></i>
                                        {{ $t('sales_order') || 'أمر البيع' }}: <strong dir="ltr">{{ printInvoiceData.sales_order.order_number }}</strong>
                                    </span>
                                </div>

                                <div class="inv-ribbon-right">
                                    <div class="inv-ribbon-item">
                                        <span class="lbl"><i class="fas fa-calendar-day"></i> {{ $t('invoice_date') || 'تاريخ الفاتورة' }}:</span>
                                        <strong class="val" dir="ltr">{{ formatDate(printInvoiceData.created_at) }}</strong>
                                    </div>
                                    <div v-if="printInvoiceData.due_date" class="inv-ribbon-item">
                                        <span class="lbl"><i class="fas fa-clock"></i> {{ $t('due_date') || 'تاريخ الاستحقاق' }}:</span>
                                        <strong class="val" dir="ltr">{{ formatDate(printInvoiceData.due_date) }}</strong>
                                    </div>
                                    <!-- Electronic QR Verification Badge in ribbon -->
                                    <div v-if="printSettings.showQrCode && qrCodeDataUrl" class="inv-ribbon-qr" :title="$t('qr_verification_label') || 'تحقق إلكتروني معتمد'">
                                        <img :src="qrCodeDataUrl" alt="QR" class="inv-ribbon-qr-img" />
                                        <span class="inv-ribbon-qr-label">{{ $t('qr_verification_label') || 'تحقق إلكتروني' }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Customer & Invoice Metadata Grid -->
                            <div v-if="printSettings.showCustomerInfo" class="inv-print-meta-grid">
                                <!-- Customer Info Box -->
                                <div class="inv-meta-card inv-meta-customer">
                                    <div class="inv-meta-card-header">
                                        <div class="inv-card-header-icon"><i class="fas fa-user-tie"></i></div>
                                        <span class="inv-meta-card-title">{{ $t('customer_info') || 'بيانات العميل والمكلف' }}</span>
                                    </div>
                                    <div class="inv-meta-card-body">
                                        <div class="inv-customer-primary">
                                            <strong class="inv-customer-name">{{ printInvoiceData.customer_name || 'عميل عام' }}</strong>
                                            <span v-if="printInvoiceData.customer_company" class="inv-customer-company">
                                                ({{ printInvoiceData.customer_company }})
                                            </span>
                                        </div>
                                        <div class="inv-customer-meta-list">
                                            <div v-if="printInvoiceData.customer_phone" class="inv-cm-item">
                                                <i class="fas fa-phone-alt"></i>
                                                <span dir="ltr">{{ printInvoiceData.customer_phone }}</span>
                                            </div>
                                            <div v-if="printInvoiceData.customer_email" class="inv-cm-item">
                                                <i class="fas fa-envelope"></i>
                                                <span dir="ltr">{{ printInvoiceData.customer_email }}</span>
                                            </div>
                                            <div v-if="printInvoiceData.customer_address || printInvoiceData.customer_city" class="inv-cm-item">
                                                <i class="fas fa-location-dot"></i>
                                                <span>{{ printInvoiceData.customer_address || [printInvoiceData.customer_city, printInvoiceData.customer_state, printInvoiceData.customer_country].filter(Boolean).join(', ') }}</span>
                                            </div>
                                            <div v-if="printInvoiceData.customer_tax_number" class="inv-cm-item inv-cm-tax">
                                                <i class="fas fa-certificate"></i>
                                                <span>{{ $t('tax_number') || 'الرقم الضريبي' }}: <strong>{{ printInvoiceData.customer_tax_number }}</strong></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Invoice Details & Payment Box -->
                                <div class="inv-meta-card inv-meta-details">
                                    <div class="inv-meta-card-header">
                                        <div class="inv-card-header-icon"><i class="fas fa-receipt"></i></div>
                                        <span class="inv-meta-card-title">{{ $t('invoice_details') || 'بيانات الفاتورة والتحصيل' }}</span>
                                    </div>
                                    <div class="inv-meta-card-body">
                                        <div class="inv-details-meta-grid">
                                            <div class="inv-dm-item">
                                                <span class="inv-dm-label">{{ $t('invoice_number') || 'رقم الفاتورة' }}:</span>
                                                <strong class="inv-dm-val inv-mono" dir="ltr">{{ printInvoiceData.invoice_number }}</strong>
                                            </div>
                                            <div class="inv-dm-item">
                                                <span class="inv-dm-label">{{ $t('payment_method') || 'طريقة الدفع' }}:</span>
                                                <span class="inv-dm-val inv-pay-method-pill">
                                                    <i class="fas fa-wallet"></i> {{ printInvoiceData.payment_method_label || printInvoiceData.payment_method || '—' }}
                                                </span>
                                            </div>
                                            <div class="inv-dm-item">
                                                <span class="inv-dm-label">{{ $t('payment_state') || 'حالة السداد' }}:</span>
                                                <span class="inv-dm-val inv-state-pill" :class="`p-${printInvoiceData.payment_state || 'unpaid'}`">
                                                    {{ paymentStateLabel(printInvoiceData.payment_state) }}
                                                </span>
                                            </div>
                                            <div v-if="printInvoiceData.warehouse" class="inv-dm-item">
                                                <span class="inv-dm-label">{{ $t('warehouse') || 'مستودع الصرف' }}:</span>
                                                <span class="inv-dm-val"><i class="fas fa-warehouse text-muted"></i> {{ printInvoiceData.warehouse.name }}</span>
                                            </div>
                                            <div v-if="printInvoiceData.assigned_employee || printInvoiceData.user" class="inv-dm-item">
                                                <span class="inv-dm-label">{{ $t('sales_officer') || 'الموظف المسؤول' }}:</span>
                                                <span class="inv-dm-val">{{ printInvoiceData.assigned_employee?.name || printInvoiceData.user?.name }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Line Items Table -->
                            <table class="inv-print-table">
                                <thead>
                                    <tr>
                                        <th style="width: 34px; text-align: center;">#</th>
                                        <th v-if="printSettings.showImages" style="width: 48px; text-align: center;">{{ $t('image') || 'الصورة' }}</th>
                                        <th>{{ $t('product') || 'المنتج / البيان والتفاصيل' }}</th>
                                        <th v-if="printSettings.showSku" style="width: 105px;">{{ $t('sku') || 'الرمز / SKU' }}</th>
                                        <th style="width: 80px; text-align: center;">{{ $t('quantity') || 'الكمية' }}</th>
                                        <th style="width: 110px; text-align: left;">{{ $t('unit_price') || 'السعر الإفرادي' }}</th>
                                        <th v-if="hasItemDiscount" style="width: 85px; text-align: left;">{{ $t('discount') || 'الخصم' }}</th>
                                        <th v-if="hasItemTax" style="width: 85px; text-align: left;">{{ $t('tax') || 'الضريبة' }}</th>
                                        <th style="width: 125px; text-align: left;">{{ $t('total') || 'الإجمالي' }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(item, idx) in (printInvoiceData.items || [])" :key="item.id || idx">
                                        <td style="text-align: center;" class="inv-row-idx">{{ idx + 1 }}</td>
                                        <td v-if="printSettings.showImages" class="inv-print-img-cell" style="text-align: center;">
                                            <EntityImage
                                                :src="item.product?.image_main || item.product?.image || item.product?.image_url"
                                                type="product"
                                                :size="printSettings.density === 'compact' ? 30 : 38"
                                                shape="square"
                                                :lazy="false"
                                            />
                                        </td>
                                        <td>
                                            <div class="inv-item-name">{{ item.product_name || item.product?.name_ar || item.product?.name || item.notes || '-' }}</div>
                                            <div v-if="item.product?.name_en" class="inv-item-name-en">{{ item.product.name_en }}</div>
                                            <div v-if="item.product_variant_id && item.variant" class="inv-item-variant">
                                                <VariantChip :label="variantLabelOf(item.variant) || item.notes" />
                                            </div>
                                            <div v-else-if="item.notes && item.notes !== item.product_name" class="inv-item-desc">
                                                {{ item.notes }}
                                            </div>
                                        </td>
                                        <td v-if="printSettings.showSku" class="inv-sku-cell">
                                            <code>{{ item.variant?.sku || item.product?.sku || '—' }}</code>
                                        </td>
                                        <td style="text-align: center;" class="inv-qty-cell">
                                            <span class="inv-qty-num">{{ item.quantity }}</span>
                                            <span v-if="item.unit_name || item.product_unit?.name" class="inv-unit-label">
                                                {{ item.unit_name || item.product_unit?.name }}
                                            </span>
                                        </td>
                                        <td style="text-align: left;" class="inv-price-cell" dir="ltr">
                                            {{ formatCurrency(item.unit_price) }}
                                        </td>
                                        <td v-if="hasItemDiscount" style="text-align: left;" class="inv-discount-cell" dir="ltr">
                                            {{ toNum(item.discount) ? `− ${formatCurrency(item.discount)}` : '—' }}
                                        </td>
                                        <td v-if="hasItemTax" style="text-align: left;" class="inv-tax-cell" dir="ltr">
                                            {{ toNum(item.tax_amount) ? formatCurrency(item.tax_amount) : '—' }}
                                        </td>
                                        <td style="text-align: left;" class="inv-total-cell" dir="ltr">
                                            <strong>{{ formatCurrency(lineTotal(item)) }}</strong>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="inv-table-summary-row">
                                        <td :colspan="(printSettings.showImages ? 1 : 0) + (printSettings.showSku ? 1 : 0) + 3" class="inv-sum-label">
                                            <i class="fas fa-layer-group text-primary"></i>
                                            <span>{{ $t('summary') || 'إجمالي بنود الفاتورة' }}:</span>
                                            <strong class="inv-sum-count">{{ printItemsCount }}</strong> {{ $t('items') || 'أصناف' }}
                                            ·
                                            <span>{{ $t('total_quantity') || 'مجموع الكميات' }}:</span>
                                            <strong class="inv-sum-count">{{ printTotalQuantity }}</strong>
                                        </td>
                                        <td :colspan="(hasItemDiscount ? 1 : 0) + (hasItemTax ? 1 : 0) + 2" class="inv-sum-total" dir="ltr">
                                            <span class="inv-sum-subtext">{{ $t('subtotal') }}:</span>
                                            <strong>{{ formatCurrency(printInvoiceData.subtotal ?? calcPrintSubtotal(printInvoiceData)) }}</strong>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>

                            <!-- Executive Closing Section: Totals, Notes, Signatures & Footer -->
                            <div class="inv-print-closing-section">
                                <!-- Notes & Expenses Block -->
                                <div class="inv-print-summary-row">
                                    <div class="inv-print-notes-col">
                                        <div v-if="printSettings.showNotes && printInvoiceData.notes" class="inv-print-notes-box">
                                            <div class="inv-notes-header">
                                                <i class="fas fa-clipboard-list"></i>
                                                <strong>{{ $t('notes') || 'ملاحظات وشروط الفاتورة' }}:</strong>
                                            </div>
                                            <p class="inv-notes-text">{{ printInvoiceData.notes }}</p>
                                        </div>

                                        <div v-if="printInvoiceData.expenses && printInvoiceData.expenses.length" class="inv-print-expenses-box">
                                            <div class="inv-expenses-header">
                                                <i class="fas fa-hand-holding-dollar"></i>
                                                <strong>{{ $t('additional_charges') || 'المصاريف والرسوم الإضافية' }}:</strong>
                                            </div>
                                            <div class="inv-expenses-list">
                                                <div v-for="exp in printInvoiceData.expenses" :key="exp.id" class="inv-exp-row">
                                                    <span>{{ exp.category_label || exp.description }}:</span>
                                                    <strong dir="ltr">{{ formatCurrency(exp.amount) }}</strong>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="inv-print-legal-notice">
                                            <i class="fas fa-circle-info"></i>
                                            <span>{{ printSettings.customNotes || $t('inv_print_legal_notice') || 'فاتورة ضريبية رسمية صادرة بموجب الأنظمة المعتمدة لدى شركة أوان التقدم للتجهيزات الصحية ومواد البناء. تخضع جميع المواد لشروط الضمان وسياسة الاسترجاع.' }}</span>
                                        </div>
                                    </div>

                                    <!-- Commercial Financial Totals Card -->
                                    <div class="inv-print-totals-col">
                                        <div class="inv-totals-card">
                                            <div class="inv-totals-header">
                                                <i class="fas fa-coins text-primary"></i>
                                                <strong>{{ $t('financial_summary') || 'الملخص المالي النهائي' }}</strong>
                                            </div>
                                            <table class="inv-totals-table">
                                                <tr>
                                                    <td>{{ $t('items_subtotal') || 'المجموع الفرعي الخاضع للضريبة' }}:</td>
                                                    <td class="val" dir="ltr">{{ formatCurrency(printInvoiceData.subtotal ?? calcPrintSubtotal(printInvoiceData)) }}</td>
                                                </tr>
                                                <tr v-if="toNum(printInvoiceData.discount) > 0">
                                                    <td>{{ $t('less_discount') || 'الخصم التجاري' }}:</td>
                                                    <td class="val discount-val" dir="ltr">− {{ formatCurrency(printInvoiceData.discount) }}</td>
                                                </tr>
                                                <tr v-if="toNum(printInvoiceData.tax) > 0">
                                                    <td>{{ $t('plus_tax') || 'ضريبة القيمة المضافة' }}:</td>
                                                    <td class="val" dir="ltr">{{ formatCurrency(printInvoiceData.tax) }}</td>
                                                </tr>
                                                <tr v-if="toNum(printInvoiceData.additional_charges) > 0">
                                                    <td>{{ $t('additional_charges') || 'رسوم وخدمات إضافية' }}:</td>
                                                    <td class="val" dir="ltr">{{ formatCurrency(printInvoiceData.additional_charges) }}</td>
                                                </tr>
                                                <tr class="grand-total-row">
                                                    <td>{{ $t('grand_total_amount') || 'المجموع الإجمالي النهائي' }}:</td>
                                                    <td class="val grand-val" dir="ltr">{{ formatCurrency(printInvoiceData.total) }}</td>
                                                </tr>
                                                <tr class="paid-row">
                                                    <td>{{ $t('paid_amount') || 'المبلغ المسدد / المحصل' }}:</td>
                                                    <td class="val paid-val" dir="ltr">{{ formatCurrency(printInvoiceData.paid_amount) }}</td>
                                                </tr>
                                                <tr class="due-row" :class="{ 'has-due': toNum(printInvoiceData.due_amount) > 0.009 }">
                                                    <td>{{ $t('due_amount') || 'الرصيد المتبقي المستحق' }}:</td>
                                                    <td class="val due-val" dir="ltr">{{ formatCurrency(printInvoiceData.due_amount) }}</td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <!-- Official Signatures Block -->
                                <div v-if="printSettings.showSignatures" class="inv-print-signatures">
                                    <div class="sig-card">
                                        <span class="sig-title"><i class="fas fa-pen-nib"></i> {{ $t('prepared_by') || 'منظم الفاتورة' }}</span>
                                        <div class="sig-name" v-if="printInvoiceData.assigned_employee?.name || printInvoiceData.user?.name">
                                            {{ printInvoiceData.assigned_employee?.name || printInvoiceData.user?.name }}
                                        </div>
                                        <div class="sig-line"></div>
                                        <span class="sig-sub">{{ $t('signature_and_date') || 'التوقيع والتاريخ' }}</span>
                                    </div>
                                    <div class="sig-card">
                                        <span class="sig-title"><i class="fas fa-calculator"></i> {{ $t('finance_audit') || 'الحسابات والتدقيق' }}</span>
                                        <div class="sig-line"></div>
                                        <span class="sig-sub">{{ $t('signature_and_date') || 'التوقيع والتاريخ' }}</span>
                                    </div>
                                    <div class="sig-card">
                                        <span class="sig-title"><i class="fas fa-user-check"></i> {{ $t('customer_acceptance') || 'استلام وقبول العميل' }}</span>
                                        <div class="sig-line"></div>
                                        <span class="sig-sub">{{ $t('signature_and_date') || 'التوقيع والتاريخ' }}</span>
                                    </div>
                                    <div class="sig-card">
                                        <span class="sig-title"><i class="fas fa-stamp"></i> {{ $t('management_approval') || 'اعتماد الإدارة والختم' }}</span>
                                        <div v-if="printSettings.authorizedPerson" class="sig-name">
                                            {{ printSettings.authorizedPerson }}
                                            <small v-if="printSettings.authorizedTitle" style="display: block; font-size: 9px; color: #64748b;">{{ printSettings.authorizedTitle }}</small>
                                        </div>
                                        <div class="sig-seal-box" :class="{ 'has-stamp': !!stampImageUrl }">
                                            <img v-if="stampImageUrl" :src="stampImageUrl" alt="Official Seal" class="sig-stamp-img" />
                                            <span v-else>الختم الرسمي</span>
                                        </div>
                                        <img v-if="signatureImageUrl" :src="signatureImageUrl" alt="Authorized Signature" class="sig-digital-img" />
                                    </div>
                                </div>

                                <!-- Running Footer -->
                                <div v-if="printSettings.showFooter" class="inv-print-footer">
                                    <div class="inv-footer-brand">
                                        <strong>شركة أوان التقدم للتجهيزات الصحية ومواد البناء</strong> — دمشق، الجمهورية العربية السورية
                                    </div>
                                    <div class="inv-footer-meta">
                                        <span>{{ printFormattedDate }}</span>
                                        <span>·</span>
                                        <span>A4 Official Tax Invoice</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </el-dialog>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Printer, Refresh, RefreshLeft, Search } from '@element-plus/icons-vue';
import { useInvoicesStore } from '@/stores/invoices';
import QuickPaymentDialog from '@/components/admin/sales/QuickPaymentDialog.vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';
import PrintDocumentHeader from '@/components/admin/PrintDocumentHeader.vue';
import PrintSettingsToolbar from '@/components/admin/PrintSettingsToolbar.vue';
import { usePrintSettings } from '@/composables/usePrintSettings';
import EntityImage from '@/components/admin/EntityImage.vue';
import VariantChip from '@/components/admin/products/VariantChip.vue';
import { invoicesApi } from '@/api/invoices';
import { variantLabelOf } from '@/utils/productPick';
import QRCode from 'qrcode';
import jsPDF from 'jspdf';
import html2canvas from 'html2canvas';
import {
    ORDER_STATUSES,
    apiErrorMessage,
    customerName,
    formatCurrency,
    formatDate,
    invoicePaidPercentage,
    localIsoDate,
    statusIcon,
    statusLabel,
} from '@/utils/sales';

const { t } = useI18n();
const router = useRouter();
const route = useRoute();
const store = useInvoicesStore();

const PAYMENT_FILTERS = ['due', 'unpaid', 'partial', 'paid', 'credit'];

const goToCreate = () => router.push('/admin/sales/invoices/create');
const goToOrder = (order) => router.push({ path: '/admin/sales/sales-orders', query: { open: order.id } });
const openInvoice = (invoice) => router.push(`/admin/sales/invoices/${invoice.id}/edit`);

// ── Summary ──────────────────────────────────────────────────────────────
const summary = computed(() => store.summary || {
    total: 0, by_status: {}, billed: 0, collected: 0, outstanding: 0, outstanding_count: 0,
    aging: {}, credit: 0, credit_count: 0,
});
const liveCount = computed(() => (summary.value.total || 0) - (summary.value.by_status?.cancelled || 0));
const collectedShare = computed(() => (summary.value.billed > 0
    ? Math.min(100, Math.round((summary.value.collected / summary.value.billed) * 100))
    : 0));

const agingBuckets = computed(() => {
    const a = summary.value.aging || {};
    return [
        { key: '0_30', label: t('inv_age_0_30'), amount: a['0_30'] || 0, olderThan: null },
        { key: '31_60', label: t('inv_age_31_60'), amount: a['31_60'] || 0, olderThan: 30 },
        { key: '61_90', label: t('inv_age_61_90'), amount: a['61_90'] || 0, olderThan: 60 },
        { key: '90_plus', label: t('inv_age_90_plus'), amount: a['90_plus'] || 0, olderThan: 90 },
    ];
});

const statusTabs = computed(() => [
    { value: '', label: t('all'), count: summary.value.total || 0 },
    ...ORDER_STATUSES
        .filter((status) => (summary.value.by_status?.[status] || 0) > 0 || status === 'pending')
        .map((status) => ({ value: status, label: statusLabel(status), count: summary.value.by_status?.[status] || 0 })),
]);

// ── Filters, sorting and paging, kept in the URL ─────────────────────────
const blankFilters = () => ({ search: '', status: '', payment: '', source: '', range: null, older_than: null });
const filters = reactive(blankFilters());
const sort = reactive({ prop: 'created_at', order: 'descending' });
const currentPage = ref(1);
const pageSize = ref(15);

const readQuery = () => {
    const q = route.query;
    Object.assign(filters, {
        // Arriving from a sales order, a journal entry or the dashboard: the
        // caller names the invoice it means with ?invoice=.
        search: q.search ? String(q.search) : (q.invoice ? String(q.invoice) : ''),
        status: ORDER_STATUSES.includes(q.status) ? q.status : '',
        payment: PAYMENT_FILTERS.includes(q.payment) ? q.payment : '',
        source: ['direct', 'order'].includes(q.source) ? q.source : '',
        range: q.from && q.to ? [String(q.from), String(q.to)] : null,
        older_than: [30, 60, 90].includes(Number(q.older_than)) ? Number(q.older_than) : null,
    });
    sort.prop = ['total', 'invoice_number', 'due'].includes(q.sort) ? q.sort : 'created_at';
    sort.order = q.direction === 'asc' ? 'ascending' : 'descending';
    currentPage.value = Math.max(1, Number(q.page) || 1);
    pageSize.value = [15, 30, 50, 100].includes(Number(q.per_page)) ? Number(q.per_page) : 15;
};

const queryKey = (query) => Object.entries(query).map(([k, v]) => `${k}=${v}`).sort().join('&');
let lastQueryKey = null;

const writeQuery = () => {
    const query = {
        search: filters.search || undefined,
        status: filters.status || undefined,
        payment: filters.payment || undefined,
        source: filters.source || undefined,
        from: filters.range?.[0] || undefined,
        to: filters.range?.[1] || undefined,
        older_than: filters.older_than || undefined,
        sort: sort.prop !== 'created_at' ? sort.prop : undefined,
        direction: sort.order === 'ascending' ? 'asc' : undefined,
        page: currentPage.value > 1 ? currentPage.value : undefined,
        per_page: pageSize.value !== 15 ? pageSize.value : undefined,
    };
    Object.keys(query).forEach((k) => query[k] === undefined && delete query[k]);
    lastQueryKey = queryKey(query);
    router.replace({ query });
};

const activeFilterCount = computed(() => [
    filters.search, filters.payment, filters.source, filters.range?.length ? '1' : '', filters.older_than,
].filter(Boolean).length);

const fetchInvoices = () => store.fetchInvoices({
    lean: 1,
    with_summary: 1,
    page: currentPage.value,
    per_page: pageSize.value,
    search: filters.search.trim() || undefined,
    status: filters.status || undefined,
    payment: filters.payment || undefined,
    source: filters.source || undefined,
    date_from: filters.range?.[0] || undefined,
    date_to: filters.range?.[1] || undefined,
    older_than: filters.older_than || undefined,
    sort: sort.prop,
    direction: sort.order === 'ascending' ? 'asc' : 'desc',
}).catch(() => {});

const applyFilters = () => {
    clearTimeout(searchTimer);
    currentPage.value = 1;
    writeQuery();
    fetchInvoices();
};

let searchTimer = null;
const onSearchInput = (text) => {
    clearTimeout(searchTimer);
    if (!text) applyFilters();
    else searchTimer = setTimeout(applyFilters, 400);
};

const resetFilters = (withStatus = false) => {
    const status = filters.status;
    Object.assign(filters, blankFilters());
    if (withStatus !== true) filters.status = status;
    applyFilters();
};

const setStatus = (status) => {
    filters.status = filters.status === status ? '' : status;
    applyFilters();
};

const setPayment = (payment) => {
    filters.payment = filters.payment === payment ? '' : payment;
    filters.older_than = null;
    applyFilters();
};

const setOlderThan = (days) => {
    if (!days) return;
    filters.older_than = filters.older_than === days ? null : days;
    applyFilters();
};

const onSortChange = ({ prop, order }) => {
    sort.prop = order ? prop : 'created_at';
    sort.order = order || 'descending';
    currentPage.value = 1;
    writeQuery();
    fetchInvoices();
};

const onPageChange = (sizeChanged) => {
    if (sizeChanged) currentPage.value = 1;
    writeQuery();
    fetchInvoices();
};

const rangeFrom = computed(() => (store.pagination.total ? (currentPage.value - 1) * pageSize.value + 1 : 0));
const rangeTo = computed(() => Math.min(currentPage.value * pageSize.value, store.pagination.total));

const dateShortcuts = computed(() => {
    const now = new Date();
    const at = (y, m, d) => localIsoDate(new Date(y, m, d));
    const y = now.getFullYear();
    const m = now.getMonth();
    return [
        { text: t('pret_this_month'), value: () => [at(y, m, 1), localIsoDate(now)] },
        { text: t('pret_last_month'), value: () => [at(y, m - 1, 1), at(y, m, 0)] },
        { text: t('pret_last_90_days'), value: () => [at(y, m, now.getDate() - 90), localIsoDate(now)] },
        { text: t('pret_this_year'), value: () => [at(y, 0, 1), localIsoDate(now)] },
    ];
});

// ── Row state ────────────────────────────────────────────────────────────
const paidPercentage = invoicePaidPercentage;

const canRecordPayment = (invoice) => invoice.status !== 'cancelled' && Number(invoice.outstanding) > 0.009;

// Moves along the stages from the status pill; cancelling is its own,
// clearly-worded menu item because of what it undoes.
const forwardMoves = (invoice) => (invoice.allowed_statuses || []).filter((s) => s !== 'cancelled');
const canCancel = (invoice) => (invoice.allowed_statuses || []).includes('cancelled');

// ── Actions ──────────────────────────────────────────────────────────────
const changeStatus = async (invoice, status) => {
    try {
        await ElMessageBox.confirm(
            t('inv_move_confirm', { number: invoice.invoice_number, status: statusLabel(status) }),
            t('change_invoice_status'),
            { type: 'info', confirmButtonText: t('confirm'), cancelButtonText: t('cancel') }
        );
    } catch {
        return;
    }

    try {
        await store.updateInvoiceStatus(invoice.id, status);
        ElMessage.success(t('invoice_status_changed'));
        fetchInvoices();
    } catch (error) {
        ElMessage.error(apiErrorMessage(error, t('failed_to_change_invoice_status')));
    }
};

const cancelInvoice = async (invoice) => {
    const paid = Number(invoice.paid_amount) || 0;
    try {
        await ElMessageBox.confirm(
            paid > 0.009
                ? t('inv_cancel_confirm_paid', { number: invoice.invoice_number, amount: formatCurrency(paid) })
                : t('inv_cancel_confirm', { number: invoice.invoice_number }),
            t('inv_cancel'),
            {
                type: 'warning',
                confirmButtonText: t('inv_cancel'),
                cancelButtonText: t('inv_keep_invoice'),
                confirmButtonClass: 'el-button--danger',
            }
        );
    } catch {
        return;
    }

    try {
        await store.updateInvoiceStatus(invoice.id, 'cancelled');
        ElMessage.success({ message: t('inv_cancelled_done'), duration: 5000 });
        fetchInvoices();
    } catch (error) {
        ElMessage.error(apiErrorMessage(error, t('failed_to_change_invoice_status')));
    }
};

const removeInvoice = async (invoice) => {
    try {
        await ElMessageBox.confirm(
            t('inv_delete_confirm', { number: invoice.invoice_number }),
            t('confirm_deletion'),
            { type: 'warning', confirmButtonText: t('delete'), cancelButtonText: t('cancel'), confirmButtonClass: 'el-button--danger' }
        );
    } catch {
        return;
    }

    try {
        await store.deleteInvoice(invoice.id);
        ElMessage.success(t('invoice_deleted'));
        fetchInvoices();
    } catch (error) {
        ElMessage.error(apiErrorMessage(error, t('failed_to_delete_invoice')));
    }
};

const runCommand = (invoice, command) => {
    if (command === 'print') return openPrintDialog(invoice);
    if (command === 'open') return openInvoice(invoice);
    if (command === 'order') return goToOrder(invoice.sales_order);
    if (command === 'payments') return router.push({ path: '/admin/sales/payments', query: { search: invoice.invoice_number } });
    if (command === 'cancel') return cancelInvoice(invoice);
    if (command === 'delete') return removeInvoice(invoice);
    return null;
};

// ── Invoice Printing State & Methods ─────────────────────────────────────
const printInvoiceDialogVisible = ref(false);
const printInvoiceLoading = ref(false);
const printInvoiceData = ref(null);
const isFullScreenPreview = ref(false);
const isExportingPdf = ref(false);
const qrCodeDataUrl = ref('');

const {
    printSettings,
    zoomLevel,
    zoomIn,
    zoomOut,
    resetZoom,
    zoomContainerStyle,
    applyPreset,
    saveAsDefault,
    resetToDefaults,
    getWatermarkLabel,
} = usePrintSettings('invoice');

const watermarkText = computed(() => getWatermarkLabel(printSettings.watermark));

const coverImageUrl = computed(() => {
    const c = printSettings.coverImage || '/cover.jpeg';
    return c.startsWith('/') || c.startsWith('http') ? c : `/storage/${c}`;
});

const headerBannerUrl = computed(() => {
    const b = printSettings.headerBanner || '/Header.jpeg';
    return b.startsWith('/') || b.startsWith('http') ? b : `/storage/${b}`;
});

const stampImageUrl = computed(() => {
    if (!printSettings.stampImage) return '';
    const s = printSettings.stampImage;
    return s.startsWith('/') || s.startsWith('http') ? s : `/storage/${s}`;
});

const signatureImageUrl = computed(() => {
    if (!printSettings.signatureImage) return '';
    const s = printSettings.signatureImage;
    return s.startsWith('/') || s.startsWith('http') ? s : `/storage/${s}`;
});

const printItemsCount = computed(() => printInvoiceData.value?.items?.length || 0);

const printTotalQuantity = computed(() => (printInvoiceData.value?.items || []).reduce((acc, it) => acc + (toNum(it.quantity)), 0));

const estimatedPages = computed(() => {
    const items = printInvoiceData.value?.items?.length || 0;
    const isCompact = printSettings.density === 'compact';
    const limit = isCompact ? 14 : 9;
    const docPages = items <= limit ? 1 : Math.ceil(items / limit);
    return printSettings.showCover ? docPages + 1 : docPages;
});

const printFormattedDate = computed(() => {
    const d = new Date();
    return `${d.toLocaleDateString('ar-SY')} ${d.toLocaleTimeString('ar-SY', { hour: '2-digit', minute: '2-digit' })}`;
});

const toNum = (val) => {
    const n = Number(val);
    return isNaN(n) ? 0 : n;
};

const hasItemDiscount = computed(() => printInvoiceData.value?.items?.some((item) => toNum(item.discount) > 0) ?? false);

const hasItemTax = computed(() => printInvoiceData.value?.items?.some((item) => toNum(item.tax_amount) > 0) ?? false);

const lineTotal = (item) => {
    if (!item) return 0;
    if (toNum(item.total_price) > 0) return toNum(item.total_price);
    const gross = toNum(item.unit_price) * toNum(item.quantity);
    const disc = toNum(item.discount);
    const tax = toNum(item.tax_amount);
    return Math.max(0, gross - disc + tax);
};

const calcPrintSubtotal = (invoice) => {
    if (!invoice?.items) return 0;
    return invoice.items.reduce((acc, it) => acc + (toNum(it.unit_price) * toNum(it.quantity)), 0);
};

const paymentStateLabel = (state) => {
    switch (state) {
        case 'paid':
            return t('inv_pay_paid') || 'مدفوع بالكامل';
        case 'partial':
            return t('inv_pay_partial') || 'مسدد جزئياً';
        case 'credit':
            return t('inv_pay_credit') || 'رصيد دائن';
        case 'unpaid':
        default:
            return t('inv_pay_unpaid') || 'غير مدفوع';
    }
};

const generateQrCode = async (invoice) => {
    if (!invoice) return;
    try {
        const payload = `https://sanitary.awaanaltakadom.sy/admin/sales/invoices?search=${encodeURIComponent(invoice.invoice_number)}&ref=${invoice.invoice_number}&total=${invoice.total || 0}&paid=${invoice.paid_amount || 0}&date=${invoice.created_at || ''}`;
        qrCodeDataUrl.value = await QRCode.toDataURL(payload, {
            width: 140,
            margin: 1,
            color: {
                dark: '#0f172a',
                light: '#ffffff',
            },
        });
    } catch (e) {
        console.error('QR code generation error:', e);
        qrCodeDataUrl.value = '';
    }
};

watch(printInvoiceData, (newInv) => {
    if (newInv) {
        generateQrCode(newInv);
    }
}, { immediate: true });

const openPrintDialog = async (invoiceOrRow) => {
    if (!invoiceOrRow) return;
    zoomLevel.value = 100;

    const invoiceId = typeof invoiceOrRow === 'object' ? invoiceOrRow.id : Number(invoiceOrRow);
    printInvoiceData.value = typeof invoiceOrRow === 'object' ? { ...invoiceOrRow } : null;
    printInvoiceDialogVisible.value = true;
    printInvoiceLoading.value = true;

    try {
        let full = typeof invoiceOrRow === 'object' ? { ...invoiceOrRow } : null;
        if (!full || !full.items || !full.items.length || !full.warehouse || !full.customer_address) {
            try {
                const res = await invoicesApi.getById(invoiceId);
                const fetched = res.data?.data || res.data;
                if (fetched && typeof fetched === 'object') {
                    full = fetched;
                }
            } catch (err) {
                console.warn('invoicesApi.getById failed, falling back to store', err);
                try {
                    const storeData = await store.fetchInvoice(invoiceId);
                    if (storeData) full = storeData;
                } catch (storeErr) {
                    console.warn('store.fetchInvoice failed', storeErr);
                }
            }
        }

        if (full) {
            if (!full.items) full.items = [];
            printInvoiceData.value = full;
            generateQrCode(full);
        }
    } catch (e) {
        console.error('Failed to load invoice for printing:', e);
        ElMessage.error(t('failed_to_load_invoice_details') || 'فشل تحميل تفاصيل الفاتورة للطباعة');
    } finally {
        printInvoiceLoading.value = false;
    }
};

const triggerPrintInvoice = async () => {
    const docEl = document.getElementById('invoice-printable-doc');
    if (!docEl) {
        const origTitle = document.title;
        document.title = '';
        window.print();
        document.title = origTitle;
        return;
    }

    try {
        const headStyles = [];
        document.querySelectorAll('link[rel="stylesheet"], style').forEach((node) => {
            headStyles.push(node.outerHTML);
        });

        const extraPrintStyles = `
            <style>
                @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;700;800&display=swap');

                @page {
                    size: A4 portrait;
                    margin: 0 !important;
                }
                @page :left { margin: 0 !important; }
                @page :right { margin: 0 !important; }
                @page :first { margin: 0 !important; }
                * {
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                    box-sizing: border-box !important;
                }
                html, body {
                    visibility: visible !important;
                    opacity: 1 !important;
                    margin: 0 !important;
                    padding: 0 !important;
                    background: #ffffff !important;
                    color: #0f172a !important;
                    font-family: 'Cairo', 'Almarai', Tahoma, -apple-system, sans-serif !important;
                    direction: rtl !important;
                    width: 100% !important;
                    height: auto !important;
                    overflow: visible !important;
                }
                a {
                    text-decoration: none !important;
                    color: inherit !important;
                }
                a[href]:after, abbr[title]:after {
                    content: none !important;
                }
                .brand-domain-badge, .domain-badge {
                    display: none !important;
                }
                #invoice-printable-doc {
                    visibility: visible !important;
                    opacity: 1 !important;
                    width: 100% !important;
                    max-width: 100% !important;
                    margin: 0 !important;
                    padding: 0 !important;
                    border: none !important;
                    box-shadow: none !important;
                    background: #ffffff !important;
                    display: block !important;
                }
                .inv-print-cover {
                    display: block !important;
                    position: relative !important;
                    width: 100% !important;
                    height: 100% !important;
                    min-height: 296mm !important;
                    max-height: 297mm !important;
                    margin: 0 !important;
                    padding: 0 !important;
                    overflow: hidden !important;
                    page-break-before: avoid !important;
                    page-break-after: always !important;
                    break-after: page !important;
                    page-break-inside: avoid !important;
                    break-inside: avoid !important;
                    box-sizing: border-box !important;
                }
                .inv-print-cover img {
                    width: 100% !important;
                    height: 100% !important;
                    min-height: 296mm !important;
                    max-height: 297mm !important;
                    object-fit: cover !important;
                    display: block !important;
                }
                .inv-document-body {
                    width: 100% !important;
                    max-width: 100% !important;
                    margin: 0 !important;
                    padding: 10mm 12mm !important;
                    border: none !important;
                    box-shadow: none !important;
                    background: #ffffff !important;
                    display: block !important;
                    box-sizing: border-box !important;
                    position: relative !important;
                    page-break-before: auto !important;
                    break-before: auto !important;
                }
                .inv-printable-sheet.has-cover .inv-document-body {
                    page-break-before: auto !important;
                    break-before: auto !important;
                }
                .inv-print-table thead {
                    display: table-header-group !important;
                }
                .inv-print-table tfoot {
                    display: table-row-group !important;
                }
                .inv-print-table tr {
                    break-inside: avoid !important;
                    page-break-inside: avoid !important;
                }
                .inv-doc-ribbon,
                .inv-print-meta-grid,
                .inv-print-summary-row,
                .inv-print-signatures,
                .inv-print-footer,
                .inv-print-closing-section {
                    break-inside: avoid !important;
                    page-break-inside: avoid !important;
                }
                .inv-screen-only,
                .inv-print-toolbar {
                    display: none !important;
                }
            </style>
        `;

        let iframe = document.getElementById('inv-print-hidden-iframe');
        if (iframe) iframe.remove();
        iframe = document.createElement('iframe');
        iframe.id = 'inv-print-hidden-iframe';
        iframe.style.position = 'fixed';
        iframe.style.left = '0';
        iframe.style.top = '0';
        iframe.style.width = '100vw';
        iframe.style.height = '100vh';
        iframe.style.border = '0';
        iframe.style.margin = '0';
        iframe.style.padding = '0';
        iframe.style.opacity = '0';
        iframe.style.pointerEvents = 'none';
        iframe.style.zIndex = '-9999';
        document.body.appendChild(iframe);

        const iframeDoc = iframe.contentWindow.document;
        iframeDoc.open();
        iframeDoc.write(`<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8">
    <base href="${window.location.origin}/">
    <title></title>
    ${headStyles.join('\n')}
    ${extraPrintStyles}
</head>
<body class="inv-print-body-isolated">
    ${docEl.outerHTML}
</body>
</html>`);
        iframeDoc.close();

        await new Promise((resolve) => {
            const images = Array.from(iframeDoc.images || []);
            const uncompleted = images.filter((img) => !img.complete);
            if (uncompleted.length === 0) {
                setTimeout(resolve, 200);
                return;
            }
            let remaining = uncompleted.length;
            const onDone = () => {
                remaining--;
                if (remaining <= 0) resolve();
            };
            uncompleted.forEach((img) => {
                img.onload = onDone;
                img.onerror = onDone;
            });
            setTimeout(resolve, 1500);
        });

        await new Promise((r) => setTimeout(r, 200));

        iframe.contentWindow.focus();
        iframe.contentWindow.print();

        setTimeout(() => {
            iframe?.remove();
        }, 60000);
    } catch (e) {
        console.error('Iframe print error, falling back to window.print():', e);
        const origTitle = document.title;
        document.title = '';
        await nextTick();
        window.print();
        document.title = origTitle;
    }
};

const exportInvoicePdf = async () => {
    const docEl = document.getElementById('invoice-printable-doc');
    if (!docEl) return;
    isExportingPdf.value = true;
    try {
        const prevZoom = zoomLevel.value;
        zoomLevel.value = 100;
        await nextTick();

        const pdf = new jsPDF({ orientation: 'p', unit: 'mm', format: 'a4' });
        const pageWidth = 210;
        const pageHeight = 297;

        const coverEl = docEl.querySelector('.inv-print-cover');
        const bodyEl = docEl.querySelector('.inv-document-body') || docEl;

        if (printSettings.showCover && coverEl) {
            const coverCanvas = await html2canvas(coverEl, {
                scale: 2,
                useCORS: true,
                backgroundColor: '#ffffff',
                logging: false,
            });
            const coverData = coverCanvas.toDataURL('image/jpeg', 0.95);
            pdf.addImage(coverData, 'JPEG', 0, 0, pageWidth, pageHeight);
            pdf.addPage();
        }

        const bodyCanvas = await html2canvas(bodyEl, {
            scale: 2,
            useCORS: true,
            backgroundColor: '#ffffff',
            logging: false,
        });

        zoomLevel.value = prevZoom;

        const bodyData = bodyCanvas.toDataURL('image/jpeg', 0.95);
        const imgHeight = (bodyCanvas.height * pageWidth) / bodyCanvas.width;
        let heightLeft = imgHeight;
        let position = 0;

        pdf.addImage(bodyData, 'JPEG', 0, position, pageWidth, imgHeight);
        heightLeft -= pageHeight;

        while (heightLeft > 0) {
            position = heightLeft - imgHeight;
            pdf.addPage();
            pdf.addImage(bodyData, 'JPEG', 0, position, pageWidth, imgHeight);
            heightLeft -= pageHeight;
        }

        const fileName = `${t('invoice') || 'Invoice'}_${printInvoiceData.value?.invoice_number || 'doc'}.pdf`;
        pdf.save(fileName);
        ElMessage.success(t('pdf_downloaded_successfully') || 'تم تجهيز وتحميل ملف PDF بنجاح');
    } catch (err) {
        console.error('PDF export error:', err);
        ElMessage.error(t('failed_to_export_pdf') || 'فشل تصدير ملف PDF');
    } finally {
        isExportingPdf.value = false;
    }
};

watch(printInvoiceDialogVisible, (visible) => {
    if (typeof document !== 'undefined') {
        if (visible) {
            document.body.classList.add('inv-print-dialog-open');
        } else {
            document.body.classList.remove('inv-print-dialog-open');
        }
    }
});

// ── Payment ──────────────────────────────────────────────────────────────
const paymentDialogVisible = ref(false);
const paymentTarget = ref(null);

const openPaymentDialog = (invoice) => {
    paymentTarget.value = invoice;
    paymentDialogVisible.value = true;
};

// A payment changes paid, due and the totals, so the list is refetched.
const onPaymentSaved = () => {
    paymentTarget.value = null;
    fetchInvoices();
};

// ── Layout and lifecycle ─────────────────────────────────────────────────
const isNarrow = ref(false);
const onResize = () => { isNarrow.value = window.innerWidth < 768; };

watch(() => route.query, (query) => {
    if (route.name !== 'admin.sales.invoices' || queryKey(query) === lastQueryKey) return;
    readQuery();
    lastQueryKey = queryKey(query);
    fetchInvoices();
});

watch(() => [route.query.print, route.query.do, route.query.invoice], () => {
    const printId = route.query.print || (route.query.do === 'print' ? route.query.invoice : null);
    if (printId) {
        openPrintDialog(printId);
    }
}, { immediate: true });

onMounted(() => {
    onResize();
    window.addEventListener('resize', onResize);
    readQuery();
    // ?invoice= is folded into the search, so the URL is rewritten to match.
    if (route.query.invoice && route.query.do !== 'print') writeQuery();
    else lastQueryKey = queryKey(route.query);
    fetchInvoices();
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', onResize);
    clearTimeout(searchTimer);
    if (typeof document !== 'undefined') {
        document.body.classList.remove('inv-print-dialog-open');
    }
    const iframe = document.getElementById('inv-print-hidden-iframe');
    if (iframe) iframe.remove();
});
</script>

<style scoped>
.invoices-page { font-family: 'Cairo', sans-serif; }

/* ── Cards ── */
.stat-card { border-radius: 14px; transition: border-color 0.15s, box-shadow 0.15s; }
.stat-card.is-clickable { cursor: pointer; }
.stat-card.is-active { border-color: #2563eb; box-shadow: 0 0 0 1px #2563eb inset; }
.stat-inner { display: flex; align-items: center; gap: 0.9rem; }
.stat-icon {
    width: 46px;
    height: 46px;
    flex-shrink: 0;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
}
.stat-icon.blue { background: #eff6ff; color: #2563eb; }
.stat-icon.green { background: #f0fdf4; color: #16a34a; }
.stat-icon.red { background: #fef2f2; color: #dc2626; }
.stat-icon.purple { background: #f5f3ff; color: #7c3aed; }
.stat-details { min-width: 0; flex: 1; }
.stat-details h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 800;
    color: #0f172a;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.stat-details p { margin: 0.15rem 0 0; font-size: 0.82rem; color: #64748b; }
.stat-sub { display: block; font-size: 0.74rem; color: #94a3b8; margin-top: 0.15rem; }
.collect-bar { height: 5px; border-radius: 999px; background: #f1f5f9; overflow: hidden; margin-top: 0.4rem; }
.collect-bar span { display: block; height: 100%; background: #16a34a; border-radius: 999px; }

/* ── Aging ── */
.aging { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 0.9rem 1.1rem; margin-bottom: 1.25rem; }
.aging-head { display: flex; align-items: baseline; gap: 0.6rem; flex-wrap: wrap; margin-bottom: 0.6rem; }
.aging-head .stat-sub { margin: 0; }
.aging-bar { display: flex; gap: 3px; height: 12px; border-radius: 999px; overflow: hidden; background: #f1f5f9; }
.aging-part { all: unset; min-width: 6px; cursor: pointer; background: var(--a); }
.aging-part:disabled { cursor: default; }
.aging-part.is-on { outline: 2px solid #0f172a; outline-offset: -2px; }
.a-0_30 { --a: #93c5fd; }
.a-31_60 { --a: #fbbf24; }
.a-61_90 { --a: #f97316; }
.a-90_plus { --a: #dc2626; }
.aging-legend { display: flex; flex-wrap: wrap; gap: 0.4rem 1rem; margin-top: 0.6rem; }
.aging-key { all: unset; cursor: pointer; display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.8rem; color: #475569; padding: 0.1rem 0.35rem; border-radius: 6px; }
.aging-key:disabled { cursor: default; }
.aging-key:not(:disabled):hover, .aging-key.is-on { background: #f1f5f9; }
.aging-key:focus-visible, .aging-part:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }
.aging-key strong { font-variant-numeric: tabular-nums; color: #0f172a; }
.dot { width: 9px; height: 9px; border-radius: 50%; background: var(--a); }

/* ── Panel ── */
.panel-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.1rem; }

.status-tabs { display: flex; gap: 0.35rem; flex-wrap: wrap; margin-bottom: 0.9rem; padding-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9; }
.status-tab {
    all: unset;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.3rem 0.75rem;
    border-radius: 999px;
    font-size: 0.85rem;
    color: #475569;
}
.status-tab:hover { background: #f1f5f9; }
.status-tab.is-on { background: #0f172a; color: #fff; }
.status-tab:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }
.status-tab-count { font-size: 0.72rem; font-weight: 700; background: rgba(100, 116, 139, 0.14); border-radius: 999px; padding: 0 0.45rem; min-width: 1.2rem; text-align: center; }
.status-tab.is-on .status-tab-count { background: rgba(255, 255, 255, 0.2); }

.filters { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; margin-bottom: 1rem; }
.filter-search { flex: 1 1 240px; max-width: 340px; }
.filter-select { width: 170px; }
.filter-dates { max-width: 270px; }

/* ── Table ── */
.invoices-table :deep(.el-table__row) { cursor: pointer; }
.invoices-table :deep(.is-cancelled td) { color: #94a3b8; }
.invoices-table :deep(.is-cancelled .amount) { text-decoration: line-through; }
.cell-stack { display: flex; flex-direction: column; gap: 0.15rem; min-width: 0; align-items: flex-start; }
.cell-stack.align-end { align-items: flex-end; }
.cell-secondary { display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.78rem; color: #64748b; }
.mono { font-family: ui-monospace, monospace; font-weight: 700; color: #0f172a; font-size: 0.85rem; }
.strong { font-weight: 600; }
.amount { font-weight: 700; font-variant-numeric: tabular-nums; }
.amount.due { color: #b91c1c; font-size: 0.82rem; }
.amount.credit { color: #7c3aed; font-size: 0.82rem; }

.link-button { all: unset; cursor: pointer; color: #2563eb; font-weight: 600; }
.link-button:hover { text-decoration: underline; }
.link-button:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; border-radius: 3px; }

.pay-cell { display: grid; gap: 0.3rem; min-width: 0; }
.pay-line { display: flex; justify-content: space-between; align-items: baseline; gap: 0.5rem; }
.pay-state { font-size: 0.78rem; font-weight: 700; }
.pay-state.p-paid { color: #16a34a; }
.pay-state.p-partial { color: #d97706; }
.pay-state.p-unpaid { color: #dc2626; }
.pay-state.p-credit { color: #7c3aed; }
.age-note { font-size: 0.72rem; color: #d97706; }
.age-note.bad { color: #dc2626; font-weight: 600; }

.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.12rem 0.6rem;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--c, #64748b);
    background: color-mix(in srgb, var(--c, #64748b) 11%, #fff);
    border: none;
    font-family: inherit;
}
.status-pill.clickable { cursor: pointer; }
.status-pill.clickable:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }
.status-pill .chevron { font-size: 0.6rem; opacity: 0.7; }
.s-pending { --c: #d97706; }
.s-confirmed { --c: #2563eb; }
.s-processing { --c: #7c3aed; }
.s-shipped { --c: #0891b2; }
.s-delivered { --c: #16a34a; }
.s-cancelled { --c: #64748b; }

.row-actions { display: inline-flex; align-items: center; gap: 0.25rem; }
:deep(.danger-item) { color: #dc2626; }

.pagination-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-top: 1rem; }

@media (max-width: 768px) {
    :deep(.admin-stat-grid) { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: 0.6rem; }
    .stat-card :deep(.el-card__body) { padding: 0.75rem; }
    .stat-inner { gap: 0.6rem; }
    .stat-icon { width: 36px; height: 36px; font-size: 0.95rem; }
    .stat-details h3 { font-size: 1rem; }

    .panel-card, .aging { padding: 0.85rem; }
    .status-tabs { flex-wrap: nowrap; overflow-x: auto; }
    .status-tab { white-space: nowrap; }
    .filter-search { max-width: none; flex-basis: 100%; }
    .filter-select { width: calc(50% - 0.375rem); }
    .filter-dates { max-width: none; width: 100% !important; }
    .pagination-row { justify-content: center; }
}

/* ══════════════════════════════════════════════════════════════════════════
   INVOICE PRINT PREVIEW & PRINTABLE DOCUMENT STYLES
   ══════════════════════════════════════════════════════════════════════════ */
.inv-print-dialog :global(.el-dialog__header) {
    margin-right: 0;
    padding: 14px 20px;
    border-bottom: 1px solid #e2e8f0;
    background: #ffffff;
}

.inv-print-dialog :global(.el-dialog__body) {
    padding: 0;
    background: #f1f5f9;
}

.inv-dialog-header-custom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
}

.inv-dh-left {
    display: flex;
    align-items: center;
    gap: 12px;
}

.inv-dh-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: linear-gradient(135deg, #1e40af, #3b82f6);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    box-shadow: 0 2px 6px rgba(30, 64, 175, 0.25);
    flex-shrink: 0;
}

.inv-dh-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.inv-dh-main-line {
    display: flex;
    align-items: center;
    gap: 8px;
}

.inv-dh-title-text {
    font-size: 14.5px;
    font-weight: 800;
    color: #0f172a;
}

.inv-dh-num {
    background: #eff6ff;
    color: #1e40af;
    border: 1px solid #bfdbfe;
    padding: 1px 8px;
    border-radius: 6px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 12.5px;
    font-weight: 800;
}

.inv-dh-subline {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 11px;
    color: #64748b;
}

.inv-dh-customer-crumb {
    color: #334155;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.inv-dh-badges {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.inv-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 700;
}
.inv-status-pill.pill-confirmed,
.inv-status-pill.pill-delivered {
    background: #dcfce7;
    color: #15803d;
}
.inv-status-pill.pill-pending {
    background: #fef3c7;
    color: #b45309;
}
.inv-status-pill.pill-processing,
.inv-status-pill.pill-shipped {
    background: #e0f2fe;
    color: #0369a1;
}
.inv-status-pill.pill-cancelled {
    background: #fee2e2;
    color: #b91c1c;
}

.inv-pay-pill-dh {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
}
.inv-pay-pill-dh.pill-pay-paid { background: #dcfce7; color: #15803d; }
.inv-pay-pill-dh.pill-pay-partial { background: #fef3c7; color: #b45309; }
.inv-pay-pill-dh.pill-pay-unpaid { background: #fee2e2; color: #b91c1c; }
.inv-pay-pill-dh.pill-pay-credit { background: #f5f3ff; color: #7c3aed; }

.inv-dh-count {
    font-size: 11.5px;
    color: #475569;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 2px 8px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.inv-dh-pages-est {
    font-size: 11px;
    color: #0284c7;
    background: #f0f9ff;
    border: 1px solid #bae6fd;
    padding: 2px 8px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-weight: 600;
}

.inv-dh-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.inv-fs-toggle-btn {
    font-size: 11.5px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

/* ── Print Settings Toolbar (Screen Only) ── */
.inv-print-toolbar {
    background: #ffffff;
    padding: 10px 18px;
    border-bottom: 1px solid #cbd5e1;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.inv-toolbar-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px 18px;
}

.inv-toolbar-primary-row {
    padding-bottom: 8px;
    border-bottom: 1px solid #f1f5f9;
}

.inv-toolbar-secondary-row {
    padding-bottom: 8px;
    border-bottom: 1px dashed #e2e8f0;
}

.inv-toolbar-toggles-row {
    align-items: center;
}

.inv-tb-unit {
    display: flex;
    align-items: center;
    gap: 6px;
}

.inv-tb-label {
    font-size: 11px;
    font-weight: 700;
    color: #475569;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    white-space: nowrap;
}

.inv-tb-actions-cluster {
    margin-inline-start: auto;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-export-pdf {
    font-weight: 700 !important;
    color: #b45309 !important;
    background: #fef3c7 !important;
    border: 1px solid #fde68a !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 5px !important;
}

.btn-export-pdf:hover {
    background: #fde68a !important;
    color: #92400e !important;
}

.btn-print-prominent {
    font-weight: 700 !important;
    background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%) !important;
    border: none !important;
    box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3) !important;
    padding-inline: 18px !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 5px !important;
}

.btn-print-prominent:hover {
    background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%) !important;
    transform: translateY(-1px);
}

.theme-dot {
    display: inline-block;
    width: 9px;
    height: 9px;
    border-radius: 50%;
    vertical-align: middle;
    margin-inline-end: 3px;
}
.dot-navy { background: #1e3a8a; }
.dot-emerald { background: #047857; }
.dot-charcoal { background: #18181b; }
.dot-indigo { background: #4338ca; }

.inv-zoom-badge {
    font-family: 'JetBrains Mono', monospace !important;
    font-size: 11px !important;
    font-weight: 700 !important;
    min-width: 48px;
}

.inv-toolbar-sublabel {
    font-size: 11px;
    font-weight: 700;
    color: #64748b;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    white-space: nowrap;
}

.inv-toggles-grid {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px 16px;
}

.inv-toggles-grid :global(.el-checkbox) {
    margin-right: 0 !important;
    margin-left: 0 !important;
}

.inv-toggles-grid :global(.el-checkbox__label) {
    font-size: 11px;
    color: #334155;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.inv-toggles-grid :global(.el-checkbox__label i) {
    color: #64748b;
    font-size: 10px;
}

/* ── Paper Simulation Stage (Screen Only) ── */
.inv-paper-stage {
    padding: 24px;
    background: #e2e8f0;
    display: flex;
    justify-content: center;
    overflow: auto;
    min-height: 480px;
    box-sizing: border-box;
}

.inv-paper-stage.is-fullscreen {
    min-height: calc(100vh - 165px);
    background: #cbd5e1;
}

.inv-paper-viewport {
    display: flex;
    justify-content: center;
    width: 100%;
    transform-origin: top center;
}

/* ── Printable Sheet Styles ── */
.inv-printable-sheet {
    --inv-primary: #1e3a8a;
    --inv-header-bg: #1e293b;
    --inv-accent-subtle: #eff6ff;
    --inv-border-color: #cbd5e1;
    --inv-highlight-bg: #f8fafc;

    position: relative;
    width: 100%;
    max-width: 860px;
    margin: 0 auto;
    direction: rtl;
    font-family: 'Cairo', 'Almarai', Tahoma, sans-serif;
}

/* Screen cover preview */
.inv-print-cover {
    width: 100%;
    max-width: 860px;
    aspect-ratio: 1 / 1.4142;
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 24px;
    box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.16), 0 0 0 1px rgba(15, 23, 42, 0.05);
    background: #0f172a;
}

.inv-print-cover img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

/* Document Sheet Body */
.inv-document-body {
    position: relative;
    width: 100%;
    background: #ffffff;
    color: #0f172a;
    padding: 18px 24px;
    box-sizing: border-box;
    border: 1px solid var(--inv-border-color);
    border-radius: 8px;
    box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.12), 0 0 0 1px rgba(15, 23, 42, 0.04);
}

/* Color Theme Overrides */
.inv-printable-sheet.theme-emerald {
    --inv-primary: #047857;
    --inv-header-bg: #064e3b;
    --inv-accent-subtle: #ecfdf5;
    --inv-border-color: #a7f3d0;
    --inv-highlight-bg: #f0fdf4;
}

.inv-printable-sheet.theme-charcoal {
    --inv-primary: #18181b;
    --inv-header-bg: #09090b;
    --inv-accent-subtle: #f4f4f5;
    --inv-border-color: #d4d4d8;
    --inv-highlight-bg: #fafafa;
}

.inv-printable-sheet.theme-indigo {
    --inv-primary: #4338ca;
    --inv-header-bg: #312e81;
    --inv-accent-subtle: #eef2ff;
    --inv-border-color: #c7d2fe;
    --inv-highlight-bg: #f5f3ff;
}

/* Watermark Overlay */
.inv-sheet-watermark {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
    user-select: none;
    z-index: 1;
    overflow: hidden;
}

.inv-sheet-watermark span {
    transform: rotate(-28deg);
    font-size: 54px;
    font-weight: 900;
    letter-spacing: 0.12em;
    padding: 12px 30px;
    border-radius: 12px;
    text-align: center;
    white-space: nowrap;
}

.inv-sheet-watermark.wm-draft span {
    color: rgba(220, 38, 38, 0.08);
    border: 4px dashed rgba(220, 38, 38, 0.14);
}
.inv-sheet-watermark.wm-approved span {
    color: rgba(22, 163, 74, 0.08);
    border: 4px solid rgba(22, 163, 74, 0.14);
}
.inv-sheet-watermark.wm-paid span {
    color: rgba(13, 148, 136, 0.08);
    border: 4px solid rgba(13, 148, 136, 0.14);
}
.inv-sheet-watermark.wm-official span {
    color: rgba(30, 58, 138, 0.08);
    border: 4px solid rgba(30, 58, 138, 0.14);
}

/* Document Executive Ribbon */
.inv-doc-ribbon {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px 16px;
    padding: 7px 12px;
    background: var(--inv-highlight-bg);
    border: 1px solid var(--inv-border-color);
    border-radius: 6px;
    margin-bottom: 12px;
}

.inv-ribbon-left {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.inv-ribbon-doc-type {
    font-size: 11px;
    font-weight: 800;
    color: var(--inv-primary);
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.inv-ribbon-status {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 1px 8px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 700;
}
.inv-ribbon-status.status-confirmed,
.inv-ribbon-status.status-delivered {
    background: #dcfce7;
    color: #15803d;
}
.inv-ribbon-status.status-pending {
    background: #fef3c7;
    color: #b45309;
}
.inv-ribbon-status.status-processing,
.inv-ribbon-status.status-shipped {
    background: #e0f2fe;
    color: #0369a1;
}
.inv-ribbon-status.status-cancelled {
    background: #fee2e2;
    color: #b91c1c;
}

.inv-ribbon-pay {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 1px 8px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 700;
}
.inv-ribbon-pay.pay-paid { background: #dcfce7; color: #15803d; }
.inv-ribbon-pay.pay-partial { background: #fef3c7; color: #b45309; }
.inv-ribbon-pay.pay-unpaid { background: #fee2e2; color: #b91c1c; }
.inv-ribbon-pay.pay-credit { background: #f5f3ff; color: #7c3aed; }

.inv-ribbon-order {
    font-size: 10.5px;
    color: #475569;
    background: #eff6ff;
    padding: 1px 6px;
    border-radius: 4px;
    border: 1px solid #bfdbfe;
}

.inv-ribbon-right {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.inv-ribbon-item {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
}
.inv-ribbon-item .lbl {
    color: #64748b;
    font-weight: 600;
}
.inv-ribbon-item .val {
    color: #0f172a;
    font-weight: 700;
}

.inv-ribbon-qr {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    padding: 2px 6px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.inv-ribbon-qr-img {
    width: 28px;
    height: 28px;
    display: block;
    image-rendering: pixelated;
}

.inv-ribbon-qr-label {
    font-size: 8px;
    font-weight: 700;
    color: #475569;
}

/* ── Customer & Invoice Details Meta Grid ── */
.inv-print-meta-grid {
    display: grid;
    grid-template-columns: 1.15fr 1fr;
    gap: 12px;
    margin-bottom: 12px;
}

.inv-meta-card {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #ffffff;
    overflow: hidden;
}

.inv-meta-card-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    background: var(--inv-highlight-bg);
    border-bottom: 1px solid #e2e8f0;
}

.inv-card-header-icon {
    width: 20px;
    height: 20px;
    border-radius: 4px;
    background: var(--inv-accent-subtle);
    color: var(--inv-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    flex-shrink: 0;
}

.inv-meta-card-title {
    font-size: 11.5px;
    font-weight: 800;
    color: var(--inv-primary);
}

.inv-meta-card-body {
    padding: 8px 12px;
    font-size: 11px;
}

.inv-customer-primary {
    margin-bottom: 6px;
    display: flex;
    align-items: baseline;
    gap: 6px;
    flex-wrap: wrap;
}

.inv-customer-name {
    font-size: 13.5px;
    color: #0f172a;
    font-weight: 800;
}

.inv-customer-company {
    font-size: 11.5px;
    color: #475569;
    font-weight: 600;
    background: #f1f5f9;
    padding: 1px 6px;
    border-radius: 4px;
}

.inv-customer-meta-list {
    display: flex;
    flex-direction: column;
    gap: 4px;
    color: #334155;
}

.inv-cm-item {
    display: flex;
    align-items: center;
    gap: 6px;
    line-height: 1.35;
}

.inv-cm-item i {
    color: #64748b;
    width: 13px;
    text-align: center;
    font-size: 9.5px;
    flex-shrink: 0;
}

.inv-cm-tax {
    color: var(--inv-primary);
    font-weight: 600;
}

.inv-cm-tax i {
    color: var(--inv-primary);
}

.inv-details-meta-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px 12px;
}

.inv-dm-item {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.inv-dm-label {
    font-size: 9.5px;
    color: #64748b;
    font-weight: 600;
}

.inv-dm-val {
    font-size: 11px;
    font-weight: 700;
    color: #0f172a;
}

.inv-mono {
    font-family: 'JetBrains Mono', monospace;
    color: var(--inv-primary);
    font-weight: 800;
}

.inv-pay-method-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: #0369a1;
}

.inv-state-pill {
    display: inline-block;
    padding: 1px 6px;
    border-radius: 4px;
    font-size: 9.5px;
    font-weight: 700;
    width: fit-content;
}
.inv-state-pill.p-paid { background: #dcfce7; color: #15803d; }
.inv-state-pill.p-due,
.inv-state-pill.p-partial { background: #fef3c7; color: #b45309; }
.inv-state-pill.p-unpaid { background: #fee2e2; color: #b91c1c; }
.inv-state-pill.p-credit { background: #f5f3ff; color: #7c3aed; }

/* ── Items Table ── */
.inv-print-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
    margin-bottom: 12px;
}

.inv-print-table thead th {
    background: var(--inv-header-bg);
    color: #ffffff;
    border: 1px solid var(--inv-header-bg);
    padding: 6px 8px;
    text-align: right;
    font-weight: 800;
    font-size: 10px;
    letter-spacing: 0.02em;
}

.inv-print-table tbody td {
    border: 1px solid #e2e8f0;
    padding: 5px 8px;
    text-align: right;
    vertical-align: middle;
}

.inv-print-table tbody tr:nth-child(even) {
    background: #fbfcfd;
}

.inv-row-idx {
    color: #64748b;
    font-weight: 700;
    font-size: 10px;
}

.inv-print-img-cell {
    padding: 2px !important;
}

.inv-item-name {
    font-weight: 700;
    color: #0f172a;
    line-height: 1.3;
}

.inv-item-name-en {
    font-size: 9px;
    color: #64748b;
    direction: ltr;
    text-align: right;
}

.inv-item-variant {
    font-size: 9.5px;
    color: #0369a1;
    margin-top: 2px;
}

.inv-item-desc {
    font-size: 9px;
    color: #64748b;
    margin-top: 2px;
}

.inv-sku-cell code {
    background: #f1f5f9;
    padding: 1px 4px;
    border-radius: 4px;
    font-size: 9.5px;
    color: #475569;
    font-family: 'JetBrains Mono', monospace;
}

.inv-qty-cell {
    font-weight: 800;
}

.inv-qty-num {
    font-size: 12px;
    color: #0f172a;
}

.inv-unit-label {
    font-size: 9px;
    font-weight: normal;
    color: #64748b;
    margin-inline-start: 2px;
}

.inv-price-cell {
    font-variant-numeric: tabular-nums;
    color: #334155;
    font-weight: 600;
}

.inv-discount-cell {
    font-variant-numeric: tabular-nums;
    color: #dc2626;
    font-weight: 600;
}

.inv-tax-cell {
    font-variant-numeric: tabular-nums;
    color: #64748b;
}

.inv-total-cell {
    font-variant-numeric: tabular-nums;
    color: var(--inv-primary);
    font-weight: 800;
}

.inv-table-summary-row td {
    background: #f8fafc;
    border-top: 2px solid #cbd5e1;
    padding: 6px 10px;
    font-size: 11px;
}

.inv-sum-label {
    color: #334155;
}

.inv-sum-count {
    color: var(--inv-primary);
    font-weight: 800;
    margin-inline: 2px;
}

.inv-sum-total {
    text-align: left !important;
    font-size: 12px;
    color: var(--inv-primary);
}

.inv-sum-subtext {
    font-size: 10px;
    color: #64748b;
    margin-inline-end: 4px;
}

/* ── Summary & Notes Row ── */
.inv-print-summary-row {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 16px;
}

.inv-print-notes-col {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.inv-print-notes-box,
.inv-print-expenses-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 7px 10px;
    font-size: 10.5px;
}

.inv-notes-header,
.inv-expenses-header {
    display: flex;
    align-items: center;
    gap: 5px;
    margin-bottom: 3px;
    color: #475569;
    font-size: 10.5px;
}

.inv-notes-header i,
.inv-expenses-header i {
    color: var(--inv-primary);
    font-size: 10px;
}

.inv-notes-text {
    margin: 0;
    color: #0f172a;
    white-space: pre-wrap;
    line-height: 1.4;
}

.inv-expenses-list {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.inv-exp-row {
    display: flex;
    justify-content: space-between;
    font-size: 10.5px;
    color: #475569;
}

.inv-print-legal-notice {
    font-size: 9.5px;
    color: #64748b;
    line-height: 1.35;
    padding: 4px 6px;
    background: #fafafa;
    border-radius: 4px;
    display: flex;
    align-items: baseline;
    gap: 5px;
}

.inv-print-legal-notice i {
    color: #94a3b8;
    font-size: 9px;
}

.inv-print-totals-col {
    width: 320px;
    flex-shrink: 0;
}

.inv-totals-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 10px 14px;
}

.inv-totals-header {
    font-size: 11.5px;
    font-weight: 800;
    color: var(--inv-primary);
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 8px;
    border-bottom: 1px dashed #cbd5e1;
    padding-bottom: 6px;
}

.inv-totals-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11.5px;
}

.inv-totals-table td {
    padding: 4px 4px;
    color: #334155;
}

.inv-totals-table td.val {
    text-align: left;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.inv-totals-table td.discount-val {
    color: #dc2626;
}

.inv-totals-table .grand-total-row td {
    font-weight: 900;
    font-size: 14px;
    color: var(--inv-primary);
    border-top: 2px solid var(--inv-primary);
    padding-top: 6px;
}

.inv-totals-table .grand-val {
    font-size: 14.5px;
    color: var(--inv-primary);
}

.inv-totals-table .paid-row td {
    color: #15803d;
    font-weight: 700;
}

.inv-totals-table .paid-val {
    color: #15803d;
}

.inv-totals-table .due-row.has-due td {
    color: #dc2626;
    font-weight: 800;
}

.inv-totals-table .due-val {
    font-size: 12px;
}

/* ── Signatures ── */
.inv-print-signatures {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-top: 16px;
    padding-top: 10px;
    border-top: 1px dashed #cbd5e1;
}

.sig-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.sig-title {
    font-size: 10.5px;
    font-weight: 700;
    color: #334155;
    margin-bottom: 20px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.sig-title i {
    color: var(--inv-primary);
    font-size: 9.5px;
}

.sig-name {
    font-size: 9.5px;
    color: var(--inv-primary);
    font-weight: 700;
    margin-bottom: 3px;
}

.sig-line {
    width: 80%;
    border-bottom: 1px solid #94a3b8;
    margin-bottom: 4px;
}

.sig-sub {
    font-size: 8.5px;
    color: #94a3b8;
}

.sig-seal-box {
    width: 60px;
    height: 60px;
    border: 2px dashed #94a3b8;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 8.5px;
    color: #94a3b8;
    text-align: center;
    overflow: hidden;
}

.sig-seal-box.has-stamp {
    border: none;
    background: transparent;
}

.sig-stamp-img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.sig-digital-img {
    max-width: 85px;
    max-height: 35px;
    object-fit: contain;
    margin-top: 4px;
}

/* ── Document Running Footer ── */
.inv-print-footer {
    margin-top: 14px;
    padding-top: 6px;
    border-top: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 9px;
    color: #94a3b8;
    line-height: 1.3;
}

.inv-footer-meta {
    display: flex;
    align-items: center;
    gap: 12px;
    direction: ltr;
}

/* ── Compact Density Mode ── */
.inv-printable-sheet.density-compact .inv-document-body {
    padding: 10px 14px;
}

.inv-printable-sheet.density-compact .inv-doc-ribbon {
    padding: 4px 8px;
    margin-bottom: 8px;
}

.inv-printable-sheet.density-compact .inv-print-meta-grid {
    gap: 8px;
    margin-bottom: 8px;
}

.inv-printable-sheet.density-compact .inv-meta-card-body {
    padding: 5px 8px;
}

.inv-printable-sheet.density-compact .inv-print-table tbody td {
    padding: 3px 6px;
}

.inv-printable-sheet.density-compact .inv-print-table thead th {
    padding: 4px 6px;
}

.inv-printable-sheet.density-compact .inv-item-name {
    font-size: 10px;
}

.inv-printable-sheet.density-compact .inv-print-summary-row {
    margin-bottom: 10px;
}

.inv-printable-sheet.density-compact .inv-print-signatures {
    margin-top: 10px;
    padding-top: 6px;
}

.inv-printable-sheet.density-compact .sig-title {
    margin-bottom: 12px;
}

/* ══════════════════════════════════════════════════════════════════════════
   MEDIA PRINT RULES (ZERO MARGIN, FLUID A4, HIDDEN CHROME)
   ══════════════════════════════════════════════════════════════════════════ */
@media print {
    @page {
        size: A4 portrait;
        margin: 0 !important;
    }
    @page :left {
        margin: 0 !important;
    }
    @page :right {
        margin: 0 !important;
    }
    @page :first {
        margin: 0 !important;
    }

    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        box-sizing: border-box !important;
    }

    /* Hide background app layout & screen elements only when print modal is active */
    :global(body.inv-print-dialog-open .admin-layout),
    :global(body.inv-print-dialog-open .admin-sidebar),
    :global(body.inv-print-dialog-open .admin-header),
    :global(body.inv-print-dialog-open .admin-main-wrapper),
    :global(body.inv-print-dialog-open .sidebar-overlay),
    :global(body.inv-print-dialog-open .el-dialog__header),
    :global(body.inv-print-dialog-open .el-dialog__footer),
    :global(body.inv-print-dialog-open .dialog-footer),
    :global(body.inv-print-dialog-open .el-dialog__headerbtn),
    .inv-screen-only,
    .inv-print-toolbar {
        display: none !important;
    }

    /* Clean printing for listing table when printing page without modal */
    :global(body:not(.inv-print-dialog-open)) .admin-sidebar,
    :global(body:not(.inv-print-dialog-open)) .admin-header,
    :global(body:not(.inv-print-dialog-open)) .page-actions-bar,
    :global(body:not(.inv-print-dialog-open)) .table-filter-bar,
    :global(body:not(.inv-print-dialog-open)) .pagination-container,
    :global(body:not(.inv-print-dialog-open)) .action-buttons,
    :global(body:not(.inv-print-dialog-open)) .bulk-actions-toolbar {
        display: none !important;
    }

    :global(.el-overlay),
    :global(.el-overlay-dialog) {
        position: static !important;
        display: block !important;
        background: transparent !important;
        padding: 0 !important;
        margin: 0 !important;
        overflow: visible !important;
        width: 100% !important;
        height: auto !important;
        inset: auto !important;
        z-index: auto !important;
    }

    :global(.el-dialog.inv-print-dialog) {
        position: static !important;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        box-shadow: none !important;
        border: none !important;
        background: transparent !important;
        border-radius: 0 !important;
    }

    :global(.el-dialog.inv-print-dialog .el-dialog__body) {
        padding: 0 !important;
        margin: 0 !important;
        background: transparent !important;
        overflow: visible !important;
    }

    .inv-paper-stage {
        padding: 0 !important;
        margin: 0 !important;
        background: transparent !important;
        display: block !important;
        min-height: auto !important;
        overflow: visible !important;
    }

    .inv-paper-viewport {
        transform: none !important;
        display: block !important;
        width: 100% !important;
    }

    #invoice-printable-doc {
        position: static !important;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        box-shadow: none !important;
        border: none !important;
        background: #ffffff !important;
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
    }

    .inv-print-cover {
        display: block !important;
        position: relative !important;
        width: 100% !important;
        height: 100% !important;
        min-height: 296mm !important;
        max-height: 297mm !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: hidden !important;
        page-break-before: avoid !important;
        page-break-after: always !important;
        break-after: page !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
        box-sizing: border-box !important;
    }

    .inv-print-cover img {
        width: 100% !important;
        height: 100% !important;
        min-height: 296mm !important;
        max-height: 297mm !important;
        object-fit: cover !important;
        display: block !important;
    }

    .inv-document-body {
        position: static !important;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 10mm 12mm !important;
        box-shadow: none !important;
        border: none !important;
        background: #ffffff !important;
        display: block !important;
        box-sizing: border-box !important;
        page-break-before: auto !important;
        break-before: auto !important;
    }

    .inv-printable-sheet.has-cover .inv-document-body {
        page-break-before: auto !important;
        break-before: auto !important;
    }

    .inv-doc-ribbon,
    .inv-print-meta-grid,
    .inv-print-table,
    .inv-print-table tr,
    .inv-print-summary-row,
    .inv-print-signatures,
    .inv-print-footer,
    .inv-print-closing-section {
        break-inside: avoid !important;
        page-break-inside: avoid !important;
    }

    .inv-print-table thead {
        display: table-header-group !important;
    }

    .inv-print-table tfoot {
        display: table-row-group !important;
    }
}
</style>
