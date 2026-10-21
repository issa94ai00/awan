<template>
    <aside class="admin-sidebar" :class="{ collapsed: collapsed, 'mobile-open': mobileOpen }" dir="rtl">
        <div class="sidebar-header">
            <div class="brand">
                <div class="brand-mark">
                    <span class="brand-mark-glow"></span>
                    <el-icon :size="17"><Box /></el-icon>
                </div>
                <div v-if="!isCollapsed" class="brand-text">
                    <span class="brand-name">{{ siteName }}</span>
                    <span class="brand-tag">
                        <span class="brand-dot"></span>
                        ERP Suite
                    </span>
                </div>
            </div>
            <el-button
                v-if="!mobileOpen"
                class="collapse-btn"
                :icon="collapsed ? Expand : Fold"
                :aria-label="collapsed ? t('expand_sidebar') : t('collapse_sidebar')"
                :title="collapsed ? t('expand_sidebar') : t('collapse_sidebar')"
                circle
                size="small"
                @click="toggleSidebar"
            />
            <el-button
                v-if="mobileOpen"
                class="collapse-btn mobile-close-btn"
                :icon="Close"
                :aria-label="t('close_menu')"
                circle
                size="small"
                @click="closeMobile"
            />
        </div>

        <nav ref="navRef" class="sidebar-nav" :aria-label="t('main_navigation')">
            <div v-if="!isCollapsed" class="nav-search">
                <el-icon class="nav-search-icon"><Search /></el-icon>
                <input
                    ref="searchInputRef"
                    v-model="searchQuery"
                    type="text"
                    class="nav-search-input"
                    :placeholder="t('search_menu')"
                    :aria-label="t('search_menu')"
                    @keydown="onSearchKeydown"
                />
                <button
                    v-if="searchQuery"
                    type="button"
                    class="nav-search-clear"
                    :aria-label="t('clear')"
                    @click="clearSearch"
                >
                    <el-icon><Close /></el-icon>
                </button>
                <kbd v-else class="nav-search-kbd" aria-hidden="true">/</kbd>
            </div>
            <el-tooltip v-else :content="t('search_menu')" :placement="flyoutPlacement" :show-after="150">
                <button type="button" class="nav-search-trigger" :aria-label="t('search_menu')" @click="openSearch">
                    <el-icon><Search /></el-icon>
                </button>
            </el-tooltip>

            <ul v-if="hasQuery" class="nav-list search-results">
                <li v-for="(item, index) in filteredNavItems" :key="item.path + item.labelKey" class="nav-item">
                    <router-link
                        :to="item.path"
                        class="nav-link"
                        :class="{ active: isActive(item.path), highlighted: index === highlightedIndex }"
                        @mouseenter="highlightedIndex = index"
                        @click="onSearchNavigate"
                    >
                        <el-icon class="nav-ic"><component :is="item.icon" /></el-icon>
                        <span class="nav-text search-result-text">
                            {{ t(item.labelKey) }}
                            <small v-if="item.groupLabelKey" class="search-result-group">{{ t(item.groupLabelKey) }}</small>
                        </span>
                    </router-link>
                </li>
                <li v-if="filteredNavItems.length === 0" class="nav-empty">{{ t('no_results') }}</li>
            </ul>

            <ul v-else class="nav-list">
                <template v-for="section in visibleMenu" :key="section.labelKey">
                    <li class="nav-section-label">{{ t(section.labelKey) }}</li>
                    <template v-for="entry in section.items" :key="entry.key">
                        <li v-if="!entry.children" class="nav-item">
                            <el-tooltip :disabled="!isCollapsed" :content="t(entry.labelKey)" :placement="flyoutPlacement" :show-after="150">
                                <router-link
                                    :to="entry.path"
                                    class="nav-link"
                                    :class="{ active: isActive(entry.path) }"
                                    :aria-current="isActive(entry.path) ? 'page' : undefined"
                                >
                                    <el-icon class="nav-ic"><component :is="entry.icon" /></el-icon>
                                    <span v-if="!isCollapsed" class="nav-text">{{ t(entry.labelKey) }}</span>
                                </router-link>
                            </el-tooltip>
                        </li>

                        <li
                            v-else
                            class="nav-group"
                            :class="{ open: isGroupOpen(entry.key), 'has-active-child': isGroupActiveRoute(entry.key) }"
                        >
                            <!-- Collapsed sidebar: hovering a group shows its pages in a flyout
                                 instead of forcing the whole sidebar open. -->
                            <el-popover
                                :disabled="!isCollapsed"
                                trigger="hover"
                                :placement="flyoutPlacement"
                                :width="230"
                                :offset="14"
                                :show-after="60"
                                :hide-after="120"
                                :show-arrow="false"
                                popper-class="sidebar-flyout"
                            >
                                <template #reference>
                                    <div
                                        class="nav-group-header"
                                        tabindex="0"
                                        role="button"
                                        :aria-expanded="isGroupOpen(entry.key)"
                                        @click="toggleGroup(entry.key)"
                                        @keydown.enter.prevent="toggleGroup(entry.key)"
                                        @keydown.space.prevent="toggleGroup(entry.key)"
                                    >
                                        <el-icon class="nav-ic"><component :is="entry.icon" /></el-icon>
                                        <span v-if="!isCollapsed" class="nav-text">{{ t(entry.labelKey) }}</span>
                                        <span v-if="!isCollapsed && entry.children" class="nav-group-count">{{ entry.children.length }}</span>
                                        <el-icon v-if="!isCollapsed" class="toggle-icon" :class="{ rotated: isGroupOpen(entry.key) }"><ArrowDown /></el-icon>
                                        <span v-if="isCollapsed && isGroupActiveRoute(entry.key)" class="collapsed-active-dot"></span>
                                    </div>
                                </template>
                                <div class="flyout-title">
                                    <span>{{ t(entry.labelKey) }}</span>
                                    <span class="flyout-count">{{ entry.children.length }}</span>
                                </div>
                                <router-link
                                    v-for="child in entry.children"
                                    :key="child.path + child.labelKey"
                                    :to="child.path"
                                    class="flyout-link"
                                    :class="{ active: isActive(child.path) }"
                                >
                                    <span class="flyout-dot"></span>
                                    {{ t(child.labelKey) }}
                                </router-link>
                            </el-popover>

                            <div class="nav-group-body" :class="{ open: isGroupOpen(entry.key) }" :inert="!isGroupOpen(entry.key)">
                                <ul class="nav-group-items">
                                    <li v-for="child in entry.children" :key="child.path + child.labelKey">
                                        <router-link
                                            :to="child.path"
                                            class="nav-link"
                                            :class="{ active: isActive(child.path) }"
                                            :aria-current="isActive(child.path) ? 'page' : undefined"
                                        >
                                            <span class="sub-dot"></span>
                                            <span class="nav-text">{{ t(child.labelKey) }}</span>
                                        </router-link>
                                    </li>
                                </ul>
                            </div>
                        </li>
                    </template>
                </template>
            </ul>
        </nav>

        <div class="sidebar-footer" :class="{ collapsed: collapsed }">
            <div class="user-card" :title="userName" @click="collapsed ? toggleSidebar() : router.push('/admin/profile')">
                <div class="user-avatar-wrap">
                    <div class="user-avatar">{{ userInitials }}</div>
                    <span class="user-online-dot"></span>
                </div>
                <div v-if="!isCollapsed" class="user-meta">
                    <span class="user-name">{{ userName }}</span>
                    <span class="user-role">{{ userRoleTitle }}</span>
                </div>
                <router-link
                    v-if="!isCollapsed"
                    to="/admin/settings"
                    class="user-action"
                    :class="{ active: matchesRoute('/admin/settings') }"
                    :aria-label="t('settings')"
                    :title="t('settings')"
                    @click.stop
                >
                    <el-icon :size="15"><Setting /></el-icon>
                </router-link>
            </div>
        </div>
    </aside>
