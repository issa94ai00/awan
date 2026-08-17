import api from './index';

export const accountingPeriodsApi = {
    getAll(params) {
        return api.get('/admin/accounting/periods', { params });
    },

    create(data) {
        return api.post('/admin/accounting/periods', data);
    },

    // Closing is what makes the dates inside a period final: postings dated
    // into it are refused afterwards, from every path in the system.
    close(id, data = {}) {
        return api.post(`/admin/accounting/periods/${id}/close`, data);
    },

    reopen(id, data = {}) {
        return api.post(`/admin/accounting/periods/${id}/reopen`, data);
    },

    remove(id) {
        return api.delete(`/admin/accounting/periods/${id}`);
    },
};
