<template>
    <div class="sales-page sales-orders">
        <!-- Modern Header -->
        <div class="page-header">
            <div class="page-title">
                <h1><i class="fas fa-shopping-cart text-primary"></i> {{ $t('sales_orders') || 'طلبات البيع' }}</h1>
                <p>{{ $t('view_current_orders_with_quick') || 'إدارة ومتابعة طلبات بيع العملاء وتحويلها إلى فواتير.' }}</p>
            </div>
            <div class="header-actions">
                <!-- Searching hits the API, so a match on any page is found. -->
                <el-input
                    v-model="searchQuery"
                    :placeholder="$t('search_by_order_number_or_customer_name') || 'ابحث برقم الطلب أو اسم العميل...'"
                    clearable
                    class="search-input"
                    :prefix-icon="Search"
                    @input="onSearchInput"
                    @keyup.enter="loadOrders(1)"
                    @clear="loadOrders(1)"
                />
                <el-button type="primary" class="create-btn" @click="openCreateDrawer">
                    <i class="fas fa-plus"></i> {{ $t('new_sales_order') || 'طلب بيع جديد' }}
                </el-button>
            </div>
        </div>

        <!-- Metric Cards. Counted over the whole table by the API, not over the
             loaded page — the old figures came from store.orders, so they only
             ever described the twenty rows that happened to be on screen. -->
        <el-row :gutter="16" class="overview-cards">
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="stat-card-wrapper">
                    <div class="stat-card-inner">
                        <div class="stat-icon-box blue-grad"><i class="fas fa-shopping-cart"></i></div>
                        <div class="stat-details">
                            <h3>{{ counts.all }}</h3>
                            <p>{{ $t('total_orders') || 'إجمالي طلبات البيع' }}</p>
                        </div>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="stat-card-wrapper">
                    <div class="stat-card-inner">
                        <div class="stat-icon-box orange-grad"><i class="fas fa-clock"></i></div>
                        <div class="stat-details">
                            <h3>{{ counts.pending }}</h3>
                            <p>بانتظار التأكيد</p>
                        </div>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="stat-card-wrapper">
                    <div class="stat-card-inner">
                        <div class="stat-icon-box purple-grad"><i class="fas fa-truck-fast"></i></div>
                        <div class="stat-details">
                            <h3>{{ counts.confirmed + counts.processing + counts.shipped }}</h3>
                            <p>قيد التنفيذ</p>
                        </div>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="6">
                <!-- The one card that is an instruction rather than a statistic. -->
                <el-card
                    shadow="hover"
                    class="stat-card-wrapper"
                    :class="{ 'attention-card': counts.overdue > 0, clickable: counts.overdue > 0 }"
                    @click="counts.overdue > 0 && showOverdue()"
                >
                    <div class="stat-card-inner">
                        <div class="stat-icon-box" :class="counts.overdue > 0 ? 'red-grad' : 'green-grad'">
                            <i class="fas" :class="counts.overdue > 0 ? 'fa-triangle-exclamation' : 'fa-circle-check'"></i>
                        </div>
                        <div class="stat-details">
                            <h3>{{ counts.overdue }}</h3>
                            <p>{{ counts.overdue > 0 ? 'متأخرة عن موعد التسليم' : 'لا توجد طلبات متأخرة' }}</p>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- Stage tabs: the pipeline as a filter, with counts across the table -->
        <el-tabs v-model="activeStage" class="stage-tabs" @tab-change="onStageChange">
            <el-tab-pane v-for="tab in stageTabs" :key="tab.name" :name="tab.name">
                <template #label>
                    <span class="stage-tab-label">
                        <i class="fas" :class="tab.icon"></i> {{ tab.label }}
                        <el-badge v-if="tab.count" :value="tab.count" :type="tab.badge" class="stage-badge" />
                    </span>
                </template>
            </el-tab-pane>
        </el-tabs>

        <!-- Main Card & Table -->
        <el-card shadow="hover" class="table-panel">
            <template #header>
                <div class="card-header">
                    <span><i class="fas fa-list text-muted"></i> {{ $t('list_of_sales_orders') || 'جدول طلبات البيع' }}</span>
                </div>
            </template>

            <div v-if="store.loading" class="loading-state">
                <el-skeleton :rows="6" animated />
            </div>
            <div v-else>
                <el-table
                    v-if="store.orders.length"
                    :data="store.orders"
                    style="width: 100%"
                    stripe
                    highlight-current-row
                    class="custom-table"
                    :row-class-name="rowClassName"
                >
                    <el-table-column prop="order_number" label="رقم الطلب" width="150">
                        <template #default="{ row }">
                            <span class="order-number-link" @click="openDetailDrawer(row.id)">{{ row.order_number }}</span>
                            <!-- Says why this row is flagged, rather than only that it is. -->
                            <el-tooltip
                                v-if="row.follow_up?.needs_attention"
                                :content="row.follow_up.attention_reasons.join(' — ')"
                                placement="top"
                            >
                                <i class="fas fa-triangle-exclamation attention-flag"></i>
                            </el-tooltip>
                        </template>
                    </el-table-column>
                    <el-table-column prop="customer.name" :label="$t('client') || 'العميل'">
                        <template #default="{ row }">
                            <div class="customer-info-cell">
                                <i class="fas fa-user-circle text-muted"></i>
                                <span>{{ row.customer?.name || '-' }}</span>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column prop="total" :label="$t('total') || 'المبلغ الإجمالي'" width="160">
                        <template #default="{ row }">
                            <strong class="total-amount">{{ formatCurrency(row.total) }}</strong>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('status') || 'حالة الطلب'" width="150" align="center">
                        <template #default="{ row }">
                            <el-tag :type="statusTagType(row.status)" effect="light" class="status-tag">
                                <i class="fas status-dot-icon" :class="statusIconClass(row.status)"></i>
                                {{ getArabicStatus(row.status) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <!-- Where the order is being served from. Routing was previously
                         invisible on this screen even though it decides everything. -->
                    <el-table-column label="التوجيه" width="180">
                        <template #default="{ row }">
                            <div class="routing-cell">
                                <span><i class="fas fa-warehouse text-muted"></i> {{ row.fulfillment_warehouse?.name || '—' }}</span>
                                <el-tag size="small" effect="plain" :type="fulfillmentTagType(row.fulfillment_type)">
                                    {{ fulfillmentLabel(row.fulfillment_type) }}
                                </el-tag>
                            </div>
                        </template>
                    </el-table-column>
                    <!-- Status says where an order is; this says how long it has been
                         there, which is what makes a stall visible at all. -->
                    <el-table-column label="المتابعة" width="150" align="center">
                        <template #default="{ row }">
                            <div class="follow-up-cell">
                                <span :class="stageAgeClass(row.follow_up)">
                                    {{ stageAgeText(row.follow_up) }}
                                </span>
                                <span v-if="row.follow_up?.is_overdue" class="overdue-note">
                                    متأخر {{ row.follow_up.days_overdue }} يوم
                                </span>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column :label="$t('order_date') || 'التاريخ'" width="130" align="center">
                        <template #default="{ row }">{{ formatDate(row.order_date) }}</template>
                    </el-table-column>

                    <!-- Actions Column -->
                    <el-table-column label="الإجراءات" width="240" align="center">
                        <template #default="{ row }">
                            <el-button-group class="action-btn-group">
                                <el-button
                                    v-if="normalizeStatus(row.status) === 'pending'"
                                    size="small"
                                    type="primary"
                                    @click="openDetailDrawer(row.id)"
                                    title="تأكيد الطلب"
                                >
                                    <i class="fas fa-circle-check"></i>
                                </el-button>
                                <el-button size="small" type="info" plain @click="openDetailDrawer(row.id)" title="عرض التفاصيل">
                                    <i class="fas fa-eye"></i>
                                </el-button>
                                <el-button
                                    size="small"
                                    type="warning"
                                    plain
                                    :disabled="normalizeStatus(row.status) !== 'pending'"
                                    @click="openEditDrawer(row.id)"
                                    :title="normalizeStatus(row.status) === 'pending' ? 'تعديل' : 'لا يمكن تعديل طلب مؤكد'"
                                >
                                    <i class="fas fa-edit"></i>
                                </el-button>
                                <el-button
                                    size="small"
                                    type="danger"
                                    plain
                                    :disabled="!['pending', 'cancelled'].includes(normalizeStatus(row.status))"
                                    @click="deleteOrder(row.id)"
                                    :title="['pending', 'cancelled'].includes(normalizeStatus(row.status)) ? 'حذف' : 'ألغِ الطلب بدل حذفه'"
                                >
                                    <i class="fas fa-trash"></i>
                                </el-button>
                            </el-button-group>
                        </template>
                    </el-table-column>
                </el-table>

                <!-- Empty State -->
                <div v-if="!store.orders.length" class="empty-state-box">
                    <i class="fas fa-shopping-cart empty-icon"></i>
                    <p>{{ $t('there_are_no_requests_matching') || 'لا توجد طلبات بيع مطابقة حالياً.' }}</p>
                    <el-button type="primary" size="medium" @click="openCreateDrawer">
                        <i class="fas fa-plus"></i> إنشاء طلب جديد
                    </el-button>
                </div>

                <!-- Paging is server-side, so the list is no longer capped at the
                     first twenty rows with a search that only saw those. -->
                <div v-if="store.pagination.total > store.pagination.per_page" class="pagination-row">
                    <el-pagination
                        layout="prev, pager, next, total"
                        :total="store.pagination.total"
                        :current-page="store.pagination.current_page"
                        :page-size="store.pagination.per_page"
                        background
                        @current-change="onPageChange"
                    />
                </div>
            </div>
        </el-card>

        <!-- Detail Drawer -->
        <el-drawer
            v-model="detailDrawerVisible"
            size="64%"
            direction="rtl"
            destroy-on-close
            class="detail-drawer"
        >
            <template #header>
                <div class="drawer-title">
                    <i class="fas fa-file-lines"></i>
                    <span>تفاصيل طلب البيع</span>
                    <strong v-if="selectedOrder">{{ selectedOrder.order_number }}</strong>
                </div>
            </template>

            <div v-if="loadingDetail" v-loading="loadingDetail" style="min-height: 320px;"></div>
            <div v-else-if="selectedOrder" class="drawer-detail-content">
                <!-- Masthead: the four facts read first, before any detail -->
                <div class="order-masthead">
                    <div class="masthead-cell">
                        <span class="lbl">الحالة</span>
                        <el-tag :type="statusTagType(selectedOrder.status)" effect="dark" class="status-tag">
                            <i class="fas" :class="statusIconClass(selectedOrder.status)"></i>
                            {{ getArabicStatus(selectedOrder.status) }}
                        </el-tag>
                    </div>
                    <div class="masthead-cell">
                        <span class="lbl">العميل</span>
                        <strong>{{ selectedOrder.customer?.name || '—' }}</strong>
                    </div>
                    <div class="masthead-cell">
                        <span class="lbl">الإجمالي</span>
                        <strong class="amount">{{ formatCurrency(selectedOrder.total) }}</strong>
                    </div>
                    <div class="masthead-cell">
                        <span class="lbl">{{ invoice ? 'المتبقي على العميل' : 'الفاتورة' }}</span>
                        <strong v-if="invoice" :class="invoiceDueAmount > 0.01 ? 'text-danger' : 'text-success'">
                            {{ invoiceDueAmount > 0.01 ? formatCurrency(invoiceDueAmount) : 'مسددة بالكامل' }}
                        </strong>
                        <span v-else class="muted">لم تُنشأ بعد</span>
                    </div>
                </div>

                <!-- What is inconsistent about this order, stated plainly -->
                <div v-if="diagnostics.length" class="diagnostics-panel">
                    <div
                        v-for="issue in diagnostics"
                        :key="issue.code"
                        class="diagnostic"
                        :class="`level-${issue.level}`"
                    >
                        <i class="fas diagnostic-icon" :class="diagnosticIcon(issue.level)"></i>
                        <div class="diagnostic-body">
                            <strong>{{ issue.title }}</strong>
                            <p>{{ issue.detail }}</p>
                            <p v-if="issue.action" class="diagnostic-action">
                                <i class="fas fa-arrow-turn-down"></i> {{ issue.action }}
                            </p>
                        </div>
                    </div>
                </div>
                <div v-else class="diagnostics-clear">
                    <i class="fas fa-shield-check"></i>
                    السجلات متطابقة: الفاتورة والقيود المحاسبية وحركات المخزون كلها متسقة مع حالة الطلب.
                </div>

                <el-tabs v-model="detailTab" class="detail-tabs">
                    <!-- ---------------- Overview ---------------- -->
                    <el-tab-pane name="overview">
                        <template #label><i class="fas fa-list-ul"></i> الأصناف والمبالغ</template>

                        <el-table :data="selectedOrder.items || []" stripe class="items-table" style="width: 100%">
                            <el-table-column label="الصنف" min-width="200">
                                <template #default="{ row }">
                                    <strong>{{ row.product?.name_ar || row.product?.name_en || row.product?.name || '—' }}</strong>
                                    <p class="row-sub">{{ row.product?.sku || '—' }}</p>
                                </template>
                            </el-table-column>
                            <el-table-column label="الكمية" width="80" align="center">
                                <template #default="{ row }">{{ row.quantity }}</template>
                            </el-table-column>
                            <el-table-column label="سعر الوحدة" width="120" align="center">
                                <template #default="{ row }">{{ formatCurrency(row.unit_price) }}</template>
                            </el-table-column>
                            <el-table-column label="الخصم" width="100" align="center">
                                <template #default="{ row }">
                                    <span :class="{ muted: !toNum(row.discount) }">{{ formatCurrency(row.discount) }}</span>
                                </template>
                            </el-table-column>
                            <el-table-column label="الضريبة" width="100" align="center">
                                <template #default="{ row }">
                                    <span :class="{ muted: !toNum(row.tax) }">{{ formatCurrency(row.tax) }}</span>
                                </template>
                            </el-table-column>
                            <el-table-column label="الإجمالي" width="130" align="center">
                                <template #default="{ row }"><strong>{{ formatCurrency(lineTotal(row)) }}</strong></template>
                            </el-table-column>
                        </el-table>

                        <!-- Amounts, in the order they build up to the total -->
                        <div class="amounts-block">
                            <div class="amount-row">
                                <span>إجمالي الأصناف</span><span>{{ formatCurrency(selectedOrder.subtotal) }}</span>
                            </div>
                            <div class="amount-row" v-if="toNum(selectedOrder.discount)">
                                <span>ناقصاً: الخصم</span><span class="text-danger">({{ formatCurrency(selectedOrder.discount) }})</span>
                            </div>
                            <div class="amount-row" v-if="toNum(selectedOrder.tax)">
                                <span>زائداً: الضريبة</span><span>{{ formatCurrency(selectedOrder.tax) }}</span>
                            </div>
                            <div class="amount-row" v-if="toNum(selectedOrder.shipping_cost)">
                                <span>زائداً: تكلفة الشحن</span><span>{{ formatCurrency(selectedOrder.shipping_cost) }}</span>
                            </div>
                            <div class="amount-row grand">
                                <span>الإجمالي الكلي</span><span>{{ formatCurrency(selectedOrder.total) }}</span>
                            </div>
                            <template v-if="invoice">
                                <div class="amount-row paid">
                                    <span>المحصَّل</span><span>{{ formatCurrency(invoice.paid_amount) }}</span>
                                </div>
                                <div class="amount-row due" :class="invoiceDueAmount > 0.01 ? 'unpaid' : 'settled'">
                                    <span>المتبقي</span><span>{{ formatCurrency(invoiceDueAmount) }}</span>
                                </div>
                            </template>
                        </div>

                        <el-row :gutter="16" class="mt-4">
                            <el-col :xs="24" :md="12">
                                <el-card shadow="never" class="info-card">
                                    <template #header><span class="card-title-txt"><i class="fas fa-user-circle"></i> العميل</span></template>
                                    <div class="info-list">
                                        <div class="info-item"><span class="lbl">الاسم</span><strong>{{ selectedOrder.customer?.name || '—' }}</strong></div>
                                        <div class="info-item" v-if="selectedOrder.customer?.company"><span class="lbl">الشركة</span><strong>{{ selectedOrder.customer.company }}</strong></div>
                                        <div class="info-item" v-if="selectedOrder.customer?.phone"><span class="lbl">الهاتف</span><strong dir="ltr">{{ selectedOrder.customer.phone }}</strong></div>
                                        <div class="info-item" v-if="selectedOrder.customer?.email"><span class="lbl">البريد</span><strong dir="ltr">{{ selectedOrder.customer.email }}</strong></div>
                                    </div>
                                </el-card>
                            </el-col>
                            <el-col :xs="24" :md="12">
                                <el-card shadow="never" class="info-card">
                                    <template #header><span class="card-title-txt"><i class="fas fa-truck"></i> التسليم</span></template>
                                    <div class="info-list">
                                        <div class="info-item"><span class="lbl">العنوان</span><strong>{{ shippingAddressText || '—' }}</strong></div>
                                        <div class="info-item"><span class="lbl">تاريخ الطلب</span><strong>{{ formatDate(selectedOrder.order_date) }}</strong></div>
                                        <div class="info-item"><span class="lbl">التسليم المتوقع</span><strong>{{ formatDate(selectedOrder.expected_delivery) }}</strong></div>
                                        <div class="info-item" v-if="selectedOrder.carrier"><span class="lbl">شركة الشحن</span><strong>{{ selectedOrder.carrier }}</strong></div>
                                        <div class="info-item" v-if="selectedOrder.tracking_number"><span class="lbl">رقم التتبع</span><strong dir="ltr">{{ selectedOrder.tracking_number }}</strong></div>
                                    </div>
                                </el-card>
                            </el-col>
                        </el-row>

                        <el-card v-if="selectedOrder.notes" shadow="never" class="info-card mt-3">
                            <template #header><span class="card-title-txt"><i class="fas fa-sticky-note"></i> ملاحظات</span></template>
                            <p class="notes-txt-view">{{ selectedOrder.notes }}</p>
                        </el-card>
                    </el-tab-pane>

                    <!-- ---------------- Execution ---------------- -->
                    <el-tab-pane name="execution">
                        <template #label><i class="fas fa-diagram-project"></i> التنفيذ والتوجيه</template>

                        <!-- Numbered stage tracker. The number is the point: it
                             says how far along the order is and how far is left,
                             which an icon alone never did. -->
                        <div class="stage-timeline">
                            <div
                                v-for="(step, i) in timelineSteps"
                                :key="step.key"
                                class="stage-step"
                                :class="{ done: step.done, current: step.current, skipped: isCancelled }"
                            >
                                <div class="step-marker">
                                    <span v-if="step.done && !step.current" class="step-check"><i class="fas fa-check"></i></span>
                                    <span v-else class="step-number">{{ i + 1 }}</span>
                                </div>
                                <div class="step-text">
                                    <strong>{{ step.label }}</strong>
                                    <span class="step-date">{{ step.at ? formatDate(step.at) : '—' }}</span>
                                    <span v-if="step.current" class="step-now">المرحلة الحالية</span>
                                </div>
                                <div v-if="i < timelineSteps.length - 1" class="step-connector" :class="{ filled: step.done }"></div>
                            </div>
                        </div>

                        <!-- What this stage produces, and what the next one needs -->
                        <div v-if="nextStage" class="next-stage-bar">
                            <div class="next-stage-head">
                                <span class="next-badge">{{ currentStageNumber }} من {{ timelineSteps.length }}</span>
                                <strong>التالي: {{ nextStage.label }}</strong>
                            </div>
                            <p class="next-stage-effect"><i class="fas fa-arrow-turn-down"></i> {{ nextStage.effect }}</p>
                        </div>
                        <el-alert v-if="isCancelled" type="info" show-icon :closable="false" class="mb-3"
                            title="هذا الطلب ملغي — عُكست آثاره المخزنية والمحاسبية." />

                        <!-- Follow-up: how long the order has sat where it is -->
                        <div class="follow-up-bar" :class="followUpClass">
                            <i class="fas" :class="followUp.needs_attention ? 'fa-triangle-exclamation' : 'fa-hourglass-half'"></i>
                            <div>
                                <strong v-if="followUp.attention_reasons?.length">{{ followUp.attention_reasons.join(' — ') }}</strong>
                                <strong v-else-if="!followUp.is_open">اكتمل مسار هذا الطلب.</strong>
                                <strong v-else>في هذه المرحلة {{ stageAgeText(followUp) }}.</strong>
                                <p v-if="followUp.is_open && followUp.stage_threshold_days">
                                    الحد المعتاد لهذه المرحلة {{ followUp.stage_threshold_days }} يوم.
                                </p>
                            </div>
                        </div>

                        <!-- The append-only record of who moved this order and why -->
                        <el-card shadow="never" class="info-card mb-3">
                            <template #header><span class="card-title-txt"><i class="fas fa-clock-rotate-left"></i> سجل المراحل</span></template>
                            <div v-if="history.length" class="history-list">
                                <div v-for="entry in history" :key="entry.id" class="history-entry">
                                    <span class="history-dot" :class="`dot-${entry.to_status}`"></span>
                                    <div class="history-body">
                                        <div class="history-head">
                                            <strong>
                                                <template v-if="entry.from_status">
                                                    {{ getArabicStatus(entry.from_status) }} ← {{ getArabicStatus(entry.to_status) }}
                                                </template>
                                                <template v-else>{{ getArabicStatus(entry.to_status) }}</template>
                                            </strong>
                                            <span class="history-when">{{ formatDateTime(entry.created_at) }}</span>
                                        </div>
                                        <p class="history-meta">
                                            <i class="fas fa-user"></i> {{ entry.user?.name || 'النظام' }}
                                        </p>
                                        <p v-if="entry.note" class="history-note">{{ entry.note }}</p>
                                    </div>
                                </div>
                            </div>
                            <el-empty v-else description="لا يوجد سجل مراحل لهذا الطلب" :image-size="52" />
                        </el-card>

                        <el-row :gutter="16">
                            <el-col :xs="24" :md="14">
                                <el-card shadow="never" class="info-card routing-card">
                                    <template #header>
                                        <div class="routing-header">
                                            <span class="card-title-txt"><i class="fas fa-route"></i> توجيه الطلب</span>
                                            <el-button text size="small" :loading="routingLoading" @click="loadRouting(selectedOrder.id)">
                                                <i class="fas fa-sync-alt"></i>
                                            </el-button>
                                        </div>
                                    </template>

                                    <div class="routing-field">
                                        <span class="lbl">نوع التنفيذ</span>
                                        <el-radio-group
                                            :model-value="selectedOrder.fulfillment_type || 'ship'"
                                            size="small"
                                            :disabled="!routing.can_change_fulfillment_type || store.saving"
                                            @change="handleFulfillmentTypeChange"
                                        >
                                            <el-radio-button value="ship">شحن</el-radio-button>
                                            <el-radio-button value="delivery">توصيل</el-radio-button>
                                            <el-radio-button value="pickup">استلام من الفرع</el-radio-button>
                                        </el-radio-group>
                                    </div>
                                    <p v-if="!routing.can_change_fulfillment_type" class="routing-locked-note">
                                        <i class="fas fa-lock"></i> لا يمكن تغيير نوع التنفيذ بعد شحن الطلب.
                                    </p>

                                    <el-divider />

                                    <!-- A routing is a set, not a single choice:
                                         an order split across two branches is
                                         routed to both, and only those two can
                                         then source its lines. -->
                                    <div class="routing-select-head">
                                        <span class="lbl">المستودعات المُوجَّه إليها</span>
                                        <el-button
                                            v-if="routingsDirty && sourcing.editable"
                                            type="primary" size="small" :loading="savingRoutings"
                                            @click="saveRoutings"
                                        >حفظ التوجيهات</el-button>
                                    </div>
                                    <p class="routing-hint">
                                        <i class="fas fa-circle-info"></i>
                                        اختر مستودعاً أو أكثر. مصدر البضاعة لكل صنف يُختار من هذه المستودعات فقط.
                                    </p>

                                    <div v-loading="routingLoading" class="warehouse-options">
                                        <div
                                            v-for="wh in routing.warehouses"
                                            :key="wh.warehouse_id"
                                            class="warehouse-option"
                                            :class="{ current: wh.is_current, selected: selectedRoutingIds.includes(wh.warehouse_id), short: !wh.covers_all }"
                                        >
                                            <div class="wh-head">
                                                <div class="wh-name">
                                                    <el-checkbox
                                                        :model-value="selectedRoutingIds.includes(wh.warehouse_id)"
                                                        :disabled="!sourcing.editable || savingRoutings"
                                                        @change="(checked) => toggleRouting(wh.warehouse_id, checked)"
                                                    />
                                                    <strong>{{ wh.name }}</strong>
                                                    <el-tag v-if="wh.is_current" size="small" type="primary" effect="dark">المسؤول</el-tag>
                                                    <el-tag v-else-if="wh.is_recommended" size="small" type="success" effect="plain">مقترح</el-tag>
                                                </div>
                                                <span class="wh-type">{{ wh.location_type_text }}</span>
                                            </div>

                                            <el-progress :percentage="wh.coverage_percentage" :stroke-width="6"
                                                :status="wh.covers_all ? 'success' : 'warning'" :show-text="false" />
                                            <div class="wh-coverage">
                                                <span :class="wh.covers_all ? 'text-success' : 'text-warning'">
                                                    <i class="fas" :class="wh.covers_all ? 'fa-check-circle' : 'fa-triangle-exclamation'"></i>
                                                    يغطي {{ wh.covered_items }} من {{ wh.total_items }} صنف
                                                </span>
                                                <el-button
                                                    v-if="!wh.is_current && routing.can_change_fulfillment_type"
                                                    size="small" text type="primary" :loading="store.saving"
                                                    @click="routeToWarehouse(wh.warehouse_id)"
                                                >اجعله المسؤول</el-button>
                                            </div>

                                            <ul v-if="!wh.covers_all" class="shortfall-list">
                                                <li v-for="item in wh.items.filter((i) => i.shortfall > 0)" :key="item.product_id">
                                                    {{ item.product_name }} — ناقص {{ item.shortfall }}
                                                    <span class="muted">(مطلوب {{ item.required }}، متاح {{ item.available }})</span>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </el-card>

                                <!-- Where each line's goods actually come from.
                                     Routing above picks a warehouse for the
                                     whole order; this decides it per line, and
                                     lets one line be split across sources. -->
                                <el-card shadow="never" class="info-card sourcing-card">
                                    <template #header>
                                        <div class="routing-header">
                                            <span class="card-title-txt"><i class="fas fa-code-branch"></i> مصدر البضاعة لكل صنف</span>
                                            <el-button
                                                v-if="sourcing.editable && sourcingDirty"
                                                type="primary" size="small" :loading="savingSourcing"
                                                @click="saveSourcing"
                                            >حفظ المصادر</el-button>
                                        </div>
                                    </template>

                                    <p v-if="!sourcing.editable" class="routing-locked-note">
                                        <i class="fas fa-lock"></i> لا يمكن تغيير المصدر بعد شحن الطلب.
                                    </p>
                                    <!-- Says which list the operator is choosing
                                         from, so a warehouse missing here reads
                                         as "not routed" rather than "not found". -->
                                    <p v-else-if="sourcing.selected_warehouse_ids" class="routing-hint">
                                        <i class="fas fa-filter"></i>
                                        المصادر المعروضة هي توجيهات الطلب ({{ sourcing.selected_warehouse_ids.length }} مستودع).
                                    </p>
                                    <p v-else class="routing-hint">
                                        <i class="fas fa-circle-info"></i>
                                        لم تُحدَّد توجيهات بعد، فكل المستودعات النشطة معروضة.
                                    </p>

                                    <div v-loading="sourcingLoading" class="sourcing-lines">
                                        <div v-for="l in sourcing.lines" :key="l.item_id" class="sourcing-line">
                                            <div class="sl-head">
                                                <div>
                                                    <strong>{{ l.product_name }}</strong>
                                                    <span class="muted"> · {{ l.sku || '—' }}</span>
                                                </div>
                                                <!-- The gap is the number that matters: unsourced units
                                                     cannot ship, so it is stated rather than implied. -->
                                                <el-tag
                                                    :type="l.allocated === l.quantity ? 'success' : 'danger'"
                                                    size="small"
                                                    effect="plain"
                                                >
                                                    {{ l.allocated }} / {{ l.quantity }}
                                                    <template v-if="l.allocated !== l.quantity">
                                                        — ناقص {{ l.quantity - l.allocated }}
                                                    </template>
                                                </el-tag>
                                            </div>

                                            <div class="sl-sources">
                                                <div v-for="s in l.sources" :key="s.warehouse_id" class="sl-source">
                                                    <div class="sl-wh">
                                                        <span>{{ s.warehouse_name }}</span>
                                                        <el-tag v-if="s.is_primary" size="small" effect="plain">رئيسي</el-tag>
                                                        <span class="sl-avail" :class="{ 'text-danger': s.available <= 0 }">
                                                            متاح {{ s.available }}
                                                        </span>
                                                    </div>
                                                    <el-input-number
                                                        :model-value="s.allocated"
                                                        :min="0"
                                                        :max="s.available"
                                                        size="small"
                                                        controls-position="right"
                                                        :disabled="!sourcing.editable || s.available <= 0"
                                                        @change="(v) => setSource(l, s, v)"
                                                    />
                                                </div>
                                            </div>
                                        </div>

                                        <el-empty v-if="!sourcing.lines?.length" description="لا توجد أصناف" :image-size="46" />
                                    </div>
                                </el-card>
                            </el-col>

                            <el-col :xs="24" :md="10">
                                <el-card shadow="never" class="info-card stage-card">
                                    <template #header><span class="card-title-txt"><i class="fas fa-forward"></i> المرحلة التالية</span></template>
                                    <p class="stage-explainer">{{ stageExplainer }}</p>
                                    <div class="stage-actions">
                                        <el-button
                                            v-for="action in stageActions"
                                            :key="action.status"
                                            :type="action.type"
                                            :plain="action.plain"
                                            :loading="store.saving"
                                            class="stage-btn"
                                            @click="handleStageMove(action)"
                                        >
                                            <i class="fas" :class="action.icon"></i> {{ action.label }}
                                        </el-button>
                                        <el-empty v-if="!stageActions.length" description="لا توجد مراحل تالية" :image-size="46" />
                                    </div>
                                </el-card>
                            </el-col>
                        </el-row>
                    </el-tab-pane>

                    <!-- ---------------- Documents ---------------- -->
                    <el-tab-pane name="documents">
                        <template #label>
                            <i class="fas fa-file-invoice-dollar"></i> المستندات والقيود
                            <el-badge v-if="documentIssueCount" :value="documentIssueCount" type="danger" class="tab-badge" />
                        </template>

                        <!-- Invoice -->
                        <el-card shadow="never" class="info-card mb-3">
                            <template #header><span class="card-title-txt"><i class="fas fa-file-invoice"></i> فاتورة المبيعات</span></template>
                            <div v-if="invoice" class="doc-invoice">
                                <div class="doc-line">
                                    <span class="doc-number">{{ invoice.invoice_number }}</span>
                                    <el-tag :type="statusTagType(invoice.status)" size="small" effect="plain">
                                        {{ getArabicStatus(invoice.status) }}
                                    </el-tag>
                                    <el-tag :type="invoicePosted ? 'success' : 'danger'" size="small" effect="dark">
                                        {{ invoicePosted ? 'مُرحَّلة محاسبياً' : 'غير مُرحَّلة' }}
                                    </el-tag>
                                </div>
                                <div class="doc-figures">
                                    <div><span>الإجمالي</span><strong>{{ formatCurrency(invoice.total) }}</strong></div>
                                    <div><span>المحصَّل</span><strong>{{ formatCurrency(invoice.paid_amount) }}</strong></div>
                                    <div><span>المتبقي</span><strong :class="invoiceDueAmount > 0.01 ? 'text-danger' : 'text-success'">{{ formatCurrency(invoiceDueAmount) }}</strong></div>
                                </div>
                                <el-button type="success" plain class="mt-2" @click="goToInvoices">
                                    <i class="fas fa-arrow-left"></i> فتح الفاتورة {{ invoice.invoice_number }}
                                </el-button>
                            </div>
                            <el-empty v-else description="لم تُنشأ فاتورة لهذا الطلب بعد" :image-size="60" />
                        </el-card>

                        <!-- Payments -->
                        <el-card v-if="payments.length" shadow="never" class="info-card mb-3">
                            <template #header><span class="card-title-txt"><i class="fas fa-hand-holding-dollar"></i> الدفعات</span></template>
                            <el-table :data="payments" stripe size="small" style="width: 100%">
                                <el-table-column label="الرقم" min-width="130">
                                    <template #default="{ row }">{{ row.payment_number || '—' }}</template>
                                </el-table-column>
                                <el-table-column label="التاريخ" width="130" align="center">
                                    <template #default="{ row }">{{ formatDate(row.payment_date) }}</template>
                                </el-table-column>
                                <el-table-column label="الطريقة" width="120" align="center">
                                    <template #default="{ row }">{{ paymentMethodLabel(row.payment_method) }}</template>
                                </el-table-column>
                                <el-table-column label="المبلغ" width="130" align="center">
                                    <template #default="{ row }"><strong>{{ formatCurrency(row.amount) }}</strong></template>
                                </el-table-column>
                            </el-table>
                        </el-card>

                        <!-- Journal entries -->
                        <el-card shadow="never" class="info-card mb-3">
                            <template #header><span class="card-title-txt"><i class="fas fa-book"></i> القيود المحاسبية</span></template>
                            <div v-if="journalEntries.length" class="entry-list">
                                <div v-for="entry in journalEntries" :key="entry.id" class="entry" :class="{ reversed: entry.status === 'reversed' }">
                                    <div class="entry-head">
                                        <strong>{{ entry.entry_number }}</strong>
                                        <span class="entry-date">{{ formatDate(entry.entry_date) }}</span>
                                        <el-tag v-if="entry.status === 'reversed'" size="small" type="info" effect="plain">معكوس</el-tag>
                                        <span class="entry-desc">{{ entry.description }}</span>
                                    </div>
                                    <table class="entry-lines">
                                        <tr v-for="l in entry.lines || []" :key="l.id">
                                            <td class="acc-code">{{ l.ledger_account?.code || '—' }}</td>
                                            <td>{{ l.ledger_account?.name || l.description }}</td>
                                            <td class="num">{{ toNum(l.debit) ? formatCurrency(l.debit) : '' }}</td>
                                            <td class="num">{{ toNum(l.credit) ? formatCurrency(l.credit) : '' }}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            <el-empty v-else description="لم تُرحَّل أي قيود لهذا الطلب" :image-size="60" />
                        </el-card>

                        <!-- Stock movements -->
                        <el-card shadow="never" class="info-card">
                            <template #header><span class="card-title-txt"><i class="fas fa-dolly"></i> حركات المخزون</span></template>
                            <el-table v-if="stockMovements.length" :data="stockMovements" stripe size="small" style="width: 100%">
                                <el-table-column label="الصنف" min-width="160">
                                    <template #default="{ row }">{{ productName(row.product_id) }}</template>
                                </el-table-column>
                                <el-table-column label="المستودع" min-width="120">
                                    <template #default="{ row }">{{ row.warehouse?.name || '—' }}</template>
                                </el-table-column>
                                <el-table-column label="النوع" width="100" align="center">
                                    <template #default="{ row }">
                                        <el-tag :type="row.movement_type === 'in' ? 'success' : 'danger'" size="small" effect="plain">
                                            {{ row.movement_type === 'in' ? 'إرجاع' : 'إخراج' }}
                                        </el-tag>
                                    </template>
                                </el-table-column>
                                <el-table-column label="الكمية" width="80" align="center">
                                    <template #default="{ row }">{{ row.quantity }}</template>
                                </el-table-column>
                                <el-table-column label="التكلفة" width="120" align="center">
                                    <template #default="{ row }">{{ formatCurrency(row.total_cost) }}</template>
                                </el-table-column>
                                <el-table-column label="التاريخ" width="130" align="center">
                                    <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
                                </el-table-column>
                            </el-table>
                            <el-empty v-else description="لم تُسجَّل حركات مخزنية لهذا الطلب" :image-size="60" />
                        </el-card>
                    </el-tab-pane>
                </el-tabs>
            </div>
        </el-drawer>

        <!-- Fulfilment type change: needs a delivery fee for ship/delivery -->
        <el-dialog v-model="typeDialogVisible" title="تغيير نوع التنفيذ" width="440px" :close-on-click-modal="false">
            <p class="dialog-lead">
                سيُعاد توجيه الطلب إلى أنسب مستودع، وتُعاد تسوية الفاتورة والقيد المحاسبي تلقائياً.
            </p>
            <el-form label-position="top">
                <el-form-item label="تكلفة الشحن المحملة على العميل">
                    <el-input-number v-model="typeForm.shipping_cost" :min="0" :step="5" style="width: 100%" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="typeDialogVisible = false">إلغاء</el-button>
                <el-button type="primary" :loading="store.saving" @click="submitFulfillmentType">تأكيد التغيير</el-button>
            </template>
        </el-dialog>

        <!-- Delivery: the goods arrive and, usually, the money comes back -->
        <el-dialog v-model="deliverDialogVisible" title="تسليم الطلب وتسوية الفاتورة" width="480px" :close-on-click-modal="false">
            <div v-if="invoice" class="settle-summary">
                <div><span>الفاتورة</span><strong>{{ invoice.invoice_number }}</strong></div>
                <div><span>إجمالي الفاتورة</span><strong>{{ formatCurrency(invoice.total) }}</strong></div>
                <div><span>المتبقي</span><strong :class="invoiceDueAmount > 0.01 ? 'text-danger' : 'text-success'">{{ formatCurrency(invoiceDueAmount) }}</strong></div>
            </div>
            <el-alert v-else type="warning" :closable="false" show-icon class="mb-3"
                title="لا توجد فاتورة على هذا الطلب — سيُختم التسليم دون تسوية مالية." />

            <el-alert
                v-if="invoice && invoiceDueAmount <= 0.01"
                type="success"
                :closable="false"
                show-icon
                class="mb-3"
                title="الفاتورة مسددة بالكامل مسبقاً — لن يُسجَّل تحصيل جديد."
            />

            <el-form v-else-if="invoice" label-position="top" class="mt-3">
                <el-form-item>
                    <el-checkbox v-model="deliverForm.settle">
                        تحصيل المبلغ الآن عند التسليم
                    </el-checkbox>
                    <p class="field-hint">
                        اتركه فارغاً لعميل آجل — يبقى المبلغ على ذمته وتُحصَّل الفاتورة لاحقاً.
                    </p>
                </el-form-item>

                <template v-if="deliverForm.settle">
                    <el-form-item label="المبلغ المُحصَّل">
                        <el-input-number
                            v-model="deliverForm.settlement_amount"
                            :min="0.01"
                            :max="invoiceDueAmount"
                            :step="10"
                            :precision="2"
                            style="width: 100%"
                        />
                        <p class="field-hint">لا يمكن أن يتجاوز المتبقي؛ المبلغ الأقل يُسجَّل كتحصيل جزئي.</p>
                    </el-form-item>
                    <el-form-item label="طريقة الدفع">
                        <el-radio-group v-model="deliverForm.payment_method">
                            <el-radio-button value="cash">نقداً</el-radio-button>
                            <el-radio-button value="card">بطاقة</el-radio-button>
                            <el-radio-button value="bank_transfer">حوالة</el-radio-button>
                            <el-radio-button value="check">شيك</el-radio-button>
                        </el-radio-group>
                    </el-form-item>
                    <el-form-item label="مرجع الدفع">
                        <el-input v-model="deliverForm.payment_reference" placeholder="رقم الإيصال أو الحوالة (اختياري)" />
                    </el-form-item>
                </template>
            </el-form>

            <template #footer>
                <el-button @click="deliverDialogVisible = false">إلغاء</el-button>
                <el-button type="success" :loading="store.saving" @click="submitDelivery">
                    {{ deliverForm.settle && invoice && invoiceDueAmount > 0.01 ? 'تسليم وتحصيل' : 'تأكيد التسليم' }}
                </el-button>
            </template>
        </el-dialog>

        <!-- Shipping: capture the tracking details as the goods leave -->
        <el-dialog v-model="shipDialogVisible" title="تأكيد الشحن" width="440px" :close-on-click-modal="false">
            <el-alert
                type="warning"
                :closable="false"
                show-icon
                title="سيخرج المخزون فعلياً من المستودع ويُسجَّل قيد تكلفة البضاعة المباعة."
                class="mb-3"
            />
            <el-form label-position="top">
                <el-form-item label="شركة الشحن">
                    <el-input v-model="shipForm.carrier" placeholder="مثال: أرامكس" />
                </el-form-item>
                <el-form-item label="رقم التتبع">
                    <el-input v-model="shipForm.tracking_number" placeholder="TRK-000000" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="shipDialogVisible = false">إلغاء</el-button>
                <el-button type="primary" :loading="store.saving" @click="submitShipment">تأكيد الشحن</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted, computed, reactive } from 'vue';
import { useRouter } from 'vue-router';
import { useSalesOrdersStore } from '@/stores/salesOrders';
import { salesOrdersApi } from '@/api/salesOrders';
import { useCustomersStore } from '@/stores/customers';
import { useProductsStore } from '@/stores/products';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Search } from '@element-plus/icons-vue';
import {
    normalizeStatus,
    statusTagType,
    statusIcon,
    statusLabel,
    formatCurrency,
    formatDate,
    paymentMethodLabel,
    apiErrorMessage,
} from '@/utils/sales';

