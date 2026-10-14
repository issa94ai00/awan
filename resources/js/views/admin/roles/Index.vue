<template>
    <div class="roles-page" v-loading="loading && !roles.length">
        <!-- Header -->
        <div class="page-header">
            <div>
                <span class="eyebrow">{{ $t('ac_eyebrow') }}</span>
                <h2>{{ $t('nav_roles') }}</h2>
                <p class="subtitle">{{ $t('acr_subtitle') }}</p>
            </div>
            <div class="header-actions">
                <el-button :icon="Switch" :disabled="roles.length < 2" @click="openCompare()">
                    {{ $t('acr_compare') }}
                </el-button>
                <el-button :icon="Refresh" :loading="loading" @click="fetchRoles">{{ $t('ac_refresh') }}</el-button>
                <el-button v-if="auth.can('roles.create')" type="primary" :icon="Plus" @click="openEditor()">
                    {{ $t('acr_new_role') }}
                </el-button>
            </div>
        </div>

        <!-- KPI cards double as filters -->
        <div class="stats-grid">
            <button
                v-for="card in statCards"
                :key="card.key"
                type="button"
                class="stat-card"
                :class="[card.tone, { active: card.filter && statusFilter === card.filter }]"
                @click="card.filter && (statusFilter = statusFilter === card.filter ? 'all' : card.filter)"
            >
                <span class="icon-wrap"><el-icon><component :is="card.icon" /></el-icon></span>
                <span class="stat-body">
                    <span class="stat-label">{{ card.label }}</span>
                    <strong>{{ card.value }}</strong>
                </span>
            </button>
        </div>

        <!-- Toolbar -->
        <div class="toolbar">
            <el-input v-model="search" :placeholder="$t('acr_search')" clearable :prefix-icon="Search" class="search-input" />
            <el-segmented v-model="statusFilter" :options="statusOptions" />
            <el-select v-model="sortBy" class="sort-select">
                <el-option :label="$t('acr_sort_name')" value="name" />
                <el-option :label="$t('acr_sort_users')" value="users" />
                <el-option :label="$t('acr_sort_coverage')" value="coverage" />
            </el-select>
        </div>

        <!-- Role cards -->
        <div v-if="visibleRoles.length" class="roles-grid">
            <article
                v-for="role in visibleRoles"
                :key="role.id"
                class="role-card"
                :class="{ inactive: !role.is_active }"
                :style="{ '--role-color': role.color || '#6366f1' }"
            >
                <div class="role-band"></div>
                <header class="role-head">
                    <div class="role-avatar"><el-icon><component :is="role.is_super ? Lock : UserFilled" /></el-icon></div>
                    <div class="role-title">
                        <h3>{{ role.display_name }}</h3>
                        <code>{{ role.name }}</code>
                    </div>
                    <el-dropdown trigger="click" @command="(cmd) => onCommand(cmd, role)">
                        <el-button text circle :icon="MoreFilled" :aria-label="$t('acr_more_actions')" />
                        <template #dropdown>
                            <el-dropdown-menu>
                                <el-dropdown-item command="edit" :icon="Edit">{{ $t('acr_edit') }}</el-dropdown-item>
                                <el-dropdown-item command="compare" :icon="Switch">{{ $t('acr_compare_with') }}</el-dropdown-item>
                                <el-dropdown-item
                                    v-if="auth.can('roles.create') && !role.is_super"
                                    command="duplicate"
                                    :icon="CopyDocument"
                                >{{ $t('acr_duplicate') }}</el-dropdown-item>
                                <el-dropdown-item
                                    v-if="auth.can('roles.delete') && !role.is_system"
                                    command="delete"
                                    :icon="Delete"
                                    divided
                                    class="danger-item"
                                >{{ $t('acr_delete') }}</el-dropdown-item>
                            </el-dropdown-menu>
                        </template>
                    </el-dropdown>
                </header>

                <div class="role-badges">
                    <el-tag v-if="role.is_super" size="small" type="danger" effect="dark" round>
                        <el-icon class="tag-icon"><Lock /></el-icon>{{ $t('ac_super_badge') }}
                    </el-tag>
                    <el-tag v-if="role.is_system" size="small" type="info" effect="plain" round>{{ $t('ac_system_badge') }}</el-tag>
                    <el-tag v-else size="small" type="success" effect="plain" round>{{ $t('ac_custom_badge') }}</el-tag>
                    <el-tag v-if="!role.is_active" size="small" type="warning" effect="light" round>{{ $t('ac_inactive_badge') }}</el-tag>
                </div>

                <p class="role-desc">{{ role.description || $t('acr_no_description') }}</p>

                <div class="coverage">
                    <div class="coverage-line">
                        <span>{{ $t('acr_coverage') }}</span>
                        <strong>{{ role.is_super ? $t('ac_super_badge') : `${role.permissions_count} / ${totalPermissions}` }}</strong>
                    </div>
                    <el-progress
                        :percentage="coverageOf(role)"
                        :show-text="false"
                        :stroke-width="8"
                        :color="role.color || '#6366f1'"
                    />
                </div>

                <footer class="role-foot">
                    <span><el-icon><User /></el-icon>{{ $t('acr_users_n', { n: role.users_count }) }}</span>
                    <span><el-icon><Grid /></el-icon>{{ $t('acr_features_n', { n: role.is_super ? '∞' : role.features_count }) }}</span>
                    <el-button size="small" type="primary" plain @click="openEditor(role)">
                        {{ canEditRole(role) ? $t('acr_manage') : $t('acr_view') }}
                    </el-button>
                </footer>
            </article>
        </div>
        <el-empty v-else-if="!loading" :description="$t('ac_no_results')" />

        <!-- Editor drawer -->
        <el-drawer
            v-model="editor.open"
            :size="drawerSize"
            :before-close="beforeCloseEditor"
            class="role-drawer"
            destroy-on-close
        >
            <template #header>
                <div class="drawer-head" :style="{ '--role-color': form.color || '#6366f1' }">
                    <div class="role-avatar"><el-icon><component :is="editor.role?.is_super ? Lock : UserFilled" /></el-icon></div>
                    <div>
                        <span class="eyebrow">{{ editor.role ? $t('acr_edit_role') : $t('acr_new_role') }}</span>
                        <h3>{{ form.display_name || $t('acr_untitled') }}</h3>
                    </div>
                </div>
            </template>

            <el-tabs v-model="editor.tab" class="editor-tabs">
                <!-- Details -->
                <el-tab-pane :label="$t('acr_tab_details')" name="details">
                    <el-form ref="formRef" :model="form" :rules="rules" label-position="top" :disabled="!editable">
                        <el-form-item :label="$t('acr_display_name')" prop="display_name">
                            <el-input v-model="form.display_name" maxlength="100" show-word-limit />
                        </el-form-item>
                        <el-form-item :label="$t('acr_identifier')" prop="name">
                            <el-input
                                v-model="form.name"
                                dir="ltr"
                                class="mono"
                                maxlength="50"
                                :disabled="!!editor.role?.is_system"
                                @input="form.name = form.name.toLowerCase().replace(/[^a-z0-9_]/g, '_')"
                            />
                            <div class="field-hint">
                                {{ editor.role?.is_system ? $t('acr_identifier_locked') : $t('acr_identifier_hint') }}
                            </div>
                        </el-form-item>
                        <el-form-item :label="$t('acr_description')">
                            <el-input v-model="form.description" type="textarea" :rows="3" maxlength="500" show-word-limit />
                        </el-form-item>
                        <el-form-item :label="$t('acr_color')">
                            <div class="swatches">
                                <button
                                    v-for="c in palette"
                                    :key="c"
                                    type="button"
                                    class="swatch"
                                    :class="{ selected: form.color === c }"
                                    :style="{ background: c }"
                                    :aria-label="c"
                                    @click="form.color = c"
                                ></button>
                                <el-color-picker v-model="form.color" size="small" />
                            </div>
                        </el-form-item>
                        <el-form-item :label="$t('acr_status')">
                            <el-switch
                                v-model="form.is_active"
                                :disabled="!!editor.role?.is_super"
                                :active-text="$t('acr_active')"
                                :inactive-text="$t('acr_inactive')"
                            />
                            <div v-if="!form.is_active" class="field-hint warn">{{ $t('acr_inactive_hint') }}</div>
                        </el-form-item>
                    </el-form>
                </el-tab-pane>

                <!-- Permissions -->
                <el-tab-pane name="permissions">
                    <template #label>
                        {{ $t('acr_tab_permissions') }}
                        <el-badge v-if="!editor.role?.is_super" :value="selectedIds.size" type="primary" class="tab-badge" />
                    </template>

                    <el-alert v-if="editor.role?.is_super" type="warning" :closable="false" show-icon :title="$t('ac_super_note')" />
                    <template v-else>
                        <el-alert
                            v-if="!auth.isSuperAdmin && permissionsEditable"
                            type="info"
                            :closable="false"
                            show-icon
                            :title="$t('acr_own_permissions_note')"
                            class="mb-12"
                        />
                        <div class="perm-toolbar">
                            <el-input v-model="permSearch" :placeholder="$t('ac_search_permissions')" clearable :prefix-icon="Search" />
                            <div class="perm-actions" v-if="permissionsEditable">
                                <el-button size="small" @click="applyPreset('view')">{{ $t('acr_preset_view') }}</el-button>
                                <el-button size="small" @click="applyPreset('all')">{{ $t('ac_select_all') }}</el-button>
                                <el-button size="small" @click="applyPreset('none')">{{ $t('ac_clear_all') }}</el-button>
                            </div>
                        </div>

                        <div class="perm-summary">
                            <span>{{ $t('acr_selected_n', { n: selectedIds.size, total: totalPermissions }) }}</span>
                            <span v-if="diff.added.length" class="added">+{{ diff.added.length }}</span>
                            <span v-if="diff.removed.length" class="removed">−{{ diff.removed.length }}</span>
                        </div>

                        <el-collapse v-model="openModules" class="module-list">
                            <el-collapse-item v-for="mod in filteredModules" :key="mod.module" :name="mod.module">
                                <template #title>
                                    <div class="module-title" @click.stop>
                                        <el-checkbox
                                            :model-value="moduleState(mod) === 'all'"
                                            :indeterminate="moduleState(mod) === 'some'"
                                            :disabled="!permissionsEditable"
                                            @change="(v) => toggleModule(mod, v)"
                                        />
                                    </div>
                                    <span class="module-name">{{ moduleLabel(mod.module) }}</span>
                                    <span class="module-count">{{ countIn(mod) }}/{{ mod.permissions.length }}</span>
                                </template>
                                <div class="perm-grid">
                                    <label
                                        v-for="p in mod.permissions"
                                        :key="p.id"
                                        class="perm-item"
                                        :class="{
                                            checked: selectedIds.has(p.id),
                                            added: selectedIds.has(p.id) && !originalIds.has(p.id),
                                            removed: !selectedIds.has(p.id) && originalIds.has(p.id),
                                            muted: !p.is_active,
                                        }"
                                    >
                                        <el-checkbox
                                            :model-value="selectedIds.has(p.id)"
                                            :disabled="!canTogglePermission(p)"
                                            @change="(v) => togglePermission(p.id, v)"
                                        />
                                        <span class="perm-text">
                                            <span class="perm-name">{{ p.display_name }}</span>
                                            <code>{{ p.name }}</code>
                                        </span>
                                        <el-tooltip v-if="!canTogglePermission(p) && permissionsEditable" :content="$t('acr_not_yours')" placement="top">
                                            <el-icon class="lock-icon"><Lock /></el-icon>
                                        </el-tooltip>
                                        <el-tag v-if="!p.is_active" size="small" type="warning" effect="plain">{{ $t('ac_inactive_badge') }}</el-tag>
                                    </label>
                                </div>
                            </el-collapse-item>
                        </el-collapse>
                        <el-empty v-if="!filteredModules.length" :description="$t('ac_no_results')" :image-size="80" />
                    </template>
                </el-tab-pane>

                <!-- Members -->
                <el-tab-pane v-if="editor.role" name="members">
                    <template #label>
                        {{ $t('acr_tab_members') }}
                        <el-badge :value="editor.role.users_count" type="info" class="tab-badge" />
                    </template>
                    <div v-loading="editor.loadingMembers" class="members">
                        <div v-for="m in editor.members" :key="m.id" class="member-row">
                            <div class="member-avatar">{{ (m.name || '?').charAt(0) }}</div>
                            <div class="member-text">
                                <strong>{{ m.name }}</strong>
                                <small dir="ltr">{{ m.email }}</small>
                            </div>
                            <el-tag v-if="m.is_primary" size="small" effect="plain" round>{{ $t('ac_primary_badge') }}</el-tag>
                        </div>
                        <el-empty v-if="!editor.loadingMembers && !editor.members.length" :description="$t('acr_no_members')" :image-size="80" />
                        <router-link v-if="auth.can('users.view')" :to="{ path: '/admin/users', query: { role_id: editor.role.id } }" class="members-link">
                            {{ $t('acr_manage_members') }}
                        </router-link>
                    </div>
                </el-tab-pane>
            </el-tabs>

            <template #footer>
                <div class="drawer-footer">
                    <span v-if="dirtyCount" class="dirty">{{ $t('ac_unsaved_changes', { n: dirtyCount }) }}</span>
                    <span v-else-if="!editable" class="dirty muted">{{ $t('acr_read_only') }}</span>
                    <span class="spacer"></span>
                    <el-button v-if="dirtyCount && editor.role" @click="resetForm">{{ $t('ac_discard') }}</el-button>
                    <el-button @click="closeEditor">{{ $t('ac_close') }}</el-button>
                    <el-button v-if="editable" type="primary" :loading="saving" :disabled="editor.role && !dirtyCount" @click="save">
                        {{ editor.role ? $t('ac_save_changes') : $t('acr_create') }}
                    </el-button>
                </div>
            </template>
        </el-drawer>

        <!-- Delete with reassignment -->
        <el-dialog v-model="deleting.open" :title="$t('acr_delete_title')" width="min(480px, 92vw)">
            <p>{{ $t('acr_delete_confirm', { name: deleting.role?.display_name }) }}</p>
            <template v-if="deleting.role?.users_count">
                <el-alert type="warning" :closable="false" show-icon :title="$t('acr_delete_has_users', { n: deleting.role.users_count })" />
                <el-select v-model="deleting.reassignTo" :placeholder="$t('acr_reassign_to')" class="full mt-12" filterable>
                    <el-option
                        v-for="r in reassignOptions"
                        :key="r.id"
                        :label="r.display_name"
                        :value="r.id"
                        :disabled="r.is_super && !auth.isSuperAdmin"
                    />
                </el-select>
            </template>
            <template #footer>
                <el-button @click="deleting.open = false">{{ $t('ac_cancel') }}</el-button>
                <el-button
                    type="danger"
                    :loading="deleting.busy"
                    :disabled="!!deleting.role?.users_count && !deleting.reassignTo"
                    @click="confirmDelete"
                >{{ $t('acr_delete') }}</el-button>
            </template>
        </el-dialog>

        <!-- Compare two roles -->
        <el-dialog v-model="compare.open" :title="$t('acr_compare_title')" width="min(860px, 96vw)" class="compare-dialog">
            <div class="compare-pickers">
                <el-select v-model="compare.a" filterable>
                    <el-option v-for="r in roles" :key="r.id" :label="r.display_name" :value="r.id" />
                </el-select>
                <el-icon class="vs"><Switch /></el-icon>
                <el-select v-model="compare.b" filterable>
                    <el-option v-for="r in roles" :key="r.id" :label="r.display_name" :value="r.id" />
                </el-select>
            </div>
            <div v-if="compareResult" class="compare-summary">
                <span class="only-a">{{ $t('acr_only_in', { name: compareResult.a.display_name, n: compareResult.onlyA }) }}</span>
                <span class="both">{{ $t('acr_in_both', { n: compareResult.both }) }}</span>
                <span class="only-b">{{ $t('acr_only_in', { name: compareResult.b.display_name, n: compareResult.onlyB }) }}</span>
                <el-checkbox v-model="compare.diffOnly">{{ $t('acr_differences_only') }}</el-checkbox>
            </div>
            <div v-if="compareResult" class="compare-table-wrap">
                <table class="compare-table">
                    <thead>
                        <tr>
                            <th>{{ $t('ac_module') }}</th>
                            <th>{{ compareResult.a.display_name }}</th>
                            <th>{{ compareResult.b.display_name }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="mod in compareResult.modules" :key="mod.module">
                            <tr class="module-row"><td colspan="3">{{ moduleLabel(mod.module) }}</td></tr>
                            <tr v-for="p in mod.rows" :key="p.id" :class="{ differs: p.a !== p.b }">
                                <td>
                                    <span class="perm-name">{{ p.display_name }}</span>
                                    <code>{{ p.name }}</code>
                                </td>
                                <td class="mark"><el-icon :class="p.a ? 'yes' : 'no'"><component :is="p.a ? Check : Close" /></el-icon></td>
                                <td class="mark"><el-icon :class="p.b ? 'yes' : 'no'"><component :is="p.b ? Check : Close" /></el-icon></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <el-empty v-if="!compareResult.modules.length" :description="$t('acr_identical')" :image-size="80" />
            </div>
        </el-dialog>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
    Check, Checked, Close, CopyDocument, Delete, Edit, Grid, Key, Lock, MoreFilled,
    Plus, Refresh, Search, Switch, User, UserFilled,
} from '@element-plus/icons-vue';
import accessApi from '@/api/access';
import { useAuthStore } from '@/stores/auth';
import { matchesSearch } from '@/utils/search';

