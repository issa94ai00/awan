import api from './index';

// Access control: roles, the permission catalogue and user assignments.
// Backed by spatie/laravel-permission on the server.
export const accessApi = {
    // Roles
    getRoles() {
        return api.get('/admin/access/roles');
    },
    getRole(id) {
        return api.get(`/admin/access/roles/${id}`);
    },
    createRole(data) {
        return api.post('/admin/access/roles', data);
    },
    updateRole(id, data) {
        return api.put(`/admin/access/roles/${id}`, data);
    },
    deleteRole(id, reassignTo = null) {
        return api.delete(`/admin/access/roles/${id}`, { data: reassignTo ? { reassign_to: reassignTo } : {} });
    },
    syncRolePermissions(id, permissionIds) {
        return api.put(`/admin/access/roles/${id}/permissions`, { permission_ids: permissionIds });
    },
    toggleRolePermissions(id, permissionIds, granted) {
        return api.post(`/admin/access/roles/${id}/permissions/toggle`, { permission_ids: permissionIds, granted });
    },
    duplicateRole(id, data = {}) {
        return api.post(`/admin/access/roles/${id}/duplicate`, data);
    },

    // Permission catalogue
    getPermissions() {
        return api.get('/admin/access/permissions');
    },
    createPermission(data) {
        return api.post('/admin/access/permissions', data);
    },
    updatePermission(id, data) {
        return api.put(`/admin/access/permissions/${id}`, data);
    },
    deletePermission(id) {
        return api.delete(`/admin/access/permissions/${id}`);
    },

    // Users
    getUsers(params = {}) {
        return api.get('/admin/access/users', { params });
    },
    getUser(id) {
        return api.get(`/admin/access/users/${id}`);
    },
    createUser(data) {
        return api.post('/admin/access/users', data);
    },
    updateUserAccess(id, data) {
        return api.put(`/admin/access/users/${id}/access`, data);
    },
};

export default accessApi;
