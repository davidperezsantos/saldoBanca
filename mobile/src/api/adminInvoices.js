import { apiClient } from './client';

export async function listInvoicesAdmin(filters = {}) {
    const { data } = await apiClient.get('/invoices', { params: filters });
    return data.data;
}

export async function createInvoice(payload) {
    const { data } = await apiClient.post('/invoices/payment', payload);
    return data.data;
}

export async function cancelInvoice(id) {
    const { data } = await apiClient.put(`/invoices/${id}/cancel`);
    return data.data;
}

export async function refundInvoice(id) {
    const { data } = await apiClient.put(`/invoices/${id}/refund`);
    return data.data;
}
