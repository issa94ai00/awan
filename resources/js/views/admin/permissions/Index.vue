<template>
    <div class="permissions-page">
        <!-- Page header -->
        <div class="page-header">
            <div class="header-info">
                <span class="eyebrow">{{ $t('ac_eyebrow') }}</span>
                <h2>{{ $t('acp_title') }}</h2>
                <p class="subtitle">{{ $t('acp_subtitle') }}</p>
            </div>

            <div class="header-actions">
                <el-radio-group v-model="viewMode" class="view-switcher">
                    <el-radio-button value="matrix">
                        <el-icon class="btn-icon"><Grid /></el-icon>
                        <span>{{ $t('acp_view_matrix') }}</span>
                    </el-radio-button>
                    <el-radio-button value="catalogue">
                        <el-icon class="btn-icon"><Document /></el-icon>
                        <span>{{ $t('acp_view_catalogue') }}</span>
                    </el-radio-button>
                </el-radio-group>

                <el-button :icon="Refresh" class="header-btn" :loading="loading" @click="fetchAll">
                    {{ $t('ac_refresh') }}
                </el-button>
                <el-button
                    v-if="canManage"
                    type="primary"
                    :icon="Plus"
                    class="header-btn"
                    @click="openCreate"
                >
                    {{ $t('acp_new_permission') }}
                </el-button>
            </div>
        </div>

        <!-- KPI cards (click to filter) -->
        <div class="stats-grid">
            <div class="stat-card" :class="{ active: !hasFilters }" @click="resetFilters">
                <div class="icon-wrap total"><el-icon><Key /></el-icon></div>
                <div class="stat-body">
                    <span class="stat-label">{{ $t('acp_stat_total') }}</span>
                    <strong>{{ stats.total }}</strong>
                </div>
                <div class="stat-indicator"></div>
            </div>
            <div class="stat-card" :class="{ active: statusFilter === 'active' }" @click="toggleStatus('active')">
                <div class="icon-wrap success"><el-icon><CircleCheck /></el-icon></div>
                <div class="stat-body">
                    <span class="stat-label">{{ $t('acp_stat_active') }}</span>
                    <strong>{{ stats.active }}</strong>
                </div>
                <div class="stat-indicator"></div>
            </div>
            <div class="stat-card" :class="{ active: !!moduleFilter }" @click="moduleFilter = ''">
                <div class="icon-wrap muted"><el-icon><Collection /></el-icon></div>
                <div class="stat-body">
                    <span class="stat-label">{{ $t('acp_stat_modules') }}</span>
                    <strong>{{ stats.modules }}</strong>
                </div>
                <div class="stat-indicator"></div>
            </div>
            <div class="stat-card" :class="{ active: unassignedOnly }" @click="unassignedOnly = !unassignedOnly">
                <div class="icon-wrap warning"><el-icon><WarningFilled /></el-icon></div>
                <div class="stat-body">
                    <span class="stat-label">{{ $t('acp_stat_unassigned') }}</span>
                    <strong>{{ unassignedCount }}</strong>
                </div>
                <div class="stat-indicator"></div>
            </div>
        </div>

        <!-- Toolbar -->
        <el-card shadow="never" class="toolbar-card">
            <div class="toolbar">
                <el-input
                    v-model="search"
                    :placeholder="$t('acp_search')"
                    :prefix-icon="Search"
                    clearable
                    class="search-input"
                />
                <el-select v-model="moduleFilter" :placeholder="$t('acp_all_modules')" clearable filterable class="filter-select">
                    <el-option
                        v-for="module in moduleOptions"
                        :key="module"
                        :label="moduleLabel(module)"
                        :value="module"
                    />
                </el-select>
                <el-select v-model="statusFilter" class="filter-select">
                    <el-option :label="$t('acp_status_all')" value="all" />
                    <el-option :label="$t('acp_status_active')" value="active" />
                    <el-option :label="$t('acp_status_inactive')" value="inactive" />
                </el-select>
                <el-checkbox v-model="unassignedOnly" :label="$t('acp_filter_unassigned')" border />

                <div class="toolbar-spacer"></div>

                <template v-if="viewMode === 'matrix'">
                    <el-button text :icon="ArrowDown" @click="expandAll">{{ $t('ac_expand_all') }}</el-button>
                    <el-button text :icon="ArrowUp" @click="collapseAll">{{ $t('ac_collapse_all') }}</el-button>
                </template>
                <el-button v-if="hasFilters" text type="primary" @click="resetFilters">
                    {{ $t('acp_clear_filters') }}
                </el-button>
                <el-tooltip :content="$t('acp_export_hint')" placement="top">
                    <el-button :icon="Download" :disabled="!filtered.length" @click="exportCsv">{{ $t('acp_export') }}</el-button>
                </el-tooltip>
            </div>
        </el-card>

        <el-alert
            v-if="!canEditMatrix && viewMode === 'matrix'"
            type="info"
            :title="$t('acp_readonly_banner')"
            :closable="false"
            show-icon
            class="readonly-banner"
        />

        <!-- MATRIX -->
        <el-card v-if="viewMode === 'matrix'" v-loading="loading" shadow="never" class="matrix-card">
            <el-empty v-if="!loading && !groups.length" :description="$t('acp_empty_title')">
                <template #default>
                    <p class="empty-hint">{{ $t('acp_empty_hint') }}</p>
                </template>
            </el-empty>

            <div v-else class="matrix-scroll">
                <table class="matrix">
                    <thead>
                        <tr>
                            <th class="sticky-col head-perm">{{ $t('acp_permission_column') }}</th>
                            <th
                                v-for="role in roles"
                                :key="role.id"
                                class="role-head"
                                :class="{ dimmed: !role.is_active }"
                            >
                                <el-tooltip
                                    :content="role.is_super ? $t('ac_super_note') : (!role.is_active ? $t('acp_role_inactive') : role.display_name)"
                                    placement="top"
                                >
                                    <div class="role-head-inner">
                                        <span class="role-dot" :style="{ background: role.color || '#94a3b8' }"></span>
                                        <span class="role-head-name">{{ role.display_name }}</span>
                                        <el-icon v-if="role.is_super" class="super-icon"><Lock /></el-icon>
                                    </div>
                                </el-tooltip>
                            </th>
                        </tr>
                    </thead>

                    <tbody v-for="group in groups" :key="group.module">
                        <!-- Module header row -->
                        <tr class="module-row">
                            <th class="sticky-col module-cell" @click="toggleGroup(group.module)">
                                <div class="module-title">
                                    <el-icon class="chevron" :class="{ open: !collapsed[group.module] }"><ArrowRight /></el-icon>
                                    <span class="module-name">{{ moduleLabel(group.module) }}</span>
                                    <span class="module-count">{{ $t('acp_count_in_module', { n: group.items.length }) }}</span>
                                </div>
                            </th>
                            <td v-for="role in roles" :key="role.id" class="module-role-cell" :class="{ dimmed: !role.is_active }">
                                <el-tooltip v-if="role.is_super" :content="$t('ac_super_note')" placement="top">
                                    <span class="super-cell"><el-icon><Check /></el-icon></span>
                                </el-tooltip>
                                <el-tooltip v-else :content="$t('acp_module_toggle_hint')" placement="top" :disabled="!canEditMatrix">
                                    <span class="cell-wrap">
                                        <el-checkbox
                                            :model-value="moduleState(group, role) === 'all'"
                                            :indeterminate="moduleState(group, role) === 'some'"
                                            :disabled="!canEditMatrix || !!moduleLoading[moduleKey(group.module, role.id)]"
                                            @change="(value) => toggleModule(group, role, value)"
                                        />
                                        <el-icon v-if="moduleLoading[moduleKey(group.module, role.id)]" class="is-loading cell-spinner"><Loading /></el-icon>
                                    </span>
                                </el-tooltip>
                            </td>
                        </tr>

                        <!-- Permission rows -->
                        <template v-if="!collapsed[group.module]">
                            <tr
                                v-for="permission in group.items"
                                :key="permission.id"
                                class="perm-row"
                                :class="{ dimmed: !permission.is_active }"
                            >
                                <th class="sticky-col perm-cell">
                                    <div class="perm-label">
                                        <span class="perm-display">
                                            {{ permission.display_name }}
                                            <el-tooltip v-if="!permission.is_active" :content="$t('acp_permission_inactive')" placement="top">
                                                <el-tag size="small" type="info" effect="plain" class="tiny-tag">{{ $t('ac_inactive_badge') }}</el-tag>
                                            </el-tooltip>
                                        </span>
                                        <code class="perm-key">{{ permission.name }}</code>
                                    </div>
                                </th>
                                <td
                                    v-for="role in roles"
                                    :key="role.id"
                                    class="perm-role-cell"
                                    :class="{ dimmed: !role.is_active, granted: role.is_super || hasGrant(role, permission) }"
                                >
                                    <span v-if="role.is_super" class="super-cell faint"><el-icon><Check /></el-icon></span>
                                    <span v-else class="cell-wrap">
                                        <el-checkbox
                                            :model-value="hasGrant(role, permission)"
                                            :disabled="!canEditMatrix || !!cellLoading[cellKey(role.id, permission.id)]"
                                            @change="(value) => toggleCell(role, permission, value)"
                                        />
                                        <el-icon v-if="cellLoading[cellKey(role.id, permission.id)]" class="is-loading cell-spinner"><Loading /></el-icon>
                                    </span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </el-card>

        <!-- CATALOGUE -->
        <div v-else v-loading="loading" class="catalogue">
            <el-card v-if="!loading && !groups.length" shadow="never" class="table-card">
                <el-empty :description="$t('acp_empty_title')">
                    <p class="empty-hint">{{ $t('acp_empty_hint') }}</p>
                </el-empty>
            </el-card>

            <el-card v-for="group in groups" :key="group.module" shadow="never" class="module-card">
                <template #header>
                    <div class="module-card-head">
                        <div class="module-title">
                            <el-icon class="module-icon"><Collection /></el-icon>
                            <span class="module-name">{{ moduleLabel(group.module) }}</span>
                            <code class="perm-key">{{ group.module }}</code>
                        </div>
                        <el-tag effect="light" round>{{ $t('acp_count_in_module', { n: group.items.length }) }}</el-tag>
                    </div>
                </template>

                <div class="perm-list">
                    <div
                        v-for="permission in group.items"
                        :key="permission.id"
                        class="perm-item"
                        :class="{ dimmed: !permission.is_active }"
                    >
                        <div class="perm-main">
                            <div class="perm-title-line">
                                <span class="perm-display">{{ permission.display_name }}</span>
                                <el-tooltip v-if="permission.is_system" :content="$t('acp_system_tooltip')" placement="top">
                                    <el-tag size="small" type="warning" effect="plain" class="tiny-tag">
                                        <el-icon class="tag-icon"><Lock /></el-icon>{{ $t('ac_system_badge') }}
                                    </el-tag>
                                </el-tooltip>
                                <el-tag v-else size="small" type="success" effect="plain" class="tiny-tag">{{ $t('ac_custom_badge') }}</el-tag>
                            </div>
                            <div class="key-line">
                                <code class="perm-key">{{ permission.name }}</code>
                                <el-tooltip :content="$t('acp_copy_key')" placement="top">
                                    <el-button text size="small" :icon="CopyDocument" class="copy-btn" @click="copyKey(permission.name)" />
                                </el-tooltip>
                            </div>
                            <p v-if="permission.description" class="perm-desc">{{ permission.description }}</p>

                            <div class="perm-meta">
                                <span class="meta-label">{{ $t('acp_roles_holding') }}:</span>
                                <template v-if="permission.role_ids.length">
                                    <span
                                        v-for="role in rolesFor(permission)"
                                        :key="role.id"
                                        class="role-chip"
                                        :class="{ dimmed: !role.is_active }"
                                        :style="chipStyle(role)"
                                    >
                                        <span class="role-dot" :style="{ background: role.color || '#94a3b8' }"></span>
                                        {{ role.display_name }}
                                    </span>
                                </template>
                                <span v-else class="muted-text">{{ $t('acp_no_roles') }}</span>
                                <el-tag v-if="permission.direct_users_count" size="small" type="info" effect="plain" round class="tiny-tag">
                                    <el-icon class="tag-icon"><User /></el-icon>{{ $t('acp_direct_users', { n: permission.direct_users_count }) }}
                                </el-tag>
                            </div>
                        </div>

                        <div class="perm-actions">
                            <el-switch
                                :model-value="permission.is_active"
                                :disabled="!canManage"
                                :loading="!!statusLoading[permission.id]"
                                @change="(value) => toggleActive(permission, value)"
                            />
                            <template v-if="canManage">
                                <el-tooltip :content="$t('acp_edit')" placement="top">
                                    <el-button circle size="small" :icon="Edit" @click="openEdit(permission)" />
                                </el-tooltip>
                                <el-tooltip v-if="!permission.is_system" :content="$t('acp_delete')" placement="top">
                                    <el-button circle size="small" type="danger" plain :icon="Delete" @click="confirmDelete(permission)" />
                                </el-tooltip>
                            </template>
                        </div>
                    </div>
                </div>
            </el-card>
        </div>

        <!-- Create / edit dialog -->
        <el-dialog
            v-model="dialogVisible"
            :title="editing ? $t('acp_dialog_edit') : $t('acp_dialog_create')"
            width="560px"
            class="perm-dialog"
            destroy-on-close
            @closed="resetForm"
        >
            <el-form ref="formRef" :model="form" :rules="rules" label-position="top" @submit.prevent>
                <el-form-item :label="$t('acp_field_name')" prop="name">
                    <el-input
                        v-model="form.name"
                        dir="ltr"
                        class="mono-input"
                        placeholder="products.export"
                        :disabled="editing && editing.is_system"
                        @input="syncModuleFromName"
                    />
                    <div class="field-hint">
                        {{ editing && editing.is_system ? $t('acp_field_name_locked') : $t('acp_field_name_hint') }}
                    </div>
                </el-form-item>
                <el-form-item :label="$t('acp_field_display_name')" prop="display_name">
                    <el-input v-model="form.display_name" maxlength="150" />
                </el-form-item>
                <el-form-item :label="$t('acp_field_module')">
                    <el-select
                        v-model="form.module"
                        filterable
                        allow-create
                        default-first-option
                        clearable
                        class="full-width"
                        @change="moduleTouched = true"
                    >
                        <el-option v-for="module in moduleOptions" :key="module" :label="moduleLabel(module)" :value="module" />
                    </el-select>
                    <div class="field-hint">{{ $t('acp_field_module_hint') }}</div>
                </el-form-item>
                <el-form-item :label="$t('acp_field_description')">
                    <el-input v-model="form.description" type="textarea" :rows="3" maxlength="500" show-word-limit />
                </el-form-item>
                <el-form-item :label="$t('acp_field_active')">
                    <el-switch v-model="form.is_active" />
                </el-form-item>
            </el-form>

            <template #footer>
                <el-button @click="dialogVisible = false">{{ $t('ac_cancel') }}</el-button>
                <el-button type="primary" :loading="saving" @click="submitForm">
                    {{ editing ? $t('acp_save') : $t('acp_create') }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
    ArrowDown,
    ArrowRight,
    ArrowUp,
    Check,
    CircleCheck,
    Collection,
    CopyDocument,
    Delete,
    Document,
    Download,
    Edit,
    Grid,
    Key,
    Loading,
    Lock,
    Plus,
    Refresh,
    Search,
    User,
    WarningFilled,
} from '@element-plus/icons-vue';
import accessApi from '@/api/access';
import { useAuthStore } from '@/stores/auth';
import { matchesSearch } from '@/utils/search';