const { t, te } = useI18n();
const auth = useAuthStore();

const palette = ['#7c3aed', '#dc2626', '#2563eb', '#0891b2', '#16a34a', '#d97706', '#db2777', '#475569'];

const loading = ref(false);
const saving = ref(false);
const roles = ref([]);
const modules = ref([]);
const stats = ref({ total: 0, active: 0, custom: 0, permissions: 0, users_with_roles: 0 });

const search = ref('');
const statusFilter = ref('all');
const sortBy = ref('name');

const errorMessage = (error, fallback) => error?.response?.data?.message || fallback || t('acr_action_failed');
const moduleLabel = (module) => (te(`ac_module_${module}`) ? t(`ac_module_${module}`) : module);

const allPermissions = computed(() => modules.value.flatMap((m) => m.permissions));
const totalPermissions = computed(() => allPermissions.value.length);
const coverageOf = (role) => (role.is_super ? 100 : totalPermissions.value
    ? Math.round((role.permissions_count / totalPermissions.value) * 100) : 0);

const statusOptions = computed(() => [
    { label: t('acr_filter_all'), value: 'all' },
    { label: t('acr_active'), value: 'active' },
    { label: t('acr_inactive'), value: 'inactive' },
    { label: t('ac_custom_badge'), value: 'custom' },
]);

