import api from './index';

export const costCentersApi = {
    getAll(params = {}) {
        return api.get('/admin/accounting/cost-centers', { params });
    },

    create(data) {
        return api.post('/admin/accounting/cost-centers', data);
    },

    update(id, data) {
        return api.put(`/admin/accounting/cost-centers/${id}`, data);
    },

    // A centre that has carried figures is deactivated, not deleted: deleting
    // one detaches it from every line that named it, so the analysis of a
    // closed month would silently change.
    remove(id) {
        return api.delete(`/admin/accounting/cost-centers/${id}`);
    },
};
