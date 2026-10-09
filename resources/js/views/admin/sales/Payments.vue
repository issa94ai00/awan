<template>
    <div class="payments-page">
        <AdminPageHeader
            :icon="tab === 'expenses' ? 'fas fa-receipt' : 'fas fa-money-bill-transfer'"
            :title="tab === 'expenses' ? $t('pay_expenses_title') : $t('payments')"
            :subtitle="tab === 'expenses' ? $t('pay_expenses_subtitle') : $t('pay_subtitle')"
        >
            <template #actions>
                <el-tooltip :content="$t('refresh')" placement="bottom" :enterable="false">
                    <el-button
                        :icon="Refresh"
                        :loading="store.loading || expensesLoading"
                        :aria-label="$t('refresh')"
                        @click="reload"
                    />
                </el-tooltip>
                <el-button v-if="tab === 'expenses'" type="primary" :icon="Plus" @click="openExpenseDialog()">
                    {{ $t('add_expense') }}
                </el-button>
                <el-button v-else type="primary" :icon="Plus" @click="paymentDialogVisible = true">
                    {{ $t('record_payment') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <!-- ── Payments Stat Cards ── -->
        <AdminStatGrid v-if="tab === 'payments'" :min="200">
            <el-card shadow="hover" class="stat-card">
                <div class="stat-inner">
                    <div class="stat-icon green"><i class="fas fa-sack-dollar"></i></div>
                    <div class="stat-details">
                        <h3>{{ formatCurrency(summary.collected) }}</h3>
                        <p>{{ $t('pay_collected') }} · {{ periodLabel }}</p>
                        <div v-if="methodSplit.length" class="method-bar" :aria-label="methodSplitText">
                            <span
                                v-for="part in methodSplit"
                                :key="part.method"
                                class="method-bar-part"
                                :class="`m-${part.method}`"
                                :style="{ flexGrow: part.share }"
                                :title="`${paymentMethodLabel(part.method)}: ${formatCurrency(part.total)}`"
                            />
                        </div>
                        <span class="stat-sub">{{ methodSplitText || $t('pay_payments_n', { count: summary.collected_count || 0 }) }}</span>
                    </div>
                </div>
            </el-card>

            <el-card shadow="hover" class="stat-card">
                <div class="stat-inner">
                    <div class="stat-icon blue"><i class="fas fa-calendar-day"></i></div>
                    <div class="stat-details">
                        <h3>{{ formatCurrency(summary.today) }}</h3>
                        <p>{{ $t('pay_today') }}</p>
                        <span class="stat-sub">{{ $t('pay_payments_n', { count: summary.today_count || 0 }) }}</span>
                    </div>
                </div>
            </el-card>

            <el-card shadow="hover" class="stat-card is-clickable" :class="{ 'is-active': filters.kind === 'on_account' }" @click="setKind('on_account')">
                <div class="stat-inner">
                    <div class="stat-icon purple"><i class="fas fa-user-tag"></i></div>
                    <div class="stat-details">
                        <h3>{{ formatCurrency(summary.on_account) }}</h3>
                        <p>{{ $t('pay_on_account') }}</p>
                        <span class="stat-sub">{{ $t('pay_on_account_hint', { count: summary.on_account_count || 0 }) }}</span>
                    </div>
                </div>
            </el-card>

            <el-card
                v-if="summary.refunded_count"
                shadow="hover"
                class="stat-card is-clickable"
                :class="{ 'is-active': filters.kind === 'refund' }"
                @click="setKind('refund')"
            >
                <div class="stat-inner">
                    <div class="stat-icon red"><i class="fas fa-rotate-left"></i></div>
                    <div class="stat-details">
                        <h3>{{ formatCurrency(summary.refunded) }}</h3>
                        <p>{{ $t('pay_refunded') }}</p>
                        <span class="stat-sub">{{ $t('pay_net', { amount: formatCurrency(summary.net) }) }}</span>
                    </div>
                </div>
            </el-card>
        </AdminStatGrid>

        <!-- ── Expenses Stat Cards ── -->
        <AdminStatGrid v-else :min="200">
            <el-card shadow="hover" class="stat-card">
                <div class="stat-inner">
                    <div class="stat-icon red"><i class="fas fa-receipt"></i></div>
                    <div class="stat-details">
                        <h3>{{ formatCurrency(expensesSummary.total) }}</h3>
                        <p>{{ $t('pay_expenses_total', { amount: '' }).replace(': ', '').trim() }} · {{ expensePeriodLabel }}</p>
                        <div v-if="expenseCategorySplit.length" class="method-bar" :aria-label="expenseCategorySplitText">
                            <span
                                v-for="part in expenseCategorySplit"
                                :key="part.category"
                                class="method-bar-part"
                                :class="`cat-${part.category}`"
                                :style="{ flexGrow: part.share }"
                                :title="`${expenseCategoryLabel(part.category)}: ${formatCurrency(part.total)}`"
                            />
                        </div>
                        <span class="stat-sub">{{ expenseCategorySplitText || $t('pay_expenses_count', { count: expensesSummary.count || 0 }) }}</span>
                    </div>
                </div>
            </el-card>

            <el-card shadow="hover" class="stat-card">
                <div class="stat-inner">
                    <div class="stat-icon blue"><i class="fas fa-calendar-day"></i></div>
                    <div class="stat-details">
                        <h3>{{ formatCurrency(expensesSummary.today) }}</h3>
                        <p>{{ $t('pay_expenses_today') }}</p>
                        <span class="stat-sub">{{ $t('pay_expenses_count', { count: expensesSummary.today_count || 0 }) }}</span>
                    </div>
                </div>
            </el-card>

            <el-card
                shadow="hover"
                class="stat-card is-clickable"
                :class="{ 'is-active': expenseFilters.status === 'paid' }"
                @click="setExpenseStatus('paid')"
            >
                <div class="stat-inner">
                    <div class="stat-icon green"><i class="fas fa-circle-check"></i></div>
                    <div class="stat-details">
                        <h3>{{ formatCurrency(expensesSummary.by_status?.paid?.total || 0) }}</h3>
                        <p>{{ $t('pay_expenses_paid') }}</p>
                        <span class="stat-sub">{{ $t('pay_expenses_count', { count: expensesSummary.by_status?.paid?.count || 0 }) }}</span>
                    </div>
                </div>
            </el-card>

            <el-card
                shadow="hover"
                class="stat-card is-clickable"
                :class="{ 'is-active': expenseFilters.status === 'pending' }"
                @click="setExpenseStatus('pending')"
            >
                <div class="stat-inner">
                    <div class="stat-icon amber"><i class="fas fa-clock"></i></div>
                    <div class="stat-details">
                        <h3>{{ formatCurrency(expensesSummary.by_status?.pending?.total || 0) }}</h3>
                        <p>{{ $t('pay_expenses_pending') }}</p>
                        <span class="stat-sub">{{ $t('pay_expenses_count', { count: expensesSummary.by_status?.pending?.count || 0 }) }}</span>
                    </div>
                </div>
            </el-card>
        </AdminStatGrid>

        <!-- Cash by currency — only shown when on payments tab and multiple held -->
        <section v-if="tab === 'payments' && showWallets" class="wallets">
            <div v-for="wallet in store.wallets" :key="wallet.currency" class="wallet" :class="{ 'is-base': wallet.is_base }">
                <span class="wallet-code">{{ wallet.currency }}</span>
                <strong class="wallet-amount">{{ formatWalletTotal(wallet) }}</strong>
                <span class="stat-sub">{{ $t('pay_payments_n', { count: wallet.payments_count }) }}</span>
            </div>
        </section>

        <el-tabs v-model="tab" class="page-tabs" @tab-change="onTabChange">
            <el-tab-pane name="payments" :label="$t('pay_tab_payments')" />
            <el-tab-pane name="expenses" :label="$t('pay_tab_expenses')" />
        </el-tabs>

        <!-- ══ Payments ══ -->
        <section v-show="tab === 'payments'" class="panel-card">
            <div class="filters">
                <el-input
                    v-model="filters.search"
                    class="filter-search"
                    :placeholder="$t('pay_search_placeholder')"
                    :prefix-icon="Search"
                    clearable
                    @input="onSearchInput"
                />
                <el-select v-model="filters.method" class="filter-select" :placeholder="$t('spay_all_methods')" clearable @change="applyFilters">
                    <el-option v-for="m in PAYMENT_METHODS" :key="m" :label="paymentMethodLabel(m)" :value="m" />
                </el-select>
                <el-select v-model="filters.kind" class="filter-select" :placeholder="$t('pay_any_kind')" clearable @change="applyFilters">
                    <el-option value="invoice" :label="$t('pay_kind_invoice')" />
                    <el-option value="on_account" :label="$t('pay_on_account')" />
                    <el-option value="refund" :label="$t('pay_kind_refund')" />
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
                <el-tag v-if="filters.customer" closable size="large" class="customer-chip" @close="clearCustomer">
                    <i class="fas fa-user"></i> {{ $t('pay_customer_filter', { name: filters.customerName || `#${filters.customer}` }) }}
                </el-tag>
                <el-button v-if="activeFilterCount" text type="primary" :icon="RefreshLeft" @click="resetFilters">
                    {{ $t('prod_admin_clear_filters', { count: activeFilterCount }) }}
                </el-button>
            </div>

            <el-result v-if="store.error && !store.payments.length" icon="error" :title="store.error">
                <template #extra>
                    <el-button type="primary" :icon="Refresh" @click="fetchPayments">{{ $t('cat_admin_retry') }}</el-button>
                </template>
            </el-result>

            <el-table
                v-else
                v-loading="store.loading"
                :data="store.payments"
                row-key="id"
                style="width: 100%"
                class="payments-table"
                :default-sort="{ prop: sort.prop, order: sort.order }"
                @sort-change="onSortChange"
            >
                <template #empty>
                    <el-empty v-if="!store.loading && activeFilterCount" :description="$t('there_are_no_payments_matching')" :image-size="90">
                        <el-button @click="resetFilters">{{ $t('cat_admin_clear_filters') }}</el-button>
                    </el-empty>
                    <el-empty v-else-if="!store.loading" :description="$t('no_payments_yet')" :image-size="90">
                        <el-button type="primary" :icon="Plus" @click="paymentDialogVisible = true">{{ $t('record_payment') }}</el-button>
                    </el-empty>
                    <span v-else />
                </template>

                <el-table-column type="expand" width="36">
                    <template #default="{ row }">
                        <dl class="row-details">
                            <div>
                                <dt>{{ $t('pret_recorded_by') }}</dt>
                                <dd>{{ row.creator?.name || '—' }}</dd>
                            </div>
                            <div>
                                <dt>{{ $t('spay_recorded_at') }}</dt>
                                <dd>{{ formatDateTime(row.created_at) }}</dd>
                            </div>
                            <div v-if="row.tendered_amount !== null && row.tendered_amount !== undefined">
                                <dt>{{ $t('pay_tendered') }}</dt>
                                <dd dir="ltr">{{ formatCurrency(row.tendered_amount, row.currency) }}</dd>
                            </div>
                            <div v-if="row.invoice">
                                <dt>{{ $t('pay_invoice_left') }}</dt>
                                <dd>{{ formatCurrency(invoiceOwed(row)) }}</dd>
                            </div>
                            <div class="wide">
                                <dt>{{ $t('notes') }}</dt>
                                <dd>{{ row.notes || '—' }}</dd>
                            </div>
                        </dl>
                    </template>
                </el-table-column>

                <el-table-column prop="payment_date" :label="$t('payment_number')" min-width="140" sortable="custom">
                    <template #default="{ row }">
                        <div class="cell-stack">
                            <span class="mono" dir="ltr">{{ row.payment_number || '—' }}</span>
                            <span class="cell-secondary">{{ formatDate(row.payment_date || row.created_at) }}</span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('client')" min-width="170">
                    <template #default="{ row }">
                        <div class="cell-stack">
                            <el-tooltip v-if="row.customer_id && String(row.customer_id) !== filters.customer" :content="$t('pay_filter_by_customer')" placement="top" :enterable="false">
                                <button type="button" class="link-button plain" @click="filterByCustomer(row)">{{ customerName(row) }}</button>
                            </el-tooltip>
                            <span v-else class="strong">{{ customerName(row) }}</span>
                            <span v-if="row.customer?.phone" class="cell-secondary" dir="ltr">{{ row.customer.phone }}</span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('spay_applied_to')" min-width="160">
                    <template #default="{ row }">
                        <div class="cell-stack">
                            <span v-if="row.is_refund" class="kind-tag refund"><i class="fas fa-rotate-left"></i> {{ $t('pay_kind_refund') }}</span>
                            <button v-if="row.invoice" type="button" class="link-button" @click="goToInvoice(row.invoice)">
                                <span dir="ltr">{{ row.invoice.invoice_number }}</span>
                            </button>
                            <span v-else-if="!row.is_refund" class="kind-tag account">{{ $t('pay_on_account') }}</span>
                            <span v-if="row.invoice && !row.is_refund" class="cell-secondary" :class="invoiceState(row).cls">
                                {{ invoiceState(row).text }}
                            </span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('payment_method')" min-width="140">
                    <template #default="{ row }">
                        <div class="cell-stack">
                            <span class="method-tag" :class="`m-${row.payment_method}`">
                                <i class="fas" :class="methodIcon(row.payment_method)"></i>
                                {{ paymentMethodLabel(row.payment_method) }}
                            </span>
                            <span v-if="row.reference" class="cell-secondary" dir="auto">{{ row.reference }}</span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column prop="amount" :label="$t('amount')" min-width="130" align="right" sortable="custom">
                    <template #default="{ row }">
                        <strong class="amount" :class="row.is_refund ? 'out' : 'in'">
                            {{ row.is_refund ? '−' : '' }}{{ formatCurrency(Math.abs(Number(row.amount))) }}
                        </strong>
                        <span v-if="isForeign(row)" class="cell-secondary tendered" dir="ltr">
                            {{ formatCurrency(Math.abs(Number(row.tendered_amount)), row.currency) }}
                        </span>
                    </template>
                </el-table-column>

                <el-table-column width="96" align="center">
                    <template #default="{ row }">
                        <template v-if="row.can_change">
                            <el-tooltip :content="$t('edit')" placement="top" :enterable="false">
                                <el-button size="small" circle text :aria-label="$t('edit')" @click="openEdit(row)"><i class="fas fa-pen"></i></el-button>
                            </el-tooltip>
                            <el-tooltip :content="$t('pay_reverse')" placement="top" :enterable="false">
                                <el-button size="small" circle text type="danger" :aria-label="$t('pay_reverse')" @click="reversePayment(row)">
                                    <i class="fas fa-rotate-left"></i>
                                </el-button>
                            </el-tooltip>
                        </template>
                        <el-tooltip v-else :content="$t('pay_refund_locked')" placement="top" :enterable="false">
                            <i class="fas fa-lock locked"></i>
                        </el-tooltip>
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
                    :page-sizes="[10, 20, 50, 100]"
                    :layout="isNarrow ? 'prev, pager, next' : 'sizes, prev, pager, next'"
                    background
                    @size-change="onPageChange(true)"
                    @current-change="onPageChange(false)"
                />
            </div>
        </section>

        <!-- ══ Expenses ══ -->
        <section v-show="tab === 'expenses'" class="panel-card">
            <div class="filters">
                <el-input
                    v-model="expenseFilters.search"
                    class="filter-search"
                    :placeholder="$t('pay_expense_search')"
                    :prefix-icon="Search"
                    clearable
                    @input="onExpenseSearchInput"
                />
                <el-select
                    v-model="expenseFilters.category"
                    class="filter-select"
                    :placeholder="$t('pay_all_categories')"
                    clearable
                    @change="applyExpenseFilters"
                >
                    <el-option value="shipping" :label="$t('shipping')" />
                    <el-option value="packaging" :label="$t('packaging')" />
                    <el-option value="handling" :label="$t('process')" />
                    <el-option value="other" :label="$t('subject_other')" />
                </el-select>
                <el-select
                    v-model="expenseFilters.status"
                    class="filter-select"
                    :placeholder="$t('pay_all_statuses')"
                    clearable
                    @change="applyExpenseFilters"
                >
                    <el-option value="paid" :label="$t('expense_paid')" />
                    <el-option value="pending" :label="$t('expense_pending')" />
                    <el-option value="approved" :label="$t('pay_expense_status_approved')" />
                    <el-option value="rejected" :label="$t('pay_expense_status_rejected')" />
                </el-select>
                <el-date-picker
                    v-model="expenseFilters.range"
                    type="daterange"
                    class="filter-dates"
                    value-format="YYYY-MM-DD"
                    format="YYYY-MM-DD"
                    unlink-panels
                    :start-placeholder="$t('pret_from')"
                    :end-placeholder="$t('pret_to')"
                    :shortcuts="dateShortcuts"
                    @change="applyExpenseFilters"
                />
                <el-button v-if="activeExpenseFilterCount" text type="primary" :icon="RefreshLeft" @click="resetExpenseFilters">
                    {{ $t('prod_admin_clear_filters', { count: activeExpenseFilterCount }) }}
                </el-button>
                <div class="expenses-stat-badge">
                    <span>{{ $t('pay_expenses_total', { amount: formatCurrency(expensesSummary.total) }) }}</span>
                </div>
            </div>

            <el-alert v-if="expensesError && !expenses.length" type="error" show-icon :closable="false" :title="expensesError">
                <template #default>
                    <el-button type="primary" size="small" :icon="Refresh" style="margin-top: 0.5rem" @click="fetchExpenses">
                        {{ $t('cat_admin_retry') }}
                    </el-button>
                </template>
            </el-alert>

            <el-table
                v-else
                v-loading="expensesLoading"
                :data="expenses"
                row-key="id"
                style="width: 100%"
                class="expenses-table"
                :default-sort="{ prop: expenseSort.prop, order: expenseSort.order }"
                @sort-change="onExpenseSortChange"
            >
                <template #empty>
                    <el-empty v-if="!expensesLoading && activeExpenseFilterCount" :description="$t('there_are_no_expenses_matching')" :image-size="90">
                        <el-button @click="resetExpenseFilters">{{ $t('cat_admin_clear_filters') }}</el-button>
                    </el-empty>
                    <el-empty v-else-if="!expensesLoading" :description="$t('there_are_no_expenses_matching')" :image-size="90">
                        <el-button type="primary" :icon="Plus" @click="openExpenseDialog()">{{ $t('add_expense') }}</el-button>
                    </el-empty>
                    <span v-else />
                </template>

                <el-table-column type="expand" width="36">
                    <template #default="{ row }">
                        <dl class="row-details">
                            <div>
                                <dt>{{ $t('pret_recorded_by') }}</dt>
                                <dd>{{ row.creator?.name || '—' }}</dd>
                            </div>
                            <div>
                                <dt>{{ $t('spay_recorded_at') }}</dt>
                                <dd>{{ formatDateTime(row.created_at) }}</dd>
                            </div>
                            <div>
                                <dt>{{ $t('pay_expense_accounting_effect') }}</dt>
                                <dd>
                                    <span v-if="row.status === 'paid'" class="effect-tag success">
                                        <i class="fas fa-check-circle"></i> {{ $t('pay_expense_paid_desc') }}
                                    </span>
                                    <span v-else-if="row.status === 'pending'" class="effect-tag warning">
                                        <i class="fas fa-clock"></i> {{ $t('pay_expense_pending_desc') }}
                                    </span>
                                    <span v-else-if="row.status === 'approved'" class="effect-tag primary">
                                        <i class="fas fa-thumbs-up"></i> {{ $t('pay_expense_approved_desc') }}
                                    </span>
                                    <span v-else class="effect-tag danger">
                                        <i class="fas fa-ban"></i> {{ $t('pay_expense_rejected_desc') }}
                                    </span>
                                </dd>
                            </div>
                            <div v-if="row.customer">
                                <dt>{{ $t('client') }}</dt>
                                <dd>{{ row.customer.name }} {{ row.customer.phone ? `(${row.customer.phone})` : '' }}</dd>
                            </div>
                            <div v-if="row.sales_order || row.salesOrder">
                                <dt>{{ $t('sales_order') }}</dt>
                                <dd>
                                    <button type="button" class="link-button" @click="goToSalesOrder(row.sales_order || row.salesOrder)">
                                        <i class="fas fa-file-lines"></i> {{ (row.sales_order || row.salesOrder).order_number }}
                                    </button>
                                </dd>
                            </div>
                            <div v-if="row.invoice">
                                <dt>{{ $t('invoice') }}</dt>
                                <dd>
                                    <button type="button" class="link-button" @click="goToInvoice(row.invoice)">
                                        {{ row.invoice.invoice_number }} ({{ formatCurrency(row.invoice.total) }})
                                    </button>
                                </dd>
                            </div>
                            <div class="wide">
                                <dt>{{ $t('notes') }}</dt>
                                <dd>{{ row.notes || '—' }}</dd>
                            </div>
                        </dl>
                    </template>
                </el-table-column>

                <el-table-column prop="expense_date" :label="$t('expense_number')" min-width="150" sortable="custom">
                    <template #default="{ row }">
                        <div class="cell-stack">
                            <span class="mono" dir="ltr">{{ row.expense_number || '—' }}</span>
                            <span class="cell-secondary">{{ formatDate(row.expense_date || row.created_at) }}</span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('description')" min-width="210">
                    <template #default="{ row }">
                        <div class="cell-stack">
                            <strong class="strong">{{ row.description }}</strong>
                            <span v-if="row.notes" class="cell-secondary text-truncate" :title="row.notes">{{ row.notes }}</span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('category')" min-width="130">
                    <template #default="{ row }">
                        <span class="expense-cat-tag" :class="`cat-${row.category}`">
                            <i :class="categoryIcon(row.category)"></i>
                            {{ expenseCategoryLabel(row.category) }}
                        </span>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('spay_applied_to')" min-width="160">
                    <template #default="{ row }">
                        <div class="cell-stack">
                            <button v-if="row.sales_order || row.salesOrder" type="button" class="link-button" @click="goToSalesOrder(row.sales_order || row.salesOrder)">
                                <i class="fas fa-file-lines"></i> <span dir="ltr">{{ (row.sales_order || row.salesOrder).order_number }}</span>
                            </button>
                            <button v-if="row.invoice" type="button" class="link-button" @click="goToInvoice(row.invoice)">
                                <i class="fas fa-file-invoice"></i> <span dir="ltr">{{ row.invoice.invoice_number }}</span>
                            </button>
                            <span v-else-if="row.customer" class="cell-secondary">
                                <i class="fas fa-user"></i> {{ row.customer.name }}
                            </span>
                            <span v-else-if="!(row.sales_order || row.salesOrder)" class="cell-secondary">—</span>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('expense_status')" min-width="120">
                    <template #default="{ row }">
                        <el-tag :type="statusTagType(row.status)" size="small" class="expense-status-tag" round>
                            <i :class="statusIcon(row.status)"></i>
                            {{ expenseStatusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column prop="amount" :label="$t('amount')" min-width="130" align="right" sortable="custom">
                    <template #default="{ row }">
                        <strong class="amount out">
                            − {{ formatCurrency(row.amount) }}
                        </strong>
                    </template>
                </el-table-column>

                <el-table-column width="120" align="center">
                    <template #default="{ row }">
                        <div class="actions-row">
                            <el-tooltip v-if="row.status === 'pending'" :content="$t('pay_expense_mark_paid')" placement="top" :enterable="false">
                                <el-button size="small" circle text type="success" :aria-label="$t('pay_expense_mark_paid')" @click="markExpenseAsPaid(row)">
                                    <i class="fas fa-check"></i>
                                </el-button>
                            </el-tooltip>
                            <el-tooltip :content="$t('edit')" placement="top" :enterable="false">
                                <el-button size="small" circle text :aria-label="$t('edit')" @click="openExpenseDialog(row)">
                                    <i class="fas fa-pen"></i>
                                </el-button>
                            </el-tooltip>
                            <el-tooltip :content="$t('delete')" placement="top" :enterable="false">
                                <el-button size="small" circle text type="danger" :aria-label="$t('delete')" @click="deleteExpense(row)">
                                    <i class="fas fa-trash-can"></i>
                                </el-button>
                            </el-tooltip>
                        </div>
                    </template>
                </el-table-column>
            </el-table>

            <div v-if="expensesTotalCount > 0" class="pagination-row">
                <span class="cell-secondary">
                    {{ $t('prod_admin_range', { from: expenseRangeFrom, to: expenseRangeTo, total: expensesTotalCount }) }}
                </span>
                <el-pagination
                    v-model:current-page="expenseCurrentPage"
                    v-model:page-size="expensePageSize"
                    :total="expensesTotalCount"
                    :page-sizes="[10, 20, 50, 100]"
                    :layout="isNarrow ? 'prev, pager, next' : 'sizes, prev, pager, next'"
                    background
                    @size-change="onExpensePageChange(true)"
                    @current-change="onExpensePageChange(false)"
                />
            </div>
        </section>

        <!-- Dialogs -->
        <QuickPaymentDialog v-model="paymentDialogVisible" @saved="onPaymentSaved" />

        <ExpenseDialog
            v-model="expenseDialogVisible"
            :expense="editingExpense"
            @saved="onExpenseSaved"
        />

        <!-- Correct a payment: what it was, never who or which invoice -->
        <el-dialog v-model="editVisible" :title="$t('pay_edit_title', { number: editing?.payment_number || '' })" :width="isNarrow ? '94%' : '460px'" :close-on-click-modal="false">
            <el-form v-if="editing" label-position="top" @submit.prevent>
                <div class="edit-context">
                    <span>{{ customerName(editing) }}</span>
                    <span v-if="editing.invoice" dir="ltr">{{ editing.invoice.invoice_number }}</span>
                    <span v-else>{{ $t('pay_on_account') }}</span>
                </div>
                <el-form-item :label="$t('amount')">
                    <el-input-number v-model="editForm.amount" :min="0.01" :precision="2" :controls="false" :disabled="editing.amount_locked" style="width: 100%" />
                    <p v-if="editing.amount_locked" class="field-hint">
                        {{ editing.invoice?.status === 'cancelled' ? $t('pay_amount_locked_cancelled') : $t('pay_amount_locked_currency') }}
                    </p>
                    <p v-else-if="editing.invoice" class="field-hint">{{ $t('pay_amount_max', { amount: formatCurrency(editMax) }) }}</p>
                </el-form-item>
                <el-form-item :label="$t('payment_method')">
                    <el-radio-group v-model="editForm.payment_method">
                        <el-radio-button v-for="m in PAYMENT_METHODS" :key="m" :value="m">{{ paymentMethodLabel(m) }}</el-radio-button>
                    </el-radio-group>
                </el-form-item>
                <div class="edit-row">
                    <el-form-item :label="$t('payment_date')">
                        <el-date-picker v-model="editForm.payment_date" type="date" value-format="YYYY-MM-DD" format="YYYY-MM-DD" style="width: 100%" />
                    </el-form-item>
                    <el-form-item :label="$t('payment_reference')">
                        <el-input v-model="editForm.reference" maxlength="100" />
                    </el-form-item>
                </div>
                <el-form-item :label="$t('notes')">
                    <el-input v-model="editForm.notes" type="textarea" :rows="2" maxlength="1000" />
                </el-form-item>
                <el-alert v-if="editAmountChanged" type="info" :closable="false" show-icon :title="$t('pay_edit_effect')" />
            </el-form>
            <template #footer>
                <el-button @click="editVisible = false">{{ $t('cancel') }}</el-button>
                <el-button type="primary" :loading="store.saving" :disabled="!!editBlocker" @click="saveEdit">{{ $t('save_changes') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Refresh, RefreshLeft, Search } from '@element-plus/icons-vue';
import { usePaymentsStore } from '@/stores/payments';
import { expensesApi } from '@/api/expenses';
import QuickPaymentDialog from '@/components/admin/sales/QuickPaymentDialog.vue';
import ExpenseDialog from '@/components/admin/sales/ExpenseDialog.vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';
import {
    PAYMENT_METHODS,
    apiErrorMessage,
    customerName,
    formatCurrency,
    formatDate,
    localIsoDate,
    normalizeStatus,
    paymentMethodLabel,
} from '@/utils/sales';
import { formatMoney } from '@/utils/currency';

const { t, locale } = useI18n();
const route = useRoute();
const router = useRouter();
const store = usePaymentsStore();

const METHOD_ICONS = { cash: 'fa-money-bill-wave', card: 'fa-credit-card', bank_transfer: 'fa-building-columns', check: 'fa-money-check' };
const methodIcon = (method) => METHOD_ICONS[method] || 'fa-coins';

/** Each wallet is written in its own currency's precision — never the base's. */
const formatWalletTotal = (wallet) => formatMoney(wallet.total, { code: wallet.currency, decimals: wallet.decimal_places });
const showWallets = computed(() => store.wallets.length > 1 || store.wallets.some((w) => !w.is_base));

const goToInvoice = (invoice) => router.push(`/admin/sales/invoices/${invoice.id}/edit`);
const goToSalesOrder = (so) => router.push({ path: '/admin/sales/sales-orders', query: { open: so.id } });

// What the invoice still owes, net of credit notes (sent by the server); the
// old reading, total less paid, is only a fallback for a stale row.
const invoiceOwed = (row) => (row.invoice_owed !== null && row.invoice_owed !== undefined
    ? Number(row.invoice_owed)
    : Math.max(0, Number(row.invoice?.total) - Number(row.invoice?.paid_amount)));

const invoiceState = (row) => {
    if (row.invoice?.status === 'cancelled') return { cls: 'is-cancelled', text: t('pay_invoice_cancelled') };
    const owed = invoiceOwed(row);
    return owed > 0.009
        ? { cls: 'is-owing', text: t('pay_invoice_owes', { amount: formatCurrency(owed) }) }
        : { cls: 'is-settled', text: t('pay_invoice_settled') };
};

const isForeign = (row) => row.tendered_amount !== null && row.tendered_amount !== undefined
    && row.currency && !store.wallets.find((w) => w.currency === row.currency)?.is_base;

// ── Summary ──────────────────────────────────────────────────────────────
const summary = computed(() => store.summary || {
    collected: 0, collected_count: 0, by_method: {}, on_account: 0, on_account_count: 0,
    today: 0, today_count: 0, refunded: 0, refunded_count: 0, net: 0,
});

const methodSplit = computed(() => {
    const byMethod = summary.value.by_method || {};
    const total = Object.values(byMethod).reduce((sum, m) => sum + Number(m.total || 0), 0);
    if (!total) return [];
    return Object.entries(byMethod)
        .map(([method, m]) => ({ method, total: Number(m.total || 0), share: Number(m.total || 0) / total }))
        .filter((p) => p.total > 0)
        .sort((a, b) => b.total - a.total);
});

const methodSplitText = computed(() => (methodSplit.value.length > 1
    ? methodSplit.value.map((p) => `${paymentMethodLabel(p.method)} ${Math.round(p.share * 100)}%`).join(' · ')
    : ''));

const periodLabel = computed(() => {
    if (!filters.range?.length) return t('spay_all_time');
    const [from, to] = filters.range;
    return `${formatDate(from)} – ${formatDate(to)}`;
});

// ── Tabs ─────────────────────────────────────────────────────────────────
const tab = ref('payments');
let expensesLoaded = false;
const onTabChange = (name) => {
    writeQuery();
    if (name === 'expenses' && !expensesLoaded) fetchExpenses();
};

// ── Filters, sorting and paging for Payments ──────────────────────────────
const blankFilters = () => ({ search: '', method: '', kind: '', range: null, customer: '', customerName: '' });
const filters = reactive(blankFilters());
const sort = reactive({ prop: 'payment_date', order: 'descending' });
const currentPage = ref(1);
const pageSize = ref(20);

// ── Filters, sorting and paging for Expenses ──────────────────────────────
const blankExpenseFilters = () => ({ search: '', category: '', status: '', range: null });
const expenseFilters = reactive(blankExpenseFilters());
const expenseSort = reactive({ prop: 'expense_date', order: 'descending' });
const expenseCurrentPage = ref(1);
const expensePageSize = ref(20);
const expensesTotalCount = ref(0);

const readQuery = () => {
    const q = route.query;
    tab.value = q.tab === 'expenses' ? 'expenses' : 'payments';

    if (tab.value === 'expenses') {
        Object.assign(expenseFilters, {
            search: q.search ? String(q.search) : '',
            category: ['shipping', 'packaging', 'handling', 'other'].includes(q.category) ? q.category : '',
            status: ['paid', 'pending', 'approved', 'rejected'].includes(q.status) ? q.status : '',
            range: q.from && q.to ? [String(q.from), String(q.to)] : null,
        });
        expenseSort.prop = q.sort === 'amount' ? 'amount' : (q.sort === 'expense_number' ? 'expense_number' : 'expense_date');
        expenseSort.order = q.direction === 'asc' ? 'ascending' : 'descending';
        expenseCurrentPage.value = Math.max(1, Number(q.page) || 1);
        expensePageSize.value = [10, 20, 50, 100].includes(Number(q.per_page)) ? Number(q.per_page) : 20;
    } else {
        Object.assign(filters, {
            search: q.search ? String(q.search) : '',
            method: PAYMENT_METHODS.includes(q.method) ? q.method : '',
            kind: ['invoice', 'on_account', 'refund'].includes(q.kind) ? q.kind : '',
            range: q.from && q.to ? [String(q.from), String(q.to)] : null,
            customer: /^\d+$/.test(String(q.customer || '')) ? String(q.customer) : '',
            customerName: q.customer_name ? String(q.customer_name) : '',
        });
        sort.prop = q.sort === 'amount' ? 'amount' : 'payment_date';
        sort.order = q.direction === 'asc' ? 'ascending' : 'descending';
        currentPage.value = Math.max(1, Number(q.page) || 1);
        pageSize.value = [10, 20, 50, 100].includes(Number(q.per_page)) ? Number(q.per_page) : 20;
    }
};

const queryKey = (query) => Object.entries(query).map(([k, v]) => `${k}=${v}`).sort().join('&');
let lastQueryKey = null;

const writeQuery = () => {
    let query;
    if (tab.value === 'expenses') {
        query = {
            tab: 'expenses',
            search: expenseFilters.search || undefined,
            category: expenseFilters.category || undefined,
            status: expenseFilters.status || undefined,
            from: expenseFilters.range?.[0] || undefined,
            to: expenseFilters.range?.[1] || undefined,
            sort: expenseSort.prop !== 'expense_date' ? expenseSort.prop : undefined,
            direction: expenseSort.order === 'ascending' ? 'asc' : undefined,
            page: expenseCurrentPage.value > 1 ? expenseCurrentPage.value : undefined,
            per_page: expensePageSize.value !== 20 ? expensePageSize.value : undefined,
        };
    } else {
        query = {
            tab: undefined,
            search: filters.search || undefined,
            method: filters.method || undefined,
            kind: filters.kind || undefined,
            from: filters.range?.[0] || undefined,
            to: filters.range?.[1] || undefined,
            customer: filters.customer || undefined,
            customer_name: filters.customer ? filters.customerName || undefined : undefined,
            sort: sort.prop !== 'payment_date' ? sort.prop : undefined,
            direction: sort.order === 'ascending' ? 'asc' : undefined,
            page: currentPage.value > 1 ? currentPage.value : undefined,
            per_page: pageSize.value !== 20 ? pageSize.value : undefined,
        };
    }
    Object.keys(query).forEach((k) => query[k] === undefined && delete query[k]);
    lastQueryKey = queryKey(query);
    router.replace({ query });
};

const activeFilterCount = computed(() => [filters.search, filters.method, filters.kind, filters.range?.length ? '1' : '', filters.customer].filter(Boolean).length);

const fetchPayments = () => store.fetchPayments({
    with_summary: 1,
    page: currentPage.value,
    per_page: pageSize.value,
    search: filters.search.trim() || undefined,
    payment_method: filters.method || undefined,
    kind: filters.kind || undefined,
    date_from: filters.range?.[0] || undefined,
    date_to: filters.range?.[1] || undefined,
    customer_id: filters.customer || undefined,
    today: localIsoDate(),
    sort: sort.prop === 'amount' ? 'amount' : 'date',
    direction: sort.order === 'ascending' ? 'asc' : 'desc',
}).catch(() => {});

const reload = () => {
    if (tab.value === 'expenses') {
        fetchExpenses();
    } else {
        fetchPayments();
        store.fetchCurrencyWallets().catch(() => {});
    }
};

const applyFilters = () => {
    clearTimeout(searchTimer);
    currentPage.value = 1;
    writeQuery();
    fetchPayments();
};

let searchTimer = null;
const onSearchInput = (text) => {
    clearTimeout(searchTimer);
    if (!text) applyFilters();
    else searchTimer = setTimeout(applyFilters, 400);
};

const resetFilters = () => {
    Object.assign(filters, blankFilters());
    applyFilters();
};

const filterByCustomer = (row) => {
    filters.customer = String(row.customer_id);
    filters.customerName = customerName(row);
    applyFilters();
};

const clearCustomer = () => {
    filters.customer = '';
    filters.customerName = '';
    applyFilters();
};

const setKind = (kind) => {
    filters.kind = filters.kind === kind ? '' : kind;
    tab.value = 'payments';
    applyFilters();
};

const onSortChange = ({ prop, order }) => {
    sort.prop = order ? prop : 'payment_date';
    sort.order = order || 'descending';
    currentPage.value = 1;
    writeQuery();
    fetchPayments();
};

const onPageChange = (sizeChanged) => {
    if (sizeChanged) currentPage.value = 1;
    writeQuery();
    fetchPayments();
};

const rangeFrom = computed(() => (store.pagination.total ? (currentPage.value - 1) * pageSize.value + 1 : 0));
const rangeTo = computed(() => Math.min(currentPage.value * pageSize.value, store.pagination.total));

const dateShortcuts = computed(() => {
    const now = new Date();
    const at = (y, m, d) => localIsoDate(new Date(y, m, d));
    const y = now.getFullYear();
    const m = now.getMonth();
    return [
        { text: t('today'), value: () => [localIsoDate(now), localIsoDate(now)] },
        { text: t('pret_this_month'), value: () => [at(y, m, 1), localIsoDate(now)] },
        { text: t('pret_last_month'), value: () => [at(y, m - 1, 1), at(y, m, 0)] },
        { text: t('pret_this_year'), value: () => [at(y, 0, 1), localIsoDate(now)] },
    ];
});

// ── Recording, correcting and reversing Payments ──────────────────────────
const paymentDialogVisible = ref(false);

const onPaymentSaved = () => {
    currentPage.value = 1;
    writeQuery();
    fetchPayments();
    store.fetchCurrencyWallets().catch(() => {});
};

const editVisible = ref(false);
const editing = ref(null);
const editForm = reactive({ amount: 0, payment_method: 'cash', payment_date: '', reference: '', notes: '' });

const openEdit = (payment) => {
    editing.value = payment;
    Object.assign(editForm, {
        amount: Number(payment.amount),
        payment_method: payment.payment_method,
        payment_date: String(payment.payment_date || payment.created_at || '').slice(0, 10),
        reference: payment.reference || '',
        notes: payment.notes || '',
    });
    editVisible.value = true;
};

const editAmountChanged = computed(() => editing.value && Math.abs(Number(editForm.amount) - Number(editing.value.amount)) > 0.009);

const editMax = computed(() => {
    if (!editing.value?.invoice) return Infinity;
    return invoiceOwed(editing.value) + Number(editing.value.amount);
});

const editBlocker = computed(() => {
    if (!(Number(editForm.amount) > 0)) return t('enter_amount_above_zero');
    if (Number(editForm.amount) - editMax.value > 0.009) return t('pay_amount_max', { amount: formatCurrency(editMax.value) });
    return '';
});

const saveEdit = async () => {
    if (editBlocker.value) return;
    try {
        await store.updatePayment(editing.value.id, { ...editForm });
        ElMessage.success(t('pay_saved'));
        editVisible.value = false;
        fetchPayments();
    } catch (error) {
        ElMessage.error(apiErrorMessage(error, t('pay_save_failed')));
    }
};

const reversePayment = async (payment) => {
    try {
        await ElMessageBox.confirm(
            payment.invoice
                ? t('pay_reverse_confirm_invoice', { number: payment.payment_number, amount: formatCurrency(payment.amount), invoice: payment.invoice.invoice_number })
                : t('pay_reverse_confirm', { number: payment.payment_number, amount: formatCurrency(payment.amount), customer: customerName(payment) }),
            t('pay_reverse'),
            { type: 'warning', confirmButtonText: t('pay_reverse'), cancelButtonText: t('pay_keep'), confirmButtonClass: 'el-button--danger' }
        );
    } catch {
        return;
    }

    try {
        await store.deletePayment(payment.id);
        ElMessage.success(t('pay_reversed'));
        fetchPayments();
        store.fetchCurrencyWallets().catch(() => {});
    } catch (error) {
        ElMessage.error(apiErrorMessage(error, t('failed_to_delete_payment')));
    }
};

// ── Expenses Logic & State ────────────────────────────────────────────────
const EXPENSE_CATEGORIES = ['shipping', 'packaging', 'handling', 'other'];
const EXPENSE_CATEGORY_LABELS = computed(() => ({
    shipping: t('shipping'),
    packaging: t('packaging'),
    handling: t('process'),
    other: t('subject_other'),
}));
const expenseCategoryLabel = (category) => EXPENSE_CATEGORY_LABELS.value[normalizeStatus(category)] || category || '—';

const CATEGORY_ICONS = {
    shipping: 'fas fa-truck',
    packaging: 'fas fa-box-open',
    handling: 'fas fa-dolly',
    other: 'fas fa-receipt',
};
const categoryIcon = (category) => CATEGORY_ICONS[normalizeStatus(category)] || 'fas fa-tag';

const EXPENSE_STATUS_LABELS = computed(() => ({
    paid: t('expense_paid'),
    pending: t('expense_pending'),
    approved: t('pay_expense_status_approved'),
    rejected: t('pay_expense_status_rejected'),
}));
const expenseStatusLabel = (status) => EXPENSE_STATUS_LABELS.value[normalizeStatus(status)] || status || '—';

const statusTagType = (status) => {
    switch (normalizeStatus(status)) {
        case 'paid': return 'success';
        case 'pending': return 'warning';
        case 'approved': return 'primary';
        case 'rejected': return 'danger';
        default: return 'info';
    }
};

const statusIcon = (status) => {
    switch (normalizeStatus(status)) {
        case 'paid': return 'fas fa-check';
        case 'pending': return 'fas fa-clock';
        case 'approved': return 'fas fa-thumbs-up';
        case 'rejected': return 'fas fa-ban';
        default: return 'fas fa-circle-question';
    }
};

const expenses = ref([]);
const expensesLoading = ref(false);
const expensesError = ref('');
const expensesSummary = ref({
    total: 0,
    count: 0,
    today: 0,
    today_count: 0,
    this_month: 0,
    this_month_count: 0,
    by_category: {},
    by_status: {},
});

const expenseCategorySplit = computed(() => {
    const byCat = expensesSummary.value.by_category || {};
    const total = expensesSummary.value.total || 0;
    if (!total) return [];
    return Object.entries(byCat)
        .map(([category, data]) => ({
            category,
            total: Number(data.total || 0),
            share: Number(data.share || (total > 0 ? Number(data.total || 0) / total : 0)),
        }))
        .filter((p) => p.total > 0)
        .sort((a, b) => b.total - a.total);
});

const expenseCategorySplitText = computed(() => (
    expenseCategorySplit.value.length > 1
        ? expenseCategorySplit.value.map((p) => `${expenseCategoryLabel(p.category)} ${Math.round(p.share * 100)}%`).join(' · ')
        : ''
));

const expensePeriodLabel = computed(() => {
    if (!expenseFilters.range?.length) return t('spay_all_time');
    const [from, to] = expenseFilters.range;
    return `${formatDate(from)} – ${formatDate(to)}`;
});

const activeExpenseFilterCount = computed(() =>
    [expenseFilters.search, expenseFilters.category, expenseFilters.status, expenseFilters.range?.length ? '1' : ''].filter(Boolean).length
);

const fetchExpenses = async () => {
    expensesLoading.value = true;
    expensesError.value = '';
    try {
        const res = await expensesApi.getAll({
            with_summary: 1,
            page: expenseCurrentPage.value,
            per_page: expensePageSize.value,
            search: expenseFilters.search.trim() || undefined,
            category: expenseFilters.category || undefined,
            status: expenseFilters.status || undefined,
            date_from: expenseFilters.range?.[0] || undefined,
            date_to: expenseFilters.range?.[1] || undefined,
            today: localIsoDate(),
            sort: expenseSort.prop === 'amount' ? 'amount' : (expenseSort.prop === 'expense_number' ? 'expense_number' : 'date'),
            direction: expenseSort.order === 'ascending' ? 'asc' : 'desc',
        });
        const payload = res.data?.data;
        if (payload) {
            expenses.value = payload.expenses || (Array.isArray(payload) ? payload : []);
            if (payload.summary) expensesSummary.value = payload.summary;
            if (payload.pagination) {
                expensesTotalCount.value = payload.pagination.total;
            } else {
                expensesTotalCount.value = expenses.value.length;
            }
        }
        expensesLoaded = true;
    } catch (error) {
        expensesError.value = apiErrorMessage(error, t('failed_to_load_expenses'));
    } finally {
        expensesLoading.value = false;
    }
};

let expenseSearchTimer = null;
const onExpenseSearchInput = (text) => {
    clearTimeout(expenseSearchTimer);
    if (!text) applyExpenseFilters();
    else expenseSearchTimer = setTimeout(applyExpenseFilters, 400);
};

const applyExpenseFilters = () => {
    clearTimeout(expenseSearchTimer);
    expenseCurrentPage.value = 1;
    writeQuery();
    fetchExpenses();
};

const resetExpenseFilters = () => {
    Object.assign(expenseFilters, blankExpenseFilters());
    applyExpenseFilters();
};

const setExpenseStatus = (status) => {
    expenseFilters.status = expenseFilters.status === status ? '' : status;
    applyExpenseFilters();
};

const onExpenseSortChange = ({ prop, order }) => {
    expenseSort.prop = order ? prop : 'expense_date';
    expenseSort.order = order || 'descending';
    expenseCurrentPage.value = 1;
    writeQuery();
    fetchExpenses();
};

const onExpensePageChange = (sizeChanged) => {
    if (sizeChanged) expenseCurrentPage.value = 1;
    writeQuery();
    fetchExpenses();
};

const expenseRangeFrom = computed(() => (expensesTotalCount.value ? (expenseCurrentPage.value - 1) * expensePageSize.value + 1 : 0));
const expenseRangeTo = computed(() => Math.min(expenseCurrentPage.value * expensePageSize.value, expensesTotalCount.value));

// Expense Dialog & Actions
const expenseDialogVisible = ref(false);
const editingExpense = ref(null);

const openExpenseDialog = (expense = null) => {
    editingExpense.value = expense;
    expenseDialogVisible.value = true;
};

const onExpenseSaved = () => {
    fetchExpenses();
};

const markExpenseAsPaid = async (row) => {
    try {
        await ElMessageBox.confirm(
            t('pay_expense_mark_paid_confirm', { number: row.expense_number || `#${row.id}`, amount: formatCurrency(row.amount) }),
            t('pay_expense_mark_paid'),
            { type: 'info', confirmButtonText: t('pay_expense_mark_paid'), cancelButtonText: t('cancel') }
        );
    } catch {
        return;
    }

    try {
        await expensesApi.update(row.id, { status: 'paid' });
        ElMessage.success(t('pay_expense_saved'));
        fetchExpenses();
    } catch (error) {
        ElMessage.error(apiErrorMessage(error, t('pay_save_failed')));
    }
};

const deleteExpense = async (row) => {
    try {
        await ElMessageBox.confirm(
            t('pay_expense_reverse_confirm', { number: row.expense_number || `#${row.id}`, amount: formatCurrency(row.amount) }),
            t('delete'),
            { type: 'warning', confirmButtonText: t('delete'), cancelButtonText: t('cancel'), confirmButtonClass: 'el-button--danger' }
        );
    } catch {
        return;
    }

    try {
        await expensesApi.delete(row.id);
        ElMessage.success(t('pay_expense_deleted'));
        fetchExpenses();
    } catch (error) {
        ElMessage.error(apiErrorMessage(error, t('pay_save_failed')));
    }
};

// ── Formatting, layout and lifecycle ─────────────────────────────────────
const formatDateTime = (value) => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return new Intl.DateTimeFormat(locale.value === 'en' ? 'en-GB' : 'ar-SY', {
        year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
    }).format(date);
};

const isNarrow = ref(false);
const onResize = () => { isNarrow.value = window.innerWidth < 768; };

watch(() => route.query, (query) => {
    if (route.name !== 'admin.payments.index' || queryKey(query) === lastQueryKey) return;
    readQuery();
    lastQueryKey = queryKey(query);
    if (tab.value === 'expenses') {
        fetchExpenses();
    } else {
        fetchPayments();
    }
});

onMounted(() => {
    onResize();
    window.addEventListener('resize', onResize);
    readQuery();
    lastQueryKey = queryKey(route.query);
    fetchPayments();
    store.fetchCurrencyWallets().catch(() => {});
    if (tab.value === 'expenses') fetchExpenses();
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', onResize);
    clearTimeout(searchTimer);
    clearTimeout(expenseSearchTimer);
});
</script>

<style scoped>
.payments-page { font-family: 'Cairo', sans-serif; }

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
.stat-icon.green { background: #f0fdf4; color: #16a34a; }
.stat-icon.blue { background: #eff6ff; color: #2563eb; }
.stat-icon.purple { background: #f5f3ff; color: #7c3aed; }
.stat-icon.red { background: #fef2f2; color: #dc2626; }
.stat-icon.amber { background: #fef3c7; color: #d97706; }
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

.method-bar { display: flex; height: 5px; border-radius: 999px; overflow: hidden; margin-top: 0.4rem; background: #f1f5f9; gap: 2px; }
.method-bar-part { min-width: 3px; background: var(--m); }
.m-cash { --m: #16a34a; }
.m-card { --m: #7c3aed; }
.m-bank_transfer { --m: #2563eb; }
.m-check { --m: #d97706; }

.cat-shipping { background: #2563eb !important; }
.cat-packaging { background: #d97706 !important; }
.cat-handling { background: #7c3aed !important; }
.cat-other { background: #64748b !important; }

/* ── Wallets ── */
.wallets { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 0.75rem; margin-bottom: 1.25rem; }
.wallet { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 0.7rem 0.9rem; display: grid; gap: 0.1rem; }
.wallet.is-base { border-color: #93c5fd; }
.wallet-code { font-size: 0.75rem; font-weight: 700; color: #64748b; }
.wallet-amount { font-size: 1.05rem; font-variant-numeric: tabular-nums; }

.page-tabs { margin-bottom: 0.25rem; }
.page-tabs :deep(.el-tabs__header) { margin-bottom: 0.75rem; }

/* ── Panel ── */
.panel-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.1rem; }
.filters { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; margin-bottom: 1rem; }
.filter-search { flex: 1 1 240px; max-width: 340px; }
.filter-select { width: 170px; }
.filter-dates { max-width: 270px; }
.expenses-stat-badge {
    margin-inline-start: auto;
    font-size: 0.85rem;
    font-weight: 700;
    color: #0f172a;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 0.35rem 0.75rem;
    border-radius: 8px;
}

/* ── Table ── */
.cell-stack { display: flex; flex-direction: column; gap: 0.15rem; min-width: 0; align-items: flex-start; }
.cell-secondary { display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.78rem; color: #64748b; }
.text-truncate {
    max-width: 280px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.mono { font-family: ui-monospace, monospace; font-weight: 700; color: #0f172a; font-size: 0.85rem; }
.strong { font-weight: 600; }
.amount { font-weight: 700; font-variant-numeric: tabular-nums; }
.amount.in { color: #15803d; }
.amount.out { color: #b91c1c; }

.link-button { all: unset; cursor: pointer; color: #2563eb; font-weight: 600; display: inline-flex; align-items: center; gap: 0.3rem; }
.link-button:hover { text-decoration: underline; }
.link-button.plain { color: #0f172a; }
.link-button.plain:hover { color: #2563eb; }
.cell-secondary.is-owing { color: #b45309; }
.cell-secondary.is-settled { color: #15803d; }
.cell-secondary.is-cancelled { color: #94a3b8; text-decoration: line-through; }
.tendered { display: block; margin-top: 0.1rem; }
.customer-chip { font-weight: 600; }
.customer-chip i { margin-inline-end: 0.3rem; }
.link-button:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; border-radius: 3px; }

.kind-tag { display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.76rem; font-weight: 600; padding: 0.05rem 0.5rem; border-radius: 999px; }
.kind-tag.account { color: #7c3aed; background: #f5f3ff; }
.kind-tag.refund { color: #b91c1c; background: #fef2f2; }

.method-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.1rem 0.55rem;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--m, #475569);
    background: color-mix(in srgb, var(--m, #475569) 10%, #fff);
}
.locked { color: #cbd5e1; }

/* ── Expense Category & Status Tags ── */
.expense-cat-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.12rem 0.6rem;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 600;
}
.expense-cat-tag.cat-shipping { color: #1d4ed8; background: #eff6ff; }
.expense-cat-tag.cat-packaging { color: #b45309; background: #fffbeb; }
.expense-cat-tag.cat-handling { color: #6d28d9; background: #f5f3ff; }
.expense-cat-tag.cat-other { color: #475569; background: #f1f5f9; }

.expense-status-tag {
    font-weight: 600;
    font-size: 0.76rem;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.actions-row {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.effect-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.76rem;
    font-weight: 600;
    padding: 0.15rem 0.5rem;
    border-radius: 6px;
}
.effect-tag.success { background: #f0fdf4; color: #15803d; }
.effect-tag.warning { background: #fffbeb; color: #b45309; }
.effect-tag.primary { background: #eff6ff; color: #1d4ed8; }
.effect-tag.danger { background: #fef2f2; color: #b91c1c; }

.row-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 0.75rem 1.25rem;
    margin: 0;
    padding: 0.5rem 1.25rem 0.75rem;
}
.row-details .wide { grid-column: 1 / -1; }
.row-details dt { font-size: 0.74rem; color: #64748b; }
.row-details dd { margin: 0.1rem 0 0; font-weight: 600; white-space: pre-line; }

.pagination-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-top: 1rem; }

/* ── Dialogs ── */
.edit-context { display: flex; justify-content: space-between; gap: 1rem; padding: 0.55rem 0.8rem; margin-bottom: 1rem; background: #f8fafc; border-radius: 8px; font-weight: 600; font-size: 0.88rem; }
.edit-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0 0.75rem; }
.field-hint { margin: 0.25rem 0 0; font-size: 0.76rem; color: #64748b; line-height: 1.4; }

@media (max-width: 768px) {
    :deep(.admin-stat-grid) { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: 0.6rem; }
    .stat-card :deep(.el-card__body) { padding: 0.75rem; }
    .stat-inner { gap: 0.6rem; }
    .stat-icon { width: 36px; height: 36px; font-size: 0.95rem; }
    .stat-details h3 { font-size: 1rem; }

    .panel-card { padding: 0.85rem; }
    .filter-search { max-width: none; flex-basis: 100%; }
    .filter-select { width: calc(50% - 0.375rem); }
    .filter-dates { max-width: none; width: 100% !important; }
    .expenses-stat-badge { width: 100%; text-align: center; }
    .pagination-row { justify-content: center; }
    .edit-row { grid-template-columns: 1fr; }
}
</style>
