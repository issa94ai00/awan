import api from '@/api';

const API_BASE_URL = '/admin/rma';

/**
 * Returns (RMA) API client.
 *
 * Every method here maps to a route that actually exists in routes/api.php.
 * The previous version advertised a much larger surface — processRma, addNote,
 * getResolution/updateResolution, addRmaItem/removeRmaItem, getRmaTrends,
 * getReturnReasons, generateReturnLabel/downloadReturnLabel/trackReturn — none
 * of which had a backing route, so any caller would have hit a 404. They have
 * been removed rather than left as traps; add them back alongside real
 * endpoints if that functionality is built.
 */
export const rmaService = {
    // --- Requests ---
    getRmaRequests(params = {}) {
        return api.get(API_BASE_URL, { params });
    },

    getRmaRequest(id) {
        return api.get(`${API_BASE_URL}/${id}`);
    },

    createRmaRequest(data) {
        return api.post(API_BASE_URL, data);
    },

    updateRmaRequest(id, data) {
        return api.put(`${API_BASE_URL}/${id}`, data);
    },

    deleteRmaRequest(id) {
        return api.delete(`${API_BASE_URL}/${id}`);
    },

    // --- Workflow actions ---
    approveRma(id, data = {}) {
        return api.post(`${API_BASE_URL}/${id}/approve`, data);
    },

    rejectRma(id, data = {}) {
        return api.post(`${API_BASE_URL}/${id}/reject`, data);
    },

    /**
     * Books returned goods back into the warehouse and moves the request to
     * `received`. Quantities are absolute (not deltas) — the server works out
     * the difference from what was previously received.
     */
    receiveRma(id, data = {}) {
        return api.post(`${API_BASE_URL}/${id}/receive`, data);
    },

    completeRma(id, data = {}) {
        return api.post(`${API_BASE_URL}/${id}/complete`, data);
    },

    cancelRma(id, data = {}) {
        return api.post(`${API_BASE_URL}/${id}/cancel`, data);
    },

    // --- Items ---
    getRmaItems(rmaId, params = {}) {
        return api.get(`${API_BASE_URL}/${rmaId}/items`, { params });
    },

    // The route is /rma/items/{id}, keyed by the item id alone — the previous
    // signature built /rma/{rmaId}/items/{itemId}, which matches no route.
    updateRmaItem(itemId, data) {
        return api.put(`${API_BASE_URL}/items/${itemId}`, data);
    },

    // --- Reference data ---
    getCustomersWithOrders(params = {}) {
        return api.get(`${API_BASE_URL}/customers-with-orders`, { params });
    },

    // --- Activity & statistics ---
    getActivity(rmaId, params = {}) {
        return api.get(`${API_BASE_URL}/${rmaId}/activity`, { params });
    },

    getStatistics(params = {}) {
        return api.get(`${API_BASE_URL}/statistics`, { params });
    },

    // --- Export ---
    exportRmaRequests(params = {}) {
        return api.get(`${API_BASE_URL}/export`, {
            params,
            responseType: 'blob',
        });
    },
};

export default rmaService;
