<template>
    <div class="roles-page">
        <div class="page-header">
            <div>
                <p class="eyebrow">إدارة النظام</p>
                <h2>الأدوار</h2>
            </div>
            <el-button type="primary" :icon="Plus" class="primary-btn" @click="openCreateRoleDialog">
                إضافة دور جديد
            </el-button>
        </div>

        <div class="stats-grid">
            <div class="stat-card accent">
                <div class="icon-wrap"><el-icon><UserFilled /></el-icon></div>
                <div>
                    <span class="stat-label">إجمالي الأدوار</span>
                    <strong>{{ roles.length }}</strong>
                </div>
            </div>
            <div class="stat-card">
                <div class="icon-wrap"><el-icon><Key /></el-icon></div>
                <div>
                    <span class="stat-label">الصلاحيات المخصصة</span>
                    <strong>{{ totalPermissions }}</strong>
                </div>
            </div>
            <div class="stat-card">
                <div class="icon-wrap"><el-icon><User /></el-icon></div>
                <div>
                    <span class="stat-label">عدد المستخدمين</span>
                    <strong>{{ totalUsers }}</strong>
                </div>
            </div>
            <div class="stat-card">
                <div class="icon-wrap"><el-icon><Checked /></el-icon></div>
                <div>
                    <span class="stat-label">الأدوار النشطة</span>
                    <strong>{{ activeRoles }}</strong>
                </div>
            </div>
        </div>

        <el-card shadow="never" class="toolbar-card">
            <div class="toolbar">
                <el-input v-model="search" placeholder="البحث عن الدور..." clearable :prefix-icon="Search" />
                <el-select v-model="statusFilter" placeholder="حالة الدور" clearable style="width: 180px">
                    <el-option label="نشط" :value="true" />
                    <el-option label="غير نشط" :value="false" />
                </el-select>
            </div>
        </el-card>

        <el-card shadow="never" class="table-card">
            <template #header>
                <div class="card-head">
                    <span>قائمة الأدوار</span>
                    <el-tag type="success" effect="light">{{ filteredRoles.length }} نتيجة</el-tag>
                </div>
            </template>

            <el-table :data="filteredRoles" stripe border style="width: 100%">
                <el-table-column prop="display_name" label="اسم الدور" min-width="180">
                    <template #default="scope">
                        <div class="role-cell">
                            <div class="role-badge" :class="scope.row.color">
                                {{ scope.row.display_name.charAt(0) }}
                            </div>
                            <div>
                                <div class="role-name">{{ scope.row.display_name }}</div>
                                <small>{{ scope.row.name }}</small>
                            </div>
                        </div>
                    </template>
                </el-table-column>

                <el-table-column prop="description" label="الوصف" min-width="220">
                    <template #default="scope">
                        <span class="description-text">{{ scope.row.description }}</span>
                    </template>
                </el-table-column>

                <el-table-column label="المستخدمين" width="120" align="center">
                    <template #default="scope">
                        <el-tag effect="plain" type="info">{{ scope.row.users_count }}</el-tag>
                    </template>
                </el-table-column>

                <el-table-column label="الصلاحيات" width="130" align="center">
                    <template #default="scope">
                        <el-tag effect="plain" type="warning">{{ scope.row.permissions.length }}</el-tag>
                    </template>
                </el-table-column>

                <el-table-column label="الحالة" width="120" align="center">
                    <template #default="scope">
                        <el-tag :type="scope.row.is_active ? 'success' : 'info'" effect="light">
                            {{ scope.row.is_active ? 'نشط' : 'غير نشط' }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column label="الإجراءات" width="220" align="center">
                    <template #default="scope">
                        <div class="actions">
                            <el-button size="small" type="primary" plain @click="editRole(scope.row)">تعديل</el-button>
                            <el-button size="small" type="danger" plain @click="deleteRole(scope.row.id)">حذف</el-button>
                        </div>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <el-dialog v-model="roleDialogVisible" :title="isEditingRole ? 'تعديل الدور' : 'إضافة دور جديد'" width="760px">
            <el-form :model="roleForm" label-position="top" class="role-form">
                <div class="form-grid">
                    <el-form-item label="اسم الدور">
                        <el-input v-model="roleForm.name" placeholder="مثل: sales" />
                    </el-form-item>
                    <el-form-item label="اسم العرض">
                        <el-input v-model="roleForm.display_name" placeholder="مثل: فريق المبيعات" />
                    </el-form-item>
                </div>

                <el-form-item label="الوصف">
                    <el-input v-model="roleForm.description" type="textarea" :rows="3" placeholder="وصف مختصر عن الدور" />
                </el-form-item>

                <el-form-item label="الحالة">
                    <el-switch v-model="roleForm.is_active" active-text="نشط" inactive-text="غير نشط" />
                </el-form-item>

                <div class="permissions-box">
                    <div class="permissions-head">
                        <strong>الصلاحيات</strong>
                        <el-tag type="info" effect="light">{{ selectedPermissionsCount }} محددة</el-tag>
                    </div>

                    <div class="permission-groups">
                        <div v-for="module in permissionCatalog" :key="module.label" class="permission-group">
                            <div class="module-label">{{ module.label }}</div>
                            <div class="checkbox-list">
                                <el-checkbox
                                    v-for="permission in module.permissions"
                                    :key="permission"
                                    :label="permission"
                                    :model-value="roleForm.permissions.includes(permission)"
                                    @change="togglePermission(permission)"
                                >
                                    {{ permission }}
                                </el-checkbox>
                            </div>
                        </div>
                    </div>
                </div>
            </el-form>

            <template #footer>
                <span class="dialog-footer">
                    <el-button @click="roleDialogVisible = false">إلغاء</el-button>
                    <el-button type="primary" @click="saveRole">حفظ الدور</el-button>
                </span>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import {
    UserFilled,
    Key,
    User,
    Checked,
    Plus,
    Search,
} from '@element-plus/icons-vue';

const permissionCatalog = ref([
    { label: 'لوحة التحكم', permissions: ['dashboard.view'] },
    { label: 'المستخدمون', permissions: ['users.manage', 'users.view', 'roles.assign'] },
    { label: 'المبيعات', permissions: ['sales.view', 'sales.create', 'sales.edit'] },
    { label: 'المخزون', permissions: ['inventory.view', 'inventory.manage'] },
    { label: 'المشتريات', permissions: ['purchases.view', 'purchases.create'] },
    { label: 'التقارير', permissions: ['reports.view'] },
    { label: 'التسويق', permissions: ['marketing.view', 'marketing.campaigns'] },
    { label: 'الموارد البشرية', permissions: ['hr.view', 'hr.manage'] },
    { label: 'الإعدادات', permissions: ['settings.view', 'settings.edit'] },
]);

const roles = ref([
    {
        id: 1,
        name: 'admin',
        display_name: 'مدير النظام',
        description: 'صلاحيات كاملة على النظام وإدارة جميع أقسام المؤسسة.',
        users_count: 2,
        is_active: true,
        color: 'teal',
        permissions: ['dashboard.view', 'users.manage', 'users.view', 'roles.assign', 'sales.view', 'sales.create', 'sales.edit', 'inventory.view', 'inventory.manage', 'purchases.view', 'purchases.create', 'reports.view', 'marketing.view', 'marketing.campaigns', 'hr.view', 'hr.manage', 'settings.view', 'settings.edit'],
    },
    {
        id: 2,
        name: 'manager',
        display_name: 'مدير',
        description: 'إدارة العمليات الإدارية والتشغيلية الأساسية.',
        users_count: 3,
        is_active: true,
        color: 'blue',
        permissions: ['dashboard.view', 'sales.view', 'inventory.view', 'reports.view', 'users.view'],
    },
    {
        id: 3,
        name: 'employee',
        display_name: 'موظف',
        description: 'صلاحيات التشغيل اليومية مع وصول محدود.',
        users_count: 8,
        is_active: true,
        color: 'purple',
        permissions: ['dashboard.view', 'sales.view'],
    },
    {
        id: 4,
        name: 'sells',
        display_name: 'مبيعات',
        description: 'إدارة المبيعات والعملاء والعروض.',
        users_count: 5,
        is_active: true,
        color: 'amber',
        permissions: ['dashboard.view', 'sales.view', 'sales.create', 'sales.edit', 'reports.view'],
    },
    {
        id: 5,
        name: 'accountant',
        display_name: 'محاسب',
        description: 'متابعة الحسابات والتقارير المالية.',
        users_count: 2,
        is_active: true,
        color: 'green',
        permissions: ['dashboard.view', 'inventory.view', 'reports.view', 'settings.view'],
    },
    {
        id: 6,
        name: 'marketer',
        display_name: 'مسوق',
        description: 'إدارة الحملات والعروض الرقمية.',
        users_count: 1,
        is_active: false,
        color: 'rose',
        permissions: ['dashboard.view', 'marketing.view'],
    },
]);

const roleDialogVisible = ref(false);
const isEditingRole = ref(false);
const currentRoleId = ref(null);
const search = ref('');
const statusFilter = ref(null);
const roleForm = ref({
    name: '',
    display_name: '',
    description: '',
    is_active: true,
    permissions: [],
});

const totalPermissions = computed(() => roles.value.reduce((sum, role) => sum + role.permissions.length, 0));
const totalUsers = computed(() => roles.value.reduce((sum, role) => sum + role.users_count, 0));
const activeRoles = computed(() => roles.value.filter((role) => role.is_active).length);
const selectedPermissionsCount = computed(() => roleForm.value.permissions.length);

const filteredRoles = computed(() => {
    return roles.value.filter((role) => {
        const matchSearch = !search.value ||
            role.display_name.toLowerCase().includes(search.value.toLowerCase()) ||
            role.name.toLowerCase().includes(search.value.toLowerCase());

        const matchStatus = statusFilter.value === null || role.is_active === statusFilter.value;
        return matchSearch && matchStatus;
    });
});

const resetRoleForm = () => {
    roleForm.value = {
        name: '',
        display_name: '',
        description: '',
        is_active: true,
        permissions: [],
    };
};

const openCreateRoleDialog = () => {
    isEditingRole.value = false;
    currentRoleId.value = null;
    resetRoleForm();
    roleDialogVisible.value = true;
};

const editRole = (role) => {
    isEditingRole.value = true;
    currentRoleId.value = role.id;
    roleForm.value = {
        name: role.name,
        display_name: role.display_name,
        description: role.description,
        is_active: role.is_active,
        permissions: [...role.permissions],
    };
    roleDialogVisible.value = true;
};

const togglePermission = (permission) => {
    const exists = roleForm.value.permissions.includes(permission);
    if (exists) {
        roleForm.value.permissions = roleForm.value.permissions.filter((item) => item !== permission);
    } else {
        roleForm.value.permissions = [...roleForm.value.permissions, permission];
    }
};

const saveRole = () => {
    if (!roleForm.value.name || !roleForm.value.display_name) {
        return;
    }

    if (isEditingRole.value && currentRoleId.value) {
        const index = roles.value.findIndex((role) => role.id === currentRoleId.value);
        if (index !== -1) {
            roles.value[index] = {
                ...roles.value[index],
                name: roleForm.value.name,
                display_name: roleForm.value.display_name,
                description: roleForm.value.description,
                is_active: roleForm.value.is_active,
                permissions: [...roleForm.value.permissions],
            };
        }
    } else {
        roles.value.unshift({
            id: Date.now(),
            name: roleForm.value.name,
            display_name: roleForm.value.display_name,
            description: roleForm.value.description,
            users_count: 0,
            is_active: roleForm.value.is_active,
            color: 'teal',
            permissions: [...roleForm.value.permissions],
        });
    }

    roleDialogVisible.value = false;
    resetRoleForm();
};

const deleteRole = (roleId) => {
    roles.value = roles.value.filter((role) => role.id !== roleId);
};
</script>

<style scoped>
.roles-page {
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
    background: linear-gradient(135deg, rgba(22, 163, 74, 0.08), rgba(45, 212, 191, 0.08));
    border-color: rgba(45, 212, 191, 0.25);
}

.icon-wrap {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    background: rgba(79, 70, 229, 0.12);
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

.toolbar-card,
.table-card {
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
}

.toolbar {
    display: flex;
    gap: 12px;
    align-items: center;
    justify-content: space-between;
}

.card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-weight: 700;
    color: #111827;
}

.role-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}

