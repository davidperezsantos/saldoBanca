<script setup>
import { ref, computed, onMounted, reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import { hasScope } from '../../api/permissions';
import { useUser } from '../../composables/user';
import {
    listCommissionSettlements,
    createCommissionSettlement,
    getAvailableCommission,
    getCommissionSettlement,
    requestCommissionSettlementPin,
    verifyCommissionSettlementPin,
    approveCommissionSettlement,
    assignCommissionSettlementAccount,
    settleCommissionSettlement,
    closeCommissionSettlement,
} from '../../api/adminCommissionSettlements';
import { useActiveCurrencies, loadActiveCurrencies } from '../../composables/currencies';
import { currencySymbol } from '../../utils/currency';

const { t } = useI18n();
const { user } = useUser();
const { activeCurrencies } = useActiveCurrencies();

const SETTLEMENT_METHODS = ['efectivo', 'transferencia'];

const canCreate = ref(false);
const canApprove = ref(false);
const canAssignAccount = ref(false);
const canSettle = ref(false);
const canClose = ref(false);

const loading = ref(true);
const error = ref('');
const settlements = ref([]);
const status = ref('');
const currency = ref('');

const STATUS_LABELS = computed(() => ({
    pending_admin_approval: t('admin.commissionSettlements.statusPendingApproval'),
    approved_admin: t('admin.commissionSettlements.statusApprovedAdmin'),
    pending_settlement: t('admin.commissionSettlements.statusPendingSettlement'),
    settled: t('admin.commissionSettlements.statusSettled'),
    closed: t('admin.commissionSettlements.statusClosed'),
}));

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const filters = {};
        if (status.value) filters.status = status.value;
        if (currency.value) filters.currency = currency.value;
        settlements.value = await listCommissionSettlements(filters);
    } catch (e) {
        error.value = e.response?.data?.message || t('admin.commissionSettlements.error');
    } finally {
        loading.value = false;
    }
}

// --- crear ---
const showCreateForm = ref(false);
const createForm = reactive({ amount: '', currency: 'USD', notes: '' });
const createSaving = ref(false);
const createError = ref('');
const available = ref(null);
const loadingAvailable = ref(false);

function toggleCreateForm() {
    showCreateForm.value = !showCreateForm.value;
    createForm.amount = '';
    createForm.currency = 'USD';
    createForm.notes = '';
    createError.value = '';
    available.value = null;
    if (showCreateForm.value) {
        loadActiveCurrencies();
        loadAvailable();
    }
}

async function loadAvailable() {
    if (!createForm.currency) {
        available.value = null;
        return;
    }
    loadingAvailable.value = true;
    try {
        available.value = await getAvailableCommission(createForm.currency);
    } catch (e) {
        available.value = null;
    } finally {
        loadingAvailable.value = false;
    }
}

async function submitCreate() {
    createError.value = '';
    if (available.value && Number(createForm.amount) > Number(available.value.available)) {
        createError.value = t('admin.commissionSettlements.amountExceedsAvailable');
        return;
    }
    createSaving.value = true;
    try {
        await createCommissionSettlement({ ...createForm, createdBy: user.value?.username });
        toggleCreateForm();
        await load();
    } catch (e) {
        createError.value = e.response?.data?.message || t('admin.commissionSettlements.saveError');
    } finally {
        createSaving.value = false;
    }
}

// --- detalle + PIN + acciones ---
const selectedId = ref(null);
const detail = ref(null);
const detailLoading = ref(false);
const detailError = ref('');
const pinRequested = ref(false);
const pinCode = ref('');
const pinVerified = ref(false);
const actionBusy = ref(false);
const actionError = ref('');
const settleForm = reactive({ settlementMethod: '', settlementReference: '' });
const assignForm = reactive({ payoutAccountNumber: '', payoutBankName: '', payoutAccountHolder: '' });

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
    pinRequested.value = false;
    pinVerified.value = false;
    pinCode.value = '';
    detailLoading.value = true;
    try {
        detail.value = await getCommissionSettlement(id);
    } catch (e) {
        detailError.value = e.response?.data?.message || t('admin.commissionSettlements.error');
    } finally {
        detailLoading.value = false;
    }
}

