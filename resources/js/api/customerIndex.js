import axios from 'axios';

const customerApi = axios.create({
    baseURL: '/api/v1',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
});

// Request interceptor for customer requests
customerApi.interceptors.request.use(
    (config) => {
        const token = localStorage.getItem('customer_token');
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

// Response interceptor for customer requests
customerApi.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            localStorage.removeItem('customer_token');
            localStorage.removeItem('customer_info');
        }
        return Promise.reject(error);
    }
);

export default customerApi;
