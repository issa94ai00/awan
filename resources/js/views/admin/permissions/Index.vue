<template>
    <div class="permissions-page">
        <div class="page-header">
            <div>
                <p class="eyebrow">{{ $t('security_management') }}</p>
                <h2>{{ $t('nav_permissions') }}</h2>
            </div>
            <el-button type="primary" :icon="Plus" class="primary-btn" @click="openCreatePermissionDialog">
                {{ $t('add_permission') }}
            </el-button>
        </div>

        <div class="stats-grid">
            <div class="stat-card accent">
                <div class="icon-wrap"><el-icon><Lock /></el-icon></div>
                <div>
                    <span class="stat-label">{{ $t('total_permissions') }}</span>
                    <strong>{{ permissions.length }}</strong>
                </div>
            </div>
            <div class="stat-card">
                <div class="icon-wrap"><el-icon><DataAnalysis /></el-icon></div>
                <div>
                    <span class="stat-label">{{ $t('active_groups') }}</span>
                    <strong>{{ moduleCount }}</strong>
                </div>
            </div>
            <div class="stat-card">
                <div class="icon-wrap"><el-icon><Checked /></el-icon></div>
                <div>
                    <span class="stat-label">{{ $t('currently_enabled') }}</span>
                    <strong>{{ activePermissions }}</strong>
                </div>
            </div>
            <div class="stat-card">
                <div class="icon-wrap"><el-icon><Setting /></el-icon></div>
                <div>
                    <span class="stat-label">{{ $t('groups') }}</span>
                    <strong>{{ moduleOptions.length }}</strong>
                </div>
            </div>
        </div>

        <el-card shadow="never" class="toolbar-card">
            <div class="toolbar">
                <el-input v-model="search" :placeholder="$t('search_permission_or_module')" clearable :prefix-icon="Search" />
                <el-select v-model="moduleFilter" :placeholder="$t('filter_by_module')" clearable style="width: 200px">
                    <el-option v-for="module in moduleOptions" :key="module" :label="module" :value="module" />
                </el-select>
            </div>
        </el-card>

        <div class="module-grid">
            <el-card v-for="group in filteredGroups" :key="group.module" shadow="never" class="module-card">
                <template #header>
                    <div class="module-header">
                        <span>{{ group.moduleLabel }}</span>
                        <el-tag type="primary" effect="light">{{ group.permissions.length }}</el-tag>
                    </div>
                </template>

                <div class="permission-list">
                    <div v-for="permission in group.permissions" :key="permission.id" class="permission-item">
                        <div>
                            <strong>{{ permission.name }}</strong>
                            <small>{{ permission.description }}</small>
                        </div>
                        <div class="permission-meta">
                            <el-tag :type="permission.status === 'active' ? 'success' : 'info'" size="small">
                                {{ permission.status === 'active' ? 'مفعلة' : 'غير مفعلة' }}
                            </el-tag>
                            <el-button size="small" text @click="editPermission(permission)">{{ $t('edit') }}</el-button>
                            <el-button size="small" text type="danger" @click="deletePermission(permission.id)">{{ $t('delete') }}</el-button>
                        </div>
                    </div>
                </div>
            </el-card>
        </div>

        <el-dialog v-model="permissionDialogVisible" :title="isEditingPermission ? 'تعديل الصلاحية' : 'إضافة صلاحية جديدة'" width="620px">
            <el-form :model="permissionForm" label-position="top">
                <div class="form-grid">
                    <el-form-item :label="$t('permission_name')">
                        <el-input v-model="permissionForm.name" placeholder="مثل: sales.create" />
                    </el-form-item>
                    <el-form-item :label="$t('unity')">
                        <el-select v-model="permissionForm.module" :placeholder="$t('select_unit')" style="width: 100%">
                            <el-option v-for="module in moduleOptions" :key="module" :label="module" :value="module" />
                        </el-select>
                    </el-form-item>
                </div>

                <el-form-item :label="$t('description')">
                    <el-input v-model="permissionForm.description" type="textarea" :rows="3" :placeholder="$t('permission_short_description')" />
                </el-form-item>

                <el-form-item :label="$t('status')">
                    <el-switch v-model="permissionForm.status" active-value="active" inactive-value="inactive" :active-text="$t('enabled')" :inactive-text="$t('not_enabled')" />
                </el-form-item>
            </el-form>

            <template #footer>
                <span class="dialog-footer">
                    <el-button @click="permissionDialogVisible = false">{{ $t('cancel') }}</el-button>
                    <el-button type="primary" @click="savePermission">{{ $t('save') }}</el-button>
                </span>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import { computed, ref } from 'vue';