</template>

<script setup>
import { ref, watch, computed, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useSettingsStore } from '@/stores/settings';
import { useAuthStore } from '@/stores/auth';
import { useI18n } from 'vue-i18n';
import { matchesSearch } from '@/utils/search';
import { pagePermissionFor } from '@/config/pagePermissions';
import {
    Box, Fold, Expand, Close, Odometer, ShoppingCart, ArrowDown, Setting,
    ShoppingBag, Coin, UserFilled, ChatDotRound, Tools, DataAnalysis, Monitor,
    ChatLineRound, View, Refresh, Bell, Location, Cpu, Search
} from '@element-plus/icons-vue';

const { t, locale } = useI18n();
const settingsStore = useSettingsStore();
const authStore = useAuthStore();
const siteName = computed(() => settingsStore.data?.site_name || t('site_fallback_name'));

const props = defineProps({
    collapsed: {
        type: Boolean,
        default: false
    },
    mobileOpen: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:collapsed', 'update:mobileOpen']);

const isCollapsed = computed(() => props.collapsed && !props.mobileOpen);

// The sidebar is always situated on the right side in RTL, so flyouts open to the left towards the page content.
const flyoutPlacement = 'left-start';

const closeMobile = () => {
    emit('update:mobileOpen', false);
};

const route = useRoute();
const router = useRouter();

// Single source of truth for the menu: the rendered tree, the search index,
// active-route matching and the group a route belongs to are all derived
// from it. Entries with `children` are collapsible groups gated by
// canAccessGroup(key); plain entries are visible to everyone.
const menu = [
    {
        labelKey: 'nav_label_main',
        items: [
            { key: 'dashboard', labelKey: 'dashboard', path: '/admin/dashboard', icon: Odometer },
        ],
    },
    {
        labelKey: 'nav_label_content',
        items: [
            {
                key: 'content', labelKey: 'content_management', icon: Box,
                children: [
                    { labelKey: 'categories', path: '/admin/categories' },
                    { labelKey: 'products', path: '/admin/products' },
                    { labelKey: 'product_units', path: '/admin/products/units' },
                    { labelKey: 'catalog', path: '/admin/products/price-offer' },
                    { labelKey: 'special_offers', path: '/admin/special-offers' },
                    { labelKey: 'secondary_navbar', path: '/admin/secondary-navbar' },
                ],
            },
        ],
    },
    {
        labelKey: 'nav_label_commerce',
        items: [
            {
                key: 'sales', labelKey: 'sales', icon: ShoppingCart,
                children: [
                    { labelKey: 'overview', path: '/admin/sales' },
                    { labelKey: 'invoices', path: '/admin/sales/invoices' },
                    { labelKey: 'create_invoice', path: '/admin/sales/invoices/create' },
                    { labelKey: 'customers', path: '/admin/sales/customers' },
                    { labelKey: 'quotes', path: '/admin/sales/quotes' },
                    { labelKey: 'sales_orders', path: '/admin/sales/sales-orders' },
                    { labelKey: 'payments', path: '/admin/sales/payments' },
                    // A rep's way to their clients' statements; accountants
                    // reach the same screen under accounting.
                    { labelKey: 'party_statement', path: '/admin/accounting/party-statement', customerStatements: true },
                    { labelKey: 'professional_sales_reports', path: '/admin/sales/reports' },
                ],
            },
            {
                key: 'rma', labelKey: 'rma', icon: Refresh,
                children: [
                    { labelKey: 'overview', path: '/admin/rma' },
                    { labelKey: 'create', path: '/admin/rma/create' },
                    { labelKey: 'credit_notes', path: '/admin/rma/credit-notes' },
                ],
            },
            {
                key: 'purchases', labelKey: 'purchases', icon: ShoppingBag,
                children: [
                    { labelKey: 'overview', path: '/admin/purchases' },
                    { labelKey: 'suppliers', path: '/admin/purchases/suppliers' },
                    { labelKey: 'purchase_orders', path: '/admin/purchases/orders' },
                    { labelKey: 'receipts', path: '/admin/purchases/receipts' },
                    { labelKey: 'supplier_payments', path: '/admin/purchases/payments' },
                    { labelKey: 'purchase_returns', path: '/admin/purchases/returns' },
                    { labelKey: 'purchase_report', path: '/admin/purchases/report' },
                ],
            },
            {
                key: 'accounting', labelKey: 'accounting', icon: Coin,
                children: [
                    { labelKey: 'overview', path: '/admin/accounting' },
                    { labelKey: 'journal', path: '/admin/accounting/journal' },
                    { labelKey: 'chart_of_accounts', path: '/admin/accounting/chart-of-accounts' },
                    { labelKey: 'ledger', path: '/admin/accounting/ledger' },
                    { labelKey: 'trial_balance', path: '/admin/accounting/trial-balance' },
                    { labelKey: 'income_statement', path: '/admin/accounting/income-statement' },
                    { labelKey: 'balance_sheet', path: '/admin/accounting/balance-sheet' },
                    { labelKey: 'aging_report', path: '/admin/accounting/aging' },
                    { labelKey: 'cash_flow_statement', path: '/admin/accounting/cash-flow' },
                    { labelKey: 'party_statement', path: '/admin/accounting/party-statement' },
                    { labelKey: 'fixed_assets', path: '/admin/accounting/fixed-assets' },
                    { labelKey: 'bank_reconciliation', path: '/admin/accounting/bank-reconciliation' },
                    { labelKey: 'cost_centers', path: '/admin/accounting/cost-centers' },
                    { labelKey: 'vat_return', path: '/admin/accounting/vat-return' },
                    { labelKey: 'accounting_periods', path: '/admin/accounting/periods' },
                ],
            },
        ],
    },
    {
        labelKey: 'nav_label_inventory',
        items: [
            {
                key: 'inventory', labelKey: 'inventory', icon: Box,
                children: [
                    { labelKey: 'overview', path: '/admin/inventory' },
                    { labelKey: 'inventory_management', path: '/admin/stock' },
                    { labelKey: 'stock_movements', path: '/admin/inventory/movements' },
                ],
            },
            {
                key: 'wms', labelKey: 'wms', icon: Location,
                children: [
                    { labelKey: 'overview', path: '/admin/wms' },
                    { labelKey: 'warehouses', path: '/admin/wms/warehouses' },
                    { labelKey: 'bins', path: '/admin/wms/bins' },
                    { labelKey: 'picking_lists', path: '/admin/wms/picking' },
                    { labelKey: 'packing_lists', path: '/admin/wms/packing' },
                    { labelKey: 'cycle_counts', path: '/admin/wms/cycle-counts' },
                    { labelKey: 'trf_title', path: '/admin/wms/transfers' },
                    { labelKey: 'wms_performance', path: '/admin/wms/performance' },
                ],
            },
        ],
    },
    {
        labelKey: 'nav_label_hr',
        items: [
            {
                key: 'hr', labelKey: 'hr', icon: UserFilled,
                children: [
                    { labelKey: 'overview', path: '/admin/hr' },
                    { labelKey: 'employees', path: '/admin/hr/employees' },
                    { labelKey: 'employee_customer_relationship', path: '/admin/hr/employee-customers' },
                    { labelKey: 'attendance', path: '/admin/hr/attendance' },
                    { labelKey: 'leaves', path: '/admin/hr/leaves' },
                    { labelKey: 'payrolls', path: '/admin/hr/payrolls' },
                    { labelKey: 'commission_statement', path: '/admin/hr/commissions' },
                ],
            },
            {
                key: 'crm', labelKey: 'crm', icon: ChatDotRound,
                children: [
                    { labelKey: 'overview', path: '/admin/crm' },
                    { labelKey: 'customers', path: '/admin/crm/customers' },
                    { labelKey: 'tickets', path: '/admin/crm/tickets' },
                ],
            },
            {
                key: 'production', labelKey: 'production', icon: Tools,
                children: [
                    { labelKey: 'overview', path: '/admin/production' },
                ],
            },
        ],
    },
    {
        labelKey: 'nav_label_reports',
        items: [
            {
                key: 'reports', labelKey: 'reports', icon: DataAnalysis,
                children: [
                    { labelKey: 'overview', path: '/admin/reports' },
                    { labelKey: 'sales_report', path: '/admin/reports/sales' },
                    { labelKey: 'inventory_report', path: '/admin/reports/inventory' },
                    { labelKey: 'financial_report', path: '/admin/reports/financial' },
                    { labelKey: 'payroll_report', path: '/admin/reports/payroll' },
                ],
            },
            {
                key: 'bi_analytics', labelKey: 'bi_analytics', icon: DataAnalysis,
                children: [
                    { labelKey: 'overview', path: '/admin/analytics' },
                    { labelKey: 'analytics_sales', path: '/admin/analytics/sales' },
                    { labelKey: 'analytics_inventory', path: '/admin/analytics/inventory' },
                    { labelKey: 'analytics_warehouse', path: '/admin/analytics/warehouse' },
                    { labelKey: 'analytics_financial', path: '/admin/analytics/financial' },
                    { labelKey: 'analytics_metrics', path: '/admin/analytics/metrics' },
                    { labelKey: 'analytics_reports', path: '/admin/analytics/reports' },
                    { labelKey: 'analytics_dashboards', path: '/admin/analytics/dashboards' },
                ],
            },
            {
                key: 'workflows', labelKey: 'workflows', icon: Cpu,
                children: [
                    { labelKey: 'workflow_list', path: '/admin/workflows' },
                    { labelKey: 'create', path: '/admin/workflows/create' },
                ],
            },
        ],
    },
    {
        labelKey: 'nav_label_system',
        items: [
            {
                key: 'notifications_management', labelKey: 'notifications_management', icon: Bell,
                children: [
                    { labelKey: 'notification_logs', path: '/admin/notifications' },
                    { labelKey: 'notification_templates', path: '/admin/notifications/templates' },
                    { labelKey: 'notification_preferences', path: '/admin/notifications/preferences' },
                ],
            },
            {
                key: 'audit_logs', labelKey: 'audit_logs', icon: View,
                children: [
                    { labelKey: 'overview', path: '/admin/audit' },
                    { labelKey: 'entity_logs', path: '/admin/audit/entity-logs' },
                    { labelKey: 'audit_statistics', path: '/admin/audit/statistics' },
                ],
            },
            { key: 'pos', labelKey: 'pos', path: '/admin/pos', icon: Monitor },
            { key: 'inquiries', labelKey: 'inquiries', path: '/admin/inquiries', icon: ChatLineRound },
            { key: 'visitors', labelKey: 'visitors', path: '/admin/visitors', icon: View },
            {
                key: 'system', labelKey: 'system', icon: Setting,
                children: [
                    { labelKey: 'features', path: '/admin/features', masterOnly: true },
                    { labelKey: 'ac_nav_users', path: '/admin/users' },
                    { labelKey: 'roles', path: '/admin/roles' },
                    { labelKey: 'permissions', path: '/admin/permissions' },
                    { labelKey: 'currencies', path: '/admin/currencies' },
                ],
            },
        ],
    },
];

// Flat list of every navigable page, used for search and route matching.
const navIndex = menu.flatMap((section) => section.items.flatMap((entry) => (
    entry.children
        ? entry.children.map((child) => ({ ...child, icon: entry.icon, group: entry.key, groupLabelKey: entry.labelKey }))
        : [{ key: entry.key, labelKey: entry.labelKey, path: entry.path, icon: entry.icon }]
)));

const matchesRoute = (path) => route.path === path || route.path.startsWith(path + '/');

// Only the most specific menu entry is highlighted, so e.g. "Create invoice"
// doesn't also light up "Invoices" and the sales "Overview".
const activeEntry = computed(() => {
    let best = null;
    for (const item of navIndex) {
        if (matchesRoute(item.path) && (!best || item.path.length > best.path.length)) {
            best = item;
        }
    }
    return best;
});

const isActive = (path) => activeEntry.value?.path === path;

const activeGroup = computed(() => activeEntry.value?.group || null);

const openGroups = ref(activeGroup.value ? [activeGroup.value] : ['content']);

const isGroupOpen = (group) => {
    return openGroups.value.includes(group);
};

const isGroupActiveRoute = (group) => {
    return activeGroup.value === group;
};

const toggleGroup = (group) => {
    if (props.collapsed && !props.mobileOpen) {
        // Collapsed groups can't show labeled sub-items, so expand the
        // sidebar first instead of rendering an unreadable row of dots.
        emit('update:collapsed', false);
        localStorage.setItem('sidebarCollapsed', 'false');
    }

    const index = openGroups.value.indexOf(group);
    if (index > -1) {
        openGroups.value.splice(index, 1);
    } else {
        openGroups.value = [group];
    }
};

const navRef = ref(null);

const scrollActiveIntoView = () => {
    nextTick(() => {
        // Wait for the group's expand transition before measuring.
        setTimeout(() => {
            navRef.value?.querySelector('.nav-link.active')?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }, 240);
    });
};

watch(() => route.path, () => {
    const group = activeGroup.value;
    if (group && !openGroups.value.includes(group) && !isCollapsed.value) {
        openGroups.value = [group];
    }
    // The mobile drawer covers the page, so close it once a link is followed.
    closeMobile();
    scrollActiveIntoView();
});

const toggleSidebar = () => {
    emit('update:collapsed', !props.collapsed);
    localStorage.setItem('sidebarCollapsed', !props.collapsed);
};

watch(() => props.collapsed, (newVal) => {
    if (newVal) {
        openGroups.value = [];
    } else if (activeGroup.value && openGroups.value.length === 0) {
        openGroups.value = [activeGroup.value];
    }
});

// A collapsible group is shown when the signed-in role has been granted its
// feature on the Features Management screen. Super admins (admin / master)
// receive every enabled feature from the API, so they keep everything. The
// group keys are exactly the feature keys.
const canAccessGroup = (group) => authStore.canAccessFeature(group);

// Pages marked `masterOnly` (Features Management) are hidden from everyone
// but the master role, admins included. Every other page needs its page
// permission (config/pagePermissions.js), granted on the Roles screen.
const canSeeChild = (child) => {
    if (child.customerStatements) return authStore.customerStatementsOnly;
    if (child.masterOnly && !authStore.isMaster) return false;
    const permission = pagePermissionFor(child.path);
    return !permission || authStore.can(permission);
};

// Every entry is gated by its feature key — collapsible groups by the group
// key, plain entries (POS, inquiries, visitors) by their own key. Sections
// left with no visible entry drop their label too.
const visibleMenu = computed(() => menu
    .map((section) => ({
        ...section,
        items: section.items
            .filter((entry) => canAccessGroup(entry.key))
            .map((entry) => (entry.children
                ? { ...entry, children: entry.children.filter(canSeeChild) }
                : entry))
            // A group whose every page is withheld goes too.
            .filter((entry) => (entry.children ? entry.children.length > 0 : canSeeChild(entry))),
    }))
    .filter((section) => section.items.length > 0));

const searchQuery = ref('');
const searchInputRef = ref(null);
const highlightedIndex = ref(0);
const hasQuery = computed(() => searchQuery.value.trim() !== '');

const filteredNavItems = computed(() => {
    // matchesSearch folds Arabic letter variants, so "اصناف" finds "أصناف".
    if (!hasQuery.value) {
        return [];
    }

    return navIndex.filter((item) => {
        if (!canAccessGroup(item.group || item.key) || !canSeeChild(item)) {
            return false;
        }
        const groupLabel = item.groupLabelKey ? t(item.groupLabelKey) : '';
        return matchesSearch([t(item.labelKey), groupLabel], searchQuery.value);
    });
});

watch(searchQuery, () => {
    highlightedIndex.value = 0;
});

const clearSearch = () => {
    searchQuery.value = '';
    searchInputRef.value?.focus();
};

const onSearchNavigate = () => {
    searchQuery.value = '';
    closeMobile();
};

const moveHighlight = (step) => {
    const count = filteredNavItems.value.length;
    if (!count) {
        return;
    }
    highlightedIndex.value = (highlightedIndex.value + step + count) % count;
    nextTick(() => {
        navRef.value?.querySelector('.search-results .nav-link.highlighted')?.scrollIntoView({ block: 'nearest' });
    });
};

const onSearchKeydown = (event) => {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        moveHighlight(1);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        moveHighlight(-1);
    } else if (event.key === 'Enter') {
        const item = filteredNavItems.value[highlightedIndex.value];
        if (item) {
            event.preventDefault();
            router.push(item.path);
            onSearchNavigate();
            searchInputRef.value?.blur();
        }
    } else if (event.key === 'Escape') {
        if (searchQuery.value) {
            searchQuery.value = '';
        } else {
            searchInputRef.value?.blur();
        }
    }
};

const openSearch = () => {
    if (isCollapsed.value) {
        emit('update:collapsed', false);
        localStorage.setItem('sidebarCollapsed', 'false');
    }
    nextTick(() => searchInputRef.value?.focus());
};

// "/" jumps to the menu search from anywhere that isn't a text field.
// Matching on the physical key keeps it working on the Arabic layout.
const onGlobalKeydown = (event) => {
    if (event.ctrlKey || event.metaKey || event.altKey) {
        return;
    }
    if (event.key !== '/' && event.code !== 'Slash') {
        return;
    }
    const target = event.target;
    if (target?.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target?.tagName)) {
        return;
    }
    // Hidden off-canvas on small screens unless the drawer is open.
    if (!props.mobileOpen && window.matchMedia('(max-width: 992px)').matches) {
        return;
    }
    event.preventDefault();
    openSearch();
};

