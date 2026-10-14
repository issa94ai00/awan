<template>
    <div class="users-page">
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-info">
                <span class="eyebrow">{{ $t('ac_eyebrow') }}</span>
                <h2>{{ $t('acu_title') }}</h2>
                <p class="subtitle">{{ $t('acu_subtitle') }}</p>
            </div>
            <div class="header-actions">
                <el-button :icon="Refresh" class="header-btn" :loading="loading" @click="reloadAll">
                    {{ $t('ac_refresh') }}
                </el-button>
                <el-button
                    v-if="auth.can('users.create')"
                    type="primary"
                    :icon="Plus"
                    class="header-btn"
                    @click="openCreate"
                >
                    {{ $t('acu_new_user') }}
                </el-button>
            </div>
        </div>

        <!-- KPI cards double as scope filters -->
        <div class="stats-grid">
            <div
                v-for="card in statCards"
                :key="card.key"
                class="stat-card"
                :class="{ active: filters.scope === card.scope }"
                @click="setScope(card.scope)"
            >
                <div class="icon-wrap" :class="card.tone"><el-icon><component :is="card.icon" /></el-icon></div>
                <div class="stat-body">
                    <span class="stat-label">{{ $t(card.label) }}</span>
                    <strong>{{ stats[card.key] ?? 0 }}</strong>
                </div>
                <div class="stat-indicator"></div>
            </div>
        </div>

        <!-- Toolbar -->
        <el-card shadow="never" class="toolbar-card">
            <div class="toolbar">
                <el-input
                    v-model="filters.search"
                    :placeholder="$t('acu_search')"
                    :prefix-icon="Search"
                    clearable
                    class="search-input"
                />
                <el-select
                    v-model="filters.role_id"
                    :placeholder="$t('acu_filter_role')"
                    clearable
                    filterable
                    class="role-select"
                    @change="applyFilters"
                >
                    <el-option v-for="role in roles" :key="role.id" :label="role.display_name" :value="role.id">
                        <span class="option-dot" :style="{ background: role.color || '#94a3b8' }"></span>
                        {{ role.display_name }}
                    </el-option>
                </el-select>
                <el-radio-group v-model="filters.scope" class="scope-switch" @change="applyFilters">
                    <el-radio-button v-for="scope in scopes" :key="scope" :value="scope">
                        {{ $t('acu_scope_' + scope) }}
                    </el-radio-button>
                </el-radio-group>
            </div>
        </el-card>

        <!-- Users table -->
        <el-card shadow="never" class="table-card">
            <transition name="bulk">
                <div v-if="selected.length" class="bulk-bar">
                    <span class="bulk-count">{{ $t('acu_bulk_selected', { n: selected.length }) }}</span>
                    <el-select
                        v-model="bulk.roleIds"
                        multiple
                        collapse-tags
                        collapse-tags-tooltip
                        filterable
                        :placeholder="$t('acu_bulk_pick_roles')"
                        class="bulk-roles"
                    >
                        <el-option
                            v-for="role in roles"
                            :key="role.id"
                            :label="role.display_name"
                            :value="role.id"
                            :disabled="!!roleLockReason(role)"
                        >
                            <span class="option-dot" :style="{ background: role.color || '#94a3b8' }"></span>
                            {{ role.display_name }}
                        </el-option>
                    </el-select>
                    <el-button type="primary" :icon="Plus" :disabled="!bulk.roleIds.length" :loading="bulk.busy === 'add'" @click="runBulk('add')">
                        {{ $t('acu_bulk_add') }}
                    </el-button>
                    <el-button type="danger" plain :icon="Minus" :disabled="!bulk.roleIds.length" :loading="bulk.busy === 'remove'" @click="runBulk('remove')">
                        {{ $t('acu_bulk_remove') }}
                    </el-button>
                    <el-button text @click="clearSelection">{{ $t('acu_bulk_clear') }}</el-button>
                </div>
            </transition>
            <div class="table-scroll">
                <el-table
                    ref="tableRef"
                    v-loading="loading"
                    :data="users"
                    style="width: 100%; min-width: 820px"
                    row-key="id"
                    @selection-change="(rows) => (selected = rows)"
                >
                    <el-table-column
                        v-if="auth.can('users.assign_roles')"
                        type="selection"
                        width="44"
                        reserve-selection
                        :selectable="(row) => row.editable"
                    />
                    <el-table-column :label="$t('acu_col_user')" min-width="260">
                        <template #default="{ row }">
                            <div class="user-cell">
                                <div class="avatar" :style="{ background: avatarColor(row) }">{{ initial(row.name) }}</div>
                                <div class="user-meta">
                                    <div class="user-name">
                                        {{ row.name }}
                                        <el-tag v-if="row.is_self" size="small" effect="plain" round>{{ $t('acu_you') }}</el-tag>
                                    </div>
                                    <small>{{ row.email }}</small>
                                </div>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('acu_col_roles')" min-width="280">
                        <template #default="{ row }">
                            <div class="tag-row">
                                <!-- A super role already shows as its own chip; only the
                                     account-level is_admin flag needs a badge of its own. -->
                                <el-tooltip
                                    v-if="row.is_admin_flag && !row.roles.some((role) => isSuperRoleName(role.name))"
                                    :content="$t('acu_admin_flag_hint')"
                                    placement="top"
                                >
                                    <el-tag size="small" effect="dark" type="danger" class="badge-tag">
                                        <el-icon class="primary-star"><Lock /></el-icon>{{ $t('acu_admin_flag') }}
                                    </el-tag>
                                </el-tooltip>
                                <span
                                    v-for="role in row.roles"
                                    :key="role.id"
                                    class="role-chip"
                                    :class="{ inactive: !role.is_active }"
                                    :style="chipStyle(role.color)"
                                >
                                    <el-icon v-if="role.id === row.primary_role_id" class="primary-star"><StarFilled /></el-icon>
                                    <el-icon v-else-if="isSuperRoleName(role.name)" class="primary-star"><Lock /></el-icon>
                                    {{ role.display_name }}
                                </span>
                                <span v-if="!row.roles.length && !row.is_admin_flag" class="muted">{{ $t('acu_no_roles') }}</span>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('acu_col_direct')" width="110" align="center">
                        <template #default="{ row }">
                            <el-tag v-if="row.permission_ids.length" type="success" effect="light" round>
                                +{{ row.permission_ids.length }}
                            </el-tag>
                            <span v-else class="muted">—</span>
                        </template>
                    </el-table-column>

                    <el-table-column prop="created_at" :label="$t('acu_col_created')" width="130" />

                    <el-table-column
                        v-if="auth.can('users.assign_roles')"
                        :label="$t('acu_col_actions')"
                        width="190"
                        align="center"
                    >
                        <template #default="{ row }">
                            <el-tooltip :disabled="row.editable" :content="$t('acu_not_editable')" placement="top">
                                <span>
                                    <el-button
                                        size="small"
                                        type="primary"
                                        plain
                                        :icon="row.editable ? EditPen : Lock"
                                        :disabled="!row.editable"
                                        @click="openEditor(row)"
                                    >
                                        {{ $t('acu_edit_access') }}
                                    </el-button>
                                </span>
                            </el-tooltip>
                        </template>
                    </el-table-column>

                    <template #empty>
                        <el-empty :description="$t('ac_no_results')" :image-size="90" />
                    </template>
                </el-table>
            </div>

            <div v-if="pagination.total > pagination.per_page" class="pager">
                <el-pagination
                    v-model:current-page="pagination.current_page"
                    :page-size="pagination.per_page"
                    :total="pagination.total"
                    layout="prev, pager, next, total"
                    background
                    @current-change="fetchUsers"
                />
            </div>
        </el-card>

        <!-- Edit drawer -->
        <el-drawer
            v-model="drawer.visible"
            :size="drawerSize"
            :before-close="confirmCloseDrawer"
            class="access-drawer"
            destroy-on-close
        >
            <template #header>
                <div v-if="drawer.user" class="drawer-head">
                    <div class="avatar large" :style="{ background: avatarColor(drawer.user) }">{{ initial(drawer.user.name) }}</div>
                    <div>
                        <h3>{{ drawer.user.name }}</h3>
                        <small>{{ drawer.user.email }}</small>
                    </div>
                </div>
            </template>

            <div v-loading="drawer.loading" class="drawer-body">
                <el-alert
                    v-if="catalogueFailed"
                    type="warning"
                    :closable="false"
                    show-icon
                    :title="$t('acu_catalogue_unavailable')"
                    class="mb-12"
                />
                <el-alert
                    v-if="liveIsSuper"
                    type="info"
                    :closable="false"
                    show-icon
                    :title="$t('ac_super_note')"
                    class="mb-12"
                />

                <el-tabs v-model="drawer.tab" class="drawer-tabs">
                    <!-- Roles -->
                    <el-tab-pane name="roles">
                        <template #label>
                            <span class="tab-label">{{ $t('acu_tab_roles') }}
                                <el-badge :value="form.roleIds.length" type="primary" class="tab-badge" />
                            </span>
                        </template>
                        <p class="hint">{{ $t('acu_roles_hint') }}</p>
                        <div class="role-grid">
                            <div
                                v-for="role in roles"
                                :key="role.id"
                                class="role-card"
                                :class="{ checked: isRoleChecked(role.id), locked: roleLockReason(role) }"
                                @click="toggleRole(role)"
                            >
                                <el-tooltip :disabled="!roleLockReason(role)" :content="roleLockReason(role)" placement="top">
                                    <div class="role-card-inner">
                                        <el-checkbox
                                            :model-value="isRoleChecked(role.id)"
                                            :disabled="!!roleLockReason(role)"
                                            @click.stop
                                            @change="toggleRole(role)"
                                        />
                                        <span class="role-dot" :style="{ background: role.color || '#94a3b8' }"></span>
                                        <div class="role-card-text">
                                            <div class="role-card-title">
                                                {{ role.display_name }}
                                                <el-tag v-if="role.is_super" size="small" type="danger" effect="light">{{ $t('ac_super_badge') }}</el-tag>
                                                <el-tag v-if="!role.is_active" size="small" type="info" effect="light">{{ $t('ac_inactive_badge') }}</el-tag>
                                            </div>
                                            <small>
                                                {{ $t('acu_permissions_count', { n: role.is_super ? totalPermissions : role.permissions_count }) }}
                                                · {{ $t('acu_users_count', { n: role.users_count }) }}
                                            </small>
                                        </div>
                                        <button
                                            v-if="isRoleChecked(role.id)"
                                            type="button"
                                            class="primary-toggle"
                                            :class="{ on: form.primaryRoleId === role.id }"
                                            :title="$t('acu_set_primary')"
                                            @click.stop="form.primaryRoleId = role.id"
                                        >
                                            <el-icon><StarFilled v-if="form.primaryRoleId === role.id" /><Star v-else /></el-icon>
                                            <span>{{ form.primaryRoleId === role.id ? $t('ac_primary_badge') : $t('acu_set_primary') }}</span>
                                        </button>
                                    </div>
                                </el-tooltip>
                            </div>
                        </div>
                    </el-tab-pane>

                    <!-- Direct permissions -->
                    <el-tab-pane name="direct">
                        <template #label>
                            <span class="tab-label">{{ $t('acu_tab_direct') }}
                                <el-badge v-if="form.permissionIds.length" :value="form.permissionIds.length" type="success" class="tab-badge" />
                            </span>
                        </template>
                        <p class="hint">{{ $t('acu_direct_hint') }}</p>
                        <div class="panel-tools">
                            <el-input v-model="directSearch" :placeholder="$t('ac_search_permissions')" :prefix-icon="Search" clearable />
                            <el-button text @click="setAllOpen(true)">{{ $t('ac_expand_all') }}</el-button>
                            <el-button text @click="setAllOpen(false)">{{ $t('ac_collapse_all') }}</el-button>
                        </div>

                        <el-collapse v-model="openModules" class="module-collapse">
                            <el-collapse-item v-for="group in filteredModules" :key="group.module" :name="group.module">
                                <template #title>
                                    <div class="module-title" @click.stop>
                                        <el-checkbox
                                            :model-value="moduleState(group).all"
                                            :indeterminate="moduleState(group).some"
                                            @change="(value) => toggleModule(group, value)"
                                        />
                                    </div>
                                    <span class="module-name">{{ moduleLabel(group.module) }}</span>
                                    <span class="module-count">{{ moduleState(group).count }}/{{ group.permissions.length }}</span>
                                </template>
                                <div class="perm-list">
                                    <div v-for="perm in group.permissions" :key="perm.id" class="perm-row">
                                        <el-checkbox
                                            :model-value="form.permissionIds.includes(perm.id)"
                                            @change="(value) => toggleDirect(perm.id, value)"
                                        />
                                        <div class="perm-text" @click="toggleDirect(perm.id, !form.permissionIds.includes(perm.id))">
                                            <span class="perm-name">{{ perm.display_name }}</span>
                                            <code>{{ perm.name }}</code>
                                        </div>
                                        <span v-if="rolesGranting(perm.id).length" class="via-hint" :title="$t('acu_redundant')">
                                            {{ $t('acu_via_role', { role: rolesGranting(perm.id).map((r) => r.display_name).join('، ') }) }}
                                        </span>
                                        <el-tag v-if="!perm.is_active" size="small" type="info">{{ $t('acu_perm_inactive') }}</el-tag>
                                    </div>
                                </div>
                            </el-collapse-item>
                        </el-collapse>
                        <el-empty v-if="!filteredModules.length" :description="$t('ac_no_results')" :image-size="70" />
                    </el-tab-pane>

                    <!-- Effective access -->
                    <el-tab-pane name="effective">
                        <template #label>
                            <span class="tab-label">{{ $t('acu_tab_effective') }}</span>
                        </template>
                        <div class="effective-summary">
                            <el-progress
                                type="circle"
                                :width="64"
                                :stroke-width="7"
                                :percentage="totalPermissions ? Math.round((effectiveGrantedCount / totalPermissions) * 100) : 0"
                            />
                            <div>
                                <strong>{{ $t('acu_effective_summary', { granted: effectiveGrantedCount, total: totalPermissions }) }}</strong>
                                <p class="hint">{{ $t('acu_effective_hint') }}</p>
                            </div>
                            <el-switch v-model="onlyGranted" :active-text="$t('acu_only_granted')" class="only-granted" />
                        </div>

                        <div v-for="group in effectiveModules" :key="group.module" class="effective-group">
                            <div class="effective-head">
                                <span class="module-name">{{ moduleLabel(group.module) }}</span>
                                <span class="module-count">{{ group.granted }}/{{ group.total }}</span>
                            </div>
                            <div
                                v-for="perm in group.items"
                                :key="perm.id"
                                class="effective-row"
                                :class="{ denied: !perm.granted }"
                            >
                                <el-icon class="state-icon"><CircleCheckFilled v-if="perm.granted" /><CircleCloseFilled v-else /></el-icon>
                                <div class="perm-text">
                                    <span class="perm-name">{{ perm.display_name }}</span>
                                    <code>{{ perm.name }}</code>
                                </div>
                                <div class="source-chips">
                                    <el-tag v-if="!perm.is_active" size="small" type="info">{{ $t('acu_perm_inactive') }}</el-tag>
                                    <template v-else-if="liveIsSuper">
                                        <el-tag size="small" type="danger" effect="light">{{ $t('ac_super_badge') }}</el-tag>
                                    </template>
                                    <template v-else>
                                        <span
                                            v-for="src in perm.roles"
                                            :key="src.id"
                                            class="role-chip small"
                                            :class="{ inactive: !src.is_active }"
                                            :style="chipStyle(src.color)"
                                        >{{ src.display_name }}</span>
                                        <el-tag v-if="perm.direct" size="small" type="success" effect="light">{{ $t('ac_direct_badge') }}</el-tag>
                                        <span v-if="!perm.granted" class="muted small">{{ $t('acu_denied') }}</span>
                                    </template>
                                </div>
                            </div>
                        </div>
                        <el-empty v-if="!effectiveModules.length" :description="$t('ac_no_results')" :image-size="70" />
                    </el-tab-pane>

                    <!-- History -->
                    <el-tab-pane v-if="drawer.user" :label="$t('acu_tab_history')" name="history" lazy>
                        <AccessActivity :params="{ user_id: drawer.user.id }" :reload-key="historyKey" />
                    </el-tab-pane>
                </el-tabs>
            </div>

            <template #footer>
                <div class="drawer-footer">
                    <span v-if="dirtyCount" class="dirty-note">
                        <el-icon><WarningFilled /></el-icon>
                        {{ $t('ac_unsaved_changes', { n: dirtyCount }) }}
                    </span>
                    <span v-else class="muted">&nbsp;</span>
                    <div class="footer-actions">
                        <el-button :disabled="!dirtyCount || drawer.saving" @click="discard">{{ $t('ac_discard') }}</el-button>
                        <el-button type="primary" :disabled="!dirtyCount" :loading="drawer.saving" @click="save">
                            {{ $t('ac_save_changes') }}
                        </el-button>
                    </div>
                </div>
            </template>
        </el-drawer>

        <!-- Create user -->
        <el-dialog v-model="create.visible" :title="$t('acu_create_title')" :width="dialogWidth" destroy-on-close>
            <el-form ref="createFormRef" :model="create.form" :rules="createRules" label-position="top">
                <div class="form-grid">
                    <el-form-item :label="$t('acu_name')" prop="name">
                        <el-input v-model="create.form.name" />
                    </el-form-item>
                    <el-form-item :label="$t('acu_email')" prop="email">
                        <el-input v-model="create.form.email" dir="ltr" />
                    </el-form-item>
                    <el-form-item :label="$t('acu_phone')" prop="phone">
                        <el-input v-model="create.form.phone" dir="ltr" />
                    </el-form-item>
                    <el-form-item :label="$t('acu_password')" prop="password">
                        <el-input v-model="create.form.password" type="password" show-password dir="ltr">
                            <template #append>
                                <el-button :icon="MagicStick" @click="generatePassword">{{ $t('acu_generate') }}</el-button>
                            </template>
                        </el-input>
                    </el-form-item>
                </div>
                <el-form-item :label="$t('acu_select_roles')">
                    <el-select v-model="create.form.role_ids" multiple filterable style="width: 100%" @change="syncCreatePrimary">
                        <el-option
                            v-for="role in roles"
                            :key="role.id"
                            :label="role.display_name"
                            :value="role.id"
                            :disabled="!!roleLockReason(role)"
                        >
                            <span class="option-dot" :style="{ background: role.color || '#94a3b8' }"></span>
                            {{ role.display_name }}
                        </el-option>
                    </el-select>
                </el-form-item>
                <el-form-item v-if="create.form.role_ids.length" :label="$t('acu_primary_role')">
                    <el-radio-group v-model="create.form.primary_role_id">
                        <el-radio v-for="id in create.form.role_ids" :key="id" :value="id">
                            {{ roleById(id)?.display_name }}
                        </el-radio>
                    </el-radio-group>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="create.visible = false">{{ $t('ac_cancel') }}</el-button>
                <el-button type="primary" :loading="create.saving" @click="submitCreate">{{ $t('acu_create') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { computed, h, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
    CircleCheckFilled, CircleCloseFilled, Connection, EditPen, Key, Lock, MagicStick, Minus,
    Plus, Refresh, Search, Star, StarFilled, User, UserFilled, WarningFilled,
} from '@element-plus/icons-vue';
import { accessApi } from '@/api/access';
import AccessActivity from '@/components/admin/access/AccessActivity.vue';
import { useAuthStore } from '@/stores/auth';

const { t, te } = useI18n();
const auth = useAuthStore();

const isSuperRoleName = (name) => name === 'master' || name === 'admin';

const scopes = ['all', 'staff', 'no_role', 'multi_role', 'direct', 'super'];
const statCards = [
    { key: 'total', scope: 'all', label: 'acu_stat_total', icon: User, tone: 'total-icon' },
    { key: 'with_roles', scope: 'staff', label: 'acu_stat_with_roles', icon: UserFilled, tone: 'success' },
    { key: 'multi_role', scope: 'multi_role', label: 'acu_stat_multi_role', icon: Connection, tone: 'warning' },
    { key: 'direct_grants', scope: 'direct', label: 'acu_stat_direct', icon: Key, tone: 'muted' },
];

const loading = ref(false);
const users = ref([]);
const stats = ref({});
const pagination = reactive({ current_page: 1, per_page: 15, total: 0, last_page: 1 });
// The roles screen links here with ?role_id= to list a role's members.
const route = useRoute();
const filters = reactive({ search: '', role_id: Number(route.query.role_id) || null, scope: 'all' });

const roles = ref([]);
const modules = ref([]);
const catalogueFailed = ref(false);

const errorMessage = (error, fallback) => error?.response?.data?.message || fallback || t('ac_no_access');

// ---------------------------------------------------------------- loading

async function fetchCatalogue() {
    try {
        const res = await accessApi.getRoles();
        roles.value = res.data?.data?.roles || [];
        modules.value = res.data?.data?.modules || [];
        catalogueFailed.value = false;
    } catch (error) {
        catalogueFailed.value = true;
    }
}

async function fetchUsers() {
    loading.value = true;
    try {
        const params = {
            page: pagination.current_page,
            per_page: pagination.per_page,
            scope: filters.scope,
        };
        if (filters.search) params.search = filters.search;
        if (filters.role_id) params.role_id = filters.role_id;
        const res = await accessApi.getUsers(params);
        const data = res.data?.data || {};
        users.value = data.users || [];
        stats.value = data.stats || {};
        Object.assign(pagination, data.pagination || {});
    } catch (error) {
        ElMessage.error(errorMessage(error, t('acu_load_failed')));
    } finally {
        loading.value = false;
    }
}

function reloadAll() {
    fetchCatalogue();
    fetchUsers();
}

function applyFilters() {
    pagination.current_page = 1;
    fetchUsers();
}

function setScope(scope) {
    filters.scope = scope;
    applyFilters();
}

let searchTimer = null;
watch(() => filters.search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 350);
});

