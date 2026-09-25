<template>
    <div class="categories-index">
        <AdminPageHeader
            icon="fas fa-sitemap"
            :title="$t('category_management')"
            :subtitle="$t('cat_admin_subtitle')"
        >
            <template #actions>
                <el-button :icon="Refresh" :loading="loading" @click="fetchCategories">
                    {{ $t('cat_admin_refresh') }}
                </el-button>
                <el-button type="primary" :icon="Plus" @click="goToCreate()">
                    {{ $t('add_category') }}
                </el-button>
            </template>
        </AdminPageHeader>

        <AdminStatGrid :min="165">
            <el-card
                v-for="card in statCards"
                :key="card.key"
                shadow="hover"
                class="stat-card"
                :class="{ 'is-clickable': card.filter, 'is-selected': card.filter && card.filter !== 'all' && filters.status === card.filter }"
                @click="card.filter && applyStatFilter(card.filter)"
            >
                <div class="stat-card-inner">
                    <div class="stat-icon-box" :class="card.key">
                        <el-icon><component :is="card.icon" /></el-icon>
                    </div>
                    <div class="stat-details">
                        <h3>{{ loaded ? card.value : '—' }}</h3>
                        <p>{{ card.title }}</p>
                    </div>
                </div>
            </el-card>
        </AdminStatGrid>

        <div class="panel-card">
            <div class="toolbar">
                <el-input
                    v-model="filters.search"
                    class="toolbar-search"
                    :placeholder="$t('cat_admin_search_placeholder')"
                    :prefix-icon="Search"
                    clearable
                />

                <el-radio-group v-model="filters.status" class="toolbar-status">
                    <el-radio-button value="all">{{ $t('cat_admin_all') }}</el-radio-button>
                    <el-radio-button value="active">{{ $t('active') }}</el-radio-button>
                    <el-radio-button value="inactive">{{ $t('inactive') }}</el-radio-button>
                    <el-radio-button value="empty">{{ $t('cat_admin_empty') }}</el-radio-button>
                </el-radio-group>

                <span class="toolbar-spacer" />

                <el-button text :icon="expandAll ? Fold : Expand" @click="toggleExpandAll">
                    {{ expandAll ? $t('cat_admin_collapse_all') : $t('cat_admin_expand_all') }}
                </el-button>
            </div>

            <el-result
                v-if="loadError && !loaded"
                icon="error"
                :title="$t('failed_to_fetch_categories')"
            >
                <template #extra>
                    <el-button type="primary" :icon="Refresh" @click="fetchCategories">
                        {{ $t('cat_admin_retry') }}
                    </el-button>
                </template>
            </el-result>

            <div v-else class="table-wrapper">
                <el-table
                    :key="tableKey"
                    v-loading="loading && !loaded"
                    :data="tree"
                    row-key="id"
                    :default-expand-all="expandAll"
                    :tree-props="{ children: 'children' }"
                    :row-class-name="rowClassName"
                    style="width: 100%"
                >
                    <template #empty>
                        <el-empty
                            v-if="loaded && !categories.length"
                            :description="$t('cat_admin_none_yet')"
                            :image-size="90"
                        >
                            <el-button type="primary" :icon="Plus" @click="goToCreate()">
                                {{ $t('add_category') }}
                            </el-button>
                        </el-empty>
                        <el-empty v-else-if="loaded" :description="$t('cat_admin_no_matches')" :image-size="90">
                            <el-button @click="resetFilters">{{ $t('cat_admin_clear_filters') }}</el-button>
                        </el-empty>
                        <span v-else />
                    </template>

                    <el-table-column :label="$t('cat_admin_category')" min-width="300" class-name="name-col">
                        <template #default="{ row }">
                            <div class="category-cell">
                                <EntityImage
                                    :src="row.image || ''"
                                    type="category"
                                    :size="row.parent_id ? 38 : 46"
                                    :preview-src-list="row.image ? [row.image] : []"
                                />
                                <div class="cell-stack">
                                    <span class="cell-primary">
                                        <router-link :to="editRoute(row)" class="name-link">
                                            {{ row.name_ar || row.name_en }}
                                        </router-link>
                                        <el-tag
                                            v-if="!row.parent_id"
                                            size="small"
                                            effect="plain"
                                            class="level-tag"
                                        >
                                            {{ $t('cat_admin_section') }}
                                        </el-tag>
                                    </span>
                                    <span class="cell-secondary">
                                        <template v-if="row.name_en && row.name_en !== row.name_ar">
                                            <span dir="ltr">{{ row.name_en }}</span>
                                            <span class="dot">·</span>
                                        </template>
                                        <code dir="ltr">/{{ row.slug }}</code>
                                    </span>
                                </div>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('cat_admin_subcategories')" width="130" align="center">
                        <template #default="{ row }">
                            <span v-if="row.parent_id" class="cell-empty">—</span>
                            <el-tag v-else-if="row.children_count" type="info" size="small" round>
                                {{ row.children_count }}
                            </el-tag>
                            <span v-else class="cell-empty">0</span>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('number_of_products')" width="160" align="center">
                        <template #default="{ row }">
                            <el-tooltip :content="productsTooltip(row)" placement="top" :enterable="false">
                                <router-link
                                    v-if="row.total_products"
                                    :to="productsRoute(row)"
                                    class="count-link"
                                >
                                    <strong>{{ row.total_products }}</strong>
                                    <span v-if="row.total_active !== row.total_products" class="count-live">
                                        {{ row.total_active }} {{ $t('cat_admin_live') }}
                                    </span>
                                </router-link>
                                <el-tag v-else type="warning" size="small" effect="light">
                                    {{ $t('cat_admin_empty') }}
                                </el-tag>
                            </el-tooltip>
                        </template>
                    </el-table-column>

                    <el-table-column width="130" align="center">
                        <template #header>
                            <el-tooltip :content="$t('cat_admin_order_hint')" placement="top">
                                <span class="header-hint">
                                    {{ $t('ranking') }}
                                    <el-icon><InfoFilled /></el-icon>
                                </span>
                            </el-tooltip>
                        </template>
                        <template #default="{ row }">
                            <el-input-number
                                :model-value="row.sort_order"
                                :min="0"
                                size="small"
                                controls-position="right"
                                class="order-input"
                                :disabled="saving.has(row.id)"
                                @change="(value) => updateOrder(row, value)"
                            />
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('status')" width="140" align="center">
                        <template #default="{ row }">
                            <div class="status-cell">
                                <el-switch
                                    :model-value="row.is_active"
                                    :loading="saving.has(row.id)"
                                    class="status-switch"
                                    :aria-label="$t('status')"
                                    @change="(value) => toggleStatus(row, value)"
                                />
                                <span class="status-label" :class="{ 'is-on': row.is_active }">
                                    {{ row.is_active ? $t('active') : $t('inactive') }}
                                </span>
                                <el-tooltip
                                    v-if="row.is_active && row.parentInactive"
                                    :content="$t('cat_admin_parent_inactive')"
                                    placement="top"
                                >
                                    <el-icon class="status-warning"><WarningFilled /></el-icon>
                                </el-tooltip>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('procedures')" width="210" align="center" fixed="right">
                        <template #default="{ row }">
                            <div class="row-actions">
                                <el-tooltip :content="$t('edit_category')" placement="top" :enterable="false">
                                    <el-button size="small" circle :icon="Edit" @click="router.push(editRoute(row))" />
                                </el-tooltip>
                                <el-tooltip
                                    v-if="!row.parent_id"
                                    :content="$t('cat_admin_add_subcategory')"
                                    placement="top"
                                    :enterable="false"
                                >
                                    <el-button size="small" circle :icon="FolderAdd" @click="goToCreate(row.id)" />
                                </el-tooltip>
                                <el-tooltip
                                    :content="row.is_active ? $t('cat_admin_view_on_site') : $t('cat_admin_hidden_on_site')"
                                    placement="top"
                                    :enterable="false"
                                >
                                    <el-button
                                        size="small"
                                        circle
                                        :icon="View"
                                        :disabled="!row.is_active || !row.url"
                                        @click="openOnSite(row)"
                                    />
                                </el-tooltip>
                                <el-tooltip :content="$t('cat_admin_delete')" placement="top" :enterable="false">
                                    <el-button
                                        size="small"
                                        circle
                                        type="danger"
                                        plain
                                        :icon="Delete"
                                        :loading="deleting === row.id"
                                        @click="deleteCategory(row)"
                                    />
                                </el-tooltip>
                            </div>
                        </template>
                    </el-table-column>
                </el-table>
            </div>

            <p v-if="loaded && categories.length" class="table-footnote">
                {{ $t('cat_admin_showing', { shown: visibleCount, total: categories.length }) }}
            </p>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
    Plus, Edit, Delete, View, Search, Refresh, FolderAdd, Fold, Expand,
    Files, FolderOpened, CircleCheck, CircleClose, Box, InfoFilled, WarningFilled,
} from '@element-plus/icons-vue';
import { categoriesApi } from '@/api/categories';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import AdminStatGrid from '@/components/admin/AdminStatGrid.vue';
import EntityImage from '@/components/admin/EntityImage.vue';