const router = useRouter();
const store = useSalesOrdersStore();
const customersStore = useCustomersStore();
const productsStore = useProductsStore();

const searchQuery = ref('');

// Drawers and actions state
const detailDrawerVisible = ref(false);
const loadingDetail = ref(false);
const selectedOrder = ref(null);

// Status presentation is shared across the sales module so tags, icons and
// labels stay identical on every screen — see resources/js/utils/sales.js.
const statusIconClass = (status) => statusIcon(status);
const getArabicStatus = (status) => statusLabel(status);

const FULFILLMENT_LABELS = { ship: 'شحن', pickup: 'استلام من الفرع', delivery: 'توصيل' };
const fulfillmentLabel = (type) => FULFILLMENT_LABELS[normalizeStatus(type)] || 'غير محدد';
const fulfillmentTagType = (type) => ({ ship: 'primary', delivery: 'warning', pickup: 'success' }[normalizeStatus(type)] || 'info');

// Timeline step mappings
const getTimelineProgressWidth = (status) => {
    const value = normalizeStatus(status);
    if (value === 'pending') return '0%';
    if (value === 'confirmed') return '25%';
    if (value === 'processing') return '50%';
    if (value === 'shipped') return '75%';
    if (value === 'delivered') return '100%';
    return '0%';
};

