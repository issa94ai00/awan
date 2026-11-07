<template>
    <div class="pf-page invoice-form-page">
        <!-- Header: what this is, and the way out -->
        <header class="pf-page-head">
            <div class="pf-title">
                <span class="pf-title-icon is-sale"><i class="fas fa-file-invoice-dollar"></i></span>
                <div>
                    <h2>
                        {{ isEdit ? (t('edit_invoice') || 'تعديل الفاتورة') : (t('create_invoice') || 'إصدار فاتورة مبيعات') }}
                        <span v-if="isEdit && loadedInvoice" class="pf-title-tag" dir="ltr">{{ loadedInvoice.invoice_number }}</span>
                        <span v-if="isEdit" class="pf-title-tag">{{ statusLabels[form.status] || form.status }}</span>
                    </h2>
                    <p>{{ t('prepare_invoice_with_products') || 'إعداد فاتورة مبيعات وإدارة الأصناف والتحصيل' }}</p>
                </div>
            </div>
            <div class="pf-page-actions">
                <span v-if="draftSavedAt" class="pf-hint">
                    <i class="fas fa-floppy-disk"></i> {{ t('sinv_draft_saved', { time: draftSavedAt }) }}
                </span>
                <el-button text @click="shortcutsModalVisible = true">
                    <i class="fas fa-keyboard"></i>&nbsp;{{ t('keyboard_shortcuts') || 'اختصارات لوحة المفاتيح' }}
                </el-button>
                <el-button v-if="!isEdit && items.length" text type="danger" @click="confirmClearInvoice">
                    <i class="fas fa-trash-can"></i>&nbsp;{{ t('sinv_clear') }}
                </el-button>
                <el-button @click="goBack">
                    <i class="fas" :class="backArrow"></i>&nbsp;{{ t('back_to_invoices') || 'العودة للفواتير' }}
                </el-button>
            </div>
        </header>

        <section v-if="loading" class="pf-card pf-skeleton">
            <el-skeleton :rows="8" animated />
        </section>

        <div v-else class="pf-shell">
            <div ref="formRoot" class="pf-doc">
                <!-- Server / validation errors & shortage notices -->
                <el-alert
                    v-if="formErrors.length"
                    :title="t('sinv_save_problems')"
                    type="error"
                    show-icon
                    @close="formErrors = []"
                >
                    <ul class="pf-alerts">
                        <li v-for="(err, idx) in formErrors" :key="idx">{{ err }}</li>
                    </ul>
                </el-alert>

                <!-- Who, from where and when -->
                <section ref="headRef" class="pf-card pf-card-pad">
                    <div class="pf-fields">
                        <div class="pf-field span-8" :class="{ 'is-flagged': isFlagged('customer_id') }">
                            <span class="pf-label">{{ t('client') || 'العميل' }}</span>
                            <div class="sf-customer-row">
                                <el-tooltip
                                    :content="isWalkInCustomer ? t('sinv_walk_in_on_hint') : t('sinv_walk_in_off_hint')"
                                    placement="top"
                                >
                                    <button
                                        type="button"
                                        class="sf-walkin"
                                        :class="{ 'is-active': isWalkInCustomer }"
                                        @click="toggleWalkInCustomer"
                                    >
                                        <i class="fas fa-cash-register"></i>
                                        <span>{{ t('sinv_walk_in') }}</span>
                                    </button>
                                </el-tooltip>

                                <el-select
                                    v-if="!isWalkInCustomer"
                                    ref="customerSelectRef"
                                    v-model="form.customer_id"
                                    filterable
                                    clearable
                                    remote
                                    reserve-keyword
                                    :remote-method="searchCustomers"
                                    :loading="customersLoading"
                                    :placeholder="t('search_or_choose_client') || 'ابحث باسم العميل، الهاتف، أو الشركة…'"
                                    @change="onCustomerPicked"
                                    @focus="customerOptions.length <= 1 && searchCustomers('')"
                                >
                                    <el-option
                                        v-for="customer in customerOptions"
                                        :key="customer.id"
                                        :label="customer.name"
                                        :value="customer.id"
                                    >
                                        <div class="sf-option">
                                            <div class="sf-option-main">
                                                <span class="sf-option-name">{{ customer.name }}</span>
                                                <span v-if="customer.phone" class="sf-option-meta" dir="ltr">{{ customer.phone }}</span>
                                            </div>
                                            <span v-if="customer.company" class="sf-option-meta">{{ customer.company }}</span>
                                        </div>
                                    </el-option>
                                    <template #loading>
                                        <div class="sf-select-empty">
                                            <i class="fas fa-spinner fa-spin"></i>&nbsp;{{ t('searching') || 'جاري البحث…' }}
                                        </div>
                                    </template>
                                    <template #empty>
                                        <div class="sf-select-empty">
                                            <p>{{ t('no_clients_found') || 'لم يتم العثور على عملاء مطابقين' }}</p>
                                            <button type="button" class="sf-select-empty-add" @mousedown.prevent @click="openQuickCustomer(customerQuery)">
                                                <i class="fas fa-user-plus"></i>
                                                {{ customerQuery ? (t('add_client_named', { name: customerQuery }) || `إضافة "${customerQuery}" كعميل جديد`) : (t('new_client') || 'إضافة عميل جديد') }}
                                            </button>
                                        </div>
                                    </template>
                                </el-select>

                                <div v-else class="sf-walkin-box">
                                    <i class="fas fa-user-tag"></i>
                                    <span>{{ t('sinv_walk_in_active') }}</span>
                                    <el-button text size="small" type="primary" @click="isWalkInCustomer = false">
                                        {{ t('sinv_pick_registered') }}
                                    </el-button>
                                </div>

                                <el-button v-if="!isWalkInCustomer" plain :title="t('new_client') || 'إضافة عميل جديد'" @click="toggleQuickCustomer">
                                    <i class="fas" :class="quickCustomerOpen ? 'fa-xmark' : 'fa-user-plus'"></i>
                                </el-button>
                            </div>

                            <small v-if="selectedCustomer && !isWalkInCustomer" class="sf-customer-facts">
                                <span v-if="selectedCustomer.phone"><i class="fas fa-phone"></i> <span dir="ltr">{{ selectedCustomer.phone }}</span></span>
                                <span v-if="selectedCustomer.company"><i class="fas fa-building"></i> {{ selectedCustomer.company }}</span>
                                <span v-if="clientBalance" :class="clientBalance.tone">
                                    <i class="fas fa-wallet"></i> {{ clientBalance.label }}: {{ money(clientBalance.amount) }}
                                </span>
                                <button v-if="hasCustomerCredit" type="button" class="sf-link-btn" @click="applyCustomerCredit">
                                    <i class="fas fa-arrow-down"></i> {{ t('sinv_use_credit') }}
                                </button>
                                <button type="button" class="sf-link-btn is-muted" :title="t('sinv_clear_customer')" @click="clearCustomerSelection">
                                    <i class="fas fa-xmark"></i> {{ t('sinv_clear_customer') }}
                                </button>
                            </small>

                            <transition name="el-zoom-in-top">
                                <CustomerQuickAdd
                                    v-if="quickCustomerOpen"
                                    :seed="quickCustomerSeed"
                                    @select="onQuickCustomerSelect"
                                    @close="quickCustomerOpen = false"
                                />
                            </transition>
                        </div>

                        <div class="pf-field span-4" :class="{ 'is-flagged': isFlagged('due_date') }">
                            <span class="pf-label">{{ t('due_date') || 'تاريخ الاستحقاق' }}</span>
                            <el-date-picker
                                ref="deliveryPicker"
                                v-model="form.due_date"
                                type="date"
                                value-format="YYYY-MM-DD"
                                format="YYYY-MM-DD"
                                :placeholder="t('due_date') || 'اختر التاريخ'"
                                style="width: 100%"
                            />
                            <div class="pf-chips" role="group" :aria-label="t('due_date')">
                                <button
                                    v-for="days in [0, 7, 14, 30, 60]"
                                    :key="days"
                                    type="button"
                                    class="pf-chip-btn"
                                    :class="{ 'is-active': form.due_date === inDays(days) }"
                                    :aria-pressed="form.due_date === inDays(days)"
                                    @click="form.due_date = inDays(days)"
                                >
                                    {{ days === 0 ? t('today') : t(days <= 10 ? 'sinv_days_few' : 'sinv_days_many', { count: days }) }}
                                </button>
                            </div>
                        </div>

                        <div class="pf-field" :class="isEdit ? 'span-4' : 'span-6'">
                            <span class="pf-label"><i class="fas fa-warehouse"></i> {{ t('sinv_default_warehouse') }}</span>
                            <el-select
                                v-model="form.default_warehouse_id"
                                filterable
                                clearable
                                :loading="warehousesLoading"
                                :placeholder="t('choose_warehouse_placeholder') || 'اختر مستودع الصرف'"
                                @change="onDefaultWarehouseChange"
                            >
                                <el-option v-for="wh in warehouses" :key="wh.id" :label="wh.name" :value="wh.id">
                                    <div class="sf-option-main">
                                        <span>{{ wh.name }}</span>
                                        <span v-if="wh.code" class="sf-option-meta" dir="ltr">{{ wh.code }}</span>
                                    </div>
                                </el-option>
                                <template #empty>
                                    <div class="sf-select-empty">
                                        <p>{{ t('sinv_no_warehouses') }}</p>
                                        <button type="button" class="sf-select-empty-add" @click="fetchWarehouses">
                                            <i class="fas fa-rotate"></i> {{ t('sinv_retry') }}
                                        </button>
                                    </div>
                                </template>
                            </el-select>
                        </div>

                        <div class="pf-field" :class="isEdit ? 'span-4' : 'span-6'">
                            <span class="pf-label"><i class="fas fa-user-tie"></i> {{ t('sales_representative') || 'مندوب المبيعات' }}</span>
                            <el-select
                                v-model="form.assigned_employee_id"
                                filterable
                                :clearable="!isSalesRep"
                                :disabled="isSalesRep"
                                :loading="employeesLoading"
                                :placeholder="t('unassigned') || 'غير محدد (اختر المندوب)'"
                            >
                                <el-option v-for="employee in salesEmployees" :key="employee.id" :label="employee.name" :value="employee.id">
                                    <div class="sf-option-main">
                                        <span>{{ employee.name }}</span>
                                        <span v-if="employee.department" class="sf-option-meta">{{ employee.department }}</span>
                                    </div>
                                </el-option>
                                <template #empty>
                                    <div class="sf-select-empty">
                                        <p>{{ t('sinv_no_reps') }}</p>
                                        <button type="button" class="sf-select-empty-add" @click="fetchEmployees">
                                            <i class="fas fa-rotate"></i> {{ t('sinv_retry') }}
                                        </button>
                                    </div>
                                </template>
                            </el-select>
                        </div>

                        <div v-if="isEdit" class="pf-field span-4">
                            <span class="pf-label"><i class="fas fa-flag"></i> {{ t('sinv_status') }}</span>
                            <el-select v-model="form.status">
                                <el-option
                                    v-for="st in availableStatuses"
                                    :key="st"
                                    :value="st"
                                    :label="statusLabels[st] || st"
                                />
                            </el-select>
                        </div>
                    </div>
                </section>

                <!-- What still stands between the form and a save -->
                <div v-if="showChecklist" class="pf-checklist" :class="{ 'is-ready': !issues.length }">
                    <span class="pf-checklist-title">
                        <i class="fas" :class="issues.length ? 'fa-list-check' : 'fa-circle-check'"></i>
                        {{ issues.length ? t('pf_before_saving') : (t('sinv_ready') || t('pf_ready_to_save')) }}
                    </span>
                    <button v-for="issue in issues" :key="issue.key" type="button" @click="goToIssue(issue)">
                        <i class="fas fa-angle-left"></i> {{ issue.text }}
                    </button>
                </div>

                <!-- Lines -->
                <section ref="linesRef" class="pf-card pf-lines-card">
                    <header class="pf-card-head">
                        <h3 class="pf-card-title">
                            <i class="fas fa-boxes-stacked"></i>
                            {{ t('invoice_items') || 'بنود الفاتورة' }}
                            <span class="pf-count">{{ items.length }}</span>
                        </h3>
                        <div class="pf-card-tools">
                            <span v-if="shortLines.length" class="pf-badge is-warn">
                                <i class="fas fa-triangle-exclamation"></i>
                                {{ t('sinv_lines_over_stock', { count: shortLines.length }) }}
                            </span>
                            <el-popconfirm
                                v-if="items.length"
                                :title="t('confirm_clear_all_lines') || 'هل تريد تفريغ وحذف جميع البنود؟'"
                                :confirm-button-text="t('yes_delete_all') || 'نعم، حذف الكل'"
                                :cancel-button-text="t('cancel') || 'إلغاء'"
                                confirm-button-type="danger"
                                @confirm="clearAllLines"
                            >
                                <template #reference>
                                    <el-button size="small" text type="danger">
                                        <i class="fas fa-eraser"></i>&nbsp;{{ t('pf_clear_lines') }}
                                    </el-button>
                                </template>
                            </el-popconfirm>
                        </div>
                    </header>

                    <!-- Undo for the last removed line -->
                    <transition name="el-zoom-in-top">
                        <div v-if="lastRemoved" class="sf-undo" role="status">
                            <span>
                                <i class="fas fa-trash-can"></i>&nbsp;{{ t('sinv_line_removed') }}:
                                <strong>{{ lastRemoved.item.name }}</strong>
                            </span>
                            <el-button size="small" type="primary" text @click="undoRemove">
                                <i class="fas fa-rotate-left"></i>&nbsp;{{ t('undo') || 'تراجع' }}
                            </el-button>
                        </div>
                    </transition>

                    <template v-if="items.length">
                        <div class="pf-lines-head sline-grid-inv">
                            <span>#</span>
                            <span>{{ t('product') || 'المنتج' }}</span>
                            <span>{{ t('so_unit') || t('unit') || 'الوحدة' }}</span>
                            <span>{{ t('quantity') || 'الكمية' }}</span>
                            <span>{{ t('unit_price') || 'سعر الوحدة' }}</span>
                            <span>{{ t('total') || 'الإجمالي' }}</span>
                            <span></span>
                        </div>
                        <div class="pf-lines">
                            <div
                                v-for="(item, i) in items"
                                :key="item.pick"
                                class="sline sline-grid-inv"
                                :class="`tone-${lineTone(item)}`"
                            >
                                <span class="sline-idx">{{ i + 1 }}</span>

                                <div class="sline-product">
                                    <EntityImage
                                        :src="item.image || getImageUrl(item.image)"
                                        type="product"
                                        :size="44"
                                        shape="square"
                                        class="sline-thumb"
                                    />
                                    <div class="sline-info">
                                        <div class="sline-title">
                                            <strong class="sline-name" :title="item.name">{{ item.name }}</strong>
                                            <span v-if="Number(item.price) === 0" class="sline-badge">
                                                <i class="fas fa-gift"></i> {{ t('sinv_free_item') }}
                                            </span>
                                        </div>
                                        <div class="sline-meta">
                                            <template v-if="!variantInfo(item)">
                                                <span v-if="item.sku" class="sline-sku" dir="ltr"><i class="fas fa-barcode"></i> {{ item.sku }}</span>
                                                <span v-if="item.variant_label">{{ item.variant_label }}</span>
                                            </template>
                                            <span v-if="item.stock !== undefined" class="sline-stock" :class="isLineShort(item) ? 'is-out' : 'is-ok'">
                                                <i class="fas" :class="isLineShort(item) ? 'fa-triangle-exclamation' : 'fa-warehouse'"></i>
                                                {{ isLineShort(item) ? t('sinv_stock_short', { stock: item.stock }) : t('sinv_available', { stock: item.stock }) }}
                                            </span>
                                            <el-dropdown
                                                v-if="item.allocations && item.allocations[0]"
                                                trigger="click"
                                                max-height="280px"
                                                @command="(whId) => setLineWarehouse(item, whId)"
                                            >
                                                <button
                                                    type="button"
                                                    class="sline-wh"
                                                    :class="{
                                                        'is-missing': !item.allocations[0].warehouse_id || lineWarehouseShort(item),
                                                        'is-custom': item.allocations[0].warehouse_id && item.allocations[0].warehouse_id !== form.default_warehouse_id,
                                                    }"
                                                    :title="t('sinv_source_warehouse')"
                                                >
                                                    <i class="fas fa-warehouse"></i>
                                                    <span>{{ warehouseName(item.allocations[0].warehouse_id) || (t('choose_warehouse') || 'اختر المستودع') }}</span>
                                                    <small
                                                        v-if="item.warehouse_stock && item.allocations[0].warehouse_id"
                                                        :class="{ 'is-short': lineWarehouseShort(item) }"
                                                        :title="t('sinv_available_in_warehouse')"
                                                    >· {{ lineWarehouseQty(item, item.allocations[0].warehouse_id) }}</small>
                                                    <i class="fas fa-chevron-down sline-wh-caret"></i>
                                                </button>
                                                <template #dropdown>
                                                    <el-dropdown-menu>
                                                        <el-dropdown-item
                                                            v-for="wh in warehouses"
                                                            :key="wh.id"
                                                            :command="wh.id"
                                                        >
                                                            <i class="fas" :class="wh.id === item.allocations[0].warehouse_id ? 'fa-check text-primary' : 'fa-warehouse text-muted'"></i>
                                                            &nbsp;{{ wh.name }}
                                                            <small v-if="wh.id === form.default_warehouse_id" class="text-muted">&nbsp;({{ t('sinv_default') }})</small>
                                                            <small
                                                                v-if="item.warehouse_stock"
                                                                class="sline-wh-qty"
                                                                :class="{ 'is-zero': lineWarehouseQty(item, wh.id) <= 0 }"
                                                            >{{ lineWarehouseQty(item, wh.id) }}</small>
                                                        </el-dropdown-item>
                                                    </el-dropdown-menu>
                                                </template>
                                            </el-dropdown>
                                        </div>

                                        <VariantDetails
                                            v-if="variantInfo(item)"
                                            :variant="variantInfo(item)"
                                            show-codes
                                            class="sline-variant-details"
                                        />

                                        <!-- The product's other sizes, one click away -->
                                        <div v-if="lineSiblings(item).length > 1" class="sline-sizes">
                                            <span class="sline-sizes-label"><i class="fas fa-tag"></i> {{ t('po_switch_size') || 'المقاس:' }}</span>
                                            <button
                                                v-for="v in lineSiblings(item)"
                                                :key="optionKey(v)"
                                                type="button"
                                                class="sline-size"
                                                :class="{
                                                    'is-active': (v.variant_id || null) === (item.product_variant_id || null),
                                                    'is-out': v.stock_quantity != null && Number(v.stock_quantity) <= 0,
                                                }"
                                                :title="[v.sku, money(v.price)].filter(Boolean).join(' · ')"
                                                @click.stop="switchLineVariant(item, v)"
                                            >
                                                <span dir="ltr">{{ v.variant_label || v.sku }}</span>
                                                <small v-if="v.stock_quantity != null">{{ v.stock_quantity }}</small>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="sline-fields">
                                    <div class="sline-field">
                                        <label>{{ t('so_unit') || 'الوحدة' }}</label>
                                        <el-select
                                            v-if="item.units && item.units.length > 1"
                                            v-model="item.selectedUnit"
                                            value-key="id"
                                            @change="(u) => onUnitChange(item, u)"
                                        >
                                            <el-option
                                                v-for="u in item.units"
                                                :key="u.id ?? u.name"
                                                :label="u.name_ar || u.name"
                                                :value="u"
                                            />
                                        </el-select>
                                        <span v-else class="sline-unit">
                                            <i class="fas fa-box text-muted"></i>
                                            {{ item.selectedUnit?.name_ar || item.selectedUnit?.name || t('piece') || 'حبة' }}
                                        </span>
                                    </div>

                                    <div class="sline-field">
                                        <label>{{ t('quantity') || 'الكمية' }}</label>
                                        <div v-if="item.allocations && item.allocations[0]" class="sline-stepper" :class="{ 'is-over': isLineShort(item) }">
                                            <button
                                                type="button"
                                                :class="{ 'is-remove': (item.allocations[0].quantity || 1) <= 1 }"
                                                :title="(item.allocations[0].quantity || 1) <= 1 ? (t('sinv_delete_line') || 'حذف البند') : (t('decrease') || 'إنقاص')"
                                                @click.stop="decrementQty(item, i)"
                                            >
                                                <i class="fas" :class="(item.allocations[0].quantity || 1) <= 1 ? 'fa-trash-can' : 'fa-minus'"></i>
                                            </button>
                                            <input
                                                :value="item.allocations[0].quantity"
                                                type="number"
                                                inputmode="numeric"
                                                min="1"
                                                step="1"
                                                dir="ltr"
                                                :aria-label="t('quantity')"
                                                @focus="$event.target.select()"
                                                @input="onLineQtyInput(item, $event)"
                                                @blur="onLineQtyBlur(item, $event)"
                                            />
                                            <button type="button" :title="t('increase') || 'زيادة'" @click.stop="incrementQty(item)">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                        <small v-if="unitMult(item) > 1" class="sline-hint">
                                            = {{ formatNumber(linePieces(item)) }} {{ t('piece') || 'قطعة' }}
                                        </small>
                                    </div>

                                    <div class="sline-field">
                                        <label>{{ t('unit_price') || 'السعر' }}</label>
                                        <el-input-number
                                            v-model="item.price"
                                            :min="0"
                                            :precision="2"
                                            :controls="false"
                                            :class="{ 'is-changed': priceChanged(item) }"
                                        />
                                        <small v-if="priceChanged(item)" class="sline-hint is-changed">
                                            <span class="was">{{ money(listPrice(item)) }}</span>
                                            <button type="button" :title="t('sinv_restore_list_price')" @click="restoreListPrice(item)">
                                                <i class="fas fa-rotate-left"></i>
                                            </button>
                                        </small>
                                    </div>

                                    <div class="sline-field sline-total">
                                        <label>{{ t('total') || 'الإجمالي' }}</label>
                                        <strong>{{ money(lineTotal(item)) }}</strong>
                                    </div>
                                </div>

                                <div class="sline-actions">
                                    <el-dropdown trigger="click" @command="(cmd) => onLineCommand(cmd, item, i)">
                                        <button type="button" class="sline-btn" :title="t('sinv_more')" :aria-label="t('sinv_more')">
                                            <i class="fas fa-ellipsis-vertical"></i>
                                            <span v-if="item.notes" class="dot"></span>
                                        </button>
                                        <template #dropdown>
                                            <el-dropdown-menu>
                                                <el-dropdown-item command="note">
                                                    <i class="fas fa-comment-dots"></i>&nbsp;{{ t('sinv_line_note') }}
                                                </el-dropdown-item>
                                                <el-dropdown-item command="duplicate">
                                                    <i class="fas fa-copy"></i>&nbsp;{{ t('duplicate_line') || 'تكرار البند' }}
                                                </el-dropdown-item>
                                                <el-dropdown-item command="free">
                                                    <i class="fas fa-gift"></i>&nbsp;{{ t('sinv_mark_free') }}
                                                </el-dropdown-item>
                                            </el-dropdown-menu>
                                        </template>
                                    </el-dropdown>
                                    <button
                                        type="button"
                                        class="sline-btn is-remove"
                                        :title="t('sinv_delete_line') || 'حذف البند'"
                                        :aria-label="t('sinv_delete_line') || 'حذف البند'"
                                        @click.stop="removeItem(i)"
                                    >
                                        <i class="fas fa-trash-can"></i>
                                    </button>
                                </div>

                                <div v-if="item._showNote || item.notes" class="sline-note">
                                    <el-input
                                        v-model="item.notes"
                                        size="small"
                                        maxlength="500"
                                        show-word-limit
                                        clearable
                                        :placeholder="t('sinv_line_note_placeholder')"
                                    >
                                        <template #prefix>
                                            <i class="fas fa-pen-to-square"></i>
                                        </template>
                                    </el-input>
                                </div>
                            </div>
                        </div>
                    </template>

                    <div v-else class="pf-empty">
                        <span class="pf-empty-icon"><i class="fas fa-cart-shopping"></i></span>
                        <strong>{{ t('sinv_empty_title') }}</strong>
                        <p>{{ t('sinv_empty_hint') }}</p>
                        <div class="pf-row">
                            <el-button type="primary" @click="focusCatalog"><i class="fas fa-magnifying-glass"></i>&nbsp;{{ t('pf_search_catalog') }}</el-button>
                            <el-button @click="openQuickProduct('')"><i class="fas fa-plus"></i>&nbsp;{{ t('pqa_button') }}</el-button>
                        </div>
                    </div>

                    <footer v-if="items.length" class="pf-lines-foot">
                        <span>{{ t('sinv_items_count', { count: items.length }) }} · {{ t('sinv_pieces_count', { count: totalPieces }) }}</span>
                        <span>{{ t('subtotal') || 'المجموع الفرعي' }}: <strong>{{ money(subtotal) }}</strong></span>
                    </footer>
                </section>

                <!-- Payment, extras and the money -->
                <div class="pf-bottom">
                    <div class="sf-side-stack">
                        <section ref="payRef" class="pf-card pf-card-pad sf-pay">
                            <div class="sf-pay-head">
                                <span class="pf-label"><i class="fas fa-wallet"></i> {{ t('sinv_settlement') }}</span>
                                <span class="sf-remaining" :class="remainingTone">
                                    {{ remaining > 0 ? t('sinv_remaining_due') : (remaining < 0 ? t('sinv_credit_left') : (t('fully_settled') || 'مدفوعة بالكامل')) }}
                                    <strong>{{ money(Math.abs(remaining)) }}</strong>
                                </span>
                            </div>

                            <div class="sf-methods" role="radiogroup" :aria-label="t('payment_method')">
                                <button
                                    v-for="m in PAYMENT_METHODS"
                                    :key="m.value"
                                    type="button"
                                    class="sf-method"
                                    :class="{ 'is-active': form.payment_method === m.value }"
                                    :aria-pressed="form.payment_method === m.value"
                                    @click="form.payment_method = m.value"
                                >
                                    <i class="fas" :class="m.icon"></i>
                                    <span>{{ m.label() }}</span>
                                </button>
                            </div>

                            <div class="sf-pay-row">
                                <div class="pf-field span-12">
                                    <span class="pf-label">{{ t('sinv_paid_now') }}</span>
                                    <div class="sf-money">
                                        <el-input-number
                                            v-model="form.paid_amount"
                                            :min="0"
                                            :precision="2"
                                            :controls="false"
                                        />
                                    </div>
                                </div>
                                <div class="pf-chips">
                                    <button
                                        type="button"
                                        class="pf-chip-btn"
                                        :class="{ 'is-active': form.paid_amount === total && total > 0 }"
                                        @click="form.paid_amount = total"
                                    >
                                        <i class="fas fa-check-double"></i> {{ t('sinv_pay_full') }}
                                    </button>
                                    <button
                                        v-if="!isWalkInCustomer"
                                        type="button"
                                        class="pf-chip-btn"
                                        :class="{ 'is-active': form.paid_amount === 0 }"
                                        @click="form.paid_amount = 0"
                                    >
                                        <i class="fas fa-clock"></i> {{ t('sinv_pay_on_credit') }}
                                    </button>
                                </div>
                            </div>

                            <div v-if="form.payment_method === 'cash' && form.paid_amount > 0" class="sf-change">
                                <span class="pf-label">{{ t('sinv_cash_tendered') }}</span>
                                <el-input-number
                                    v-model="cashTendered"
                                    :min="0"
                                    :precision="2"
                                    :controls="false"
                                    size="small"
                                    :placeholder="money(form.paid_amount)"
                                />
                                <span v-if="cashChangeAmount > 0" class="sf-change-due">
                                    {{ t('sinv_change_due') }}: <strong>{{ money(cashChangeAmount) }}</strong>
                                </span>
                            </div>

                            <p v-if="total > 0" class="sf-words">
                                <i class="fas fa-receipt"></i>&nbsp;{{ tafqeet(total) }}
                            </p>
                        </section>

                        <section class="pf-card pf-card-pad">
                            <div v-if="!showExpenses || !showNotes" class="pf-chips" :style="showExpenses || showNotes ? 'margin-bottom: 0.75rem' : ''">
                                <button v-if="!showExpenses" type="button" class="sf-add-chip" @click="expensesOpen = true">
                                    <i class="fas fa-plus"></i> {{ t('sinv_add_charges') }}
                                </button>
                                <button v-if="!showNotes" type="button" class="sf-add-chip" @click="notesOpen = true">
                                    <i class="fas fa-plus"></i> {{ t('sinv_notes') }}
                                </button>
                            </div>
                            <OrderExpensesEditor v-if="showExpenses" v-model="form.expenses" />
                            <template v-if="showNotes">
                                <span class="pf-label" style="margin: 0.5rem 0 0.4rem"><i class="fas fa-note-sticky"></i> {{ t('sinv_notes') }}</span>
                                <el-input
                                    v-model="form.notes"
                                    type="textarea"
                                    :rows="3"
                                    maxlength="1000"
                                    show-word-limit
                                    :placeholder="t('sinv_notes_placeholder')"
                                />
                            </template>
                        </section>
                    </div>

                    <section ref="totalsRef" class="pf-card pf-card-pad pf-totals">
                        <div class="pf-sum-row">
                            <span>{{ t('subtotal') || 'إجمالي الأصناف' }}</span>
                            <strong>{{ money(subtotal) }}</strong>
                        </div>

                        <div class="sf-disc">
                            <div class="sf-disc-head">
                                <span class="sf-disc-label"><i class="fas fa-tag"></i> {{ t('sales.discount_label') || t('discount') || 'الخصم' }}</span>
                                <div class="sf-seg" role="radiogroup" :aria-label="t('discount')">
                                    <button type="button" :class="{ 'is-active': form.discount_type === 'percent' }" @click="switchDiscountType('percent')">%</button>
                                    <button type="button" :class="{ 'is-active': form.discount_type === 'amount' }" @click="switchDiscountType('amount')">{{ t('sales.discount_as_amount') || 'مبلغ' }}</button>
                                </div>
                            </div>
                            <div class="sf-money" :class="{ 'is-invalid': !!discountError }">
                                <el-input-number
                                    v-if="form.discount_type === 'percent'"
                                    ref="discountInput"
                                    v-model="form.discount_percent"
                                    :min="0"
                                    :max="100"
                                    :precision="2"
                                    :controls="false"
                                />
                                <el-input-number
                                    v-else
                                    ref="discountInput"
                                    v-model="form.discount_amount"
                                    :min="0"
                                    :max="subtotal"
                                    :precision="2"
                                    :controls="false"
                                />
                            </div>
                            <div class="sf-disc-foot">
                                <div class="pf-chips">
                                    <button
                                        v-for="d in [5, 10, 15, 20]"
                                        :key="d"
                                        type="button"
                                        class="pf-chip-btn"
                                        :class="{ 'is-active': form.discount_type === 'percent' && Number(form.discount_percent) === d }"
                                        @click="setDiscountPreset(form.discount_type === 'percent' && Number(form.discount_percent) === d ? 0 : d)"
                                    >{{ d }}%</button>
                                </div>
                                <span v-if="!discountError && discountAmount > 0" class="sf-disc-live">
                                    <template v-if="form.discount_type === 'percent'">− {{ money(discountAmount) }}</template>
                                    <template v-else>≈ {{ effectiveDiscountRate }}%</template>
                                </span>
                            </div>
                            <small v-if="discountError" class="sf-disc-error">{{ discountError }}</small>
                        </div>

                        <button v-if="!showTax" type="button" class="sf-add-chip" @click="taxOpen = true">
                            <i class="fas fa-plus"></i> {{ t('sinv_add_tax') }}
                        </button>
                        <div v-else class="sf-disc">
                            <div class="sf-disc-head">
                                <span class="sf-disc-label"><i class="fas fa-receipt"></i> {{ t('tax_percent') }}</span>
                                <div class="pf-chips">
                                    <button type="button" class="pf-chip-btn" :class="{ 'is-active': Number(form.tax_percent) === 0 }" @click="form.tax_percent = 0">0%</button>
                                    <button type="button" class="pf-chip-btn" :class="{ 'is-active': Number(form.tax_percent) === 15 }" @click="form.tax_percent = 15">15%</button>
                                </div>
                            </div>
                            <div class="sf-money">
                                <el-input-number
                                    v-model="form.tax_percent"
                                    :min="0"
                                    :max="100"
                                    :precision="2"
                                    :controls="false"
                                />
                            </div>
                            <span v-if="taxAmount > 0" class="pf-hint">+ {{ money(taxAmount) }}</span>
                        </div>

                        <div v-if="totalExpenses > 0" class="pf-sum-row">
                            <span>{{ t('sinv_add_charges') }}</span>
                            <span class="pf-num">+ {{ money(totalExpenses) }}</span>
                        </div>

                        <div class="pf-grand" :class="{ 'is-invalid': !!discountError }">
                            <span>{{ t('sinv_net_total') }}</span>
                            <strong>{{ money(total) }}</strong>
                        </div>
                    </section>
                </div>
            </div>

            <aside class="pf-side" :class="{ 'is-folded': catalogFolded }">
                <button type="button" class="pf-catalog-toggle" @click="catalogFolded = !catalogFolded">
                    <span><i class="fas fa-store"></i> {{ t('pf_catalog') }}</span>
                    <i class="fas" :class="catalogFolded ? 'fa-chevron-down' : 'fa-chevron-up'"></i>
                </button>
                <SalesCatalogPanel
                    ref="quickSearchInputRef"
                    v-model:query="quickSearchQuery"
                    v-model:category-id="selectedCategoryId"
                    :groups="quickSearchGroups"
                    :loading="quickSearchLoading"
                    :categories="categoryOptions"
                    :count-of="getItemQuantity"
                    :pending-of="pendingQtyOf"
                    :is-over="isRowOverStock"
                    :name-of="productName"
                    :default-warehouse-id="form.default_warehouse_id"
                    :add-label="t('add') || 'إضافة'"
                    :placeholder="t('search_product_placeholder') || 'ابحث بالاسم، الباركود، الكود (F2)...'"
                    @input="onQuickSearchInput"
                    @enter="onQuickSearchEnter"
                    @clear="onQuickSearchClear"
                    @category-change="onCategoryFilterChange"
                    @create="openQuickProduct"
                    @add="addProductDirect"
                    @bump-pending="bumpPendingQty"
                    @set-pending="setPendingQty"
                    @blur-pending="normalizePendingQty"
                    @decrement="decrementProduct"
                    @set="setProductQuantity"
                    @commit="commitProductQuantity"
                />
            </aside>
        </div>

        <!-- Footer: the money at a glance and the ways to save -->
        <footer v-if="!loading" class="pf-page-footer">
            <div class="pf-footer">
                <div class="pf-footer-sum">
                    <div class="pf-footer-metric is-main" :class="{ 'is-invalid': !!discountError }">
                        <span>{{ t('sinv_net_total') }}</span>
                        <strong>{{ money(total) }}</strong>
                    </div>
                    <span class="pf-footer-sep"></span>
                    <div class="pf-footer-metric">
                        <span>{{ t('items') }}</span>
                        <strong>{{ items.length }}</strong>
                    </div>
                    <div class="pf-footer-metric">
                        <span>{{ t('pf_units') }}</span>
                        <strong>{{ formatNumber(totalPieces) }}</strong>
                    </div>
                    <div v-if="total > 0" class="pf-footer-metric" :class="{ 'is-warn': remaining > 0.005 }">
                        <span>{{ remaining > 0 ? t('sinv_remaining_due') : t('sinv_paid_now') }}</span>
                        <strong>{{ money(remaining > 0 ? remaining : form.paid_amount) }}</strong>
                    </div>
                </div>
                <div class="pf-footer-actions">
                    <span v-if="issues.length" class="pf-footer-hint">
                        <i class="fas fa-circle-exclamation"></i> {{ blockerText }}
                    </span>
                    <span v-else class="pf-kbd-hint"><kbd>F9</kbd> · <kbd>Ctrl</kbd> + <kbd>Enter</kbd> · <kbd>F2</kbd> {{ t('search') }} · <kbd>F4</kbd> {{ t('pqa_button') }}</span>
                    <el-button @click="goBack">{{ t('cancel') }}</el-button>
                    <template v-if="!isEdit">
                        <el-button :loading="submitting" :disabled="!canSubmit" @click="submitInvoice('new')">
                            <i class="fas fa-plus"></i>&nbsp;{{ t('sinv_save_and_new') }}
                        </el-button>
                        <el-button type="success" plain :loading="submitting" :disabled="!canSubmit" @click="submitInvoice('print')">
                            <i class="fas fa-print"></i>&nbsp;{{ t('sinv_save_and_print') }}
                        </el-button>
                    </template>
                    <el-button type="primary" :loading="submitting" :disabled="!canSubmit" @click="submitInvoice('close')">
                        <i class="fas fa-circle-check"></i>&nbsp;{{ isEdit ? (t('save_changes') || 'حفظ التعديلات') : t('sinv_issue') }}
                    </el-button>
                </div>
            </div>
        </footer>

        <ProductQuickAdd
            v-model="quickProductVisible"
            :seed="quickProductSeed"
            :categories="categoryOptions"
            :default-category-id="selectedCategoryId"
            @created="onQuickProductCreated"
            @use-existing="(row, qty) => addProductDirect(row, qty)"
        />

        <el-dialog
            v-model="shortcutsModalVisible"
            :title="t('keyboard_shortcuts') || 'اختصارات لوحة المفاتيح'"
            width="460px"
            destroy-on-close
            append-to-body
        >
            <div class="sf-shortcuts">
                <div><span>{{ t('sinv_sc_search') }}</span><kbd>F2</kbd></div>
                <div><span>{{ t('sinv_sc_new_product') }}</span><kbd>F4</kbd></div>
                <div>
                    <span>{{ t('sinv_sc_save') }}</span>
                    <span><kbd>Ctrl</kbd> + <kbd>Enter</kbd> {{ t('sinv_sc_or') }} <kbd>F9</kbd></span>
                </div>
                <div><span>{{ t('sinv_sc_close') }}</span><kbd>Esc</kbd></div>
            </div>
            <template #footer>
                <el-button type="primary" @click="shortcutsModalVisible = false">{{ t('close') || 'إغلاق' }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { useRouter, useRoute, onBeforeRouteLeave } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useInvoicesStore } from '@/stores/invoices';
