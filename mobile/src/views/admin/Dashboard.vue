<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { hasScope } from '../../api/permissions';
import { getActiveRole } from '../../api/client';
import { getDashboardStats } from '../../api/adminDashboard';
import { roleVersion } from '../../composables/role';
import { useUser } from '../../composables/user';
import { formatMoney } from '../../utils/currency';

const { t } = useI18n();
const { user } = useUser();

const activeRole = ref(null);
const canViewStats = ref(false);

const loading = ref(true);
const error = ref('');
const period = ref('7d');
const stats = ref(null);

const PERIODS = ['today', '7d', '30d'];
const OP_TYPES = ['recharges', 'transfers', 'invoices'];

const STATUS_CLASS = {
    completed: 'ok',
    paid: 'ok',
    processed: 'ok',
    active: 'ok',
    pending: 'pending',
    failed: 'bad',
    cancelled: 'bad',
    refunded: 'bad',
    suspended: 'bad',
};

function statusClass(status) {
    return STATUS_CLASS[status] || 'default';
}

function statusLabel(opType, status) {
    const cap = status.charAt(0).toUpperCase() + status.slice(1);
    return t(`${opType}.status${cap}`);
}

const PERIOD_KEYS = { today: 'periodToday', '7d': 'period7d', '30d': 'period30d' };
function periodLabel(p) {
    return t(`admin.dashboard.${PERIOD_KEYS[p]}`);
}

function countByStatus(rows, status) {
    return rows?.find((r) => r.status === status)?.total ?? 0;
}

function firstName(name) {
    return name ? name.trim().split(/\s+/)[0] : '';
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr.replace(' ', 'T')).toLocaleDateString('es-ES', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}

const hasBalances = computed(() => stats.value?.balancesByCurrency?.length > 0);

async function loadPermissions() {
    activeRole.value = await getActiveRole();
    canViewStats.value = await hasScope('dashboard.read');
}

async function loadStats() {
    if (!canViewStats.value) return;
    loading.value = true;
    error.value = '';
    try {
        stats.value = await getDashboardStats(period.value);
    } catch (e) {
        error.value = e.response?.data?.message || t('admin.dashboard.error');
    } finally {
        loading.value = false;
    }
}

async function refresh() {
    await loadPermissions();
    await loadStats();
}

function selectPeriod(p) {
    if (p === period.value) return;
    period.value = p;
    loadStats();
}

onMounted(refresh);
watch(roleVersion, refresh);
</script>

