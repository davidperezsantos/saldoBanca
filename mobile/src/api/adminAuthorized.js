import { apiClient } from './client';

export async function listAuthorizedAdmin(filters = {}) {
    const { data } = await apiClient.get('/admin/authorized', { params: filters });
    return data.data;
}

export async function createAuthorizedAdmin(payload) {
    const { data } = await apiClient.post('/admin/authorized', payload);
    return data.data;
}

export async function updateAuthorizedAdmin(id, payload) {
    const { data } = await apiClient.put(`/admin/authorized/${id}`, payload);
    return data.data;
}

export async function deleteAuthorizedAdmin(id) {
    await apiClient.delete(`/admin/authorized/${id}`);
}

export async function changeAuthorizedAdminStatus(id, status) {
    const { data } = await apiClient.put(`/admin/authorized/${id}/status`, { status });
    return data.data;
}