import { useAuthStore } from '@/stores/auth';
import { posApi } from '@/api/pos';
import { invoicesApi } from '@/api/invoices';
import { categoriesApi } from '@/api/categories';
import api from '@/api';
import { formatMoney, formatNumber, roundUnitPrice, unitPriceDigits } from '@/utils/currency';
import { tafqeet } from '@/utils/tafqeet';
import { getImageUrl } from '@/utils/imageUrl';
import { resolveImageUrl } from '@/utils/productImages';
import { pickKey, optionKey, variantLabelOf, variantFieldsOf, lineVariantInfo } from '@/utils/productPick';
import { useVariantSiblings } from '@/composables/useVariantSiblings';
import EntityImage from '@/components/admin/EntityImage.vue';
import VariantDetails from '@/components/admin/products/VariantDetails.vue';
import OrderExpensesEditor from '@/components/admin/sales/OrderExpensesEditor.vue';
import CustomerQuickAdd from '@/components/admin/sales/CustomerQuickAdd.vue';
import ProductQuickAdd from '@/components/admin/sales/ProductQuickAdd.vue';
import SalesCatalogPanel from '@/components/admin/sales/SalesCatalogPanel.vue';
import '@/components/admin/sales/sales-form.css';
import { ElMessage, ElMessageBox } from 'element-plus';