const statCards = computed(() => [
    { key: 'total', label: t('acr_stat_total'), value: stats.value.total, icon: UserFilled, tone: 'indigo', filter: 'all' },
    { key: 'active', label: t('acr_stat_active'), value: stats.value.active, icon: Checked, tone: 'green', filter: 'active' },
    { key: 'custom', label: t('acr_stat_custom'), value: stats.value.custom, icon: Edit, tone: 'amber', filter: 'custom' },
    { key: 'users', label: t('acr_stat_users'), value: stats.value.users_with_roles, icon: User, tone: 'cyan' },
    { key: 'perms', label: t('acr_stat_permissions'), value: stats.value.permissions, icon: Key, tone: 'slate' },
]);

const visibleRoles = computed(() => {
    const list = roles.value.filter((role) => {
        if (statusFilter.value === 'active' && !role.is_active) return false;
        if (statusFilter.value === 'inactive' && role.is_active) return false;
        if (statusFilter.value === 'custom' && role.is_system) return false;
        return matchesSearch([role.display_name, role.name, role.description], search.value);
    });
    const sorters = {
        name: (a, b) => (b.is_super - a.is_super) || a.display_name.localeCompare(b.display_name),
        users: (a, b) => b.users_count - a.users_count,
        coverage: (a, b) => coverageOf(b) - coverageOf(a),
    };
    return [...list].sort(sorters[sortBy.value]);
});

