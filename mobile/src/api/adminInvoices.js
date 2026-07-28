import { apiClient } from './client';

export async function listInvoicesAdmin(filters = {}) {
    const { data } = await apiClient.get('/admin/invoices', { params: filters });
    return data.data;
}
