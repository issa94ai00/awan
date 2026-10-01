/**
 * The state behind the new / edit sales order page: the lines, the customer
 * and delivery, where each line ships from, and saving it.
 *
 * Kept out of the page so the four steps can be separate components that all
 * read and change one order. The page provides it; the steps inject it.
 */
import { computed, inject, provide, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import api from '@/api/index';
import { salesOrdersApi } from '@/api/salesOrders';
import { pickKey, optionKey, baseName, variantLabelOf } from '@/utils/productPick';
import { localIsoDate } from '@/utils/sales';

const KEY = Symbol('salesOrderForm');
const DRAFT_KEY = 'sales-order-draft';

export const STEPS = ['lines', 'customer', 'routing', 'review'];

const round2 = (n) => Math.round((Number(n) || 0) * 100) / 100;
// The local date: toISOString() is UTC, which names yesterday until 3am here.
const today = () => localIsoDate();

export function useSalesOrderForm() {
    return inject(KEY);
}

export function provideSalesOrderForm({ orderId = null } = {}) {
    const state = createSalesOrderForm({ orderId });
    provide(KEY, state);
    return state;
}

function createSalesOrderForm({ orderId }) {
    const { t } = useI18n();

    const isEdit = computed(() => !!orderId);

    /* ---------------------------------------------------------------- *
     * The order
     * ---------------------------------------------------------------- */

    const form = reactive({
        customer_id: null,
        // The chosen customer, kept whole: the picker searches the server, so
        // the list it shows may no longer hold them.
        customer: null,
        order_date: today(),
        expected_delivery: '',
        fulfillment_type: 'ship',
        shipping_address: '',
        // The API stores one shipping figure. The old page offered a list of
        // charges with descriptions and categories, and saved only their sum.
        shipping_cost: 0,
        // A discount and a tax are typed as an amount or a rate; the order
        // keeps the amount.
        discount_mode: 'amount',
        discount: 0,
        tax_mode: 'amount',
        tax: 0,
        notes: '',
    });

    const lines = ref([]);
    const step = ref(0);

    const loadedOrder = ref(null);
    const loading = ref(false);

    // Only a pending order can have its lines rewritten; the API refuses the
    // rest, so the page says so before anyone redoes the order.
    const isLocked = computed(() => isEdit.value && !!loadedOrder.value && loadedOrder.value.status !== 'pending');

    /* ---------------------------------------------------------------- *
     * Totals
     * ---------------------------------------------------------------- */

    const lineTotal = (line) => round2((Number(line.price) || 0) * (Number(line.quantity) || 0));
    const subtotal = computed(() => round2(lines.value.reduce((sum, line) => sum + lineTotal(line), 0)));
    const pieces = computed(() => lines.value.reduce((sum, line) => sum + (Number(line.quantity) || 0), 0));

    const discountAmount = computed(() => (form.discount_mode === 'percent'
        ? round2(subtotal.value * Math.min(100, Number(form.discount) || 0) / 100)
        : round2(form.discount)));
    // A rate of tax is struck on the goods after the discount.
    const taxAmount = computed(() => (form.tax_mode === 'percent'
        ? round2(Math.max(0, subtotal.value - discountAmount.value) * (Number(form.tax) || 0) / 100)
        : round2(form.tax)));
    const shipping = computed(() => round2(form.shipping_cost));
    const total = computed(() => round2(subtotal.value - discountAmount.value + taxAmount.value + shipping.value));

    /* ---------------------------------------------------------------- *
     * Lines
     * ---------------------------------------------------------------- */

    const pieceUnit = (name) => ({
        id: null, name: name || t('piece'), name_ar: name || t('piece'),
        base_unit_multiplier: 1, price_multiplier: 1, barcode: '',
    });

    /** Pieces a line takes from stock, whatever unit it is sold in. */
    const linePieces = (line) => (Number(line.quantity) || 0) * (Number(line.unit?.base_unit_multiplier) || 1);
    const overStock = (line) => linePieces(line) > (Number(line.stock) || 0);

    /** The catalogue price for the line's unit, before anyone typed over it. */
    const listPrice = (line) => round2((Number(line.base_price) || 0) * (Number(line.unit?.price_multiplier) || 1));
    const priceEdited = (line) => Math.abs((Number(line.price) || 0) - listPrice(line)) >= 0.005;
    const unitCost = (line) => round2((Number(line.cost_price) || 0) * (Number(line.unit?.base_unit_multiplier) || 1));
    const belowCost = (line) => unitCost(line) > 0 && (Number(line.price) || 0) < unitCost(line);

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

            line.units = units;
            if (keepSelection) {
                const saved = units.find((u) => u.id === line.unit?.id);
                if (saved) line.unit = saved;
                return;
            }
            line.unit = units.find((u) => u.is_default) || units[0];
            line.price = listPrice(line);
        } catch {
            // The line keeps its piece unit; nothing else depends on the list.
        }
    };

    /**
     * A search result onto the order. A second pick of the same product (and
     * size) adds one to its line; another size is a line of its own.
     * Returns the line it landed on.
     *
     * The line picked last goes first, right under the search box, so it is
     * in view without scrolling a long order — a repeat pick moves its line
     * back up too. The order is saved as it is listed.
     */
    const addProduct = (option) => {
        const key = optionKey(option);
        const existing = lines.value.find((line) => line.key === key);
        if (existing) {
            existing.quantity = (Number(existing.quantity) || 0) + 1;
            lines.value = [existing, ...lines.value.filter((line) => line !== existing)];
            return existing;
        }

        const unit = pieceUnit(option.unit);
        const line = reactive({
            key,
            product_id: option.id,
            product_variant_id: option.variant_id || null,
            variant_label: option.variant_label || '',
            name: baseName(option) || option.name_en || '',
            sku: option.sku || '',
            image: option.image_main || null,
            base_price: parseFloat(option.price) || 0,
            cost_price: parseFloat(option.cost_price) || 0,
            price: parseFloat(option.price) || 0,
            quantity: 1,
            stock: Number(option.stock_quantity) || 0,
            unit,
            units: [unit],
            allocations: [],
        });
        lines.value.unshift(line);
        loadUnits(line);
        return line;
    };

    const removeLine = (line) => {
        lines.value = lines.value.filter((l) => l !== line);
    };

    const setUnit = (line, unit) => {
        line.unit = unit;
        line.price = listPrice(line);
    };

    /* ---------------------------------------------------------------- *
     * Customer
     * ---------------------------------------------------------------- */

    const setCustomer = (customer) => {
        form.customer = customer || null;
        form.customer_id = customer?.id || null;
        // Their address, unless one was already typed for this order.
        if (customer?.address && !form.shipping_address) form.shipping_address = customer.address;
    };

    /** What the customer would owe with this order, against their limit. */
    const creditCheck = computed(() => {
        const c = form.customer;
        if (!c) return null;
        const owes = Number(c.balance) || 0;
        const limit = Number(c.credit_limit) || 0;
        const after = round2(owes + total.value);
        return { owes, limit, after, over: limit > 0 && after > limit };
    });

    /* ---------------------------------------------------------------- *
     * Routing: which warehouse fills each line
     *
     * Planned from a server suggestion the seller edits. Stock is counted per
     * product, and two sizes of one product draw on the same stock, so what a
     * warehouse has free for a line is what it holds less what the order's
     * other lines of that product already take from it there.
     * ---------------------------------------------------------------- */

    const routing = reactive({
        mode: 'plan',
        loading: false,
        warehouses: [],
        // product id => { warehouse id => free units before this order }
        available: {},
    });

    const allocated = (line) => (line.allocations || []).reduce((sum, a) => sum + (Number(a.quantity) || 0), 0);

    const freeFor = (line, warehouseId, exceptIndex = -1) => {
        const free = Number(routing.available[line.product_id]?.[warehouseId] ?? 0);
        const taken = lines.value.reduce((sum, other) => {
            if (other.product_id !== line.product_id) return sum;
            return sum + (other.allocations || []).reduce((s, a, i) => {
                if (a.warehouse_id !== warehouseId || (other === line && i === exceptIndex)) return s;
                return s + (Number(a.quantity) || 0);
            }, 0);
        }, 0);
        return Math.max(0, free - taken);
    };

    /** ok: placed in full from stock that is there · short: placed, a source lacks stock · open: not all placed. */
    const routingState = (line) => {
        if (allocated(line) !== Number(line.quantity)) return 'open';
        const short = (line.allocations || []).some((a, i) => a.warehouse_id && Number(a.quantity) > freeFor(line, a.warehouse_id, i));
        return short ? 'short' : 'ok';
    };

    const warehouseName = (id) => routing.warehouses.find((w) => w.id === id)?.name || (id ? `#${id}` : '—');
    const warehousesUsed = computed(() => new Set(lines.value.flatMap((line) => (line.allocations || []).map((a) => a.warehouse_id)).filter(Boolean)));

    const coversAll = (warehouseId) => {
        const need = {};
        // In the unit sold, as freeFor and the server's own check count it.
        lines.value.forEach((line) => { need[line.product_id] = (need[line.product_id] || 0) + (Number(line.quantity) || 0); });
        return Object.entries(need).every(([productId, qty]) => Number(routing.available[productId]?.[warehouseId] ?? 0) >= qty);
    };

    /**
     * Asks the server for a plan. A line already planned in full keeps its
     * plan unless `force`, so going back to change the customer does not undo
     * the seller's edits; a line whose quantity changed is planned again.
     */
    const suggestRouting = async (force = false) => {
        if (!lines.value.length) return;
        routing.loading = true;
        try {
            const { data } = await salesOrdersApi.suggestRouting({
                items: lines.value.map((line) => ({ product_id: line.product_id, quantity: Number(line.quantity) || 1 })),
            });
            const plan = data?.data || {};
            routing.warehouses = plan.warehouses || [];
            const available = {};
            (plan.lines || []).forEach((l) => { available[l.product_id] = l.available || {}; });
            routing.available = available;

            lines.value.forEach((line, index) => {
                const suggestion = plan.lines?.[index];
                if (!suggestion) return;
                if (!force && line.allocations.length && allocated(line) === Number(line.quantity)) return;

                const allocations = (suggestion.allocations || []).map((a) => ({ warehouse_id: a.warehouse_id, quantity: a.quantity }));
                // What no warehouse can cover still needs a source for the plan
                // to add up: it goes on the first one, and shows as short.
                if (suggestion.shortfall > 0) {
                    if (allocations.length) allocations[0].quantity += suggestion.shortfall;
                    else if (plan.preferred_warehouse_id) allocations.push({ warehouse_id: plan.preferred_warehouse_id, quantity: suggestion.shortfall });
                }
                line.allocations = allocations;
            });
        } catch (error) {
            ElMessage.error(error.response?.data?.message || t('so_routing_failed'));
        } finally {
            routing.loading = false;
        }
    };

    const routeAllTo = (warehouseId) => {
        if (!warehouseId) return;
        lines.value.forEach((line) => { line.allocations = [{ warehouse_id: warehouseId, quantity: Number(line.quantity) }]; });
    };

    const addSource = (line) => {
        const used = new Set(line.allocations.map((a) => a.warehouse_id));
        const next = routing.warehouses
            .filter((w) => !used.has(w.id))
            .sort((a, b) => freeFor(line, b.id) - freeFor(line, a.id))[0];
        const remaining = Number(line.quantity) - allocated(line);
        line.allocations.push({ warehouse_id: next?.id ?? null, quantity: Math.max(1, remaining) });
    };

    const removeSource = (line, index) => {
        line.allocations.splice(index, 1);
    };

    const sourcesText = (line) => {
        if (routing.mode !== 'plan' || !line.allocations?.length) return t('so_routing_at_confirmation');
        return line.allocations.map((a) => `${warehouseName(a.warehouse_id)} × ${a.quantity}`).join(' + ');
    };

    /* ---------------------------------------------------------------- *
     * What stands between each step and the next
     *
     * One list per step, read by the summary panel and by "next": the button
     * says what is missing instead of greying out, and the same words show
     * beside it.
     * ---------------------------------------------------------------- */

    const issuesFor = (index) => {
        const issues = [];
        if (index === 0) {
            if (!lines.value.length) issues.push({ field: 'lines', text: t('add_item_before_next_step') });
            if (lines.value.some((line) => !(Number(line.quantity) >= 1) || !Number.isInteger(Number(line.quantity)))) {
                issues.push({ field: 'lines', text: t('sof_quantity_whole') });
            }
        }
        if (index === 1) {
            if (!form.customer_id) issues.push({ field: 'customer', text: t('choose_customer_before_final_step') });
            if (form.expected_delivery && form.order_date && form.expected_delivery <= form.order_date) {
                issues.push({ field: 'expected_delivery', text: t('sof_delivery_after_order') });
            }
            if (discountAmount.value > subtotal.value + taxAmount.value + shipping.value) {
                issues.push({ field: 'discount', text: t('sof_discount_too_big') });
            }
        }
        // Counted while a suggestion loads too, so "next" cannot slip past it.
        if (index === 2 && routing.mode === 'plan') {
            const open = lines.value.filter((line) => routingState(line) === 'open').length;
            if (open) issues.push({ field: 'routing', text: t('so_routing_incomplete', { n: open }) });
            if (form.fulfillment_type === 'pickup' && warehousesUsed.value.size > 1) {
                issues.push({ field: 'routing', text: t('so_pickup_one_branch') });
            }
        }
        return issues;
    };

    /** The first step, up to `target`, that is not ready to be left. */
    const firstBlockedBefore = (target) => {
        for (let i = 0; i < target; i++) {
            if (issuesFor(i).length) return i;
        }
        return -1;
    };

    // Set when "next" was pressed on a step that was not ready; its fields
    // show their problem from then on, not before anyone has touched them.
    const attempted = reactive({ 0: false, 1: false, 2: false });

    const goTo = (target) => {
        if (target <= step.value) {
            step.value = target;
            return true;
        }
        const blocked = firstBlockedBefore(target);
        if (blocked !== -1) {
            attempted[blocked] = true;
            step.value = blocked;
            ElMessage.warning(issuesFor(blocked)[0].text);
            return false;
        }
        step.value = target;
        return true;
    };

    // However the routing step is reached (or stopped at, when a jump ahead
    // finds it unfinished), it opens with a suggestion to edit.
    watch(step, (now) => {
        if (now === 2 && routing.mode === 'plan') suggestRouting(false);
    });

    /* ---------------------------------------------------------------- *
     * Draft kept in this browser
     *
     * Saved as the order is built, not on request: the old page only kept a
     * draft when its menu item was found and clicked.
     * ---------------------------------------------------------------- */

    const dirty = ref(false);
    let tracking = false;
    let draftTimer = null;
    const draftSavedAt = ref(null);

    const snapshot = () => ({
        saved_at: new Date().toISOString(),
        step: step.value,
        form: { ...form },
        lines: lines.value.map((line) => ({ ...line, units: [line.unit].filter(Boolean) })),
        routing_mode: routing.mode,
    });

    const saveDraft = () => {
        if (isEdit.value) return;
        // Every line removed: nothing left worth offering back next time.
        if (!lines.value.length) {
            clearDraft();
            return;
        }
        try {
            localStorage.setItem(DRAFT_KEY, JSON.stringify(snapshot()));
            draftSavedAt.value = new Date();
        } catch {
            // Storage full or blocked: the order is still on screen.
        }
    };

    const clearDraft = () => {
        try { localStorage.removeItem(DRAFT_KEY); } catch { /* storage unavailable */ }
        draftSavedAt.value = null;
    };

    watch([lines, form, () => routing.mode], () => {
        if (!tracking) return;
        dirty.value = true;
        clearTimeout(draftTimer);
        draftTimer = setTimeout(saveDraft, 600);
    }, { deep: true });

    /** Lines saved by the page before this one: `pick` for the key, and charges as a list. */
    const fromDraftLine = (line) => {
        // The old page kept the chosen unit in `selectedUnit`, and `unit` was
        // the product's unit name as a string.
        const unit = line.selectedUnit
            || (line.unit && typeof line.unit === 'object' ? line.unit : null)
            || pieceUnit(typeof line.unit === 'string' ? line.unit : line.unit_name);
        return reactive({
            ...line,
            key: line.key || line.pick,
            unit,
            units: line.units?.length ? line.units : [unit],
            allocations: line.allocations || [],
        });
    };

    const offerDraft = async () => {
        let draft = null;
        try { draft = JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null'); } catch { draft = null; }
        const draftLines = draft?.lines || draft?.items;
        if (!draftLines?.length) return;

        try {
            await ElMessageBox.confirm(
                t('so_restore_draft_message', { count: draftLines.length }),
                t('so_restore_draft_title'),
                { type: 'info', confirmButtonText: t('so_restore_draft'), cancelButtonText: t('so_discard_draft'), distinguishCancelAndClose: true },
            );
        } catch (action) {
            if (action === 'cancel') clearDraft();
            return;
        }

        const saved = draft.form || {};
        const legacyCharges = (saved.expenses || []).reduce((sum, e) => sum + (Number(e.amount) || 0), 0);
        Object.assign(form, {
            ...saved,
            shipping_cost: saved.shipping_cost ?? legacyCharges,
            customer: saved.customer || null,
        });
        delete form.expenses;
        lines.value = draftLines.map(fromDraftLine);
        routing.mode = draft.routing_mode || 'plan';
        lines.value.forEach((line) => loadUnits(line, { keepSelection: true }));
        // A draft from the old page knows the customer only by id.
        if (form.customer_id && !form.customer) {
            try {
                const { data } = await api.get(`/pos/customers/${form.customer_id}`);
                form.customer = data?.data || null;
            } catch { /* the id is still sent */ }
        }
    };

    /* ---------------------------------------------------------------- *
     * Loading an order to edit
     * ---------------------------------------------------------------- */

    const loadOrder = async () => {
        loading.value = true;
        try {
            const { data } = await salesOrdersApi.getById(orderId);
            const order = data?.data || null;
            loadedOrder.value = order;
            if (!order || order.status !== 'pending') return;

            Object.assign(form, {
                customer_id: order.customer_id,
                customer: order.customer || null,
                // The API sends dates as full ISO timestamps.
                order_date: order.order_date ? String(order.order_date).slice(0, 10) : today(),
                expected_delivery: order.expected_delivery ? String(order.expected_delivery).slice(0, 10) : '',
                fulfillment_type: order.fulfillment_type || 'ship',
                shipping_address: order.shipping_address || '',
                shipping_cost: parseFloat(order.shipping_cost) || 0,
                discount_mode: 'amount',
                discount: parseFloat(order.discount) || 0,
                tax_mode: 'amount',
                tax: parseFloat(order.tax) || 0,
                notes: order.notes || '',
            });

            lines.value = (order.items || []).map((item) => {
                const unit = {
                    ...pieceUnit(item.unit_name || item.product?.unit),
                    id: item.product_unit_id || null,
                    base_unit_multiplier: parseFloat(item.unit_multiplier) || 1,
                };
                return reactive({
                    key: pickKey(item.product_id, item.product_variant_id),
                    product_id: item.product_id,
                    product_variant_id: item.product_variant_id || null,
                    variant_label: variantLabelOf(item.variant) || '',
                    name: item.product?.name_ar || item.product?.name_en || item.description || t('unknown_product'),
                    sku: item.variant?.sku || item.product?.sku || '',
                    image: item.product?.image_main || null,
                    // The catalogue price now, so a line sold at another price
                    // shows what the list says beside it.
                    base_price: parseFloat(item.product?.price) || parseFloat(item.unit_price) || 0,
                    cost_price: parseFloat(item.product?.cost_price) || 0,
                    price: parseFloat(item.unit_price) || 0,
                    quantity: Number(item.quantity) || 1,
                    stock: Number(item.product?.stock_quantity) || 0,
                    unit,
                    units: [unit],
                    allocations: (item.allocations || []).map((a) => ({ warehouse_id: a.warehouse_id, quantity: a.quantity })),
                });
            });
            lines.value.forEach((line) => loadUnits(line, { keepSelection: true }));
            routing.mode = lines.value.some((line) => line.allocations.length) ? 'plan' : 'later';
        } catch {
            ElMessage.error(t('failed_to_load_sales_order_for_edit'));
        } finally {
            loading.value = false;
        }
    };

    /** Called once the page has its starting point; changes after this count. */
    const startTracking = () => {
        dirty.value = false;
        tracking = true;
    };

    /* ---------------------------------------------------------------- *
     * Saving
     * ---------------------------------------------------------------- */

    const submitting = ref(null); // null | 'draft' | 'confirm'
    const serverErrors = ref([]);

    const payload = (execute) => ({
        customer_id: form.customer_id,
        order_date: form.order_date || null,
        expected_delivery: form.expected_delivery || null,
        fulfillment_type: form.fulfillment_type,
        shipping_address: form.shipping_address || null,
        shipping_cost: shipping.value,
        discount: discountAmount.value,
        tax: taxAmount.value,
        notes: form.notes || null,
        items: lines.value.map((line) => ({
            product_id: line.product_id,
            product_variant_id: line.product_variant_id || null,
            quantity: Number(line.quantity),
            unit_price: round2(line.price),
            product_unit_id: line.unit?.id || null,
            // The plan from the routing step; left out to route at confirmation.
            // A plan that no longer adds up to the quantity (changed since)
            // would be refused whole; such a line routes at confirmation.
            allocations: routing.mode === 'plan' && line.allocations?.length && allocated(line) === Number(line.quantity)
                ? line.allocations
                    .filter((a) => a.warehouse_id && Number(a.quantity) > 0)
                    .map((a) => ({ warehouse_id: a.warehouse_id, quantity: Number(a.quantity) }))
                : undefined,
        })),
        ...(execute && !isEdit.value ? { execute } : {}),
    });

    /**
     * Saves the order, and confirms it when asked. Resolves to
     * { id, execution } on save, or null when nothing was saved.
     */
    const submit = async ({ confirm = false } = {}) => {
        serverErrors.value = [];
        const blocked = firstBlockedBefore(confirm ? 3 : (isEdit.value ? 1 : 2));
        if (blocked !== -1) {
            goTo(3);
            return null;
        }

        submitting.value = confirm ? 'confirm' : 'draft';
        try {
            let id = orderId;
            let execution = null;
            if (isEdit.value) {
                await salesOrdersApi.update(orderId, payload(null));
                if (confirm) {
                    // Confirmed through the endpoint the order screen uses, so
                    // a refusal reads the same.
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
                const { data } = await salesOrdersApi.create(payload(confirm ? 'confirm' : null));
                id = data?.data?.id;
                execution = data?.execution || null;
            }
            dirty.value = false;
            tracking = false;
            clearTimeout(draftTimer);
            clearDraft();
            return { id, execution };
        } catch (error) {
            const errors = error.response?.data?.errors;
            serverErrors.value = errors
                ? Object.values(errors).flat()
                : [error.response?.data?.message || error.message || t('failed_to_save_sales_order')];
            return null;
        } finally {
            submitting.value = null;
        }
    };

    const reset = () => {
        lines.value = [];
        Object.assign(form, {
            customer_id: null, customer: null, order_date: today(), expected_delivery: '',
            fulfillment_type: 'ship', shipping_address: '', shipping_cost: 0,
            discount_mode: 'amount', discount: 0, tax_mode: 'amount', tax: 0, notes: '',
        });
        Object.assign(attempted, { 0: false, 1: false, 2: false });
        step.value = 0;
        clearDraft();
    };

    return {
        isEdit, isLocked, loadedOrder, loading,
        form, lines, step, attempted,
        subtotal, pieces, discountAmount, taxAmount, shipping, total, lineTotal,
        linePieces, overStock, listPrice, priceEdited, unitCost, belowCost,
        addProduct, removeLine, setUnit,
        setCustomer, creditCheck,
        routing, allocated, freeFor, routingState, warehouseName, warehousesUsed, coversAll,
        suggestRouting, routeAllTo, addSource, removeSource, sourcesText,
        issuesFor, goTo,
        dirty, draftSavedAt, offerDraft, clearDraft, loadOrder, startTracking,
        submitting, serverErrors, submit, reset,
    };
}
