<script setup>
import { ref, computed, onMounted, reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import { hasScope } from '../../api/permissions';
import { listAccounts } from '../../api/adminAccounts';
import {
    previewReconciliation,
    createReconciliation,
    listReconciliationsAdmin,
    getReconciliation,
    sendReconciliation,
    approveReconciliation,
    rejectReconciliation,
    settleReconciliation,
} from '../../api/adminReconciliations';
import { currencySymbol } from '../../utils/currency';

const { t } = useI18n();

const canCreate = ref(false);
const canSend = ref(false);
const canApprove = ref(false);
const canSettle = ref(false);

const loading = ref(true);
const error = ref('');
const reconciliations = ref([]);
const status = ref('');

const STATUS_LABELS = computed(() => ({
    pending_business: t('admin.reconciliations.statusPendingBusiness'),
    approved_business: t('admin.reconciliations.statusApprovedBusiness'),
    rejected_business: t('admin.reconciliations.statusRejectedBusiness'),
    approved_admin: t('admin.reconciliations.statusApprovedAdmin'),
    rejected_admin: t('admin.reconciliations.statusRejectedAdmin'),
    settled: t('admin.reconciliations.statusSettled'),
}));

// --- filtro por cuenta de negocio ---
const accountSearch = ref('');
const accountResults = ref([]);
const searchingAccounts = ref(false);
const filterAccount = ref(null);

async function searchAccounts() {
    if (!accountSearch.value) {
        accountResults.value = [];
        return;
    }
    searchingAccounts.value = true;
    try {
        accountResults.value = await listAccounts({ search: accountSearch.value, accountType: 'business' });
    } finally {
        searchingAccounts.value = false;
    }
}

function selectFilterAccount(acc) {
    filterAccount.value = { id: acc.id, label: acc.businessName };
    accountResults.value = [];
    accountSearch.value = '';
    load();
}

function clearFilterAccount() {
    filterAccount.value = null;
    load();
}

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const filters = {};
        if (status.value) filters.status = status.value;
        if (filterAccount.value) filters.businessAccountId = filterAccount.value.id;
        reconciliations.value = await listReconciliationsAdmin(filters);
    } catch (e) {
        error.value = e.response?.data?.message || t('admin.reconciliations.error');
    } finally {
        loading.value = false;
    }
}

// --- crear ---
const showCreateForm = ref(false);
const createAccountSearch = ref('');
const createAccountResults = ref([]);
const createAccount = ref(null);
const periodStart = ref('');
const periodEnd = ref('');
const preview = ref(null);
const previewError = ref('');
const previewing = ref(false);
const creating = ref(false);
const createError = ref('');

function toggleCreateForm() {
    showCreateForm.value = !showCreateForm.value;
    createAccount.value = null;
    createAccountResults.value = [];
    createAccountSearch.value = '';
    periodStart.value = '';
    periodEnd.value = '';
    preview.value = null;
    previewError.value = '';
    createError.value = '';
}

async function searchCreateAccounts() {
    if (!createAccountSearch.value) {
        createAccountResults.value = [];
        return;
    }
    createAccountResults.value = await listAccounts({ search: createAccountSearch.value, accountType: 'business' });
}

function selectCreateAccount(acc) {
    createAccount.value = { id: acc.id, label: acc.businessName };
    createAccountResults.value = [];
    createAccountSearch.value = '';
}

async function doPreview() {
    previewError.value = '';
    preview.value = null;
    if (!createAccount.value || !periodStart.value || !periodEnd.value) {
        previewError.value = t('admin.reconciliations.previewMissingFields');
        return;
    }
    previewing.value = true;
    try {
        preview.value = await previewReconciliation({
            businessAccountId: createAccount.value.id,
            periodStart: periodStart.value,
            periodEnd: periodEnd.value,
        });
    } catch (e) {
        previewError.value = e.response?.data?.message || t('admin.reconciliations.previewError');
    } finally {
        previewing.value = false;
    }
}

async function confirmCreate() {
    createError.value = '';
    creating.value = true;
    try {
        await createReconciliation({
            businessAccountId: createAccount.value.id,
            periodStart: periodStart.value,
            periodEnd: periodEnd.value,
        });
        toggleCreateForm();
        await load();
    } catch (e) {
        createError.value = e.response?.data?.message || t('admin.reconciliations.saveError');
    } finally {
        creating.value = false;
    }
}

