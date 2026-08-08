import api from './index';

export const inventoryApi = {
    getSummary() {
        return api.get('/admin/inventory/summary');
    },

    getStock(params) {
        return api.get('/admin/inventory/stock', { params });
    },

    getMovements(params) {
        return api.get('/admin/inventory/movements', { params });
    },

    createMovement(data) {
        return api.post('/admin/inventory/movements', data);
    },

    getWarehouses(params) {
        return api.get('/admin/wms/warehouses', { params });
    },

    exportStock(params) {
        return api.get('/admin/inventory/export', { params, responseType: 'arraybuffer' });
    },

    importStock(formData) {
        return api.post('/admin/inventory/import', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
    },
};
