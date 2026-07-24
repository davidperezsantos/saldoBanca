import { apiClient, setToken, clearToken, setScopes, clearScopes, setUser, clearUser } from './client';

export async function login(username, password) {
    const { data } = await apiClient.post('/login', { username, password });
    await setToken(data.data.token);
    await setScopes(data.data.scopes);
    await setUser(data.data.user);
    return data.data.user;
}

export async function logout() {
    await clearToken();
    await clearScopes();
    await clearUser();
}