// ---------------------------------------------------------------- presentation

const palette = ['#4f46e5', '#0891b2', '#16a34a', '#d97706', '#db2777', '#7c3aed', '#2563eb', '#dc2626'];
const initial = (name) => (name || '?').trim().charAt(0).toUpperCase();
const avatarColor = (user) => {
    const primary = user.roles?.find((role) => role.id === user.primary_role_id);
    return primary?.color || palette[(user.id || 0) % palette.length];
};
const chipStyle = (color) => {
    const c = color || '#64748b';
    return { color: c, background: `${c}14`, borderColor: `${c}40` };
};
const moduleLabel = (module) => (te(`ac_module_${module}`) ? t(`ac_module_${module}`) : module);
const roleById = (id) => roles.value.find((role) => role.id === id);
const totalPermissions = computed(() => modules.value.reduce((sum, group) => sum + group.permissions.length, 0));

const viewportWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1280);
const onResize = () => { viewportWidth.value = window.innerWidth; };
const drawerSize = computed(() => (viewportWidth.value < 780 ? '100%' : '720px'));
const dialogWidth = computed(() => (viewportWidth.value < 680 ? '94%' : '620px'));

// Super roles need a super admin; master needs a master. Mirrors the server.
function roleLockReason(role) {
    if (role.name === 'master' && !auth.isMaster) return t('acu_master_role_locked');
    if (role.is_super && !auth.isSuperAdmin) return t('acu_super_role_locked');
    return '';
}

