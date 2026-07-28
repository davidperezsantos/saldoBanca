import { apiClient } from './client';

export async function listExchangeProviders() {
    const { data } = await apiClient.get('/admin/exchange-providers');
    return data.data;
}

export async function createExchangeProvider(payload) {
    const { data } = await apiClient.post('/admin/exchange-providers', payload);
    return data.data;
}

export async function updateExchangeProvider(id, payload) {
    await apiClient.put(`/admin/exchange-providers/${id}`, payload);
}

export async function toggleExchangeProviderStatus(id, status) {
    await apiClient.put(`/admin/exchange-providers/${id}/status`, { status });
}

export async function listExchangeRates(providerId = null) {
    const { data } = await apiClient.get('/admin/exchange-rates', {
        params: providerId ? { providerId } : {},
    });
    return data.data;
}

export async function fetchExchangeRates() {
    const { data } = await apiClient.post('/admin/exchange-rates/fetch');
    return data;
}

export async function createManualExchangeRate(payload) {
    const { data } = await apiClient.post('/admin/exchange-rates/manual', payload);
    return data;
}