const router = useRouter();
const { t } = useI18n();

// The page keeps its own list rather than the shared categories store: the
// store backs the storefront and holds active categories only, while this
// screen has to show the inactive ones too — otherwise switching a category
// off made it vanish from the one place it could be switched back on.
const categories = ref([]);
const loading = ref(false);
const loaded = ref(false);
const loadError = ref(false);
const saving = reactive(new Set());
const deleting = ref(null);

const filters = reactive({ search: '', status: 'all' });
const expandAll = ref(true);
// el-table only reads default-expand-all on mount, so the toggle remounts it.
const tableKey = ref(0);

const fetchCategories = async () => {
    loading.value = true;
    loadError.value = false;
    try {
        const response = await categoriesApi.getAll();
        categories.value = response.data.data || response.data || [];
        loaded.value = true;
    } catch (error) {
        loadError.value = true;
        ElMessage.error(t('failed_to_fetch_categories'));
    } finally {
        loading.value = false;
    }
};

const byOrder = (a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0) || a.id - b.id;

const normalize = (value) => String(value ?? '').toLowerCase().trim();

const matchesSearch = (category, term) => !term
    || [category.name_ar, category.name_en, category.slug].some((field) => normalize(field).includes(term));

/**
 * Sections with their subcategories nested under them, each carrying the
 * product totals the visitor actually sees: a section's own products plus
 * everything filed in its subcategories.
 */
