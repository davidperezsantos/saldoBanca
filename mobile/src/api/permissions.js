import { getScopes } from './client';

export async function hasScope(scope) {
    const scopes = await getScopes();
    return scopes.includes(scope);
}

export async function hasAnyScope(candidates) {
    const scopes = await getScopes();
    return candidates.some((scope) => scopes.includes(scope));
}