const { t, te } = useI18n();
const authStore = useAuthStore();

const NAME_PATTERN = /^[a-z][a-z0-9_]*\.[a-z][a-z0-9_.]*$/;

const loading = ref(false);
const permissions = ref([]);
const roles = ref([]);
const serverStats = ref({ total: 0, active: 0, modules: 0, unassigned: 0 });

const viewMode = ref('matrix');
const search = ref('');
const moduleFilter = ref('');
const statusFilter = ref('all');
const unassignedOnly = ref(false);
const collapsed = reactive({});

const cellLoading = reactive({});
const moduleLoading = reactive({});
const statusLoading = reactive({});

const canManage = computed(() => authStore.can('permissions.manage'));
const canEditMatrix = computed(() => authStore.can(['roles.edit', 'permissions.assign']));

const errorMessage = (error, fallback) => error?.response?.data?.message || fallback || t('acp_action_failed');

const moduleLabel = (module) => (te(`ac_module_${module}`) ? t(`ac_module_${module}`) : module);

const isUnassigned = (permission) => !permission.role_ids.length && !permission.direct_users_count;

// Stats are recomputed locally so toggles in the matrix are reflected at once.
const stats = computed(() => ({
    total: permissions.value.length,
    active: permissions.value.filter((p) => p.is_active).length,
    modules: new Set(permissions.value.map((p) => p.module)).size || serverStats.value.modules,
}));
const unassignedCount = computed(() => permissions.value.filter(isUnassigned).length);

