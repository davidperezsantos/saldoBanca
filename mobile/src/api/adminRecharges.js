import { apiClient } from './client';

export async function listRechargesAdmin(filters = {}) {
    const { data } = await apiClient.get('/admin/recharges', { params: filters });
    return data.data;
}
