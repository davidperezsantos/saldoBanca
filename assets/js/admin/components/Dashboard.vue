<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between flex-wrap gap-3">
      <h1 class="text-2xl font-bold text-gray-800">{{ $t('dashboard.title') }}</h1>
      <div class="flex items-center gap-3">
        <span class="text-sm text-gray-500">{{ new Date().toLocaleDateString('es-ES', {
          weekday: 'long', year:
            'numeric', month: 'long', day: 'numeric'
        }) }}</span>
        <select v-model="period" @change="changePeriod"
          class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 bg-white text-gray-700">
          <option value="today">{{ $t('dashboard.periodToday') }}</option>
          <option value="7d">{{ $t('dashboard.period7d') }}</option>
          <option value="30d">{{ $t('dashboard.period30d') }}</option>
        </select>
      </div>
    </div>

    <!-- Saldo del sistema, por moneda -->
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
      <h2 class="text-lg font-semibold text-gray-800 mb-4">{{ $t('dashboard.systemBalance') }}</h2>
      <div v-if="stats.balancesByCurrency?.length" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <div v-for="b in stats.balancesByCurrency" :key="b.currency" class="p-4 bg-gray-50 rounded-lg">
          <p class="text-sm font-medium text-gray-500">{{ b.currency }}</p>
          <p class="text-xl font-bold text-gray-900 mt-1">{{ formatCurrency(b.available, b.currency) }}</p>
          <p class="text-xs text-gray-500 mt-1">{{ $t('dashboard.reserved') }}: {{ formatCurrency(b.reserved,
            b.currency) }}</p>
        </div>
        <div class="p-4 bg-emerald-50 rounded-lg">
          <p class="text-sm font-medium text-gray-500">{{ $t('dashboard.settledBase') }} ({{
            stats.reconciliations?.baseCurrency }})</p>
          <p class="text-xl font-bold text-emerald-700 mt-1">{{ formatCurrency(stats.reconciliations?.baseAmount,
            stats.reconciliations?.baseCurrency) }}</p>
        </div>
        <div class="p-4 bg-amber-50 rounded-lg">
          <p class="text-sm font-medium text-gray-500">{{ $t('dashboard.systemCommission') }} ({{
            stats.reconciliations?.baseCurrency }})</p>
          <p class="text-xl font-bold text-amber-700 mt-1">{{ formatCurrency(stats.reconciliations?.taxAmount,
            stats.reconciliations?.baseCurrency) }}</p>
          <p class="text-xs text-gray-500 mt-1">{{ $t('dashboard.notPeriodScoped') }}</p>
        </div>
        <div class="p-4 bg-sky-50 rounded-lg">
          <p class="text-sm font-medium text-gray-500">{{ $t('dashboard.settledSecondary') }} ({{
            stats.reconciliations?.secondaryCurrency }})</p>
          <p class="text-xl font-bold text-sky-700 mt-1">{{ formatCurrency(stats.reconciliations?.secondaryAmount,
            stats.reconciliations?.secondaryCurrency) }}</p>
        </div>
      </div>
      <p v-else class="text-sm text-gray-500 text-center py-8">{{ $t('dashboard.noData') }}</p>
    </div>

    <!-- Cuentas y autorizados -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
      <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 hover:shadow-md transition-shadow">
        <p class="text-sm font-medium text-gray-500">{{ $t('dashboard.totalAccounts') }}</p>
        <p class="text-3xl font-bold text-gray-900 mt-1">{{ stats.accounts?.total ?? 0 }}</p>
        <p class="text-xs text-emerald-600 mt-1">{{ countByKey(stats.accounts?.byStatus, 'status', 'active') }} {{
          $t('dashboard.active') }}</p>
      </div>
      <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 hover:shadow-md transition-shadow">
        <p class="text-sm font-medium text-gray-500">{{ $t('dashboard.pendingAccounts') }}</p>
        <p class="text-3xl font-bold text-amber-600 mt-1">{{ countByKey(stats.accounts?.byStatus, 'status', 'pending')
        }}</p>
      </div>
      <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 hover:shadow-md transition-shadow">
        <p class="text-sm font-medium text-gray-500">{{ $t('dashboard.suspendedAccounts') }}</p>
        <p class="text-3xl font-bold text-red-600 mt-1">{{ countByKey(stats.accounts?.byStatus, 'status', 'suspended')
        }}</p>
      </div>
      <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 hover:shadow-md transition-shadow">
        <p class="text-sm font-medium text-gray-500">{{ $t('dashboard.authorizedUsers') }}</p>
        <p class="text-3xl font-bold text-gray-900 mt-1">{{ stats.authorized?.total ?? 0 }}</p>
        <p class="text-xs text-emerald-600 mt-1">{{ stats.authorized?.active ?? 0 }} {{ $t('dashboard.active') }}</p>
      </div>
    </div>

    <!-- Operaciones por estado, con ventana de tiempo -->
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
      <h2 class="text-lg font-semibold text-gray-800 mb-4">{{ $t('dashboard.operations') }}</h2>
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div v-for="opType in ['recharges', 'transfers', 'invoices']" :key="opType">
          <h3 class="text-sm font-semibold text-gray-600 mb-2">{{ $t(`${opType}.title`) }}</h3>
          <div v-if="stats.operations?.[opType]?.length" class="space-y-2">
            <div v-for="(row, idx) in stats.operations[opType]" :key="idx"
              class="flex items-center justify-between text-sm p-2 bg-gray-50 rounded-lg">
              <span :class="getStatusClass(row.status)" class="text-xs px-2 py-1 rounded-full">
                {{ $t(`${opType}.${row.status}`) }}
              </span>
              <span class="text-gray-600">{{ row.total }} · {{ formatCurrency(row.amount, row.currency) }}</span>
            </div>
          </div>
          <p v-else class="text-xs text-gray-500 py-4">{{ $t('dashboard.noData') }}</p>
        </div>
      </div>
    </div>

    <!-- Actividad reciente -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">{{ $t('dashboard.recentRecharges') }}</h2>
        <div v-if="stats.recentRecharges?.length" class="space-y-3">
          <div v-for="recharge in stats.recentRecharges" :key="recharge.id"
            class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
            <div>
              <p class="text-sm font-medium text-gray-800">{{ recharge.businessname }}</p>
              <p class="text-xs text-gray-500">{{ recharge.accountnumber }}</p>
            </div>
            <div class="text-right">
              <p class="text-sm font-semibold text-emerald-600">+{{ formatCurrency(recharge.amount, recharge.currency)
              }}</p>
              <span :class="getStatusClass(recharge.status)" class="text-xs px-2 py-1 rounded-full">
                {{ $t(`recharges.${recharge.status}`) }}
              </span>
            </div>
          </div>
        </div>
        <p v-else class="text-sm text-gray-500 text-center py-8">{{ $t('dashboard.noData') }}</p>
      </div>

      <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">{{ $t('dashboard.recentTransfers') }}</h2>
        <div v-if="stats.recentTransfers?.length" class="space-y-3">
          <div v-for="transfer in stats.recentTransfers" :key="transfer.id"
            class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
            <div>
              <p class="text-sm font-medium text-gray-800">{{ transfer.from_account }} → {{ transfer.to_account }}</p>
            </div>
            <div class="text-right">
              <p class="text-sm font-semibold text-gray-800">{{ formatCurrency(transfer.amount, transfer.currency) }}
              </p>
              <span :class="getStatusClass(transfer.status)" class="text-xs px-2 py-1 rounded-full">
                {{ $t(`transfers.${transfer.status}`) }}
              </span>
            </div>
          </div>
        </div>
        <p v-else class="text-sm text-gray-500 text-center py-8">{{ $t('dashboard.noData') }}</p>
      </div>

      <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">{{ $t('dashboard.recentInvoices') }}</h2>
        <div v-if="stats.recentInvoices?.length" class="space-y-3">
          <div v-for="invoice in stats.recentInvoices" :key="invoice.id"
            class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
            <div>
              <p class="text-sm font-medium text-gray-800">{{ invoice.invoicenumber }}</p>
              <p class="text-xs text-gray-500">{{ invoice.businessname }}</p>
            </div>
            <div class="text-right">
              <p class="text-sm font-semibold text-gray-800">{{ formatCurrency(invoice.totalamount, invoice.currency) }}
              </p>
              <span :class="getStatusClass(invoice.status)" class="text-xs px-2 py-1 rounded-full">
                {{ $t(`invoices.${invoice.status}`) }}
              </span>
            </div>
          </div>
        </div>
        <p v-else class="text-sm text-gray-500 text-center py-8">{{ $t('dashboard.noData') }}</p>
      </div>
    </div>

    <!-- Tasas de cambio vigentes -->
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
      <h2 class="text-lg font-semibold text-gray-800 mb-4">{{ $t('dashboard.exchangeRatesTitle') }}</h2>
      <div v-if="stats.exchangeRates?.length" class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-gray-500 border-b border-gray-100">
              <th class="pb-2 font-medium">{{ $t('dashboard.provider') }}</th>
              <th class="pb-2 font-medium">{{ $t('dashboard.pair') }}</th>
              <th class="pb-2 font-medium">{{ $t('dashboard.rate') }}</th>
              <th class="pb-2 font-medium">{{ $t('dashboard.updatedAt') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(rate, idx) in stats.exchangeRates" :key="idx" class="border-b border-gray-50">
              <td class="py-2">{{ rate.provider_name || $t('dashboard.manualRate') }}</td>
              <td class="py-2">{{ rate.fromcurrency }} → {{ rate.tocurrency }}</td>
              <td class="py-2">{{ rate.rate }}</td>
              <td class="py-2 text-gray-500">{{ rate.fetchedat }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else class="text-sm text-gray-500 text-center py-8">{{ $t('dashboard.noData') }}</p>
    </div>

    <!-- Acciones rápidas -->
    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
      <h2 class="text-lg font-semibold text-gray-800 mb-4">{{ $t('dashboard.quickActions') }}</h2>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <a href="/accounts"
          class="flex flex-col items-center p-5 bg-gradient-to-br from-indigo-50 to-indigo-100 rounded-xl hover:from-indigo-100 hover:to-indigo-200 transition-all">
          <svg class="w-8 h-8 text-indigo-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
          <span class="text-sm font-medium text-indigo-800">{{ $t('menu.accounts') }}</span>
        </a>
        <a href="/recharges"
          class="flex flex-col items-center p-5 bg-gradient-to-br from-emerald-50 to-emerald-100 rounded-xl hover:from-emerald-100 hover:to-emerald-200 transition-all">
          <svg class="w-8 h-8 text-emerald-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
              d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span class="text-sm font-medium text-emerald-800">{{ $t('menu.recharges') }}</span>
        </a>
        <a href="/transfers"
          class="flex flex-col items-center p-5 bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl hover:from-purple-100 hover:to-purple-200 transition-all">
          <svg class="w-8 h-8 text-purple-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
              d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
          </svg>
          <span class="text-sm font-medium text-purple-800">{{ $t('menu.transfers') }}</span>
        </a>
        <a href="/invoices"
          class="flex flex-col items-center p-5 bg-gradient-to-br from-amber-50 to-amber-100 rounded-xl hover:from-amber-100 hover:to-amber-200 transition-all">
          <svg class="w-8 h-8 text-amber-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
              d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
          </svg>
          <span class="text-sm font-medium text-amber-800">{{ $t('menu.invoices') }}</span>
        </a>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const stats = ref({
  period: '7d',
  balancesByCurrency: [],
  accounts: { total: 0, byStatus: [], byType: [] },
  authorized: { total: 0, active: 0 },
  operations: { recharges: [], transfers: [], invoices: [] },
  recentRecharges: [],
  recentTransfers: [],
  recentInvoices: [],
  exchangeRates: [],
  reconciliations: { baseCurrency: '', baseAmount: '0', taxAmount: '0', secondaryCurrency: '', secondaryAmount: '0' },
})

const period = ref('7d')

const formatCurrency = (value, currency = 'USD') => {
  const num = parseFloat(value) || 0
  try {
    return new Intl.NumberFormat('es-US', { style: 'currency', currency }).format(num)
  } catch (e) {
    return `${num.toFixed(2)} ${currency}`
  }
}

const countByKey = (rows, key, value) => {
  if (!rows?.length) return 0
  const row = rows.find((r) => r[key] === value)
  return row ? row.total : 0
}

const getStatusClass = (status) => {
  const classes = {
    completed: 'bg-emerald-100 text-emerald-800',
    paid: 'bg-emerald-100 text-emerald-800',
    active: 'bg-emerald-100 text-emerald-800',
    pending: 'bg-amber-100 text-amber-800',
    failed: 'bg-red-100 text-red-800',
    cancelled: 'bg-gray-100 text-gray-800',
    refunded: 'bg-purple-100 text-purple-800',
  }
  return classes[status] || 'bg-gray-100 text-gray-800'
}

const changePeriod = () => {
  const url = new URL(window.location.href)
  url.searchParams.set('period', period.value)
  window.location.href = url.toString()
}

onMounted(() => {
  if (window.__STATS__) {
    stats.value = window.__STATS__
    period.value = window.__STATS__.period || '7d'
  }
})
</script>
