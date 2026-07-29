import { apiClient } from './client';

export async function listAccounts(filters = {}) {
    const { data } = await apiClient.get('/accounts', { params: filters });
    return data.data;
}

export async function createAccount(payload) {
    const { data } = await apiClient.post('/accounts', payload);
    return data.data;
}

export async function updateAccount(id, payload) {
    const { data } = await apiClient.put(`/accounts/${id}`, payload);
    return data.data;
}

export async function changeAccountStatus(id, status) {
    const { data } = await apiClient.put(`/accounts/${id}/status`, { status });
    return data.data;
}

export async function getAccountBalance(id) {
    const { data } = await apiClient.get(`/accounts/${id}/balance`);
    return data.data;
}
