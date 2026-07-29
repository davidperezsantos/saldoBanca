import { apiClient } from './client';

export async function listTransfersAdmin(filters = {}) {
    const { data } = await apiClient.get('/transfers', { params: filters });
    return data.data;
}

export async function createTransfer(payload) {
    const { data } = await apiClient.post('/transfers', payload);
    return data.data;
}

export async function processTransfer(id) {
    const { data } = await apiClient.put(`/transfers/${id}/process`);
    return data.data;
}

export async function cancelTransfer(id) {
    const { data } = await apiClient.put(`/transfers/${id}/cancel`);
    return data.data;
}