const fullTree = computed(() => {
    const ids = new Set(categories.value.map((c) => c.id));
    const childrenOf = new Map();

    categories.value.forEach((c) => {
        // A child whose parent is gone is shown as a section rather than lost.
        if (c.parent_id && ids.has(c.parent_id)) {
            if (!childrenOf.has(c.parent_id)) childrenOf.set(c.parent_id, []);
            childrenOf.get(c.parent_id).push(c);
        }
    });

    return categories.value
        .filter((c) => !c.parent_id || !ids.has(c.parent_id))
        .sort(byOrder)
        .map((parent) => {
            const children = (childrenOf.get(parent.id) || []).sort(byOrder).map((child) => ({
                ...child,
                parentInactive: !parent.is_active,
                total_products: child.products_count ?? 0,
                total_active: child.active_products_count ?? 0,
            }));

            return {
                ...parent,
                parent_id: null,
                children_count: children.length,
                total_products: (parent.products_count ?? 0)
                    + children.reduce((sum, c) => sum + c.total_products, 0),
                total_active: (parent.active_products_count ?? 0)
                    + children.reduce((sum, c) => sum + c.total_active, 0),
                children,
            };
        });
});

const passesStatus = (category) => {
    switch (filters.status) {
        case 'active': return category.is_active;
        case 'inactive': return !category.is_active;
        case 'empty': return !category.total_products;
        default: return true;
    }
};

/**
 * The tree narrowed to the filters. A section stays in view when one of its
 * subcategories matches, so a hit is never shown without the section it sits
 * in; a section that matches the search itself keeps all its children.
 */
const tree = computed(() => {
    const term = normalize(filters.search);

    return fullTree.value.reduce((rows, parent) => {
        const parentHit = matchesSearch(parent, term);
        const children = parent.children.filter((child) =>
            passesStatus(child) && (parentHit || matchesSearch(child, term)));

        if ((parentHit && passesStatus(parent)) || children.length) {
            rows.push({ ...parent, children });
        }
        return rows;
    }, []);
});

const visibleCount = computed(() =>
    tree.value.reduce((sum, parent) => sum + 1 + parent.children.length, 0));

