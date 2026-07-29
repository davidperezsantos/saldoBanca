<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { listTransfersAdmin, createTransfer, processTransfer, cancelTransfer } from '../../api/adminTransfers';
import { convertToBase } from '../../api/adminExchange';
import { listAccounts } from '../../api/adminAccounts';
import { hasScope } from '../../api/permissions';
import { useActiveCurrencies, loadActiveCurrencies } from '../../composables/currencies';
import { currencySymbol } from '../../utils/currency';

const { activeCurrencies } = useActiveCurrencies();

const { t } = useI18n();

const loading = ref(true);
const error = ref('');
const transfers = ref([]);
const status = ref('');

const accountSearch = ref('');
const accountResults = ref([]);
const searchingAccounts = ref(false);
const selectedAccount = ref(null);

const canCreate = ref(false);
const canProcess = ref(false);
const canCancel = ref(false);

const showCreateForm = ref(false);
const createSaving = ref(false);
const createError = ref('');
const createForm = reactive(emptyCreateForm());
const originSearch = ref('');
const originResults = ref([]);
const searchingOrigin = ref(false);
const selectedOrigin = ref(null);

const actionId = ref(null);
const actionError = ref('');

const converting = ref(false);
const convertError = ref('');
const convertResult = ref(null);

const STATUS_LABELS = computed(() => ({
    pending: t('transfers.statusPending'),
    processed: t('transfers.statusProcessed'),
    cancelled: t('transfers.statusCancelled'),
}));

function emptyCreateForm() {
    return { destinationAccountNumber: '', amount: '', currency: 'USD', notes: '' };
}

