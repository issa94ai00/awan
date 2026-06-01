import api from './index';

export function login(credentials) {
    return api.post('/auth/login', credentials);
}

export function fetchUser() {
    return api.get('/auth/user');
}

export function logout() {
    return api.post('/auth/logout');
}

export default { login, fetchUser, logout };
