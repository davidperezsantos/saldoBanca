import { apiClient } from './client';

export async function listRechargesAdmin(filters = {}) {
    const { data } = await apiClient.get('/recharges', { params: filters });
    return data.data;
}

export async function createRecharge(payload) {
    const { data } = await apiClient.post('/recharges', payload);
    return data.data;
}

export async function completeRecharge(id) {
    const { data } = await apiClient.put(`/recharges/${id}/complete`);
    return data.data;
}

export async function cancelRecharge(id) {
    const { data } = await apiClient.put(`/recharges/${id}/cancel`);
    return data.data;
}

export async function failRecharge(id, reason) {
    const { data } = await apiClient.put(`/recharges/${id}/fail`, { reason });
    return data.data;
}
