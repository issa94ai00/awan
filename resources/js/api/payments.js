import api from './index';

export const paymentsApi = {
    getAll(params) {
        return api.get('/payments', { params });
    },

    getById(id) {
        return api.get(`/payments/${id}`);
    },

    /** Totals actually collected per currency — see PaymentController::currencySummary. */
    currencySummary() {
        return api.get('/payments/currency-summary');
    },

    create(data) {
        return api.post('/payments', data);
    },

    update(id, data) {
        return api.put(`/payments/${id}`, data);
    },

    delete(id) {
        return api.delete(`/payments/${id}`);
    }
};
