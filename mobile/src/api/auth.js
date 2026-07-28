import {
    apiClient,
    setToken,
    clearToken,
    setScopes,
    clearScopes,
    setUser,
    clearUser,
    setActiveRole,
    clearActiveRole,
    setAvailableRoles,
    clearAvailableRoles,
} from './client';

/**
 * Si el usuario tiene más de un Role asignado y no se pasa roleId, el backend no devuelve token:
 * responde { requiresRoleSelection: true, roles, user } para que Login.vue decida (rol guardado
 * como default, o preguntarle a la persona) y reintente con ese roleId.
 */
export async function login(username, password, roleId = null) {
    const { data } = await apiClient.post('/login', { username, password, roleId });

    if (data.data.requiresRoleSelection) {
        return { requiresRoleSelection: true, roles: data.data.roles, user: data.data.user };
    }

    await setToken(data.data.token);
    await setScopes(data.data.scopes);
    await setUser(data.data.user);
    await setActiveRole(data.data.activeRole);
    await setAvailableRoles(data.data.availableRoles);
    return { requiresRoleSelection: false, user: data.data.user };
}

/**
 * Cambia el rol activo de la sesión actual (botón flotante) — reemite el token con los scopes del
 * rol elegido, sin pedir contraseña. No toca el default persistido (eso lo hace Perfil.vue).
 */
export async function switchActiveRole(roleId) {
    const { data } = await apiClient.put('/me/active-role', { roleId });
    await setToken(data.data.token);
    await setScopes(data.data.scopes);
    await setActiveRole(data.data.activeRole);
    await setAvailableRoles(data.data.availableRoles);
    return data.data;
}

export async function logout() {
    await clearToken();
    await clearScopes();
    await clearUser();
    await clearActiveRole();
    await clearAvailableRoles();
}
