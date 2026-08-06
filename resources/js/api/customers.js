import { posApi } from './pos';

export const customersApi = {
    getAll(params) {
        return posApi.customers(params);
    },

    getById(id) {
        return posApi.customerShow(id);
    },

    store(data) {
        return posApi.customerStore(data);
    },

    update(id, data) {
        return posApi.customerUpdate(id, data);
    },

    delete(id) {
        return posApi.customerDelete(id);
    }
};