const isStepCompleted = (currentStatus, step) => {
    const val = normalizeStatus(currentStatus);
    const steps = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];
    const currentIndex = steps.indexOf(val);
    const stepIndex = steps.indexOf(step);
    return stepIndex <= currentIndex;
};

/* ------------------------------------------------------------------ *
 * Pipeline view
 *
 * Filtering, searching and counting all used to happen in the browser over
 * whatever page was loaded — so an order on page two could not be found, and
 * "إجمالي طلبات البيع" was really "orders currently on screen". All three are
 * now the server's answers over the whole table.
 * ------------------------------------------------------------------ */

const activeStage = ref('all');
const counts = computed(() => store.statusCounts);

const stageTabs = computed(() => [
    { name: 'all', label: 'الكل', icon: 'fa-layer-group', count: counts.value.all, badge: 'info' },
    { name: 'pending', label: 'معلق', icon: 'fa-clock', count: counts.value.pending, badge: 'warning' },
    { name: 'confirmed', label: 'مؤكد', icon: 'fa-circle-check', count: counts.value.confirmed, badge: 'primary' },
    { name: 'processing', label: 'قيد التجهيز', icon: 'fa-gears', count: counts.value.processing, badge: 'primary' },
    { name: 'shipped', label: 'مشحون', icon: 'fa-truck-fast', count: counts.value.shipped, badge: 'primary' },
    { name: 'delivered', label: 'مُسلَّم', icon: 'fa-box-open', count: counts.value.delivered, badge: 'success' },
    { name: 'cancelled', label: 'ملغي', icon: 'fa-ban', count: counts.value.cancelled, badge: 'danger' },
    { name: 'overdue', label: 'متأخرة', icon: 'fa-triangle-exclamation', count: counts.value.overdue, badge: 'danger' },
]);