async function doRequestPin() {
    actionBusy.value = true;
    actionError.value = '';
    try {
        await requestCommissionSettlementPin(selectedId.value, user.value?.username);
        pinRequested.value = true;
    } catch (e) {
        actionError.value = e.response?.data?.message || t('admin.commissionSettlements.actionError');
    } finally {
        actionBusy.value = false;
    }
}

async function doVerifyPin() {
    actionBusy.value = true;
    actionError.value = '';
    try {
        await verifyCommissionSettlementPin(selectedId.value, user.value?.username, pinCode.value);
        pinVerified.value = true;
    } catch (e) {
        actionError.value = e.response?.data?.message || t('admin.commissionSettlements.actionError');
    } finally {
        actionBusy.value = false;
    }
}

async function doApprove() {
    actionBusy.value = true;
    actionError.value = '';
    try {
        detail.value = await approveCommissionSettlement(selectedId.value, user.value?.username);
        pinRequested.value = false;
        pinVerified.value = false;
        await load();
    } catch (e) {
        actionError.value = e.response?.data?.message || t('admin.commissionSettlements.actionError');
    } finally {
        actionBusy.value = false;
    }
}

async function doAssignAccount() {
    actionBusy.value = true;
    actionError.value = '';
    try {
        detail.value = await assignCommissionSettlementAccount(selectedId.value, { ...assignForm, performedBy: user.value?.username });
        pinRequested.value = false;
        pinVerified.value = false;
        await load();
    } catch (e) {
        actionError.value = e.response?.data?.message || t('admin.commissionSettlements.actionError');
    } finally {
        actionBusy.value = false;
    }
}

async function doSettle() {
    actionBusy.value = true;
    actionError.value = '';
    try {
        detail.value = await settleCommissionSettlement(selectedId.value, { ...settleForm, performedBy: user.value?.username });
        pinRequested.value = false;
        pinVerified.value = false;
        await load();
    } catch (e) {
        actionError.value = e.response?.data?.message || t('admin.commissionSettlements.actionError');
    } finally {
        actionBusy.value = false;
    }
}

async function doClose() {
    actionBusy.value = true;
    actionError.value = '';
    try {
        detail.value = await closeCommissionSettlement(selectedId.value, user.value?.username);
        pinRequested.value = false;
        pinVerified.value = false;
        await load();
    } catch (e) {
        actionError.value = e.response?.data?.message || t('admin.commissionSettlements.actionError');
    } finally {
        actionBusy.value = false;
    }
}

onMounted(async () => {
    canCreate.value = await hasScope('commission_settlements.create');
    canApprove.value = await hasScope('commission_settlements.approve');
    canAssignAccount.value = await hasScope('commission_settlements.assign_account');
    canSettle.value = await hasScope('commission_settlements.settle');
    canClose.value = await hasScope('commission_settlements.close');
    await load();
    loadActiveCurrencies();
});
</script>

