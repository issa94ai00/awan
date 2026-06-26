import axios from 'axios';

const API_BASE_URL = '/api/admin/rma';

export const rmaService = {
    // RMA Requests
    getRmaRequests(params = {}) {
        return axios.get(`${API_BASE_URL}`, { params });
    },

    getRmaRequest(id) {
        return axios.get(`${API_BASE_URL}/${id}`);
    },

    createRmaRequest(data) {
        return axios.post(`${API_BASE_URL}`, data);
    },

    updateRmaRequest(id, data) {
        return axios.put(`${API_BASE_URL}/${id}`, data);
    },

    deleteRmaRequest(id) {
        return axios.delete(`${API_BASE_URL}/${id}`);
    },

    // RMA Actions
    approveRma(id, data = {}) {
        return axios.post(`${API_BASE_URL}/${id}/approve`, data);
    },

    rejectRma(id, data = {}) {
        return axios.post(`${API_BASE_URL}/${id}/reject`, data);
    },

    processRma(id, data = {}) {
        return axios.post(`${API_BASE_URL}/${id}/process`, data);
    },

    completeRma(id, data = {}) {
        return axios.post(`${API_BASE_URL}/${id}/complete`, data);
    },

    cancelRma(id, data = {}) {
        return axios.post(`${API_BASE_URL}/${id}/cancel`, data);
    },

    // RMA Items
    getRmaItems(rmaId, params = {}) {
        return axios.get(`${API_BASE_URL}/${rmaId}/items`, { params });
    },

    addRmaItem(rmaId, data) {
        return axios.post(`${API_BASE_URL}/${rmaId}/items`, data);
    },

    updateRmaItem(rmaId, itemId, data) {
        return axios.put(`${API_BASE_URL}/${rmaId}/items/${itemId}`, data);
    },

    removeRmaItem(rmaId, itemId) {
        return axios.delete(`${API_BASE_URL}/${rmaId}/items/${itemId}`);
    },

    // RMA Resolution
    getResolution(rmaId) {
        return axios.get(`${API_BASE_URL}/${rmaId}/resolution`);
    },

    updateResolution(rmaId, data) {
        return axios.put(`${API_BASE_URL}/${rmaId}/resolution`, data);
    },

    // RMA Activity
    getActivity(rmaId, params = {}) {
        return axios.get(`${API_BASE_URL}/${rmaId}/activity`, { params });
    },

    addNote(rmaId, data) {
        return axios.post(`${API_BASE_URL}/${rmaId}/notes`, data);
    },

    // RMA Statistics
    getStatistics(params = {}) {
        return axios.get(`${API_BASE_URL}/statistics`, { params });
    },

    getRmaTrends(params = {}) {
        return axios.get(`${API_BASE_URL}/trends`, { params });
    },

    getReturnReasons(params = {}) {
        return axios.get(`${API_BASE_URL}/return-reasons`, { params });
    },

    // Export
    exportRmaRequests(params = {}, format = 'csv') {
        return axios.get(`${API_BASE_URL}/export`, {
            params: { ...params, format },
            responseType: 'blob'
        });
    },

    // RMA Labels/Shipping
    generateReturnLabel(rmaId) {
        return axios.post(`${API_BASE_URL}/${rmaId}/return-label`);
    },

    downloadReturnLabel(rmaId) {
        return axios.get(`${API_BASE_URL}/${rmaId}/return-label/download`, {
            responseType: 'blob'
        });
    },

    trackReturn(rmaId) {
        return axios.get(`${API_BASE_URL}/${rmaId}/tracking`);
    }
};

export default rmaService;