const loadOrders = (page = 1) => {
    const params = { page, per_page: 20 };

    if (activeStage.value === 'overdue') {
        params.overdue = 1;
    } else if (activeStage.value !== 'all') {
        params.status = activeStage.value;
    }

    if (searchQuery.value.trim()) {
        params.search = searchQuery.value.trim();
    }

    return store.fetchOrders(params).catch(() => {});
};

const onStageChange = () => loadOrders(1);
const onPageChange = (page) => loadOrders(page);

// Debounced, so typing a nine-character order number is one request and not nine.
let searchTimer = null;
const onSearchInput = () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadOrders(1), 400);
};

const showOverdue = () => {
    activeStage.value = 'overdue';
    loadOrders(1);
};

/** How long the order has sat where it is. */
const stageAgeText = (followUp) => {
    if (!followUp) return '—';
    if (!followUp.is_open) return 'مكتمل';

    const days = followUp.days_in_stage;
    if (days === 0) return 'اليوم';
    if (days === 1) return 'منذ يوم';
    if (days === 2) return 'منذ يومين';
    return `منذ ${days} يوم`;
};

const stageAgeClass = (followUp) => {
    if (!followUp?.is_open) return 'age-done';
    if (followUp.is_overdue) return 'age-overdue';
    if (followUp.is_stalled) return 'age-stalled';
    return 'age-ok';
};

