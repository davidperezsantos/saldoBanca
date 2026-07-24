import { ref } from 'vue';
import { apiClient } from '../api/client';

const account = ref(null);
const loaded = ref(false);

export function useAccount() {
    return { account, loaded, loadAccount };
}

export async function loadAccount(force = false) {
    if (loaded.value && !force) {
        return account.value;
    }
    const { data } = await apiClient.get('/accounts');
    account.value = data.data[0] ?? null;
    loaded.value = true;
    return account.value;
}

export function resetAccount() {
    account.value = null;
    loaded.value = false;
}