async function fetchRoles() {
    loading.value = true;
    try {
        const { data } = await accessApi.getRoles();
        roles.value = data.data.roles;
        modules.value = data.data.modules;
        stats.value = data.data.stats;
    } catch (error) {
        ElMessage.error(errorMessage(error));
    } finally {
        loading.value = false;
    }
}

// ------------------------------------------------------------------ editor

const formRef = ref(null);
const editor = reactive({ open: false, role: null, tab: 'details', members: [], loadingMembers: false });
const form = reactive({ display_name: '', name: '', description: '', color: '#2563eb', is_active: true });
const original = ref({});
const selectedIds = ref(new Set());
const originalIds = ref(new Set());
const permSearch = ref('');
const openModules = ref([]);

const drawerSize = computed(() => (window.innerWidth < 760 ? '100%' : '680px'));

const canEditRole = (role) => {
    if (!auth.can('roles.edit')) return false;
    if (role.name === 'master') return auth.isMaster;
    if (role.is_super) return auth.isSuperAdmin;
    return true;
};
const editable = computed(() => (editor.role ? canEditRole(editor.role) : auth.can('roles.create')));
const permissionsEditable = computed(() => editable.value && !editor.role?.is_super
    && (editor.role ? auth.can(['roles.edit', 'permissions.assign']) : true));