const rowClassName = ({ row }) => (row.follow_up?.needs_attention ? 'row-needs-attention' : '');

/* ------------------------------------------------------------------ *
 * Routing and execution stages
 *
 * Confirming an order reserves stock, raises an invoice and posts to the
 * ledger — so the operator needs to see, before committing, whether the
 * warehouse serving the order can actually cover its lines. That check used to
 * be invisible: the warehouse was silently defaulted to "the first active one"
 * and the shortfall only surfaced as a failed confirmation.
 * ------------------------------------------------------------------ */

const routing = ref({ warehouses: [], can_change_fulfillment_type: true, recommended_warehouse_id: null });
const routingLoading = ref(false);

const typeDialogVisible = ref(false);
const typeForm = reactive({ fulfillment_type: 'ship', shipping_cost: 0, fulfillment_warehouse_id: null });

const shipDialogVisible = ref(false);
const shipForm = reactive({ carrier: '', tracking_number: '' });

const deliverDialogVisible = ref(false);
const deliverForm = reactive({
    settle: true,
    settlement_amount: 0,
    payment_method: 'cash',
    payment_reference: '',
    note: '',
});

/* ------------------------------------------------------------------ *
 * Source selection
 *
 * Routing chooses a warehouse for the whole order. This chooses it per line,
 * and lets a single line draw from more than one place — the seller's own
 * stock first, the remainder from the main warehouse. The split is what the
 * ledger then credits, so it has to be visible and editable here rather than
 * being decided silently.
 * ------------------------------------------------------------------ */

const sourcing = ref({ lines: [], editable: false, selected_warehouse_ids: null });
const sourcingLoading = ref(false);
const savingSourcing = ref(false);
const sourcingDirty = ref(false);

/* ------------------------------------------------------------------ *
 * Routing selection
 *
 * The order may be routed through several warehouses at once, and the sourcing
 * editor below offers exactly those. The selection is edited locally and
 * committed in one call, so ticking three boxes is one decision rather than
 * three separate saves the server would have to reconcile.
 * ------------------------------------------------------------------ */

const selectedRoutingIds = ref([]);
const savingRoutings = ref(false);
const routingsDirty = ref(false);

const sameIds = (a, b) => a.length === b.length && [...a].sort().every((v, i) => v === [...b].sort()[i]);

/** Mirrors the saved selection back into the local one, clearing the dirty flag. */
const syncRoutingSelection = () => {
    // `null` means nothing has been narrowed down; the boxes start empty rather
    // than pretending every warehouse was deliberately chosen.
    selectedRoutingIds.value = [...(sourcing.value.selected_warehouse_ids || [])];
    routingsDirty.value = false;
};

const toggleRouting = (warehouseId, checked) => {
    const next = new Set(selectedRoutingIds.value);
    if (checked) {
        next.add(warehouseId);
    } else {
        next.delete(warehouseId);
    }
    selectedRoutingIds.value = [...next];
    routingsDirty.value = !sameIds(selectedRoutingIds.value, sourcing.value.selected_warehouse_ids || []);
};

const saveRoutings = async () => {
    if (!selectedRoutingIds.value.length) {
        ElMessage.warning('اختر مستودعاً واحداً على الأقل لتوجيه الطلب إليه.');
        return;
    }

    savingRoutings.value = true;
    try {
        const res = await salesOrdersApi.saveRoutings(selectedOrder.value.id, selectedRoutingIds.value);
        sourcing.value = res.data?.data || sourcing.value;
        syncRoutingSelection();
        sourcingDirty.value = false;
        ElMessage.success(res.data?.message || 'تم حفظ توجيهات الطلب.');
        // The coverage panel and the order header both read the owning
        // warehouse, which may have moved with the selection.
        await Promise.all([loadRouting(selectedOrder.value.id), refreshDetail()]);
    } catch (e) {
        ElMessage.error(apiErrorMessage(e, 'تعذّر حفظ توجيهات الطلب.'));
        // Put the boxes back to what the server actually holds, so the screen
        // never shows a selection that was refused.
        syncRoutingSelection();
    } finally {
        savingRoutings.value = false;
    }
};

const loadSourcing = async (id) => {
    sourcingLoading.value = true;
    try {
        const res = await salesOrdersApi.sourcing(id);
        sourcing.value = res.data?.data || { lines: [], editable: false, selected_warehouse_ids: null };
        sourcingDirty.value = false;
        syncRoutingSelection();
    } catch (e) {
        ElMessage.error(apiErrorMessage(e, 'تعذّر تحميل مصادر البضاعة.'));
    } finally {
        sourcingLoading.value = false;
    }
};

/** Edits one warehouse's share locally; nothing is committed until saved. */
const setSource = (line, source, value) => {
    source.allocated = Number(value) || 0;
    line.allocated = line.sources.reduce((sum, s) => sum + (Number(s.allocated) || 0), 0);
    sourcingDirty.value = true;
};

const saveSourcing = async () => {
    // Refused server-side too, but catching it here spares a round trip and
    // names the line that is short.
    const incomplete = (sourcing.value.lines || []).find((l) => l.allocated !== l.quantity);
    if (incomplete) {
        ElMessage.warning(
            `«${incomplete.product_name}»: مُسند ${incomplete.allocated} من ${incomplete.quantity}. أكمل المصادر قبل الحفظ.`
        );
        return;
    }

    savingSourcing.value = true;
    try {
        const res = await salesOrdersApi.saveSourcing(selectedOrder.value.id, {
            lines: sourcing.value.lines.map((l) => ({
                item_id: l.item_id,
                sources: l.sources
                    .filter((s) => Number(s.allocated) > 0)
                    .map((s) => ({ warehouse_id: s.warehouse_id, quantity: Number(s.allocated) })),
            })),
        });

        sourcing.value = res.data?.data || sourcing.value;
        sourcingDirty.value = false;
        syncRoutingSelection();
        ElMessage.success(res.data?.message || 'تم حفظ مصادر البضاعة.');
        await refreshDetail();
    } catch (e) {
        ElMessage.error(apiErrorMessage(e, 'تعذّر حفظ مصادر البضاعة.'));
    } finally {
        savingSourcing.value = false;
    }
};

const loadRouting = async (id) => {
    routingLoading.value = true;
    try {
        routing.value = await store.fetchRouting(id);
    } catch (e) {
        ElMessage.error(apiErrorMessage(e, 'تعذّر تحميل بيانات توجيه الطلب.'));
    } finally {
        routingLoading.value = false;
    }
};

/** What each stage move will actually do, said plainly before it is clicked. */
const STAGE_ACTIONS = {
    confirmed: { label: 'تأكيد الطلب', icon: 'fa-circle-check', type: 'primary', plain: false,
        confirm: 'سيُحجز المخزون المطلوب، وتُنشأ فاتورة المبيعات، ويُرحَّل قيد الإيراد. متابعة؟' },
    processing: { label: 'بدء التجهيز', icon: 'fa-gears', type: 'warning', plain: true,
        confirm: 'نقل الطلب إلى مرحلة التجهيز؟ يبقى المخزون محجوزاً.' },
    shipped: { label: 'تأكيد الشحن', icon: 'fa-truck-fast', type: 'success', plain: false, dialog: 'ship' },
    // Delivery opens the settlement dialog rather than a plain confirm: the
    // money usually comes back at the door, and asking after the fact means it
    // gets recorded from memory or not at all.
    delivered: { label: 'تسليم وتسوية', icon: 'fa-hand-holding-dollar', type: 'success', plain: false, dialog: 'deliver' },
    cancelled: { label: 'إلغاء الطلب', icon: 'fa-ban', type: 'danger', plain: true,
        confirm: 'سيُحرَّر حجز المخزون (أو تُرجَع البضاعة إن كانت شُحنت)، وتُلغى الفاتورة وتُعكس قيودها. متابعة؟' },
};

const stageActions = computed(() =>
    (routing.value.allowed_transitions || []).map((status) => ({ status, ...STAGE_ACTIONS[status] })).filter((a) => a.label)
);

/** Where the order sits in the 1..5 sequence, for the numbered tracker. */
const STAGE_SEQUENCE = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];

const currentStageNumber = computed(() => {
    const i = STAGE_SEQUENCE.indexOf(normalizeStatus(selectedOrder.value?.status));
    return i === -1 ? 0 : i + 1;
});

/**
 * The next stage and what entering it will do. Named from the sequence rather
 * than from `allowed_transitions`, because cancelling is an exit, not progress.
 */
const NEXT_STAGE_EFFECT = {
    confirmed: 'يُحجز المخزون، وتُنشأ الفاتورة، ويُرحَّل قيد الإيراد.',
    processing: 'يبدأ التجهيز ويبقى المخزون محجوزاً — لا أثر مالي.',
    shipped: 'تخرج البضاعة فعلياً من المستودع ويُرحَّل قيد تكلفة البضاعة المباعة.',
    delivered: 'يُختم التسليم، ويمكن تحصيل المبلغ وتسوية الفاتورة في الخطوة نفسها.',
};

const nextStage = computed(() => {
    const current = normalizeStatus(selectedOrder.value?.status);
    const i = STAGE_SEQUENCE.indexOf(current);
    if (i === -1 || i >= STAGE_SEQUENCE.length - 1) return null;

    const next = STAGE_SEQUENCE[i + 1];
    if (!(routing.value.allowed_transitions || []).includes(next)) return null;

    return { status: next, label: STAGE_ACTIONS[next]?.label || next, effect: NEXT_STAGE_EFFECT[next] || '' };
});

const stageExplainer = computed(() => {
    switch (normalizeStatus(selectedOrder.value?.status)) {
        case 'pending': return 'الطلب لم يُؤكَّد بعد — لم يُحجز مخزون ولم تُنشأ فاتورة.';
        case 'confirmed': return 'المخزون محجوز والفاتورة مُنشأة. البضاعة ما زالت في المستودع حتى الشحن.';
        case 'processing': return 'جارٍ التجهيز. الحجز قائم والبضاعة لم تخرج بعد.';
        case 'shipped': return 'خرجت البضاعة من المستودع وسُجِّل قيد تكلفة البضاعة المباعة.';
        case 'delivered': return invoiceDueAmount.value > 0.01
            ? `اكتمل التسليم، وما زال على العميل ${formatCurrency(invoiceDueAmount.value)}.`
            : 'اكتمل الطلب وسُوِّيت الفاتورة بالكامل.';
        case 'cancelled': return 'الطلب ملغي وقد عُكست آثاره المخزنية والمحاسبية.';
        default: return '';
    }
});

