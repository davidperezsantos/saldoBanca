import { apiClient } from './client';

export async function listCurrencies(activeOnly = false) {
    const { data } = await apiClient.get('/admin/currencies', {
        params: activeOnly ? { active: 1 } : {},
    });
    return data.data;
}

export async function createCurrency(payload) {
    const { data } = await apiClient.post('/admin/currencies', payload);
    return data.data;
}

export async function updateCurrency(id, payload) {
    await apiClient.put(`/admin/currencies/${id}`, payload);
}

export async function toggleCurrencyStatus(id, isActive) {
    const { data } = await apiClient.put(`/admin/currencies/${id}/status`, { isActive });
    return data.data;
}