// ---------------------------------------------------------------- editor

const drawer = reactive({ visible: false, loading: false, saving: false, tab: 'roles', user: null });
const form = reactive({ roleIds: [], primaryRoleId: null, permissionIds: [] });
const original = reactive({ roleIds: [], primaryRoleId: null, permissionIds: [] });
const directSearch = ref('');
const openModules = ref([]);
const onlyGranted = ref(false);
const historyKey = ref(0);

function loadForm(user) {
    original.roleIds = user.roles.map((role) => role.id);
    original.primaryRoleId = user.primary_role_id ?? null;
    original.permissionIds = [...user.permission_ids];
    discard();
}

function discard() {
    form.roleIds = [...original.roleIds];
    form.primaryRoleId = original.primaryRoleId;
    form.permissionIds = [...original.permissionIds];
}

async function openEditor(row) {
    drawer.user = row;
    drawer.tab = 'roles';
    drawer.visible = true;
    directSearch.value = '';
    onlyGranted.value = false;
    loadForm(row);
    openModules.value = modules.value.map((group) => group.module);
    drawer.loading = true;
    try {
        const res = await accessApi.getUser(row.id);
        drawer.user = res.data?.data || row;
        loadForm(drawer.user);
    } catch (error) {
        ElMessage.error(errorMessage(error));
    } finally {
        drawer.loading = false;
    }
}

