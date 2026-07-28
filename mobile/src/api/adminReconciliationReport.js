import { apiClient } from './client';

export async function getReconciliationReport(filters) {
    const { data } = await apiClient.get('/admin/reports/reconciliations', { params: filters });
    return data.data;
}
