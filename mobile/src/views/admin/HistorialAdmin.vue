<script setup>
import { ref, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { listAccounts } from '../../api/adminAccounts';
import { listHistoryAdmin } from '../../api/adminHistory';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();

// Mismo patrón que AutorizadosAdmin.vue: la cuenta llega elegida desde Cuentas.vue (query
// accountId/accountLabel) o se elige acá con el buscador — accountId es obligatorio en el
// backend para consultar historial.
const selectedAccount = ref(null);
const cameFromAccountsList = ref(false);

const accountSearch = ref('');
const accountResults = ref([]);
const searchingAccounts = ref(false);
const accountSearchDone = ref(false);
const accountSearchError = ref('');

const loading = ref(false);
const error = ref('');
const movements = ref([]);

const TYPE_LABELS = computed(() => ({
    credit: t('admin.history.credit'),
    debit: t('admin.history.debit'),
}));

async function load() {
    if (!selectedAccount.value) return;
    loading.value = true;
    error.value = '';
    try {
        movements.value = await listHistoryAdmin({ accountId: selectedAccount.value.id, limit: 200 });
    } catch (e) {
        error.value = e.response?.data?.message || t('admin.history.error');
    } finally {
        loading.value = false;
    }
}

async function searchAccounts() {
    accountSearchError.value = '';
    searchingAccounts.value = true;
    accountSearchDone.value = false;
    try {
        accountResults.value = await listAccounts(accountSearch.value ? { search: accountSearch.value } : {});
    } catch (e) {
        accountSearchError.value = e.response?.data?.message || t('admin.accounts.error');
    } finally {
        searchingAccounts.value = false;
        accountSearchDone.value = true;
    }
}

function selectAccount(acc) {
    selectedAccount.value = { id: acc.id, label: acc.businessName };
    accountResults.value = [];
    accountSearch.value = '';
    accountSearchDone.value = false;
    router.replace({ path: '/historial-admin', query: { accountId: acc.id, accountLabel: acc.businessName } });
    load();
}

function changeAccount() {
    if (cameFromAccountsList.value) {
        router.push({ path: '/cuentas' });
        return;
    }
    selectedAccount.value = null;
    movements.value = [];
    router.replace({ path: '/historial-admin' });
}

onMounted(async () => {
    if (route.query.accountId) {
        cameFromAccountsList.value = true;
        selectedAccount.value = {
            id: route.query.accountId,
            label: route.query.accountLabel || route.query.accountId,
        };
        await load();
    }
});
</script>

<template>
  <div class="card">
    <h1>{{ t('admin.history.title') }}</h1>
    <p class="hint">{{ t('admin.history.hint') }}</p>

    <div v-if="!selectedAccount" class="account-picker">
      <form class="search-row" @submit.prevent="searchAccounts">
        <input v-model="accountSearch" type="text" :placeholder="t('admin.authorizedAdmin.searchAccountPlaceholder')" />
        <button type="submit">{{ t('admin.accounts.searchBtn') }}</button>
      </form>
      <p v-if="searchingAccounts">{{ t('common.loading') }}</p>
      <p v-else-if="accountSearchError" class="error">{{ accountSearchError }}</p>
      <p v-else-if="accountSearchDone && !accountResults.length" class="empty">{{ t('admin.authorizedAdmin.noAccountsFound') }}</p>
      <ul v-else-if="accountResults.length" class="account-results">
        <li v-for="acc in accountResults" :key="acc.id">
          <button type="button" class="account-result" @click="selectAccount(acc)">
            <span>{{ acc.businessName }}</span>
            <span class="account-result-sub">{{ acc.accountNumber }}</span>
          </button>
        </li>
      </ul>
    </div>

    <template v-else>
      <div class="selected-account">
        <p class="selected-account-name">{{ selectedAccount.label }}</p>
        <button type="button" class="link-btn" @click="changeAccount">{{ t('admin.authorizedAdmin.changeAccount') }}</button>
      </div>

      <p v-if="loading">{{ t('common.loading') }}</p>
      <p v-else-if="error" class="error">{{ error }}</p>
      <p v-else-if="!movements.length" class="empty">{{ t('admin.history.empty') }}</p>
      <ul v-else class="list">
        <li v-for="m in movements" :key="m.id" class="item">
          <div class="item-row">
            <p class="item-title">{{ m.description || (TYPE_LABELS[m.movementType] ?? m.movementType) }}</p>
            <span class="amount" :class="{ up: m.amount >= 0, down: m.amount < 0 }">{{ m.amount }} {{ m.currency }}</span>
          </div>
          <p class="item-phone">{{ m.balanceBefore }} → {{ m.balanceAfter }} {{ m.currency }}</p>
          <p class="item-phone">{{ m.performedBy }} · {{ m.createdAt }}</p>
        </li>
      </ul>
    </template>
  </div>
</template>

<style scoped>
.card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
h1 {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--primary-dark);
}
.hint {
    margin: 0 0 0.5rem;
    font-size: 0.82rem;
    color: #777;
}
.search-row {
    display: flex;
    gap: 0.5rem;
}
.search-row input {
    flex: 1;
    padding: 0.5rem 0.6rem;
    border: 1px solid #d0d3d8;
    border-radius: 8px;
    font-size: 0.9rem;
}
.search-row button {
    padding: 0.5rem 0.9rem;
    border: 1px solid #d0d3d8;
    border-radius: 8px;
    background: white;
    color: #333;
    font-weight: 600;
    cursor: pointer;
}
.account-results {
    list-style: none;
    margin: 0.6rem 0 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}
.account-result {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.65rem 0.75rem;
    background: #f7f9fa;
    border: none;
    border-radius: 8px;
    color: #333;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    text-align: left;
}
.account-result-sub {
    font-size: 0.75rem;
    font-weight: 500;
    color: #888;
}
.selected-account {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.7rem 0.9rem;
    background: #eefaf5;
    border-radius: 8px;
    margin-bottom: 0.3rem;
}
.selected-account-name {
    margin: 0;
    font-weight: 700;
    color: var(--primary-dark);
}
.link-btn {
    background: none;
    border: none;
    color: var(--primary-dark);
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    padding: 0;
    white-space: nowrap;
}
.empty {
    color: #888;
    font-size: 0.9rem;
}
.error {
    color: #c0392b;
    font-size: 0.85rem;
}
.list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.7rem;
}
.item {
    padding: 0.6rem 0;
    border-bottom: 1px solid #f0f1f3;
}
.item:last-child {
    border-bottom: none;
}
.item-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.5rem;
}
.item-title {
    flex: 1;
    margin: 0;
    min-width: 0;
    font-weight: 600;
}
.item-phone {
    margin: 0.2rem 0 0;
    font-size: 0.78rem;
    color: #888;
}
.amount {
    flex-shrink: 0;
    font-weight: 700;
    font-size: 0.9rem;
}
.amount.up {
    color: #0f9d58;
}
.amount.down {
    color: #c0392b;
}
</style>