<template>
  <div class="card">
    <div class="header-row">
      <h1>{{ t('admin.commissionSettlements.title') }}</h1>
      <button v-if="canCreate" class="link-btn" @click="toggleCreateForm">
        {{ showCreateForm ? t('common.cancel') : t('admin.commissionSettlements.newBtn') }}
      </button>
    </div>
    <p class="hint">{{ t('admin.commissionSettlements.hint') }}</p>

    <form v-if="showCreateForm" class="create-form" @submit.prevent="submitCreate">
      <div class="row">
        <label>
          {{ t('admin.commissionSettlements.amountLabel') }}
          <input v-model="createForm.amount" type="number" step="0.01" required />
        </label>
        <label>
          {{ t('admin.commissionSettlements.currencyLabel') }}
          <select v-model="createForm.currency" required @change="loadAvailable">
            <option v-for="c in activeCurrencies" :key="c.code" :value="c.code">{{ c.code }} - {{ c.name }}</option>
          </select>
        </label>
      </div>
      <p v-if="loadingAvailable" class="hint">{{ t('common.loading') }}</p>
      <p v-else-if="available" class="available-hint">
        {{ t('admin.commissionSettlements.availableLabel') }}: {{ available.available }} {{ currencySymbol(available.currency) }}
      </p>
      <label>
        {{ t('admin.commissionSettlements.notesLabel') }}
        <input v-model="createForm.notes" type="text" />
      </label>
      <p v-if="createError" class="error">{{ createError }}</p>
      <button type="submit" class="submit-btn" :disabled="createSaving">
        {{ createSaving ? t('common.saving') : t('common.save') }}
      </button>
    </form>

    <div class="filters">
      <select v-model="status" @change="load">
        <option value="">{{ t('admin.recharges.allStatuses') }}</option>
        <option v-for="(label, key) in STATUS_LABELS" :key="key" :value="key">{{ label }}</option>
      </select>
      <select v-model="currency" @change="load">
        <option value="">{{ t('admin.commissionSettlements.allCurrencies') }}</option>
        <option v-for="c in activeCurrencies" :key="c.code" :value="c.code">{{ c.code }}</option>
      </select>
    </div>

    <p v-if="loading">{{ t('common.loading') }}</p>
    <p v-else-if="error" class="error">{{ error }}</p>
    <p v-else-if="!settlements.length" class="empty">{{ t('admin.commissionSettlements.empty') }}</p>
    <ul v-else class="list">
      <li v-for="s in settlements" :key="s.id" class="item">
        <button type="button" class="item-header" @click="openDetail(s.id)">
          <div class="item-row">
            <p class="item-title">{{ s.settlementNumber }} · {{ s.amount }} {{ currencySymbol(s.currency) }}</p>
            <span class="badge" :class="s.status">{{ STATUS_LABELS[s.status] ?? s.status }}</span>
          </div>
          <p class="item-phone">{{ s.createdBy }} · {{ s.createdAt }}</p>
        </button>

        <div v-if="selectedId === s.id" class="detail">
          <p v-if="detailLoading">{{ t('common.loading') }}</p>
          <p v-else-if="detailError" class="error">{{ detailError }}</p>
          <template v-else-if="detail">
            <p v-if="detail.payoutAccountNumber">{{ t('admin.commissionSettlements.payoutAccount') }}: {{ detail.payoutBankName }} · {{ detail.payoutAccountNumber }}</p>
            <p v-if="detail.settlementMethod">{{ t('admin.reconciliations.settlementMethod') }}: {{ detail.settlementMethod }} ({{ detail.settlementReference }})</p>

            <p v-if="actionError" class="error">{{ actionError }}</p>

            <template v-if="['pending_admin_approval', 'approved_admin', 'pending_settlement', 'settled'].includes(detail.status) && (canApprove || canAssignAccount || canSettle || canClose)">
              <div v-if="!pinVerified" class="pin-box">
                <button v-if="!pinRequested" type="button" class="secondary" :disabled="actionBusy" @click="doRequestPin">
                  {{ t('admin.commissionSettlements.requestPinBtn') }}
                </button>
                <form v-else class="pin-form" @submit.prevent="doVerifyPin">
                  <input v-model="pinCode" type="text" inputmode="numeric" :placeholder="t('admin.commissionSettlements.pinPlaceholder')" />
                  <button type="submit" :disabled="actionBusy">{{ t('admin.commissionSettlements.verifyPinBtn') }}</button>
                </form>
              </div>

              <div v-else class="actions">
                <button v-if="canApprove && detail.status === 'pending_admin_approval'" class="secondary" :disabled="actionBusy" @click="doApprove">
                  {{ t('admin.commissionSettlements.approveBtn') }}
                </button>
                <button v-if="canSettle && detail.status === 'approved_admin'" class="secondary" :disabled="actionBusy" @click="doSettle">
                  {{ t('admin.commissionSettlements.settleBtn') }}
                </button>
                <button v-if="canClose && detail.status === 'settled'" class="secondary" :disabled="actionBusy" @click="doClose">
                  {{ t('admin.commissionSettlements.closeBtn') }}
                </button>
              </div>

              <form v-if="pinVerified && canAssignAccount && detail.status === 'approved_admin'" class="assign-form" @submit.prevent="doAssignAccount">
                <input v-model="assignForm.payoutAccountNumber" type="text" :placeholder="t('admin.commissionSettlements.accountNumberPlaceholder')" required />
                <input v-model="assignForm.payoutBankName" type="text" :placeholder="t('admin.commissionSettlements.bankNamePlaceholder')" />
                <input v-model="assignForm.payoutAccountHolder" type="text" :placeholder="t('admin.commissionSettlements.accountHolderPlaceholder')" />
                <button type="submit" :disabled="actionBusy">{{ t('admin.commissionSettlements.assignAccountBtn') }}</button>
              </form>

              <form v-if="pinVerified && canSettle && detail.status === 'pending_settlement'" class="assign-form" @submit.prevent="doSettle">
                <select v-model="settleForm.settlementMethod" required>
                  <option value="" disabled>{{ t('admin.reconciliations.settlementMethod') }}</option>
                  <option v-for="m in SETTLEMENT_METHODS" :key="m" :value="m">{{ t(`admin.reconciliations.method_${m}`) }}</option>
                </select>
                <input v-if="settleForm.settlementMethod === 'transferencia'" v-model="settleForm.settlementReference" type="text" :placeholder="t('admin.reconciliations.settlementReference')" required />
                <button type="submit" :disabled="actionBusy">{{ t('admin.commissionSettlements.settleBtn') }}</button>
              </form>
            </template>
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
.create-form label {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    font-size: 0.8rem;
    color: #555;
}
.row input,
.row select,
.create-form input {
    padding: 0.5rem 0.6rem;
    border: 1px solid #d0d3d8;
    border-radius: 8px;
    font-size: 0.9rem;
    background: white;
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
.filters {
    display: flex;
    gap: 0.5rem;
}
.filters select,
.filters input {
    flex: 1;
    padding: 0.5rem 0.6rem;
    border: 1px solid #d0d3d8;
    border-radius: 8px;
    font-size: 0.9rem;
    background: white;
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
.badge.settled, .badge.closed {
    background: #e3f9ef;
    color: #0f9d58;
}
.badge.approved_admin, .badge.pending_settlement {
    background: #e6f0fd;
    color: #1a56b0;
}
.badge.pending_admin_approval {
    background: #fff4e0;
    color: #b7791f;
}
.detail {
    margin-top: 0.5rem;
    padding: 0.75rem 0.9rem;
    background: #f7f9fa;
    border-radius: 8px;
    font-size: 0.85rem;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}
.detail p {
    margin: 0;
}
.pin-box {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}
.pin-form {
    display: flex;
    gap: 0.5rem;
}
.pin-form input {
    flex: 1;
    padding: 0.5rem 0.6rem;
    border: 1px solid #d0d3d8;
    border-radius: 8px;
    font-size: 0.9rem;
}
.actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.actions .secondary,
.pin-form button,
.pin-box > button {
    padding: 0.5rem 0.7rem;
    border: 1px solid #d0d3d8;
    border-radius: 8px;
    background: white;
    color: #333;
    font-weight: 600;
    font-size: 0.82rem;
    cursor: pointer;
}
.assign-form {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.assign-form input,
.assign-form select {
    padding: 0.5rem 0.6rem;
    border: 1px solid #d0d3d8;
    border-radius: 8px;
    font-size: 0.85rem;
    background: white;
}
.available-hint {
    margin: 0;
    font-size: 0.8rem;
    color: #0f9d58;
    font-weight: 600;
}
.assign-form button {
    padding: 0.5rem 0.9rem;
    border: none;
    border-radius: 8px;
    background: var(--primary-dark);
    color: white;
    font-weight: 600;
    cursor: pointer;
}
</style>
