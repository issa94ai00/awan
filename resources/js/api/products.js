import api from './index';

export const productsApi = {
    getAll(params) {
        return api.get('/admin/products', { params });
    },
    
    getById(id) {
        return api.get(`/admin/products/${id}`);
    },
    
    create(data) {
        return api.post('/admin/products', data);
    },
    
    update(id, data) {
        return api.put(`/admin/products/${id}`, data);
    },

    updateVariant(id, data) {
        return api.put(`/admin/product-variants/${id}`, data);
    },

    createVariant(data) {
        return api.post('/admin/product-variants', data);
    },

    deleteVariant(id) {
        return api.delete(`/admin/product-variants/${id}`);
    },

    delete(id) {
        return api.delete(`/admin/products/${id}`);
    },

    nextSku() {
        return api.get('/admin/products/next-sku');
    },

    exportExcel(params) {
        return api.get('/admin/products/export', { params, responseType: 'blob' });
    },

    importExcel(formData) {
        return api.post('/admin/products/import', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
    },

    // Public endpoints for frontend
    getPublicAll(params) {
        return api.get('/products', { params });
    },
    
    getPublicById(id) {
        return api.get(`/products/${id}`);
    }
};

export const priceOfferListsApi = {
    getAll() {
        return api.get('/admin/price-offer-lists');
    },
    create(data) {
        return api.post('/admin/price-offer-lists', data);
    },
    show(id) {
        return api.get(`/admin/price-offer-lists/${id}`);
    },
    update(id, data) {
        return api.put(`/admin/price-offer-lists/${id}`, data);
    },
    remove(id) {
        return api.delete(`/admin/price-offer-lists/${id}`);
    },
};
