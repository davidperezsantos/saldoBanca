<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { listAccounts } from '../../api/adminAccounts';
import { getReconciliationReport } from '../../api/adminReconciliationReport';
import { currencySymbol } from '../../utils/currency';

const { t } = useI18n();

const periodStart = ref('');
const periodEnd = ref('');
const status = ref('settled');
const filterAccount = ref(null);
const accountSearch = ref('');
const accountResults = ref([]);

const loading = ref(false);
const error = ref('');
const report = ref(null);

const STATUS_LABELS = computed(() => ({
    pending_business: t('admin.reconciliations.statusPendingBusiness'),
    approved_business: t('admin.reconciliations.statusApprovedBusiness'),
    rejected_business: t('admin.reconciliations.statusRejectedBusiness'),
    approved_admin: t('admin.reconciliations.statusApprovedAdmin'),
    rejected_admin: t('admin.reconciliations.statusRejectedAdmin'),
    settled: t('admin.reconciliations.statusSettled'),
}));

async function searchAccounts() {
    if (!accountSearch.value) {
        accountResults.value = [];
        return;
    }
    accountResults.value = await listAccounts({ search: accountSearch.value, accountType: 'business' });
}

function selectAccount(acc) {
    filterAccount.value = { id: acc.id, label: acc.businessName };
    accountResults.value = [];
    accountSearch.value = '';
}

function clearAccount() {
    filterAccount.value = null;
}

async function runReport() {
    error.value = '';
    report.value = null;
    if (!periodStart.value || !periodEnd.value) {
        error.value = t('admin.reconciliations.previewMissingFields');
        return;
    }
    loading.value = true;
    try {
        const filters = { periodStart: periodStart.value, periodEnd: periodEnd.value };
        if (status.value) filters.status = status.value;
        if (filterAccount.value) filters.businessAccountId = filterAccount.value.id;
        report.value = await getReconciliationReport(filters);
    } catch (e) {
        error.value = e.response?.data?.message || t('admin.reconciliations.error');
    } finally {
        loading.value = false;
    }
}
</script>

<template>
  <div class="card">
    <h1>{{ t('admin.reconciliationReport.title') }}</h1>
    <p class="hint">{{ t('admin.reconciliationReport.hint') }}</p>

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
    <select v-model="status">
      <option value="">{{ t('admin.recharges.allStatuses') }}</option>
      <option v-for="(label, key) in STATUS_LABELS" :key="key" :value="key">{{ label }}</option>
    </select>

    <div v-if="filterAccount" class="selected-account">
      <p class="selected-account-name">{{ filterAccount.label }}</p>
      <button type="button" class="link-btn" @click="clearAccount">{{ t('admin.viewAllAccounts') }}</button>
    </div>
    <form v-else class="search-row" @submit.prevent="searchAccounts">
      <input v-model="accountSearch" type="text" :placeholder="t('admin.filterByAccountPlaceholder')" />
      <button type="submit">{{ t('admin.accounts.searchBtn') }}</button>
    </form>
    <ul v-if="accountResults.length" class="account-results">
      <li v-for="acc in accountResults" :key="acc.id">
        <button type="button" class="account-result" @click="selectAccount(acc)">
          <span>{{ acc.businessName }}</span>
          <span class="account-result-sub">{{ acc.accountNumber }}</span>
        </button>
      </li>
    </ul>

    <button type="button" class="submit-btn" :disabled="loading" @click="runReport">
      {{ loading ? t('common.loading') : t('admin.reconciliationReport.runBtn') }}
    </button>
    <p v-if="error" class="error">{{ error }}</p>

    <div v-if="report" class="report">
      <p class="grand-total">
        {{ t('admin.reconciliationReport.grandTotal') }}:
        {{ report.grandTotal.baseAmount }} {{ currencySymbol(report.baseCurrency) }}
        <template v-if="report.secondaryCurrency"> / {{ report.grandTotal.secondaryAmount }} {{ currencySymbol(report.secondaryCurrency) }}</template>
      </p>

      <p v-if="!report.businesses.length" class="empty">{{ t('admin.reconciliationReport.empty') }}</p>
      <div v-for="b in report.businesses" :key="b.businessName" class="business-block">
        <p class="business-name">{{ b.businessName }}</p>
        <p class="business-total">{{ t('admin.reconciliations.previewNet') }}: {{ b.netTotal }} {{ b.defaultCurrency }}</p>
        <ul class="list">
          <li v-for="row in b.rows" :key="row.reconciliationNumber" class="item">
            <div class="item-row">
              <p class="item-title">{{ row.reconciliationNumber }}</p>
              <span class="badge" :class="row.status">{{ STATUS_LABELS[row.status] ?? row.status }}</span>
            </div>
            <p class="item-phone">{{ row.periodStart }} — {{ row.periodEnd }} · {{ row.netAmount }} {{ currencySymbol(row.currency) }}</p>
          </li>
        </ul>
      </div>
    </div>
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
.row {
    display: flex;
    gap: 0.6rem;
}
.row label {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    font-size: 0.8rem;
    color: #555;
}
.row input,
select {
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
.submit-btn {
    padding: 0.6rem;
    border: none;
    border-radius: 8px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    font-weight: 600;
    cursor: pointer;
}
.submit-btn:disabled {
    opacity: 0.6;
}
.error {
    color: #c0392b;
    font-size: 0.85rem;
}
.empty {
    color: #888;
    font-size: 0.9rem;
}
.report {
    margin-top: 0.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
}
.grand-total {
    margin: 0;
    font-weight: 700;
    color: var(--primary-dark);
}
.business-block {
    padding: 0.75rem 0.9rem;
    background: #f7f9fa;
    border-radius: 8px;
}
.business-name {
    margin: 0;
    font-weight: 700;
}
.business-total {
    margin: 0.15rem 0 0.5rem;
    font-size: 0.82rem;
    color: #666;
}
.list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.item {
    padding: 0.4rem 0;
    border-bottom: 1px solid #e5e7eb;
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
    font-size: 0.85rem;
}
.item-phone {
    margin: 0.2rem 0 0;
    font-size: 0.76rem;
    color: #888;
}
.badge {
    flex-shrink: 0;
    font-size: 0.68rem;
    font-weight: 600;
    padding: 0.2rem 0.5rem;
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
</style>
