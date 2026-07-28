<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { listInvoicesAdmin } from '../../api/adminInvoices';
import { listAccounts } from '../../api/adminAccounts';
import { currencySymbol } from '../../utils/currency';

const { t } = useI18n();

const loading = ref(true);
const error = ref('');
const invoices = ref([]);
const status = ref('');

const accountSearch = ref('');
const accountResults = ref([]);
const searchingAccounts = ref(false);
const selectedAccount = ref(null);

const STATUS_LABELS = computed(() => ({
    pending: t('invoices.statusPending'),
    paid: t('invoices.statusPaid'),
    cancelled: t('invoices.statusCancelled'),
    refunded: t('invoices.statusRefunded'),
}));

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const filters = {};
        if (status.value) filters.status = status.value;
        if (selectedAccount.value) filters.accountId = selectedAccount.value.id;
        invoices.value = await listInvoicesAdmin(filters);
    } catch (e) {
        error.value = e.response?.data?.message || t('invoices.loadError');
    } finally {
        loading.value = false;
    }
}

async function searchAccounts() {
    if (!accountSearch.value) {
        accountResults.value = [];
        return;
    }
    searchingAccounts.value = true;
    try {
        accountResults.value = await listAccounts({ search: accountSearch.value });
    } finally {
        searchingAccounts.value = false;
    }
}

function selectAccount(acc) {
    selectedAccount.value = { id: acc.id, label: acc.businessName };
    accountResults.value = [];
    accountSearch.value = '';
    load();
}

function clearAccount() {
    selectedAccount.value = null;
    load();
}

onMounted(load);
</script>

<template>
  <div class="card">
    <h1>{{ t('admin.invoices.title') }}</h1>
    <p class="hint">{{ t('admin.invoices.hint') }}</p>

    <div class="filters">
      <select v-model="status" @change="load">
        <option value="">{{ t('admin.recharges.allStatuses') }}</option>
        <option v-for="(label, key) in STATUS_LABELS" :key="key" :value="key">{{ label }}</option>
      </select>
    </div>

    <div v-if="selectedAccount" class="selected-account">
      <p class="selected-account-name">{{ selectedAccount.label }}</p>
      <button type="button" class="link-btn" @click="clearAccount">{{ t('admin.viewAllAccounts') }}</button>
    </div>
    <form v-else class="search-row" @submit.prevent="searchAccounts">
      <input v-model="accountSearch" type="text" :placeholder="t('admin.filterByAccountPlaceholder')" />
      <button type="submit">{{ t('admin.accounts.searchBtn') }}</button>
    </form>
    <p v-if="searchingAccounts">{{ t('common.loading') }}</p>
    <ul v-else-if="accountResults.length" class="account-results">
      <li v-for="acc in accountResults" :key="acc.id">
        <button type="button" class="account-result" @click="selectAccount(acc)">
          <span>{{ acc.businessName }}</span>
          <span class="account-result-sub">{{ acc.accountNumber }}</span>
        </button>
      </li>
    </ul>

    <p v-if="loading">{{ t('common.loading') }}</p>
    <p v-else-if="error" class="error">{{ error }}</p>
    <p v-else-if="!invoices.length" class="empty">{{ t('invoices.empty') }}</p>
    <ul v-else class="list">
      <li v-for="i in invoices" :key="i.id" class="item">
        <div class="item-row">
          <p class="item-title">{{ i.totalAmount }} {{ currencySymbol(i.currency) }}</p>
          <span class="badge" :class="i.status">{{ STATUS_LABELS[i.status] ?? i.status }}</span>
        </div>
        <p class="item-phone">{{ i.accountName }} · {{ i.invoiceNumber }}</p>
        <p class="item-phone">{{ i.invoiceDate }}</p>
      </li>
    </ul>
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
    margin: 0 0 0.3rem;
    font-size: 0.82rem;
    color: #777;
}
.filters {
    display: flex;
    gap: 0.5rem;
}
.filters select {
    flex: 1;
    padding: 0.5rem 0.6rem;
    border: 1px solid #d0d3d8;
    border-radius: 8px;
    font-size: 0.9rem;
    background: white;
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
    margin: 0;
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
    padding: 0.6rem 0.8rem;
    background: #eefaf5;
    border-radius: 8px;
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
.badge {
    flex-shrink: 0;
    font-size: 0.72rem;
    font-weight: 600;
    padding: 0.25rem 0.55rem;
    border-radius: 999px;
    background: #eef2f5;
    color: #666;
}
.badge.paid {
    background: #e3f9ef;
    color: #0f9d58;
}
.badge.pending {
    background: #fff4e0;
    color: #b7791f;
}
.badge.cancelled, .badge.refunded {
    background: #fdecea;
    color: #c0392b;
}
</style>
