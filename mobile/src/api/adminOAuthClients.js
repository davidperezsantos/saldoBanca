import { apiClient } from './client';

export async function listOAuthClients() {
    const { data } = await apiClient.get('/admin/oauth-clients');
    return data.data;
}

export async function revealOAuthClientSecret(identifier) {
    const { data } = await apiClient.get(`/admin/oauth-clients/${identifier}/secret`);
    return data.data.secret;
}

export async function createOAuthClient(payload) {
    const { data } = await apiClient.post('/admin/oauth-clients', payload);
    return data.data;
}

export async function updateOAuthClient(identifier, payload) {
    await apiClient.put(`/admin/oauth-clients/${identifier}`, payload);
}

export async function deleteOAuthClient(identifier) {
    await apiClient.delete(`/admin/oauth-clients/${identifier}`);
}

export async function toggleOAuthClientStatus(identifier, active) {
    const { data } = await apiClient.put(`/admin/oauth-clients/${identifier}/status`, { active });
    return data.data;
}