const { t, locale } = useI18n();
const router = useRouter();
const route = useRoute();
const invoicesStore = useInvoicesStore();
const authStore = useAuthStore();

const invoiceId = route.params.id || null;
const isEdit = computed(() => !!invoiceId);
const isSalesRep = computed(() => !!authStore.user?.is_sales_scoped && !!authStore.user?.employee_id);
const backArrow = computed(() => (locale.value === 'ar' ? 'fa-arrow-right' : 'fa-arrow-left'));
const DRAFT_KEY = 'invoice-draft';

const toNum = (v) => (Number.isFinite(Number(v)) ? Number(v) : 0);
const round2 = (n) => Math.round((Number(n) || 0) * 100) / 100;
const money = (val) => formatMoney(val);
const rate = (val) => Math.min(100, Math.max(0, Number(val) || 0));

function inDays(days) {
    const d = new Date();
    d.setDate(d.getDate() + Number(days));
    return d.toISOString().slice(0, 10);
}

const formRoot = ref(null);
const customerSelectRef = ref(null);
const deliveryPicker = ref(null);
const discountInput = ref(null);
const loading = ref(false);
const loadedInvoice = ref(null);
const submitting = ref(false);
const formErrors = ref([]);
const dirty = ref(false);
let draftTimer = null;
let draftTracking = false;
const draftSavedAt = ref('');