<template>
  <div class="stack">
    <div class="card hero">
      <div>
        <p class="greeting">{{ t('admin.dashboard.greeting', { name: firstName(user?.name) || user?.username }) }}</p>
        <span v-if="activeRole" class="role-badge">{{ activeRole.label }}</span>
      </div>
    </div>

    <template v-if="canViewStats">
      <div class="period-row">
        <button
          v-for="p in PERIODS"
          :key="p"
          type="button"
          class="period-btn"
          :class="{ active: period === p }"
          @click="selectPeriod(p)"
        >{{ periodLabel(p) }}</button>
      </div>

      <p v-if="loading" class="loading-text">{{ t('common.loading') }}</p>
      <p v-else-if="error" class="error">{{ error }}</p>

      <template v-else-if="stats">
        <div class="card">
          <h2>{{ t('admin.dashboard.systemBalance') }}</h2>
          <div v-if="hasBalances" class="tiles-grid">
            <div v-for="b in stats.balancesByCurrency" :key="b.currency" class="tile">
              <p class="tile-label">{{ b.currency }}</p>
              <p class="tile-value">{{ formatMoney(b.available, b.currency) }}</p>
              <p class="tile-sub">{{ t('admin.dashboard.reserved') }}: {{ formatMoney(b.reserved, b.currency) }}</p>
            </div>
            <div class="tile accent-ok">
              <p class="tile-label">{{ t('admin.dashboard.settledBase') }} ({{ stats.reconciliations?.baseCurrency }})</p>
              <p class="tile-value">{{ formatMoney(stats.reconciliations?.baseAmount, stats.reconciliations?.baseCurrency) }}</p>
            </div>
            <div class="tile accent-pending">
              <p class="tile-label">{{ t('admin.dashboard.systemCommission') }} ({{ stats.reconciliations?.baseCurrency }})</p>
              <p class="tile-value">{{ formatMoney(stats.reconciliations?.taxAmount, stats.reconciliations?.baseCurrency) }}</p>
              <p class="tile-sub">{{ t('admin.dashboard.notPeriodScoped') }}</p>
            </div>
            <div class="tile accent-info">
              <p class="tile-label">{{ t('admin.dashboard.settledSecondary') }} ({{ stats.reconciliations?.secondaryCurrency }})</p>
              <p class="tile-value">{{ formatMoney(stats.reconciliations?.secondaryAmount, stats.reconciliations?.secondaryCurrency) }}</p>
            </div>
          </div>
          <p v-else class="empty">{{ t('admin.dashboard.noData') }}</p>
        </div>

        <div class="card">
          <h2>{{ t('admin.dashboard.accountsTitle') }}</h2>
          <div class="tiles-grid">
            <div class="tile">
              <p class="tile-label">{{ t('admin.dashboard.totalAccounts') }}</p>
              <p class="tile-value">{{ stats.accounts?.total ?? 0 }}</p>
              <p class="tile-sub ok">{{ countByStatus(stats.accounts?.byStatus, 'active') }} {{ t('admin.dashboard.active') }}</p>
            </div>
            <div class="tile">
              <p class="tile-label">{{ t('admin.dashboard.pendingAccounts') }}</p>
              <p class="tile-value pending">{{ countByStatus(stats.accounts?.byStatus, 'pending') }}</p>
            </div>
            <div class="tile">
              <p class="tile-label">{{ t('admin.dashboard.suspendedAccounts') }}</p>
              <p class="tile-value bad">{{ countByStatus(stats.accounts?.byStatus, 'suspended') }}</p>
            </div>
            <div class="tile">
              <p class="tile-label">{{ t('admin.dashboard.authorizedUsers') }}</p>
              <p class="tile-value">{{ stats.authorized?.total ?? 0 }}</p>
              <p class="tile-sub ok">{{ stats.authorized?.active ?? 0 }} {{ t('admin.dashboard.active') }}</p>
            </div>
          </div>
        </div>

        <div class="card">
          <h2>{{ t('admin.dashboard.operations') }}</h2>
          <div class="op-groups">
            <div v-for="opType in OP_TYPES" :key="opType" class="op-group">
              <p class="op-title">{{ t(`${opType}.title`) }}</p>
              <div v-if="stats.operations?.[opType]?.length" class="op-rows">
                <div v-for="(row, idx) in stats.operations[opType]" :key="idx" class="op-row">
                  <span class="badge" :class="statusClass(row.status)">{{ statusLabel(opType, row.status) }}</span>
                  <span class="op-amount">{{ row.total }} · {{ formatMoney(row.amount, row.currency) }}</span>
                </div>
              </div>
              <p v-else class="empty small">{{ t('admin.dashboard.noData') }}</p>
            </div>
          </div>
        </div>

        <div class="card">
          <h2>{{ t('admin.dashboard.recentRecharges') }}</h2>
          <ul v-if="stats.recentRecharges?.length" class="activity-list">
            <li v-for="r in stats.recentRecharges" :key="r.id" class="activity-item">
              <div>
                <p class="activity-title">{{ r.businessname || r.accountnumber }}</p>
                <p class="activity-sub">{{ r.accountnumber }} · {{ formatDate(r.createdat) }}</p>
              </div>
              <div class="activity-right">
                <p class="activity-amount ok">+{{ formatMoney(r.amount, r.currency) }}</p>
                <span class="badge" :class="statusClass(r.status)">{{ statusLabel('recharges', r.status) }}</span>
              </div>
            </li>
          </ul>
          <p v-else class="empty">{{ t('admin.dashboard.noData') }}</p>
        </div>

        <div class="card">
          <h2>{{ t('admin.dashboard.recentTransfers') }}</h2>
          <ul v-if="stats.recentTransfers?.length" class="activity-list">
            <li v-for="tr in stats.recentTransfers" :key="tr.id" class="activity-item">
              <div>
                <p class="activity-title">{{ tr.from_account }} → {{ tr.to_account }}</p>
                <p class="activity-sub">{{ formatDate(tr.createdat) }}</p>
              </div>
              <div class="activity-right">
                <p class="activity-amount">{{ formatMoney(tr.amount, tr.currency) }}</p>
                <span class="badge" :class="statusClass(tr.status)">{{ statusLabel('transfers', tr.status) }}</span>
              </div>
            </li>
          </ul>
          <p v-else class="empty">{{ t('admin.dashboard.noData') }}</p>
        </div>

        <div class="card">
          <h2>{{ t('admin.dashboard.recentInvoices') }}</h2>
          <ul v-if="stats.recentInvoices?.length" class="activity-list">
            <li v-for="inv in stats.recentInvoices" :key="inv.id" class="activity-item">
              <div>
                <p class="activity-title">{{ inv.invoicenumber }}</p>
                <p class="activity-sub">{{ inv.businessname }} · {{ formatDate(inv.createdat) }}</p>
              </div>
              <div class="activity-right">
                <p class="activity-amount">{{ formatMoney(inv.totalamount, inv.currency) }}</p>
                <span class="badge" :class="statusClass(inv.status)">{{ statusLabel('invoices', inv.status) }}</span>
              </div>
            </li>
          </ul>
          <p v-else class="empty">{{ t('admin.dashboard.noData') }}</p>
        </div>

        <div class="card">
          <h2>{{ t('admin.dashboard.exchangeRatesTitle') }}</h2>
          <ul v-if="stats.exchangeRates?.length" class="rates-list">
            <li v-for="(rate, idx) in stats.exchangeRates" :key="idx" class="rate-row">
              <div>
                <p class="activity-title">{{ rate.fromcurrency }} → {{ rate.tocurrency }}</p>
                <p class="activity-sub">{{ rate.provider_name || t('admin.dashboard.manualRate') }} · {{ formatDate(rate.fetchedat) }}</p>
              </div>
              <p class="rate-value">{{ rate.rate }}</p>
            </li>
          </ul>
          <p v-else class="empty">{{ t('admin.dashboard.noData') }}</p>
        </div>
      </template>
    </template>
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
.hero {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
}
.greeting {
    margin: 0 0 0.4rem;
    font-size: 1.15rem;
    font-weight: 700;
    color: white;
}
.role-badge {
    display: inline-block;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 0.3rem 0.7rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.22);
    color: white;
}
h2 {
    margin: 0 0 1rem;
    font-size: 1.05rem;
    color: #333;
}
.empty {
    margin: 0;
    color: #888;
    font-size: 0.9rem;
    text-align: center;
    padding: 1rem 0;
}
.empty.small {
    padding: 0.5rem 0;
    text-align: left;
}
.error {
    color: #c0392b;
    font-size: 0.85rem;
}
.loading-text {
    text-align: center;
    color: #888;
    font-size: 0.9rem;
}

