import { apiClient } from './client';

export async function listHistoryAdmin(filters = {}) {
    const { data } = await apiClient.get('/history', { params: filters });
    return data.data;
}
