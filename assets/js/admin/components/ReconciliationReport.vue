<template>
    <Card>
        <template #title>
            <h1 class="text-2xl font-bold text-gray-800">{{ $t('reconciliationReport.title') }}</h1>
        </template>
        <template #content>
    <div class="container mx-auto px-4 py-8">

        <div class="card mb-6">
            <div class="flex flex-wrap gap-4 items-end">
                <div class="form-group">
                    <label class="form-label">{{ $t('reconciliations.periodStart') }}</label>
                    <InputText v-model="filters.periodStart" class="w-full" type="date" />
                </div>
                <div class="form-group">
                    <label class="form-label">{{ $t('reconciliations.periodEnd') }}</label>
                    <InputText v-model="filters.periodEnd" class="w-full" type="date" />
                </div>
                <div class="form-group">
                    <label class="form-label">{{ $t('reconciliations.business') }}</label>
                    <Select v-model="filters.businessAccountId" :options="businessAccounts" optionLabel="businessName"
                        optionValue="id" class="w-52" filter showClear :placeholder="$t('common.all')" />
                </div>
                <div class="form-group">
                    <label class="form-label">{{ $t('common.status') }}</label>
                    <Select v-model="filters.status" :options="statusOptions" optionLabel="label" optionValue="value"
                        class="w-56" />
                </div>
                <Button :label="$t('reconciliationReport.generate')" icon="pi pi-search" @click="loadReport"
                    :loading="loading" :disabled="!filters.periodStart || !filters.periodEnd" />
                <Button :label="$t('reconciliationReport.exportPdf')" icon="pi pi-file-pdf"
                    class="p-button-outlined" @click="exportPdf"
                    :disabled="!report || !report.businesses.length" />
            </div>
        </div>

        <div v-if="report">
            <p v-if="!report.businesses.length" class="text-center py-8 text-gray-500">
                {{ $t('reconciliationReport.noResults') }}
            </p>

            <div v-for="business in report.businesses" :key="business.businessName" class="mb-8">
                <h3 class="font-semibold text-gray-700 mb-2">
                    {{ business.businessName }}
                    <span class="text-xs text-gray-400 font-normal">({{ $t('reconciliationReport.configuredCurrency') }}: {{ business.defaultCurrency }})</span>
                </h3>
                <div v-if="business.accounts.length" class="border rounded-lg overflow-x-auto mb-3 bg-blue-50">
                    <table class="w-full text-sm">
                        <thead>
                            <tr>
                                <th class="text-left px-3 py-2">{{ $t('reconciliations.payoutAccounts') }}</th>
                                <th class="text-left px-3 py-2">{{ $t('accounts.accountNumber') }}</th>
                                <th class="text-left px-3 py-2">{{ $t('accounts.currency') }}</th>
                                <th class="text-right px-3 py-2">{{ $t('reconciliationReport.count') }}</th>
                                <th class="text-right px-3 py-2">{{ $t('reconciliations.totalAmount') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="acc in business.accounts" :key="acc.payoutAccountId || 'unassigned'" class="border-t">
                                <td class="px-3 py-2">
                                    {{ acc.alias || $t('reconciliationReport.unassignedAccount') }}
                                    <span v-if="acc.bankName" class="text-gray-500 text-xs">({{ acc.bankName }})</span>
                                </td>
                                <td class="px-3 py-2">{{ acc.accountNumber || '—' }}</td>
                                <td class="px-3 py-2">{{ acc.currency }}</td>
                                <td class="px-3 py-2 text-right">{{ acc.reconciliationCount }}</td>
                                <td class="px-3 py-2 text-right">{{ formatCurrency(acc.amountTotal, acc.currency) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="border rounded-lg overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="text-left px-3 py-2">{{ $t('reconciliations.code') }}</th>
                                <th class="text-left px-3 py-2">{{ $t('common.period') }}</th>
                                <th class="text-left px-3 py-2">{{ $t('common.status') }}</th>
                                <th class="text-right px-3 py-2">{{ $t('reconciliations.subtotal') }}</th>
                                <th class="text-right px-3 py-2">{{ $t('reconciliations.taxAmount') }}</th>
                                <th class="text-right px-3 py-2">{{ $t('reconciliations.totalAmount') }}</th>
                                <th class="text-right px-3 py-2">{{ report.baseCurrency }}</th>
                                <th class="text-right px-3 py-2">{{ $t('reconciliationReport.equivalent') }} {{ business.defaultCurrency }}</th>
                                <th class="text-right px-3 py-2">{{ report.secondaryCurrency }}</th>
                                <th class="text-right px-3 py-2">{{ $t('reconciliationReport.equivalent') }} {{ business.defaultCurrency }}</th>
                                <th class="text-right px-3 py-2">{{ $t('reconciliationReport.rate') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, idx) in business.rows" :key="idx" class="border-t">
                                <td class="px-3 py-2">{{ row.reconciliationNumber }}</td>
                                <td class="px-3 py-2">{{ row.periodStart }} — {{ row.periodEnd }}</td>
                                <td class="px-3 py-2">
                                    <span :class="['px-2 py-1 text-xs rounded-full font-medium', statusBadgeClass(row.status)]">
                                        {{ $t('reconciliations.' + row.status) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-right">{{ formatCurrency(row.subtotal, row.currency) }}</td>
                                <td class="px-3 py-2 text-right">{{ formatCurrency(row.taxAmount, row.currency) }} ({{ row.taxPercent }}%)</td>
                                <td class="px-3 py-2 text-right">{{ formatCurrency(row.netAmount, row.currency) }}</td>
                                <td class="px-3 py-2 text-right">{{ formatCurrency(row.baseAmount, row.baseCurrency) }} ({{ row.basePercent }}%)</td>
                                <td class="px-3 py-2 text-right">{{ row.baseAmountInDefaultCurrency ? formatCurrency(row.baseAmountInDefaultCurrency, business.defaultCurrency) : '—' }}</td>
                                <td class="px-3 py-2 text-right">{{ formatCurrency(row.secondaryAmount, row.secondaryCurrency) }} ({{ row.secondaryPercent }}%)</td>
                                <td class="px-3 py-2 text-right">{{ row.secondaryAmountInDefaultCurrency ? formatCurrency(row.secondaryAmountInDefaultCurrency, business.defaultCurrency) : '—' }}</td>
                                <td class="px-3 py-2 text-right">{{ row.exchangeRate }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-gray-50 font-semibold border-t">
                            <tr>
                                <td class="px-3 py-2" colspan="3">{{ $t('reconciliationReport.businessTotal') }}</td>
                                <td class="px-3 py-2 text-right">{{ business.subtotalTotal }}</td>
                                <td class="px-3 py-2 text-right">{{ business.taxTotal }}</td>
                                <td class="px-3 py-2 text-right">{{ business.netTotal }}</td>
                                <td class="px-3 py-2 text-right">{{ business.baseAmountTotal }} {{ report.baseCurrency }}</td>
                                <td class="px-3 py-2 text-right">{{ business.defaultCurrencyBaseTotal }}</td>
                                <td class="px-3 py-2 text-right">{{ business.secondaryAmountTotal }} {{ report.secondaryCurrency }}</td>
                                <td class="px-3 py-2 text-right">{{ business.defaultCurrencySecondaryTotal }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div v-if="report.businesses.length" class="border rounded-lg p-4 bg-amber-50 max-w-md">
                <h3 class="font-semibold text-gray-700 mb-2">{{ $t('reconciliationReport.grandTotal') }}</h3>
                <div class="grid grid-cols-2 gap-y-1 text-sm">
                    <div>{{ $t('reconciliations.subtotal') }}</div>
                    <div class="text-right">{{ report.grandTotal.subtotal }}</div>
                    <div>{{ $t('reconciliations.taxAmount') }}</div>
                    <div class="text-right">{{ report.grandTotal.taxAmount }}</div>
                    <div>{{ report.baseCurrency }}</div>
                    <div class="text-right">{{ report.grandTotal.baseAmount }}</div>
                    <div>{{ report.secondaryCurrency }}</div>
                    <div class="text-right">{{ report.grandTotal.secondaryAmount }}</div>
                </div>
            </div>
        </div>

        <Toast />
    </div>
        </template>
    </Card>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Card from 'primevue/card';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import common from '../../common/common.js';

const { t } = useI18n();
const toast = useToast();

const urls = document.getElementById('vue-app').dataset;
const onAjaxError = (jqXHR, textStatus, errorThrown) => {
    toast.add({ severity: 'error', summary: t('common.error'), detail: jqXHR?.responseJSON?.message || errorThrown || textStatus });
};

const businessAccounts = ref([]);
const loading = ref(false);
const report = ref(null);

const filters = ref({ periodStart: '', periodEnd: '', businessAccountId: null, status: 'settled' });

const statusOptions = [
    { label: t('common.all'), value: '' },
    { label: t('reconciliations.pending_business'), value: 'pending_business' },
    { label: t('reconciliations.approved_business'), value: 'approved_business' },
    { label: t('reconciliations.approved_admin'), value: 'approved_admin' },
    { label: t('reconciliations.settled'), value: 'settled' },
    { label: t('reconciliations.rejected_business'), value: 'rejected_business' },
    { label: t('reconciliations.rejected_admin'), value: 'rejected_admin' },
];

const loadBusinessAccounts = () => {
    common.ajax(urls.accountsListUrl, 'GET', { accountType: 'business', limit: 200 }, (data) => {
        if (data.success) businessAccounts.value = data.data;
    }, onAjaxError);
};

const buildParams = () => {
    const params = { periodStart: filters.value.periodStart, periodEnd: filters.value.periodEnd };
    if (filters.value.businessAccountId) params.businessAccountId = filters.value.businessAccountId;
    if (filters.value.status) params.status = filters.value.status;
    return params;
};

const statusBadgeClass = (status) => {
    if (status === 'settled') return 'bg-emerald-100 text-emerald-800';
    if (status === 'approved_admin' || status === 'approved_business') return 'bg-blue-100 text-blue-800';
    if (status === 'pending_business') return 'bg-amber-100 text-amber-800';
    return 'bg-red-100 text-red-800';
};

const loadReport = () => {
    loading.value = true;
    common.ajax(urls.dataUrl, 'GET', buildParams(), (data) => {
        if (data.success) {
            report.value = data.data;
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
        loading.value = false;
    }, (...args) => {
        onAjaxError(...args);
        loading.value = false;
    });
};

const exportPdf = () => {
    const params = new URLSearchParams(buildParams());
    window.location.href = `${urls.pdfUrl}?${params.toString()}`;
};

const formatCurrency = (amount, currency) => {
    if (!amount) return '—';
    return new Intl.NumberFormat('es-ES', { style: 'currency', currency: currency || 'USD' }).format(amount);
};

onMounted(() => {
    loadBusinessAccounts();
});
</script>
