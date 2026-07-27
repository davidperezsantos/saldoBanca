<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { apiClient } from '../api/client';
import { useAccount, loadAccount } from '../composables/account';
import { useUser } from '../composables/user';
import Sparkline from '../components/Sparkline.vue';
import Barcode from '../components/Barcode.vue';
import { currencySymbol } from '../utils/currency';

const BARCODE_PREFIX = 'SG-';

const { t } = useI18n();
const { account } = useAccount();
const { user } = useUser();
const loading = ref(true);
const error = ref('');
const balance = ref(null);
const baseBalance = ref(null);
const movements = ref([]);

const BAR_COLORS = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100'];

function firstName(name) {
    return name ? name.trim().split(/\s+/)[0] : '';
}

function categoryLabel(referenceType) {
    const labels = {
        invoice: t('account.categoryInvoices'),
        transfer: t('account.categoryTransfers'),
        recharge: t('account.categoryRecharges'),
    };
    return labels[referenceType] ?? t('account.categoryOther');
}

const sparklineValues = computed(() => movements.value.map((m) => parseFloat(m.balanceAfter)));

const barcodeValue = computed(() =>
    account.value ? BARCODE_PREFIX + account.value.accountNumber : ''
);

const balanceDelta = computed(() => {
    if (!movements.value.length || !balance.value) return null;
    const oldestBefore = parseFloat(movements.value[0].balanceBefore);
    return parseFloat(balance.value.available) - oldestBefore;
});

const spendByCategory = computed(() => {
    const totals = {};
    for (const m of movements.value) {
        const amount = parseFloat(m.amount);
        if (amount >= 0) continue;
        const label = categoryLabel(m.referenceType);
        totals[label] = (totals[label] ?? 0) + Math.abs(amount);
    }
    const ranked = Object.entries(totals)
        .map(([label, total]) => ({ label, total }))
        .sort((a, b) => b.total - a.total)
        .slice(0, 4);
    const max = ranked[0]?.total || 1;
    return ranked.map((item, i) => ({
        ...item,
        color: BAR_COLORS[i],
        widthPct: Math.max(6, (item.total / max) * 100),
    }));
});

async function load() {
    loading.value = true;
    error.value = '';
    try {
        await loadAccount();
        if (account.value) {
            // Sin ?currency= devuelve el saldo en la moneda BASE del sistema — la usamos para
            // saber cuál es esa moneda, y de paso queda como fallback si la cuenta ya está en
            // esa misma moneda (no hace falta pedir una segunda vez).
            const { data: baseRes } = await apiClient.get(`/balance/${account.value.id}`);
            baseBalance.value = baseRes.data;

            if (account.value.defaultCurrency && account.value.defaultCurrency !== baseRes.data.currency) {
                const { data: ownRes } = await apiClient.get(`/balance/${account.value.id}`, {
                    params: { currency: account.value.defaultCurrency },
                });
                balance.value = ownRes.data;
            } else {
                balance.value = baseRes.data;
            }

            const dateFrom = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000)
                .toISOString()
                .slice(0, 19)
                .replace('T', ' ');
            const { data: historyRes } = await apiClient.get('/history', {
                params: { accountId: account.value.id, dateFrom, limit: 200 },
            });
            movements.value = [...historyRes.data].sort(
                (a, b) => new Date(a.createdAt) - new Date(b.createdAt)
            );
        }
    } catch (e) {
        error.value = e.response?.data?.message || t('account.error');
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
  <div class="stack">
    <p class="greeting">{{ t('account.greeting', { name: firstName(user?.name) || user?.username }) }}</p>

    <div class="card">
      <h1>{{ t('account.title') }}</h1>
      <p v-if="loading">{{ t('common.loading') }}</p>
      <p v-else-if="error" class="error">{{ error }}</p>
      <template v-else-if="account">
        <p class="label">{{ t('account.accountLabel') }}</p>
        <p class="value">{{ account.accountNumber }}</p>
        <p class="label">{{ t('account.balanceLabel') }}</p>
        <p class="balance">{{ balance?.available ?? '0.00' }} {{ currencySymbol(balance?.currency) }}</p>
        <p v-if="baseBalance && balance && baseBalance.currency !== balance.currency" class="balance-alt">
          ≈ {{ baseBalance.available }} {{ currencySymbol(baseBalance.currency) }}
        </p>
        <p v-if="balanceDelta !== null" class="delta" :class="{ up: balanceDelta >= 0, down: balanceDelta < 0 }">
          {{ balanceDelta >= 0 ? '+' : '' }}{{ balanceDelta.toFixed(2) }} {{ t('account.deltaSuffix') }}
        </p>
        <Sparkline v-if="sparklineValues.length > 1" :values="sparklineValues" />
      </template>
      <p v-else>{{ t('account.noAccount') }}</p>
    </div>

    <div v-if="spendByCategory.length" class="card">
      <h2>{{ t('account.spendTitle') }}</h2>
      <p class="period">{{ t('account.last30Days') }}</p>
      <ul class="bars">
        <li v-for="item in spendByCategory" :key="item.label" class="bar-row">
          <div class="bar-track">
            <div class="bar-fill" :style="{ width: item.widthPct + '%', background: item.color }">
              <span class="bar-label">{{ item.label }}</span>
            </div>
          </div>
          <span class="bar-amount">{{ item.total.toFixed(2) }} {{ currencySymbol(balance?.currency) }}</span>
        </li>
      </ul>
    </div>

    <div v-if="account" class="card barcode-card">
      <Barcode :value="barcodeValue" />
      <p class="barcode-number">{{ account.accountNumber }}</p>
    </div>
  </div>
</template>

<style scoped>
.stack {
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
}
.greeting {
    margin: 0 0.2rem;
    font-size: 1.1rem;
    font-weight: 600;
    color: #333;
}
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
    margin: 0 0 0.5rem;
    font-size: 1.4rem;
    font-weight: 700;
    text-align: center;
    color: var(--primary-dark);
}
h2 {
    margin: 0;
    font-size: 1.05rem;
    color: #333;
}
.period {
    margin: -0.3rem 0 0.4rem;
    font-size: 0.75rem;
    color: #999;
}
.label {
    margin: 0.5rem 0 0;
    font-size: 0.8rem;
    color: #777;
}
.value {
    margin: 0;
    font-size: 1.1rem;
}
.balance {
    margin: 0;
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--primary-dark);
}
.balance-alt {
    margin: 0;
    font-size: 0.9rem;
    color: #888;
}
.delta {
    margin: 0;
    font-size: 0.8rem;
    font-weight: 600;
}
.delta.up {
    color: #006300;
}
.delta.down {
    color: #c0392b;
}
.error {
    color: #c0392b;
}
.bars {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}
.bar-row {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}
.bar-track {
    flex: 1;
    background: #f0f1f3;
    border-radius: 6px;
    overflow: hidden;
    height: 26px;
    display: flex;
    align-items: center;
}
.bar-fill {
    height: 100%;
    border-radius: 6px;
    display: flex;
    align-items: center;
    padding: 0 0.5rem;
    box-sizing: border-box;
    min-width: fit-content;
    white-space: nowrap;
}
.bar-label {
    color: white;
    font-size: 0.72rem;
    font-weight: 600;
}
.bar-amount {
    font-size: 0.82rem;
    font-weight: 700;
    color: #333;
    white-space: nowrap;
}
.barcode-card {
    align-items: center;
    padding: 1.25rem 1.5rem;
}
.barcode-number {
    margin: 0.4rem 0 0;
    font-size: 0.85rem;
    font-weight: 600;
    letter-spacing: 0.05em;
    color: #555;
}
</style>
