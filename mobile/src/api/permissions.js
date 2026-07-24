import { getScopes } from './client';

export async function hasScope(scope) {
    const scopes = await getScopes();
    return scopes.includes(scope);
}
