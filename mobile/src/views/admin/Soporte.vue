<script setup>
import { ref, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { hasScope } from '../../api/permissions';
import { roleVersion } from '../../composables/role';

const { t } = useI18n();

const canAccounts = ref(false);
const canAuthorized = ref(false);
const canExchangeProviders = ref(false);
const canExchangeRates = ref(false);
const canCurrencies = ref(false);

async function load() {
    canAccounts.value = await hasScope('accounts_admin.read');
    canAuthorized.value = await hasScope('authorized_admin.read');
    canExchangeProviders.value = await hasScope('exchange_providers_admin.read');
    canExchangeRates.value = await hasScope('exchange_rates_admin.read');
    canCurrencies.value = await hasScope('currencies_admin.read');
}

onMounted(load);
watch(roleVersion, load);
</script>

<template>
  <div class="stack">
    <div class="card">
      <h1>{{ t('admin.soporte.title') }}</h1>
      <p class="hint">{{ t('admin.soporte.hint') }}</p>
      <div class="cards-grid">
        <router-link v-if="canAccounts" to="/cuentas" class="dash-card">
          <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <path d="M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM12 14a7 7 0 0 0-7 7h14a7 7 0 0 0-7-7Z" />
          </svg>
          <span>{{ t('admin.soporte.accounts') }}</span>
        </router-link>
        <router-link v-if="canAuthorized" to="/autorizados-admin" class="dash-card">
          <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 12l2 2 4-4m5.62-4.02A11.96 11.96 0 0 1 12 2.94a11.96 11.96 0 0 1-8.62 3.04A12.02 12.02 0 0 0 3 9c0 5.59 3.82 10.29 9 11.62 5.18-1.33 9-6.03 9-11.62 0-1.04-.13-2.05-.38-3.02Z" />
          </svg>
          <span>{{ t('admin.soporte.authorized') }}</span>
        </router-link>
        <router-link v-if="canExchangeProviders" to="/exchange-providers" class="dash-card">
          <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <path d="M7 16V4M7 4 3 8m4-4 4 4m6 0v12m0 0 4-4m-4 4-4-4" />
          </svg>
          <span>{{ t('admin.soporte.exchangeProviders') }}</span>
        </router-link>
        <router-link v-if="canExchangeRates" to="/exchange-rates" class="dash-card">
          <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
          </svg>
          <span>{{ t('admin.soporte.exchangeRates') }}</span>
        </router-link>
        <router-link v-if="canCurrencies" to="/currencies" class="dash-card">
          <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="9" />
            <path d="M9.5 9.5c0-1 .9-1.8 2.5-1.8s2.5.8 2.5 1.8-1 1.5-2.5 1.5-2.5.6-2.5 1.7 1 1.8 2.5 1.8 2.5-.7 2.5-1.8M12 6.5v11" />
          </svg>
          <span>{{ t('admin.soporte.currencies') }}</span>
        </router-link>
      </div>
      <p v-if="!canAccounts && !canAuthorized && !canExchangeProviders && !canExchangeRates && !canCurrencies" class="empty">
        {{ t('admin.soporte.empty') }}
      </p>
    </div>
  </div>
</template>

<style scoped>
.stack {
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
}
.card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
}
h1 {
    margin: 0 0 0.3rem;
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--primary-dark);
}
.hint {
    margin: 0 0 1rem;
    font-size: 0.82rem;
    color: #777;
}
.empty {
    color: #888;
    font-size: 0.9rem;
}
.cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(96px, 1fr));
    gap: 0.75rem;
}
.dash-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 1rem 0.5rem;
    border-radius: 12px;
    background: #f7f9fa;
    color: var(--primary-dark);
    text-decoration: none;
    font-size: 0.78rem;
    font-weight: 600;
    text-align: center;
}
</style>