// A non-super editor may only add permissions they hold themselves (the
// server refuses the rest); taking one away is always allowed.
const canTogglePermission = (p) => permissionsEditable.value
    && (auth.isSuperAdmin || selectedIds.value.has(p.id) || auth.permissions.includes(p.name));

const rules = computed(() => ({
    display_name: [{ required: true, message: t('acr_required'), trigger: 'blur' }],
    name: [
        { required: true, message: t('acr_required'), trigger: 'blur' },
        { pattern: /^[a-z][a-z0-9_]*$/, message: t('acr_identifier_hint'), trigger: 'blur' },
    ],
}));

function openEditor(role = null, tab = 'details') {
    editor.role = role;
    editor.tab = tab;
    editor.members = [];
    const source = role || { display_name: '', name: '', description: '', color: palette[2], is_active: true, permission_ids: [] };
    Object.assign(form, {
        display_name: source.display_name,
        name: source.name,
        description: source.description || '',
        color: source.color || palette[2],
        is_active: source.is_active,
    });
    original.value = { ...form };
    originalIds.value = new Set(source.permission_ids || []);
    selectedIds.value = new Set(source.permission_ids || []);
    permSearch.value = '';
    openModules.value = modules.value.filter((m) => countIn(m) > 0).map((m) => m.module);
    if (!openModules.value.length && modules.value.length) openModules.value = [modules.value[0].module];
    editor.open = true;
    if (role) loadMembers(role);
}

async function loadMembers(role) {
    editor.loadingMembers = true;
    try {
        const { data } = await accessApi.getRole(role.id);
        if (editor.role?.id === role.id) editor.members = data.data.users;
    } catch {
        editor.members = [];
    } finally {
        editor.loadingMembers = false;
    }
}

const detailsChanged = computed(() => ['display_name', 'name', 'description', 'color', 'is_active']
    .filter((key) => (form[key] ?? '') !== (original.value[key] ?? '')));

const diff = computed(() => ({
    added: [...selectedIds.value].filter((id) => !originalIds.value.has(id)),
    removed: [...originalIds.value].filter((id) => !selectedIds.value.has(id)),
}));