const onWindowResize = () => {
    if (props.mobileOpen && window.innerWidth > 992) {
        closeMobile();
    }
};

onMounted(() => {
    document.addEventListener('keydown', onGlobalKeydown);
    window.addEventListener('resize', onWindowResize, { passive: true });
    scrollActiveIntoView();
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onGlobalKeydown);
    window.removeEventListener('resize', onWindowResize);
});

const userName = computed(() => authStore.user?.name || t('system_administrator'));
const userEmail = computed(() => authStore.user?.email || '');
const userRoleTitle = computed(() => {
    if (authStore.isMaster) return t('role_master') || (locale.value === 'ar' ? 'المدير الرئيسي' : 'Master Admin');
    if (authStore.isSuperAdmin) return t('role_superadmin') || (locale.value === 'ar' ? 'مدير النظام' : 'Super Admin');
    return authStore.roleNames?.[0] || authStore.user?.email || '';
});
const userInitials = computed(() => {
    const name = (authStore.user?.name || t('system_administrator')).trim();
    const parts = name.split(/\s+/).filter(Boolean);
    const first = (parts[0] || '')[0] || '';
    const second = parts[1]?.[0] || (parts[0] || '')[1] || '';
    return (first + second).toUpperCase() || 'م';
});
</script>

<style scoped>
.admin-sidebar {
    --sb-accent: #2dd4bf;
    --sb-accent-2: #67e8f9;
    --sb-accent-soft: rgba(45, 212, 191, 0.14);
    --sb-accent-border: rgba(45, 212, 191, 0.32);
    --sb-glow: rgba(45, 212, 191, 0.35);
    --sb-text: rgba(203, 213, 225, 0.68);
    --sb-text-strong: rgba(241, 245, 249, 0.94);

    width: 268px;
    background:
        radial-gradient(120% 45% at 50% -8%, rgba(45, 212, 191, 0.10), transparent 60%),
        radial-gradient(90% 40% at 0% 110%, rgba(129, 140, 248, 0.07), transparent 60%),
        linear-gradient(180deg, #0a0f1e 0%, #0d1526 50%, #0f1a2e 100%);
    color: var(--sb-text);
    position: fixed;
    right: 0 !important;
    left: auto !important;
    top: 0;
    height: 100vh;
    z-index: 1000;
    transition: width 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
    direction: rtl !important;
    text-align: right;
    box-shadow: inset 1px 0 0 rgba(255, 255, 255, 0.04), 0 0 40px rgba(0, 0, 0, 0.35);
}

.admin-sidebar.collapsed {
    width: 72px;
}

.sidebar-header {
    padding: 1.1rem 1.15rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    display: flex;
    justify-content: space-between;
    align-items: center;
    min-height: 68px;
    gap: 0.5rem;
}

.admin-sidebar.collapsed .sidebar-header {
    justify-content: center;
    padding: 1.1rem 0.75rem;
}

.sidebar-header :deep(.collapse-btn) {
    flex-shrink: 0;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.08);
    color: rgba(226, 232, 240, 0.72);
    transition: all 0.2s ease;
}

