<template>
    <div class="pf-page order-form-page">
        <!-- Header: what this is, and the way out -->
        <header class="pf-page-head">
            <div class="pf-title">
                <span class="pf-title-icon is-order"><i class="fas fa-file-invoice"></i></span>
                <div>
                    <h2>
                        {{ isEdit ? t('edit_sales_order') : t('create_sales_order') }}
                        <span v-if="isEdit && loadedOrder" class="pf-title-tag" dir="ltr">{{ loadedOrder.order_number }}</span>
                        <span v-if="isEdit && loadedOrder" class="pf-title-tag">{{ statusLabel(loadedOrder.status) }}</span>
                    </h2>
                    <p>{{ t('create_order_subtitle') }}</p>
                </div>
            </div>
            <div class="pf-page-actions">
                <span v-if="draftSavedAt" class="pf-hint">
                    <i class="fas fa-floppy-disk"></i> {{ t('so_kept_on_this_device', { time: draftSavedAt }) }}
                </span>
                <el-button v-if="!isEdit && form.items.length" text type="danger" @click="clearOrder">
                    <i class="fas fa-trash-can"></i>&nbsp;{{ t('clear_the_form') }}
                </el-button>
                <el-button @click="goBack">
                    <i class="fas" :class="backArrow"></i>&nbsp;{{ t('back_to_sales_orders') }}
                </el-button>
            </div>
        </header>

        <section v-if="loading" class="pf-card pf-skeleton">
            <el-skeleton :rows="8" animated />
        </section>

        <!-- Locked order: its lines cannot be rewritten from here -->
        <section v-else-if="isLocked" class="pf-card sf-locked">
            <i class="fas fa-lock sf-locked-icon"></i>
            <h2>{{ t('so_locked_title') }}</h2>
            <p>{{ lockedReason }}</p>
            <dl>
                <div><dt>{{ t('status') }}</dt><dd>{{ statusLabel(loadedOrder?.status) }}</dd></div>
                <div><dt>{{ t('customer') }}</dt><dd>{{ loadedOrder?.customer?.name || '—' }}</dd></div>
                <div><dt>{{ t('grand_total') }}</dt><dd>{{ formatCurrency(loadedOrder?.total) }}</dd></div>
            </dl>
            <el-button type="primary" @click="openOrder(orderId)">
                <i class="fas fa-eye"></i>&nbsp;{{ t('open_order_to_follow_stages') }}
            </el-button>
            <p class="pf-hint" style="margin-top: 1rem">{{ t('so_rep_locked_note') }}</p>
        </section>

        <div v-else class="pf-shell">
            <div ref="formRoot" class="pf-doc">
                <!-- What the server refused -->
                <el-alert
                    v-if="serverErrors.length"
                    type="error"
                    show-icon
                    :title="t('please_fix_these_errors')"
                    @close="serverErrors = []"
                >
                    <ul class="pf-alerts">
                        <li v-for="(err, idx) in serverErrors" :key="idx">{{ err }}</li>
                    </ul>
                </el-alert>

                <!-- Past confirmation only the lines and their pricing change. -->
                <el-alert
                    v-if="amending"
                    type="warning"
                    :closable="false"
                    show-icon
                    :title="t('so_amend_title', { status: statusLabel(loadedOrder?.status) })"
                    :description="t(['shipped', 'delivered'].includes(loadedOrder?.status) ? 'so_amend_notice_shipped' : 'so_amend_notice')"
                />
                <!-- Past the pending stage an edit is saved as a new version; the current one is kept. -->
                <section v-if="versioning" class="pf-card pf-card-pad">
                    <el-alert
                        type="info"
                        :closable="false"
                        show-icon
                        :title="t('so_new_revision_title', { next: currentRevision + 1 })"
                        :description="t('so_new_revision_notice', { current: currentRevision })"
                    />
                    <span class="pf-label" style="margin: 0.75rem 0 0.4rem">{{ t('so_revision_note') }}</span>
                    <el-input
                        v-model="revisionNote"
                        type="textarea"
                        :rows="2"
                        maxlength="500"
                        show-word-limit
                        :placeholder="t('so_revision_note_ph')"
                    />
                </section>

                <!-- Who, from where and when -->
                <section ref="headRef" class="pf-card pf-card-pad">
                    <div class="pf-fields">
                        <div class="pf-field span-8" :class="{ 'is-flagged': isFlagged('customer_id') || !!errors.customer_id }">
                            <span class="pf-label">{{ t('customer') }} <span class="pf-req">*</span></span>
                            <div class="sf-customer-row">
                                <el-select
                                    ref="customerSelect"
                                    v-model="form.customer_id"
                                    filterable
                                    remote
                                    :clearable="!amending"
                                    :disabled="amending"
                                    :remote-method="searchCustomers"
                                    :loading="customersLoading"
                                    :placeholder="t('qt_find_customer')"
                                    @focus="customerOptions.length <= 1 && searchCustomers('')"
                                    @visible-change="(vis) => vis && customerOptions.length <= 1 && searchCustomers('')"
                                    @change="onCustomerChange"
                                >
                                    <el-option v-for="c in customerOptions" :key="c.id" :label="c.name" :value="c.id">
                                        <div class="sf-option-main">
                                            <span class="sf-option-name">{{ c.name }}</span>
                                            <span class="sf-option-meta" dir="ltr">{{ c.phone || c.company || '' }}</span>
                                        </div>
                                    </el-option>

                                    <!-- Nothing matched: offer to create the typed client in place -->
                                    <template #empty>
                                        <div class="sf-select-empty">
                                            <template v-if="customersLoading">
                                                <i class="fas fa-spinner fa-spin"></i>&nbsp;{{ t('loading') }}
                                            </template>
                                            <template v-else>
                                                <p>{{ customerQuery ? t('qt_no_customer_match', { query: customerQuery }) : t('qt_no_customers_yet') }}</p>
                                                <button v-if="canAddCustomer" type="button" class="sf-select-empty-add" @mousedown.prevent @click="openQuickCustomer(customerQuery)">
                                                    <i class="fas fa-user-plus"></i>
                                                    {{ customerQuery ? t('qt_add_as_customer', { query: customerQuery }) : t('qt_new_customer') }}
                                                </button>
                                            </template>
                                        </div>
                                    </template>
                                </el-select>
                                <el-button
                                    v-if="canAddCustomer && !amending"
                                    plain
                                    :title="t('qt_new_customer')"
                                    :aria-expanded="quickCustomer.open"
                                    @click="toggleQuickCustomer"
                                >
                                    <i class="fas" :class="quickCustomer.open ? 'fa-xmark' : 'fa-user-plus'"></i>
                                </el-button>
                            </div>
                            <small v-if="fieldError('customer_id')" class="pf-field-error">{{ fieldError('customer_id') }}</small>

                            <small v-if="selectedCustomer && !quickCustomer.open" class="sf-customer-facts">
                                <span v-if="selectedCustomer.phone"><i class="fas fa-phone"></i> <span dir="ltr">{{ selectedCustomer.phone }}</span></span>
                                <span v-if="selectedCustomer.company"><i class="fas fa-building"></i> {{ selectedCustomer.company }}</span>
                                <span v-if="Number(selectedCustomer.balance) > 0" class="is-warn">
                                    <i class="fas fa-wallet"></i> {{ t('customer_credit') }}: {{ formatCurrency(selectedCustomer.balance) }}
                                </span>
                                <span v-if="creditCheck && creditCheck.over" class="is-danger">
                                    <i class="fas fa-triangle-exclamation"></i> {{ t('credit_limit_exceeded') }} ({{ formatCurrency(creditCheck.limit) }})
                                </span>
                                <span v-if="selectedCustomer.id === justAddedCustomerId" class="is-fresh">
                                    <i class="fas fa-check-circle"></i> {{ t('qt_customer_just_added') }}
                                </span>
                            </small>

                            <transition name="el-zoom-in-top">
                                <CustomerQuickAdd
                                    v-if="quickCustomer.open"
                                    :seed="quickCustomer.seed"
                                    @select="onQuickCustomerSelect"
                                    @close="closeQuickCustomer"
                                />
                            </transition>
                        </div>

                        <div class="pf-field span-4" :class="{ 'is-flagged': isFlagged('expected_delivery') || !!errors.expected_delivery }">
                            <span class="pf-label">{{ t('expected_delivery_date') }}</span>
                            <el-date-picker
                                ref="deliveryPicker"
                                v-model="form.expected_delivery"
                                type="date"
                                value-format="YYYY-MM-DD"
                                format="YYYY-MM-DD"
                                :placeholder="t('choose_delivery_date')"
                                style="width: 100%"
                            />
                            <div class="pf-chips" role="group" :aria-label="t('expected_delivery_date')">
                                <button
                                    v-for="days in [0, 3, 7, 14, 30]"
                                    :key="days"
                                    type="button"
                                    class="pf-chip-btn"
                                    :class="{ 'is-active': form.expected_delivery === inDays(days) }"
                                    :aria-pressed="form.expected_delivery === inDays(days)"
                                    @click="form.expected_delivery = inDays(days)"
                                >
                                    {{ days === 0 ? t('today') : t('qt_days_n', { count: days }) }}
                                </button>
                            </div>
                            <small v-if="fieldError('expected_delivery')" class="pf-field-error">{{ fieldError('expected_delivery') }}</small>
                        </div>

                        <div class="pf-field span-3">
                            <span class="pf-label"><i class="fas fa-truck"></i> {{ t('so_fulfillment_type') }}</span>
                            <el-select v-model="form.fulfillment_type" :disabled="amending">
                                <el-option value="ship" :label="t('so_shipping_transfer')" />
                                <el-option value="pickup" :label="t('so_pickup_branch')" />
                                <el-option value="delivery" :label="t('so_direct_delivery')" />
                            </el-select>
                        </div>

                        <div class="pf-field span-3">
                            <span class="pf-label"><i class="fas fa-warehouse"></i> {{ t('choose_warehouse') }}</span>
                            <el-select
                                v-model="form.fulfillment_warehouse_id"
                                filterable
                                :clearable="!authStore.isSalesRep && !amending"
                                :disabled="authStore.isSalesRep || amending"
                                :placeholder="t('choose_warehouse_placeholder')"
                            >
                                <el-option v-for="wh in warehouses" :key="wh.id" :label="wh.name" :value="wh.id">
                                    <div class="sf-option-main">
                                        <span>{{ wh.name }}</span>
                                        <span v-if="wh.code" class="sf-option-meta" dir="ltr">{{ wh.code }}</span>
                                    </div>
                                </el-option>
                            </el-select>
                        </div>

                        <div class="pf-field span-3">
                            <span class="pf-label"><i class="fas fa-calendar"></i> {{ t('order_date') }}</span>
                            <el-date-picker
                                v-model="form.order_date"
                                type="date"
                                value-format="YYYY-MM-DD"
                                format="YYYY-MM-DD"
                                style="width: 100%"
                            />
                        </div>

                        <!-- Who the order is credited to: the customer's own rep by
                             default; a rep's orders are always their own. -->
                        <div class="pf-field span-3">
                            <span class="pf-label"><i class="fas fa-user-tie"></i> {{ t('sales_representative') }}</span>
                            <el-select
                                v-model="form.assigned_employee_id"
                                filterable
                                :clearable="!isSellsRole"
                                :disabled="isSellsRole"
                                :loading="employeesLoading"
                                :placeholder="t('so_rep_placeholder')"
                            >
                                <el-option v-for="e in salesEmployees" :key="e.id" :label="e.name" :value="e.id" />
                            </el-select>
                            <small v-if="repFromCustomer && form.assigned_employee_id === repFromCustomer" class="pf-hint">
                                <i class="fas fa-link"></i> {{ t('so_rep_from_customer') }}
                            </small>
                        </div>
                    </div>
                </section>

                <!-- What still stands between the form and a save -->
                <div v-if="showChecklist" class="pf-checklist" :class="{ 'is-ready': !issues.length }">
                    <span class="pf-checklist-title">
                        <i class="fas" :class="issues.length ? 'fa-list-check' : 'fa-circle-check'"></i>
                        {{ issues.length ? t('pf_before_saving') : (t('so_ready_to_save') || t('pf_ready_to_save')) }}
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
                            {{ t('items') }}
                            <span class="pf-count">{{ form.items.length }}</span>
                        </h3>
                        <div class="pf-card-tools">
                            <span v-if="overStockLines.length" class="pf-badge is-warn">
                                <i class="fas fa-triangle-exclamation"></i>
                                {{ t('so_over_stock_n', { count: overStockLines.length }) }}
                            </span>
                            <el-button v-if="form.items.length" size="small" text type="danger" @click="clearLines">
                                <i class="fas fa-eraser"></i>&nbsp;{{ t('pf_clear_lines') }}
                            </el-button>
                        </div>
                    </header>

                    <template v-if="form.items.length">
                        <div class="pf-lines-head sline-grid-so">
                            <span>#</span>
                            <span>{{ t('product') }}</span>
                            <span>{{ t('so_unit') }}</span>
                            <span>{{ t('quantity') }}</span>
                            <span>{{ t('unit_price') }}</span>
                            <span>{{ t('discount') }}</span>
                            <span>{{ t('tax') }}</span>
                            <span>{{ t('total') }}</span>
                            <span></span>
                        </div>
                        <div class="pf-lines">
                            <div
                                v-for="(line, i) in form.items"
                                :key="line.key"
                                class="sline sline-grid-so"
                                :class="`tone-${lineTone(line, i)}`"
                            >
                                <span class="sline-idx">{{ i + 1 }}</span>

                                <div class="sline-product">
                                    <EntityImage
                                        :src="line.image || productImageSrc(line)"
                                        type="product"
                                        :size="44"
                                        shape="square"
                                        class="sline-thumb"
                                    />
                                    <div class="sline-info">
                                        <div class="sline-title">
                                            <strong class="sline-name" :title="line.name">{{ line.name }}</strong>
                                        </div>
                                        <div class="sline-meta">
                                            <!-- A variant line's codes sit in its details below. -->
                                            <template v-if="!variantInfo(line)">
                                                <span v-if="line.sku" class="sline-sku" dir="ltr"><i class="fas fa-barcode"></i> {{ line.sku }}</span>
                                                <span v-if="line.variant_label">{{ line.variant_label }}</span>
                                            </template>
                                            <span v-if="line.stock !== undefined && line.stock !== null" class="sline-stock" :class="overStock(line) ? 'is-out' : 'is-ok'">
                                                <i class="fas" :class="overStock(line) ? 'fa-triangle-exclamation' : 'fa-warehouse'"></i>
                                                {{ overStock(line) ? t('so_stock_shortage') : `${t('available')}: ${line.stock}` }}
                                            </span>
                                            <button
                                                v-if="overStock(line) && canShowPurchase"
                                                type="button"
                                                class="sline-act"
                                                :title="t('so_add_to_purchase_list') || 'طلب شراء لهذا البند'"
                                                @click.stop="openPurchaseDialogFromLine(line)"
                                            >
                                                <i class="fas fa-cart-flatbed"></i> {{ t('so_add_to_purchase_list') || 'طلب شراء' }}
                                            </button>
                                        </div>
                                        <VariantDetails
                                            v-if="variantInfo(line)"
                                            :variant="variantInfo(line)"
                                            show-codes
                                            class="sline-variant-details"
                                        />
                                        <!-- The other sizes of this product, one click away: changing
                                             the size keeps the quantity and a price the seller typed. -->
                                        <div v-if="lineSiblings(line).length > 1" class="sline-sizes">
                                            <span class="sline-sizes-label"><i class="fas fa-tag"></i> {{ t('po_switch_size') }}</span>
                                            <button
                                                v-for="v in lineSiblings(line)"
                                                :key="v.listing_key || optionKey(v)"
                                                type="button"
                                                class="sline-size"
                                                :class="{
                                                    'is-active': (v.variant_id || null) === (line.product_variant_id || null),
                                                    'is-out': v.stock_quantity != null && Number(v.stock_quantity) <= 0,
                                                }"
                                                :title="[v.sku, formatCurrency(listPrice(v))].filter(Boolean).join(' · ')"
                                                @click.stop="switchLineVariant(line, v)"
                                            >
                                                <span dir="ltr">{{ v.variant_label || v.sku }}</span>
                                                <small v-if="v.stock_quantity != null">{{ v.stock_quantity }}</small>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="sline-fields">
                                    <div class="sline-field">
                                        <label>{{ t('so_unit') }}</label>
                                        <el-select
                                            v-if="line.units && line.units.length > 1"
                                            v-model="line.unit"
                                            value-key="id"
                                            :aria-label="fieldLabel(line, t('so_unit'))"
                                            @change="(u) => onUnitChange(line, u)"
                                        >
                                            <el-option v-for="u in line.units" :key="u.id" :label="unitName(u) || u.name_ar || u.name" :value="u" />
                                        </el-select>
                                        <span v-else class="sline-unit">
                                            <i class="fas fa-box text-muted"></i> {{ unitName(line.unit) || t('piece') }}
                                        </span>
                                    </div>

                                    <div class="sline-field">
                                        <label>{{ t('quantity') }}</label>
                                        <div class="sline-stepper" :class="{ 'is-over': overStock(line) }">
                                            <button type="button" :disabled="toNum(line.quantity) <= 1" :aria-label="t('decrease')" @click="stepLineQty(line, -1)">
                                                <i class="fas fa-minus"></i>
                                            </button>
                                            <input
                                                :value="line.quantity"
                                                type="number"
                                                inputmode="numeric"
                                                min="1"
                                                step="1"
                                                dir="ltr"
                                                :aria-label="fieldLabel(line, t('quantity'))"
                                                @focus="$event.target.select()"
                                                @input="onLineQtyInput(line, $event)"
                                                @blur="onLineQtyBlur(line, $event)"
                                            />
                                            <button type="button" :aria-label="t('increase')" @click="stepLineQty(line, 1)">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                        <small v-if="(toNum(line.unit?.base_unit_multiplier) || 1) > 1" class="sline-hint">
                                            = {{ linePieces(line) }} {{ t('piece') }}
                                        </small>
                                    </div>

                                    <div class="sline-field">
                                        <label>{{ t('unit_price') }}</label>
                                        <el-input-number
                                            v-model="line.price"
                                            :min="0"
                                            :precision="unitPriceDigits(toNum(line.base_price) * (toNum(line.unit?.price_multiplier) || 1))"
                                            :controls="false"
                                            :aria-label="fieldLabel(line, t('unit_price'))"
                                        />
                                    </div>

                                    <div class="sline-field">
                                        <label>{{ t('discount') }}</label>
                                        <el-input-number
                                            v-model="line.discount"
                                            :min="0"
                                            :precision="2"
                                            :controls="false"
                                            :aria-label="fieldLabel(line, t('discount'))"
                                            :class="{ 'is-invalid': lineDiscountTooBig(line) || errors[`items.${i}.discount`] }"
                                        />
                                    </div>

                                    <div class="sline-field">
                                        <label>{{ t('tax') }}</label>
                                        <el-input-number
                                            v-model="line.tax"
                                            :min="0"
                                            :precision="2"
                                            :controls="false"
                                            :aria-label="fieldLabel(line, t('tax'))"
                                        />
                                    </div>

                                    <div class="sline-field sline-total">
                                        <label>{{ t('so_line_total_of') }}</label>
                                        <strong>{{ formatCurrency(lineTotal(line)) }}</strong>
                                    </div>
                                </div>

                                <!-- Row actions: duplicate then remove, most destructive last -->
                                <div class="sline-actions">
                                    <button
                                        type="button"
                                        class="sline-btn"
                                        :title="t('duplicate_line')"
                                        :aria-label="`${t('duplicate_line')}: ${line.name}`"
                                        @click="duplicateLine(i)"
                                    >
                                        <i class="fas fa-copy"></i>
                                    </button>
                                    <button
                                        type="button"
                                        class="sline-btn is-remove"
                                        :title="t('delete')"
                                        :aria-label="`${t('delete')}: ${line.name}`"
                                        @click="form.items.splice(i, 1)"
                                    >
                                        <i class="fas fa-trash-can"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>

                    <div v-else class="pf-empty">
                        <span class="pf-empty-icon"><i class="fas fa-cart-shopping"></i></span>
                        <strong>{{ fieldError('items') || t('pf_no_lines_title') }}</strong>
                        <p>{{ t('qt_no_lines') }}</p>
                        <div class="pf-row">
                            <el-button type="primary" @click="focusCatalog"><i class="fas fa-magnifying-glass"></i>&nbsp;{{ t('pf_search_catalog') }}</el-button>
                            <el-button @click="openQuickProduct('')"><i class="fas fa-plus"></i>&nbsp;{{ t('pqa_button') }}</el-button>
                        </div>
                    </div>

                    <footer v-if="form.items.length" class="pf-lines-foot">
                        <span>{{ t('so_items_n', { count: form.items.length }) }} · {{ t('pieces_count', { count: pieces }) }}</span>
                        <span>{{ t('subtotal') }}: <strong>{{ formatCurrency(subtotal) }}</strong></span>
                    </footer>
                </section>

                <!-- Charges, notes and the money -->
                <div class="pf-bottom">
                    <div class="sf-side-stack">
                        <section v-if="!amending" class="pf-card pf-card-pad">
                            <OrderExpensesEditor v-model="form.expenses" @total-change="onExpensesTotalChange" />
                        </section>
                        <section class="pf-card pf-card-pad">
                            <span class="pf-label" style="margin-bottom: 0.4rem"><i class="fas fa-note-sticky"></i> {{ t('notes') }}</span>
                            <el-input
                                v-model="form.notes"
                                type="textarea"
                                :rows="3"
                                maxlength="1000"
                                show-word-limit
                                :placeholder="t('notes')"
                            />
                            <span class="pf-label" style="margin: 0.85rem 0 0.4rem"><i class="fas fa-location-dot"></i> {{ t('delivery_and_shipping_address') }}</span>
                            <el-input
                                v-model="form.shipping_address"
                                type="textarea"
                                :rows="2"
                                maxlength="500"
                                :placeholder="t('shipping_address_label')"
                            />
                        </section>
                    </div>

                    <section ref="totalsRef" class="pf-card pf-card-pad pf-totals">
                        <div class="pf-sum-row">
                            <span>{{ t('subtotal') }}</span>
                            <strong>{{ formatCurrency(subtotal) }}</strong>
                        </div>

                        <div class="sf-disc">
                            <div class="sf-disc-head">
                                <span class="sf-disc-label"><i class="fas fa-tag"></i> {{ t('sales.discount_label') || t('discount') }}</span>
                                <div class="sf-seg" role="radiogroup" :aria-label="t('discount')">
                                    <button type="button" :class="{ 'is-active': form.discount_type === 'percent' }" @click="switchDiscountType('percent')">%</button>
                                    <button type="button" :class="{ 'is-active': form.discount_type === 'amount' }" @click="switchDiscountType('amount')">{{ t('sales.discount_as_amount') || 'مبلغ ثابت' }}</button>
                                </div>
                            </div>
                            <div class="sf-money" :class="{ 'is-invalid': discountTooBig || errors.discount || errors.discount_percent }">
                                <el-input-number
                                    v-if="form.discount_type === 'percent'"
                                    ref="discountInput"
                                    v-model="form.discount_percent"
                                    :min="0"
                                    :max="100"
                                    :precision="2"
                                    :controls="false"
                                    :aria-label="t('sales.discount_as_percent')"
                                />
                                <el-input-number
                                    v-else
                                    ref="discountInput"
                                    v-model="form.discount_amount"
                                    :min="0"
                                    :max="subtotal"
                                    :precision="2"
                                    :controls="false"
                                    :aria-label="t('sales.discount_as_amount')"
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
                                        @click="setDiscountPreset(d)"
                                    >{{ d }}%</button>
                                </div>
                                <span v-if="!discountError && discountAmount > 0" class="sf-disc-live">
                                    <template v-if="form.discount_type === 'percent'">− {{ formatCurrency(discountAmount) }}</template>
                                    <template v-else>≈ {{ effectiveDiscountRate }}%</template>
                                </span>
                            </div>
                            <small v-if="discountError" class="sf-disc-error">{{ discountError }}</small>
                        </div>

                        <div class="pf-field span-12">
                            <span class="pf-label"><i class="fas fa-receipt"></i> {{ t('qt_extra_tax') }}</span>
                            <div class="sf-money">
                                <el-input-number
                                    v-model="form.tax"
                                    :min="0"
                                    :precision="2"
                                    :controls="false"
                                    :aria-label="t('qt_extra_tax')"
                                />
                            </div>
                        </div>

                        <div v-if="form.expenses && form.expenses.length" class="pf-sum-row">
                            <span>{{ t('so_shipping_cost') }}</span>
                            <span class="pf-num">+ {{ formatCurrency(form.shipping_cost) }}</span>
                        </div>
                        <div v-else class="pf-field span-12">
                            <span class="pf-label"><i class="fas fa-truck-fast"></i> {{ t('so_shipping_cost') }}</span>
                            <div class="sf-money">
                                <el-input-number
                                    v-model="form.shipping_cost"
                                    :disabled="amending"
                                    :min="0"
                                    :precision="2"
                                    :controls="false"
                                    :aria-label="t('so_shipping_cost')"
                                />
                            </div>
                        </div>

                        <div class="pf-grand" :class="{ 'is-invalid': discountTooBig }">
                            <span>{{ t('total') }}</span>
                            <strong>{{ formatCurrency(total) }}</strong>
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
                    :count-of="getOrderItemQuantity"
                    :pending-of="pendingQtyOf"
                    :is-over="isOverStock"
                    :price-of="listPrice"
                    :name-of="productName"
                    :show-purchase="canShowPurchase"
                    :purchase-label="t('so_add_to_purchase_list')"
                    :add-label="t('so_add_to_order')"
                    :placeholder="t('qt_product_search_placeholder')"
                    @input="onQuickSearchInput"
                    @enter="onQuickSearchEnter"
                    @clear="onQuickSearchClear"
                    @category-change="onCategoryFilterChange"
                    @create="openQuickProduct"
                    @add="addProduct"
                    @bump-pending="bumpPendingQty"
                    @set-pending="setPendingQty"
                    @blur-pending="normalizePendingQty"
                    @decrement="decrementOrderProduct"
                    @set="setOrderProductQuantity"
                    @commit="commitOrderProductQuantity"
                    @purchase="openPurchaseDialog"
                />
            </aside>
        </div>

        <!-- Footer: the money at a glance and the ways to save -->
        <footer v-if="!loading && !isLocked" class="pf-page-footer">
            <div class="pf-footer">
                <div class="pf-footer-sum">
                    <div class="pf-footer-metric is-main" :class="{ 'is-invalid': discountTooBig }">
                        <span>{{ t('total') }}</span>
                        <strong>{{ formatCurrency(total) }}</strong>
                    </div>
                    <span class="pf-footer-sep"></span>
                    <div class="pf-footer-metric">
                        <span>{{ t('items') }}</span>
                        <strong>{{ form.items.length }}</strong>
                    </div>
                    <div class="pf-footer-metric">
                        <span>{{ t('pf_units') }}</span>
                        <strong>{{ pieces }}</strong>
                    </div>
                    <div v-if="overStockLines.length" class="pf-footer-metric is-warn">
                        <span>{{ t('so_stock_shortage') }}</span>
                        <strong>{{ overStockLines.length }}</strong>
                    </div>
                </div>
                <div class="pf-footer-actions">
                    <span v-if="issues.length" class="pf-footer-hint">
                        <i class="fas fa-circle-exclamation"></i> {{ blocker }}
                    </span>
                    <span v-else class="pf-kbd-hint"><kbd>Ctrl</kbd> + <kbd>Enter</kbd> · <kbd>F2</kbd> {{ t('search') }} · <kbd>F4</kbd> {{ t('pqa_button') }}</span>
                    <el-button @click="goBack">{{ t('cancel') }}</el-button>
                    <el-button
                        :type="!authStore.isSalesRep && !versioning ? 'default' : 'primary'"
                        :loading="saving === 'draft'"
                        :disabled="!!saving"
                        @click="trySubmit(false)"
                    >
                        <i class="fas fa-floppy-disk"></i>&nbsp;{{ isEdit ? t('save_order_changes') : t('save_sales_order_draft') }}
                    </el-button>
                    <el-button
                        v-if="!authStore.isSalesRep && !versioning"
                        type="primary"
                        :loading="saving === 'confirm'"
                        :disabled="!!saving"
                        @click="trySubmit(true)"
                    >
                        <i class="fas fa-circle-check"></i>&nbsp;{{ t('so_save_and_confirm') }}
                    </el-button>
                </div>
            </div>
        </footer>
        <!-- Quick Purchase Dialog -->
        <el-dialog
            v-if="canShowPurchase"
            v-model="purchaseDialogVisible"
            :title="$t('so_quick_purchase_title') || 'إضافة المنتج لقائمة الشراء / أمر الشراء'"
            width="540px"
            destroy-on-close
            append-to-body
            class="quick-purchase-dialog"
        >
            <div v-if="purchaseDialogProduct" class="purchase-dialog-content">
                <!-- Product Preview Strip -->
                <div class="purchase-product-preview">
                    <EntityImage
                        :src="productImageSrc(purchaseDialogProduct)"
                        type="product"
                        :size="64"
                        shape="square"
                        class="dialog-product-img"
                    />
                    <div class="dialog-product-info">
                        <h4 class="dialog-product-name">{{ productName(purchaseDialogProduct) }}</h4>
                        <div class="dialog-product-meta">
                            <span v-if="purchaseDialogProduct.sku" class="sku-badge"><i class="fas fa-barcode"></i> {{ purchaseDialogProduct.sku }}</span>
                            <VariantChip v-if="purchaseDialogProduct.variant_id" :label="purchaseDialogProduct.variant_label" />
                            <span :class="['stock-pill', Number(purchaseDialogProduct.stock_quantity) <= 0 ? 'is-out' : 'is-ok']">
                                {{ $t('available') }}: {{ purchaseDialogProduct.stock_quantity ?? 0 }}
                            </span>
                        </div>
                    </div>
                </div>

                <el-form label-position="top" class="purchase-dialog-form">
                    <div class="purchase-grid-2">
                        <!-- Purchase Quantity -->
                        <el-form-item :label="$t('so_purchase_qty') || 'كمية الشراء'" required>
                            <el-input-number
                                v-model="purchaseForm.quantity"
                                :min="1"
                                :step="1"
                                controls-position="right"
                                style="width: 100%"
                            />
                        </el-form-item>

                        <!-- Purchase Cost / Unit Price -->
                        <el-form-item :label="$t('so_purchase_cost') || 'سعر الشراء للوحدة'">
                            <el-input-number
                                v-model="purchaseForm.unit_price"
                                :min="0"
                                :precision="2"
                                :controls="false"
                                style="width: 100%"
                            />
                        </el-form-item>
                    </div>

                    <!-- Supplier Selection -->
                    <el-form-item :label="$t('so_select_supplier') || 'المورد'" required>
                        <el-select
                            v-model="purchaseForm.supplier_id"
                            filterable
                            clearable
                            :placeholder="$t('so_select_supplier') || 'اختر المورد...'"
                            :loading="suppliersLoading"
                            style="width: 100%"
                        >
                            <el-option
                                v-for="s in suppliersStore.suppliers"
                                :key="s.id"
                                :label="s.name"
                                :value="s.id"
                            >
                                <div class="supplier-option-row">
                                    <span>{{ s.name }}</span>
                                    <small v-if="s.company" class="text-muted">({{ s.company }})</small>
                                </div>
                            </el-option>
                        </el-select>
                    </el-form-item>

                    <!-- Target Warehouse -->
                    <el-form-item :label="$t('warehouse') || 'المستودع المستهدف'">
                        <el-select
                            v-model="purchaseForm.warehouse_id"
                            clearable
                            filterable
                            :placeholder="$t('choose_warehouse_placeholder')"
                            style="width: 100%"
                        >
                            <el-option
                                v-for="wh in warehouses"
                                :key="wh.id"
                                :label="wh.name"
                                :value="wh.id"
                            />
                        </el-select>
                    </el-form-item>

                    <!-- Notes -->
                    <el-form-item :label="$t('notes')">
                        <el-input
                            v-model="purchaseForm.notes"
                            type="textarea"
                            :rows="2"
                            :placeholder="$t('po_note_placeholder') || 'ملاحظات أمر الشراء...'"
                        />
                    </el-form-item>
                </el-form>
            </div>

            <template #footer>
                <div class="dialog-footer-actions">
                    <el-button @click="purchaseDialogVisible = false">
                        {{ $t('cancel') }}
                    </el-button>
                    <el-button
                        type="default"
                        @click="openInPurchasesScreen"
                    >
                        <i class="fas fa-arrow-up-right-from-square"></i>&nbsp;{{ $t('so_open_in_purchases') || 'فتح في شاشة المشتريات' }}
                    </el-button>
                    <el-button
                        type="primary"
                        :loading="submittingPurchase"
                        :disabled="!purchaseForm.supplier_id"
                        @click="submitPurchaseOrder"
                    >
                        <i class="fas fa-check"></i>&nbsp;{{ $t('so_create_purchase_order') || 'إنشاء أمر الشراء' }}
                    </el-button>
                </div>
            </template>
        </el-dialog>
        <ProductQuickAdd
            v-model="quickProductVisible"
            :seed="quickProductSeed"
            :categories="categoryOptions"
            :default-category-id="selectedCategoryId"
            :hide-cost="!canShowPurchase"
            @created="onQuickProductCreated"
            @use-existing="(row, qty) => addProduct(row, qty)"
        />
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter, onBeforeRouteLeave } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import EntityImage from '@/components/admin/EntityImage.vue';
import VariantChip from '@/components/admin/products/VariantChip.vue';
import VariantDetails from '@/components/admin/products/VariantDetails.vue';
import SalesCatalogPanel from '@/components/admin/sales/SalesCatalogPanel.vue';
import { useVariantSiblings } from '@/composables/useVariantSiblings';
import { categoriesApi } from '@/api/categories';
import { posApi } from '@/api/pos';
import { inventoryApi } from '@/api/inventory';
import { salesOrdersApi } from '@/api/salesOrders';
import { purchaseOrdersApi } from '@/api/purchaseOrders';
import { useSuppliersStore } from '@/stores/suppliers';
import { useAuthStore } from '@/stores/auth';
import { resolveImageUrl } from '@/utils/productImages';
import api from '@/api/index';
import { useStockShortage } from '@/Composables/useStockShortage';
import { formatCurrency, localIsoDate, statusLabel } from '@/utils/sales';
import { roundUnitPrice, unitPriceDigits } from '@/utils/currency';
import { optionKey, variantLabelOf, variantFieldsOf, lineVariantInfo } from '@/utils/productPick';
import OrderExpensesEditor from '@/components/admin/sales/OrderExpensesEditor.vue';
import CustomerQuickAdd from '@/components/admin/sales/CustomerQuickAdd.vue';
import ProductQuickAdd from '@/components/admin/sales/ProductQuickAdd.vue';
import '@/components/admin/sales/sales-form.css';

