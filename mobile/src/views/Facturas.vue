<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { apiClient } from '../api/client';
import { useAccount, loadAccount } from '../composables/account';
import { currencySymbol } from '../utils/currency';

const { t } = useI18n();
const { account } = useAccount();
const loading = ref(true);
const error = ref('');
const invoices = ref([]);

const payingId = ref(null);
const pinRequested = ref(false);
const pinCode = ref('');
const payError = ref('');
const paySuccess = ref('');
const payBusy = ref(false);

const STATUS_LABELS = computed(() => ({
    pending: t('invoices.statusPending'),
    paid: t('invoices.statusPaid'),
    cancelled: t('invoices.statusCancelled'),
    refunded: t('invoices.statusRefunded'),
}));

function hasTax(invoice) {
    return invoice.taxAmount && parseFloat(invoice.taxAmount) > 0;
}

// El monto "real" de la factura es originalAmount/originalCurrency (lo que se tecleó al
// crearla); amount/taxAmount/totalAmount ya están convertidos a la moneda de la cuenta. Si no
// hay originalCurrency (se creó directo en la moneda de la cuenta) no hay conversión que
// mostrar aparte.
function hasConversion(invoice) {
    return invoice.originalCurrency && invoice.originalCurrency !== invoice.currency;
}

async function load() {
    loading.value = true;
    error.value = '';
    try {
        await loadAccount();
        const { data } = await apiClient.get('/invoices');
        invoices.value = data.data;
    } catch (e) {
        error.value = e.response?.data?.message || t('invoices.error');
    } finally {
        loading.value = false;
    }
}

function startPay(invoice) {
    payingId.value = invoice.id;
    pinRequested.value = false;
    pinCode.value = '';
    payError.value = '';
    paySuccess.value = '';
}

function cancelPay() {
    payingId.value = null;
}

async function requestPin() {
    payError.value = '';
    payBusy.value = true;
    try {
        await apiClient.post(`/accounts/${account.value.id}/request-pin`);
        pinRequested.value = true;
    } catch (e) {
        payError.value = e.response?.data?.message || t('invoices.pinError');
    } finally {
        payBusy.value = false;
    }
}

async function confirmPay() {
    payError.value = '';
    payBusy.value = true;
    try {
        await apiClient.put(`/invoices/${payingId.value}/pay`, { pinCode: pinCode.value });
        paySuccess.value = t('invoices.paySuccess');
        payingId.value = null;
        await load();
    } catch (e) {
        payError.value = e.response?.data?.message || t('invoices.payError');
    } finally {
        payBusy.value = false;
    }
}

onMounted(load);
</script>