// --- detalle ---
const selectedId = ref(null);
const detail = ref(null);
const detailLoading = ref(false);
const detailError = ref('');
const actionBusy = ref(false);
const actionError = ref('');
const rejectReason = ref('');
const showRejectForm = ref(false);
const SETTLEMENT_METHODS = ['efectivo', 'transferencia'];
const settleForm = reactive({
    settlementMethod: '',
    settlementReference: '',
    settlementSecondaryMethod: '',
    settlementSecondaryReference: '',
    settlementNotes: '',
});

async function openDetail(id) {
    if (selectedId.value === id) {
        selectedId.value = null;
        detail.value = null;
        return;
    }
    selectedId.value = id;
    detail.value = null;
    detailError.value = '';
    actionError.value = '';
    showRejectForm.value = false;
    settleForm.settlementMethod = '';
    settleForm.settlementReference = '';
    settleForm.settlementSecondaryMethod = '';
    settleForm.settlementSecondaryReference = '';
    settleForm.settlementNotes = '';
    detailLoading.value = true;
    try {
        detail.value = await getReconciliation(id);
    } catch (e) {
        detailError.value = e.response?.data?.message || t('admin.reconciliations.error');
    } finally {
        detailLoading.value = false;
    }
}

async function doSend() {
    actionBusy.value = true;
    actionError.value = '';
    try {
        detail.value = await sendReconciliation(selectedId.value);
        await load();
    } catch (e) {
        actionError.value = e.response?.data?.message || t('admin.reconciliations.actionError');
    } finally {
        actionBusy.value = false;
    }
}

async function doApprove() {
    actionBusy.value = true;
    actionError.value = '';
    try {
        detail.value = await approveReconciliation(selectedId.value);
        await load();
    } catch (e) {
        actionError.value = e.response?.data?.message || t('admin.reconciliations.actionError');
    } finally {
        actionBusy.value = false;
    }
}

async function doReject() {
    actionBusy.value = true;
    actionError.value = '';
    try {
        detail.value = await rejectReconciliation(selectedId.value, rejectReason.value);
        showRejectForm.value = false;
        await load();
    } catch (e) {
        actionError.value = e.response?.data?.message || t('admin.reconciliations.actionError');
    } finally {
        actionBusy.value = false;
    }
}

async function doSettle() {
    actionBusy.value = true;
    actionError.value = '';
    try {
        detail.value = await settleReconciliation(selectedId.value, { ...settleForm });
        await load();
    } catch (e) {
        actionError.value = e.response?.data?.message || t('admin.reconciliations.actionError');
    } finally {
        actionBusy.value = false;
    }
}

onMounted(async () => {
    canCreate.value = await hasScope('reconciliations_admin.create');
    canSend.value = await hasScope('reconciliations_admin.send');
    canApprove.value = await hasScope('reconciliations_admin.approve');
    canSettle.value = await hasScope('reconciliations_admin.settle');
    await load();
});
</script>