const { t, locale } = useI18n();
const route = useRoute();
const router = useRouter();
const { handleStockShortage } = useStockShortage();

const orderId = route.params.id || null;
const isEdit = computed(() => !!orderId);
const authStore = useAuthStore();

// A manager edits the order at any stage; a sales rep (sales-scoped) edits it
// until it is delivered or cancelled. Past confirmation the API moves the
// hold, the stock and the invoice with the lines
// (SalesOrderWorkflowService::amendLines), and every edit past the pending
// stage is kept as a new version of the order (snapshotRevision).
const isScoped = computed(() => Boolean(authStore.user?.is_sales_scoped));
const AMENDABLE_STATUSES = computed(() => (isScoped.value
    ? ['confirmed', 'processing', 'shipped']
    : ['confirmed', 'processing', 'shipped', 'delivered']));
const EDITABLE_STATUSES = computed(() => (isScoped.value
    ? ['pending', ...AMENDABLE_STATUSES.value]
    : ['pending', ...AMENDABLE_STATUSES.value, 'cancelled']));
const isSellsRole = computed(() => {
    if (authStore.isSuperAdmin) return false;
    return authStore.hasRole('sells') ||
        authStore.user?.role?.name === 'sells' ||
        authStore.user?.roles?.some((r) => r.name === 'sells') ||
        Boolean(authStore.user?.is_sales_scoped);
});

