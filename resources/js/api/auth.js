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

export function updateProfile(payload) {
    return api.put('/auth/profile', payload);
}

export function changePassword(payload) {
    return api.post('/auth/change-password', payload);
}

export function fetchSessions() {
    return api.get('/auth/sessions');
}

export function revokeSession(id) {
    return api.delete(`/auth/sessions/${id}`);
}

export function revokeOtherSessions() {
    return api.delete('/auth/sessions');
}

export default {
    login, fetchUser, logout, updateProfile, changePassword,
    fetchSessions, revokeSession, revokeOtherSessions,
};