const shortcutsModalVisible = ref(false);

/* ------------------------------------------------------------------ *
 * Workspace: the invoice beside the product catalogue (the purchase
 * order's layout). Below a wide screen the catalogue folds above it.
 * ------------------------------------------------------------------ */
const headRef = ref(null);
const linesRef = ref(null);
const payRef = ref(null);
const totalsRef = ref(null);
const catalogFolded = ref(false);

const focusCatalog = () => {
    catalogFolded.value = false;
    nextTick(() => quickSearchInputRef.value?.focus());
};

const PAYMENT_METHODS = [
    { value: 'cash', icon: 'fa-money-bill-wave', label: () => t('cash') || 'نقداً' },
    { value: 'card', icon: 'fa-credit-card', label: () => t('sinv_pay_card') },
    { value: 'transfer', icon: 'fa-building-columns', label: () => t('bank_transfer') || 'تحويل بنكي' },
    { value: 'check', icon: 'fa-money-check-dollar', label: () => t('sinv_pay_check') },
];

/* ------------------------------------------------------------------ *
 * Form State
 * ------------------------------------------------------------------ */

const blankForm = () => ({
    customer_id: null,
    assigned_employee_id: null,
    default_warehouse_id: null,
    payment_method: 'cash',
    due_date: inDays(0),
    discount_type: 'percent',
    discount_percent: 0,
    discount_amount: 0,
    tax_percent: 0,
    paid_amount: 0,
    notes: '',
    status: 'pending',
    expenses: [],
});

const form = reactive(blankForm());
const items = ref([]);
const isWalkInCustomer = ref(false);

const warehouses = ref([]);
const warehousesLoading = ref(false);
const salesEmployees = ref([]);
const employeesLoading = ref(false);

/* ------------------------------------------------------------------ *
 * Progressive disclosure: rarely used fields stay collapsed until needed
 * ------------------------------------------------------------------ */

const moreOptionsOpen = ref(false);
const expensesOpen = ref(false);
const notesOpen = ref(false);
const taxOpen = ref(false);

const selectedWarehouseName = computed(
    () => warehouses.value.find((w) => w.id === form.default_warehouse_id)?.name || '',
);
const selectedEmployeeName = computed(
    () => salesEmployees.value.find((e) => e.id === form.assigned_employee_id)?.name || '',
);

const warehouseName = (id) => warehouses.value.find((w) => w.id === id)?.name || '';

// Sellable quantity of a line's product in one warehouse (from the search
// breakdown). Base units, so compare against quantity × unit factor.
const lineWarehouseQty = (item, whId) =>
    (item.warehouse_stock || []).find((w) => w.warehouse_id === whId)?.available || 0;

const lineWarehouseShort = (item) => {
    const alloc = item.allocations?.[0];
    if (!item.warehouse_stock || !alloc?.warehouse_id) return false;
    const mult = toNum(item.selectedUnit?.base_unit_multiplier) || 1;
    return toNum(alloc.quantity) * mult > lineWarehouseQty(item, alloc.warehouse_id);
};

const setLineWarehouse = (item, whId) => {
    if (!item.allocations?.[0] || item.allocations[0].warehouse_id === whId) return;
    item.allocations[0].warehouse_id = whId;
    dirty.value = true;
};

const showExpenses = computed(() => expensesOpen.value || (form.expenses || []).length > 0);
const showNotes = computed(() => notesOpen.value || !!form.notes);
const showTax = computed(() => taxOpen.value || Number(form.tax_percent) > 0);

const resetDisclosure = () => {
    moreOptionsOpen.value = false;
    expensesOpen.value = false;
    notesOpen.value = false;
    taxOpen.value = false;
    lastRemoved.value = null;
};

/* Undo for the last removed line */
const lastRemoved = ref(null);
let undoTimer = null;

const fetchWarehouses = async () => {
    warehousesLoading.value = true;
    try {
        let res;
        try {
            res = await api.get('/admin/wms/warehouses', { params: { per_page: 200, is_active: true } });
        } catch {
            try {
                res = await api.get('/admin/warehouses', { params: { per_page: 200, is_active: true } });
            } catch {
                res = await api.get('/wms/warehouses', { params: { per_page: 200, is_active: true } });
            }
        }
        const data = res?.data?.data || res?.data || [];
        warehouses.value = Array.isArray(data) ? data : [];

        // Pre-select employee warehouse if sales rep, otherwise first active warehouse
        if (authStore.isSalesRep && authStore.user?.employee_warehouse_id) {
            if (!isEdit.value || !form.default_warehouse_id) {
                form.default_warehouse_id = authStore.user.employee_warehouse_id;
            }
        } else if (!form.default_warehouse_id && warehouses.value.length) {
            form.default_warehouse_id = warehouses.value[0].id;
        }

        // Propagate to any line missing warehouse_id
        if (form.default_warehouse_id) {
            items.value.forEach((item) => {
                if (item.allocations && item.allocations[0] && !item.allocations[0].warehouse_id) {
                    item.allocations[0].warehouse_id = form.default_warehouse_id;
                }
            });
        }
    } catch (e) {
        console.error('Failed to load warehouses:', e);
        warehouses.value = [];
    } finally {
        warehousesLoading.value = false;
    }
};