.sidebar-header :deep(.collapse-btn:hover) {
    background: var(--sb-accent-soft);
    border-color: var(--sb-accent-border);
    color: var(--sb-accent);
    box-shadow: 0 0 16px -4px var(--sb-glow);
}

.brand {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    color: white;
    min-width: 0;
}

.brand-mark {
    position: relative;
    flex-shrink: 0;
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #0d9488 0%, #0891b2 100%);
    color: #ecfeff;
    box-shadow: 0 6px 18px -4px var(--sb-glow), inset 0 1px 0 rgba(255, 255, 255, 0.25);
    border: 1px solid rgba(255, 255, 255, 0.14);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.brand:hover .brand-mark {
    transform: scale(1.04);
    box-shadow: 0 8px 22px -4px var(--sb-glow);
}

.brand-mark-glow {
    position: absolute;
    inset: 0;
    border-radius: 12px;
    background: radial-gradient(90% 90% at 30% 20%, rgba(255, 255, 255, 0.35), transparent 60%);
    pointer-events: none;
}

.brand-text {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
    line-height: 1.1;
}

.brand-name {
    font-weight: 700;
    font-size: 1rem;
    color: var(--sb-text-strong);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.brand-tag {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.62rem;
    font-weight: 600;
    letter-spacing: 0.16em;
    color: rgba(148, 163, 184, 0.7);
    text-transform: uppercase;
}

.brand-dot {
    width: 6px;
    height: 6px;
    border-radius: 999px;
    background: var(--sb-accent);
    box-shadow: 0 0 8px var(--sb-glow);
    animation: pulse-dot 2.5s infinite ease-in-out;
}

@keyframes pulse-dot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.55; transform: scale(0.85); }
}