/** Applies whatever the API says a move changed, and reports it in plain terms. */
const afterStageChange = async (result) => {
    selectedOrder.value = { ...selectedOrder.value, ...(result.sales_order || {}) };
    // A stage move rewrites the documents behind the order, so the whole detail
    // payload is refetched rather than just the routing figures.
    await refreshDetail();

    const effects = result.effects || result.transition?.effects || {};
    const notes = [];
    if (effects.invoice_number) notes.push(`الفاتورة ${effects.invoice_number}`);
    if (effects.cost_of_goods_sold) notes.push(`تكلفة مبيعات ${effects.cost_of_goods_sold}`);
    if (effects.stock_movements?.length) notes.push(`${effects.stock_movements.length} حركة مخزنية`);
    if (effects.reservation_released) notes.push('تحرير الحجز');
    if (effects.stock_returned) notes.push('إرجاع البضاعة للمخزون');
    if (effects.invoice_cancelled) notes.push(`إلغاء الفاتورة ${effects.invoice_cancelled}`);
    if (effects.invoice_restated) notes.push(`إعادة إثبات الفاتورة ${effects.invoice_restated}`);
    if (effects.settlement?.payment_number) {
        notes.push(`تحصيل ${formatCurrency(effects.settlement.amount)} (${effects.settlement.payment_number})`);
        if (effects.settlement.remaining > 0.01) notes.push(`متبقٍّ ${formatCurrency(effects.settlement.remaining)}`);
    }

    ElMessage.success({
        message: notes.length ? `${result.message} (${notes.join('، ')})` : result.message,
        duration: 5000,
    });
};

const handleStageMove = async (action) => {
    if (action.dialog === 'ship') {
        shipForm.carrier = selectedOrder.value?.carrier || '';
        shipForm.tracking_number = selectedOrder.value?.tracking_number || '';
        shipDialogVisible.value = true;
        return;
    }

    if (action.dialog === 'deliver') {
        // Pre-filled with the whole outstanding balance, which is what
        // collecting on delivery normally means; a partial amount is a typed
        // correction rather than something the operator has to assemble.
        deliverForm.settle = invoiceDueAmount.value > 0.01;
        deliverForm.settlement_amount = invoiceDueAmount.value;
        deliverForm.payment_method = 'cash';
        deliverForm.payment_reference = '';
        deliverDialogVisible.value = true;
        return;
    }

    let note = null;

    if (action.status === 'cancelled') {
        // Cancelling reverses stock and posts reversing entries. The reason goes
        // onto the stage history, so the record explains itself later.
        const prompted = await ElMessageBox.prompt(action.confirm, action.label, {
            type: 'warning',
            confirmButtonText: 'إلغاء الطلب',
            cancelButtonText: 'تراجع',
            inputPlaceholder: 'سبب الإلغاء (إلزامي)',
            inputValidator: (v) => (v && v.trim().length >= 3) || 'يرجى ذكر سبب الإلغاء',
        }).catch(() => null);

        if (!prompted) return;
        note = prompted.value;
    } else {
        try {
            await ElMessageBox.confirm(action.confirm, action.label, {
                type: 'info',
                confirmButtonText: action.label,
                cancelButtonText: 'إلغاء',
            });
        } catch {
            return;
        }
    }

    try {
        await afterStageChange(await store.moveToStage(selectedOrder.value.id, action.status, note ? { note } : {}));
    } catch (e) {
        ElMessage.error(apiErrorMessage(e, 'تعذّر نقل الطلب إلى المرحلة المطلوبة.'));
    }
};

const submitDelivery = async () => {
    const payload = { note: deliverForm.note || undefined };

    if (deliverForm.settle) {
        payload.settle = true;
        payload.settlement_amount = deliverForm.settlement_amount;
        payload.payment_method = deliverForm.payment_method;
        if (deliverForm.payment_reference) payload.payment_reference = deliverForm.payment_reference;
    }

    try {
        const result = await store.moveToStage(selectedOrder.value.id, 'delivered', payload);
        deliverDialogVisible.value = false;
        await afterStageChange(result);
    } catch (e) {
        ElMessage.error(apiErrorMessage(e, 'تعذّر تأكيد التسليم.'));
    }
};

const submitShipment = async () => {
    try {
        const result = await store.moveToStage(selectedOrder.value.id, 'shipped', { ...shipForm });
        shipDialogVisible.value = false;
        await afterStageChange(result);
    } catch (e) {
        ElMessage.error(apiErrorMessage(e, 'تعذّر تأكيد الشحن.'));
    }
};

const handleFulfillmentTypeChange = async (type) => {
    typeForm.fulfillment_type = type;
    typeForm.fulfillment_warehouse_id = null;

    // Collecting in person carries no delivery fee, so there is nothing to ask.
    if (type === 'pickup') {
        typeForm.shipping_cost = 0;
        await submitFulfillmentType();
        return;
    }

    typeForm.shipping_cost = Number(selectedOrder.value?.shipping_cost || 0);
    typeDialogVisible.value = true;
};

const routeToWarehouse = async (warehouseId) => {
    typeForm.fulfillment_type = selectedOrder.value?.fulfillment_type || 'ship';
    typeForm.fulfillment_warehouse_id = warehouseId;
    typeForm.shipping_cost = Number(selectedOrder.value?.shipping_cost || 0);
    await submitFulfillmentType();
};

const submitFulfillmentType = async () => {
    try {
        const result = await store.changeFulfillmentType(selectedOrder.value.id, { ...typeForm });
        typeDialogVisible.value = false;
        await afterStageChange(result);
    } catch (e) {
        ElMessage.error(apiErrorMessage(e, 'تعذّر تغيير نوع التنفيذ.'));
    }
};

/* ------------------------------------------------------------------ *
 * Detail view
 *
 * The drawer used to render the order row on its own. An order could look
 * finished — delivered, stock gone — while the revenue behind it had never
 * reached the ledger, and nothing on the screen said so. The documents that are
 * supposed to follow an order are now shown beside it, together with a plain
 * statement of which one is missing.
 * ------------------------------------------------------------------ */

const detailTab = ref('overview');
const detail = ref({});

const invoice = computed(() => detail.value.invoice || null);
const payments = computed(() => detail.value.payments || []);
const journalEntries = computed(() => detail.value.journal_entries || []);
const stockMovements = computed(() => detail.value.stock_movements || []);
const diagnostics = computed(() => detail.value.diagnostics || []);
const history = computed(() => detail.value.history || []);
const followUp = computed(() => detail.value.follow_up || {});

const followUpClass = computed(() => {
    if (!followUp.value.is_open) return 'fu-done';
    if (followUp.value.is_overdue) return 'fu-overdue';
    if (followUp.value.is_stalled) return 'fu-stalled';
    return 'fu-ok';
});

/** History rows carry a full timestamp; the date alone would hide same-day moves. */
const formatDateTime = (value) => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);

    return `${formatDate(value)} — ${date.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })}`;
};

const toNum = (v) => {
    const n = parseFloat(v);
    return Number.isFinite(n) ? n : 0;
};

const lineTotal = (row) => toNum(row.unit_price) * toNum(row.quantity) - toNum(row.discount) + toNum(row.tax);

const invoiceDueAmount = computed(() =>
    invoice.value ? Math.max(0, toNum(invoice.value.total) - toNum(invoice.value.paid_amount)) : 0
);

/**
 * Whether the invoice reached the ledger — the check the diagnostics turn on.
 * Matched exactly or on a colon-anchored suffix, since a bare `invoice:1`
 * prefix would also match `invoice:10`.
 */
const invoicePosted = computed(() => {
    if (!invoice.value) return false;
    const key = `invoice:${invoice.value.id}`;

    return journalEntries.value.some((e) => {
        const k = String(e.posting_key || '');
        return k === key || k.startsWith(`${key}:`);
    });
});

const documentIssueCount = computed(() =>
    diagnostics.value.filter((d) => d.level === 'error').length
);

const diagnosticIcon = (level) =>
    ({ error: 'fa-circle-exclamation', warning: 'fa-triangle-exclamation', info: 'fa-circle-info' }[level] || 'fa-circle-info');

const isCancelled = computed(() => normalizeStatus(selectedOrder.value?.status) === 'cancelled');

/** Stage tracker carrying the date each stage actually happened. */
const timelineSteps = computed(() => {
    const status = normalizeStatus(selectedOrder.value?.status);
    const order = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];
    const reached = order.indexOf(status);
    const t = detail.value.timeline || {};

    return [
        { key: 'pending', label: 'معلق', icon: 'fa-clock', at: t.order_date },
        { key: 'confirmed', label: 'مؤكد', icon: 'fa-circle-check', at: t.confirmed_at },
        { key: 'processing', label: 'تجهيز', icon: 'fa-gears', at: null },
        { key: 'shipped', label: 'شحن', icon: 'fa-truck-fast', at: t.shipped_at },
        { key: 'delivered', label: 'تسليم', icon: 'fa-box-open', at: t.delivered_at },
    ].map((step, i) => ({
        ...step,
        done: reached >= i && reached !== -1,
        current: reached === i,
    }));
});

/** `shipping_address` is a JSON column on some rows and a plain string on others. */
const shippingAddressText = computed(() => {
    const value = selectedOrder.value?.shipping_address;
    if (!value) return '';
    if (typeof value === 'string') return value;
    return [value.line1, value.city, value.country].filter(Boolean).join('، ');
});

const productName = (productId) =>
    selectedOrder.value?.items?.find((i) => i.product_id === productId)?.product?.name_ar
    || selectedOrder.value?.items?.find((i) => i.product_id === productId)?.product?.name
    || `#${productId}`;

/**
 * Opens the invoices screen on *this* order's invoice. It previously pushed the
 * bare list, leaving the user to find the row themselves — the link knew which
 * invoice it meant and threw that away.
 */
const goToInvoices = () => {
    detailDrawerVisible.value = false;
    router.push({
        path: '/admin/sales/invoices',
        query: invoice.value ? { invoice: invoice.value.invoice_number } : {},
    });
};

// Drawer Actions
const openDetailDrawer = async (id) => {
    detailDrawerVisible.value = true;
    loadingDetail.value = true;
    detailTab.value = 'overview';
    detail.value = {};
    try {
        // One request for the whole screen: the order, its documents and the
        // diagnosis of what does not line up.
        detail.value = await store.fetchDetail(id);
        selectedOrder.value = detail.value.sales_order;
        routing.value = detail.value.routing || routing.value;
        await loadSourcing(id);
    } catch (e) {
        ElMessage.error(apiErrorMessage(e, 'خطأ أثناء تحميل تفاصيل الطلب.'));
    } finally {
        loadingDetail.value = false;
    }
};

/** Reloads the documents and diagnosis after a stage move changed them. */
const refreshDetail = async () => {
    if (!selectedOrder.value?.id) return;
    try {
        detail.value = await store.fetchDetail(selectedOrder.value.id);
        selectedOrder.value = detail.value.sales_order;
        routing.value = detail.value.routing || routing.value;
    } catch {
        /* the stage move already reported its own outcome */
    }
};

