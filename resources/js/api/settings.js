import api from './index';

const settingsApi = {
    get() {
        return api.get('/settings');
    },

    update(formData) {
        return api.post('/settings', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
    }
};

export default settingsApi;