const dirtyCount = computed(() => detailsChanged.value.length + diff.value.added.length + diff.value.removed.length);

function resetForm() {
    Object.assign(form, original.value);
    selectedIds.value = new Set(originalIds.value);
}

function togglePermission(id, on) {
    const next = new Set(selectedIds.value);
    on ? next.add(id) : next.delete(id);
    selectedIds.value = next;
}

const filteredModules = computed(() => modules.value
    .map((m) => ({
        ...m,
        permissions: m.permissions.filter((p) => matchesSearch([p.display_name, p.name, p.description, moduleLabel(m.module)], permSearch.value)),
    }))
    .filter((m) => m.permissions.length));

const countIn = (mod) => mod.permissions.filter((p) => selectedIds.value.has(p.id)).length;

function moduleState(mod) {
    const n = countIn(mod);
    if (!n) return 'none';
    return n === mod.permissions.length ? 'all' : 'some';
}

function toggleModule(mod, on) {
    const next = new Set(selectedIds.value);
    mod.permissions.forEach((p) => {
        if (!canTogglePermission(p) && on) return;
        on ? next.add(p.id) : next.delete(p.id);
    });
    selectedIds.value = next;
}

function applyPreset(kind) {
    const pool = filteredModules.value.flatMap((m) => m.permissions);
    const next = new Set(selectedIds.value);
    pool.forEach((p) => {
        if (kind === 'none') next.delete(p.id);
        else if ((kind === 'all' || p.name.endsWith('.view')) && canTogglePermission({ ...p })) next.add(p.id);
    });
    selectedIds.value = next;
}

async function beforeCloseEditor(done) {
    if (!dirtyCount.value) return done();
    try {
        await ElMessageBox.confirm(t('acr_discard_confirm'), t('ac_confirm'), { type: 'warning' });
        done();
    } catch {
        // stay open
    }
}

const closeEditor = () => beforeCloseEditor(() => { editor.open = false; });

async function save() {
    try {
        await formRef.value?.validate();
    } catch {
        editor.tab = 'details';
        return;
    }
    saving.value = true;
    try {
        if (!editor.role) {
            await accessApi.createRole({ ...form, permission_ids: [...selectedIds.value] });
        } else {
            if (detailsChanged.value.length) {
                const payload = Object.fromEntries(detailsChanged.value.map((key) => [key, form[key]]));
                await accessApi.updateRole(editor.role.id, payload);
            }
            if (diff.value.added.length || diff.value.removed.length) {
                await accessApi.syncRolePermissions(editor.role.id, [...selectedIds.value]);
            }
        }
        ElMessage.success(editor.role ? t('ac_saved') : t('acr_created'));
        original.value = { ...form };
        originalIds.value = new Set(selectedIds.value);
        editor.open = false;
        await fetchRoles();
        auth.refreshUser?.();
    } catch (error) {
        const fieldErrors = error?.response?.data?.errors;
        ElMessage.error(fieldErrors ? Object.values(fieldErrors).flat()[0] : errorMessage(error));
    } finally {
        saving.value = false;
    }
}

// --------------------------------------------------------- other actions

function onCommand(cmd, role) {
    if (cmd === 'edit') openEditor(role);
    if (cmd === 'duplicate') duplicate(role);
    if (cmd === 'delete') openDelete(role);
    if (cmd === 'compare') openCompare(role);
}

async function duplicate(role) {
    try {
        const { value } = await ElMessageBox.prompt(t('acr_duplicate_prompt'), t('acr_duplicate'), {
            inputValue: `${role.display_name} (${t('acr_copy')})`,
            inputValidator: (v) => !!v?.trim() || t('acr_required'),
        });
        await accessApi.duplicateRole(role.id, { display_name: value.trim() });
        ElMessage.success(t('acr_duplicated'));
        await fetchRoles();
    } catch (error) {
        if (error !== 'cancel' && error !== 'close') ElMessage.error(errorMessage(error));
    }
}

const deleting = reactive({ open: false, role: null, reassignTo: null, busy: false });
const reassignOptions = computed(() => roles.value.filter((r) => r.id !== deleting.role?.id && r.name !== 'master'));

function openDelete(role) {
    Object.assign(deleting, { open: true, role, reassignTo: null, busy: false });
}

async function confirmDelete() {
    deleting.busy = true;
    try {
        await accessApi.deleteRole(deleting.role.id, deleting.reassignTo);
        ElMessage.success(t('acr_deleted'));
        deleting.open = false;
        await fetchRoles();
    } catch (error) {
        ElMessage.error(errorMessage(error));
    } finally {
        deleting.busy = false;
    }
}

const compare = reactive({ open: false, a: null, b: null, diffOnly: true });

