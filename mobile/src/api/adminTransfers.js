import { apiClient } from './client';

export async function listTransfersAdmin(filters = {}) {
    const { data } = await apiClient.get('/admin/transfers', { params: filters });
    return data.data;
}
