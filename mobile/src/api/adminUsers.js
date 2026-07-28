import { apiClient } from './client';

export async function listStaffUsers() {
    const { data } = await apiClient.get('/admin/users');
    return data.data;
}

export async function createStaffUser(payload) {
    const { data } = await apiClient.post('/admin/users', payload);
    return data.data;
}

export async function updateStaffUser(id, payload) {
    const { data } = await apiClient.put(`/admin/users/${id}`, payload);
    return data.data;
}

export async function deleteStaffUser(id) {
    await apiClient.delete(`/admin/users/${id}`);
}

export async function toggleStaffUserStatus(id) {
    const { data } = await apiClient.put(`/admin/users/${id}/status`);
    return data.data;
}
