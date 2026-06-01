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
    }
};