const fetchEmployees = async () => {
    employeesLoading.value = true;
    try {
        let empRes;
        try {
            empRes = await api.get('/sales-employees');
        } catch {
            try {
                empRes = await api.get('/admin/sales-employees');
            } catch {
                empRes = await api.get('/admin/employees', { params: { per_page: 200 } });
            }
        }

        const raw = empRes?.data;
        let list = [];
        if (Array.isArray(raw?.data)) {
            list = raw.data;
        } else if (Array.isArray(raw?.data?.employees)) {
            list = raw.data.employees;
        } else if (Array.isArray(raw)) {
            list = raw;
        } else if (Array.isArray(raw?.employees)) {
            list = raw.employees;
        }

        salesEmployees.value = list.map((e) => ({
            id: e.id,
            name: e.name || [e.first_name, e.last_name].filter(Boolean).join(' ') || e.email || `#${e.id}`,
            email: e.email || '',
            department: e.department || '',
        }));

        // If the current logged-in user is a sales rep, auto-select their employee id
        if (authStore.isSalesRep && authStore.user?.employee_id) {
            if (!isEdit.value || !form.assigned_employee_id) {
                form.assigned_employee_id = authStore.user.employee_id;
            }
            if (!salesEmployees.value.some((e) => e.id === authStore.user.employee_id)) {
                salesEmployees.value.unshift({
                    id: authStore.user.employee_id,
                    name: authStore.user.name || t('sales_representative') || 'مندوب المبيعات',
                });
            }
        }
    } catch (e) {
        console.error('Failed to load employees:', e);
        salesEmployees.value = [];
    } finally {
        employeesLoading.value = false;
    }
};

/* ------------------------------------------------------------------ *
 * Customers (Logic & UX with Walk-in & Search Fixes)
 * ------------------------------------------------------------------ */

const customers = ref([]);
const selectedCustomer = ref(null);
const customersLoading = ref(false);
const customerQuery = ref('');
let customerSearchToken = 0;

const customerOptions = computed(() => {
    const chosen = selectedCustomer.value;
    if (!chosen || customerQuery.value) return customers.value;
    return customers.value.some((c) => c.id === chosen.id)
        ? customers.value
        : [chosen, ...customers.value];
});

const clientContactLine = (customer) =>
    [customer?.phone, customer?.company].filter(Boolean).join(' · ');

const clientBalance = computed(() => {
    if (!selectedCustomer.value) return null;
    const balance = parseFloat(selectedCustomer.value.balance) || 0;
    if (Math.abs(balance) < 0.005) return null;
    return {
        amount: Math.abs(balance),
        tone: balance > 0 ? 'is-credit' : 'is-owing',
        label: balance > 0 ? t('sinv_customer_credit') : (t('sinv_customer_owes')),
    };
});

const hasCustomerCredit = computed(() => {
    if (!selectedCustomer.value) return false;
    const balance = parseFloat(selectedCustomer.value.balance) || 0;
    return balance > 0.01;
});

const applyCustomerCredit = () => {
    if (!selectedCustomer.value) return;
    const credit = Math.max(0, parseFloat(selectedCustomer.value.balance) || 0);
    if (credit <= 0) return;
    // Set paid amount up to total or credit balance
    form.paid_amount = round2(Math.min(total.value, credit));
    dirty.value = true;
    ElMessage.success(t('sinv_credit_applied', { amount: money(form.paid_amount) }));
};

const searchCustomers = async (query = '') => {
    customerQuery.value = query;
    const token = ++customerSearchToken;
    customersLoading.value = true;
    try {
        const { data } = await posApi.customers({ search: query || undefined, per_page: 30 });
        if (token === customerSearchToken) {
            const list = data?.data?.customers || data?.data || (Array.isArray(data) ? data : []);
            customers.value = Array.isArray(list) ? list : [];
        }
    } catch {
        if (token === customerSearchToken) customers.value = [];
    } finally {
        if (token === customerSearchToken) customersLoading.value = false;
    }
};

// The customer's own rep is who the sale is credited to — unless the seller is
// a rep, whose sales are always their own. A rep filled in for the previous
// customer is cleared when the new one has none; one chosen by hand is kept.
const repFromCustomer = ref(null);
const applyCustomerRep = (customer) => {
    const previous = repFromCustomer.value;
    repFromCustomer.value = customer?.employee_id || null;
    if (isSalesRep.value) return;
    if (customer?.employee_id) {
        form.assigned_employee_id = customer.employee_id;
        if (!salesEmployees.value.some((e) => e.id === customer.employee_id)) {
            salesEmployees.value = [...salesEmployees.value, {
                id: customer.employee_id,
                name: customer.sales_rep?.name || `#${customer.employee_id}`,
                email: '',
                department: '',
            }];
        }
    } else if (previous && form.assigned_employee_id === previous) {
        form.assigned_employee_id = null;
    }
};

const onCustomerPicked = (id) => {
    if (id) {
        selectedCustomer.value = customers.value.find((c) => c.id === id) || null;
        applyCustomerRep(selectedCustomer.value);
        isWalkInCustomer.value = false;
    } else {
        selectedCustomer.value = null;
    }
    dirty.value = true;
};

const clearCustomerSelection = () => {
    form.customer_id = null;
    selectedCustomer.value = null;
    dirty.value = true;
};

const toggleWalkInCustomer = () => {
    isWalkInCustomer.value = !isWalkInCustomer.value;
    if (isWalkInCustomer.value) {
        form.customer_id = null;
        selectedCustomer.value = null;
        form.payment_method = 'cash';
        // By default, walk-in pays full total
        if (total.value > 0) {
            form.paid_amount = total.value;
        }
    }
    dirty.value = true;
};

// Inline Quick Customer Add
const quickCustomerOpen = ref(false);
const quickCustomerSeed = ref('');

const toggleQuickCustomer = () => {
    quickCustomerOpen.value = !quickCustomerOpen.value;
    quickCustomerSeed.value = customerQuery.value || '';
};

const openQuickCustomer = (seed = '') => {
    quickCustomerSeed.value = seed;
    quickCustomerOpen.value = true;
};

const onQuickCustomerSelect = (customer) => {
    selectedCustomer.value = customer;
    customers.value = [customer, ...customers.value.filter((c) => c.id !== customer.id)];
    form.customer_id = customer.id;
    applyCustomerRep(customer);
    isWalkInCustomer.value = false;
    quickCustomerOpen.value = false;
    dirty.value = true;
    ElMessage.success(t('client_added_and_selected', { name: customer.name }) || 'تمت إضافة العميل واختياره');
};

/* ------------------------------------------------------------------ *
 * Categories & Search (Supercharged Barcode & Catalog)
 * ------------------------------------------------------------------ */

const categories = ref([]);
const categoriesLoading = ref(false);
const selectedCategoryId = ref(null);
// The catalogue panel (focus() puts the cursor in its search box).
const quickSearchInputRef = ref(null);
const quickSearchQuery = ref('');
const quickSearchResults = ref([]);
const quickSearchLoading = ref(false);
let quickSearchTimer = null;

const categoryOptions = computed(() => {
    return categories.value.map((c) => ({
        id: c.id,
        label: c.name_ar || c.name || c.name_en,
        parent_id: c.parent_id,
    }));
});

const fetchCategories = async () => {
    categoriesLoading.value = true;
    try {
        const res = await categoriesApi.getAll({ per_page: 500 });
        categories.value = res.data?.data || (Array.isArray(res.data) ? res.data : []);
    } catch {
        categories.value = [];
    } finally {
        categoriesLoading.value = false;
    }
};

const onCategoryFilterChange = () => {
    executeQuickSearch();
};

const onQuickSearchInput = () => {
    clearTimeout(quickSearchTimer);
    quickSearchTimer = setTimeout(() => {
        quickSearchTimer = null;
        executeQuickSearch();
    }, 250);
};

const onQuickSearchClear = () => {
    quickSearchQuery.value = '';
    executeQuickSearch();
};

// Immediate Barcode Scan or Enter handler
const onQuickSearchEnter = async () => {
    const raw = quickSearchQuery.value.trim();
    if (!raw) return;

    // A scanner types the code and presses Enter before the typed search has
    // run: the results showing are for the previous text, so search now.
    const typedAhead = quickSearchTimer !== null;
    clearTimeout(quickSearchTimer);
    quickSearchTimer = null;

    if (typedAhead || quickSearchLoading.value || !quickSearchResults.value.length) {
        await executeQuickSearch();
    }

    const trimmed = raw.toLowerCase();
    // Try exact barcode / SKU match first
    const exactMatch = quickSearchResults.value.find((p) =>
        (p.barcode && String(p.barcode).trim().toLowerCase() === trimmed) ||
        (p.sku && String(p.sku).trim().toLowerCase() === trimmed)
    );

    if (exactMatch) {
        addProductDirect(exactMatch, 1);
        quickSearchQuery.value = '';
        executeQuickSearch();
        nextTick(() => quickSearchInputRef.value?.focus());
        return;
    }

    // If exactly 1 group or single item
    if (quickSearchResults.value.length === 1) {
        addProductDirect(quickSearchResults.value[0], pendingQtyOf(quickSearchResults.value[0]));
        quickSearchQuery.value = '';
        executeQuickSearch();
        nextTick(() => quickSearchInputRef.value?.focus());
        return;
    }

    // If nothing found, offer to create
    if (!quickSearchResults.value.length && raw) {
        openQuickProduct(raw);
    }
};

// Each search is numbered so a slow earlier response cannot overwrite the
// results of the text typed after it.
let quickSearchSeq = 0;

const executeQuickSearch = async () => {
    const mine = ++quickSearchSeq;
    quickSearchLoading.value = true;
    try {
        const params = { expand_variants: 1, with_warehouses: 1 };
        if (quickSearchQuery.value && quickSearchQuery.value.trim().length >= 1) {
            params.q = quickSearchQuery.value.trim();
        }
        if (selectedCategoryId.value) {
            params.category_id = selectedCategoryId.value;
        }
        const res = await posApi.productLookup(params);
        if (mine !== quickSearchSeq) return;
        const data = res.data?.data || [];
        quickSearchResults.value = (Array.isArray(data) ? data : []).map((p) => ({
            ...p,
            listing_key: optionKey(p),
        }));
        rememberVariantRows(quickSearchResults.value);
    } catch {
        if (mine === quickSearchSeq) quickSearchResults.value = [];
    } finally {
        if (mine === quickSearchSeq) quickSearchLoading.value = false;
    }
};

const quickSearchGroups = computed(() => {
    const groups = new Map();
    quickSearchResults.value.forEach((product) => {
        const id = product.id ?? product.product_id;
        if (!groups.has(id)) {
            groups.set(id, { key: String(id), product, rows: [] });
        }
        groups.get(id).rows.push(product);
    });
    return [...groups.values()].map((g) => ({
        ...g,
        hasVariants: g.rows.some((r) => r.variant_id || r.product_variant_id),
    }));
});

/* ------------------------------------------------------------------ *
 * Variants & Sibling Sizes
 * ------------------------------------------------------------------ */

