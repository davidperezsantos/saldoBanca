import { apiClient } from './client';

export async function listRoles() {
    const { data } = await apiClient.get('/admin/roles');
    return data.data;
}

export async function createRole(payload) {
    const { data } = await apiClient.post('/admin/roles', payload);
    return data.data;
}

export async function updateRole(id, payload) {
    const { data } = await apiClient.put(`/admin/roles/${id}`, payload);
    return data.data;
}

export async function deleteRole(id) {
    await apiClient.delete(`/admin/roles/${id}`);
}
