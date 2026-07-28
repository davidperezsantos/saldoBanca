import { apiClient } from './client';

export async function getDashboardStats(period = '7d') {
    const { data } = await apiClient.get('/admin/dashboard-stats', { params: { period } });
    return data.data;
}