<template>
  <div class="card">
    <div class="header-row">
      <h1>{{ t('admin.reconciliations.title') }}</h1>
      <button v-if="canCreate" class="link-btn" @click="toggleCreateForm">
        {{ showCreateForm ? t('common.cancel') : t('admin.reconciliations.newBtn') }}
      </button>
    </div>
    <p class="hint">{{ t('admin.reconciliations.hint') }}</p>

    <div v-if="showCreateForm" class="create-form">
      <div v-if="createAccount" class="selected-account">
        <p class="selected-account-name">{{ createAccount.label }}</p>
        <button type="button" class="link-btn" @click="createAccount = null">{{ t('admin.viewAllAccounts') }}</button>
      </div>
      <form v-else class="search-row" @submit.prevent="searchCreateAccounts">
        <input v-model="createAccountSearch" type="text" :placeholder="t('admin.filterByAccountPlaceholder')" />
        <button type="submit">{{ t('admin.accounts.searchBtn') }}</button>
      </form>
      <ul v-if="createAccountResults.length" class="account-results">
        <li v-for="acc in createAccountResults" :key="acc.id">
          <button type="button" class="account-result" @click="selectCreateAccount(acc)">
            <span>{{ acc.businessName }}</span>
            <span class="account-result-sub">{{ acc.accountNumber }}</span>
          </button>
        </li>
      </ul>

      <div class="row">
        <label>
          {{ t('admin.reconciliations.periodStart') }}
          <input v-model="periodStart" type="date" />
        </label>
        <label>
          {{ t('admin.reconciliations.periodEnd') }}
          <input v-model="periodEnd" type="date" />
        </label>
      </div>

      <button type="button" class="secondary-btn" :disabled="previewing" @click="doPreview">
        {{ previewing ? t('common.loading') : t('admin.reconciliations.previewBtn') }}
      </button>
      <p v-if="previewError" class="error">{{ previewError }}</p>

      <div v-if="preview" class="preview-box">
        <p>{{ t('admin.reconciliations.previewInvoices') }}: {{ preview.invoices.length }}</p>
        <p>{{ t('admin.reconciliations.previewTotal') }}: {{ preview.total }} {{ currencySymbol(preview.currency) }}</p>
        <p>{{ t('admin.reconciliations.previewTax') }}: {{ preview.taxAmount }} ({{ preview.taxPercent }}%)</p>
        <p>{{ t('admin.reconciliations.previewNet') }}: {{ preview.netAmount }} {{ currencySymbol(preview.currency) }}</p>
        <p v-if="!preview.invoices.length" class="error">{{ t('admin.reconciliations.noInvoicesError') }}</p>

        <div v-if="preview.payoutSplitPreview" class="payout-split-box">
          <p class="payout-split-title">{{ t('admin.reconciliations.payoutSplitTitle') }}</p>
          <p v-if="preview.payoutSplitPreview.error" class="error">{{ preview.payoutSplitPreview.error }}</p>
          <template v-else>
            <p>{{ t('admin.reconciliations.payoutSplitBase') }}: {{ preview.payoutSplitPreview.baseAmount }} {{ preview.payoutSplitPreview.baseCurrency }} ({{ preview.payoutSplitPreview.basePercent }}%)</p>
            <p v-if="preview.payoutSplitPreview.secondaryAmount">
              {{ t('admin.reconciliations.payoutSplitSecondary') }}: {{ preview.payoutSplitPreview.secondaryAmount }} {{ preview.payoutSplitPreview.secondaryCurrency }} ({{ preview.payoutSplitPreview.secondaryPercent }}%)
            </p>
            <p v-if="preview.payoutSplitPreview.exchangeRate">{{ t('admin.reconciliations.payoutSplitRate') }}: {{ preview.payoutSplitPreview.exchangeRate }}</p>
          </template>
        </div>

        <p v-if="createError" class="error">{{ createError }}</p>
        <button type="button" class="submit-btn" :disabled="creating || !preview.invoices.length" @click="confirmCreate">
          {{ creating ? t('common.saving') : t('admin.reconciliations.confirmCreateBtn') }}
        </button>
      </div>
    </div>

    <div class="filters">
      <select v-model="status" @change="load">
        <option value="">{{ t('admin.recharges.allStatuses') }}</option>
        <option v-for="(label, key) in STATUS_LABELS" :key="key" :value="key">{{ label }}</option>
      </select>
    </div>
    <div v-if="filterAccount" class="selected-account">
      <p class="selected-account-name">{{ filterAccount.label }}</p>
      <button type="button" class="link-btn" @click="clearFilterAccount">{{ t('admin.viewAllAccounts') }}</button>
    </div>
    <form v-else class="search-row" @submit.prevent="searchAccounts">
      <input v-model="accountSearch" type="text" :placeholder="t('admin.filterByAccountPlaceholder')" />
      <button type="submit">{{ t('admin.accounts.searchBtn') }}</button>
    </form>
    <p v-if="searchingAccounts">{{ t('common.loading') }}</p>
    <ul v-else-if="accountResults.length" class="account-results">
      <li v-for="acc in accountResults" :key="acc.id">
        <button type="button" class="account-result" @click="selectFilterAccount(acc)">
          <span>{{ acc.businessName }}</span>
          <span class="account-result-sub">{{ acc.accountNumber }}</span>
        </button>
      </li>
    </ul>

    <p v-if="loading">{{ t('common.loading') }}</p>
    <p v-else-if="error" class="error">{{ error }}</p>
    <p v-else-if="!reconciliations.length" class="empty">{{ t('admin.reconciliations.empty') }}</p>
    <ul v-else class="list">
      <li v-for="r in reconciliations" :key="r.id" class="item">
        <button type="button" class="item-header" @click="openDetail(r.id)">
          <div class="item-row">
            <p class="item-title">{{ r.reconciliationNumber }} · {{ r.businessAccountName }}</p>
            <span class="badge" :class="r.status">{{ STATUS_LABELS[r.status] ?? r.status }}</span>
          </div>
          <p class="item-phone">{{ r.periodStart }} — {{ r.periodEnd }} · {{ r.totalAmount }} {{ currencySymbol(r.currency) }}</p>
        </button>

        <div v-if="selectedId === r.id" class="detail">
          <p v-if="detailLoading">{{ t('common.loading') }}</p>
          <p v-else-if="detailError" class="error">{{ detailError }}</p>
          <template v-else-if="detail">
            <p>{{ t('admin.reconciliations.invoiceCount') }}: {{ detail.invoiceCount }}</p>
            <p>{{ t('admin.reconciliations.previewNet') }}: {{ detail.netAmount }} {{ currencySymbol(detail.currency) }}</p>
            <p v-if="detail.payoutAccountBase">{{ t('admin.reconciliations.payoutAccount') }}: {{ detail.payoutAccountBase.bankName }} · {{ detail.payoutAccountBase.accountNumber }}</p>

            <div v-if="detail.events && detail.events.length" class="events">
              <p class="events-title">{{ t('admin.reconciliations.events') }}</p>
              <p v-for="e in detail.events" :key="e.id" class="event-line">{{ e.createdAt }} · {{ e.eventType }} · {{ e.performedBy }}</p>
            </div>

            <p v-if="actionError" class="error">{{ actionError }}</p>

            <div class="actions">
              <button v-if="canSend && detail.status === 'pending_business'" class="secondary" :disabled="actionBusy" @click="doSend">
                {{ t('admin.reconciliations.sendBtn') }}
              </button>
              <button v-if="canApprove && detail.status === 'approved_business'" class="secondary" :disabled="actionBusy" @click="doApprove">
                {{ t('admin.reconciliations.approveBtn') }}
              </button>
              <button v-if="canApprove && ['pending_business', 'approved_business'].includes(detail.status)" class="secondary" :disabled="actionBusy" @click="showRejectForm = !showRejectForm">
                {{ t('admin.reconciliations.rejectBtn') }}
              </button>
            </div>

            <form v-if="showRejectForm" class="reject-form" @submit.prevent="doReject">
              <input v-model="rejectReason" type="text" :placeholder="t('admin.reconciliations.rejectReasonPlaceholder')" />
              <button type="submit" :disabled="actionBusy">{{ t('admin.reconciliations.rejectBtn') }}</button>
            </form>

            <form v-if="canSettle && detail.status === 'approved_admin'" class="settle-form" @submit.prevent="doSettle">
              <label>
                {{ t('admin.reconciliations.settlementMethod') }}
                <select v-model="settleForm.settlementMethod" required>
                  <option value="" disabled>{{ t('admin.reconciliations.settlementMethod') }}</option>
                  <option v-for="m in SETTLEMENT_METHODS" :key="m" :value="m">{{ t(`admin.reconciliations.method_${m}`) }}</option>
                </select>
              </label>
              <label v-if="settleForm.settlementMethod === 'transferencia'">
                {{ t('admin.reconciliations.settlementReference') }}
                <input v-model="settleForm.settlementReference" type="text" required />
              </label>

              <template v-if="detail.settlementBaseAmount">
                <label>
                  {{ t('admin.reconciliations.settlementSecondaryMethod') }}
                  <select v-model="settleForm.settlementSecondaryMethod" required>
                    <option value="" disabled>{{ t('admin.reconciliations.settlementSecondaryMethod') }}</option>
                    <option v-for="m in SETTLEMENT_METHODS" :key="m" :value="m">{{ t(`admin.reconciliations.method_${m}`) }}</option>
                  </select>
                </label>
                <label v-if="settleForm.settlementSecondaryMethod === 'transferencia'">
                  {{ t('admin.reconciliations.settlementSecondaryReference') }}
                  <input v-model="settleForm.settlementSecondaryReference" type="text" required />
                </label>
              </template>

              <label>
                {{ t('admin.reconciliations.settlementNotes') }}
                <input v-model="settleForm.settlementNotes" type="text" />
              </label>
              <button type="submit" class="submit-btn" :disabled="actionBusy">
                {{ actionBusy ? t('common.saving') : t('admin.reconciliations.settleBtn') }}
              </button>
            </form>
          </template>
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
.create-form {
    padding: 0.9rem;
    background: #f7f9fa;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}
.row {
    display: flex;
    gap: 0.6rem;
}
.row label,
.settle-form label {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    font-size: 0.8rem;
    color: #555;
}
.row input,
.settle-form input,
.settle-form select {
    padding: 0.5rem 0.6rem;
    border: 1px solid #d0d3d8;
    border-radius: 8px;
    font-size: 0.9rem;
    background: white;
}
.secondary-btn {
    align-self: flex-start;
    padding: 0.5rem 0.9rem;
    border: 1px solid var(--primary-dark);
    border-radius: 8px;
    background: white;
    color: var(--primary-dark);
    font-weight: 600;
    cursor: pointer;
}
.submit-btn {
    padding: 0.6rem;
    border: none;
    border-radius: 8px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    font-weight: 600;
    cursor: pointer;
}
.submit-btn:disabled,
.secondary-btn:disabled {
    opacity: 0.6;
}
.preview-box {
    padding: 0.75rem 0.9rem;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    font-size: 0.85rem;
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}
.preview-box p {
    margin: 0;
}
.payout-split-box {
    margin-top: 0.4rem;
    padding: 0.6rem 0.75rem;
    background: #e6f0fd;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}
.payout-split-box p {
    margin: 0;
    font-size: 0.82rem;
    color: #1a56b0;
}
.payout-split-title {
    font-weight: 600;
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
    padding: 0.4rem 0;
    border-bottom: 1px solid #f0f1f3;
}
.item:last-child {
    border-bottom: none;
}
.item-header {
    width: 100%;
    background: none;
    border: none;
    padding: 0.2rem 0;
    text-align: left;
    cursor: pointer;
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
    color: #333;
}
.item-phone {
    margin: 0.2rem 0 0;
    font-size: 0.78rem;
    color: #888;
}
.badge {
    flex-shrink: 0;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 0.25rem 0.55rem;
    border-radius: 999px;
    background: #eef2f5;
    color: #666;
}
.badge.settled {
    background: #e3f9ef;
    color: #0f9d58;
}
.badge.approved_business, .badge.approved_admin {
    background: #e6f0fd;
    color: #1a56b0;
}
.badge.pending_business {
    background: #fff4e0;
    color: #b7791f;
}
.badge.rejected_business, .badge.rejected_admin {
    background: #fdecea;
    color: #c0392b;
}
.detail {
    margin-top: 0.5rem;
    padding: 0.75rem 0.9rem;
    background: #f7f9fa;
    border-radius: 8px;
    font-size: 0.85rem;
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}
.detail p {
    margin: 0;
}
.events {
    margin-top: 0.4rem;
}
.events-title {
    font-weight: 600;
    margin-bottom: 0.2rem !important;
}
.event-line {
    font-size: 0.78rem;
    color: #888;
}
.actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 0.4rem;
}
.actions .secondary {
    flex: 1;
    padding: 0.5rem 0.7rem;
    border: 1px solid #d0d3d8;
    border-radius: 8px;
    background: white;
    color: #333;
    font-weight: 600;
    font-size: 0.82rem;
    cursor: pointer;
}
.reject-form,
.settle-form {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-top: 0.5rem;
}
.reject-form {
    flex-direction: row;
}
.reject-form input {
    flex: 1;
    padding: 0.5rem 0.6rem;
    border: 1px solid #d0d3d8;
    border-radius: 8px;
    font-size: 0.85rem;
}
.reject-form button,
.settle-form button {
    padding: 0.5rem 0.9rem;
    border: none;
    border-radius: 8px;
    background: var(--primary-dark);
    color: white;
    font-weight: 600;
    cursor: pointer;
}
</style>