.sidebar-nav {
    flex: 1;
    padding: 0.35rem 0.65rem 0.75rem;
    overflow-y: auto;
    overflow-x: hidden;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.14) transparent;
}

.sidebar-nav::-webkit-scrollbar {
    width: 5px;
}

.sidebar-nav::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.14);
    border-radius: 999px;
}

.sidebar-nav::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.22);
}

.nav-search {
    position: relative;
    display: flex;
    align-items: center;
    margin: 0.35rem 0.2rem 0.75rem;
    padding: 0 0.7rem;
    border-radius: 10px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(255, 255, 255, 0.04);
    transition: border-color 0.18s ease, background 0.18s ease, box-shadow 0.18s ease;
}

.nav-search:focus-within {
    border-color: var(--sb-accent-border);
    background: rgba(255, 255, 255, 0.06);
    box-shadow: 0 0 0 3px var(--sb-accent-soft);
}

.nav-search-icon {
    flex-shrink: 0;
    font-size: 0.9rem;
    color: rgba(148, 163, 184, 0.6);
}

.nav-search-input {
    flex: 1;
    min-width: 0;
    border: none;
    outline: none;
    background: transparent;
    color: var(--sb-text-strong);
    font-size: 0.83rem;
    padding: 0.55rem 0.6rem;
    font-family: inherit;
}