const canShowPurchase = computed(() => {
    if (authStore.isSuperAdmin) return true;
    if (isSellsRole.value) return false;
    return true;
});

const canAddCustomer = computed(() => authStore.can('customers.create'));

const backArrow = computed(() => (locale.value === 'ar' ? 'fa-arrow-right' : 'fa-arrow-left'));
const DRAFT_KEY = 'sales-order-draft';

const today = () => localIsoDate();
const toNum = (v) => (Number.isFinite(Number(v)) ? Number(v) : 0);
const round2 = (n) => Math.round((Number(n) || 0) * 100) / 100;

function inDays(days) {
    const d = new Date();
    d.setDate(d.getDate() + days);
    return localIsoDate(d);
}

const pieceUnit = (name) => ({
    id: null,
    name: name || t('piece'),
    name_ar: name || t('piece'),
    base_unit_multiplier: 1,
    price_multiplier: 1,
    barcode: '',
});

const blank = () => ({
    customer_id: null,
    assigned_employee_id: null,
    order_date: today(),
    expected_delivery: inDays(3),
    fulfillment_type: 'ship',
    fulfillment_warehouse_id: null,
    shipping_cost: 0,
    shipping_address: '',
    discount_type: 'percent',
    discount_percent: 0,
    discount_amount: 0,
    discount: 0,
    tax: 0,
    notes: '',
    items: [],
    expenses: [],
});