const isRoleChecked = (id) => form.roleIds.includes(id);

function toggleRole(role) {
    if (roleLockReason(role)) return;
    if (isRoleChecked(role.id)) {
        form.roleIds = form.roleIds.filter((id) => id !== role.id);
        if (form.primaryRoleId === role.id) form.primaryRoleId = form.roleIds[0] ?? null;
    } else {
        form.roleIds = [...form.roleIds, role.id];
        if (!form.primaryRoleId) form.primaryRoleId = role.id;
    }
}

function toggleDirect(id, value) {
    form.permissionIds = value
        ? [...new Set([...form.permissionIds, id])]
        : form.permissionIds.filter((p) => p !== id);
}

const filteredModules = computed(() => {
    const q = directSearch.value.trim().toLowerCase();
    if (!q) return modules.value;
    return modules.value
        .map((group) => ({
            ...group,
            permissions: group.permissions.filter((perm) => (
                perm.name.toLowerCase().includes(q)
                || (perm.display_name || '').toLowerCase().includes(q)
                || moduleLabel(group.module).toLowerCase().includes(q)
            )),
        }))
        .filter((group) => group.permissions.length);
});

function moduleState(group) {
    const count = group.permissions.filter((perm) => form.permissionIds.includes(perm.id)).length;
    return { count, all: count > 0 && count === group.permissions.length, some: count > 0 && count < group.permissions.length };
}