.nav-search-input::placeholder {
    color: rgba(148, 163, 184, 0.55);
}

.nav-search-clear {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border: none;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.06);
    color: rgba(226, 232, 240, 0.7);
    cursor: pointer;
    font-size: 0.7rem;
    transition: background 0.18s ease, color 0.18s ease;
}

.nav-search-clear:hover {
    background: var(--sb-accent-soft);
    color: var(--sb-accent);
}

.search-result-text {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    line-height: 1.25;
}

.search-result-group {
    font-size: 0.68rem;
    font-weight: 500;
    color: rgba(148, 163, 184, 0.55);
}

.nav-search-kbd {
    flex-shrink: 0;
    min-width: 20px;
    height: 20px;
    padding: 0 0.3rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(255, 255, 255, 0.04);
    color: rgba(148, 163, 184, 0.6);
    font-family: inherit;
    font-size: 0.68rem;
    line-height: 1;
}

.nav-search:focus-within .nav-search-kbd {
    display: none;
}

.nav-search-trigger {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 38px;
    margin: 0.35rem 0 0.5rem;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.04);
    color: rgba(148, 163, 184, 0.75);
    cursor: pointer;
    font-size: 1rem;
    transition: background 0.18s ease, color 0.18s ease, border-color 0.18s ease;
}

.nav-search-trigger:hover {
    background: var(--sb-accent-soft);
    border-color: var(--sb-accent-border);
    color: var(--sb-accent);
}

.search-results .nav-link.highlighted:not(.active) {
    background: rgba(255, 255, 255, 0.07);
    color: var(--sb-text-strong);
}

.nav-empty {
    padding: 1.2rem 0.85rem;
    text-align: center;
    font-size: 0.8rem;
    color: rgba(148, 163, 184, 0.55);
}

.nav-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.nav-section-label {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 1.25rem 0.85rem 0.45rem;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: rgba(148, 163, 184, 0.55);
    white-space: nowrap;
    overflow: hidden;
}

.nav-section-label::after {
    content: '';
    flex: 1;
    height: 1px;
    background: linear-gradient(90deg, rgba(255, 255, 255, 0.08), transparent);
}

.nav-section-label:first-child {
    padding-top: 0.4rem;
}

.nav-item,
.nav-group {
    margin-bottom: 0.125rem;
}

