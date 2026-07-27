import { ref } from 'vue';
import { getUser } from '../api/client';

const user = ref(null);
const loaded = ref(false);

export function useUser() {
    return { user, loadUser };
}

export async function loadUser(force = false) {
    if (loaded.value && !force) {
        return user.value;
    }
    user.value = await getUser();
    loaded.value = true;
    return user.value;
}

export function resetUser() {
    user.value = null;
    loaded.value = false;
}

export function initials(name) {
    if (!name) return '?';
    return name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');
}
