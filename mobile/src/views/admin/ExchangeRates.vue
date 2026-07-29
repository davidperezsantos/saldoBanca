<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { hasScope } from '../../api/permissions';
import { listExchangeRates, fetchExchangeRates, createManualExchangeRate } from '../../api/adminExchange';
import { useActiveCurrencies, loadActiveCurrencies } from '../../composables/currencies';

const { t } = useI18n();
const { activeCurrencies } = useActiveCurrencies();

const loading = ref(true);
const error = ref('');
const rates = ref([]);

const canFetch = ref(false);

const fetching = ref(false);
const fetchMessage = ref('');
const fetchError = ref('');

const showManualForm = ref(false);
const manualSaving = ref(false);
const manualError = ref('');
const manualForm = ref({ toCurrency: '', rate: '', locked: false });

function toggleManualForm() {
    showManualForm.value = !showManualForm.value;
    manualError.value = '';
    if (!showManualForm.value) {
        manualForm.value = { toCurrency: '', rate: '', locked: false };
    }
}

async function submitManual() {
    manualError.value = '';
    manualSaving.value = true;
    try {
        await createManualExchangeRate(manualForm.value);
        showManualForm.value = false;
        manualForm.value = { toCurrency: '', rate: '', locked: false };
        await load();
    } catch (e) {
        manualError.value = e.response?.data?.message || t('admin.exchangeRates.saveError');
    } finally {
        manualSaving.value = false;
    }
}

async function load() {
    loading.value = true;
    error.value = '';
    try {
        rates.value = await listExchangeRates();
    } catch (e) {
        error.value = e.response?.data?.message || t('admin.exchangeRates.error');
    } finally {
        loading.value = false;
    }
}

async function refreshRates() {
    fetchMessage.value = '';
    fetchError.value = '';
    fetching.value = true;
    try {
        const result = await fetchExchangeRates();
        fetchMessage.value = result.message;
        await load();
    } catch (e) {
        fetchError.value = e.response?.data?.message || t('admin.exchangeRates.fetchError');
    } finally {
        fetching.value = false;
    }
}

onMounted(async () => {
    canFetch.value = await hasScope('exchange_rates_admin.fetch');
    await load();
    if (canFetch.value) {
        loadActiveCurrencies();
    }
});
</script>

<template>
  <div class="card">
    <div class="header-row">
      <h1>{{ t('admin.exchangeRates.title') }}</h1>
    </div>
    <p class="hint">{{ t('admin.exchangeRates.hint') }}</p>

    <div v-if="canFetch" class="fetch-row">
      <button type="button" class="secondary" :disabled="fetching" @click="refreshRates">
        {{ fetching ? t('common.saving') : t('admin.exchangeRates.fetchBtn') }}
      </button>
      <button type="button" class="link-btn" @click="toggleManualForm">
        {{ showManualForm ? t('common.cancel') : t('admin.exchangeRates.manualBtn') }}
      </button>
    </div>
    <p v-if="fetchMessage" class="success">{{ fetchMessage }}</p>
    <p v-if="fetchError" class="error">{{ fetchError }}</p>

    <form v-if="showManualForm" class="edit-form create-form" @submit.prevent="submitManual">
      <label>
        {{ t('admin.exchangeRates.toCurrencyLabel') }}
        <select v-model="manualForm.toCurrency" required>
          <option v-for="c in activeCurrencies" :key="c.code" :value="c.code">{{ c.code }} - {{ c.name }}</option>
        </select>
      </label>
      <label>
        {{ t('admin.exchangeRates.rateLabel') }}
        <input v-model="manualForm.rate" type="number" step="0.0001" required />
      </label>
      <label class="checkbox-row">
        <input type="checkbox" v-model="manualForm.locked" />
        {{ t('admin.exchangeRates.lockedLabel') }}
      </label>
      <p v-if="manualError" class="error">{{ manualError }}</p>
      <button type="submit" :disabled="manualSaving">
        {{ manualSaving ? t('common.saving') : t('common.save') }}
      </button>
    </form>

    <p v-if="loading">{{ t('common.loading') }}</p>
    <p v-else-if="error" class="error">{{ error }}</p>
    <p v-else-if="!rates.length" class="empty">{{ t('admin.exchangeRates.empty') }}</p>

    <ul v-else class="list">
      <li v-for="(rate, idx) in rates" :key="idx" class="item">
        <div class="item-row">
          <p class="item-title">{{ rate.fromCurrency }} → {{ rate.toCurrency }}</p>
          <span v-if="rate.isLocked" class="badge">{{ t('admin.exchangeRates.locked') }}</span>
        </div>
        <p class="item-phone">{{ rate.providerName }} · {{ rate.fetchedAt }}</p>
        <p class="rate-value">{{ rate.rate }}</p>
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
    margin: 0 0 0.5rem;
    font-size: 0.82rem;
    color: #777;
}
.fetch-row {
    display: flex;
    gap: 0.6rem;
    align-items: center;
    margin-bottom: 0.3rem;
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
    margin-bottom: 0.5rem;
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
.checkbox-row {
    flex-direction: row !important;
    align-items: center;
    gap: 0.4rem !important;
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
.rate-value {
    margin: 0.3rem 0 0;
    font-size: 0.95rem;
    font-weight: 700;
    color: #333;
}
.badge {
    flex-shrink: 0;
    font-size: 0.72rem;
    font-weight: 600;
    padding: 0.25rem 0.55rem;
    border-radius: 999px;
    background: #fff4e0;
    color: #b7791f;
}

.secondary {
    padding: 0.5rem 0.9rem;
    border: 1px solid #d0d3d8;
    border-radius: 8px;
    background: white;
    color: #333;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
}
.secondary:disabled {
    opacity: 0.6;
}
</style>