function toggleModule(group, value) {
    const ids = group.permissions.map((perm) => perm.id);
    form.permissionIds = value
        ? [...new Set([...form.permissionIds, ...ids])]
        : form.permissionIds.filter((id) => !ids.includes(id));
}

function setAllOpen(open) {
    openModules.value = open ? modules.value.map((group) => group.module) : [];
}

const selectedRoles = computed(() => form.roleIds.map(roleById).filter(Boolean));

// Roles in the current (unsaved) selection that already grant a permission.
function rolesGranting(permissionId) {
    return selectedRoles.value.filter((role) => !role.is_super && role.permission_ids.includes(permissionId));
}

const liveIsSuper = computed(() => !!drawer.user?.is_admin_flag || selectedRoles.value.some((role) => role.is_super));

const effectiveModules = computed(() => modules.value
    .map((group) => {
        const items = group.permissions.map((perm) => {
            const viaRoles = rolesGranting(perm.id);
            const direct = form.permissionIds.includes(perm.id);
            const granted = perm.is_active && (liveIsSuper.value || direct || viaRoles.some((role) => role.is_active));
            return { ...perm, roles: viaRoles, direct, granted };
        });
        return {
            module: group.module,
            total: items.length,
            granted: items.filter((item) => item.granted).length,
            items: onlyGranted.value ? items.filter((item) => item.granted) : items,
        };
    })
    .filter((group) => group.items.length));