function openCompare(role = null) {
    const others = roles.value.filter((r) => !r.is_super);
    compare.a = role?.id ?? others[0]?.id ?? roles.value[0]?.id;
    compare.b = (others.find((r) => r.id !== compare.a) || roles.value.find((r) => r.id !== compare.a))?.id;
    compare.open = true;
}

const compareResult = computed(() => {
    const a = roles.value.find((r) => r.id === compare.a);
    const b = roles.value.find((r) => r.id === compare.b);
    if (!a || !b) return null;
    const has = (role, id) => role.is_super || role.permission_ids.includes(id);
    let onlyA = 0; let onlyB = 0; let both = 0;
    const mods = modules.value.map((m) => {
        const rows = m.permissions.map((p) => {
            const row = { ...p, a: has(a, p.id), b: has(b, p.id) };
            if (row.a && row.b) both += 1; else if (row.a) onlyA += 1; else if (row.b) onlyB += 1;
            return row;
        }).filter((row) => !compare.diffOnly || row.a !== row.b);
        return { module: m.module, rows };
    }).filter((m) => m.rows.length);
    return { a, b, modules: mods, onlyA, onlyB, both };
});

watch(() => compare.a, (id) => { if (id === compare.b) compare.b = roles.value.find((r) => r.id !== id)?.id; });

onMounted(fetchRoles);
</script>

<style scoped>
.roles-page {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
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
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.page-header h2 {
    margin: 4px 0 0;
    font-size: 26px;
    font-weight: 800;
    color: #0f172a;
}

.subtitle {
    margin: 6px 0 0;
    color: #64748b;
    font-size: 14px;
    max-width: 640px;
    line-height: 1.6;
}

.header-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

/* KPI */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 14px;
}

.stat-card {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px 18px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 18px;
    text-align: start;
    cursor: pointer;
    font: inherit;
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 24px -6px rgba(15, 23, 42, 0.1);
}

.stat-card.active {
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
}

.icon-wrap {
    width: 46px;
    height: 46px;
    border-radius: 14px;
    display: grid;
    place-items: center;
    font-size: 20px;
    flex-shrink: 0;
}

