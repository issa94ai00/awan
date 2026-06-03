import api from './index';

export const ticketsApi = {
    getAll(params = {}) {
        return api.get('/tickets', { params });
    },
    getById(id) {
        return api.get(`/tickets/${id}`);
    },
    create(data) {
        return api.post('/tickets', data);
    },
    update(id, data) {
        return api.put(`/tickets/${id}`, data);
    },
    delete(id) {
        return api.delete(`/tickets/${id}`);
    }
};
