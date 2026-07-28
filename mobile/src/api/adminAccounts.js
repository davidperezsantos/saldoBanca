import { apiClient } from './client';

export async function listAccounts(filters = {}) {
    const { data } = await apiClient.get('/admin/accounts', { params: filters });
    return data.data;
}

export async function createAccount(payload) {
    const { data } = await apiClient.post('/admin/accounts', payload);
    return data.data;
}

export async function updateAccount(id, payload) {
    const { data } = await apiClient.put(`/admin/accounts/${id}`, payload);
    return data.data;
}

export async function changeAccountStatus(id, status) {
    const { data } = await apiClient.put(`/admin/accounts/${id}/status`, { status });
    return data.data;
}

export async function getAccountBalance(id) {
    const { data } = await apiClient.get(`/admin/accounts/${id}/balance`);
    return data.data;
}
