import api from './index';

export const salesOrdersApi = {
    getAll(params) {
        return api.get('/sales-orders', { params });
    },

    getById(id) {
        return api.get(`/sales-orders/${id}`);
    },

    create(data) {
        return api.post('/sales-orders', data);
    },

    update(id, data) {
        return api.put(`/sales-orders/${id}`, data);
    },

    delete(id) {
        return api.delete(`/sales-orders/${id}`);
    },

    convertToInvoice(id) {
        return api.post(`/sales-orders/${id}/convert-to-invoice`);
    },

    /** The whole detail screen in one call: order, documents, diagnosis. */
    detail(id) {
        return api.get(`/sales-orders/${id}/detail`);
    },

    /** Where each line's goods come from, and what each warehouse could supply. */
    sourcing(id) {
        return api.get(`/sales-orders/${id}/sourcing`);
    },

    /** Saves the sourcing plan; the stock hold moves with it. */
    saveSourcing(id, payload) {
        return api.put(`/sales-orders/${id}/sourcing`, payload);
    },

    /** Per-warehouse coverage of the order, for the routing panel. */
    routing(id) {
        return api.get(`/sales-orders/${id}/routing`);
    },

    /**
     * Sets which warehouses the order may draw on. More than one is allowed:
     * sourcing then distributes each line across exactly these.
     */
    saveRoutings(id, warehouseIds) {
        return api.put(`/sales-orders/${id}/routings`, { warehouse_ids: warehouseIds });
    },

    /** Confirms: reserves the stock, raises the invoice, posts the entry. */
    confirm(id) {
        return api.post(`/sales-orders/${id}/confirm`);
    },

    /** Moves the order to an execution stage, with all its side effects. */
    transition(id, payload) {
        return api.post(`/sales-orders/${id}/transition`, payload);
    },

    /** Changes ship/pickup/delivery, re-routing and re-pricing accordingly. */
    changeFulfillmentType(id, payload) {
        return api.post(`/sales-orders/${id}/fulfillment-type`, payload);
    },
};
