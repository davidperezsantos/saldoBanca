import { apiClient } from './client';

export async function listHistoryAdmin(filters = {}) {
    const { data } = await apiClient.get('/admin/history', { params: filters });
    return data.data;
}