const form = reactive(blank());
const errors = reactive({});
const serverErrors = ref([]);
const saving = ref(null); // null | 'draft' | 'confirm'
const loading = ref(false);
const isLocked = ref(false);
const loadedOrder = ref(null);
const amending = computed(() => isEdit.value && AMENDABLE_STATUSES.value.includes(loadedOrder.value?.status));
const versioning = computed(() => isEdit.value && !!loadedOrder.value && loadedOrder.value.status !== 'pending');
const currentRevision = computed(() => Number(loadedOrder.value?.revision) || 1);
const revisionNote = ref('');
const dirty = ref(false);
let lineSeq = 0;

// Element handles used to move focus onto whichever field needs fixing.
const formRoot = ref(null);
const customerSelect = ref(null);
const deliveryPicker = ref(null);
const discountInput = ref(null);

/* Workspace: the order beside the product catalogue (the purchase order's
   layout). Below a wide screen the catalogue folds above it. */
const headRef = ref(null);
const linesRef = ref(null);
const totalsRef = ref(null);
const catalogFolded = ref(false);

const focusCatalog = () => {
    if (isLocked.value) return;
    catalogFolded.value = false;
    nextTick(() => quickSearchInputRef.value?.focus());
};

// Warehouses
const warehouses = ref([]);

// Customers lookup
const customerOptions = ref([]);
const customersLoading = ref(false);
const selectedCustomer = ref(null);
let customerTimer = null;

// Product lookup
const productOptions = ref([]);
const productsLoading = ref(false);
const pickedProduct = ref(null);
const productPicker = ref(null);
const productQuery = ref('');
let productTimer = null;

/** MIN_SEARCH_CHARS used to be a silent rule: one letter returned nothing at
 *  all with no explanation, which read as "no products match". */
const MIN_SEARCH_CHARS = 2;

const pickerHint = computed(() => {
    const q = productQuery.value.trim();
    if (productsLoading.value) return { kind: 'is-info', text: t('searching_products') };
    if (q.length && q.length < MIN_SEARCH_CHARS) return { kind: 'is-info', text: t('search_type_to_search') };
    if (q.length && !productOptions.value.length) {
        return { kind: 'is-empty', text: `${t('no_matching_results')} — ${t('try_other_search_terms')}` };
    }
    // Idle: an empty box with no options looks broken, so name what it accepts.
    return { kind: 'is-idle', text: t('qt_add_product') };
});

const productName = (p) => (locale.value === 'en' && p.name_en ? p.name_en : (p.name_ar || p.name || ''));
const productLabel = (p) => `${productName(p)}${p.sku ? ` (${p.sku})` : ''}`;
/** Units carry both spellings; reading name_ar unconditionally showed Arabic
    names under the English locale. Mirrors productName's fallback order. */
const unitName = (u) => (locale.value === 'en' && u?.name ? u.name : (u?.name_ar || u?.name || ''));
const listPrice = (p) => toNum(p.has_sale && p.sale_price ? p.sale_price : p.price);

/** Names every line input after the product it belongs to, because the column
 *  header row is decorative and screen readers otherwise hit four bare numbers. */
const fieldLabel = (line, label) => `${label} — ${line.name || t('unknown_product')}`;

const stockColorClass = (stock) => {
    const qty = Number(stock) || 0;
    if (qty <= 0) return 'text-danger';
    if (qty <= 5) return 'text-warning';
    return 'text-success';
};

/* ── Customer Management ─────────────────────────────────────────── */

const searchCustomers = (query) => {
    customerQuery.value = String(query || '').trim();
    clearTimeout(customerTimer);
    customerTimer = setTimeout(async () => {
        customersLoading.value = true;
        try {
            const params = { search: query || undefined, per_page: 30 };
            if (isSellsRole.value) {
                params.mine = 1;
            }
            const res = await posApi.customers(params);
            const found = res.data?.data?.customers || [];
            const current = customerOptions.value.find((c) => c.id === form.customer_id);
            customerOptions.value = current && !found.some((c) => c.id === current.id) ? [current, ...found] : found;
        } catch {
            // Keep existing options
        } finally {
            customersLoading.value = false;
        }
    }, 250);
};

// ── Sales rep ──────────────────────────────────────────────────────────
const salesEmployees = ref([]);
const employeesLoading = ref(false);
// The rep the selected customer is served by, when the form took it from them.
const repFromCustomer = ref(null);

const ensureEmployeeOption = (id, name) => {
    if (id && !salesEmployees.value.some((e) => e.id === id)) {
        salesEmployees.value = [...salesEmployees.value, { id, name: name || `#${id}` }];
    }
};

const fetchSalesEmployees = async () => {
    employeesLoading.value = true;
    try {
        const { data } = await api.get('/sales-employees');
        const list = Array.isArray(data?.data) ? data.data : (Array.isArray(data?.data?.employees) ? data.data.employees : []);
        const current = salesEmployees.value;
        salesEmployees.value = list.map((e) => ({ id: e.id, name: e.name || `#${e.id}` }));
        current.forEach((e) => ensureEmployeeOption(e.id, e.name));
    } catch {
        // The field stays usable with whatever it already holds.
    } finally {
        employeesLoading.value = false;
    }
    // A rep's orders are always their own.
    if (isSellsRole.value && authStore.user?.employee_id) {
        form.assigned_employee_id = authStore.user.employee_id;
        ensureEmployeeOption(authStore.user.employee_id, authStore.user.name);
    }
};