const statCards = computed(() => {
    const all = categories.value;
    const flat = fullTree.value.flatMap((p) => [p, ...p.children]);
    return [
        { key: 'total', icon: Files, title: t('cat_admin_total'), value: all.length, filter: 'all' },
        { key: 'sections', icon: FolderOpened, title: t('cat_admin_sections'), value: fullTree.value.length },
        { key: 'active', icon: CircleCheck, title: t('active'), value: all.filter((c) => c.is_active).length, filter: 'active' },
        { key: 'inactive', icon: CircleClose, title: t('inactive'), value: all.filter((c) => !c.is_active).length, filter: 'inactive' },
        { key: 'empty', icon: Box, title: t('cat_admin_without_products'), value: flat.filter((c) => !c.total_products).length, filter: 'empty' },
    ];
});

// Clicking a card filters by it; clicking the selected card again clears it.
const applyStatFilter = (status) => {
    filters.status = filters.status === status ? 'all' : status;
};

const resetFilters = () => {
    filters.search = '';
    filters.status = 'all';
};

const toggleExpandAll = () => {
    expandAll.value = !expandAll.value;
    tableKey.value += 1;
};

const rowClassName = ({ row }) => [
    row.parent_id ? 'row-child' : 'row-section',
    row.is_active ? '' : 'row-inactive',
].join(' ');

const productsTooltip = (row) => {
    if (!row.total_products) return t('cat_admin_no_products_hint');
    return t('cat_admin_products_hint', { total: row.total_products, live: row.total_active });
};

const editRoute = (category) => `/admin/categories/${category.id}/edit`;

const goToCreate = (parentId = null) => {
    router.push({ path: '/admin/categories/create', query: parentId ? { parent_id: parentId } : {} });
};

const productsRoute = (category) => ({ path: '/admin/products', query: { category_id: category.id } });

const openOnSite = (category) => {
    if (category.is_active && category.url) window.open(category.url, '_blank', 'noopener');
};

/** Writes one field and patches the row in place, so the table never flashes. */
const patchCategory = async (category, data) => {
    saving.add(category.id);
    try {
        await categoriesApi.update(category.id, data);
        const stored = categories.value.find((c) => c.id === category.id);
        if (stored) Object.assign(stored, data);
        return true;
    } catch (error) {
        ElMessage.error(error.response?.data?.message || t('failed_to_update_status'));
        return false;
    } finally {
        saving.delete(category.id);
    }
};

const toggleStatus = async (category, value) => {
    if (await patchCategory(category, { is_active: value })) {
        ElMessage.success(value ? t('cat_admin_now_visible') : t('cat_admin_now_hidden'));
    }
};

const updateOrder = async (category, value) => {
    if (value === null || value === undefined || value === category.sort_order) return;
    if (await patchCategory(category, { sort_order: value })) {
        ElMessage.success(t('cat_admin_order_saved'));
    }
};

const deleteCategory = async (category) => {
    const name = category.name_ar || category.name_en;
    const consequences = [];
    if (category.children_count) {
        consequences.push(t('cat_admin_delete_children', { count: category.children_count }));
    }
    const ownProducts = category.products_count ?? 0;
    if (ownProducts) {
        consequences.push(t('cat_admin_delete_products', { count: ownProducts }));
    }

    try {
        await ElMessageBox.confirm(
            [t('delete_category_confirm', { name }), ...consequences, t('cat_admin_cannot_undo')].join('\n\n'),
            t('confirm_deletion'),
            {
                confirmButtonText: t('cat_admin_delete'),
                cancelButtonText: t('cancel'),
                confirmButtonClass: 'el-button--danger',
                type: 'warning',
                customClass: 'category-delete-box',
            }
        );
    } catch {
        return;
    }

    deleting.value = category.id;
    try {
        await categoriesApi.delete(category.id);
        ElMessage.success(t('the_category_has_been_successfully'));
        await fetchCategories();
    } catch (error) {
        ElMessage.error(error.response?.data?.message || t('failed_to_delete_category'));
    } finally {
        deleting.value = null;
    }
};

onMounted(fetchCategories);
</script>

<style scoped>
.categories-index {
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

    padding: 0;
    color: var(--ink);
}

/* ── Stat cards ─────────────────────────────────────────────────────── */
.stat-card {
    border-radius: 14px;
    border: 1px solid transparent;
    transition: border-color 0.15s ease, transform 0.15s ease;
}

.stat-card.is-clickable { cursor: pointer; }
.stat-card.is-clickable:hover { transform: translateY(-1px); }
.stat-card.is-selected { border-color: var(--primary); }

.stat-card-inner { display: flex; align-items: center; gap: 1rem; }

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

