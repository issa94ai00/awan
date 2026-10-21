import api from './index';

export const creditNotesApi = {
    get(id) {
        return api.get(`/admin/credit-notes/${id}`);
    },
};