<template>
  <div class="stack">
    <div class="card">
      <h2>{{ t('invoices.title') }}</h2>
      <p v-if="loading">{{ t('common.loading') }}</p>
      <p v-else-if="error" class="error">{{ error }}</p>
      <p v-else-if="!invoices.length" class="empty">{{ t('invoices.empty') }}</p>
      <ul v-else class="list">
        <li v-for="i in invoices" :key="i.id" class="item">
          <div class="item-row">
            <div class="item-info">
              <p class="item-title">{{ t('invoices.number', { number: i.invoiceNumber }) }}</p>
              <p v-if="i.businessAccountName" class="item-sub">{{ t('invoices.issuedBy', { name: i.businessAccountName }) }}</p>
              <p class="item-sub">{{ t('invoices.issuedOn', { date: i.invoiceDate }) }}</p>
              <p v-if="i.dueDate" class="item-sub">{{ t('invoices.dueOn', { date: i.dueDate }) }}</p>
              <p v-if="i.paymentDate" class="item-sub">{{ t('invoices.paidOn', { date: i.paymentDate }) }}</p>
            </div>
            <span class="badge" :class="i.status">{{ STATUS_LABELS[i.status] ?? i.status }}</span>
          </div>

          <div class="amounts">
            <div v-if="hasTax(i)" class="amount-row">
              <span>{{ t('invoices.subtotal') }}</span>
              <span>{{ i.amount }} {{ currencySymbol(i.currency) }}</span>
            </div>
            <div v-if="hasTax(i)" class="amount-row">
              <span>{{ t('invoices.tax') }}</span>
              <span>{{ i.taxAmount }} {{ currencySymbol(i.currency) }}</span>
            </div>
            <div class="amount-row total">
              <span>{{ t('invoices.total') }}</span>
              <span>
                <template v-if="hasConversion(i)">{{ i.originalAmount }} {{ currencySymbol(i.originalCurrency) }}</template>
                <template v-else>{{ i.totalAmount }} {{ currencySymbol(i.currency) }}</template>
              </span>
            </div>
            <div v-if="hasConversion(i)" class="amount-row alt">
              <span>≈</span>
              <span>{{ i.totalAmount }} {{ currencySymbol(i.currency) }}</span>
            </div>
          </div>

          <button v-if="i.status === 'pending' && payingId !== i.id" class="pay-btn" @click="startPay(i)">
            {{ t('invoices.pay') }}
          </button>

          <div v-if="payingId === i.id" class="pay-box">
            <template v-if="!pinRequested">
              <p class="hint">{{ t('invoices.pinIntro') }}</p>
              <div class="pay-actions">
                <button class="secondary" @click="cancelPay">{{ t('common.cancel') }}</button>
                <button :disabled="payBusy" @click="requestPin">
                  {{ payBusy ? t('invoices.sendingCode') : t('invoices.sendCode') }}
                </button>
              </div>
            </template>
            <template v-else>
              <label>
                {{ t('invoices.codeLabel') }}
                <input v-model="pinCode" type="text" inputmode="numeric" placeholder="0000" />
              </label>
              <div class="pay-actions">
                <button class="secondary" @click="cancelPay">{{ t('common.cancel') }}</button>
                <button :disabled="payBusy || !pinCode" @click="confirmPay">
                  {{ payBusy ? t('invoices.confirmingPay') : t('invoices.confirmPay') }}
                </button>
              </div>
            </template>
            <p v-if="payError" class="error">{{ payError }}</p>
          </div>
        </li>
      </ul>
      <p v-if="paySuccess" class="success">{{ paySuccess }}</p>
    </div>
  </div>
</template>

<style scoped>
.stack {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.card {
    background: white;
    border-radius: 12px;
    padding: 1.25rem;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
}
h2 {
    margin: 0 0 0.75rem;
    font-size: 1.05rem;
}
.empty {
    color: #888;
    font-size: 0.9rem;
}
.error {
    color: #c0392b;
    font-size: 0.85rem;
}
.success {
    color: #0f9d58;
    font-size: 0.85rem;
    margin-top: 0.75rem;
}
.list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
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
.item-info {
    flex: 1;
    min-width: 0;
}
.item-title {
    margin: 0;
    font-weight: 600;
}
.item-sub {
    margin: 0.1rem 0 0;
    font-size: 0.75rem;
    color: #888;
    overflow-wrap: break-word;
}
.badge {
    flex-shrink: 0;
    font-size: 0.72rem;
    font-weight: 600;
    padding: 0.25rem 0.55rem;
    border-radius: 999px;
    background: #eef2f5;
    color: #666;
    text-transform: capitalize;
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
.amounts {
    margin-top: 0.5rem;
    padding-top: 0.5rem;
    border-top: 1px dashed #eee;
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}
.amount-row {
    display: flex;
    justify-content: space-between;
    font-size: 0.78rem;
    color: #888;
}
.amount-row.total {
    font-size: 0.92rem;
    font-weight: 700;
    color: var(--primary-dark);
}
.pay-btn {
    margin-top: 0.5rem;
    padding: 0.45rem 0.9rem;
    border: none;
    border-radius: 8px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
}
.pay-box {
    margin-top: 0.6rem;
    padding: 0.75rem;
    background: #f7f9fa;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}
.hint {
    margin: 0;
    font-size: 0.82rem;
    color: #555;
}
.pay-box label {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    font-size: 0.82rem;
    color: #333;
}
.pay-box input {
    padding: 0.55rem 0.65rem;
    border: 1px solid #d0d3d8;
    border-radius: 8px;
    font-size: 1rem;
    letter-spacing: 0.1em;
}
.pay-actions {
    display: flex;
    gap: 0.5rem;
}
.pay-actions button {
    flex: 1;
    padding: 0.5rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
}
.pay-actions button:disabled {
    opacity: 0.6;
}
.pay-actions button.secondary {
    background: white;
    border: 1px solid #d0d3d8;
    color: #333;
}
</style>