const effectiveGrantedCount = computed(() => modules.value.reduce((sum, group) => sum + group.permissions.filter((perm) => (
    perm.is_active && (liveIsSuper.value
        || form.permissionIds.includes(perm.id)
        || rolesGranting(perm.id).some((role) => role.is_active))
)).length, 0));

const symmetricDiff = (a, b) => a.filter((x) => !b.includes(x)).length + b.filter((x) => !a.includes(x)).length;
const dirtyCount = computed(() => {
    if (!drawer.user) return 0;
    return symmetricDiff(form.roleIds, original.roleIds)
        + symmetricDiff(form.permissionIds, original.permissionIds)
        + ((form.primaryRoleId ?? null) !== (original.primaryRoleId ?? null) ? 1 : 0);
});

async function confirmCloseDrawer(done) {
    if (!dirtyCount.value) return done();
    try {
        await ElMessageBox.confirm(t('acu_confirm_close'), t('acu_confirm_close_title'), {
            type: 'warning',
            confirmButtonText: t('acu_close_anyway'),
            cancelButtonText: t('acu_keep_editing'),
        });
        done();
    } catch {
        // keep editing
    }
}

async function save() {
    if (!drawer.user) return;
    drawer.saving = true;
    try {
        const res = await accessApi.updateUserAccess(drawer.user.id, {
            role_ids: form.roleIds,
            primary_role_id: form.roleIds.length ? form.primaryRoleId : null,
            permission_ids: form.permissionIds,
        });
        const updated = res.data?.data;
        ElMessage.success(res.data?.message || t('ac_saved'));
        if (updated) {
            const index = users.value.findIndex((user) => user.id === updated.id);
            if (index !== -1) users.value.splice(index, 1, updated);
            drawer.user = { ...drawer.user, ...updated };
            loadForm(updated);
        }
        if (updated?.is_self || drawer.user?.id === auth.user?.id) {
            auth.refreshUser();
        }
        // Counts on the cards and role list move with the change.
        historyKey.value += 1;
        fetchCatalogue();
        fetchUsers();
    } catch (error) {
        ElMessage.error(errorMessage(error));
    } finally {
        drawer.saving = false;
    }
}

// ---------------------------------------------------------------- bulk

const tableRef = ref(null);
const selected = ref([]);
const bulk = reactive({ roleIds: [], busy: null });

function clearSelection() {
    tableRef.value?.clearSelection();
    selected.value = [];
}

async function runBulk(mode) {
    const names = bulk.roleIds.map((id) => roleById(id)?.display_name).filter(Boolean).join('، ');
    try {
        await ElMessageBox.confirm(
            t(mode === 'add' ? 'acu_bulk_add_confirm' : 'acu_bulk_remove_confirm', { roles: names, n: selected.value.length }),
            t('ac_confirm'),
            { type: mode === 'add' ? 'info' : 'warning' },
        );
    } catch {
        return;
    }
    bulk.busy = mode;
    try {
        const res = await accessApi.bulkUserRoles(selected.value.map((user) => user.id), bulk.roleIds, mode);
        const { skipped = [] } = res.data?.data || {};
        ElMessage.success(res.data?.message || t('ac_saved'));
        if (skipped.length) {
            ElMessageBox.alert(
                h('ul', { class: 'skipped-list' }, skipped.map((row) => h('li', [h('strong', `${row.name}: `), row.reason]))),
                t('acu_bulk_skipped_title', { n: skipped.length }),
                { type: 'warning' },
            );
        }
        if (selected.value.some((user) => user.is_self)) auth.refreshUser();
        bulk.roleIds = [];
        clearSelection();
        fetchCatalogue();
        fetchUsers();
    } catch (error) {
        ElMessage.error(errorMessage(error));
    } finally {
        bulk.busy = null;
    }
}