.indigo .icon-wrap { background: rgba(99, 102, 241, 0.12); color: #4f46e5; }
.green .icon-wrap { background: rgba(16, 185, 129, 0.12); color: #059669; }
.amber .icon-wrap { background: rgba(245, 158, 11, 0.14); color: #d97706; }
.cyan .icon-wrap { background: rgba(8, 145, 178, 0.12); color: #0891b2; }
.slate .icon-wrap { background: rgba(100, 116, 139, 0.12); color: #475569; }

.stat-body {
    display: flex;
    flex-direction: column;
}

.stat-label {
    font-size: 13px;
    color: #64748b;
    font-weight: 600;
}

.stat-card strong {
    font-size: 26px;
    color: #0f172a;
    font-weight: 800;
    line-height: 1.15;
}

/* Toolbar */
.toolbar {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 18px;
    padding: 14px 16px;
}

.search-input {
    flex: 1 1 260px;
    max-width: 360px;
}

.sort-select {
    width: 180px;
}

/* Cards */
.roles-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 16px;
}

.role-card {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 20px;
    padding-top: 24px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    overflow: hidden;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.role-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 14px 30px -10px rgba(15, 23, 42, 0.15);
}

.role-card.inactive {
    opacity: 0.72;
}

.role-band {
    position: absolute;
    inset-inline: 0;
    top: 0;
    height: 4px;
    background: var(--role-color);
}

.role-head {
    display: flex;
    align-items: center;
    gap: 12px;
}

.role-avatar {
    width: 44px;
    height: 44px;
    border-radius: 14px;
    display: grid;
    place-items: center;
    flex-shrink: 0;
    font-weight: 800;
    font-size: 18px;
    color: var(--role-color);
    background: color-mix(in srgb, var(--role-color) 14%, white);
}

.role-title {
    flex: 1;
    min-width: 0;
}

.role-title h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 800;
    color: #0f172a;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

code {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 12px;
    color: #64748b;
    direction: ltr;
    unicode-bidi: isolate;
}

.role-badges {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.tag-icon {
    margin-inline-end: 4px;
    vertical-align: -2px;
}

.role-desc {
    margin: 0;
    color: #475569;
    font-size: 13px;
    line-height: 1.6;
    min-height: 2.6em;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.coverage-line {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    color: #64748b;
    margin-bottom: 6px;
}

.coverage-line strong {
    color: #0f172a;
}

.role-foot {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-top: auto;
    padding-top: 12px;
    border-top: 1px dashed #e2e8f0;
    font-size: 13px;
    color: #475569;
}

.role-foot span {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.role-foot .el-button {
    margin-inline-start: auto;
}

:deep(.danger-item) {
    color: #dc2626;
}

/* Drawer */
.drawer-head {
    display: flex;
    align-items: center;
    gap: 12px;
}

.drawer-head h3 {
    margin: 2px 0 0;
    font-size: 18px;
    color: #0f172a;
}

.field-hint {
    font-size: 12px;
    color: #94a3b8;
    margin-top: 4px;
    line-height: 1.5;
}

.field-hint.warn {
    color: #d97706;
}

.mono :deep(input) {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
}

.swatches {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.swatch {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    border: 2px solid transparent;
    cursor: pointer;
    box-shadow: inset 0 0 0 2px #ffffff;
}

.swatch.selected {
    border-color: #0f172a;
}

.tab-badge {
    margin-inline-start: 6px;
    vertical-align: middle;
}

.mb-12 { margin-bottom: 12px; }
.mt-12 { margin-top: 12px; }
.full { width: 100%; }

.perm-toolbar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
}

.perm-toolbar .el-input {
    flex: 1 1 220px;
}

.perm-actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.perm-actions .el-button + .el-button {
    margin-inline-start: 0;
}

.perm-summary {
    display: flex;
    gap: 10px;
    align-items: center;
    margin: 12px 0;
    font-size: 13px;
    color: #475569;
    font-weight: 600;
}

.perm-summary .added { color: #059669; }
.perm-summary .removed { color: #dc2626; }

.module-list {
    border-top: none;
}

.module-title {
    display: inline-flex;
    margin-inline-end: 10px;
}

.module-name {
    font-weight: 700;
    color: #0f172a;
}

.module-count {
    margin-inline-start: auto;
    margin-inline-end: 10px;
    font-size: 12px;
    color: #64748b;
    background: #f1f5f9;
    padding: 2px 8px;
    border-radius: 999px;
}

.perm-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 8px;
}

.perm-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    cursor: pointer;
    transition: background 0.15s ease, border-color 0.15s ease;
}

.perm-item.checked {
    background: #f5f7ff;
    border-color: #c7d2fe;
}

.perm-item.added {
    border-color: #6ee7b7;
    background: #ecfdf5;
}

.perm-item.removed {
    border-color: #fecaca;
    background: #fef2f2;
}

.perm-item.muted {
    opacity: 0.6;
}

.perm-text {
    display: flex;
    flex-direction: column;
    min-width: 0;
    flex: 1;
}

.perm-name {
    font-size: 13px;
    font-weight: 600;
    color: #0f172a;
}

.lock-icon {
    color: #94a3b8;
}

.members {
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-height: 120px;
}

.member-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
}

.member-avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    background: #eef2ff;
    color: #4f46e5;
    font-weight: 700;
}

.member-text {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-width: 0;
}

.member-text small {
    color: #64748b;
    text-align: start;
}

.members-link {
    margin-top: 6px;
    color: #4f46e5;
    font-weight: 600;
    font-size: 13px;
}

.drawer-footer {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.drawer-footer .spacer {
    flex: 1;
}

.dirty {
    font-size: 13px;
    font-weight: 700;
    color: #d97706;
}

.dirty.muted {
    color: #94a3b8;
}

/* Compare */
.compare-pickers {
    display: flex;
    align-items: center;
    gap: 12px;
}

.compare-pickers .el-select {
    flex: 1;
}

.vs {
    color: #94a3b8;
    font-size: 18px;
}

.compare-summary {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
    margin: 14px 0;
    font-size: 13px;
    font-weight: 600;
}

.compare-summary span {
    padding: 4px 10px;
    border-radius: 999px;
}

.only-a { background: #eef2ff; color: #4338ca; }
.only-b { background: #fdf2f8; color: #be185d; }
.both { background: #ecfdf5; color: #047857; }

.compare-summary .el-checkbox {
    margin-inline-start: auto;
}

.compare-table-wrap {
    max-height: 60vh;
    overflow: auto;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
}

.compare-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.compare-table th {
    position: sticky;
    top: 0;
    background: #f8fafc;
    padding: 10px 12px;
    text-align: start;
    font-weight: 700;
    color: #0f172a;
    z-index: 1;
}

.compare-table td {
    padding: 8px 12px;
    border-top: 1px solid #f1f5f9;
}

.compare-table td:first-child {
    display: flex;
    flex-direction: column;
}

.compare-table .module-row td {
    display: table-cell;
    background: #f8fafc;
    font-weight: 700;
    color: #475569;
    font-size: 12px;
}

.compare-table tr.differs {
    background: #fffbeb;
}

.mark {
    text-align: center;
    width: 120px;
}

.mark .yes { color: #059669; font-size: 16px; }
.mark .no { color: #cbd5e1; font-size: 16px; }

@media (max-width: 640px) {
    .page-header {
        padding: 18px;
    }

    .search-input,
    .sort-select {
        max-width: none;
        width: 100%;
    }

    .compare-pickers {
        flex-direction: column;
        align-items: stretch;
    }

    .vs {
        align-self: center;
        transform: rotate(90deg);
    }
}
</style>
