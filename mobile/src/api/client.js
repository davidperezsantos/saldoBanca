import axios from 'axios';
import { Preferences } from '@capacitor/preferences';
import { markSessionActive, handleSessionExpired } from '../composables/session';

const TOKEN_KEY = 'saldobanca_token';
const SCOPES_KEY = 'saldobanca_scopes';
const USER_KEY = 'saldobanca_user';
const ACTIVE_ROLE_KEY = 'saldobanca_active_role';
const AVAILABLE_ROLES_KEY = 'saldobanca_available_roles';
// A diferencia de las anteriores, esta sobrevive al logout a propósito: es la preferencia de
// "qué rol usar por defecto la próxima vez que este dispositivo loguee a este usuario" — solo la
// escribe una acción explícita (ver Perfil.vue), nunca el cambio de rol de sesión (RoleSwitcher).
const DEFAULT_ROLE_KEY = 'saldobanca_default_role';

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
    (response) => {
        if (response.config?.headers?.Authorization) {
            markSessionActive();
        }
        return response;
    },
    async (error) => {
        // /login tiene su propio manejo de 401 (contraseña incorrecta) en Login.vue,
        // no es una expiración de sesión.
        if (error.response?.status === 401 && error.config?.url !== '/login') {
            const expiredUser = await getUser();
            await clearToken();
            await clearScopes();
            await clearUser();
            await clearActiveRole();
            await clearAvailableRoles();
            handleSessionExpired(expiredUser?.username);
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

export async function getScopes() {
    const { value } = await Preferences.get({ key: SCOPES_KEY });
    return value ? JSON.parse(value) : [];
}

export async function setScopes(scopes) {
    await Preferences.set({ key: SCOPES_KEY, value: JSON.stringify(scopes ?? []) });
}

export async function clearScopes() {
    await Preferences.remove({ key: SCOPES_KEY });
}

export async function getUser() {
    const { value } = await Preferences.get({ key: USER_KEY });
    return value ? JSON.parse(value) : null;
}

export async function setUser(user) {
    await Preferences.set({ key: USER_KEY, value: JSON.stringify(user ?? null) });
}

export async function clearUser() {
    await Preferences.remove({ key: USER_KEY });
}

export async function getActiveRole() {
    const { value } = await Preferences.get({ key: ACTIVE_ROLE_KEY });
    return value ? JSON.parse(value) : null;
}

export async function setActiveRole(role) {
    await Preferences.set({ key: ACTIVE_ROLE_KEY, value: JSON.stringify(role ?? null) });
}

export async function clearActiveRole() {
    await Preferences.remove({ key: ACTIVE_ROLE_KEY });
}

export async function getAvailableRoles() {
    const { value } = await Preferences.get({ key: AVAILABLE_ROLES_KEY });
    return value ? JSON.parse(value) : [];
}

export async function setAvailableRoles(roles) {
    await Preferences.set({ key: AVAILABLE_ROLES_KEY, value: JSON.stringify(roles ?? []) });
}

export async function clearAvailableRoles() {
    await Preferences.remove({ key: AVAILABLE_ROLES_KEY });
}

export async function getDefaultRoleId() {
    const { value } = await Preferences.get({ key: DEFAULT_ROLE_KEY });
    return value || null;
}

export async function setDefaultRoleId(roleId) {
    await Preferences.set({ key: DEFAULT_ROLE_KEY, value: roleId });
}
