import api from './index';

export const adminSearchApi = {
    search(q) {
        return api.get('/admin/search', { params: { q } });
    }
};

export default adminSearchApi;