const openCreateDrawer = () => {
    router.push('/admin/sales/sales-orders/create');
};

const openEditDrawer = (id) => {
    router.push(`/admin/sales/sales-orders/${id}/edit`);
};

// Detail view actions

const deleteOrder = async (id) => {
    // Was a native confirm(), which is unstyled, not RTL-aware and blocks the tab.
    try {
        await ElMessageBox.confirm(
            'حذف طلب البيع؟ لا يمكن التراجع عن هذا الإجراء.',
            'تأكيد الحذف',
            { type: 'warning', confirmButtonText: 'حذف', cancelButtonText: 'إلغاء' }
        );
    } catch {
        return;
    }

    try {
        await store.deleteOrder(id);
        ElMessage.success('تم حذف طلب البيع بنجاح.');
        if (selectedOrder.value?.id === id) detailDrawerVisible.value = false;
    } catch (error) {
        ElMessage.error(apiErrorMessage(error, 'خطأ أثناء حذف طلب البيع.'));
    }
};

const handleConvertToInvoice = async (id) => {
    try {
        await ElMessageBox.confirm(
            'تحويل طلب البيع إلى فاتورة مبيعات؟',
            'تحويل إلى فاتورة',
            { type: 'info', confirmButtonText: 'تحويل', cancelButtonText: 'إلغاء' }
        );
    } catch {
        return;
    }

    try {
        const invoice = await store.convertToInvoice(id);
        detailDrawerVisible.value = false;
        ElMessage.success(`تم إنشاء الفاتورة ${invoice?.invoice_number || ''}`);
        // Send the user to the invoice so they can collect payment on it —
        // previously the conversion left them on the orders list with no clue
        // where the new invoice went.
        router.push('/admin/sales/invoices');
    } catch (e) {
        ElMessage.error(apiErrorMessage(e, 'حدث خطأ أثناء تحويل الطلب إلى فاتورة.'));
    }
};

onMounted(async () => {
    loadOrders(1);
    customersStore.fetchCustomers().catch(() => {});
    productsStore.fetchProducts({ per_page: 100 }).catch(() => {});
});
</script>

<style scoped>
.sales-page {
    padding: 0;
    font-family: 'Cairo', sans-serif;
}

.page-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1.25rem;
    margin-bottom: 2rem;
    padding-bottom: 1.25rem;
    border-bottom: 2px solid var(--border-color);
}

