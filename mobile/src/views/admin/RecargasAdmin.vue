<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { listRechargesAdmin, createRecharge, completeRecharge, cancelRecharge, failRecharge } from '../../api/adminRecharges';
import { convertToBase } from '../../api/adminExchange';
import { listAccounts } from '../../api/adminAccounts';
import { hasScope } from '../../api/permissions';
import { useActiveCurrencies, loadActiveCurrencies } from '../../composables/currencies';
import { currencySymbol } from '../../utils/currency';

const { activeCurrencies } = useActiveCurrencies();

const { t } = useI18n();

const loading = ref(true);
const error = ref('');
const recharges = ref([]);
const status = ref('');

const accountSearch = ref('');
const accountResults = ref([]);
const searchingAccounts = ref(false);
const selectedAccount = ref(null);

const canCreate = ref(false);
const canComplete = ref(false);
const canCancel = ref(false);
const canFail = ref(false);

const showCreateForm = ref(false);
const createSaving = ref(false);
const createError = ref('');
const createForm = reactive(emptyCreateForm());
const targetSearch = ref('');
const targetResults = ref([]);
const searchingTarget = ref(false);
const selectedTarget = ref(null);

const actionId = ref(null);
const actionError = ref('');

const converting = ref(false);
const convertError = ref('');
const convertResult = ref(null);

const STATUS_LABELS = computed(() => ({
    pending: t('recharges.statusPending'),
    completed: t('recharges.statusCompleted'),
    cancelled: t('recharges.statusCancelled'),
    failed: t('recharges.statusFailed'),
}));

function hasConversion(r) {
    return r.originalCurrency && r.originalCurrency !== r.currency;
}

function emptyCreateForm() {
    return { amount: '', currency: 'USD', paymentMethod: '', referenceNumber: '', notes: '' };
}

async function doConvert() {
    convertError.value = '';
    converting.value = true;
    try {
        const result = await convertToBase(createForm.amount, createForm.currency);
        convertResult.value = result;
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
        recharges.value = await listRechargesAdmin(filters);
    } catch (e) {
        error.value = e.response?.data?.message || t('recharges.error');
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
        selectedTarget.value = null;
    }
}

async function searchTarget() {
    if (!targetSearch.value) {
        targetResults.value = [];
        return;
    }
    searchingTarget.value = true;
    try {
        targetResults.value = await listAccounts({ search: targetSearch.value });
    } finally {
        searchingTarget.value = false;
    }
}

function selectTarget(acc) {
    selectedTarget.value = { id: acc.id, label: acc.businessName, number: acc.accountNumber };
    targetResults.value = [];
    targetSearch.value = '';
}

async function submitCreate() {
    createError.value = '';
    if (!selectedTarget.value) {
        createError.value = t('admin.recharges.createError');
        return;
    }
    createSaving.value = true;
    try {
        await createRecharge({
            accountNumber: selectedTarget.value.number,
            amount: createForm.amount,
            currency: createForm.currency,
            paymentMethod: createForm.paymentMethod || null,
            referenceNumber: createForm.referenceNumber || null,
            notes: createForm.notes || null,
        });
        showCreateForm.value = false;
        Object.assign(createForm, emptyCreateForm());
        selectedTarget.value = null;
        convertResult.value = null;
        await load();
    } catch (e) {
        createError.value = e.response?.data?.message || t('admin.recharges.createError');
    } finally {
        createSaving.value = false;
    }
}

async function doComplete(r) {
    actionError.value = '';
    actionId.value = r.id;
    try {
        await completeRecharge(r.id);
        await load();
    } catch (e) {
        actionError.value = e.response?.data?.message || t('admin.recharges.actionError');
    } finally {
        actionId.value = null;
    }
}

async function doCancel(r) {
    actionError.value = '';
    actionId.value = r.id;
    try {
        await cancelRecharge(r.id);
        await load();
    } catch (e) {
        actionError.value = e.response?.data?.message || t('admin.recharges.actionError');
    } finally {
        actionId.value = null;
    }
}

async function doFail(r) {
    actionError.value = '';
    actionId.value = r.id;
    try {
        await failRecharge(r.id, 'Marcado como fallida por staff');
        await load();
    } catch (e) {
        actionError.value = e.response?.data?.message || t('admin.recharges.actionError');
    } finally {
        actionId.value = null;
    }
}