// ---------------------------------------------------------------- create

const createFormRef = ref(null);
const blankCreate = () => ({ name: '', email: '', phone: '', password: '', role_ids: [], primary_role_id: null });
const create = reactive({ visible: false, saving: false, form: blankCreate() });

const createRules = computed(() => ({
    name: [{ required: true, message: t('acu_required'), trigger: 'blur' }],
    email: [
        { required: true, message: t('acu_required'), trigger: 'blur' },
        { type: 'email', message: t('acu_invalid_email'), trigger: 'blur' },
    ],
    password: [
        { required: true, message: t('acu_required'), trigger: 'blur' },
        { min: 8, message: t('acu_password_min'), trigger: 'blur' },
    ],
}));

function openCreate() {
    create.form = blankCreate();
    create.visible = true;
}

function syncCreatePrimary() {
    if (!create.form.role_ids.includes(create.form.primary_role_id)) {
        create.form.primary_role_id = create.form.role_ids[0] ?? null;
    }
}

async function generatePassword() {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%';
    const bytes = new Uint32Array(14);
    window.crypto.getRandomValues(bytes);
    create.form.password = Array.from(bytes, (b) => chars[b % chars.length]).join('');
    try {
        await navigator.clipboard.writeText(create.form.password);
        ElMessage.success(t('acu_password_copied'));
    } catch {
        // Clipboard unavailable (insecure context); the field still shows it.
    }
}

async function submitCreate() {
    const valid = await createFormRef.value?.validate().catch(() => false);
    if (!valid) return;
    create.saving = true;
    try {
        const payload = { ...create.form };
        if (!payload.phone) delete payload.phone;
        const res = await accessApi.createUser(payload);
        ElMessage.success(res.data?.message || t('acu_created'));
        create.visible = false;
        fetchCatalogue();
        fetchUsers();
    } catch (error) {
        const errors = error?.response?.data?.errors;
        const first = errors ? Object.values(errors)[0]?.[0] : null;
        ElMessage.error(first || errorMessage(error));
    } finally {
        create.saving = false;
    }
}

onMounted(() => {
    window.addEventListener('resize', onResize);
    reloadAll();
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', onResize);
    clearTimeout(searchTimer);
});
</script>

<style scoped>
.users-page {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

/* Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
    background: #ffffff;
    padding: 24px 28px;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.03);
}

.eyebrow {
    font-size: 12px;
    color: #6366f1;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

.page-header h2 {
    margin: 6px 0 0;
    font-size: 26px;
    color: #0f172a;
    font-weight: 800;
    letter-spacing: -0.02em;
}

.subtitle {
    margin: 6px 0 0;
    color: #64748b;
    max-width: 650px;
    font-size: 14px;
    line-height: 1.6;
}

.header-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.header-btn {
    border-radius: 10px;
    height: 40px;
    font-weight: 700;
}

/* Stats */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}

.stat-card {
    position: relative;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 18px;
    padding: 18px 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.03);
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    overflow: hidden;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 24px -4px rgba(15, 23, 42, 0.08);
    border-color: #cbd5e1;
}

.stat-card.active {
    border-color: #4f46e5;
    background: linear-gradient(135deg, #ffffff, #f8faff);
    box-shadow: 0 10px 25px -4px rgba(79, 70, 229, 0.15);
}

.stat-card.active .stat-indicator {
    position: absolute;
    bottom: 0;
    inset-inline: 0;
    height: 3px;
    background: #4f46e5;
}

.icon-wrap {
    width: 50px;
    height: 50px;
    border-radius: 14px;
    display: grid;
    place-items: center;
    font-size: 22px;
    flex-shrink: 0;
}

.icon-wrap.total-icon { background: rgba(99, 102, 241, 0.12); color: #4f46e5; }
.icon-wrap.success { background: rgba(16, 185, 129, 0.12); color: #10b981; }
.icon-wrap.warning { background: rgba(245, 158, 11, 0.12); color: #f59e0b; }
.icon-wrap.muted { background: rgba(100, 116, 139, 0.12); color: #64748b; }

.stat-body {
    display: flex;
    flex-direction: column;
}

.stat-label {
    font-size: 13px;
    color: #64748b;
    font-weight: 600;
    margin-bottom: 4px;
}

.stat-card strong {
    font-size: 28px;
    color: #0f172a;
    font-weight: 800;
    line-height: 1.1;
}

/* Toolbar */
.toolbar-card,
.bulk-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
    padding: 10px 12px;
    border: 1px solid #c7d2fe;
    border-radius: 12px;
    background: #eef2ff;
}

.bulk-bar .el-button + .el-button {
    margin-inline-start: 0;
}

.bulk-count {
    font-weight: 700;
    color: #3730a3;
    margin-inline-end: 4px;
}

.bulk-roles {
    flex: 1 1 220px;
    max-width: 360px;
}

.bulk-enter-active,
.bulk-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}

.bulk-enter-from,
.bulk-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

.table-card {
    border-radius: 18px;
    border: 1px solid #e2e8f0;
}

.toolbar {
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
}

.search-input {
    width: 300px;
    max-width: 100%;
}

.role-select {
    width: 220px;
    max-width: 100%;
}

.scope-switch {
    flex-wrap: wrap;
}

.option-dot,
.role-dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    margin-inline-end: 8px;
    flex-shrink: 0;
}

/* Table */
.table-scroll {
    overflow-x: auto;
}

.user-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}

.avatar {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    color: #fff;
    font-weight: 800;
    font-size: 16px;
    flex-shrink: 0;
}

.avatar.large {
    width: 48px;
    height: 48px;
    font-size: 19px;
}

.user-meta small {
    color: #64748b;
}

.user-name {
    font-weight: 700;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 6px;
}

.tag-row {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    align-items: center;
}

.badge-tag {
    border: none;
}

.role-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 10px;
    border-radius: 999px;
    border: 1px solid;
    font-size: 12px;
    font-weight: 600;
    line-height: 20px;
}

