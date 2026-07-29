<script setup>
import { ref, onMounted, reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import { hasScope } from '../../api/permissions';
import { listAccounts, createAccount, updateAccount, changeAccountStatus, getAccountBalance } from '../../api/adminAccounts';
import { formatMoney } from '../../utils/currency';
import { useActiveCurrencies, loadActiveCurrencies } from '../../composables/currencies';
import documentTypes from '../../data/documentTypes.json';

const { t } = useI18n();
const { activeCurrencies } = useActiveCurrencies();

const loading = ref(true);
const error = ref('');
const accounts = ref([]);
const search = ref('');

const canCreate = ref(false);
const canUpdate = ref(false);
const canChangeStatus = ref(false);
const canViewBalance = ref(false);

const editingId = ref(null);
const forms = reactive({});
const saving = ref(false);
const saveError = ref('');

const balances = reactive({});
const balanceLoadingId = ref(null);

const showCreateForm = ref(false);
const createSaving = ref(false);
const createError = ref('');
const createForm = ref(emptyForm());

const STATUSES = ['active', 'pending', 'suspended'];

function emptyForm() {
    return {
        accountType: 'client',
        businessName: '',
        documentType: documentTypes[0].value,
        documentNumber: '',
        email: '',
        phone: '',
        defaultCurrency: 'USD',
        creditLimit: '0.00',
    };
}

function toggleCreateForm() {
    showCreateForm.value = !showCreateForm.value;
    createError.value = '';
    if (!showCreateForm.value) {
        createForm.value = emptyForm();
    }
}

async function submitCreate() {
    createError.value = '';
    createSaving.value = true;
    try {
        await createAccount(createForm.value);
        showCreateForm.value = false;
        createForm.value = emptyForm();
        await load();
    } catch (e) {
        createError.value = e.response?.data?.message || t('admin.accounts.saveError');
    } finally {
        createSaving.value = false;
    }
}

async function load() {
    loading.value = true;
    error.value = '';
    try {
        accounts.value = await listAccounts(search.value ? { search: search.value } : {});
    } catch (e) {
        error.value = e.response?.data?.message || t('admin.accounts.error');
    } finally {
        loading.value = false;
    }
}

function startEdit(item) {
    editingId.value = item.id;
    saveError.value = '';
    forms[item.id] = {
        accountType: item.accountType,
        businessName: item.businessName,
        documentType: item.documentType,
        documentNumber: item.documentNumber,
        email: item.email ?? '',
        phone: item.phone ?? '',
        defaultCurrency: item.defaultCurrency,
        creditLimit: item.creditLimit,
        status: item.status,
    };
}

function cancelEdit() {
    editingId.value = null;
}

async function saveEdit(id) {
    saveError.value = '';
    saving.value = true;
    try {
        const { status, ...payload } = forms[id];
        await updateAccount(id, payload);
        if (status !== accounts.value.find((a) => a.id === id)?.status) {
            await changeAccountStatus(id, status);
        }
        editingId.value = null;
        await load();
    } catch (e) {
        saveError.value = e.response?.data?.message || t('admin.accounts.saveError');
    } finally {
        saving.value = false;
    }
}

async function toggleBalance(item) {
    if (balances[item.id]) {
        delete balances[item.id];
        return;
    }
    balanceLoadingId.value = item.id;
    try {
        balances[item.id] = await getAccountBalance(item.id);
    } catch (e) {
        saveError.value = e.response?.data?.message || t('admin.accounts.error');
    } finally {
        balanceLoadingId.value = null;
    }
}

onMounted(async () => {
    canCreate.value = await hasScope('accounts_admin.create');
    canUpdate.value = await hasScope('accounts_admin.update');
    canChangeStatus.value = await hasScope('accounts_admin.status');
    canViewBalance.value = await hasScope('accounts_admin.balance');
    await load();
    if (canCreate.value || canUpdate.value) {
        loadActiveCurrencies();
    }
});
</script>

<template>
  <div class="card">
    <div class="header-row">
      <h1>{{ t('admin.accounts.title') }}</h1>
      <button v-if="canCreate" class="link-btn" @click="toggleCreateForm">
        {{ showCreateForm ? t('common.cancel') : t('admin.accounts.newBtn') }}
      </button>
    </div>
    <p class="hint">{{ t('admin.accounts.hint') }}</p>

    <form class="search-row" @submit.prevent="load">
      <input v-model="search" type="text" :placeholder="t('admin.accounts.searchPlaceholder')" />
      <button type="submit">{{ t('admin.accounts.searchBtn') }}</button>
    </form>

    <form v-if="showCreateForm" class="edit-form create-form" @submit.prevent="submitCreate">
      <label>
        {{ t('admin.accounts.typeLabel') }}
        <select v-model="createForm.accountType">
          <option value="client">{{ t('admin.accounts.typeClient') }}</option>
          <option value="business">{{ t('admin.accounts.typeBusiness') }}</option>
        </select>
      </label>
      <label>
        {{ t('admin.accounts.nameLabel') }}
        <input v-model="createForm.businessName" type="text" required />
      </label>
      <label>
        {{ t('admin.accounts.docTypeLabel') }}
        <select v-model="createForm.documentType">
          <option v-for="d in documentTypes" :key="d.value" :value="d.value">{{ d.label }}</option>
        </select>
      </label>
      <label>
        {{ t('admin.accounts.docNumberLabel') }}
        <input v-model="createForm.documentNumber" type="text" required />
      </label>
      <label>
        {{ t('admin.accounts.emailLabel') }}
        <input v-model="createForm.email" type="email" />
      </label>
      <label>
        {{ t('admin.accounts.phoneLabel') }}
        <input v-model="createForm.phone" type="text" />
      </label>
      <div class="row">
        <label>
          {{ t('admin.accounts.currencyLabel') }}
          <select v-model="createForm.defaultCurrency">
            <option v-for="c in activeCurrencies" :key="c.code" :value="c.code">{{ c.code }} - {{ c.name }}</option>
          </select>
        </label>
        <label>
          {{ t('admin.accounts.creditLimitLabel') }}
          <input v-model="createForm.creditLimit" type="number" step="0.01" />
        </label>
      </div>
      <p v-if="createError" class="error">{{ createError }}</p>
      <button type="submit" :disabled="createSaving">
        {{ createSaving ? t('common.saving') : t('common.save') }}
      </button>
    </form>

    <p v-if="loading">{{ t('common.loading') }}</p>
    <p v-else-if="error" class="error">{{ error }}</p>
    <p v-else-if="!accounts.length" class="empty">{{ t('admin.accounts.empty') }}</p>

    <ul v-else class="list">
      <li v-for="item in accounts" :key="item.id" class="item">
        <div class="item-row">
          <p class="item-title">{{ item.businessName }}</p>
          <span class="badge" :class="item.status">{{ t(`admin.accounts.status${item.status.charAt(0).toUpperCase()}${item.status.slice(1)}`) }}</span>
        </div>
        <p class="item-phone">{{ item.accountNumber }} · {{ item.accountType === 'business' ? t('admin.accounts.typeBusiness') : t('admin.accounts.typeClient') }}</p>
        <p class="item-phone">{{ formatMoney(item.saldoDisponible, item.defaultCurrency) }}</p>

        <div v-if="balances[item.id]" class="balance-box">
          <p>{{ t('admin.accounts.balanceAvailable') }}: {{ formatMoney(balances[item.id].available, balances[item.id].currency) }}</p>
          <p>{{ t('admin.accounts.balancePending') }}: {{ formatMoney(balances[item.id].pending, balances[item.id].currency) }}</p>
          <p>{{ t('admin.accounts.balanceReserved') }}: {{ formatMoney(balances[item.id].reserved, balances[item.id].currency) }}</p>
        </div>

        <div v-if="editingId !== item.id" class="actions">
          <button v-if="canViewBalance" class="secondary" :disabled="balanceLoadingId === item.id" @click="toggleBalance(item)">
            {{ balances[item.id] ? t('admin.accounts.hideBalance') : t('admin.accounts.viewBalance') }}
          </button>
          <button v-if="canUpdate" class="secondary" @click="startEdit(item)">{{ t('common.edit') }}</button>
          <router-link
            class="secondary"
            :to="{ path: '/autorizados-admin', query: { accountId: item.id, accountLabel: item.businessName } }"
          >{{ t('admin.accounts.viewAuthorized') }}</router-link>
          <router-link
            class="secondary"
            :to="{ path: '/historial-admin', query: { accountId: item.id, accountLabel: item.businessName } }"
          >{{ t('admin.accounts.viewHistory') }}</router-link>
        </div>

        <form v-else class="edit-form" @submit.prevent="saveEdit(item.id)">
          <label>
            {{ t('admin.accounts.nameLabel') }}
            <input v-model="forms[item.id].businessName" type="text" required />
          </label>
          <label>
            {{ t('admin.accounts.emailLabel') }}
            <input v-model="forms[item.id].email" type="email" />
          </label>
          <label>
            {{ t('admin.accounts.phoneLabel') }}
            <input v-model="forms[item.id].phone" type="text" />
          </label>
          <div class="row">
            <label>
              {{ t('admin.accounts.currencyLabel') }}
              <select v-model="forms[item.id].defaultCurrency">
                <option v-for="c in activeCurrencies" :key="c.code" :value="c.code">{{ c.code }} - {{ c.name }}</option>
              </select>
            </label>
            <label>
              {{ t('admin.accounts.creditLimitLabel') }}
              <input v-model="forms[item.id].creditLimit" type="number" step="0.01" />
            </label>
          </div>
          <label v-if="canChangeStatus">
            {{ t('admin.accounts.statusLabel') }}
            <select v-model="forms[item.id].status">
              <option v-for="s in STATUSES" :key="s" :value="s">
                {{ t(`admin.accounts.status${s.charAt(0).toUpperCase()}${s.slice(1)}`) }}
              </option>
            </select>
          </label>
          <p v-if="saveError" class="error">{{ saveError }}</p>
          <div class="actions">
            <button type="button" class="secondary" @click="cancelEdit">{{ t('common.cancel') }}</button>
            <button type="submit" :disabled="saving">{{ saving ? t('common.saving') : t('common.save') }}</button>
          </div>
        </form>
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
.hint {
    margin: 0 0 0.5rem;
    font-size: 0.82rem;
    color: #777;
}
.search-row {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
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
.create-form {
    padding: 0.9rem;
    background: #f7f9fa;
    border-radius: 8px;
    margin-bottom: 0.5rem;
}
.create-form select {
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
    gap: 0.9rem;
}
.item {
    padding: 0.7rem 0;
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
    overflow-wrap: break-word;
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
.badge.active {
    background: #e3f9ef;
    color: #0f9d58;
}
.badge.pending {
    background: #fff4e0;
    color: #b7791f;
}
.badge.suspended {
    background: #fdecea;
    color: #c0392b;
}
.balance-box {
    margin-top: 0.5rem;
    padding: 0.6rem 0.75rem;
    background: #f7f9fa;
    border-radius: 8px;
    font-size: 0.8rem;
    color: #555;
}
.balance-box p {
    margin: 0.15rem 0;
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
    text-decoration: none;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
}
.actions button[type='submit'] {
    border: none;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
}
.actions button:disabled {
    opacity: 0.6;
}
.edit-form {
    margin-top: 0.6rem;
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
}
.row {
    display: flex;
    gap: 0.6rem;
}
.row label {
    flex: 1;
}
</style>