const moduleOptions = computed(() => [...new Set(permissions.value.map((p) => p.module))].sort());

const hasFilters = computed(() => !!search.value || !!moduleFilter.value || statusFilter.value !== 'all' || unassignedOnly.value);

const filtered = computed(() => permissions.value.filter((permission) => {
    if (moduleFilter.value && permission.module !== moduleFilter.value) return false;
    if (statusFilter.value === 'active' && !permission.is_active) return false;
    if (statusFilter.value === 'inactive' && permission.is_active) return false;
    if (unassignedOnly.value && !isUnassigned(permission)) return false;
    if (search.value && !matchesSearch([permission.name, permission.display_name, permission.description, moduleLabel(permission.module)], search.value)) return false;
    return true;
}));

const groups = computed(() => {
    const byModule = new Map();
    for (const permission of filtered.value) {
        if (!byModule.has(permission.module)) byModule.set(permission.module, []);
        byModule.get(permission.module).push(permission);
    }
    return [...byModule.entries()]
        .sort(([a], [b]) => moduleLabel(a).localeCompare(moduleLabel(b)))
        .map(([module, items]) => ({ module, items }));
});

const rolesById = computed(() => Object.fromEntries(roles.value.map((role) => [role.id, role])));
const rolesFor = (permission) => permission.role_ids.map((id) => rolesById.value[id]).filter(Boolean);

