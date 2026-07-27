<template>
    <Card>
        <template #title>
            <h1 class="text-2xl font-bold text-gray-800">{{ $t('reconciliations.title') }}</h1>
        </template>
        <template #content>
    <div class="container mx-auto px-4 py-8">

        <div class="card mb-6">
            <div class="flex flex-wrap gap-4 items-center justify-between">
            <Button v-if="common.can('reconciliations:create')" :label="$t('reconciliations.create')" icon="pi pi-plus" @click="openCreateModal" />
                <div class="flex gap-2">
                    <Select v-model="filters.businessAccountId" :options="businessAccounts" optionLabel="businessName"
                        optionValue="id" class="w-52" filter showClear :placeholder="$t('reconciliations.business')"
                        @change="loadReconciliations" />
                    <Select v-model="filters.status" :options="statusOptions" optionLabel="label" optionValue="value"
                        class="w-56" :placeholder="$t('common.status')" @change="loadReconciliations" />
                    <Button :label="$t('common.search')" @click="loadReconciliations" />
                </div>
            </div>
        </div>

        <DataTable :value="reconciliations" :loading="loading" size="small" :paginator="true" :rows="10"
            responsiveLayout="scroll">
            <Column field="reconciliationNumber" :header="$t('reconciliations.code')" />
            <Column field="businessAccountName" :header="$t('reconciliations.business')" />
            <Column :header="$t('common.period')">
                <template #body="{ data }">{{ data.periodStart }} — {{ data.periodEnd }}</template>
            </Column>
            <Column field="invoiceCount" :header="$t('reconciliations.invoiceCount')" />
            <Column field="totalAmount" :header="$t('reconciliations.totalAmount')">
                <template #body="{ data }">{{ formatCurrency(data.totalAmount, data.currency) }}</template>
            </Column>
            <Column field="status" :header="$t('common.status')">
                <template #body="{ data }">
                    <span :class="['px-2 py-1 text-xs rounded-full font-medium', statusBadgeClass(data.status)]">
                        {{ $t('reconciliations.' + data.status) }}
                    </span>
                </template>
            </Column>
            <Column field="createdAt" :header="$t('common.createdAt')" />
            <Column :header="$t('common.actions')" style="width: 6rem">
                <template #body="{ data }">
                    <Button v-if="common.can('reconciliations:details')" icon="pi pi-eye" class="p-button-rounded p-button-text" @click="showDetail(data)" />
                </template>
            </Column>
        </DataTable>

        <Dialog v-model:visible="showModal" :header="$t('reconciliations.create')" :modal="true"
            :style="{ width: '650px' }">
            <div class="flex flex-col gap-4">
                <div class="form-group">
                    <label class="form-label">{{ $t('reconciliations.business') }}</label>
                    <Select v-model="form.businessAccountId" :options="businessAccounts" optionLabel="businessName"
                        optionValue="id" class="w-full" filter />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">{{ $t('reconciliations.periodStart') }}</label>
                        <InputText v-model="form.periodStart" class="w-full" type="date" />
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ $t('reconciliations.periodEnd') }}</label>
                        <InputText v-model="form.periodEnd" class="w-full" type="date" />
                    </div>
                </div>

                <Button :label="$t('reconciliations.preview')" icon="pi pi-search" class="w-full p-button-sm"
                    @click="loadPreview" :loading="previewing"
                    :disabled="!form.businessAccountId || !form.periodStart || !form.periodEnd" />

                <div v-if="previewResult">
                    <div v-if="previewResult.invoices.length" class="border rounded-lg overflow-hidden">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="text-left px-3 py-2">{{ $t('invoices.invoiceNumber') }}</th>
                                    <th class="text-right px-3 py-2">{{ $t('invoices.totalAmount') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="invoice in previewResult.invoices" :key="invoice.id" class="border-t">
                                    <td class="px-3 py-2">{{ invoice.invoiceNumber }}</td>
                                    <td class="px-3 py-2 text-right">{{ formatCurrency(invoice.totalAmount,
                                        invoice.currency) }}
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="border-t bg-gray-50">
                                <tr>
                                    <td class="px-3 py-2">{{ $t('reconciliations.subtotal') }}</td>
                                    <td class="px-3 py-2 text-right">{{ formatCurrency(previewResult.total,
                                        previewResult.currency) }}</td>
                                </tr>
                                <tr>
                                    <td class="px-3 py-2">{{ $t('reconciliations.taxAmount') }} ({{ previewResult.taxPercent }}%)</td>
                                    <td class="px-3 py-2 text-right">{{ formatCurrency(previewResult.taxAmount,
                                        previewResult.currency) }}</td>
                                </tr>
                                <tr class="font-semibold border-t">
                                    <td class="px-3 py-2">{{ $t('reconciliations.totalAmount') }}</td>
                                    <td class="px-3 py-2 text-right">{{ formatCurrency(previewResult.netAmount,
                                        previewResult.currency) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                        <div v-if="previewResult.payoutSplitPreview?.baseAmount" class="border-t rounded-b-lg p-3 bg-amber-50">
                            <h3 class="font-semibold text-gray-700 mb-2 text-sm">Split de pago (se fijará a esta tasa al crear la conciliación)</h3>
                            <div class="grid grid-cols-2 gap-2 text-sm">
                                <div>{{ formatCurrency(previewResult.payoutSplitPreview.baseAmount, previewResult.payoutSplitPreview.baseCurrency) }} <span class="text-gray-500 text-xs">({{ previewResult.payoutSplitPreview.basePercent }}%)</span></div>
                                <div>{{ formatCurrency(previewResult.payoutSplitPreview.secondaryAmount, previewResult.payoutSplitPreview.secondaryCurrency) }} <span class="text-gray-500 text-xs">({{ previewResult.payoutSplitPreview.secondaryPercent }}%)</span></div>
                            </div>
                            <div class="text-xs text-gray-500 mt-1">Tasa: {{ previewResult.payoutSplitPreview.exchangeRate }}</div>
                        </div>
                        <div v-else-if="previewResult.payoutSplitPreview?.error" class="border-t rounded-b-lg p-3 bg-red-50 text-sm text-red-700">
                            {{ previewResult.payoutSplitPreview.error }}
                        </div>
                    </div>
                    <div v-else class="text-center py-4 text-gray-500 text-sm">
                        {{ $t('reconciliations.noInvoicesFound') }}
                    </div>
                </div>
            </div>
            <template #footer>
                <Button :label="$t('common.cancel')" class="p-button-text" @click="closeModal" />
                <Button :label="$t('common.save')" @click="saveReconciliation" :loading="saving"
                    :disabled="!previewResult || !previewResult.invoices.length" />
            </template>
        </Dialog>

        <Dialog v-model:visible="showDetailModal" :header="$t('common.details')" :modal="true"
            :style="{ width: '750px' }">
            <div v-if="selected" class="flex flex-col gap-5 text-sm">
                <div class="grid grid-cols-2 gap-3">
                    <div><span class="font-medium text-gray-600">{{ $t('reconciliations.code') }}:</span> {{
                        selected.reconciliationNumber }}</div>
                    <div><span class="font-medium text-gray-600">{{ $t('reconciliations.business') }}:</span> {{
                        selected.businessAccountName }}</div>
                    <div><span class="font-medium text-gray-600">{{ $t('common.period') }}:</span> {{
                        selected.periodStart }} —
                        {{ selected.periodEnd }}</div>
                    <div><span class="font-medium text-gray-600">{{ $t('reconciliations.subtotal') }}:</span> {{
                        formatCurrency(selected.totalAmount, selected.currency) }}</div>
                    <div><span class="font-medium text-gray-600">{{ $t('reconciliations.taxAmount') }} ({{ selected.taxPercent }}%):</span> {{
                        formatCurrency(selected.taxAmount, selected.currency) }}</div>
                    <div><span class="font-medium text-gray-600">{{ $t('reconciliations.totalAmount') }}:</span> {{
                        formatCurrency(selected.netAmount, selected.currency) }}</div>
                    <div><span class="font-medium text-gray-600">{{ $t('common.status') }}:</span>
                        <span
                            :class="['px-2 py-1 text-xs rounded-full font-medium', statusBadgeClass(selected.status)]">{{
                                $t('reconciliations.' + selected.status) }}</span>
                    </div>
                </div>

                <div>
                    <h3 class="font-semibold text-gray-700 mb-2">{{ $t('invoices.title') }}</h3>
                    <table class="w-full border rounded-lg overflow-hidden">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="text-left px-3 py-2">{{ $t('invoices.invoiceNumber') }}</th>
                                <th class="text-right px-3 py-2">{{ $t('invoices.totalAmount') }}</th>
                                <th class="text-left px-3 py-2">{{ $t('common.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="invoice in selected.invoices" :key="invoice.id" class="border-t">
                                <td class="px-3 py-2">{{ invoice.invoiceNumber }}</td>
                                <td class="px-3 py-2 text-right">{{ formatCurrency(invoice.totalAmount,
                                    invoice.currency) }}
                                </td>
                                <td class="px-3 py-2">{{ $t('invoices.' + invoice.status, invoice.status) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="selected.settlementBaseAmount" class="border rounded-lg p-3 bg-emerald-50">
                    <h3 class="font-semibold text-gray-700 mb-2">Split de pago{{ selected.status === 'settled' ? ' liquidado' : '' }}</h3>
                    <div class="grid grid-cols-2 gap-2">
                        <div>{{ formatCurrency(selected.settlementBaseAmount, selected.settlementBaseCurrency) }} <span class="text-gray-500 text-xs">({{ selected.settlementBasePercent }}%)</span></div>
                        <div>{{ formatCurrency(selected.settlementSecondaryAmount, selected.settlementSecondaryCurrency) }} <span class="text-gray-500 text-xs">({{ selected.settlementSecondaryPercent }}%)</span></div>
                    </div>
                    <div class="text-xs text-gray-500 mt-1">Tasa fijada al crear la conciliación: {{ selected.settlementExchangeRate }}</div>
                </div>

                <div v-if="selected.payoutAccountBase" class="border rounded-lg p-3 bg-blue-50">
                    <h3 class="font-semibold text-gray-700 mb-2">{{ $t('reconciliations.payoutAccounts') }}</h3>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <span class="text-gray-500 text-xs">{{ selected.payoutAccountBase.currency }}</span><br>
                            {{ selected.payoutAccountBase.alias }} — {{ selected.payoutAccountBase.accountNumber }}
                            <span v-if="selected.payoutAccountBase.bankName" class="text-gray-500 text-xs">({{ selected.payoutAccountBase.bankName }})</span>
                        </div>
                        <div v-if="selected.payoutAccountSecondary">
                            <span class="text-gray-500 text-xs">{{ selected.payoutAccountSecondary.currency }}</span><br>
                            {{ selected.payoutAccountSecondary.alias }} — {{ selected.payoutAccountSecondary.accountNumber }}
                            <span v-if="selected.payoutAccountSecondary.bankName" class="text-gray-500 text-xs">({{ selected.payoutAccountSecondary.bankName }})</span>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button v-if="selected.status === 'pending_business' && common.can('reconciliations:send')" :label="$t('reconciliations.send')"
                        icon="pi pi-whatsapp" class="p-button-sm" @click="sendReconciliation"
                        :loading="actionLoading" />
                    <Button v-if="selected.status === 'approved_business' && common.can('reconciliations:approve')" :label="$t('reconciliations.approve')"
                        icon="pi pi-check" class="p-button-sm p-button-success" @click="approveReconciliation"
                        :loading="actionLoading" />
                    <Button v-if="['pending_business', 'approved_business'].includes(selected.status) && common.can('reconciliations:approve')"
                        :label="$t('reconciliations.reject')" icon="pi pi-times" class="p-button-sm p-button-danger"
                        @click="rejectReconciliation" :loading="actionLoading" />
                    <Button v-if="selected.status === 'approved_admin' && common.can('reconciliations:settle')" :label="$t('reconciliations.settle')"
                        icon="pi pi-money-bill" class="p-button-sm p-button-info" @click="showSettleForm = true"
                        :loading="actionLoading" />
                </div>

                <div v-if="showSettleForm" class="border rounded-lg p-4 bg-gray-50 flex flex-col gap-3">
                    <template v-if="selected.settlementBaseAmount">
                        <div class="border rounded-lg p-3 bg-white">
                            <h4 class="font-medium text-gray-700 mb-2 text-sm">
                                Pago en {{ selected.settlementBaseCurrency }}
                                ({{ formatCurrency(selected.settlementBaseAmount, selected.settlementBaseCurrency) }})
                            </h4>
                            <div class="form-group">
                                <label class="form-label">{{ $t('reconciliations.settlementMethod') }}</label>
                                <Select v-model="settleForm.method" :options="settlementMethodOptions"
                                    optionLabel="label" optionValue="value" class="w-full" />
                            </div>
                            <div class="form-group" v-if="settleForm.method === 'transferencia'">
                                <label class="form-label">{{ $t('reconciliations.settlementReference') }}</label>
                                <InputText v-model="settleForm.reference" class="w-full" />
                            </div>
                        </div>
                        <div class="border rounded-lg p-3 bg-white">
                            <h4 class="font-medium text-gray-700 mb-2 text-sm">
                                Pago en {{ selected.settlementSecondaryCurrency }}
                                ({{ formatCurrency(selected.settlementSecondaryAmount, selected.settlementSecondaryCurrency) }})
                            </h4>
                            <div class="form-group">
                                <label class="form-label">{{ $t('reconciliations.settlementMethod') }}</label>
                                <Select v-model="settleForm.secondaryMethod" :options="settlementMethodOptions"
                                    optionLabel="label" optionValue="value" class="w-full" />
                            </div>
                            <div class="form-group" v-if="settleForm.secondaryMethod === 'transferencia'">
                                <label class="form-label">{{ $t('reconciliations.settlementReference') }}</label>
                                <InputText v-model="settleForm.secondaryReference" class="w-full" />
                            </div>
                        </div>
                    </template>
                    <template v-else>
                        <div class="form-group">
                            <label class="form-label">{{ $t('reconciliations.settlementMethod') }}</label>
                            <Select v-model="settleForm.method" :options="settlementMethodOptions" optionLabel="label"
                                optionValue="value" class="w-full" />
                        </div>
                        <div class="form-group" v-if="settleForm.method === 'transferencia'">
                            <label class="form-label">{{ $t('reconciliations.settlementReference') }}</label>
                            <InputText v-model="settleForm.reference" class="w-full" />
                        </div>
                    </template>
                    <div class="form-group">
                        <label class="form-label">{{ $t('reconciliations.settlementNotes') }}</label>
                        <Textarea v-model="settleForm.notes" class="w-full" rows="2" />
                    </div>
                    <div class="flex gap-2">
                        <Button :label="$t('common.cancel')" class="p-button-text p-button-sm"
                            @click="showSettleForm = false" />
                        <Button :label="$t('reconciliations.settle')" class="p-button-sm" @click="settleReconciliation"
                            :loading="actionLoading" />
                    </div>
                </div>

                <div>
                    <h3 class="font-semibold text-gray-700 mb-2">{{ $t('reconciliations.history') }}</h3>
                    <table class="w-full border rounded-lg overflow-hidden">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="text-left px-3 py-2">{{ $t('common.createdAt') }}</th>
                                <th class="text-left px-3 py-2">{{ $t('reconciliations.event') }}</th>
                                <th class="text-left px-3 py-2">{{ $t('common.performedBy') }}</th>
                                <th class="text-left px-3 py-2">{{ $t('common.notes') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="event in selected.events" :key="event.id" class="border-t">
                                <td class="px-3 py-2">{{ event.createdAt }}</td>
                                <td class="px-3 py-2">{{ $t('reconciliations.' + event.eventType) }}</td>
                                <td class="px-3 py-2">{{ event.performedBy || '—' }}</td>
                                <td class="px-3 py-2">{{ event.notes || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <template #footer>
                <Button :label="$t('common.close')" class="p-button-text" @click="showDetailModal = false" />
            </template>
        </Dialog>

        <Toast />
    </div>
        </template>
    </Card>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import Dialog from 'primevue/dialog';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import common from '../../common/common.js';
import Card from 'primevue/card';

const { t } = useI18n();
const toast = useToast();

const urls = document.getElementById('vue-app').dataset;
const onAjaxError = (jqXHR, textStatus, errorThrown) => {
    toast.add({ severity: 'error', summary: t('common.error'), detail: jqXHR?.responseJSON?.message || errorThrown || textStatus });
};

const reconciliations = ref([]);
const businessAccounts = ref([]);
const loading = ref(false);
const saving = ref(false);
const previewing = ref(false);
const actionLoading = ref(false);
const showModal = ref(false);
const showDetailModal = ref(false);
const showSettleForm = ref(false);
const selected = ref(null);
const previewResult = ref(null);

const filters = ref({ businessAccountId: null, status: '' });

const statusOptions = [
    { label: t('common.all'), value: '' },
    { label: t('reconciliations.pending_business'), value: 'pending_business' },
    { label: t('reconciliations.approved_business'), value: 'approved_business' },
    { label: t('reconciliations.approved_admin'), value: 'approved_admin' },
    { label: t('reconciliations.settled'), value: 'settled' },
    { label: t('reconciliations.rejected_business'), value: 'rejected_business' },
    { label: t('reconciliations.rejected_admin'), value: 'rejected_admin' },
];

const settlementMethodOptions = [
    { label: t('reconciliations.cash'), value: 'efectivo' },
    { label: t('reconciliations.transfer'), value: 'transferencia' },
];

const form = ref({ businessAccountId: null, periodStart: '', periodEnd: '' });
const settleForm = ref({ method: 'efectivo', reference: '', secondaryMethod: 'efectivo', secondaryReference: '', notes: '' });

const loadBusinessAccounts = () => {
    common.ajax(urls.accountsListUrl, 'GET', { accountType: 'business', limit: 200 }, (data) => {
        if (data.success) businessAccounts.value = data.data;
    }, onAjaxError);
};

const loadReconciliations = () => {
    loading.value = true;
    const params = {};
    if (filters.value.businessAccountId) params.businessAccountId = filters.value.businessAccountId;
    if (filters.value.status) params.status = filters.value.status;

    common.ajax(urls.listUrl, 'GET', params, (data) => {
        if (data.success) {
            reconciliations.value = data.data;
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
        loading.value = false;
    }, (...args) => {
        onAjaxError(...args);
        loading.value = false;
    });
};

const openCreateModal = () => {
    form.value = { businessAccountId: null, periodStart: '', periodEnd: '' };
    previewResult.value = null;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    previewResult.value = null;
};

const loadPreview = () => {
    previewing.value = true;
    previewResult.value = null;
    const params = {
        businessAccountId: form.value.businessAccountId,
        periodStart: form.value.periodStart,
        periodEnd: form.value.periodEnd,
    };
    common.ajax(urls.previewUrl, 'GET', params, (data) => {
        if (data.success) {
            previewResult.value = data.data;
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
        previewing.value = false;
    }, (...args) => {
        onAjaxError(...args);
        previewing.value = false;
    });
};

const saveReconciliation = () => {
    saving.value = true;
    common.ajax(urls.createUrl, 'POST', JSON.stringify(form.value), (data) => {
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: data.message });
            closeModal();
            loadReconciliations();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
        saving.value = false;
    }, (...args) => {
        onAjaxError(...args);
        saving.value = false;
    });
};

const showDetail = (reconciliation) => new Promise((resolve) => {
    common.ajax(urls.detailUrl.replace('__ID__', reconciliation.id), 'GET', null, (data) => {
        if (data.success) {
            selected.value = data.data;
            showSettleForm.value = false;
            settleForm.value = { method: 'efectivo', reference: '', secondaryMethod: 'efectivo', secondaryReference: '', notes: '' };
            showDetailModal.value = true;
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
        resolve();
    }, (...args) => {
        onAjaxError(...args);
        resolve();
    });
});

const refreshDetail = async () => {
    if (!selected.value) return;
    await showDetail(selected.value);
    loadReconciliations();
};

const sendReconciliation = () => {
    actionLoading.value = true;
    common.ajax(urls.sendUrl.replace('__ID__', selected.value.id), 'POST', null, async (data) => {
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: data.message });
            await refreshDetail();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
        actionLoading.value = false;
    }, (...args) => {
        onAjaxError(...args);
        actionLoading.value = false;
    });
};

const approveReconciliation = () => {
    actionLoading.value = true;
    common.ajax(urls.approveUrl.replace('__ID__', selected.value.id), 'PUT', null, async (data) => {
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: data.message });
            await refreshDetail();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
        actionLoading.value = false;
    }, (...args) => {
        onAjaxError(...args);
        actionLoading.value = false;
    });
};

const rejectReconciliation = () => {
    const reason = prompt(t('reconciliations.reject') + ' - motivo:');
    if (reason === null) return;
    actionLoading.value = true;
    common.ajax(urls.rejectUrl.replace('__ID__', selected.value.id), 'PUT', JSON.stringify({ reason }), async (data) => {
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: data.message });
            await refreshDetail();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
        actionLoading.value = false;
    }, (...args) => {
        onAjaxError(...args);
        actionLoading.value = false;
    });
};

const settleReconciliation = () => {
    actionLoading.value = true;
    const payload = {
        settlementMethod: settleForm.value.method,
        settlementReference: settleForm.value.reference || null,
        settlementSecondaryMethod: selected.value.settlementBaseAmount ? settleForm.value.secondaryMethod : null,
        settlementSecondaryReference: selected.value.settlementBaseAmount ? (settleForm.value.secondaryReference || null) : null,
        settlementNotes: settleForm.value.notes || null,
    };
    common.ajax(urls.settleUrl.replace('__ID__', selected.value.id), 'PUT', JSON.stringify(payload), async (data) => {
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: data.message });
            showSettleForm.value = false;
            await refreshDetail();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
        actionLoading.value = false;
    }, (...args) => {
        onAjaxError(...args);
        actionLoading.value = false;
    });
};

const statusBadgeClass = (status) => {
    if (status === 'settled') return 'bg-emerald-100 text-emerald-800';
    if (status === 'approved_admin' || status === 'approved_business') return 'bg-blue-100 text-blue-800';
    if (status === 'pending_business') return 'bg-amber-100 text-amber-800';
    return 'bg-red-100 text-red-800';
};

const formatCurrency = (amount, currency) => {
    if (!amount) return '—';
    return new Intl.NumberFormat('es-ES', { style: 'currency', currency: currency || 'USD' }).format(amount);
};

onMounted(() => {
    loadBusinessAccounts();
    loadReconciliations();
});
</script>