const { rememberVariantRows, ensureVariantsLoaded, siblingsOf } = useVariantSiblings();
const lineSiblings = (line) => (line?.product_id && line?.product_variant_id ? siblingsOf(line.product_id) : []);
const variantInfo = (line) => lineVariantInfo(line, lineSiblings(line));

const switchLineVariant = (line, row) => {
    const pick = optionKey(row);
    if (pick === line.pick) return;

    const other = items.value.find((l) => l !== line && l.pick === pick);
    if (other) {
        if (other.allocations && line.allocations) {
            other.allocations[0].quantity += line.allocations[0].quantity;
        }
        const index = items.value.indexOf(line);
        if (index !== -1) items.value.splice(index, 1);
        ElMessage.success(t('so_qty_now', { name: other.name, count: other.allocations?.[0]?.quantity }) || `أصبحت كمية ${other.name}: ${other.allocations?.[0]?.quantity}`);
        return;
    }

    const multiplier = toNum(line.selectedUnit?.price_multiplier) || 1;
    const priceUntouched = !toNum(line.base_price) || roundUnitPrice(line.price) === roundUnitPrice(toNum(line.base_price) * multiplier);
    line.pick = pick;
    line.product_variant_id = row.variant_id || null;
    line.name = productName(row);
    line.sku = row.sku || '';
    line.variant_label = row.variant_label || '';
    line.variant = variantFieldsOf(row);
    line.image = productImageSrc(row) || line.image;
    line.stock = row.stock_quantity != null ? Number(row.stock_quantity) : undefined;
    line.base_price = parseFloat(row.price) || 0;
    if (priceUntouched) line.price = roundUnitPrice(line.base_price * multiplier);
    dirty.value = true;
};

/* ------------------------------------------------------------------ *
 * Line Item helpers & Quantities
 * ------------------------------------------------------------------ */

const pendingQty = reactive({});
const productKey = (product) => product.listing_key || optionKey(product);
const pendingQtyOf = (product) => toNum(pendingQty[productKey(product)]) || 1;

const setPendingQty = (product, raw) => {
    const key = productKey(product);
    const n = Math.floor(toNum(raw));
    if (n >= 1) pendingQty[key] = n;
};

const bumpPendingQty = (product, delta) => {
    const key = productKey(product);
    pendingQty[key] = Math.max(1, pendingQtyOf(product) + delta);
};

const normalizePendingQty = (product, input) => {
    const shown = pendingQtyOf(product);
    if (input && String(input.value) !== String(shown)) input.value = shown;
};

const lineFor = (product) => {
    if (!product) return undefined;
    const pick = optionKey(product);
    return items.value.find((l) => l.pick === pick);
};

const getItemQuantity = (product) => {
    const line = lineFor(product);
    return line ? (line.allocations?.[0]?.quantity || 0) : 0;
};

const setProductQuantity = (product, raw) => {
    const line = lineFor(product);
    if (!line || !line.allocations?.length) return;
    const n = Math.floor(toNum(raw));
    if (n >= 1) line.allocations[0].quantity = n;
    dirty.value = true;
};

const commitProductQuantity = (product, input) => {
    const line = lineFor(product);
    if (!line) return;
    const index = items.value.indexOf(line);
    if (index === -1) return;
    const n = Math.floor(toNum(input?.value));
    if (n < 1) {
        items.value.splice(index, 1);
        ElMessage.info(t('item_removed') || 'تم حذف البند');
    } else {
        line.allocations[0].quantity = n;
    }
    dirty.value = true;
};

const decrementProduct = (product) => {
    const line = lineFor(product);
    if (!line || !line.allocations?.length) return;
    const index = items.value.indexOf(line);
    if (index === -1) return;

    if (line.allocations[0].quantity > 1) {
        line.allocations[0].quantity -= 1;
    } else {
        items.value.splice(index, 1);
        ElMessage.info(t('item_removed') || 'تم حذف البند');
    }
    dirty.value = true;
};

const addProductDirect = (product, quantity = 1) => {
    const pick = optionKey(product);
    const existing = items.value.find((l) => l.pick === pick);
    if (product.variant_id) ensureVariantsLoaded(product.id);

    const targetWarehouseId = form.default_warehouse_id || (warehouses.value[0]?.id || null);

    if (existing) {
        existing.allocations[0].quantity += Math.max(1, quantity);
        ElMessage.success(t('so_qty_now', { name: existing.name, count: existing.allocations[0].quantity }) || `أصبحت كمية ${existing.name}: ${existing.allocations[0].quantity}`);
    } else {
        const defaultUnit = {
            id: null,
            name: product.unit || t('piece') || 'حبة',
            name_ar: product.unit || t('piece') || 'حبة',
            base_unit_multiplier: 1,
            price_multiplier: 1,
        };

        const line = reactive({
            pick,
            product_id: product.id ?? product.product_id,
            product_variant_id: product.variant_id ?? product.product_variant_id ?? null,
            variant_label: product.variant_label || variantLabelOf(product),
            variant: variantFieldsOf(product),
            name: productName(product),
            sku: product.sku || '',
            image: productImageSrc(product),
            price: parseFloat(product.price) || 0,
            base_price: parseFloat(product.price) || 0,
            stock: product.stock_quantity != null ? Number(product.stock_quantity) : undefined,
            warehouse_stock: Array.isArray(product.warehouse_stock) ? product.warehouse_stock : null,
            selectedUnit: defaultUnit,
            units: [defaultUnit],
            notes: '',
            _showNote: false,
            allocations: [
                {
                    warehouse_id: targetWarehouseId,
                    quantity: Math.max(1, quantity),
                },
            ],
            sources: [],
            loadingStock: false,
        });

        items.value.unshift(line);
        loadUnits(line);
        ElMessage.success(t('so_added', { name: line.name }) || `تمت إضافة ${line.name}`);
    }

    pendingQty[productKey(product)] = 1;
    dirty.value = true;
};

const duplicateLine = (index) => {
    const original = items.value[index];
    if (!original) return;
    const copy = reactive({
        ...original,
        pick: `${original.pick}-copy-${Date.now()}`,
        allocations: original.allocations.map((a) => ({ ...a })),
    });
    items.value.splice(index + 1, 0, copy);
    dirty.value = true;
    ElMessage.success(t('sinv_line_duplicated'));
};

const removeItem = (index) => {
    const [removed] = items.value.splice(index, 1);
    if (!removed) return;
    dirty.value = true;
    lastRemoved.value = { item: removed, index };
    clearTimeout(undoTimer);
    undoTimer = setTimeout(() => { lastRemoved.value = null; }, 8000);
    ElMessage({
        message: `${t('sinv_line_removed') || 'تم حذف البند'}: ${removed.name || ''}`,
        type: 'info',
        duration: 2500,
    });
};

// The quantity typed into a line's stepper: only a whole number of at least
// one lands while typing; leaving the box puts back the last good one.
const onLineQtyInput = (item, e) => {
    const n = Math.floor(Number(e.target.value));
    if (item.allocations?.[0] && Number.isFinite(n) && n >= 1) {
        item.allocations[0].quantity = n;
        dirty.value = true;
    }
};

const onLineQtyBlur = (item, e) => {
    if (!item.allocations?.[0]) return;
    e.target.value = item.allocations[0].quantity;
};

const decrementQty = (item, index) => {
    if (!item.allocations || !item.allocations[0]) return;
    if (item.allocations[0].quantity <= 1) {
        removeItem(index);
        return;
    }
    item.allocations[0].quantity -= 1;
    dirty.value = true;
};

const incrementQty = (item) => {
    if (!item.allocations || !item.allocations[0]) return;
    item.allocations[0].quantity = (Number(item.allocations[0].quantity) || 0) + 1;
    dirty.value = true;
};

const clearAllLines = () => {
    if (!items.value.length) return;
    const count = items.value.length;
    items.value = [];
    dirty.value = true;
    lastRemoved.value = null;
    clearTimeout(undoTimer);
    ElMessage({
        message: t('sinv_all_lines_cleared') || `تم تفريغ كافة البنود (${count} أصناف)`,
        type: 'info',
        duration: 2500,
    });
};

const undoRemove = () => {
    if (!lastRemoved.value) return;
    const { item, index } = lastRemoved.value;
    items.value.splice(Math.min(index, items.value.length), 0, item);
    lastRemoved.value = null;
    clearTimeout(undoTimer);
    dirty.value = true;
};

const onLineCommand = (cmd, item, index) => {
    if (cmd === 'note') {
        item._showNote = !item._showNote;
    } else if (cmd === 'duplicate') {
        duplicateLine(index);
    } else if (cmd === 'free') {
        item.price = 0;
        dirty.value = true;
    }
};

// The product's units for the line's unit picker. A new line moves to the
// product's default unit; a saved or restored line keeps the unit it has
// (keepSelection), taking that unit's multipliers from the list.
const loadUnits = async (line, { keepSelection = false } = {}) => {
    try {
        const { data } = await api.get(`/admin/products/${line.product_id}/units`);
        const rows = data?.data || [];
        if (!rows.length) return;
        const units = rows.map((u) => ({
            id: u.id,
            name: u.name,
            name_ar: u.name_ar || u.name,
            base_unit_multiplier: parseFloat(u.base_unit_multiplier) || 1,
            price_multiplier: parseFloat(u.price_multiplier) || 1,
            is_default: !!u.is_default,
        }));
        if (keepSelection) {
            const same = units.find((u) => u.id === line.selectedUnit?.id);
            if (same) {
                // A saved line's price is per its unit; the base it is
                // compared with is per piece.
                if (line.base_from_saved && same.price_multiplier) line.base_price = toNum(line.base_price) / same.price_multiplier;
                line.selectedUnit = same;
            }
        }
        line.units = [line.selectedUnit, ...units.filter((u) => u.id !== line.selectedUnit?.id)];
        if (keepSelection) return;
        const defaultU = units.find((u) => u.is_default);
        if (defaultU) {
            line.selectedUnit = defaultU;
            if (line.base_price) line.price = roundUnitPrice(line.base_price * defaultU.price_multiplier);
        }
    } catch {
        /* ignore */
    }
};

const onUnitChange = (line, unit) => {
    line.selectedUnit = unit;
    if (line.base_price && unit?.price_multiplier) {
        line.price = roundUnitPrice(line.base_price * unit.price_multiplier);
    }
    dirty.value = true;
};

const onDefaultWarehouseChange = (whId) => {
    if (!whId) return;
    items.value.forEach((item) => {
        if (item.allocations && item.allocations.length) {
            item.allocations[0].warehouse_id = whId;
        }
    });
    dirty.value = true;
    if (items.value.length) {
        ElMessage.info(t('lines_moved_to_warehouse', { name: warehouseName(whId) }) || `تم تحويل مصدر كل البنود إلى: ${warehouseName(whId)}`);
    }
};

/* ------------------------------------------------------------------ *
 * Naming & Images
 * ------------------------------------------------------------------ */

const productName = (p) => {
    if (!p) return '';
    return p.name_ar || p.name || p.name_en || t('unknown_product') || 'منتج غير محدد';
};

const productImageSrc = (p) => {
    if (!p) return '';
    const img = p.image || p.image_main || p.images?.[0]?.url || p.images?.[0]?.path;
    return img ? resolveImageUrl(img) : '';
};

/* ------------------------------------------------------------------ *
 * Quick Add Product Modal (F4)
 * ------------------------------------------------------------------ */

const quickProductVisible = ref(false);
const quickProductSeed = ref('');

const openQuickProduct = (seed = '') => {
    quickProductSeed.value = String(seed || '').trim();
    quickProductVisible.value = true;
};

