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
    
    delete(id) {
        return api.delete(`/admin/products/${id}`);
    },

    // Public endpoints for frontend
    getPublicAll(params) {
        return api.get('/products', { params });
    },
    
    getPublicById(id) {
        return api.get(`/products/${id}`);
    }
};
