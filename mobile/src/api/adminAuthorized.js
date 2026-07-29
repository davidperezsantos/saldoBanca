import { apiClient } from './client';

export async function listAuthorizedAdmin(filters = {}) {
    const { data } = await apiClient.get('/authorized', { params: filters });
    return data.data;
}

export async function createAuthorizedAdmin(payload) {
    const { data } = await apiClient.post('/authorized', payload);
    return data.data;
}

export async function updateAuthorizedAdmin(id, payload) {
    const { data } = await apiClient.put(`/authorized/${id}`, payload);
    return data.data;
}

export async function deleteAuthorizedAdmin(id) {
    await apiClient.delete(`/authorized/${id}`);
}

export async function changeAuthorizedAdminStatus(id, status) {
    const { data } = await apiClient.put(`/authorized/${id}/status`, { status });
    return data.data;
}