/** A customer's own rep becomes the order's, unless the seller is a rep. */
const applyCustomerRep = (customer) => {
    const previous = repFromCustomer.value;
    repFromCustomer.value = customer?.employee_id || null;
    if (isSellsRole.value || amending.value) return;
    if (customer?.employee_id) {
        form.assigned_employee_id = customer.employee_id;
        ensureEmployeeOption(customer.employee_id, customer.sales_rep?.name);
    } else if (previous && form.assigned_employee_id === previous) {
        // The last customer's rep, filled in for them: not this customer's.
        form.assigned_employee_id = null;
    }
};

const onCustomerChange = (customerId) => {
    delete errors.customer_id;
    const customer = customerOptions.value.find((c) => c.id === customerId);
    selectedCustomer.value = customer || null;
    applyCustomerRep(customer);
    if (customer?.address && !form.shipping_address) {
        form.shipping_address = customer.address;
    }
};

/** Put a customer in the options (if missing) and select it. */
const selectCustomer = (customer) => {
    if (!customer?.id) return;
    if (!customerOptions.value.some((c) => c.id === customer.id)) {
        customerOptions.value = [customer, ...customerOptions.value];
    }
    form.customer_id = customer.id;
    onCustomerChange(customer.id);
};

// ── Quick-add customer ─────────────────────────────────────────────────
// The panel itself lives in CustomerQuickAdd.vue (shared with the quote
// form); this form only decides when it is open and what seeds it.
const customerQuery = ref('');
const justAddedCustomerId = ref(null);
const quickCustomer = reactive({ open: false, seed: '' });

const openQuickCustomer = (seed = '') => {
    customerSelect.value?.blur();
    quickCustomer.seed = String(seed || '').trim();
    quickCustomer.open = true;
};

const closeQuickCustomer = () => {
    quickCustomer.open = false;
};

const toggleQuickCustomer = () => {
    if (quickCustomer.open) closeQuickCustomer();
    else openQuickCustomer(customerQuery.value);
};

const onQuickCustomerSelect = (customer, { created } = {}) => {
    justAddedCustomerId.value = created ? customer?.id || null : null;
    selectCustomer(customer);
    closeQuickCustomer();
};

const creditCheck = computed(() => {
    const c = selectedCustomer.value;
    if (!c) return null;
    const balance = Number(c.balance) || 0;
    const limit = Number(c.credit_limit) || 0;
    const after = round2(balance + total.value);
    return { balance, limit, after, over: limit > 0 && after > limit };
});

/* ── Categories & Products Catalog ───────────────────────────────────── */
const productImageSrc = (p) => {
    if (!p) return '';
    const raw = p.image_main || p.image || p.primary_image_url || p.image_url || '';
    return resolveImageUrl(raw);
};

// Categories State
const categories = ref([]);
const categoriesLoading = ref(false);
const selectedCategoryId = ref(null);

const fetchCategories = async () => {
    if (categories.value.length) return;
    categoriesLoading.value = true;
    try {
        const res = await categoriesApi.getAll({ per_page: 500 });
        const list = res.data?.data || (Array.isArray(res.data) ? res.data : []);
        categories.value = list;
    } catch {
        categories.value = [];
    } finally {
        categoriesLoading.value = false;
    }
};

const categoryOptions = computed(() => {
    const name = (c) => (locale.value === 'en' && c.name_en ? c.name_en : (c.name_ar || c.name || ''));
    const roots = categories.value.filter((c) => !c.parent_id);
    return roots.flatMap((root) => [
        { id: root.id, parent_id: null, label: name(root) },
        ...categories.value.filter((c) => c.parent_id === root.id).map((c) => ({ id: c.id, parent_id: c.parent_id, label: name(c) })),
    ]);
});

const onCategoryFilterChange = () => {
    executeQuickSearch();
};

// Quick Search & Suggested Catalog State
const quickSearchQuery = ref('');
const quickSearchLoading = ref(false);
const quickSearchResults = ref([]);
// The catalogue panel (focus() puts the cursor in its search box).
const quickSearchInputRef = ref(null);
let quickSearchDebounceTimer = null;

const onQuickSearchInput = () => {
    clearTimeout(quickSearchDebounceTimer);
    quickSearchDebounceTimer = setTimeout(() => {
        quickSearchDebounceTimer = null;
        executeQuickSearch();
    }, 250);
};

const onQuickSearchClear = () => {
    quickSearchQuery.value = '';
    executeQuickSearch();
};

// Enter adds what the search unambiguously points at: a lone product, or a
// lone size. With several sizes showing it is the seller's call which one.
const onQuickSearchEnter = async () => {
    // A scanner types the code and presses Enter before the typed search has
    // run: the results showing are for the previous text, so search now.
    if (quickSearchDebounceTimer !== null) {
        clearTimeout(quickSearchDebounceTimer);
        quickSearchDebounceTimer = null;
        await executeQuickSearch();
    }
    if (quickSearchLoading.value) return;
    const first = quickSearchGroups.value[0];
    // Nothing matched what was typed: offer to create it.
    if (!first && quickSearchQuery.value.trim()) {
        openQuickProduct(quickSearchQuery.value);
        return;
    }
    if (!first) return;
    if (!first.hasVariants || first.rows.length === 1) {
        addProduct(first.rows[0], pendingQtyOf(first.rows[0]));
    }
};

// Search rows regrouped by product: a product with sizes comes back as one
// row per size, and the panel shows those as one card with a tile per size.
const quickSearchGroups = computed(() => {
    const groups = new Map();
    quickSearchResults.value.forEach((row) => {
        if (!groups.has(row.id)) groups.set(row.id, { key: String(row.id), product: row, rows: [] });
        groups.get(row.id).rows.push(row);
    });
    return [...groups.values()].map((g) => ({ ...g, hasVariants: g.rows.some((r) => r.variant_id) }));
});


// The sibling sizes of each picked product, for the switcher under a line.
const { rememberVariantRows, ensureVariantsLoaded, siblingsOf } = useVariantSiblings();
const lineSiblings = (line) => (line.product_id && line.product_variant_id ? siblingsOf(line.product_id) : []);
const variantInfo = (line) => lineVariantInfo(line, lineSiblings(line));
const preloadLineSiblings = () => form.items.forEach((line) => line.product_variant_id && ensureVariantsLoaded(line.product_id));

// Switching a line to another size keeps its quantity, unit and any price the
// seller typed; a price still at the old size's list price follows the new
// one. If that size is already a line, the two lines become one.
const switchLineVariant = (line, row) => {
    if (!line || (row.variant_id || null) === (line.product_variant_id || null)) return;
    const index = form.items.indexOf(line);
    if (index === -1) return;

    const other = lineFor(row);
    if (other && other !== line) {
        other.quantity = toNum(other.quantity) + toNum(line.quantity);
        form.items.splice(index, 1);
        ElMessage.success(t('so_qty_now', { name: other.name, count: other.quantity }));
        return;
    }

    const multiplier = toNum(line.unit?.price_multiplier) || 1;
    const priceUntouched = !toNum(line.base_price) || roundUnitPrice(line.price) === roundUnitPrice(toNum(line.base_price) * multiplier);
    line.product_variant_id = row.variant_id || null;
    line.name = productName(row);
    line.sku = row.sku || '';
    line.variant_label = row.variant_label || '';
    line.variant = variantFieldsOf(row);
    line.image = productImageSrc(row) || line.image;
    line.stock = row.stock_quantity != null ? Number(row.stock_quantity) : undefined;
    line.base_price = listPrice(row);
    if (priceUntouched) line.price = roundUnitPrice(line.base_price * multiplier);
};

// Each search is numbered so a slow earlier response cannot overwrite the
// results of the text typed after it.
let quickSearchSeq = 0;