.nav-link,
.nav-group-header {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.7rem;
    padding: 0.62rem 0.8rem;
    color: var(--sb-text);
    text-decoration: none;
    cursor: pointer;
    border-radius: 10px;
    font-size: 0.95rem;
    font-weight: 600;
    border: 1px solid transparent;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.nav-link:hover,
.nav-group-header:hover {
    background: rgba(255, 255, 255, 0.06);
    color: var(--sb-text-strong);
    transform: translateX(-2px);
}

.nav-group-count {
    margin-inline-start: auto;
    padding: 0.1rem 0.45rem;
    font-size: 0.68rem;
    font-weight: 600;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.06);
    color: rgba(148, 163, 184, 0.7);
    transition: all 0.2s ease;
}

.nav-group-header:hover .nav-group-count {
    background: rgba(255, 255, 255, 0.12);
    color: #ffffff;
}

.nav-link.active {
    color: var(--sb-accent);
    background: var(--sb-accent-soft);
    border-color: var(--sb-accent-border);
    font-weight: 700;
    box-shadow: 0 8px 20px -12px var(--sb-glow);
}

.nav-link.active::before {
    content: '';
    position: absolute;
    inset-block: 9px;
    inset-inline-start: 0;
    width: 3px;
    border-radius: 999px;
    background: linear-gradient(180deg, var(--sb-accent), var(--sb-accent-2));
    box-shadow: 0 0 10px var(--sb-glow);
}

.nav-ic {
    flex-shrink: 0;
    font-size: 1.12rem;
    opacity: 0.85;
    transition: transform 0.2s ease, opacity 0.2s ease, color 0.2s ease;
}

.nav-link:hover .nav-ic,
.nav-group-header:hover .nav-ic {
    opacity: 1;
}

.nav-link.active .nav-ic {
    color: var(--sb-accent);
    opacity: 1;
}

.nav-text {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.nav-group.open > .nav-group-header {
    color: var(--sb-text-strong);
    background: rgba(255, 255, 255, 0.04);
}

.nav-group.open > .nav-group-header .nav-ic {
    color: var(--sb-accent);
    opacity: 1;
}

.nav-group.has-active-child:not(.open) > .nav-group-header {
    color: var(--sb-text-strong);
}

.nav-group.has-active-child:not(.open) > .nav-group-header .nav-ic {
    color: var(--sb-accent);
    opacity: 1;
}

.nav-link:focus-visible,
.nav-group-header:focus-visible,
.nav-search-input:focus-visible,
.nav-search-clear:focus-visible,
.nav-search-trigger:focus-visible,
.collapse-btn:focus-visible {
    outline: 2px solid var(--sb-accent);
    outline-offset: 2px;
}

.toggle-icon {
    margin-inline-start: auto;
    transition: transform 0.25s ease;
    font-size: 0.68rem;
    color: rgba(148, 163, 184, 0.5);
}

.toggle-icon.rotated {
    transform: rotate(180deg);
    color: var(--sb-accent);
}

.nav-group-body {
    display: grid;
    grid-template-rows: 0fr;
    transition: grid-template-rows 0.22s cubic-bezier(0.4, 0, 0.2, 1);
}

.nav-group-body.open {
    grid-template-rows: 1fr;
}

.nav-group-items {
    list-style: none;
    padding: 0;
    margin: 0;
    position: relative;
    min-height: 0;
    overflow: hidden;
}

.nav-group-body.open .nav-group-items {
    padding: 0.15rem 0 0.4rem;
}

/* Vertical guide line tying sub-items to their group. */
.nav-group-items::before {
    content: '';
    position: absolute;
    inset-block: 0.35rem 0.6rem;
    inset-inline-start: calc(0.8rem + 0.5rem);
    width: 1px;
    background: rgba(255, 255, 255, 0.07);
}

.nav-group-items .sub-dot {
    position: relative;
    margin-inline-start: 0.34rem;
}

.admin-sidebar.collapsed .nav-group-body {
    display: none;
}

.collapsed-active-dot {
    position: absolute;
    top: 7px;
    inset-inline-end: 12px;
    width: 6px;
    height: 6px;
    border-radius: 999px;
    background: var(--sb-accent);
    box-shadow: 0 0 8px var(--sb-glow);
}

.nav-group-items .nav-link {
    padding-block: 0.5rem;
    padding-inline: 1.55rem 0.8rem;
    font-size: 0.9rem;
    font-weight: 600;
    color: rgba(148, 163, 184, 0.62);
    border: none;
    background: transparent;
    box-shadow: none;
}

.nav-group-items .nav-link:hover {
    color: var(--sb-text-strong);
    background: rgba(255, 255, 255, 0.045);
}

.nav-group-items .nav-link.active {
    color: var(--sb-accent);
    background: transparent;
    font-weight: 700;
}

.nav-group-items .nav-link.active::before {
    display: none;
}

.sub-dot {
    flex-shrink: 0;
    width: 5px;
    height: 5px;
    border-radius: 999px;
    background: rgba(148, 163, 184, 0.35);
    transition: background 0.2s ease, box-shadow 0.2s ease;
}

.nav-group-items .nav-link:hover .sub-dot {
    background: var(--sb-accent);
}

.nav-group-items .nav-link.active .sub-dot {
    background: var(--sb-accent);
    box-shadow: 0 0 8px var(--sb-glow);
}

.sidebar-footer {
    padding: 0.85rem;
    border-top: 1px solid rgba(255, 255, 255, 0.06);
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0.045));
}

.user-card {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    padding: 0.55rem 0.65rem;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid rgba(255, 255, 255, 0.06);
    background: rgba(255, 255, 255, 0.03);
}

