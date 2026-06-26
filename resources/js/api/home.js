import api from './index';

export const homeApi = {
    getHomeData() {
        return api.get('/home');
    }
};
