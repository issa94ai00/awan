import api from './index';

export const stockMovementsApi = {
    getAll(params) {
        return api.get('/admin/inventory/movements', { params });
    },

    create(data) {
        return api.post('/admin/inventory/movements', data);
    }
};
