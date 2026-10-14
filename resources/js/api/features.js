import api from './index';

export const featuresApi = {
    getAll() {
        return api.get('/admin/features');
    },

    update(id, data) {
        return api.put(`/admin/features/${id}`, data);
    },

    updateRoles(id, roleIds) {
        return api.put(`/admin/features/${id}/roles`, { role_ids: roleIds });
    },

    toggleRole(id, roleId, granted) {
        return api.post(`/admin/features/${id}/roles/toggle`, { role_id: roleId, granted });
    },

    bulkToggle(keys, isEnabled) {
        return api.post('/admin/features/bulk-toggle', { keys, is_enabled: isEnabled });
    },

    toggleGroup(group, isEnabled) {
        return api.post('/admin/features/group-toggle', { group, is_enabled: isEnabled });
    }
};

export default featuresApi;