.stat-icon-box.total { background: var(--purple-soft); color: var(--purple); }
.stat-icon-box.sections { background: var(--primary-soft); color: var(--primary); }
.stat-icon-box.active { background: var(--ok-soft); color: var(--ok); }
.stat-icon-box.inactive { background: var(--danger-soft); color: var(--danger); }
.stat-icon-box.empty { background: var(--warn-soft); color: var(--warn); }

.stat-details { min-width: 0; }
.stat-details h3 { margin: 0; font-size: 1.4rem; font-weight: 800; line-height: 1.2; }
.stat-details p { margin: 0.2rem 0 0; color: var(--ink-mute); font-size: 0.82rem; font-weight: 600; }

/* ── Panel / toolbar ────────────────────────────────────────────────── */
.panel-card {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 14px;
    padding: 1.1rem 1.25rem;
    box-shadow: 0 1px 2px rgba(18, 28, 44, 0.04);
}

.toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.toolbar-search { width: 300px; max-width: 100%; }
.toolbar-spacer { flex: 1 1 auto; }

/* ── Table ──────────────────────────────────────────────────────────── */
.table-wrapper { border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }

.category-cell { display: inline-flex; align-items: center; gap: 0.75rem; min-width: 0; vertical-align: middle; }

.cell-stack { display: flex; flex-direction: column; gap: 0.15rem; min-width: 0; }
.cell-primary { display: flex; align-items: center; gap: 0.45rem; font-weight: 700; }
.cell-secondary {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.76rem;
    color: var(--ink-mute);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.cell-secondary code { font-size: 0.72rem; color: #94a3b8; }
.cell-empty { color: #cbd5e1; }
.dot { color: #cbd5e1; }

.name-link { color: var(--ink); text-decoration: none; }
.name-link:hover { color: var(--primary); text-decoration: underline; }

.level-tag { font-size: 0.68rem; font-weight: 600; }

.count-link {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    line-height: 1.25;
    color: var(--primary);
    text-decoration: none;
    padding: 0.15rem 0.6rem;
    border-radius: 8px;
    transition: background 0.15s ease;
}
.count-link:hover { background: var(--primary-soft); }
.count-link strong { font-size: 0.95rem; }
.count-live { font-size: 0.7rem; color: var(--ink-mute); }

.header-hint { display: inline-flex; align-items: center; gap: 0.25rem; cursor: help; }

.order-input { width: 90px; }

.status-cell { display: inline-flex; align-items: center; gap: 0.4rem; }
.status-switch { --el-switch-on-color: var(--ok); }
.status-label { font-size: 0.78rem; font-weight: 600; color: var(--ink-mute); min-width: 3.2rem; text-align: start; }
.status-label.is-on { color: var(--ok); }
.status-warning { color: var(--warn); cursor: help; }

.row-actions { display: flex; gap: 0.35rem; justify-content: center; }
.row-actions .el-button + .el-button { margin-inline-start: 0; }

/* Keeps the tree's expand arrow and indent on the same line as the name. */
:deep(.name-col .cell) { display: flex; align-items: center; }

:deep(.row-section) > td { background: #fbfcfe; }
:deep(.row-section .cell-primary) { font-size: 0.95rem; }
:deep(.row-child .cell-primary) { font-weight: 600; }
:deep(.row-inactive .category-cell),
:deep(.row-inactive .count-link) { opacity: 0.55; }

.table-footnote { margin: 0.75rem 0 0; font-size: 0.78rem; color: var(--ink-mute); }

@media (max-width: 768px) {
    :deep(.admin-stat-grid) { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; gap: 0.6rem; }
    .stat-card :deep(.el-card__body) { padding: 0.75rem; }
    .stat-card-inner { gap: 0.6rem; }
    .stat-icon-box { width: 38px; height: 38px; font-size: 1.1rem; }
    .stat-details h3 { font-size: 1.15rem; }
    .toolbar-search { width: 100%; }
    .toolbar-status { width: 100%; display: flex; }
    .toolbar-status :deep(.el-radio-button) { flex: 1; }
    .toolbar-status :deep(.el-radio-button__inner) { width: 100%; }
}
</style>

<style>
/* Teleported to <body>, so it cannot be scoped: keeps the paragraph breaks in
   the delete confirmation that spell out what happens to products and
   subcategories. */
.category-delete-box .el-message-box__message p { white-space: pre-line; }
</style>