const onQuickProductCreated = (product, quantity = 1) => {
    addProductDirect(product, Math.max(1, Math.floor(Number(quantity)) || 1));
    quickProductVisible.value = false;
};

/* ------------------------------------------------------------------ *
 * Totals & Calculations
 * ------------------------------------------------------------------ */

const lineTotal = (item) => {
    const qty = item.allocations?.[0]?.quantity || 0;
    return round2(toNum(item.price) * toNum(qty));
};

// Row helpers for the item design: unit factor, piece count, list-price tracking.
const unitMult = (item) => toNum(item.selectedUnit?.base_unit_multiplier) || 1;
const linePieces = (item) => toNum(item.allocations?.[0]?.quantity) * unitMult(item);
const listPrice = (item) => roundUnitPrice(toNum(item.base_price) * (toNum(item.selectedUnit?.price_multiplier) || 1));
const priceChanged = (item) => toNum(item.base_price) > 0 && roundUnitPrice(toNum(item.price)) !== listPrice(item);
const restoreListPrice = (item) => {
    item.price = listPrice(item);
    dirty.value = true;
};

const totalPieces = computed(() => {
    return items.value.reduce((sum, item) => {
        const qty = item.allocations?.[0]?.quantity || 0;
        const mult = item.selectedUnit?.base_unit_multiplier || 1;
        return sum + (toNum(qty) * toNum(mult));
    }, 0);
});

const subtotal = computed(() => {
    return round2(items.value.reduce((sum, item) => sum + lineTotal(item), 0));
});

const totalExpenses = computed(() => {
    return round2(form.expenses.reduce((sum, exp) => sum + toNum(exp.amount), 0));
});

const discountAmount = computed(() => {
    if (form.discount_type === 'amount') {
        return round2(Math.min(Math.max(0, toNum(form.discount_amount)), Math.max(0, subtotal.value)));
    }
    return round2(Math.max(0, subtotal.value) * Math.min(100, Math.max(0, toNum(form.discount_percent))) / 100);
});

const effectiveDiscountRate = computed(() =>
    subtotal.value > 0 ? Math.round((discountAmount.value / subtotal.value) * 10000) / 100 : 0
);

const discountError = computed(() => {
    if (form.discount_type === 'amount') {
        const amount = toNum(form.discount_amount);
        if (amount < 0) return t('sales.discount_negative') || 'قيمة الخصم لا يمكن أن تكون سالبة';
        if (amount > 0 && amount - subtotal.value > 0.005) {
            return t('sales.discount_amount_exceeds_subtotal', {
                discount: money(amount),
                subtotal: money(subtotal.value),
            }) || `قيمة الخصم (${money(amount)}) تتجاوز المجموع الفرعي (${money(subtotal.value)})`;
        }
        return '';
    }
    const val = toNum(form.discount_percent);
    if (val < 0 || val > 100) return t('sales.discount_percent_out_of_range') || 'نسبة الخصم يجب أن تكون بين 0 و 100%';
    return '';
});

const switchDiscountType = (type) => {
    if (type === form.discount_type) return;
    if (type === 'amount') {
        form.discount_amount = discountAmount.value;
    } else {
        form.discount_percent = effectiveDiscountRate.value;
    }
    form.discount_type = type;
};

const setDiscountPreset = (percent) => {
    form.discount_type = 'percent';
    form.discount_percent = percent;
    dirty.value = true;
};

const taxAmount = computed(() =>
    round2(Math.max(0, subtotal.value - discountAmount.value) * rate(form.tax_percent) / 100)
);

const total = computed(() =>
    Math.max(0, subtotal.value - discountAmount.value + taxAmount.value + totalExpenses.value)
);

const remaining = computed(() => round2(total.value - toNum(form.paid_amount)));

const remainingTone = computed(() => {
    if (Math.abs(remaining.value) < 0.005) return 'is-settled';
    return remaining.value > 0 ? 'is-owing' : 'is-credit';
});

// Cash Tendered & Change Calculator
const cashTendered = ref(null);
const cashChangeAmount = computed(() => {
    if (form.payment_method !== 'cash') return 0;
    const given = toNum(cashTendered.value);
    const paid = toNum(form.paid_amount);
    return given > paid ? round2(given - paid) : 0;
});

// Auto-align paid amount with total for walk-in cash sales if desired
watch(
    () => total.value,
    (newTotal) => {
        if (isWalkInCustomer.value && (form.paid_amount === 0 || form.paid_amount == null)) {
            form.paid_amount = newTotal;
        }
    }
);

/* ------------------------------------------------------------------ *
 * Validation & Issues Checklist
 * ------------------------------------------------------------------ */

const isLineShort = (item) => {
    if (item.stock == null) return false;
    const qty = item.allocations?.[0]?.quantity || 0;
    const mult = item.selectedUnit?.base_unit_multiplier || 1;
    return (toNum(qty) * toNum(mult)) > Number(item.stock);
};

const shortLines = computed(() => items.value.filter(isLineShort));

// A catalogue row asked for more than its stock.
const isRowOverStock = (row, qty) => row?.stock_quantity != null && toNum(qty) > Number(row.stock_quantity);

// The line's state against the document: over stock (or its warehouse's
// stock), given away, or fine.
const lineTone = (item) => {
    if (isLineShort(item) || lineWarehouseShort(item)) return 'warn';
    if (Number(item.price) === 0) return 'info';
    return 'ok';
};

const issues = computed(() => {
    const list = [];
    if (!items.value.length) {
        list.push({ key: 'items', target: 'items', text: t('qt_need_lines') || 'يرجى إضافة بند واحد على الأقل للفاتورة' });
    }
    if (discountError.value) {
        list.push({ key: 'discount', target: 'discount', text: discountError.value });
    }
    return list;
});

const blockerText = computed(() => issues.value[0]?.text || '');

const canSubmit = computed(() => issues.value.length === 0 && !submitting.value);

const isFlagged = (target) => issues.value.some((issue) => issue.target === target);

// The checklist shows once there is something on the invoice to check.
const showChecklist = computed(() => items.value.length > 0 || !!form.customer_id || isWalkInCustomer.value);

const goToIssue = (issue) => {
    let el = null;
    if (issue.target === 'items') {
        focusCatalog();
        linesRef.value?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    } else if (issue.target === 'discount') {
        el = discountInput.value?.$el || totalsRef.value;
    } else if (issue.target === 'customer') {
        el = customerSelectRef.value?.$el || headRef.value;
    } else if (issue.target === 'settlement') {
        el = payRef.value;
    }
    el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    const focusable = el?.querySelector('input, select, textarea') || el;
    focusable?.focus?.();
};

/* ------------------------------------------------------------------ *
 * Status Transitions & Labels
 * ------------------------------------------------------------------ */

const statusTransitions = {
    pending: ['confirmed', 'cancelled'],
    confirmed: ['processing', 'cancelled'],
    processing: ['shipped', 'cancelled'],
    shipped: ['delivered', 'cancelled'],
    delivered: [],
    cancelled: [],
};

const statusLabels = {
    pending: t('sales_status_pending') || 'مسودة',
    confirmed: t('sales_status_confirmed') || 'مؤكدة',
    processing: t('sales_status_processing') || 'قيد التجهيز',
    shipped: t('sales_status_shipped') || 'مشحونة',
    delivered: t('sales_status_delivered') || 'تم التسليم',
    cancelled: t('sales_status_cancelled') || 'ملغاة',
};

const availableStatuses = computed(() => [form.status, ...(statusTransitions[form.status] || [])]);

/* ------------------------------------------------------------------ *
 * Draft Auto-saving & Restoring
 * ------------------------------------------------------------------ */

const saveDraft = () => {
    if (isEdit.value) return;
    if (!items.value.length && !form.customer_id && !isWalkInCustomer.value) {
        clearDraft();
        return;
    }
    try {
        const draftData = {
            saved_at: new Date().toISOString(),
            isWalkInCustomer: isWalkInCustomer.value,
            form: {
                ...form,
            },
            items: items.value.map((line) => ({
                ...line,
                units: [line.selectedUnit].filter(Boolean),
            })),
            selectedCustomer: selectedCustomer.value,
        };
        localStorage.setItem(DRAFT_KEY, JSON.stringify(draftData));
        draftSavedAt.value = new Date().toLocaleTimeString(
            locale.value === 'ar' ? 'ar' : 'en-GB',
            { hour: '2-digit', minute: '2-digit' }
        );
    } catch {
        /* storage unavailable */
    }
};

const clearDraft = () => {
    try { localStorage.removeItem(DRAFT_KEY); } catch { /* ignore */ }
    draftSavedAt.value = '';
};

