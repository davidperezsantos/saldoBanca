import { ref } from 'vue';
import { listCurrencies } from '../api/adminCurrencies';

const activeCurrencies = ref([]);
let loaded = false;
let loadingPromise = null;

export async function loadActiveCurrencies(force = false) {
    if (loaded && !force) {
        return activeCurrencies.value;
    }
    if (loadingPromise) {
        return loadingPromise;
    }
    loadingPromise = listCurrencies(true)
        .then((currencies) => {
            activeCurrencies.value = currencies;
            loaded = true;
            return currencies;
        })
        .finally(() => {
            loadingPromise = null;
        });
    return loadingPromise;
}

export function useActiveCurrencies() {
    return { activeCurrencies, loadActiveCurrencies };
}