.page-title h1 {
    margin: 0;
    font-size: 1.6rem;
    font-weight: 700;
    color: var(--text-dark);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.page-title p {
    margin: 0.5rem 0 0;
    color: var(--text-muted);
    font-size: 0.9rem;
}

.header-actions {
    display: flex;
    gap: 0.75rem;
    align-items: center;
    flex-wrap: wrap;
}

.search-input {
    width: min(100%, 280px);
}

.create-btn {
    font-weight: 600;
    border-radius: var(--radius-md);
    padding: 0.625rem 1.25rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.overview-cards {
    margin-bottom: 2rem;
}

.stat-card-wrapper {
    border-radius: 1rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.stat-card-wrapper:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
}

.stat-card-inner {
    display: flex;
    align-items: center;
    gap: 1.25rem;
}

.stat-icon-box {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.blue-grad {
    background: linear-gradient(135deg, var(--accent-blue) 0%, var(--accent-blue-light) 100%);
}

.orange-grad {
    background: linear-gradient(135deg, var(--warning) 0%, var(--warning-dark) 100%);
}

.green-grad {
    background: linear-gradient(135deg, var(--success) 0%, var(--success-dark) 100%);
}

.stat-details h3 {
    margin: 0;
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--text-dark);
    line-height: 1.2;
}

.stat-details p {
    margin: 0.25rem 0 0;
    color: var(--text-muted);
    font-size: 0.875rem;
    font-weight: 500;
}

.table-panel {
    border-radius: 1rem;
    overflow: hidden;
}

.card-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 700;
    color: var(--text-dark);
}

.order-number-link {
    color: var(--accent-blue);
    font-weight: 700;
    cursor: pointer;
    transition: var(--transition);
}

.order-number-link:hover {
    text-decoration: underline;
    opacity: 0.8;
}

.customer-info-cell {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
}

.total-amount {
    color: var(--text-dark);
    font-size: 0.95rem;
}

.status-tag {
    font-size: 0.8rem;
    font-weight: 600;
    border-radius: 20px;
    padding: 0.25rem 0.75rem;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.status-dot-icon {
    font-size: 0.8rem;
}

.action-btn-group .el-button {
    padding: 0.4rem 0.6rem;
}

.loading-state {
    padding: 2rem;
}

.empty-state-box {
    padding: 4rem 2rem;
    text-align: center;
    color: var(--text-muted);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.empty-icon {
    font-size: 3.5rem;
    color: var(--text-light);
    margin-bottom: 1.25rem;
    opacity: 0.5;
}

.empty-state-box p {
    font-weight: 500;
    font-size: 1.05rem;
    margin-bottom: 1.5rem;
}

/* Detail Drawer Styles */
.drawer-detail-content {
    padding: 1.25rem 1.5rem 2rem;
    font-family: 'Cairo', sans-serif;
}

.drawer-title {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    font-weight: 700;
    color: var(--text-dark);
}

.drawer-title i { color: var(--el-color-primary); }
.drawer-title strong { font-family: monospace; color: var(--el-color-primary); }

/* ---- Masthead: the four facts read before any detail ---- */
.order-masthead {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    padding: 1.1rem 1.25rem;
    background: var(--bg-light);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    margin-bottom: 1rem;
}

.masthead-cell {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    min-width: 0;
}

.masthead-cell .lbl {
    font-size: 0.75rem;
    color: var(--text-muted);
    font-weight: 600;
}

.masthead-cell strong {
    font-size: 1rem;
    color: var(--text-dark);
    overflow-wrap: anywhere;
}

.masthead-cell strong.amount { font-size: 1.2rem; font-weight: 800; }
.masthead-cell .status-tag { align-self: flex-start; }

/* ---- Diagnostics ---- */
.diagnostics-panel {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    margin-bottom: 1rem;
}

.diagnostic {
    display: flex;
    gap: 0.8rem;
    padding: 0.85rem 1rem;
    border-radius: 10px;
    border: 1px solid;
    /* A coloured edge on the reading side makes severity scannable in a stack. */
    border-inline-start-width: 4px;
}

.diagnostic.level-error { background: #fef2f2; border-color: #fca5a5; color: #991b1b; }
.diagnostic.level-warning { background: #fffbeb; border-color: #fcd34d; color: #92400e; }
.diagnostic.level-info { background: #eff6ff; border-color: #93c5fd; color: #1e40af; }

.diagnostic-icon { font-size: 1.05rem; margin-top: 0.15rem; flex-shrink: 0; }
.diagnostic-body strong { font-size: 0.92rem; display: block; }
.diagnostic-body p { margin: 0.3rem 0 0; font-size: 0.83rem; line-height: 1.7; }
.diagnostic-action { font-weight: 600; opacity: 0.9; }

.diagnostics-clear {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.8rem 1rem;
    margin-bottom: 1rem;
    border-radius: 10px;
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    color: #065f46;
    font-size: 0.85rem;
}

.detail-tabs { margin-top: 0.25rem; }
.tab-badge { margin-inline-start: 0.35rem; }

/* ---- Items and amounts ---- */
.items-table .row-sub {
    margin: 0.15rem 0 0;
    font-size: 0.75rem;
    color: var(--text-muted);
}

.amounts-block {
    margin-top: 1rem;
    margin-inline-start: auto;
    max-width: 380px;
    border: 1px solid var(--border-color);
    border-radius: 10px;
    overflow: hidden;
}

.amount-row {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.6rem 0.9rem;
    font-size: 0.88rem;
    border-bottom: 1px solid var(--border-color);
}

.amount-row:last-child { border-bottom: none; }
.amount-row.grand { background: var(--bg-light); font-weight: 800; font-size: 1rem; }
.amount-row.paid { color: var(--el-color-success); }
.amount-row.due.unpaid { color: var(--el-color-danger); font-weight: 700; }
.amount-row.due.settled { color: var(--el-color-success); font-weight: 700; }

.info-card { border-radius: 12px; margin-bottom: 0.75rem; }
.info-list { display: flex; flex-direction: column; gap: 0.55rem; }
.info-item { display: flex; justify-content: space-between; gap: 1rem; font-size: 0.86rem; }
.info-item .lbl { color: var(--text-muted); flex-shrink: 0; }
.info-item strong { text-align: end; overflow-wrap: anywhere; }
.card-title-txt { display: flex; align-items: center; gap: 0.45rem; font-weight: 700; }
.card-title-txt i { color: var(--el-color-primary); }
.notes-txt-view { margin: 0; font-size: 0.86rem; line-height: 1.8; color: var(--text-dark); }

/* ---- Stage timeline ---- */
.stage-timeline {
    display: flex;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 1.25rem 1rem;
    background: var(--bg-light);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    margin-bottom: 1rem;
}

.stage-step {
    position: relative;
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.45rem;
    text-align: center;
    min-width: 0;
}

.step-marker {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fff;
    border: 2px solid var(--border-color);
    color: var(--text-muted);
    z-index: 1;
}

.stage-step.done .step-marker { background: var(--el-color-success); border-color: var(--el-color-success); color: #fff; }
.stage-step.current .step-marker { box-shadow: 0 0 0 4px color-mix(in srgb, var(--el-color-primary) 22%, transparent); }
.stage-step.skipped .step-marker { opacity: 0.45; }

.step-text { display: flex; flex-direction: column; gap: 0.1rem; }
.step-text strong { font-size: 0.8rem; }
.step-date { font-size: 0.7rem; color: var(--text-muted); }

/* The number carries the sequence; a tick replaces it only once the stage is
   behind you, so "how far along am I" stays readable at a glance. */
.step-number { font-weight: 800; font-size: 0.95rem; }
.step-check { font-size: 0.85rem; }

.step-now {
    font-size: 0.65rem;
    font-weight: 700;
    color: var(--el-color-primary);
    margin-top: 0.1rem;
}

/* ---- Next stage ---- */
.next-stage-bar {
    padding: 0.8rem 1rem;
    border: 1px solid var(--border-color);
    border-inline-start: 4px solid var(--el-color-primary);
    border-radius: 10px;
    background: color-mix(in srgb, var(--el-color-primary) 5%, transparent);
    margin-bottom: 1rem;
}

.next-stage-head { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; font-size: 0.9rem; }

.next-badge {
    background: var(--el-color-primary);
    color: #fff;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.15rem 0.5rem;
    border-radius: 999px;
    white-space: nowrap;
}

.next-stage-effect { margin: 0.4rem 0 0; font-size: 0.8rem; color: var(--text-muted); line-height: 1.7; }

/* ---- Settlement dialog ---- */
.settle-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    background: var(--bg-light);
    border: 1px solid var(--border-color);
    border-radius: 10px;
}

.settle-summary > div { display: flex; flex-direction: column; gap: 0.2rem; }
.settle-summary span { font-size: 0.72rem; color: var(--text-muted); }
.settle-summary strong { font-size: 0.95rem; }

.field-hint { margin: 0.3rem 0 0; font-size: 0.74rem; color: var(--text-muted); line-height: 1.6; }

/* Drawn between markers rather than behind them, so a filled segment reads as
   the transition that happened and not merely as a completed dot. */
.step-connector {
    position: absolute;
    top: 19px;
    inset-inline-start: calc(50% + 19px);
    width: calc(100% - 38px);
    height: 2px;
    background: var(--border-color);
}

.step-connector.filled { background: var(--el-color-success); }

/* ---- Documents ---- */
.doc-invoice { display: flex; flex-direction: column; gap: 0.75rem; }
.doc-line { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; }
.doc-number { font-family: monospace; font-weight: 700; font-size: 1rem; }

.doc-figures {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 0.75rem;
}

.doc-figures > div { display: flex; flex-direction: column; gap: 0.2rem; }
.doc-figures span { font-size: 0.75rem; color: var(--text-muted); }
.doc-figures strong { font-size: 1rem; }

.entry-list { display: flex; flex-direction: column; gap: 0.9rem; }

.entry {
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 0.75rem 0.9rem;
}

/* A reversed entry is still part of the record; it is dimmed, never hidden. */
.entry.reversed { opacity: 0.62; background: var(--bg-light); }

.entry-head {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
    margin-bottom: 0.5rem;
    font-size: 0.85rem;
}

.entry-head strong { font-family: monospace; }
.entry-date, .entry-desc { color: var(--text-muted); font-size: 0.78rem; }
.entry-lines { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
.entry-lines td { padding: 0.3rem 0.4rem; border-top: 1px solid var(--border-color); }
.entry-lines td.num { text-align: end; font-variant-numeric: tabular-nums; white-space: nowrap; }
.entry-lines td.acc-code { font-family: monospace; color: var(--text-muted); width: 60px; }

.muted { color: var(--text-muted); }

/* ---- Source selection ---- */
.sourcing-card { margin-top: 0.75rem; }
.sourcing-lines { display: flex; flex-direction: column; gap: 0.9rem; min-height: 50px; }

.sourcing-line {
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 0.7rem 0.85rem;
}

.sl-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
    margin-bottom: 0.6rem;
    font-size: 0.86rem;
}

.sl-sources { display: flex; flex-direction: column; gap: 0.45rem; }

.sl-source {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.35rem 0.5rem;
    background: var(--bg-light);
    border-radius: 8px;
}

.sl-wh { display: flex; align-items: center; gap: 0.45rem; font-size: 0.82rem; min-width: 0; }

/* What the shelf can give sits beside the box that spends it, so the ceiling
   is visible while typing rather than discovered on save. */
.sl-avail { font-size: 0.72rem; color: var(--text-muted); white-space: nowrap; }

.text-danger { color: var(--el-color-danger); }

/* ---- Pipeline tabs and follow-up ---- */
.stage-tabs { margin-bottom: 0.5rem; }
.stage-tab-label { display: inline-flex; align-items: center; gap: 0.4rem; }
.stage-badge { margin-inline-start: 0.5rem; }

.purple-grad { background: linear-gradient(135deg, #7c3aed 0%, #a78bfa 100%); }

.stat-card-wrapper.clickable { cursor: pointer; }
.stat-card-wrapper.attention-card { border: 1px solid #fca5a5; }

.follow-up-cell {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    font-size: 0.8rem;
}

.age-ok { color: var(--text-muted); }
.age-done { color: var(--el-color-success); }
.age-stalled { color: var(--el-color-warning); font-weight: 700; }
.age-overdue { color: var(--el-color-danger); font-weight: 700; }
.overdue-note { font-size: 0.72rem; color: var(--el-color-danger); }

.attention-flag { color: var(--el-color-danger); margin-inline-start: 0.4rem; }

/* A tinted row carries the warning even when the flag column is scrolled off. */
:deep(.row-needs-attention) { background: #fff7ed !important; }

.follow-up-bar {
    display: flex;
    gap: 0.8rem;
    align-items: flex-start;
    padding: 0.8rem 1rem;
    border-radius: 10px;
    border: 1px solid;
    margin-bottom: 1rem;
    font-size: 0.85rem;
}

.follow-up-bar p { margin: 0.2rem 0 0; font-size: 0.78rem; opacity: 0.85; }
.follow-up-bar.fu-ok { background: var(--bg-light); border-color: var(--border-color); color: var(--text-dark); }
.follow-up-bar.fu-done { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
.follow-up-bar.fu-stalled { background: #fffbeb; border-color: #fcd34d; color: #92400e; }
.follow-up-bar.fu-overdue { background: #fef2f2; border-color: #fca5a5; color: #991b1b; }

/* ---- Stage history ---- */
.history-list { display: flex; flex-direction: column; }

.history-entry {
    display: flex;
    gap: 0.9rem;
    padding-bottom: 1rem;
    position: relative;
}

/* Connecting line between markers, stopping at the last entry. */
.history-entry:not(:last-child)::before {
    content: '';
    position: absolute;
    inset-inline-start: 5px;
    top: 14px;
    bottom: 0;
    width: 2px;
    background: var(--border-color);
}

.history-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    margin-top: 3px;
    flex-shrink: 0;
    background: var(--text-muted);
    z-index: 1;
}

.dot-pending { background: var(--el-color-warning); }
.dot-confirmed, .dot-processing, .dot-shipped { background: var(--el-color-primary); }
.dot-delivered { background: var(--el-color-success); }
.dot-cancelled { background: var(--el-color-danger); }

.history-body { flex: 1; min-width: 0; }
.history-head { display: flex; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap; font-size: 0.86rem; }
.history-when { color: var(--text-muted); font-size: 0.76rem; white-space: nowrap; }
.history-meta { margin: 0.2rem 0 0; font-size: 0.75rem; color: var(--text-muted); }

.history-note {
    margin: 0.35rem 0 0;
    font-size: 0.8rem;
    padding: 0.4rem 0.6rem;
    background: var(--bg-light);
    border-radius: 6px;
    border-inline-start: 3px solid var(--border-color);
}

.timeline-step-tracker {
    background: var(--bg-light);
    border: 1px solid var(--border-color);
    padding: 1.75rem 1.25rem;
    border-radius: var(--radius-md);
}

.visual-progress-timeline {
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
}

.progress-base-bar {
    position: absolute;
    top: 20px;
    left: 8%;
    right: 8%;
    height: 4px;
    background: var(--border-color);
    z-index: 1;
}

.progress-fill-bar {
    position: absolute;
    top: 20px;
    left: 8%;
    height: 4px;
    background: var(--success);
    z-index: 2;
    transition: width 0.4s ease;
}

.timeline-nodes-wrapper {
    display: flex;
    justify-content: space-around;
    width: 100%;
    z-index: 3;
}

.timeline-node {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    position: relative;
}

.node-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--text-light);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    box-shadow: var(--shadow-sm);
    transition: background 0.3s ease;
}

.timeline-node.completed .node-icon {
    background: var(--success);
}

.timeline-node span {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-muted);
}

.timeline-node.completed span {
    color: var(--text-dark);
}

.card-title-txt {
    font-weight: 700;
    font-size: 0.95rem;
    color: var(--text-dark);
}

.financial-summary-block {
    background: var(--bg-light);
    border: 1px solid var(--border-color);
    padding: 1.25rem;
    border-radius: var(--radius-md);
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.5rem;
}

.financial-row {
    display: flex;
    justify-content: space-between;
    width: 250px;
    color: var(--text-medium);
    font-size: 0.9rem;
}

.financial-row.grand-total {
    border-top: 2px solid var(--border-color);
    padding-top: 0.5rem;
    font-weight: 700;
    font-size: 1.05rem;
    color: var(--accent-blue);
}

.notes-txt-view {
    margin: 0;
    font-size: 0.9rem;
    color: var(--text-medium);
    line-height: 1.6;
}

.info-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.info-item {
    display: flex;
    justify-content: space-between;
    font-size: 0.9rem;
}

.info-item .lbl {
    color: var(--text-muted);
}

.info-item strong {
    color: var(--text-dark);
}

.convert-card-box {
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    padding: 1.25rem;
    border-radius: var(--radius-md);
    text-align: center;
}

.convert-tip {
    font-size: 0.85rem;
    color: #065f46;
    margin: 0 0 1rem 0;
    line-height: 1.5;
}

.convert-btn-invoice {
    width: 100%;
    font-weight: 700;
}

/* ---- Order routing ---- */
.routing-cell {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    font-size: 0.85rem;
}

.routing-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.routing-field {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.routing-field .lbl {
    font-size: 0.8rem;
    color: var(--text-muted);
    font-weight: 600;
}

.routing-locked-note {
    margin: 0.6rem 0 0;
    font-size: 0.78rem;
    color: var(--text-muted);
}

.routing-select-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-block-start: 0.4rem;
}

.routing-hint {
    margin: 0.35rem 0 0.7rem;
    font-size: 0.76rem;
    line-height: 1.7;
    color: var(--text-muted);
}

.warehouse-options {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    min-height: 60px;
}

.warehouse-option {
    padding: 0.7rem 0.8rem;
    border: 1px solid var(--border-color);
    border-radius: 10px;
    transition: border-color 0.2s ease, background 0.2s ease;
}

/* A chosen routing is one of the places this order may draw on. */
.warehouse-option.selected {
    border-color: color-mix(in srgb, var(--el-color-primary) 55%, transparent);
    background: color-mix(in srgb, var(--el-color-primary) 4%, transparent);
}

/* The warehouse actually serving the order has to be findable at a glance, and
   outranks the plain "selected" tint when it is both. */
.warehouse-option.current {
    border-color: var(--el-color-primary);
    background: color-mix(in srgb, var(--el-color-primary) 6%, transparent);
}

.warehouse-option.short {
    border-inline-start: 3px solid var(--el-color-warning);
}

.wh-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.45rem;
    gap: 0.5rem;
}

.wh-name {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.9rem;
}

.wh-type {
    font-size: 0.75rem;
    color: var(--text-muted);
    white-space: nowrap;
}

.wh-coverage {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 0.4rem;
    font-size: 0.78rem;
    gap: 0.5rem;
}

.shortfall-list {
    margin: 0.5rem 0 0;
    padding-inline-start: 1.1rem;
    font-size: 0.76rem;
    color: var(--el-color-warning-dark-2, #b88230);
}

.shortfall-list .muted {
    color: var(--text-muted);
}

/* ---- Execution stages ---- */
.stage-explainer {
    margin: 0 0 0.9rem;
    font-size: 0.82rem;
    color: var(--text-muted);
    line-height: 1.7;
}

.stage-actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

/* Element stacks buttons horizontally and strips the margin off siblings;
   these are a vertical list, so that reset has to be undone. */
.stage-actions .stage-btn {
    width: 100%;
    margin: 0;
    font-weight: 600;
}

.dialog-lead {
    margin: 0 0 1rem;
    font-size: 0.85rem;
    color: var(--text-muted);
    line-height: 1.7;
}

.text-success { color: var(--el-color-success); }
.text-warning { color: var(--el-color-warning); }

/* Form Grid row */
.item-grid-row {
    display: flex;
    gap: 1rem;
    align-items: center;
    margin-bottom: 1rem;
    padding: 1rem;
    background: var(--bg-light);
    border-radius: var(--radius-md);
    border: 1px solid var(--border-color);
}
</style>
