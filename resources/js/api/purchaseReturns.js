import api from './index';

export const purchaseReturnsApi = {
    getAll(params = {}) {
        return api.get('/admin/purchase-returns', { params });
    },

    get(id) {
        return api.get(`/admin/purchase-returns/${id}`);
    },

    // Records the return: the stock leaves at its FIFO cost, the supplier is
    // owed less, and the tax on the returned portion is given back — one call,
    // one transaction.
    create(data) {
        return api.post('/admin/purchase-returns', data);
    },
};
