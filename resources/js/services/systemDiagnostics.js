import api from '@/api';

const BASE = '/system-diagnostics';

export default {
    get() {
        return api.get(BASE);
    },
    // A full run touches many tables; give it more room than the default timeout.
    run() {
        return api.post(`${BASE}/run`, {}, { timeout: 180000 });
    },
    exportCsv() {
        return api.get(`${BASE}/export`, { responseType: 'blob' });
    },
};