const executeQuickSearch = async () => {
    const mine = ++quickSearchSeq;
    quickSearchLoading.value = true;
    try {
        const params = {
            expand_variants: 1,
        };
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

// The line a search row stands for. Lines restored from a saved order or a
// draft carry their own keys, so this goes by product and size rather than
// by the key the search row would have been added under.
const lineFor = (product) => {
    if (!product) return undefined;
    const productId = Number(product.id ?? product.product_id);
    const variantId = product.variant_id ?? product.product_variant_id ?? null;
    return form.items.find((line) => Number(line.product_id) === productId && (line.product_variant_id || null) === (variantId || null));
};

const getOrderItemQuantity = (product) => {
    const existing = lineFor(product);
    return existing ? toNum(existing.quantity) : 0;
};

// Quantity typed on a result card before the product is in the order, keyed
// by listing key. Absent means 1. Letting the seller type "12" here saves
// eleven clicks on the stepper or a trip down to the lines table.
const pendingQty = reactive({});
const productKey = (product) => product.listing_key || optionKey(product);
const pendingQtyOf = (product) => toNum(pendingQty[productKey(product)]) || 1;

const setPendingQty = (product, raw) => {
    const key = productKey(product);
    const n = Math.floor(toNum(raw));
    if (n >= 1) pendingQty[key] = n;
    // Empty or 0 while typing: keep the last good value until blur normalises it.
};

const bumpPendingQty = (product, delta) => {
    const key = productKey(product);
    pendingQty[key] = Math.max(1, pendingQtyOf(product) + delta);
};

const normalizePendingQty = (product, input) => {
    const shown = pendingQtyOf(product);
    if (input && String(input.value) !== String(shown)) input.value = shown;
};

const isOverStock = (product, qty) =>
    product?.stock_quantity != null && toNum(qty) > Number(product.stock_quantity);

/** Direct edit of a line's quantity from its result card; 0 or empty is resolved on blur. */
const setOrderProductQuantity = (product, raw) => {
    const line = lineFor(product);
    if (!line) return;
    const n = Math.floor(toNum(raw));
    if (n >= 1) line.quantity = n;
};

const commitOrderProductQuantity = (product, input) => {
    const index = form.items.indexOf(lineFor(product));
    if (index === -1) return;
    const n = Math.floor(toNum(input?.value));
    if (n < 1) {
        form.items.splice(index, 1);
        ElMessage.info(t('item_removed') || 'تم حذف البند من الطلب');
        return;
    }
    form.items[index].quantity = n;
    if (input && String(input.value) !== String(n)) input.value = n;
};

const decrementOrderProduct = (product) => {
    const index = form.items.indexOf(lineFor(product));
    if (index === -1) return;

    if (form.items[index].quantity > 1) {
        form.items[index].quantity -= 1;
    } else {
        form.items.splice(index, 1);
        ElMessage.info(t('item_removed') || 'تم حذف البند من الطلب');
    }
};

const loadUnits = async (line, { keepSelection = false } = {}) => {
    try {
        const { data } = await api.get(`/admin/products/${line.product_id}/units`);
        const units = (data?.data || []).map((u) => ({
            id: u.id,
            name: u.name,
            name_ar: u.name_ar || u.name,
            base_unit_multiplier: parseFloat(u.base_unit_multiplier) || 1,
            price_multiplier: parseFloat(u.price_multiplier) || 1,
            barcode: u.barcode || '',
            is_default: !!u.is_default,
        }));
        if (!units.length) return;

        line.units = [line.unit, ...units.filter((u) => u.id !== line.unit?.id)];
        if (keepSelection) {
            const saved = units.find((u) => u.id === line.unit?.id);
            if (saved) line.unit = saved;
            return;
        }
        const defaultUnit = units.find((u) => u.is_default);
        if (defaultUnit) {
            line.unit = defaultUnit;
            if (line.base_price) {
                line.price = roundUnitPrice(line.base_price * defaultUnit.price_multiplier);
            }
        }
    } catch {
        // Keep piece unit
    }
};

const onUnitChange = (line, unit) => {
    line.unit = unit;
    if (unit?.price_multiplier && line.base_price) {
        line.price = roundUnitPrice(line.base_price * unit.price_multiplier);
    }
};

const addProduct = (product, qtyDelta = 1) => {
    if (!product) return;
    const key = product.listing_key || optionKey(product);
    const existing = lineFor(product);
    if (product.variant_id) ensureVariantsLoaded(product.id);

    if (existing) {
        existing.quantity = (toNum(existing.quantity) || 0) + qtyDelta;
        ElMessage.success(t('so_qty_now', { name: existing.name, count: existing.quantity }));
    } else {
        const unit = pieceUnit(product.unit);
        const line = {
            key,
            product_id: product.id,
            product_variant_id: product.variant_id || null,
            name: productName(product),
            sku: product.sku || '',
            variant_label: product.variant_label || '',
            variant: product.variant_id ? variantFieldsOf(product) : null,
            image: productImageSrc(product),
            quantity: Math.max(1, qtyDelta),
            unit,
            units: [unit],
            base_price: listPrice(product),
            price: listPrice(product),
            stock: product.stock_quantity != null ? Number(product.stock_quantity) : undefined,
            discount: 0,
            tax: 0,
        };
        form.items.unshift(line);
        loadUnits(line);
        ElMessage.success(
            qtyDelta > 1 ? t('so_added_n', { name: line.name, count: qtyDelta }) : t('so_added', { name: line.name }),
        );
    }

    delete pendingQty[key];
    delete errors.items;
};

// ── Quick "new product" ──
// What the seller searched for seeds the dialog, so a failed search turns
// straight into the product it was looking for.
const quickProductVisible = ref(false);
const quickProductSeed = ref('');

const openQuickProduct = (seed = '') => {
    if (isLocked.value) return;
    quickProductSeed.value = String(seed || '').trim();
    quickProductVisible.value = true;
    if (!categories.value.length) fetchCategories();
};

const onQuickProductCreated = (product, quantity = 1) => {
    addProduct({ ...product, listing_key: optionKey(product) }, quantity);
    quickSearchQuery.value = '';
    executeQuickSearch();
};

// ── Quick Purchase Dialog State & Methods ──
const purchaseDialogVisible = ref(false);
const purchaseDialogProduct = ref(null);
const suppliersStore = useSuppliersStore();
const suppliersLoading = ref(false);
const submittingPurchase = ref(false);

const purchaseForm = reactive({
    quantity: 1,
    unit_price: 0,
    supplier_id: null,
    warehouse_id: null,
    notes: '',
});

const openPurchaseDialog = async (product) => {
    if (!canShowPurchase.value) return;
    purchaseDialogProduct.value = product;
    const existingQty = getOrderItemQuantity(product);
    purchaseForm.quantity = existingQty > 0 ? existingQty : 1;
    purchaseForm.unit_price = toNum(product.cost_price || product.price || 0);
    purchaseForm.supplier_id = null;
    purchaseForm.warehouse_id = form.fulfillment_warehouse_id || (warehouses.value[0]?.id || null);
    purchaseForm.notes = locale.value === 'ar'
        ? `طلب شراء للمنتج "${productName(product)}" من شاشة إنشاء أمر البيع`
        : `Purchase request for "${productName(product)}" from sales order screen`;

    purchaseDialogVisible.value = true;

    if (!suppliersStore.suppliers?.length) {
        suppliersLoading.value = true;
        try {
            await suppliersStore.fetchSuppliers({ per_page: 100 });
        } catch {}
        finally {
            suppliersLoading.value = false;
        }
    }
};

const openPurchaseDialogFromLine = (line) => {
    if (!canShowPurchase.value) return;
    const pseudoProduct = {
        id: line.product_id,
        variant_id: line.product_variant_id,
        name_ar: line.name,
        name_en: line.name,
        name: line.name,
        sku: line.sku,
        variant_label: line.variant_label,
        stock_quantity: line.stock,
        cost_price: line.base_price || line.price,
        price: line.price,
        image_main: line.image,
    };
    openPurchaseDialog(pseudoProduct);
    const shortageNeeded = linePieces(line) - (Number(line.stock) || 0);
    if (shortageNeeded > 0) {
        purchaseForm.quantity = shortageNeeded;
    }
};

const openInPurchasesScreen = () => {
    if (!purchaseDialogProduct.value) return;
    const p = purchaseDialogProduct.value;
    const query = {
        add_product_id: p.id,
        add_variant_id: p.variant_id || undefined,
        add_qty: purchaseForm.quantity || 1,
        add_price: purchaseForm.unit_price || undefined,
        supplier_id: purchaseForm.supplier_id || undefined,
        warehouse_id: purchaseForm.warehouse_id || undefined,
        notes: purchaseForm.notes || undefined,
    };
    purchaseDialogVisible.value = false;
    router.push({ path: '/admin/purchases/orders', query });
};

const submitPurchaseOrder = async () => {
    if (!purchaseDialogProduct.value) return;
    const p = purchaseDialogProduct.value;

    if (!purchaseForm.supplier_id) {
        ElMessage.warning(t('so_select_supplier') || 'يرجى اختيار المورد أولاً');
        return;
    }

    submittingPurchase.value = true;
    try {
        const payload = {
            supplier_id: purchaseForm.supplier_id,
            warehouse_id: purchaseForm.warehouse_id || null,
            order_date: today(),
            status: 'pending',
            notes: purchaseForm.notes || null,
            items: [
                {
                    product_id: p.id,
                    product_variant_id: p.variant_id || null,
                    quantity: Math.max(1, Number(purchaseForm.quantity) || 1),
                    unit_price: Number(purchaseForm.unit_price) || 0,
                    notes: purchaseForm.notes || null,
                },
            ],
        };

        const res = await purchaseOrdersApi.create(payload);
        const createdPO = res.data?.data;
        const poNumber = createdPO?.order_number || ('#' + (createdPO?.id || ''));

        ElMessageBox.alert(
            locale.value === 'ar'
                ? `تم إنشاء أمر الشراء بنجاح برقم: ${poNumber}`
                : `Purchase order created successfully with number: ${poNumber}`,
            t('success') || 'نجاح',
            {
                type: 'success',
                confirmButtonText: t('so_open_in_purchases') || 'عرض في شاشة المشتريات',
                callback: (action) => {
                    if (action === 'confirm') {
                        router.push({ path: '/admin/purchases/orders', query: { search: poNumber } });
                    }
                },
            }
        );

        purchaseDialogVisible.value = false;
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || t('failed_to_save_purchase_order') || 'تعذر حفظ أمر الشراء');
    } finally {
        submittingPurchase.value = false;
    }
};

const duplicateLine = (index) => {
    const line = form.items[index];
    if (!line) return;
    const copy = {
        ...line,
        key: ++lineSeq,
        units: [...(line.units || [])],
        unit: line.unit,
    };
    form.items.splice(index + 1, 0, copy);
    nextTick(() => {
        const rows = formRoot.value?.querySelectorAll('.sline') || [];
        rows[index + 1]?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    });
};

/* ── Totals & Validations ───────────────────────────────────────── */

const lineGross = (line) => round2(toNum(line.price) * toNum(line.quantity));
const lineTotal = (line) => round2(lineGross(line) - toNum(line.discount) + toNum(line.tax));
const lineDiscountTooBig = (line) => toNum(line.discount) > lineGross(line) + 0.00001;

const linePieces = (line) => (toNum(line.quantity) || 0) * (toNum(line.unit?.base_unit_multiplier) || 1);
const overStock = (line) => line.stock !== undefined && line.stock !== null && linePieces(line) > Number(line.stock);

const subtotal = computed(() => round2(form.items.reduce((sum, line) => sum + lineTotal(line), 0)));
const pieces = computed(() => form.items.reduce((sum, line) => sum + toNum(line.quantity), 0));
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
        if (amount < 0) return t('sales.discount_negative');
        if (amount > 0 && amount - subtotal.value > 0.005) {
            return t('sales.discount_amount_exceeds_subtotal', {
                discount: formatCurrency(amount),
                subtotal: formatCurrency(subtotal.value),
            });
        }
        return '';
    }
    const val = toNum(form.discount_percent);
    if (val < 0 || val > 100) {
        return t('sales.discount_percent_out_of_range');
    }
    return '';
});

const discountTooBig = computed(() => !!discountError.value);
const total = computed(() => round2(Math.max(0, subtotal.value - discountAmount.value) + toNum(form.tax) + toNum(form.shipping_cost)));

const switchDiscountType = (type) => {
    if (type === form.discount_type) return;
    if (type === 'amount') {
        form.discount_amount = discountAmount.value;
    } else {
        form.discount_percent = effectiveDiscountRate.value;
    }
    form.discount_type = type;
};

const overStockLines = computed(() => form.items.filter(overStock));

// The line's state against the document: a problem a save pointed at, more
// than the shelf holds, or fine.
const lineTone = (line, i) => {
    if (isFlagged(`items.${i}`) || isFlagged(`items.${i}.discount`) || lineDiscountTooBig(line)) return 'danger';
    if (overStock(line)) return 'warn';
    return 'ok';
};

// The quantity typed into a line's stepper: only a whole number of at least
// one lands while typing; leaving the box puts back the last good one.
const onLineQtyInput = (line, e) => {
    const n = Math.floor(Number(e.target.value));
    if (Number.isFinite(n) && n >= 1) line.quantity = n;
};

const onLineQtyBlur = (line, e) => {
    const n = Math.floor(toNum(line.quantity));
    line.quantity = n >= 1 ? n : 1;
    e.target.value = line.quantity;
};

const stepLineQty = (line, delta) => {
    line.quantity = Math.max(1, Math.floor(toNum(line.quantity)) + delta);
};

const setDiscountPreset = (percent) => {
    const off = form.discount_type === 'percent' && Number(form.discount_percent) === percent;
    form.discount_type = 'percent';
    form.discount_percent = off ? 0 : percent;
};