onMounted(async () => {
    canCreate.value = await hasScope('recharges_admin.create');
    canComplete.value = await hasScope('recharges_admin.complete');
    canCancel.value = await hasScope('recharges_admin.cancel');
    canFail.value = await hasScope('recharges_admin.fail');
    await load();
    if (canCreate.value) {
        loadActiveCurrencies();
    }
});
</script>

<template>
  <div class="card">
    <div class="header-row">
      <h1>{{ t('admin.recharges.title') }}</h1>
      <button v-if="canCreate" class="link-btn" @click="toggleCreateForm">
        {{ showCreateForm ? t('common.cancel') : t('admin.recharges.newBtn') }}
      </button>
    </div>
    <p class="hint">{{ t('admin.recharges.hint') }}</p>

    <form v-if="showCreateForm" class="edit-form create-form" @submit.prevent="submitCreate">
      <div v-if="selectedTarget" class="selected-account">
        <p class="selected-account-name">{{ selectedTarget.label }} ({{ selectedTarget.number }})</p>
        <button type="button" class="link-btn" @click="selectedTarget = null">{{ t('common.cancel') }}</button>
      </div>
      <template v-else>
        <label>
          {{ t('admin.recharges.accountLabel') }}
          <input v-model="targetSearch" type="text" :placeholder="t('admin.recharges.accountPlaceholder')" @keyup.enter.prevent="searchTarget" />
        </label>
        <button type="button" class="secondary" @click="searchTarget">{{ t('admin.accounts.searchBtn') }}</button>
        <p v-if="searchingTarget">{{ t('common.loading') }}</p>
        <ul v-else-if="targetResults.length" class="account-results">
          <li v-for="acc in targetResults" :key="acc.id">
            <button type="button" class="account-result" @click="selectTarget(acc)">
              <span>{{ acc.businessName }}</span>
              <span class="account-result-sub">{{ acc.accountNumber }}</span>
            </button>
          </li>
        </ul>
      </template>
      <label>
        {{ t('admin.recharges.amountLabel') }}
        <input v-model="createForm.amount" type="number" step="0.01" min="0.01" required />
      </label>
      <label>
        {{ t('admin.recharges.currencyLabel') }}
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
        {{ t('admin.recharges.paymentMethodLabel') }}
        <input v-model="createForm.paymentMethod" type="text" />
      </label>
      <label>
        {{ t('admin.recharges.referenceNumberLabel') }}
        <input v-model="createForm.referenceNumber" type="text" />
      </label>
      <label>
        {{ t('admin.recharges.notesLabel') }}
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
    <p v-else-if="!recharges.length" class="empty">{{ t('recharges.empty') }}</p>
    <ul v-else class="list">
      <li v-for="r in recharges" :key="r.id" class="item">
        <div class="item-row">
          <p class="item-title">
            <template v-if="hasConversion(r)">{{ r.originalAmount }} {{ currencySymbol(r.originalCurrency) }}</template>
            <template v-else>{{ r.amount }} {{ currencySymbol(r.currency) }}</template>
          </p>
          <span class="badge" :class="r.status">{{ STATUS_LABELS[r.status] ?? r.status }}</span>
        </div>
        <p class="item-phone">{{ r.accountName }} · {{ r.accountNumber }}</p>
        <p class="item-phone">{{ r.createdAt }}</p>
        <div v-if="r.status === 'pending' && (canComplete || canCancel || canFail)" class="actions">
          <button v-if="canComplete" class="secondary" :disabled="actionId === r.id" @click="doComplete(r)">
            {{ t('admin.recharges.complete') }}
          </button>
          <button v-if="canCancel" class="secondary" :disabled="actionId === r.id" @click="doCancel(r)">
            {{ t('common.cancel') }}
          </button>
          <button v-if="canFail" class="secondary" :disabled="actionId === r.id" @click="doFail(r)">
            {{ t('admin.recharges.fail') }}
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
.badge.completed {
    background: #e3f9ef;
    color: #0f9d58;
}
.badge.pending {
    background: #fff4e0;
    color: #b7791f;
}
.badge.failed, .badge.cancelled {
    background: #fdecea;
    color: #c0392b;
}
</style>
