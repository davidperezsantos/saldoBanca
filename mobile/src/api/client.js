import axios from 'axios';
import { Preferences } from '@capacitor/preferences';

const TOKEN_KEY = 'saldobanca_token';

export const apiClient = axios.create({
    baseURL: import.meta.env.VITE_API_URL,
});

apiClient.interceptors.request.use(async (config) => {
    const token = await getToken();
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

apiClient.interceptors.response.use(
    (response) => response,
    async (error) => {
        if (error.response?.status === 401) {
            await clearToken();
        }
        return Promise.reject(error);
    }
);

export async function getToken() {
    const { value } = await Preferences.get({ key: TOKEN_KEY });
    return value;
}

export async function setToken(token) {
    await Preferences.set({ key: TOKEN_KEY, value: token });
}

export async function clearToken() {
    await Preferences.remove({ key: TOKEN_KEY });
}