.period-row {
    display: flex;
    gap: 0.4rem;
}
.period-btn {
    flex: 1;
    padding: 0.5rem;
    border: 1px solid #d0d3d8;
    border-radius: 8px;
    background: white;
    color: #666;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
}
.period-btn.active {
    border-color: var(--primary-dark);
    background: #eefaf5;
    color: var(--primary-dark);
}

.tiles-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: 0.7rem;
}
.tile {
    background: #f7f9fa;
    border-radius: 10px;
    padding: 0.85rem;
}
.tile.accent-ok {
    background: #e3f9ef;
}
.tile.accent-pending {
    background: #fff4e0;
}
.tile.accent-info {
    background: #eaf2fb;
}
.tile-label {
    margin: 0;
    font-size: 0.72rem;
    color: #777;
    font-weight: 600;
}
.tile-value {
    margin: 0.3rem 0 0;
    font-size: 1.15rem;
    font-weight: 700;
    color: #1a1a1a;
}
.tile-value.pending {
    color: #b7791f;
}
.tile-value.bad {
    color: #c0392b;
}
.tile-sub {
    margin: 0.25rem 0 0;
    font-size: 0.72rem;
    color: #888;
}
.tile-sub.ok {
    color: #0f9d58;
    font-weight: 600;
}

.op-groups {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.op-title {
    margin: 0 0 0.5rem;
    font-size: 0.82rem;
    font-weight: 700;
    color: #555;
}
.op-rows {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}
.op-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.5rem 0.65rem;
    background: #f7f9fa;
    border-radius: 8px;
    font-size: 0.8rem;
}
.op-amount {
    color: #555;
}

.badge {
    font-size: 0.72rem;
    font-weight: 600;
    padding: 0.25rem 0.55rem;
    border-radius: 999px;
    background: #eef2f5;
    color: #666;
    text-transform: capitalize;
}
.badge.ok {
    background: #e3f9ef;
    color: #0f9d58;
}
.badge.pending {
    background: #fff4e0;
    color: #b7791f;
}
.badge.bad {
    background: #fdecea;
    color: #c0392b;
}

.activity-list, .rates-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}
.activity-item, .rate-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.6rem;
    padding: 0.65rem 0.75rem;
    background: #f7f9fa;
    border-radius: 8px;
}
.activity-title {
    margin: 0;
    font-size: 0.85rem;
    font-weight: 600;
    color: #333;
}
.activity-sub {
    margin: 0.15rem 0 0;
    font-size: 0.72rem;
    color: #888;
}
.activity-right {
    text-align: right;
    flex-shrink: 0;
}
.activity-amount {
    margin: 0 0 0.25rem;
    font-size: 0.85rem;
    font-weight: 700;
    color: #333;
}
.activity-amount.ok {
    color: #0f9d58;
}
.rate-value {
    margin: 0;
    font-size: 0.9rem;
    font-weight: 700;
    color: #333;
}

</style>
