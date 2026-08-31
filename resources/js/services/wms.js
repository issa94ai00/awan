import api from '@/api';
const API_BASE_URL = '/wms';
// Packing and cycle counts are only registered under the admin-gated
// `/admin/wms` prefix (routes/api.php), not the plain `/wms` prefix the rest
// of this service uses — hitting `${API_BASE_URL}/packing-lists` 404s.
const ADMIN_BASE_URL = '/admin/wms';

export const wmsService = {
    // Stats
    getWmsStats() {
        return api.get(`${API_BASE_URL}/stats`);
    },

    // Warehouses
    getWarehouses(params = {}) {
        return api.get(`${API_BASE_URL}/warehouses`, { params });
    },

    getWarehouse(id) {
        return api.get(`${API_BASE_URL}/warehouses/${id}`);
    },

    createWarehouse(data) {
        return api.post(`${API_BASE_URL}/warehouses`, data);
    },

    updateWarehouse(id, data) {
        return api.put(`${API_BASE_URL}/warehouses/${id}`, data);
    },

    deleteWarehouse(id) {
        return api.delete(`${API_BASE_URL}/warehouses/${id}`);
    },

    getManagers() {
        return api.get(`${API_BASE_URL}/managers`);
    },

    // Bins
    getBins(params = {}) {
        return api.get(`${API_BASE_URL}/bins`, { params });
    },

    getBin(id) {
        return api.get(`${API_BASE_URL}/bins/${id}`);
    },

    createBin(data) {
        return api.post(`${API_BASE_URL}/bins`, data);
    },

    updateBin(id, data) {
        return api.put(`${API_BASE_URL}/bins/${id}`, data);
    },

    deleteBin(id) {
        return api.delete(`${API_BASE_URL}/bins/${id}`);
    },

    // Picking Lists
    getPickingLists(params = {}) {
        return api.get(`${API_BASE_URL}/picking-lists`, { params });
    },

    getPickingList(id) {
        return api.get(`${API_BASE_URL}/picking-lists/${id}`);
    },

    createPickingList(data) {
        return api.post(`${API_BASE_URL}/picking-lists`, data);
    },

    updatePickingList(id, data) {
        return api.put(`${API_BASE_URL}/picking-lists/${id}`, data);
    },

    deletePickingList(id) {
        return api.delete(`${API_BASE_URL}/picking-lists/${id}`);
    },

    startPicking(id) {
        return api.post(`${API_BASE_URL}/picking-lists/${id}/start`);
    },

    completePicking(id) {
        return api.post(`${API_BASE_URL}/picking-lists/${id}/complete`);
    },

    cancelPicking(id) {
        return api.post(`${API_BASE_URL}/picking-lists/${id}/cancel`);
    },

    /**
     * Records what was actually taken off the shelf for one line. Returns the
     * whole refreshed list, so the caller never has to re-fetch to learn the
     * new progress.
     */
    pickItem(itemId, payload) {
        return api.post(`${API_BASE_URL}/picking-items/${itemId}`, payload);
    },

    // Packing Lists
    getPackingLists(params = {}) {
        return api.get(`${ADMIN_BASE_URL}/packing-lists`, { params });
    },

    getPackingList(id) {
        return api.get(`${ADMIN_BASE_URL}/packing-lists/${id}`);
    },

    createPackingList(data) {
        return api.post(`${ADMIN_BASE_URL}/packing-lists`, data);
    },

    startPacking(id) {
        return api.post(`${ADMIN_BASE_URL}/packing-lists/${id}/start`);
    },

    completePacking(id) {
        return api.post(`${ADMIN_BASE_URL}/packing-lists/${id}/complete`);
    },

    cancelPacking(id) {
        return api.post(`${ADMIN_BASE_URL}/packing-lists/${id}/cancel`);
    },

    // Cycle Counts
    getCycleCounts(params = {}) {
        return api.get(`${ADMIN_BASE_URL}/cycle-counts`, { params });
    },

    getCycleCount(id) {
        return api.get(`${ADMIN_BASE_URL}/cycle-counts/${id}`);
    },

    createCycleCount(data) {
        return api.post(`${ADMIN_BASE_URL}/cycle-counts`, data);
    },

    startCycleCount(id) {
        return api.post(`${ADMIN_BASE_URL}/cycle-counts/${id}/start`);
    },

    completeCycleCount(id) {
        return api.post(`${ADMIN_BASE_URL}/cycle-counts/${id}/complete`);
    },

    cancelCycleCount(id) {
        return api.post(`${ADMIN_BASE_URL}/cycle-counts/${id}/cancel`);
    },

    // Performance
    getPerformanceMetrics(params = {}) {
        return api.get(`${API_BASE_URL}/performance`, { params });
    },

    getPerformanceTrends(params = {}) {
        return api.get(`${API_BASE_URL}/performance/trends`, { params });
    },

    // Dashboard
    getDashboardStats() {
        return api.get(`${API_BASE_URL}/dashboard`);
    }
};

export default wmsService;