.role-badge {
    width: 38px;
    height: 38px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    font-weight: 800;
    color: white;
    font-size: 15px;
}

.role-badge.teal { background: linear-gradient(135deg, #14b8a6, #2dd4bf); }
.role-badge.blue { background: linear-gradient(135deg, #3b82f6, #60a5fa); }
.role-badge.purple { background: linear-gradient(135deg, #8b5cf6, #a78bfa); }
.role-badge.amber { background: linear-gradient(135deg, #f59e0b, #fbbf24); }
.role-badge.green { background: linear-gradient(135deg, #22c55e, #4ade80); }
.role-badge.rose { background: linear-gradient(135deg, #f43f5e, #fb7185); }

.role-name {
    font-weight: 700;
    color: #0f172a;
}

.role-cell small {
    color: #64748b;
}

.description-text {
    color: #475569;
    line-height: 1.7;
}

.actions {
    display: flex;
    justify-content: center;
    gap: 8px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.permissions-box {
    margin-top: 16px;
    padding: 16px;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: #f8fafc;
}

.permissions-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    font-size: 15px;
    color: #0f172a;
}

.permission-groups {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
}

.permission-group {
    padding: 12px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: white;
}

.module-label {
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 10px;
}

.checkbox-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

:deep(.el-table th) {
    background: #f8fafc;
    color: #0f172a;
    font-weight: 800;
}

:deep(.el-table td) {
    vertical-align: middle;
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