async function doConvert() {
    convertError.value = '';
    converting.value = true;
    try {
        const result = await convertToBase(createForm.amount, createForm.currency);
        convertResult.value = result;
        createForm.originalAmount = result.originalAmount;
        createForm.originalCurrency = result.originalCurrency;
        createForm.amount = result.convertedAmount;
        createForm.currency = result.baseCurrency;
    } catch (e) {
        convertError.value = e.response?.data?.message || t('admin.convert.error');
    } finally {
        converting.value = false;
    }
}

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const filters = {};
        if (status.value) filters.status = status.value;
        if (selectedAccount.value) filters.accountId = selectedAccount.value.id;
        transfers.value = await listTransfersAdmin(filters);
    } catch (e) {
        error.value = e.response?.data?.message || t('transfers.loadError');
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

function toggleCreateForm() {
    showCreateForm.value = !showCreateForm.value;
    createError.value = '';
    convertError.value = '';
    convertResult.value = null;
    if (!showCreateForm.value) {
        Object.assign(createForm, emptyCreateForm());
        selectedOrigin.value = null;
    }
}

async function searchOrigin() {
    if (!originSearch.value) {
        originResults.value = [];
        return;
    }
    searchingOrigin.value = true;
    try {
        originResults.value = await listAccounts({ search: originSearch.value });
    } finally {
        searchingOrigin.value = false;
    }
}

function selectOrigin(acc) {
    selectedOrigin.value = { id: acc.id, label: acc.businessName, number: acc.accountNumber };
    originResults.value = [];
    originSearch.value = '';
}

async function submitCreate() {
    createError.value = '';
    if (!selectedOrigin.value) {
        createError.value = t('admin.transfers.createError');
        return;
    }
    createSaving.value = true;
    try {
        await createTransfer({
            originAccountId: selectedOrigin.value.id,
            destinationAccountNumber: createForm.destinationAccountNumber,
            amount: createForm.amount,
            currency: createForm.currency,
            notes: createForm.notes || null,
            originalAmount: createForm.originalAmount || null,
            originalCurrency: createForm.originalCurrency || null,
        });
        showCreateForm.value = false;
        Object.assign(createForm, emptyCreateForm());
        selectedOrigin.value = null;
        convertResult.value = null;
        await load();
    } catch (e) {
        createError.value = e.response?.data?.message || t('admin.transfers.createError');
    } finally {
        createSaving.value = false;
    }
}

async function doProcess(tr) {
    actionError.value = '';
    actionId.value = tr.id;
    try {
        await processTransfer(tr.id);
        await load();
    } catch (e) {
        actionError.value = e.response?.data?.message || t('admin.transfers.actionError');
    } finally {
        actionId.value = null;
    }
}

async function doCancel(tr) {
    actionError.value = '';
    actionId.value = tr.id;
    try {
        await cancelTransfer(tr.id);
        await load();
    } catch (e) {
        actionError.value = e.response?.data?.message || t('admin.transfers.actionError');
    } finally {
        actionId.value = null;
    }
}

onMounted(async () => {
    canCreate.value = await hasScope('transfers_admin.create');
    canProcess.value = await hasScope('transfers_admin.process');
    canCancel.value = await hasScope('transfers_admin.cancel');
    await load();
    if (canCreate.value) {
        loadActiveCurrencies();
    }
});
</script>

<template>
  <div class="card">
    <div class="header-row">
      <h1>{{ t('admin.transfers.title') }}</h1>
      <button v-if="canCreate" class="link-btn" @click="toggleCreateForm">
        {{ showCreateForm ? t('common.cancel') : t('admin.transfers.newBtn') }}
      </button>
    </div>
    <p class="hint">{{ t('admin.transfers.hint') }}</p>

    <form v-if="showCreateForm" class="edit-form create-form" @submit.prevent="submitCreate">
      <div v-if="selectedOrigin" class="selected-account">
        <p class="selected-account-name">{{ selectedOrigin.label }} ({{ selectedOrigin.number }})</p>
        <button type="button" class="link-btn" @click="selectedOrigin = null">{{ t('common.cancel') }}</button>
      </div>
      <template v-else>
        <label>
          {{ t('admin.transfers.originLabel') }}
          <input v-model="originSearch" type="text" :placeholder="t('admin.transfers.originPlaceholder')" @keyup.enter.prevent="searchOrigin" />
        </label>
        <button type="button" class="secondary" @click="searchOrigin">{{ t('admin.accounts.searchBtn') }}</button>
        <p v-if="searchingOrigin">{{ t('common.loading') }}</p>
        <ul v-else-if="originResults.length" class="account-results">
          <li v-for="acc in originResults" :key="acc.id">
            <button type="button" class="account-result" @click="selectOrigin(acc)">
              <span>{{ acc.businessName }}</span>
              <span class="account-result-sub">{{ acc.accountNumber }}</span>
            </button>
          </li>
        </ul>
      </template>
      <label>
        {{ t('admin.transfers.destinationLabel') }}
        <input v-model="createForm.destinationAccountNumber" type="text" required />
      </label>
      <label>
        {{ t('admin.transfers.amountLabel') }}
        <input v-model="createForm.amount" type="number" step="0.01" min="0.01" required />
      </label>
      <label>
        {{ t('admin.transfers.currencyLabel') }}
        <select v-model="createForm.currency" required>
          <option v-for="c in activeCurrencies" :key="c.code" :value="c.code">{{ c.code }} - {{ c.name }}</option>
        </select>
      </label>
      <button type="button" class="secondary" :disabled="!createForm.amount || !createForm.currency || converting" @click="doConvert">
        {{ converting ? t('common.loading') : t('admin.convert.btn') }}
      </button>
      <p v-if="convertError" class="error">{{ convertError }}</p>
      <p v-if="convertResult" class="convert-hint">
        {{ t('admin.convert.result', { originalAmount: convertResult.originalAmount, originalCurrency: convertResult.originalCurrency, convertedAmount: convertResult.convertedAmount, baseCurrency: convertResult.baseCurrency }) }}
      </p>
      <label>
        {{ t('admin.transfers.notesLabel') }}
        <input v-model="createForm.notes" type="text" />
      </label>
      <p v-if="createError" class="error">{{ createError }}</p>
      <button type="submit" :disabled="createSaving">
        {{ createSaving ? t('common.saving') : t('common.save') }}
      </button>
    </form>

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

    <p v-if="actionError" class="error">{{ actionError }}</p>
    <p v-if="loading">{{ t('common.loading') }}</p>
    <p v-else-if="error" class="error">{{ error }}</p>
    <p v-else-if="!transfers.length" class="empty">{{ t('transfers.empty') }}</p>
    <ul v-else class="list">
      <li v-for="tr in transfers" :key="tr.id" class="item">
        <div class="item-row">
          <p class="item-title">{{ tr.amount }} {{ currencySymbol(tr.currency) }}</p>
          <span class="badge" :class="tr.status">{{ STATUS_LABELS[tr.status] ?? tr.status }}</span>
        </div>
        <p class="item-phone">{{ tr.originAccountName }} ({{ tr.originAccountNumber }}) → {{ tr.destAccountName }} ({{ tr.destAccountNumber }})</p>
        <p class="item-phone">{{ tr.createdAt }}</p>
        <div v-if="tr.status === 'pending' && (canProcess || canCancel)" class="actions">
          <button v-if="canProcess" class="secondary" :disabled="actionId === tr.id" @click="doProcess(tr)">
            {{ t('admin.transfers.process') }}
          </button>
          <button v-if="canCancel" class="secondary" :disabled="actionId === tr.id" @click="doCancel(tr)">
            {{ t('common.cancel') }}
          </button>
        </div>
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
.header-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}
.hint {
    margin: 0 0 0.3rem;
    font-size: 0.82rem;
    color: #777;
}
.create-form {
    padding: 0.9rem;
    background: #f7f9fa;
    border-radius: 8px;
    margin-bottom: 0.5rem;
}
.edit-form {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}
.edit-form label {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    font-size: 0.8rem;
    color: #333;
}
.edit-form input,
.edit-form select {
    padding: 0.5rem 0.6rem;
    border: 1px solid #d0d3d8;
    border-radius: 8px;
    font-size: 0.9rem;
    background: white;
}
.create-form button[type='submit'] {
    padding: 0.6rem;
    border: none;
    border-radius: 8px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    font-weight: 600;
    cursor: pointer;
}
.create-form button:disabled {
    opacity: 0.6;
}
.actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 0.6rem;
}
.actions button,
.secondary {
    flex: 1;
    min-width: 90px;
    padding: 0.5rem;
    border: 1px solid #d0d3d8;
    border-radius: 8px;
    background: white;
    color: #333;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
}
.actions button:disabled {
    opacity: 0.6;
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
.convert-hint {
    margin: 0;
    font-size: 0.8rem;
    color: #1a56b0;
    background: #e6f0fd;
    padding: 0.5rem 0.7rem;
    border-radius: 8px;
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
.badge.processed {
    background: #e3f9ef;
    color: #0f9d58;
}
.badge.pending {
    background: #fff4e0;
    color: #b7791f;
}
.badge.cancelled {
    background: #fdecea;
    color: #c0392b;
}
</style>