const chipStyle = (role) => {
    const color = role.color || '#94a3b8';
    return { borderColor: `${color}55`, background: `${color}12` };
};

const cellKey = (roleId, permissionId) => `${roleId}_${permissionId}`;
const moduleKey = (module, roleId) => `${module}_${roleId}`;

const hasGrant = (role, permission) => permission.role_ids.includes(role.id);

// The matrix as it is filtered on screen, one row per permission and one
// column per role. The BOM lets Excel read the Arabic names correctly.
function exportCsv() {
    const cell = (value) => `"${String(value ?? '').replace(/"/g, '""')}"`;
    const header = [t('acp_export_module'), t('acp_permission_column'), t('acp_export_key'), t('acp_export_status'),
        ...roles.value.map((role) => role.display_name), t('acp_export_direct')];
    const rows = filtered.value.map((p) => [
        moduleLabel(p.module),
        p.display_name,
        p.name,
        p.is_active ? t('acp_status_active') : t('acp_status_inactive'),
        ...roles.value.map((role) => (role.is_super ? '✓*' : hasGrant(role, p) ? '✓' : '')),
        p.direct_users_count || '',
    ]);
    const csv = [header, ...rows].map((row) => row.map(cell).join(',')).join('\r\n');
    const url = URL.createObjectURL(new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' }));
    const link = Object.assign(document.createElement('a'), {
        href: url,
        download: `permissions-matrix-${new Date().toISOString().slice(0, 10)}.csv`,
    });
    link.click();
    URL.revokeObjectURL(url);
}

const moduleState = (group, role) => {
    const granted = group.items.filter((p) => hasGrant(role, p)).length;
    if (!granted) return 'none';
    return granted === group.items.length ? 'all' : 'some';
};

const setGrant = (permission, roleId, granted) => {
    const has = permission.role_ids.includes(roleId);
    if (granted && !has) permission.role_ids = [...permission.role_ids, roleId];
    if (!granted && has) permission.role_ids = permission.role_ids.filter((id) => id !== roleId);
};

async function fetchAll() {
    loading.value = true;
    try {
        const { data } = await accessApi.getPermissions();
        const payload = data?.data || {};
        permissions.value = (payload.permissions || []).map((p) => ({ ...p, role_ids: [...(p.role_ids || [])] }));
        roles.value = payload.roles || [];
        serverStats.value = payload.stats || serverStats.value;
    } catch (error) {
        ElMessage.error(errorMessage(error, t('acp_load_failed')));
    } finally {
        loading.value = false;
    }
}

async function toggleCell(role, permission, granted) {
    const key = cellKey(role.id, permission.id);
    if (cellLoading[key]) return;
    cellLoading[key] = true;
    setGrant(permission, role.id, granted); // optimistic
    try {
        await accessApi.toggleRolePermissions(role.id, [permission.id], granted);
        ElMessage.success(granted ? t('acp_granted') : t('acp_revoked'));
        refreshMe();
    } catch (error) {
        setGrant(permission, role.id, !granted);
        ElMessage.error(errorMessage(error));
    } finally {
        delete cellLoading[key];
    }
}

async function toggleModule(group, role, granted) {
    const key = moduleKey(group.module, role.id);
    if (moduleLoading[key]) return;
    const targets = group.items.filter((p) => hasGrant(role, p) !== granted);
    if (!targets.length) return;

    moduleLoading[key] = true;
    targets.forEach((p) => setGrant(p, role.id, granted));
    try {
        await accessApi.toggleRolePermissions(role.id, targets.map((p) => p.id), granted);
        ElMessage.success(t(granted ? 'acp_module_granted' : 'acp_module_revoked', { role: role.display_name }));
        refreshMe();
    } catch (error) {
        targets.forEach((p) => setGrant(p, role.id, !granted));
        ElMessage.error(errorMessage(error));
    } finally {
        delete moduleLoading[key];
    }
}

// A change to a role or permission may change what the signed-in user can do.
function refreshMe() {
    authStore.refreshUser?.();
}

const toggleGroup = (module) => { collapsed[module] = !collapsed[module]; };
const expandAll = () => { Object.keys(collapsed).forEach((key) => { collapsed[key] = false; }); };
const collapseAll = () => { groups.value.forEach((group) => { collapsed[group.module] = true; }); };

function toggleStatus(status) {
    statusFilter.value = statusFilter.value === status ? 'all' : status;
}

function resetFilters() {
    search.value = '';
    moduleFilter.value = '';
    statusFilter.value = 'all';
    unassignedOnly.value = false;
}

async function copyKey(name) {
    try {
        await navigator.clipboard.writeText(name);
        ElMessage.success(t('acp_copied'));
    } catch {
        ElMessage.info(name);
    }
}

async function toggleActive(permission, value) {
    statusLoading[permission.id] = true;
    const previous = permission.is_active;
    permission.is_active = value;
    try {
        await accessApi.updatePermission(permission.id, { is_active: value });
        ElMessage.success(t('acp_status_updated'));
        refreshMe();
    } catch (error) {
        permission.is_active = previous;
        ElMessage.error(errorMessage(error));
    } finally {
        delete statusLoading[permission.id];
    }
}

async function confirmDelete(permission) {
    try {
        await ElMessageBox.confirm(
            t('acp_delete_confirm', { name: permission.display_name }),
            t('acp_delete_title'),
            { type: 'warning', confirmButtonText: t('acp_delete'), cancelButtonText: t('ac_cancel'), confirmButtonClass: 'el-button--danger' },
        );
    } catch {
        return;
    }
    try {
        await accessApi.deletePermission(permission.id);
        permissions.value = permissions.value.filter((p) => p.id !== permission.id);
        ElMessage.success(t('acp_deleted'));
        refreshMe();
    } catch (error) {
        ElMessage.error(errorMessage(error));
    }
}

// ── Create / edit dialog ────────────────────────────────────────────────
const dialogVisible = ref(false);
const editing = ref(null);
const saving = ref(false);
const formRef = ref(null);
const moduleTouched = ref(false);
const emptyForm = () => ({ name: '', display_name: '', module: '', description: '', is_active: true });
const form = reactive(emptyForm());

const rules = computed(() => ({
    name: [
        { required: true, message: t('acp_err_name_required'), trigger: 'blur' },
        { pattern: NAME_PATTERN, message: t('acp_err_name_format'), trigger: 'blur' },
    ],
    display_name: [{ required: true, message: t('acp_err_display_required'), trigger: 'blur' }],
}));

function resetForm() {
    Object.assign(form, emptyForm());
    editing.value = null;
    moduleTouched.value = false;
    formRef.value?.clearValidate();
}

function openCreate() {
    resetForm();
    if (moduleFilter.value) {
        form.module = moduleFilter.value;
        form.name = `${moduleFilter.value}.`;
    }
    dialogVisible.value = true;
}

function openEdit(permission) {
    resetForm();
    editing.value = permission;
    Object.assign(form, {
        name: permission.name,
        display_name: permission.display_name,
        module: permission.module,
        description: permission.description || '',
        is_active: permission.is_active,
    });
    moduleTouched.value = true;
    dialogVisible.value = true;
}

// Until the module is picked by hand, it follows the key's prefix.
function syncModuleFromName(value) {
    form.name = String(value || '').toLowerCase().replace(/\s+/g, '_');
    if (moduleTouched.value) return;
    const prefix = form.name.split('.')[0];
    form.module = /^[a-z][a-z0-9_]*$/.test(prefix) ? prefix : '';
}

async function submitForm() {
    try {
        await formRef.value.validate();
    } catch {
        return;
    }

    const payload = {
        display_name: form.display_name,
        module: form.module || null,
        description: form.description || null,
        is_active: form.is_active,
    };
    if (!editing.value || !editing.value.is_system) payload.name = form.name;

    saving.value = true;
    try {
        if (editing.value) {
            const { data } = await accessApi.updatePermission(editing.value.id, payload);
            Object.assign(editing.value, data.data, { direct_users_count: editing.value.direct_users_count });
            ElMessage.success(t('acp_updated'));
        } else {
            const { data } = await accessApi.createPermission(payload);
            permissions.value.push({ ...data.data, role_ids: [...(data.data.role_ids || [])], direct_users_count: 0 });
            ElMessage.success(t('acp_created'));
        }
        dialogVisible.value = false;
        refreshMe();
    } catch (error) {
        const errors = error?.response?.data?.errors;
        ElMessage.error(errors ? Object.values(errors).flat()[0] : errorMessage(error));
    } finally {
        saving.value = false;
    }
}

onMounted(fetchAll);
</script>

<style scoped>
.permissions-page {
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
    display: block;
    font-size: 12px;
    color: #6366f1;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin-bottom: 6px;
}

.page-header h2 {
    margin: 0;
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
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.view-switcher :deep(.el-radio-button__inner) {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-weight: 600;
    padding: 10px 18px;
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
    inset-inline: 0;
    bottom: 0;
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

.icon-wrap.total { background: rgba(99, 102, 241, 0.12); color: #4f46e5; }
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
.matrix-card,
.module-card,
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

.filter-select {
    width: 180px;
}

.toolbar-spacer {
    flex: 1;
}

.readonly-banner {
    border-radius: 14px;
}

/* Matrix */
.matrix-card :deep(.el-card__body) {
    padding: 0;
}

.matrix-scroll {
    overflow-x: auto;
    max-height: 72vh;
    overflow-y: auto;
}

.matrix {
    border-collapse: separate;
    border-spacing: 0;
    width: 100%;
    min-width: max-content;
    font-size: 13px;
}

.matrix th,
.matrix td {
    border-bottom: 1px solid #eef2f7;
    padding: 10px 12px;
    text-align: center;
    background: #ffffff;
}

.matrix thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #f8fafc;
    font-weight: 700;
    color: #334155;
    border-bottom: 1px solid #e2e8f0;
}

.sticky-col {
    position: sticky;
    inset-inline-start: 0;
    z-index: 1;
    text-align: start !important;
    min-width: 260px;
    max-width: 320px;
    border-inline-end: 1px solid #e2e8f0;
}

.matrix thead th.sticky-col {
    z-index: 3;
}

.head-perm {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #64748b !important;
}

.role-head {
    min-width: 96px;
    max-width: 130px;
}

.role-head-inner {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    max-width: 120px;
}

.role-head-name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.super-icon {
    color: #7c3aed;
    font-size: 12px;
}

.role-dot {
    display: inline-block;
    width: 9px;
    height: 9px;
    border-radius: 50%;
    flex-shrink: 0;
}

.module-row th,
.module-row td {
    background: #f1f5ff;
    border-bottom-color: #e0e7ff;
}

.module-cell {
    cursor: pointer;
    user-select: none;
}

.module-title {
    display: flex;
    align-items: center;
    gap: 8px;
}

.module-name {
    font-weight: 800;
    color: #1e293b;
}

.module-count {
    font-size: 12px;
    color: #64748b;
    font-weight: 600;
}

.chevron {
    transition: transform 0.2s ease;
    color: #6366f1;
}

.chevron.open {
    transform: rotate(90deg);
}

.perm-row:hover th,
.perm-row:hover td {
    background: #fafbff;
}

.perm-role-cell.granted {
    background: #f6fef9;
}

.perm-label {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.perm-display {
    font-weight: 600;
    color: #0f172a;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.perm-key {
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 12px;
    color: #6366f1;
    background: #eef2ff;
    padding: 1px 6px;
    border-radius: 6px;
    direction: ltr;
    unicode-bidi: isolate;
    width: fit-content;
}

.cell-wrap {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.cell-spinner {
    position: absolute;
    inset-inline-end: -16px;
    font-size: 12px;
    color: #6366f1;
}

.super-cell {
    display: inline-grid;
    place-items: center;
    width: 22px;
    height: 22px;
    border-radius: 6px;
    background: rgba(124, 58, 237, 0.12);
    color: #7c3aed;
    font-size: 13px;
}

.super-cell.faint {
    background: rgba(124, 58, 237, 0.06);
    color: #a78bfa;
}

.dimmed {
    opacity: 0.55;
}

.tiny-tag {
    border-radius: 6px;
    font-weight: 600;
}

.tag-icon {
    margin-inline-end: 3px;
    vertical-align: -2px;
}

.empty-hint {
    color: #94a3b8;
    margin: 0;
}

/* Catalogue */
.catalogue {
    display: flex;
    flex-direction: column;
    gap: 16px;
    min-height: 120px;
}

.module-card-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}

.module-icon {
    color: #6366f1;
}

.perm-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 12px;
}

.perm-item {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    border: 1px solid #eef2f7;
    border-radius: 14px;
    padding: 14px 16px;
    background: #ffffff;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.perm-item:hover {
    border-color: #c7d2fe;
    box-shadow: 0 6px 18px -6px rgba(79, 70, 229, 0.15);
}

.perm-main {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 0;
}

.perm-title-line {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.key-line {
    display: flex;
    align-items: center;
    gap: 2px;
}

.copy-btn {
    padding: 2px;
}

.perm-desc {
    margin: 0;
    color: #64748b;
    font-size: 13px;
    line-height: 1.5;
}

.perm-meta {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 2px;
}

.meta-label {
    font-size: 12px;
    color: #94a3b8;
    font-weight: 600;
}

.muted-text {
    font-size: 12px;
    color: #94a3b8;
}

.role-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    font-weight: 600;
    color: #334155;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    padding: 2px 9px;
}

.perm-actions {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
    flex-shrink: 0;
}

.perm-actions .el-button + .el-button {
    margin-inline-start: 0;
}

/* Dialog */
.field-hint {
    font-size: 12px;
    color: #94a3b8;
    margin-top: 4px;
    line-height: 1.4;
}

.mono-input :deep(input) {
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
}

.full-width {
    width: 100%;
}

.btn-icon {
    font-size: 15px;
}

@media (max-width: 768px) {
    .page-header {
        padding: 18px;
    }

    .search-input,
    .filter-select {
        width: 100%;
    }

    .perm-list {
        grid-template-columns: 1fr;
    }

    .sticky-col {
        min-width: 190px;
    }
}
</style>