.user-card:hover {
    background: rgba(255, 255, 255, 0.06);
    border-color: rgba(255, 255, 255, 0.12);
    transform: translateY(-1px);
}

.sidebar-footer.collapsed .user-card {
    justify-content: center;
    padding: 0.5rem;
    border: none;
    background: transparent;
}

.user-avatar-wrap {
    position: relative;
    flex-shrink: 0;
}

.user-online-dot {
    position: absolute;
    bottom: -1px;
    inset-inline-end: -1px;
    width: 9px;
    height: 9px;
    border-radius: 999px;
    background: #10b981;
    border: 2px solid #0d1527;
}

.user-avatar {
    flex-shrink: 0;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.82rem;
    font-weight: 700;
    color: #062a24;
    background: linear-gradient(135deg, var(--sb-accent) 0%, var(--sb-accent-2) 100%);
    box-shadow: 0 4px 12px -4px var(--sb-glow), inset 0 1px 0 rgba(255, 255, 255, 0.4);
}

.user-meta {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    min-width: 0;
    flex: 1;
}

.user-name {
    font-size: 0.83rem;
    font-weight: 600;
    color: var(--sb-text-strong);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-role {
    font-size: 0.68rem;
    color: rgba(148, 163, 184, 0.65);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-action {
    flex-shrink: 0;
    width: 30px;
    height: 30px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(148, 163, 184, 0.7);
    text-decoration: none;
    transition: all 0.2s ease;
}

.user-action:hover {
    color: var(--sb-accent);
    background: var(--sb-accent-soft);
}

.user-action.active {
    color: var(--sb-accent);
}

.admin-sidebar.collapsed .nav-link,
.admin-sidebar.collapsed .nav-group-header {
    justify-content: center;
    padding-inline: 0;
}

.admin-sidebar.collapsed .nav-link.active::before {
    inset-inline-start: 0;
}

.admin-sidebar.collapsed .nav-section-label {
    display: none;
}

.admin-sidebar .mobile-close-btn {
    display: none;
}

@media (max-width: 992px) {
    .admin-sidebar {
        right: 0 !important;
        left: auto !important;
        transform: translateX(105%) !important;
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: none;
        max-width: 85vw;
    }

    .admin-sidebar.mobile-open {
        transform: translateX(0) !important;
        box-shadow: -4px 0 24px rgba(0, 0, 0, 0.45) !important;
    }

    .admin-sidebar .mobile-close-btn {
        display: inline-flex;
    }

    .admin-sidebar.collapsed {
        width: 268px;
    }

    .admin-sidebar.collapsed .nav-section-label {
        display: block;
    }

    .admin-sidebar.collapsed .nav-link,
    .admin-sidebar.collapsed .nav-group-header {
        justify-content: flex-start;
        padding-inline: 0.8rem;
    }

    .admin-sidebar.collapsed .nav-group-body {
        display: grid;
    }

    .admin-sidebar.collapsed .nav-text,
    .admin-sidebar.collapsed .toggle-icon {
        display: inline-flex;
    }

    .admin-sidebar.collapsed .sidebar-header {
        justify-content: space-between;
        padding: 1.1rem 1.15rem;
    }

    .admin-sidebar.collapsed .sidebar-footer {
        padding: 0.85rem;
    }

    .admin-sidebar.collapsed .user-card {
        justify-content: flex-start;
        padding: 0.55rem 0.65rem;
        border: 1px solid rgba(255, 255, 255, 0.06);
        background: rgba(255, 255, 255, 0.03);
    }

    .admin-sidebar.collapsed .user-meta,
    .admin-sidebar.collapsed .user-action {
        display: inline-flex;
    }
}
</style>

<style>
/* Flyout for groups while the sidebar is collapsed. Element Plus teleports
   poppers to <body>, so these can't live in the scoped block above. */
.el-popover.el-popper.sidebar-flyout {
    padding: 0.5rem;
    border-radius: 14px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(11, 19, 36, 0.96);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    box-shadow: 0 20px 45px -10px rgba(0, 0, 0, 0.65);
    color: rgba(203, 213, 225, 0.8);
    max-height: 80vh;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.16) transparent;
    direction: rtl !important;
    text-align: right !important;
}

.sidebar-flyout .flyout-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.45rem 0.75rem 0.55rem;
    margin-bottom: 0.35rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    font-size: 0.8rem;
    font-weight: 700;
    color: #ffffff;
}

.sidebar-flyout .flyout-count {
    font-size: 0.68rem;
    font-weight: 600;
    padding: 0.1rem 0.45rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.08);
    color: rgba(203, 213, 225, 0.7);
}

.sidebar-flyout .flyout-link {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.5rem 0.75rem;
    border-radius: 8px;
    font-size: 0.88rem;
    font-weight: 600;
    color: rgba(203, 213, 225, 0.75);
    text-decoration: none;
    transition: all 0.15s ease;
}

.sidebar-flyout .flyout-link:hover,
.sidebar-flyout .flyout-link:focus-visible {
    background: rgba(255, 255, 255, 0.07);
    color: #ffffff;
    transform: translateX(-2px);
    outline: none;
}

.sidebar-flyout .flyout-link.active {
    color: #2dd4bf;
    background: rgba(45, 212, 191, 0.14);
    font-weight: 700;
}

.sidebar-flyout .flyout-dot {
    flex-shrink: 0;
    width: 5px;
    height: 5px;
    border-radius: 999px;
    background: rgba(148, 163, 184, 0.4);
    transition: all 0.15s ease;
}

.sidebar-flyout .flyout-link.active .flyout-dot,
.sidebar-flyout .flyout-link:hover .flyout-dot {
    background: #2dd4bf;
    box-shadow: 0 0 6px rgba(45, 212, 191, 0.5);
}
</style>