const { t } = useI18n();
import {
    Lock,
    DataAnalysis,
    Checked,
    Setting,
    Plus,
    Search,
} from '@element-plus/icons-vue';

const permissions = ref([
    { id: 1, name: 'dashboard.view', description: t('perm_dashboard_view'), module: 'dashboard', status: 'active' },
    { id: 2, name: 'sales.view', description: t('perm_sales_view'), module: 'sales', status: 'active' },
    { id: 3, name: 'sales.create', description: t('perm_invoice_create'), module: 'sales', status: 'active' },
    { id: 4, name: 'sales.edit', description: t('perm_sales_edit'), module: 'sales', status: 'active' },
    { id: 5, name: 'inventory.view', description: t('perm_inventory_view'), module: 'inventory', status: 'active' },
    { id: 6, name: 'inventory.manage', description: t('perm_inventory_manage'), module: 'inventory', status: 'active' },
    { id: 7, name: 'purchases.view', description: t('perm_purchases_view'), module: 'purchases', status: 'active' },
    { id: 8, name: 'purchases.create', description: t('perm_purchase_create'), module: 'purchases', status: 'active' },
    { id: 9, name: 'reports.view', description: t('perm_reports_view'), module: 'reports', status: 'active' },
    { id: 10, name: 'settings.view', description: t('perm_settings_view'), module: 'settings', status: 'active' },
    { id: 11, name: 'users.manage', description: t('perm_users_manage'), module: 'users', status: 'active' },
    { id: 12, name: 'roles.assign', description: t('perm_roles_assign'), module: 'users', status: 'active' },
    { id: 13, name: 'marketing.view', description: t('perm_campaigns_view'), module: 'marketing', status: 'active' },
    { id: 14, name: 'marketing.campaigns', description: t('perm_campaigns_manage'), module: 'marketing', status: 'inactive' },
    { id: 15, name: 'hr.view', description: t('perm_hr_view'), module: 'hr', status: 'active' },
    { id: 16, name: 'hr.manage', description: t('perm_employees_manage'), module: 'hr', status: 'inactive' },
]);

const search = ref('');
const moduleFilter = ref('');
const permissionDialogVisible = ref(false);
const isEditingPermission = ref(false);
const currentPermissionId = ref(null);
const permissionForm = ref({
    name: '',
    module: '',
    description: '',
    status: 'active',
});

const moduleMap = {
    dashboard: t('dashboard'),
    sales: t('nav_sales'),
    inventory: t('nav_inventory'),
    purchases: t('nav_purchases'),
    reports: t('nav_reports'),
    settings: t('settings'),
    users: t('users'),
    marketing: t('marketing'),
    hr: t('nav_hr'),
};

const moduleOptions = computed(() => Object.keys(moduleMap));

const filteredPermissions = computed(() => {
    return permissions.value.filter((permission) => {
        const matchesSearch = !search.value ||
            permission.name.toLowerCase().includes(search.value.toLowerCase()) ||
            permission.description.toLowerCase().includes(search.value.toLowerCase()) ||
            permission.module.toLowerCase().includes(search.value.toLowerCase());

        const matchesModule = !moduleFilter.value || permission.module === moduleFilter.value;

        return matchesSearch && matchesModule;
    });
});

const groupedPermissions = computed(() => {
    const groups = {};

    filteredPermissions.value.forEach((permission) => {
        const key = permission.module;
        if (!groups[key]) {
            groups[key] = {
                module: key,
                moduleLabel: moduleMap[key] || key,
                permissions: [],
            };
        }
        groups[key].permissions.push(permission);
    });

    return Object.values(groups);
});

