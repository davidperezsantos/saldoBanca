import { apiClient } from './client';

export async function listCommissionSettlements(filters = {}) {
    const { data } = await apiClient.get('/commission-settlements', { params: filters });
    return data.data;
}

export async function getAvailableCommission(currency) {
    const { data } = await apiClient.get('/commission-settlements/available', { params: { currency } });
    return data.data;
}

export async function createCommissionSettlement(payload) {
    const { data } = await apiClient.post('/commission-settlements', payload);
    return data.data;
}

export async function getCommissionSettlement(id) {
    const { data } = await apiClient.get(`/commission-settlements/${id}`);
    return data.data;
}

export async function requestCommissionSettlementPin(id, username) {
    const { data } = await apiClient.post(`/commission-settlements/${id}/request-pin`, { username });
    return data.data;
}

export async function verifyCommissionSettlementPin(id, username, pin) {
    const { data } = await apiClient.post(`/commission-settlements/${id}/verify-pin`, { username, pin });
    return data.data;
}

export async function approveCommissionSettlement(id, performedBy) {
    const { data } = await apiClient.put(`/commission-settlements/${id}/approve`, { performedBy });
    return data.data;
}

export async function assignCommissionSettlementAccount(id, payload) {
    const { data } = await apiClient.put(`/commission-settlements/${id}/assign-account`, payload);
    return data.data;
}

export async function settleCommissionSettlement(id, payload) {
    const { data } = await apiClient.put(`/commission-settlements/${id}/settle`, payload);
    return data.data;
}

export async function closeCommissionSettlement(id, performedBy) {
    const { data } = await apiClient.put(`/commission-settlements/${id}/close`, { performedBy });
    return data.data;
}
