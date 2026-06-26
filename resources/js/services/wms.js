import axios from 'axios';

const API_BASE_URL = '/api/admin/wms';

export const wmsService = {
    // Warehouses
    getWarehouses(params = {}) {
        return axios.get(`${API_BASE_URL}/warehouses`, { params });
    },

    getWarehouse(id) {
        return axios.get(`${API_BASE_URL}/warehouses/${id}`);
    },

    createWarehouse(data) {
        return axios.post(`${API_BASE_URL}/warehouses`, data);
    },

    updateWarehouse(id, data) {
        return axios.put(`${API_BASE_URL}/warehouses/${id}`, data);
    },

    deleteWarehouse(id) {
        return axios.delete(`${API_BASE_URL}/warehouses/${id}`);
    },

    // Bins
    getBins(params = {}) {
        return axios.get(`${API_BASE_URL}/bins`, { params });
    },

    getBin(id) {
        return axios.get(`${API_BASE_URL}/bins/${id}`);
    },

    createBin(data) {
        return axios.post(`${API_BASE_URL}/bins`, data);
    },

    updateBin(id, data) {
        return axios.put(`${API_BASE_URL}/bins/${id}`, data);
    },

    deleteBin(id) {
        return axios.delete(`${API_BASE_URL}/bins/${id}`);
    },

    // Picking Lists
    getPickingLists(params = {}) {
        return axios.get(`${API_BASE_URL}/picking-lists`, { params });
    },

    getPickingList(id) {
        return axios.get(`${API_BASE_URL}/picking-lists/${id}`);
    },

    createPickingList(data) {
        return axios.post(`${API_BASE_URL}/picking-lists`, data);
    },

    updatePickingList(id, data) {
        return axios.put(`${API_BASE_URL}/picking-lists/${id}`, data);
    },

    deletePickingList(id) {
        return axios.delete(`${API_BASE_URL}/picking-lists/${id}`);
    },

    startPicking(id) {
        return axios.post(`${API_BASE_URL}/picking-lists/${id}/start`);
    },

    completePicking(id) {
        return axios.post(`${API_BASE_URL}/picking-lists/${id}/complete`);
    },

    cancelPicking(id) {
        return axios.post(`${API_BASE_URL}/picking-lists/${id}/cancel`);
    },

    // Packing Lists
    getPackingLists(params = {}) {
        return axios.get(`${API_BASE_URL}/packing-lists`, { params });
    },

    getPackingList(id) {
        return axios.get(`${API_BASE_URL}/packing-lists/${id}`);
    },

    createPackingList(data) {
        return axios.post(`${API_BASE_URL}/packing-lists`, data);
    },

    updatePackingList(id, data) {
        return axios.put(`${API_BASE_URL}/packing-lists/${id}`, data);
    },

    deletePackingList(id) {
        return axios.delete(`${API_BASE_URL}/packing-lists/${id}`);
    },

    startPacking(id) {
        return axios.post(`${API_BASE_URL}/packing-lists/${id}/start`);
    },

    completePacking(id) {
        return axios.post(`${API_BASE_URL}/packing-lists/${id}/complete`);
    },

    cancelPacking(id) {
        return axios.post(`${API_BASE_URL}/packing-lists/${id}/cancel`);
    },

    // Cycle Counts
    getCycleCounts(params = {}) {
        return axios.get(`${API_BASE_URL}/cycle-counts`, { params });
    },

    getCycleCount(id) {
        return axios.get(`${API_BASE_URL}/cycle-counts/${id}`);
    },

    createCycleCount(data) {
        return axios.post(`${API_BASE_URL}/cycle-counts`, data);
    },

    updateCycleCount(id, data) {
        return axios.put(`${API_BASE_URL}/cycle-counts/${id}`, data);
    },

    deleteCycleCount(id) {
        return axios.delete(`${API_BASE_URL}/cycle-counts/${id}`);
    },

    startCycleCount(id) {
        return axios.post(`${API_BASE_URL}/cycle-counts/${id}/start`);
    },

    completeCycleCount(id) {
        return axios.post(`${API_BASE_URL}/cycle-counts/${id}/complete`);
    },

    cancelCycleCount(id) {
        return axios.post(`${API_BASE_URL}/cycle-counts/${id}/cancel`);
    },

    // Performance
    getPerformanceMetrics(params = {}) {
        return axios.get(`${API_BASE_URL}/performance`, { params });
    },

    getPerformanceTrends(params = {}) {
        return axios.get(`${API_BASE_URL}/performance/trends`, { params });
    },

    // Dashboard
    getDashboardStats() {
        return axios.get(`${API_BASE_URL}/dashboard`);
    }
};

export default wmsService;