const clearLines = async () => {
    try {
        await ElMessageBox.confirm(t('pf_clear_lines_confirm'), t('pf_clear_lines'), {
            type: 'warning',
            confirmButtonText: t('pf_clear_lines'),
            cancelButtonText: t('cancel'),
        });
        form.items.splice(0, form.items.length);
    } catch {
        // Kept them.
    }
};

const onExpensesTotalChange = (newTotal) => {
    form.shipping_cost = toNum(newTotal);
    dirty.value = true;
};

/**
 * Everything still standing between the form and a save, in the order the user
 * meets it walking down the page. This used to be a single string, so a new
 * screen showed one problem and then, after fixing it, showed the next one.
 */
const issues = computed(() => {
    const list = [];
    if (!form.customer_id) {
        list.push({ key: 'customer_id', target: 'customer', text: t('qt_need_customer') });
    }
    if (!form.items.length) {
        list.push({ key: 'items', target: 'items', text: t('qt_need_lines') });
    }
    form.items.forEach((line, i) => {
        const qty = toNum(line.quantity);
        if (qty < 1 || !Number.isInteger(qty)) {
            list.push({
                key: `items.${i}`,
                target: `line-${i}`,
                text: `${line.name}: ${t('sof_quantity_whole') || 'the quantity must be at least 1.'}`,
            });
        }
        if (lineDiscountTooBig(line)) {
            list.push({
                key: `items.${i}.discount`,
                target: `line-${i}`,
                text: `${line.name}: ${t('qt_line_discount_too_big')}`,
            });
        }
    });
    if (discountTooBig.value) {
        list.push({ key: 'discount', target: 'discount', text: discountError.value || t('qt_discount_too_big') });
    }
    if (form.expected_delivery && form.order_date && form.expected_delivery < form.order_date) {
        list.push({
            key: 'expected_delivery',
            target: 'delivery',
            text: t('sof_delivery_after_order') || 'Delivery cannot be before the order date.',
        });
    }
    return list;
});

const blocker = computed(() => issues.value[0]?.text || '');

/** A field's error text: what the server said, or — once the reader has tried
 *  to save — what we can already see is wrong. An untouched form stays quiet;
 *  the status bar is where a fresh screen says what is missing. */
const fieldError = (key) => errors[key] || (isFlagged(key) ? issues.value.find((i) => i.key === key)?.text : '');

/** Highlights a field until it is corrected, so a failed save leaves a trail. */
const flagged = ref({});
const isFlagged = (key) => flagged.value[key] === true;

// The checklist shows once there is something on the order to check, or a
// save has been tried.
const showChecklist = computed(() => Boolean(form.customer_id) || form.items.length > 0 || Object.keys(flagged.value).length > 0);

const flagIssues = () => {
    flagged.value = Object.fromEntries(issues.value.map((i) => [i.key, true]));
};

const clearFlag = (key) => {
    if (flagged.value[key]) {
        const next = { ...flagged.value };
        delete next[key];
        flagged.value = next;
    }
};

// Drop the highlight as soon as the thing it points at is corrected, so the
// screen does not keep badmouthing a field the reader has already fixed.
watch(() => issues.value.map((i) => i.key).join('|'), (keys, before) => {
    if (keys === before) return;
    const live = new Set(issues.value.map((i) => i.key));
    Object.keys(flagged.value).forEach((key) => {
        if (!live.has(key)) clearFlag(key);
    });
});

/** Takes the reader from the status bar straight to the thing that needs fixing. */
const goToIssue = async (issue) => {
    flagIssues();
    await nextTick();

    const lineMatch = /^line-(\d+)$/.exec(issue.target || '');
    let el = null;
    if (lineMatch) {
        el = formRoot.value?.querySelectorAll('.sline')[Number(lineMatch[1])];
    } else if (issue.target === 'customer') {
        el = customerSelect.value?.$el || headRef.value;
    } else if (issue.target === 'delivery') {
        el = deliveryPicker.value?.$el;
    } else if (issue.target === 'items') {
        linesRef.value?.scrollIntoView({ block: 'center', behavior: 'smooth' });
        focusCatalog();
        return;
    } else if (issue.target === 'discount') {
        el = discountInput.value?.$el || totalsRef.value;
    }

    el?.scrollIntoView({ block: 'center', behavior: 'smooth' });
    el?.querySelector?.('input, textarea')?.focus?.();
};

const trySubmit = async (andConfirm) => {
    if (saving.value) return;
    if (issues.value.length) {
        flagIssues();
        await goToIssue(issues.value[0]);
        return;
    }
    submit(andConfirm);
};

const lockedReason = computed(() => ({
    confirmed: t('lock_reason_confirmed'),
    processing: t('lock_reason_processing'),
    shipped: t('lock_reason_shipped'),
    delivered: t('order_delivered_cycle_complete'),
    cancelled: t('state_hint_cancelled'),
}[loadedOrder.value?.status] || t('status_forbids_item_edits')));

/* ── Warehouses ─────────────────────────────────────────────────── */

const fetchWarehouses = async () => {
    try {
        const res = await inventoryApi.getWarehouses({ per_page: 100, is_active: true });
        const data = res.data?.data || res.data || [];
        warehouses.value = Array.isArray(data) ? data : [];
        // A sales rep's new order is filled from their own warehouse, and they
        // cannot change it (the API holds to it either way).
        if (authStore.isSalesRep) {
            if (!isEdit.value) form.fulfillment_warehouse_id = authStore.user?.employee_warehouse_id || null;
        } else if (!form.fulfillment_warehouse_id && warehouses.value.length) {
            form.fulfillment_warehouse_id = warehouses.value[0].id;
        }
    } catch {
        warehouses.value = [];
    }
};

/* ── Draft Support ──────────────────────────────────────────────── */

let draftTimer = null;
const draftSavedAt = ref('');

const saveDraft = () => {
    if (isEdit.value) return;
    if (!form.items.length && !form.customer_id) {
        clearDraft();
        return;
    }
    try {
        const draftData = {
            saved_at: new Date().toISOString(),
            form: {
                ...form,
                items: form.items.map((line) => ({
                    ...line,
                    units: [line.unit].filter(Boolean),
                })),
            },
            selectedCustomer: selectedCustomer.value,
        };
        localStorage.setItem(DRAFT_KEY, JSON.stringify(draftData));
        // The work is kept on this device whether or not anyone knows it, and
        // the form nags about leaving unsaved — so say when the last write was.
        draftSavedAt.value = new Date().toLocaleTimeString(
            locale.value === 'ar' ? 'ar' : 'en-GB',
            { hour: '2-digit', minute: '2-digit' }
        );
    } catch {
        // Storage full or unavailable
    }
};

const clearDraft = () => {
    try { localStorage.removeItem(DRAFT_KEY); } catch { /* ignore */ }
    draftSavedAt.value = '';
};

