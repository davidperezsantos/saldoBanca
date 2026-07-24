import { apiClient, setToken, clearToken } from './client';

export async function login(username, password) {
    const { data } = await apiClient.post('/login', { username, password });
    await setToken(data.data.token);
    return data.data.user;
}

export async function logout() {
    await clearToken();
}