const offerDraft = async () => {
    let draft = null;
    try { draft = JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null'); } catch { draft = null; }
    if (!draft?.items?.length) return;

    try {
        await ElMessageBox.confirm(
            t('sinv_draft_message', { count: draft.items.length }),
            t('sinv_draft_title'),
            {
                type: 'info',
                confirmButtonText: t('sinv_draft_continue'),
                cancelButtonText: t('sinv_draft_discard'),
                distinguishCancelAndClose: true,
            }
        );
    } catch {
        clearDraft();
        return;
    }

    const saved = draft.form || {};
    if (draft.saved_at) {
        const when = new Date(draft.saved_at);
        if (!Number.isNaN(when.getTime())) {
            draftSavedAt.value = when.toLocaleTimeString(
                locale.value === 'ar' ? 'ar' : 'en-GB',
                { hour: '2-digit', minute: '2-digit' }
            );
        }
    }

    isWalkInCustomer.value = !!draft.isWalkInCustomer;

    Object.assign(form, {
        customer_id: saved.customer_id || null,
        assigned_employee_id: saved.assigned_employee_id || null,
        default_warehouse_id: saved.default_warehouse_id || null,
        payment_method: saved.payment_method || 'cash',
        due_date: saved.due_date || inDays(0),
        discount_type: saved.discount_type || 'percent',
        discount_percent: toNum(saved.discount_percent),
        discount_amount: toNum(saved.discount_amount),
        tax_percent: toNum(saved.tax_percent),
        paid_amount: toNum(saved.paid_amount),
        notes: saved.notes || '',
        status: saved.status || 'pending',
        expenses: saved.expenses || [],
    });

    items.value = (draft.items || []).map((line) => {
        const unit = line.selectedUnit || {
            id: null,
            name: t('piece') || 'حبة',
            name_ar: t('piece') || 'حبة',
            base_unit_multiplier: 1,
            price_multiplier: 1,
        };
        const l = reactive({
            ...line,
            selectedUnit: unit,
            units: [unit],
            notes: line.notes || '',
            _showNote: !!line.notes,
        });
        loadUnits(l, { keepSelection: true });
        return l;
    });

    if (draft.selectedCustomer) {
        selectedCustomer.value = draft.selectedCustomer;
        customers.value = [draft.selectedCustomer];
    }
};

watch(
    () => [
        form.customer_id,
        items.value,
        form.discount_type,
        form.discount_percent,
        form.discount_amount,
        form.tax_percent,
        form.paid_amount,
        form.default_warehouse_id,
        form.notes,
        form.due_date,
        form.payment_method,
        isWalkInCustomer.value,
    ],
    () => {
        dirty.value = true;
        clearTimeout(draftTimer);
        draftTimer = setTimeout(saveDraft, 600);
    },
    { deep: true }
);

/* ------------------------------------------------------------------ *
 * Load Existing Invoice
 * ------------------------------------------------------------------ */

const loadInvoice = async () => {
    loading.value = true;
    try {
        // GET /invoices/{id} (InvoiceResource): the customer as flat fields,
        // the warehouse and rep as { id, name }.
        const res = await invoicesApi.getById(invoiceId);
        const invoice = res.data?.data || res.data;
        loadedInvoice.value = invoice;
        if (!invoice) return;

        const customer = invoice.customer || (invoice.customer_id
            ? {
                id: invoice.customer_id,
                name: invoice.customer_name || `#${invoice.customer_id}`,
                phone: invoice.customer_phone || '',
                company: invoice.customer_company || '',
            }
            : null);
        form.customer_id = invoice.customer_id || null;
        if (customer) {
            selectedCustomer.value = customer;
            customers.value = [customer];
            isWalkInCustomer.value = false;
            // The balance and the customer's rep are not on the invoice.
            posApi.customerShow(customer.id)
                .then(({ data }) => {
                    const full = data?.data || data;
                    if (full?.id === form.customer_id) {
                        selectedCustomer.value = { ...customer, ...full };
                        repFromCustomer.value = full.employee_id || null;
                    }
                })
                .catch(() => {});
        } else {
            isWalkInCustomer.value = true;
        }
        const rep = invoice.assigned_employee || null;
        form.assigned_employee_id = invoice.assigned_employee_id ?? rep?.id ?? null;
        if (rep?.id && !salesEmployees.value.some((e) => e.id === rep.id)) {
            salesEmployees.value = [...salesEmployees.value, { id: rep.id, name: rep.name || `#${rep.id}`, email: '', department: '' }];
        }
        form.default_warehouse_id = invoice.warehouse_id ?? invoice.warehouse?.id ?? null;
        form.payment_method = invoice.payment_method || 'cash';
        form.due_date = invoice.due_date ? String(invoice.due_date).slice(0, 10) : inDays(0);
        form.paid_amount = parseFloat(invoice.paid_amount) || 0;
        form.status = invoice.status || 'pending';
        form.notes = invoice.notes || '';

        const savedSubtotal = parseFloat(invoice.subtotal) || 0;
        const savedDiscount = parseFloat(invoice.discount) || 0;
        const savedTax = parseFloat(invoice.tax) || 0;

        if (invoice.discount_percent !== null && invoice.discount_percent !== undefined) {
            form.discount_type = 'percent';
            form.discount_percent = invoice.discount_percent;
            form.discount_amount = savedDiscount;
        } else {
            form.discount_type = savedDiscount > 0 ? 'amount' : 'percent';
            form.discount_amount = savedDiscount;
            form.discount_percent = savedSubtotal > 0 ? Math.round((savedDiscount / savedSubtotal) * 10000) / 100 : 0;
        }

        form.tax_percent = invoice.tax_percent ?? (savedSubtotal > 0 ? Math.round((savedTax / Math.max(1, savedSubtotal - savedDiscount)) * 10000) / 100 : 0);

        form.expenses = (invoice.expenses || []).map((exp) => ({
            id: exp.id || null,
            category: exp.category || 'shipping',
            description: exp.description || '',
            amount: parseFloat(exp.amount) || 0,
            status: exp.status || 'paid',
            notes: exp.notes || '',
        }));

        items.value = (invoice.items || []).map((item) => {
            const unit = {
                id: item.product_unit_id ?? null,
                name: item.unit_name || item.product?.unit || t('piece') || 'حبة',
                name_ar: item.unit_name || item.product?.unit || t('piece') || 'حبة',
                base_unit_multiplier: toNum(item.unit_multiplier) || 1,
                price_multiplier: 1,
            };
            const line = reactive({
                pick: pickKey(item.product_id, item.product_variant_id),
                product_id: item.product_id,
                product_variant_id: item.product_variant_id || null,
                variant_label: item.variant?.label || variantLabelOf(item.variant),
                variant: item.variant ? variantFieldsOf(item.variant) : null,
                name: (item.product_variant_id && item.product?.name_ar) || item.product_name || item.product?.name_ar || t('unknown_product') || 'منتج',
                sku: item.variant?.sku || item.product?.sku || '',
                image: item.product?.image_main || null,
                price: parseFloat(item.unit_price) || 0,
                base_price: parseFloat(item.unit_price) || 0,
                base_from_saved: true,
                // Not sent with a saved invoice; unknown rather than zero, so
                // its lines are not all flagged as over stock.
                stock: item.product?.stock_quantity != null ? Number(item.product.stock_quantity) : undefined,
                selectedUnit: unit,
                units: [unit],
                notes: item.notes || '',
                _showNote: !!item.notes,
                allocations: [
                    {
                        warehouse_id: item.warehouse_id || form.default_warehouse_id || null,
                        quantity: item.quantity || 1,
                    },
                ],
            });
            loadUnits(line, { keepSelection: true });
            return line;
        });

        await nextTick();
        dirty.value = false;
    } catch {
        ElMessage.error(t('failed_to_load_invoice') || 'فشل تحميل بيانات الفاتورة');
    } finally {
        loading.value = false;
    }
};

/* ------------------------------------------------------------------ *
 * Submit with multiple modes (Save, Save & Print, Save & New)
 * ------------------------------------------------------------------ */

const resetFormToNew = () => {
    Object.assign(form, blankForm());
    items.value = [];
    selectedCustomer.value = null;
    isWalkInCustomer.value = false;
    formErrors.value = [];
    cashTendered.value = null;
    resetDisclosure();
    clearDraft();
    dirty.value = false;
    if (warehouses.value.length) {
        form.default_warehouse_id = warehouses.value[0].id;
    }
    nextTick(() => focusCatalog());
};

const submitInvoice = async (afterAction = 'close') => {
    if (!canSubmit.value) {
        if (issues.value.length) {
            goToIssue(issues.value[0]);
        }
        return;
    }

    if (!form.customer_id && !isWalkInCustomer.value && remaining.value > 0.005) {
        formErrors.value = [t('sinv_need_customer_or_walk_in')];
        (customerSelectRef.value?.$el || headRef.value)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }

    formErrors.value = [];
    submitting.value = true;

    try {
        const payload = {
            customer_id: isWalkInCustomer.value ? null : form.customer_id,
            warehouse_id: form.default_warehouse_id || null,
            assigned_employee_id: form.assigned_employee_id || null,
            payment_method: form.payment_method || 'cash',
            due_date: form.due_date || null,
            discount_percent: form.discount_type === 'percent' ? rate(form.discount_percent) : null,
            tax_percent: rate(form.tax_percent),
            discount: discountAmount.value,
            tax: taxAmount.value,
            paid_amount: form.paid_amount || 0,
            notes: form.notes || null,
            status: form.status,
            items: items.value.map((item) => ({
                product_id: item.product_id,
                product_variant_id: item.product_variant_id || null,
                quantity: item.allocations?.[0]?.quantity || 1,
                unit_price: roundUnitPrice(item.price),
                warehouse_id: item.allocations?.[0]?.warehouse_id || form.default_warehouse_id || null,
                product_unit_id: item.selectedUnit?.id || null,
                notes: item.notes || null,
            })),
            expenses: (form.expenses || [])
                .filter((exp) => exp.description && Number(exp.amount) > 0)
                .map((exp) => ({
                    category: exp.category || 'other',
                    description: exp.description,
                    amount: Number(exp.amount),
                    status: exp.status || 'paid',
                    notes: exp.notes || null,
                })),
        };

        if (isEdit.value) {
            await invoicesStore.updateInvoice(invoiceId, payload);
            ElMessage.success(t('invoice_updated_successfully') || 'تم تحديث الفاتورة بنجاح');
            router.push('/admin/sales/invoices');
        } else {
            const created = await invoicesStore.createInvoice(payload);
            const settlement = created?.settlement;
            const parts = [t('invoice_created_successfully') || `تم إصدار الفاتورة رقم ${created?.invoice_number || ''} بنجاح`];
            if (settlement?.payment_number) {
                parts.push(`${t('payment') || 'سند قبض'} ${settlement.payment_number}`);
            }
            ElMessage.success({ message: parts.join(' • '), duration: 4000 });
            clearDraft();

            if (afterAction === 'print') {
                // Navigate to invoice list with print preview trigger
                router.push({ path: '/admin/sales/invoices', query: { print: created.id } });
            } else if (afterAction === 'new') {
                resetFormToNew();
                ElMessage.info(t('sinv_ready_next'));
            } else {
                router.push('/admin/sales/invoices');
            }
        }
    } catch (err) {
        if (err.response?.status === 422) {
            const respData = err.response?.data || {};
            const errors = respData.errors ?? {};
            const shortages = respData.data?.shortages || [];
            const list = Object.values(errors).flat();

            if (shortages.length) {
                shortages.forEach((s) => {
                    list.push(t('sinv_stock_short_line', { product: s.product_name, sku: s.sku || '—', warehouse: s.warehouse_name }));
                });
            }

            if (!list.length && respData.message) {
                list.push(respData.message);
            }

            formErrors.value = list;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
            formErrors.value = [err.response?.data?.message || t('sinv_save_failed')];
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    } finally {
        submitting.value = false;
    }
};

/* ------------------------------------------------------------------ *
 * Lifecycle & Keyboard Shortcuts
 * ------------------------------------------------------------------ */

const confirmClearInvoice = async () => {
    try {
        await ElMessageBox.confirm(
            t('sinv_confirm_clear'),
            t('confirm') || 'تأكيد',
            { type: 'warning' }
        );
        items.value = [];
        lastRemoved.value = null;
        clearDraft();
        dirty.value = false;
        ElMessage.info(t('sinv_cleared'));
    } catch {
        /* cancel */
    }
};

const goBack = async () => {
    if (dirty.value && items.value.length) {
        try {
            await ElMessageBox.confirm(
                t('sinv_confirm_leave'),
                t('confirm') || 'تأكيد',
                { type: 'warning' }
            );
        } catch {
            return;
        }
    }
    router.push('/admin/sales/invoices');
};

const handleGlobalKeydown = (e) => {
    // The new-product dialog owns the keyboard while it is open.
    if (quickProductVisible.value) return;
    if (e.key === 'F2') {
        e.preventDefault();
        focusCatalog();
    } else if (e.key === 'F4') {
        e.preventDefault();
        openQuickProduct(quickSearchQuery.value);
    } else if (e.key === 'F9' || (e.ctrlKey && e.key === 'Enter')) {
        e.preventDefault();
        if (canSubmit.value) {
            submitInvoice('close');
        } else if (issues.value.length) {
            goToIssue(issues.value[0]);
        }
    }
};

onMounted(async () => {
    window.addEventListener('keydown', handleGlobalKeydown);

    await Promise.allSettled([
        fetchWarehouses(),
        fetchEmployees(),
    ]);

    fetchCategories();
    // The catalogue beside the invoice starts with products in it.
    executeQuickSearch();

    if (isEdit.value) {
        await loadInvoice();
    } else {
        await offerDraft();
        const cid = route.query.customer_id ? Number(route.query.customer_id) : null;
        if (cid) {
            try {
                const { data } = await posApi.customerShow(cid);
                const cust = data?.data || data;
                if (cust) {
                    customers.value = [cust];
                    form.customer_id = cust.id;
                    selectedCustomer.value = cust;
                    isWalkInCustomer.value = false;
                    applyCustomerRep(cust);
                }
            } catch {
                /* ignore */
            }
        }
        draftTracking = true;
        nextTick(() => focusCatalog());
    }
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleGlobalKeydown);
    clearTimeout(quickSearchTimer);
    clearTimeout(draftTimer);
    clearTimeout(undoTimer);
});

onBeforeRouteLeave((to, from, next) => {
    if (!isEdit.value && dirty.value && items.value.length) {
        saveDraft();
    }
    next();
});
</script>