const offerDraft = async () => {
    let draft = null;
    try { draft = JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null'); } catch { draft = null; }
    const draftItems = draft?.form?.items || draft?.lines;
    if (!draftItems?.length) return;

    try {
        await ElMessageBox.confirm(
            t('so_restore_draft_message', { count: draftItems.length }),
            t('so_restore_draft_title'),
            {
                type: 'info',
                confirmButtonText: t('so_restore_draft'),
                cancelButtonText: t('so_discard_draft'),
                distinguishCancelAndClose: true,
            }
        );
    } catch (action) {
        if (action === 'cancel') clearDraft();
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
    Object.assign(form, {
        customer_id: saved.customer_id || null,
        assigned_employee_id: isSellsRole.value ? form.assigned_employee_id : (saved.assigned_employee_id || null),
        order_date: saved.order_date || today(),
        expected_delivery: saved.expected_delivery || inDays(3),
        fulfillment_type: saved.fulfillment_type || 'ship',
        fulfillment_warehouse_id: authStore.isSalesRep
            ? (authStore.user?.employee_warehouse_id || null)
            : (saved.fulfillment_warehouse_id || null),
        shipping_cost: toNum(saved.shipping_cost),
        shipping_address: saved.shipping_address || '',
        discount_type: saved.discount_type || 'percent',
        discount_percent: saved.discount_percent !== undefined ? toNum(saved.discount_percent) : 0,
        discount_amount: saved.discount_amount !== undefined ? toNum(saved.discount_amount) : toNum(saved.discount),
        discount: toNum(saved.discount),
        tax: toNum(saved.tax),
        notes: saved.notes || '',
        items: draftItems.map((line) => {
            const l = {
                ...line,
                key: ++lineSeq,
                quantity: toNum(line.quantity) || 1,
                price: toNum(line.price),
                discount: toNum(line.discount),
                tax: toNum(line.tax),
            };
            loadUnits(l, { keepSelection: true });
            return l;
        }),
    });
    preloadLineSiblings();

    if (draft.selectedCustomer) {
        selectedCustomer.value = draft.selectedCustomer;
        customerOptions.value = [draft.selectedCustomer];
    } else if (form.customer_id) {
        try {
            const { data } = await posApi.customerShow(form.customer_id);
            if (data?.data) {
                selectedCustomer.value = data.data;
                customerOptions.value = [data.data];
            }
        } catch { /* ignore */ }
    }
};

watch(
    () => [
        form.customer_id,
        form.items,
        form.discount_type,
        form.discount_percent,
        form.discount_amount,
        form.tax,
        form.shipping_cost,
        form.notes,
        form.fulfillment_type,
        form.fulfillment_warehouse_id
    ],
    () => {
        dirty.value = true;
        clearTimeout(draftTimer);
        draftTimer = setTimeout(saveDraft, 600);
    },
    { deep: true }
);

/* ── Load Existing Order to Edit ─────────────────────────────────── */

const loadOrder = async () => {
    loading.value = true;
    try {
        const { data } = await salesOrdersApi.getById(orderId);
        const order = data?.data || null;
        loadedOrder.value = order;
        if (!order) return;

        if (!EDITABLE_STATUSES.value.includes(order.status)) {
            isLocked.value = true;
            return;
        }

        form.customer_id = order.customer_id;
        selectedCustomer.value = order.customer || null;
        form.assigned_employee_id = order.assigned_employee_id || null;
        repFromCustomer.value = order.customer?.employee_id || null;
        if (order.assigned_employee) {
            const e = order.assigned_employee;
            ensureEmployeeOption(e.id, e.name || [e.first_name, e.last_name].filter(Boolean).join(' '));
        }
        customerOptions.value = order.customer ? [order.customer] : [];
        form.order_date = order.order_date ? String(order.order_date).slice(0, 10) : today();
        form.expected_delivery = order.expected_delivery ? String(order.expected_delivery).slice(0, 10) : '';
        form.fulfillment_type = order.fulfillment_type || 'ship';
        form.fulfillment_warehouse_id = order.fulfillment_warehouse_id || null;
        form.shipping_address = order.shipping_address || '';
        form.shipping_cost = toNum(order.shipping_cost);
        const savedSubtotal = toNum(order.subtotal);
        const savedDiscount = toNum(order.discount);
        if (order.discount_percent !== null && order.discount_percent !== undefined && Number(order.discount_percent) > 0) {
            form.discount_type = 'percent';
            form.discount_percent = toNum(order.discount_percent);
            form.discount_amount = savedDiscount;
        } else if (savedDiscount > 0) {
            form.discount_type = 'amount';
            form.discount_amount = savedDiscount;
            form.discount_percent = savedSubtotal > 0 ? Math.round((savedDiscount / savedSubtotal) * 10000) / 100 : 0;
        } else {
            form.discount_type = 'percent';
            form.discount_percent = 0;
            form.discount_amount = 0;
        }
        form.discount = savedDiscount;
        form.tax = toNum(order.tax);
        form.notes = order.notes || '';
        form.expenses = (order.expenses || []).map((exp) => ({
            id: exp.id || null,
            category: exp.category || 'shipping',
            description: exp.description || '',
            amount: toNum(exp.amount),
            status: exp.status || 'paid',
            notes: exp.notes || '',
        }));

        if (!form.expenses.length && toNum(order.shipping_cost) > 0) {
            form.expenses.push({
                category: 'shipping',
                description: t('quick_add_shipping') || 'شحن وتوصيل',
                amount: toNum(order.shipping_cost),
                status: 'paid',
                notes: '',
            });
        }

        form.items = (order.items || []).map((item) => {
            const unit = {
                ...pieceUnit(item.unit_name || item.product?.unit),
                id: item.product_unit_id || null,
                base_unit_multiplier: parseFloat(item.unit_multiplier) || 1,
            };
            const line = {
                key: ++lineSeq,
                product_id: item.product_id,
                product_variant_id: item.product_variant_id || null,
                name: item.product?.name_ar || item.product?.name_en || item.description || t('unknown_product'),
                sku: item.variant?.sku || item.product?.sku || '',
                variant_label: variantLabelOf(item.variant) || '',
                variant: item.variant ? variantFieldsOf(item.variant) : null,
                image: productImageSrc(item.product),
                quantity: toNum(item.quantity) || 1,
                unit,
                units: [unit],
                base_price: toNum(item.product?.price) || toNum(item.unit_price),
                price: toNum(item.unit_price),
                stock: toNum(item.product?.stock_quantity) || 0,
                discount: toNum(item.discount),
                tax: toNum(item.tax),
            };
            loadUnits(line, { keepSelection: true });
            return line;
        });
        preloadLineSiblings();

        await nextTick();
        dirty.value = false;
    } catch {
        ElMessage.error(t('failed_to_load_sales_order_for_edit'));
    } finally {
        loading.value = false;
    }
};

/* ── Save / Submit / Confirm ─────────────────────────────────────── */

const openOrder = (id) => {
    router.push({ path: '/admin/sales/sales-orders', query: { open: id, tab: 'execution' } });
};

const submit = async (andConfirm) => {
    if (blocker.value || saving.value) return;
    Object.keys(errors).forEach((k) => delete errors[k]);
    serverErrors.value = [];
    saving.value = andConfirm ? 'confirm' : 'draft';

    const payload = {
        customer_id: form.customer_id,
        assigned_employee_id: form.assigned_employee_id || null,
        order_date: form.order_date || null,
        expected_delivery: form.expected_delivery || null,
        fulfillment_type: form.fulfillment_type || 'ship',
        fulfillment_warehouse_id: form.fulfillment_warehouse_id || null,
        shipping_cost: toNum(form.shipping_cost),
        shipping_address: form.shipping_address || null,
        discount: form.discount_type === 'amount' ? toNum(form.discount_amount) : discountAmount.value,
        discount_percent: form.discount_type === 'percent' ? toNum(form.discount_percent) : null,
        tax: toNum(form.tax),
        notes: form.notes || null,
        items: form.items.map((line) => ({
            product_id: line.product_id,
            product_variant_id: line.product_variant_id || null,
            product_unit_id: line.unit?.id || null,
            quantity: toNum(line.quantity),
            unit_price: roundUnitPrice(line.price),
            discount: toNum(line.discount),
            tax: toNum(line.tax),
        })),
        expenses: (form.expenses || [])
            .filter((exp) => Number(exp.amount) > 0 || (exp.description && exp.description.trim()))
            .map((exp) => ({
                id: exp.id || null,
                category: exp.category || 'shipping',
                description: exp.description?.trim() || t('quick_add_shipping') || 'شحن وتوصيل',
                amount: toNum(exp.amount),
                status: exp.status || 'paid',
                notes: exp.notes || null,
            })),
        ...(andConfirm && !isEdit.value ? { execute: 'confirm' } : {}),
        ...(versioning.value && revisionNote.value.trim() ? { revision_note: revisionNote.value.trim() } : {}),
    };

    try {
        let orderIdToOpen = orderId;
        let execution = null;
        let updateMessage = null;

        if (isEdit.value) {
            const { data: updated } = await salesOrdersApi.update(orderId, payload);
            // A new version was made: say so, with its number.
            if (updated?.revision) updateMessage = updated.message;
            orderIdToOpen = orderId;
            if (andConfirm) {
                try {
                    await salesOrdersApi.confirm(orderId);
                    execution = { confirmed: true };
                } catch (error) {
                    execution = {
                        confirmed: false,
                        message: error.response?.data?.message,
                        shortages: error.response?.data?.data?.shortages || [],
                    };
                }
            }
        } else {
            const { data } = await salesOrdersApi.create(payload);
            orderIdToOpen = data?.data?.id;
            execution = data?.execution || null;
        }

        dirty.value = false;
        clearDraft();

        if (execution && !execution.confirmed) {
            const shown = await handleStockShortage(
                { response: { data: { data: { shortages: execution.shortages || [] } } } },
                orderIdToOpen,
            );
            if (!shown) {
                await ElMessageBox.alert(execution.message || t('so_saved_not_confirmed'), t('so_saved_as_draft'), { type: 'warning' }).catch(() => {});
            }
        } else {
            ElMessage.success(updateMessage || (execution?.confirmed
                ? t('so_saved_and_confirmed')
                : (isEdit.value ? t('sales_order_updated') : t('sales_order_created'))));
        }

        if (orderIdToOpen) {
            openOrder(orderIdToOpen);
        } else {
            router.push('/admin/sales/sales-orders');
        }
    } catch (error) {
        const fieldErrors = error.response?.data?.errors || {};
        if (Object.keys(fieldErrors).length) {
            Object.entries(fieldErrors).forEach(([key, messages]) => {
                errors[key] = Array.isArray(messages) ? messages[0] : String(messages);
            });
            serverErrors.value = Object.values(fieldErrors).flat();
        } else {
            const msg = error.response?.data?.message || error.message || t('failed_to_save_sales_order');
            serverErrors.value = [msg];
            ElMessage.error(msg);
        }
    } finally {
        saving.value = null;
    }
};

const clearOrder = async () => {
    try {
        await ElMessageBox.confirm(t('confirm_clear_all_items'), t('clear_the_form'), { type: 'warning' });
    } catch {
        return;
    }
    Object.assign(form, blank());
    selectedCustomer.value = null;
    customerOptions.value = [];
    justAddedCustomerId.value = null;
    closeQuickCustomer();
    clearDraft();
    ElMessage.success(t('form_cleared'));
};

const goBack = async () => {
    if (dirty.value && !isEdit.value && form.items.length) {
        try {
            await ElMessageBox.confirm(t('unsaved_order_warning'), t('leave_without_saving'), {
                confirmButtonText: t('leave'),
                cancelButtonText: t('stay'),
                type: 'warning',
            });
        } catch {
            return;
        }
    }
    router.push('/admin/sales/sales-orders');
};

/* ── Guards & Shortcuts ─────────────────────────────────────────── */

onBeforeRouteLeave(async () => {
    if (!dirty.value || isLocked.value || saving.value) return true;
    if (!isEdit.value) return true; // Draft is preserved
    try {
        await ElMessageBox.confirm(t('unsaved_order_warning'), t('leave_without_saving'), {
            type: 'warning',
            confirmButtonText: t('leave'),
            cancelButtonText: t('stay'),
        });
        return true;
    } catch {
        return false;
    }
});

const onBeforeUnload = (event) => {
    if (isEdit.value && dirty.value && !saving.value) {
        event.preventDefault();
        event.returnValue = '';
    }
};

const onKeydown = (event) => {
    // The new-product dialog owns the keyboard while it is open.
    if (isLocked.value || loading.value || quickProductVisible.value) return;
    if (event.key === 'F2') {
        event.preventDefault();
        focusCatalog();
    }
    if (event.key === 'F4' && !event.altKey) {
        event.preventDefault();
        openQuickProduct(quickSearchQuery.value);
    }
    if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
        event.preventDefault();
        trySubmit(false);
    }
};

onMounted(async () => {
    window.addEventListener('keydown', onKeydown);
    window.addEventListener('beforeunload', onBeforeUnload);
    await Promise.all([
        fetchWarehouses(),
        fetchCategories(),
        fetchSalesEmployees(),
    ]);

    if (orderId) {
        await loadOrder();
    } else {
        await offerDraft();
        const cid = route.query.customer_id ? Number(route.query.customer_id) : null;
        if (cid) {
            try {
                const res = await posApi.customerShow(cid);
                const cust = res.data?.data || res.data;
                if (cust) {
                    customerOptions.value = [cust];
                    form.customer_id = cust.id;
                    selectedCustomer.value = cust;
                    applyCustomerRep(cust);
                }
            } catch {
                /* ignore */
            }
        }
        searchCustomers('');
        setTimeout(() => focusCatalog(), 200);
    }
    // The catalogue beside the order starts with products in it.
    if (!isLocked.value) executeQuickSearch();
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown);
    window.removeEventListener('beforeunload', onBeforeUnload);
    clearTimeout(draftTimer);
    clearTimeout(customerTimer);
    clearTimeout(quickSearchDebounceTimer);
});
</script>

<style scoped>
/* Quick Purchase Dialog Styling */
.purchase-dialog-content {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.purchase-product-preview {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 0.75rem 1rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
}

.dialog-product-img {
    width: 64px;
    height: 64px;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    overflow: hidden;
    flex-shrink: 0;
}

.dialog-product-img :deep(img) {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.dialog-product-info {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    min-width: 0;
}

.dialog-product-name {
    margin: 0;
    font-size: 0.96rem;
    font-weight: 700;
    color: #1e293b;
    line-height: 1.4;
}

.dialog-product-meta {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    font-size: 0.75rem;
}

.sku-badge {
    background: #e2e8f0;
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
    font-family: monospace;
    color: #475569;
    font-weight: 600;
}

.purchase-dialog-form {
    margin-top: 0.25rem;
}

.purchase-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
}

.supplier-option-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
}

.dialog-footer-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.5rem;
}
</style>