.role-chip.small {
    padding: 0 8px;
    font-size: 11px;
}

.role-chip.inactive {
    opacity: 0.5;
    text-decoration: line-through;
}

.primary-star {
    color: #f59e0b;
}

.muted {
    color: #94a3b8;
}

.muted.small {
    font-size: 12px;
}

.pager {
    display: flex;
    justify-content: center;
    padding-top: 16px;
}

/* Drawer */
.drawer-head {
    display: flex;
    align-items: center;
    gap: 14px;
}

.drawer-head h3 {
    margin: 0;
    font-size: 18px;
    color: #0f172a;
}

.drawer-head small {
    color: #64748b;
}

.drawer-body {
    min-height: 200px;
}

.mb-12 {
    margin-bottom: 12px;
}

.tab-label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.tab-badge :deep(.el-badge__content) {
    position: static;
    transform: none;
}

.hint {
    color: #64748b;
    font-size: 13px;
    margin: 0 0 12px;
    line-height: 1.6;
}

.role-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 10px;
}

.role-card {
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: #fff;
    cursor: pointer;
    transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
}

.role-card:hover {
    border-color: #c7d2fe;
}

.role-card.checked {
    border-color: #4f46e5;
    background: #f8faff;
    box-shadow: 0 6px 16px -6px rgba(79, 70, 229, 0.25);
}

.role-card.locked {
    cursor: not-allowed;
    opacity: 0.6;
}

.role-card-inner {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
}

.role-card-text {
    flex: 1;
    min-width: 0;
}

.role-card-title {
    font-weight: 700;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.role-card-text small {
    color: #64748b;
}

.primary-toggle {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    border: 1px solid #e2e8f0;
    background: #fff;
    color: #64748b;
    border-radius: 999px;
    padding: 3px 10px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
}

.primary-toggle.on {
    border-color: #f59e0b;
    background: #fffbeb;
    color: #b45309;
}

.panel-tools {
    display: flex;
    gap: 8px;
    align-items: center;
    margin-bottom: 10px;
}

.module-collapse {
    border-radius: 12px;
    overflow: hidden;
}

.module-title {
    display: inline-flex;
    align-items: center;
    margin-inline-end: 10px;
}

.module-name {
    font-weight: 700;
    color: #0f172a;
}

.module-count {
    margin-inline-start: auto;
    margin-inline-end: 10px;
    color: #64748b;
    font-size: 12px;
    font-variant-numeric: tabular-nums;
}

.perm-list {
    display: flex;
    flex-direction: column;
}

.perm-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 6px;
    border-radius: 8px;
    cursor: pointer;
}

.perm-row:hover {
    background: #f8fafc;
}

.perm-text {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-width: 0;
    text-align: start;
}

.perm-name {
    color: #0f172a;
    font-size: 13px;
    font-weight: 600;
}

.perm-text code {
    color: #94a3b8;
    font-size: 11px;
    direction: ltr;
    text-align: start;
}

.via-hint {
    color: #94a3b8;
    font-size: 11px;
    font-style: italic;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 180px;
}

/* Effective access */
.effective-summary {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 14px 16px;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: #f8fafc;
    margin-bottom: 14px;
    flex-wrap: wrap;
}

.effective-summary strong {
    color: #0f172a;
}

.effective-summary .hint {
    margin: 4px 0 0;
}

.only-granted {
    margin-inline-start: auto;
}

.effective-group {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    margin-bottom: 10px;
    overflow: hidden;
}

.effective-head {
    display: flex;
    align-items: center;
    padding: 10px 14px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}

.effective-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 14px;
    border-bottom: 1px solid #f1f5f9;
}

.effective-row:last-child {
    border-bottom: none;
}

.state-icon {
    color: #10b981;
    font-size: 18px;
    flex-shrink: 0;
}

.effective-row.denied .state-icon {
    color: #cbd5e1;
}

.effective-row.denied .perm-name {
    color: #94a3b8;
}

.source-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    justify-content: flex-end;
}

/* Footer */
.drawer-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}

.dirty-note {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #b45309;
    font-weight: 600;
    font-size: 13px;
}

.footer-actions {
    display: flex;
    gap: 8px;
}

/* Create dialog */
.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0 16px;
}

@media (max-width: 680px) {
    .page-header {
        padding: 18px;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .search-input,
    .role-select {
        width: 100%;
    }

    .role-grid {
        grid-template-columns: 1fr;
    }

    .via-hint {
        display: none;
    }
}
</style>
