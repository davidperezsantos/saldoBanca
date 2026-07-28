import { apiClient } from './client';

export async function previewReconciliation(params) {
    const { data } = await apiClient.get('/admin/reconciliations/preview', { params });
    return data.data;
}

export async function createReconciliation(payload) {
    const { data } = await apiClient.post('/admin/reconciliations', payload);
    return data.data;
}

export async function listReconciliationsAdmin(filters = {}) {
    const { data } = await apiClient.get('/admin/reconciliations', { params: filters });
    return data.data;
}

export async function getReconciliation(id) {
    const { data } = await apiClient.get(`/admin/reconciliations/${id}`);
    return data.data;
}

export async function sendReconciliation(id) {
    const { data } = await apiClient.post(`/admin/reconciliations/${id}/send`);
    return data.data;
}

export async function approveReconciliation(id) {
    const { data } = await apiClient.put(`/admin/reconciliations/${id}/approve`);
    return data.data;
}

export async function rejectReconciliation(id, reason) {
    const { data } = await apiClient.put(`/admin/reconciliations/${id}/reject`, { reason });
    return data.data;
}

export async function settleReconciliation(id, payload) {
    const { data } = await apiClient.put(`/admin/reconciliations/${id}/settle`, payload);
    return data.data;
}
