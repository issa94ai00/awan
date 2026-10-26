<template>
    <div class="purchase-request-show">
        <el-page-header @back="goBack" :title="$t('return')" style="margin-bottom:1.5rem">
            <template #content>
                <span class="text-large font-600 mr-3">{{ $t('purchase_order_details') }} {{ order?.order_number }}</span>
            </template>
        </el-page-header>

        <div v-loading="loading">
            <template v-if="order">
                <el-card shadow="hover" style="margin-bottom:1.5rem">
                    <div style="display:flex; align-items:center; gap:1rem; flex-wrap:wrap;">
                        <h2 style="margin:0; flex:1;">{{ order.order_number }}</h2>
                        <el-tag :type="statusTagType(order.status)" size="large">{{ order.status_text }}</el-tag>
                        <!-- The next step, carried out on the order screen (see
                             executeStage); only moves the workflow allows. -->
                        <el-button
                            v-if="nextForward"
                            type="primary"
                            size="small"
                            @click="executeStage(nextForward)"
                        >
                            {{ stageActionLabel(nextForward) }}
                        </el-button>
                        <el-dropdown v-if="allowedTransitions.length" @command="executeStage" trigger="click">
                            <el-button size="small">
                                {{ $t('change_status') }} <el-icon><ArrowDown /></el-icon>
                            </el-button>
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item
                                        v-for="stage in allowedTransitions"
                                        :key="stage"
                                        :command="stage"
                                        :divided="stage === 'cancelled'"
                                    >
                                        {{ stageActionLabel(stage) }}
                                    </el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>
                        <el-button size="small" plain @click="openOrder">
                            <i class="fas fa-route"></i>&nbsp;{{ $t('so_open_in_orders') }}
                        </el-button>
                    </div>
                </el-card>

                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-card shadow="hover" style="margin-bottom:1.5rem">
                            <template #header><span>{{ $t('customer_information') }}</span></template>
                            <div v-if="order.customer">
                                <el-descriptions :column="1" border>
                                    <el-descriptions-item :label="$t('name')">{{ order.customer.name }}</el-descriptions-item>
                                    <el-descriptions-item :label="$t('phone')">{{ order.customer.phone }}</el-descriptions-item>
                                    <el-descriptions-item :label="$t('mail')">
                                        {{ order.customer.email || '-' }}
                                    </el-descriptions-item>
                                    <el-descriptions-item :label="$t('address')">
                                        {{ order.customer.address || '-' }}
                                    </el-descriptions-item>
                                </el-descriptions>
                            </div>
                            <span v-else>-</span>
                        </el-card>
                    </el-col>
                    <el-col :span="12">
                        <el-card shadow="hover" style="margin-bottom:1.5rem">
                            <template #header><span>{{ $t('order_information') }}</span></template>
                            <el-descriptions :column="1" border>
                                <el-descriptions-item :label="$t('order_number')">{{ order.order_number }}</el-descriptions-item>
                                <el-descriptions-item :label="$t('the_date')">{{ order.order_date || '-' }}</el-descriptions-item>
                                <el-descriptions-item :label="$t('status')">
                                    <el-tag :type="statusTagType(order.status)" size="small">{{ order.status_text }}</el-tag>
                                </el-descriptions-item>
                                <el-descriptions-item :label="$t('creation_date')">{{ order.created_at || '-' }}</el-descriptions-item>
                            </el-descriptions>
                        </el-card>
                    </el-col>
                </el-row>

                <el-card shadow="hover" class="items-card" style="margin-bottom:1.5rem">
                    <template #header>
                        <div class="items-head">
                            <span>
                                {{ $t('products') }}
                                <small class="items-count">{{ $t('pr_lines_count', (order.items || []).length) }}</small>
                            </span>
                            <!-- A customer often asks for the wrong size. While the
                                 request is still pending, the lines can be corrected
                                 here; once confirmed, stock is reserved against them
                                 and the API refuses the change. -->
                            <div class="items-head-actions">
                                <template v-if="editing">
                                    <el-button size="small" :disabled="saving" @click="cancelEdit">{{ $t('cancel') }}</el-button>
                                    <el-button size="small" type="primary" :loading="saving" :disabled="!draft.length" @click="saveItems">
                                        {{ $t('save_changes') }}
                                    </el-button>
                                </template>
                                <el-button v-else-if="canEditItems" size="small" plain @click="startEdit">
                                    <i class="fas fa-pen"></i>&nbsp;{{ $t('pr_edit_items') }}
                                </el-button>
                                <small v-else class="items-locked">{{ $t('pr_items_locked') }}</small>
                            </div>
                        </div>
                    </template>

                    <template v-if="!editing">
                        <el-table v-if="order.items && order.items.length" :data="order.items" stripe style="width:100%">
                            <el-table-column label="#" type="index" width="50" />
                            <el-table-column :label="$t('product_name')" min-width="260">
                                <template #default="{ row }">
                                    <div class="line-cell">
                                        <EntityImage :src="imageOf(row.product)" type="product" :size="40" shape="square" />
                                        <div class="line-text">
                                            <strong>{{ lineName(row) }}</strong>
                                            <div class="line-meta">
                                                <VariantChip v-if="row.variant_id" :label="row.variant_label" />
                                                <code v-if="row.sku">{{ row.sku }}</code>
                                                <span
                                                    v-if="stockOf(row) != null"
                                                    class="stock-pill"
                                                    :class="{ 'is-short': stockOf(row) < Number(row.quantity) }"
                                                >
                                                    {{ $t('available') }}: {{ stockOf(row) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </el-table-column>
                            <el-table-column prop="quantity" :label="$t('quantity')" width="90" />
                            <el-table-column :label="$t('unit_price')" width="130">
                                <template #default="{ row }">{{ formatCurrency(row.unit_price) }}</template>
                            </el-table-column>
                            <el-table-column :label="$t('total')" width="130">
                                <template #default="{ row }">{{ formatCurrency(row.total) }}</template>
                            </el-table-column>
                            <el-table-column :label="$t('sales.source_warehouse')" min-width="170">
                                <template #default="{ row }">
                                    <span v-if="row.allocations?.length" class="alloc-list">
                                        <span v-for="a in row.allocations" :key="a.warehouse_id" class="alloc-pill">
                                            {{ a.warehouse_name || '—' }} × {{ a.quantity }}
                                        </span>
                                    </span>
                                    <span v-else class="text-muted">—</span>
                                </template>
                            </el-table-column>
                        </el-table>
                        <span v-else>-</span>
                    </template>

                    <div v-else class="items-editor">
                        <p class="editor-hint"><i class="fas fa-circle-info"></i> {{ $t('pr_edit_hint') }}</p>

                        <div v-for="(line, idx) in draft" :key="line.key" class="draft-line">
                            <EntityImage :src="line.image" type="product" :size="40" shape="square" />
                            <div class="draft-text">
                                <strong>{{ line.name }}</strong>
                                <div class="line-meta">
                                    <VariantChip v-if="line.variant_id" :label="line.variant_label" />
                                    <code v-if="line.sku">{{ line.sku }}</code>
                                    <span class="price-tag">{{ formatCurrency(line.unit_price) }}</span>
                                    <span v-if="line.stock != null" class="stock-pill" :class="{ 'is-short': line.stock < Number(line.quantity) }">
                                        {{ $t('available') }}: {{ line.stock }}
                                    </span>
                                </div>
                                <!-- The product's other sizes, one click away. -->
                                <div v-if="siblingsOf(line.product_id).length > 1" class="size-switch">
                                    <span class="switch-label"><i class="fas fa-tag"></i> {{ $t('po_switch_size') }}</span>
                                    <button
                                        v-for="v in siblingsOf(line.product_id)"
                                        :key="optionKey(v)"
                                        type="button"
                                        class="switch-chip"
                                        :class="{ 'is-active': v.variant_id === line.variant_id, 'is-out': v.stock_quantity != null && Number(v.stock_quantity) <= 0 }"
                                        :title="[v.sku, formatCurrency(listPrice(v))].filter(Boolean).join(' · ')"
                                        @click="switchLineVariant(line, v)"
                                    >
                                        <span dir="ltr">{{ v.variant_label || v.sku }}</span>
                                        <small v-if="v.stock_quantity != null">{{ v.stock_quantity }}</small>
                                    </button>
                                </div>
                            </div>
                            <el-input-number v-model="line.quantity" :min="1" :step="1" step-strictly size="small" class="draft-qty" />
                            <el-button text type="danger" :title="$t('remove')" @click="removeLine(idx)">
                                <i class="fas fa-trash"></i>
                            </el-button>
                        </div>

                        <div v-if="!draft.length" class="draft-empty">{{ $t('pr_no_lines') }}</div>

                        <!-- Adds a product, or one size of it; sizes are listed under
                             their product so the right one is picked, not guessed. -->
                        <el-select
                            v-model="addPick"
                            filterable
                            remote
                            reserve-keyword
                            clearable
                            :remote-method="searchProducts"
                            :loading="searchLoading"
                            :placeholder="$t('po_select_item_or_variant')"
                            class="draft-add"
                            @change="addPicked"
                        >
                            <template v-for="g in searchGroups" :key="g.key">
                                <el-option-group v-if="g.hasVariants" :label="g.name">
                                    <el-option v-for="row in g.rows" :key="optionKey(row)" :value="optionKey(row)" :label="`${g.name} - ${row.variant_label}`">
                                        <span class="opt-row">
                                            <span class="opt-size" dir="ltr">{{ row.variant_label }}</span>
                                            <code v-if="row.sku">{{ row.sku }}</code>
                                            <span class="opt-side">
                                                <span>{{ formatCurrency(listPrice(row)) }}</span>
                                                <span v-if="row.stock_quantity != null" :class="Number(row.stock_quantity) > 0 ? 'ok' : 'out'">{{ $t('available') }}: {{ row.stock_quantity }}</span>
                                            </span>
                                        </span>
                                    </el-option>
                                </el-option-group>
                                <el-option v-else :value="optionKey(g.product)" :label="g.name">
                                    <span class="opt-row">
                                        <span class="opt-name">{{ g.name }}</span>
                                        <code v-if="g.product.sku">{{ g.product.sku }}</code>
                                        <span class="opt-side">
                                            <span>{{ formatCurrency(listPrice(g.product)) }}</span>
                                            <span v-if="g.product.stock_quantity != null" :class="Number(g.product.stock_quantity) > 0 ? 'ok' : 'out'">{{ $t('available') }}: {{ g.product.stock_quantity }}</span>
                                        </span>
                                    </span>
                                </el-option>
                            </template>
                        </el-select>
                    </div>

                    <div class="items-total">
                        {{ $t('total') }}: {{ formatCurrency(editing ? draftTotal : order.total) }}
                    </div>
                </el-card>

                <el-row :gutter="20">
                    <el-col :span="12">
                        <el-card shadow="hover" style="margin-bottom:1.5rem">
                            <template #header><span>{{ $t('associated_invoices') }}</span></template>
                            <div v-if="order.invoices && order.invoices.length">
                                <div v-for="inv in order.invoices" :key="inv.id"
                                    style="padding:10px; margin-bottom:8px; background:#f9fafb; border-radius:8px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                                    <div>
                                        <strong>{{ inv.invoice_number }}</strong>
                                        <el-tag :type="inv.status === 'paid' ? 'success' : 'warning'" size="small" style="margin-right:8px">
                                            {{ inv.status === 'paid' ? $t('paid') : $t('on_hold') }}
                                        </el-tag>
                                    </div>
                                    <span style="font-weight:700;">{{ formatCurrency(inv.total) }}</span>
                                </div>
                            </div>
                            <span v-else>-</span>
                        </el-card>
                    </el-col>
                    <el-col :span="12">
                        <el-card shadow="hover" style="margin-bottom:1.5rem">
                            <template #header><span>{{ $t('comments') }}</span></template>
                            <p v-if="order.notes" style="white-space:pre-wrap;">{{ order.notes }}</p>
                            <span v-else>-</span>
                        </el-card>
                    </el-col>
                </el-row>
            </template>

            <div v-if="!order && !loading" class="empty-state">
                <p>{{ $t('purchase_order_not_found') }}</p>
                <el-button @click="goBack">{{ $t('return') }}</el-button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { formatCurrency } from '@/utils/sales';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { usePurchaseRequestsStore } from '@/stores/purchaseRequests';
import { ElMessage } from 'element-plus';
import { ArrowDown } from '@element-plus/icons-vue';
import { posApi } from '@/api/pos';
import { optionKey, baseName } from '@/utils/productPick';
import { resolveImageUrl } from '@/utils/productImages';
import { useVariantSiblings } from '@/composables/useVariantSiblings';
import EntityImage from '@/components/admin/EntityImage.vue';
import VariantChip from '@/components/admin/products/VariantChip.vue';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const store = usePurchaseRequestsStore();

const canApprove = computed(() => Boolean(authStore.isSuperAdmin || authStore.user?.is_admin || authStore.hasRole(['admin', 'master'])));

const order = ref(null);
const loading = ref(true);

const statusTagType = (status) => {
    const map = {
        pending: 'warning',
        confirmed: 'primary',
        processing: 'info',
        shipped: 'success',
        delivered: 'success',
        cancelled: 'danger',
    };
    return map[status] || 'info';
};

const goBack = () => {
    router.push({ name: 'admin.purchase-requests.index' });
};

const { t } = useI18n();

const stageActionLabel = (stage) => ({
    confirmed: t('confirm_order'),
    processing: t('start_preparation'),
    shipped: t('confirm_shipping'),
    delivered: t('deliver_and_settle_action'),
    cancelled: t('cancel_the_request'),
}[stage] || stage);

const allowedTransitions = computed(() => {
    const list = order.value?.allowed_transitions || [];
    if (!canApprove.value) {
        return list.filter((stage) => stage !== 'confirmed');
    }
    return list;
});

/** The forward move from here — cancelling is an exit, not the next step. */
const nextForward = computed(() => allowedTransitions.value.find((stage) => stage !== 'cancelled') || null);

/**
 * A request is a sales order, moved where every sales order is: the order
 * screen opens on its execution tab and starts the step, with its dialogs,
 * stock checks and reasons. Setting the status from here skipped all of that
 * and left the page showing the old status text.
 */
const executeStage = (stage) => {
    if (stage === 'confirmed' && !canApprove.value) {
        ElMessage.error(t('only_admin_can_approve') || 'فقط المسؤول يمكنه اعتماد طلب الشراء');
        return;
    }
    router.push({ path: '/admin/sales/sales-orders', query: { open: order.value.id, tab: 'execution', do: stage } });
};

/* ── Lines ─────────────────────────────────────────────────────── */

const lineName = (item) => item.product?.name_ar || item.product_name;
const imageOf = (product) => resolveImageUrl(product?.image_main || '');
const stockOf = (item) => item.variant?.stock_quantity ?? item.product?.stock_quantity ?? null;
const listPrice = (p) => Number(p.has_sale && p.sale_price ? p.sale_price : p.price) || 0;

// Only a pending request can have its lines changed; confirming reserves
// stock against them, and the API refuses the edit after that.
const canEditItems = computed(() => order.value?.status === 'pending');

const editing = ref(false);
const saving = ref(false);
const draft = ref([]);
let draftSeq = 0;

const { rememberVariantRows, ensureVariantsLoaded, siblingsOf } = useVariantSiblings();

const startEdit = () => {
    draft.value = (order.value.items || []).map((item) => ({
        key: ++draftSeq,
        product_id: item.product_id,
        variant_id: item.variant_id || null,
        variant_label: item.variant_label || '',
        name: lineName(item),
        sku: item.sku || '',
        image: imageOf(item.product),
        quantity: Number(item.quantity) || 1,
        unit_price: Number(item.unit_price) || 0,
        stock: stockOf(item),
    }));
    draft.value.forEach((line) => line.variant_id && ensureVariantsLoaded(line.product_id));
    editing.value = true;
};

const cancelEdit = () => {
    editing.value = false;
    draft.value = [];
    addPick.value = '';
};

const draftTotal = computed(() => draft.value.reduce((sum, line) => sum + line.unit_price * (Number(line.quantity) || 0), 0));

const sameLine = (line, productId, variantId) =>
    Number(line.product_id) === Number(productId) && (line.variant_id || null) === (variantId || null);

const removeLine = (idx) => draft.value.splice(idx, 1);

// Switching a line to another size keeps its quantity; the price shown is
// that size's list price, which is what the server will charge anyway. If
// that size is already a line, the two lines become one.
const switchLineVariant = (line, row) => {
    if ((row.variant_id || null) === (line.variant_id || null)) return;
    const other = draft.value.find((l) => l !== line && sameLine(l, row.id, row.variant_id));
    if (other) {
        other.quantity = Number(other.quantity) + Number(line.quantity);
        draft.value.splice(draft.value.indexOf(line), 1);
        return;
    }
    line.variant_id = row.variant_id || null;
    line.variant_label = row.variant_label || '';
    line.sku = row.sku || '';
    line.unit_price = listPrice(row);
    line.stock = row.stock_quantity ?? null;
};

/* Adding a product: a server search, one row per size. */
const addPick = ref('');
const searchLoading = ref(false);
const searchRows = ref([]);
let searchTimer = null;

const searchProducts = (query) => {
    clearTimeout(searchTimer);
    if (!query || !query.trim()) return;
    searchLoading.value = true;
    searchTimer = setTimeout(async () => {
        try {
            const res = await posApi.productLookup({ q: query.trim(), expand_variants: 1 });
            const data = res.data?.data || [];
            searchRows.value = Array.isArray(data) ? data : [];
            rememberVariantRows(searchRows.value);
        } catch {
            searchRows.value = [];
        } finally {
            searchLoading.value = false;
        }
    }, 250);
};

// Rows regrouped by product, so the sizes of one product sit under its name.
const searchGroups = computed(() => {
    const groups = new Map();
    searchRows.value.forEach((row) => {
        if (!groups.has(row.id)) groups.set(row.id, { key: String(row.id), product: row, name: baseName(row), rows: [] });
        groups.get(row.id).rows.push(row);
    });
    return [...groups.values()].map((g) => ({ ...g, hasVariants: g.rows.some((r) => r.variant_id) }));
});

const addPicked = (key) => {
    if (!key) return;
    const row = searchRows.value.find((r) => optionKey(r) === key);
    addPick.value = '';
    if (!row) return;
    const existing = draft.value.find((l) => sameLine(l, row.id, row.variant_id));
    if (existing) {
        existing.quantity = Number(existing.quantity) + 1;
        return;
    }
    draft.value.push({
        key: ++draftSeq,
        product_id: row.id,
        variant_id: row.variant_id || null,
        variant_label: row.variant_label || '',
        name: baseName(row),
        sku: row.sku || '',
        image: resolveImageUrl(row.image_main || row.image || ''),
        quantity: 1,
        unit_price: listPrice(row),
        stock: row.stock_quantity ?? null,
    });
    if (row.variant_id) ensureVariantsLoaded(row.id);
};

const saveItems = async () => {
    if (!draft.value.length) return;
    saving.value = true;
    try {
        order.value = await store.updateItems(order.value.id, draft.value.map((line) => ({
            product_id: line.product_id,
            variant_id: line.variant_id || null,
            quantity: Number(line.quantity) || 1,
        })));
        editing.value = false;
        draft.value = [];
        ElMessage.success(t('pr_items_saved'));
    } catch (err) {
        ElMessage.error(err.response?.data?.message || store.error || t('failed_to_load_order_details'));
    } finally {
        saving.value = false;
    }
};

const openOrder = () => {
    router.push({ path: '/admin/sales/sales-orders', query: { open: order.value.id, tab: 'execution' } });
};

onMounted(async () => {
    const id = route.params.id;
    if (id) {
        try {
            const data = await store.fetchOrder(id);
            order.value = data;
        } catch {
            ElMessage.error(t('failed_to_load_order_details'));
        } finally {
            loading.value = false;
        }
    } else {
        loading.value = false;
    }
});
</script>

<style scoped>
.purchase-request-show {
    padding: 0;
}

.items-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.items-count { color: #6b7c98; font-weight: 400; margin-inline-start: 0.35rem; }
.items-head-actions { display: flex; align-items: center; gap: 0.5rem; }
.items-locked { color: #6b7c98; font-size: 0.78rem; }

.line-cell { display: flex; align-items: center; gap: 0.6rem; min-width: 0; }
.line-text { display: flex; flex-direction: column; gap: 0.2rem; min-width: 0; }
.line-meta { display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap; font-size: 0.76rem; }
.line-meta code { font-size: 0.72rem; color: #475569; background: #f1f5f9; padding: 0 0.35rem; border-radius: 4px; }

.stock-pill { color: #15803d; background: #f0fdf4; padding: 0.05rem 0.45rem; border-radius: 4px; font-weight: 600; }
.stock-pill.is-short { color: #b91c1c; background: #fef2f2; }
.price-tag { color: #1e3a8a; font-weight: 700; }
.text-muted { color: #94a3b8; }

.alloc-list { display: flex; flex-wrap: wrap; gap: 0.3rem; }
.alloc-pill { font-size: 0.74rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 999px; padding: 0.05rem 0.5rem; }

.items-total { margin-top: 1rem; text-align: left; font-size: 1.1rem; font-weight: 700; }

/* Editor */
.editor-hint { margin: 0 0 0.75rem; color: #475569; font-size: 0.82rem; }

.draft-line {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    padding: 0.6rem 0;
    border-bottom: 1px solid #f1f5f9;
}

.draft-text { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 0.25rem; }
.draft-qty { width: 120px; flex: none; }
.draft-empty { padding: 1rem; text-align: center; color: #6b7c98; }
.draft-add { width: 100%; margin-top: 0.75rem; }

.size-switch { display: flex; align-items: center; gap: 0.3rem; flex-wrap: wrap; margin-top: 0.2rem; }
.switch-label { display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.72rem; font-weight: 600; color: #64748b; }

.switch-chip {
    all: unset;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.1rem 0.5rem;
    border-radius: 999px;
    border: 1px solid #cbd5e1;
    background: #fff;
    color: #334155;
    font-size: 0.74rem;
    font-weight: 600;
    line-height: 1.5;
    transition: all 0.15s ease;
}

.switch-chip:hover { border-color: #93c5fd; color: #1d4ed8; }
.switch-chip.is-active { border-color: #2563eb; background: #eff6ff; color: #1d4ed8; cursor: default; }
.switch-chip.is-out { color: #9a3412; border-style: dashed; }
.switch-chip small { font-size: 0.66rem; color: #16a34a; padding-inline-start: 0.3rem; border-inline-start: 1px solid #e2e8f0; }
.switch-chip.is-out small { color: #dc2626; }

.opt-row { display: flex; align-items: center; gap: 0.5rem; }
.opt-size { font-weight: 700; color: #1e3a8a; }
.opt-name { font-weight: 600; }
.opt-row code { font-size: 0.72rem; color: #64748b; }
.opt-side { margin-inline-start: auto; display: flex; gap: 0.6rem; font-size: 0.76rem; color: #475569; }
.opt-side .ok { color: #15803d; }
.opt-side .out { color: #b91c1c; }

@media (max-width: 640px) {
    .draft-line { flex-wrap: wrap; }
    .draft-qty { width: 100%; }
}
.empty-state {
    text-align: center;
    padding: 3rem;
    color: #6b7c98;
}
</style>