const filteredGroups = computed(() => groupedPermissions.value.filter((group) => group.permissions.length > 0));
const moduleCount = computed(() => new Set(permissions.value.map((permission) => permission.module)).size);
const activePermissions = computed(() => permissions.value.filter((permission) => permission.status === 'active').length);

const resetPermissionForm = () => {
    permissionForm.value = {
        name: '',
        module: '',
        description: '',
        status: 'active',
    };
};

const openCreatePermissionDialog = () => {
    isEditingPermission.value = false;
    currentPermissionId.value = null;
    resetPermissionForm();
    permissionDialogVisible.value = true;
};

const editPermission = (permission) => {
    isEditingPermission.value = true;
    currentPermissionId.value = permission.id;
    permissionForm.value = {
        name: permission.name,
        module: permission.module,
        description: permission.description,
        status: permission.status,
    };
    permissionDialogVisible.value = true;
};

const savePermission = () => {
    if (!permissionForm.value.name || !permissionForm.value.module) {
        return;
    }

    if (isEditingPermission.value && currentPermissionId.value) {
        const index = permissions.value.findIndex((permission) => permission.id === currentPermissionId.value);
        if (index !== -1) {
            permissions.value[index] = {
                ...permissions.value[index],
                name: permissionForm.value.name,
                module: permissionForm.value.module,
                description: permissionForm.value.description,
                status: permissionForm.value.status,
            };
        }
    } else {
        permissions.value.unshift({
            id: Date.now(),
            name: permissionForm.value.name,
            module: permissionForm.value.module,
            description: permissionForm.value.description,
            status: permissionForm.value.status,
        });
    }

    permissionDialogVisible.value = false;
    resetPermissionForm();
};

const deletePermission = (permissionId) => {
    permissions.value = permissions.value.filter((permission) => permission.id !== permissionId);
};
</script>

<style scoped>
.permissions-page {
    display: flex;
    flex-direction: column;
    gap: 20px;
    padding: 0;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
}

.eyebrow {
    margin: 0 0 6px;
    font-size: 12px;
    color: #7c8aa5;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

.page-header h2 {
    margin: 0;
    font-size: 28px;
    color: #0f172a;
    font-weight: 800;
}

.primary-btn {
    border-radius: 12px;
    height: 42px;
    font-weight: 700;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
}

.stat-card {
    background: linear-gradient(135deg, #ffffff, #f8fafc);
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 18px 20px;
    display: flex;
    align-items: center;
    gap: 14px;
    box-shadow: 0 12px 26px rgba(15, 23, 42, 0.04);
}

.stat-card.accent {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.08), rgba(59, 130, 246, 0.08));
    border-color: rgba(99, 102, 241, 0.2);
}

.icon-wrap {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    background: rgba(99, 102, 241, 0.12);
    color: #4338ca;
    font-size: 20px;
}

.stat-label {
    display: block;
    color: #64748b;
    font-size: 12px;
    margin-bottom: 4px;
}

.stat-card strong {
    font-size: 30px;
    color: #0f172a;
    line-height: 1;
}

.toolbar-card {
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
}

.toolbar {
    display: flex;
    gap: 12px;
    justify-content: space-between;
    align-items: center;
}

.module-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(290px, 1fr));
    gap: 18px;
}

.module-card {
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
    background: linear-gradient(180deg, #ffffff, #f8fafc);
}

.module-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    font-weight: 800;
    color: #0f172a;
}

.permission-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.permission-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 14px;
    padding: 12px 14px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: rgba(248, 250, 252, 0.82);
}

.permission-item strong {
    display: block;
    margin-bottom: 4px;
    color: #111827;
    font-size: 14px;
}

.permission-item small {
    color: #64748b;
    display: block;
    line-height: 1.5;
}

.permission-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

@media (max-width: 768px) {
    .page-header,
    .toolbar,
    .form-grid {
        flex-direction: column;
        align-items: stretch;
        grid-template-columns: 1fr;
    }

    .page-header h2 {
        font-size: 24px;
    }
}
</style>
