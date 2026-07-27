<template>
    <Card>
        <template #title>
            <h1 class="text-2xl font-bold text-gray-800">{{ $t('commissionSettlements.title') }}</h1>
        </template>
        <template #content>
    <div class="container mx-auto px-4 py-8">

        <div class="card mb-6">
            <div class="flex flex-wrap gap-4 items-center justify-between">
                <Button v-if="common.can('commission_settlements:create')" :label="$t('commissionSettlements.create')" icon="pi pi-plus" @click="openCreateModal" />
                <div class="flex gap-2">
                    <Select v-model="filters.status" :options="statusOptions" optionLabel="label" optionValue="value"
                        class="w-64" :placeholder="$t('common.status')" @change="loadSettlements" />
                    <Select v-model="filters.currency" :options="currencyFilterOptions" optionLabel="label" optionValue="value"
                        class="w-40" :placeholder="$t('commissionSettlements.currency')" @change="loadSettlements" />
                    <Button :label="$t('common.search')" @click="loadSettlements" />
                </div>
            </div>
        </div>

        <DataTable :value="settlements" :loading="loading" size="small" :paginator="true" :rows="10"
            responsiveLayout="scroll">
            <Column field="settlementNumber" :header="$t('commissionSettlements.code')" />
            <Column :header="$t('commissionSettlements.amount')">
                <template #body="{ data }">{{ formatCurrency(data.amount, data.currency) }}</template>
            </Column>
            <Column field="status" :header="$t('common.status')">
                <template #body="{ data }">
                    <span :class="['px-2 py-1 text-xs rounded-full font-medium', statusBadgeClass(data.status)]">
                        {{ $t('commissionSettlements.' + data.status) }}
                    </span>
                </template>
            </Column>
            <Column field="createdBy" :header="$t('common.performedBy')" />
            <Column field="createdAt" :header="$t('common.createdAt')" />
            <Column :header="$t('common.actions')" style="width: 6rem">
                <template #body="{ data }">
                    <Button v-if="common.can('commission_settlements:details')" icon="pi pi-eye" class="p-button-rounded p-button-text" @click="showDetail(data)" />
                </template>
            </Column>
        </DataTable>

        <Dialog v-model:visible="showModal" :header="$t('commissionSettlements.create')" :modal="true"
            :style="{ width: '480px' }">
            <div class="flex flex-col gap-4">
                <div class="form-group">
                    <label class="form-label">{{ $t('commissionSettlements.currency') }}</label>
                    <Select v-model="form.currency" :options="currencyOptions" optionLabel="label" optionValue="value"
                        class="w-full" @change="loadAvailable" />
                </div>
                <div v-if="available !== null" class="text-sm text-gray-600">
                    {{ $t('commissionSettlements.availableCommission') }}:
                    <span class="font-semibold">{{ formatCurrency(available, form.currency) }}</span>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ $t('commissionSettlements.amount') }}</label>
                    <InputText v-model="form.amount" class="w-full" type="number" min="0.01" step="0.01" />
                </div>
                <div class="form-group">
                    <label class="form-label">{{ $t('commissionSettlements.notes') }}</label>
                    <Textarea v-model="form.notes" class="w-full" rows="2" />
                </div>
            </div>
            <template #footer>
                <Button :label="$t('common.cancel')" class="p-button-text" @click="closeModal" />
                <Button :label="$t('common.save')" @click="saveSettlement" :loading="saving"
                    :disabled="!form.currency || !form.amount" />
            </template>
        </Dialog>

        <Dialog v-model:visible="showDetailModal" :header="$t('common.details')" :modal="true"
            :style="{ width: '650px' }">
            <div v-if="selected" class="flex flex-col gap-5 text-sm">
                <div class="grid grid-cols-2 gap-3">
                    <div><span class="font-medium text-gray-600">{{ $t('commissionSettlements.code') }}:</span> {{ selected.settlementNumber }}</div>
                    <div><span class="font-medium text-gray-600">{{ $t('commissionSettlements.amount') }}:</span> {{ formatCurrency(selected.amount, selected.currency) }}</div>
                    <div><span class="font-medium text-gray-600">{{ $t('common.status') }}:</span>
                        <span :class="['px-2 py-1 text-xs rounded-full font-medium', statusBadgeClass(selected.status)]">{{ $t('commissionSettlements.' + selected.status) }}</span>
                    </div>
                    <div><span class="font-medium text-gray-600">{{ $t('common.performedBy') }}:</span> {{ selected.createdBy }}</div>
                    <div v-if="selected.notes" class="col-span-2"><span class="font-medium text-gray-600">{{ $t('commissionSettlements.notes') }}:</span> {{ selected.notes }}</div>
                </div>

                <div v-if="selected.payoutAccountNumber" class="border rounded-lg p-3 bg-blue-50">
                    <h3 class="font-semibold text-gray-700 mb-2">{{ $t('commissionSettlements.assignAccount') }}</h3>
                    <div>{{ selected.payoutAccountNumber }}
                        <span v-if="selected.payoutBankName" class="text-gray-500 text-xs">({{ selected.payoutBankName }})</span>
                    </div>
                    <div v-if="selected.payoutAccountHolder" class="text-gray-500 text-xs">{{ selected.payoutAccountHolder }}</div>
                </div>

                <table class="w-full border rounded-lg overflow-hidden">
                    <tbody>
                        <tr v-if="selected.adminApprovedAt" class="border-t">
                            <td class="px-3 py-2 text-gray-500">{{ $t('commissionSettlements.adminApprovedBy') }}</td>
                            <td class="px-3 py-2">{{ selected.adminApprovedBy }} — {{ selected.adminApprovedAt }}</td>
                        </tr>
                        <tr v-if="selected.accountAssignedAt" class="border-t">
                            <td class="px-3 py-2 text-gray-500">{{ $t('commissionSettlements.accountAssignedBy') }}</td>
                            <td class="px-3 py-2">{{ selected.accountAssignedBy }} — {{ selected.accountAssignedAt }}</td>
                        </tr>
                        <tr v-if="selected.settledAt" class="border-t">
                            <td class="px-3 py-2 text-gray-500">{{ $t('commissionSettlements.settledBy') }}</td>
                            <td class="px-3 py-2">{{ selected.settledBy }} — {{ selected.settledAt }} ({{ $t('reconciliations.' + selected.settlementMethod) }}{{ selected.settlementReference ? ' · ' + selected.settlementReference : '' }})</td>
                        </tr>
                        <tr v-if="selected.closedAt" class="border-t">
                            <td class="px-3 py-2 text-gray-500">{{ $t('commissionSettlements.closedBy') }}</td>
                            <td class="px-3 py-2">{{ selected.closedBy }} — {{ selected.closedAt }}</td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="selected.status === 'pending_admin_approval' && common.can('commission_settlements:approve')" class="flex gap-2">
                    <Button :label="$t('commissionSettlements.approve')" icon="pi pi-check" class="p-button-sm p-button-success"
                        @click="approveSettlement" :loading="actionLoading" />
                </div>

                <div v-if="selected.status === 'approved_admin' && common.can('commission_settlements:assign_account')" class="border rounded-lg p-4 bg-gray-50 flex flex-col gap-3">
                    <h4 class="font-medium text-gray-700 text-sm">{{ $t('commissionSettlements.assignAccount') }}</h4>
                    <div class="form-group">
                        <label class="form-label">{{ $t('commissionSettlements.payoutAccountNumber') }}</label>
                        <InputText v-model="accountForm.payoutAccountNumber" class="w-full" />
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ $t('commissionSettlements.payoutBankName') }}</label>
                        <InputText v-model="accountForm.payoutBankName" class="w-full" />
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ $t('commissionSettlements.payoutAccountHolder') }}</label>
                        <InputText v-model="accountForm.payoutAccountHolder" class="w-full" />
                    </div>
                    <Button :label="$t('commissionSettlements.assignAccount')" class="p-button-sm"
                        @click="assignAccount" :loading="actionLoading" :disabled="!accountForm.payoutAccountNumber" />
                </div>

                <div v-if="selected.status === 'pending_settlement' && common.can('commission_settlements:settle')" class="border rounded-lg p-4 bg-gray-50 flex flex-col gap-3">
                    <h4 class="font-medium text-gray-700 text-sm">{{ $t('commissionSettlements.settle') }}</h4>
                    <div class="form-group">
                        <label class="form-label">{{ $t('reconciliations.settlementMethod') }}</label>
                        <Select v-model="settleForm.method" :options="settlementMethodOptions" optionLabel="label" optionValue="value" class="w-full" />
                    </div>
                    <div class="form-group" v-if="settleForm.method === 'transferencia'">
                        <label class="form-label">{{ $t('reconciliations.settlementReference') }}</label>
                        <InputText v-model="settleForm.reference" class="w-full" />
                    </div>
                    <Button :label="$t('commissionSettlements.settle')" class="p-button-sm"
                        @click="settleSettlement" :loading="actionLoading" />
                </div>

                <div v-if="selected.status === 'settled' && common.can('commission_settlements:close')" class="flex gap-2">
                    <Button :label="$t('commissionSettlements.close')" icon="pi pi-lock" class="p-button-sm p-button-info"
                        @click="closeSettlement" :loading="actionLoading" />
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

const settlements = ref([]);
const currencyOptions = ref([]);
const loading = ref(false);
const saving = ref(false);
const actionLoading = ref(false);
const showModal = ref(false);
const showDetailModal = ref(false);
const selected = ref(null);
const available = ref(null);

const filters = ref({ status: '', currency: '' });
const form = ref({ currency: '', amount: '', notes: '' });
const accountForm = ref({ payoutAccountNumber: '', payoutBankName: '', payoutAccountHolder: '' });
const settleForm = ref({ method: 'efectivo', reference: '' });

const statusOptions = [
    { label: t('common.all'), value: '' },
    { label: t('commissionSettlements.pending_admin_approval'), value: 'pending_admin_approval' },
    { label: t('commissionSettlements.approved_admin'), value: 'approved_admin' },
    { label: t('commissionSettlements.pending_settlement'), value: 'pending_settlement' },
    { label: t('commissionSettlements.settled'), value: 'settled' },
    { label: t('commissionSettlements.closed'), value: 'closed' },
];

const currencyFilterOptions = ref([{ label: t('common.all'), value: '' }]);

const settlementMethodOptions = [
    { label: t('reconciliations.cash'), value: 'efectivo' },
    { label: t('reconciliations.transfer'), value: 'transferencia' },
];

const loadCurrencies = () => {
    common.ajax(urls.currenciesListUrl, 'GET', { active: 1 }, (data) => {
        if (data.success) {
            currencyOptions.value = data.data.map(c => ({ label: c.code, value: c.code }));
            currencyFilterOptions.value = [{ label: t('common.all'), value: '' }, ...currencyOptions.value];
        }
    }, onAjaxError);
};

const loadAvailable = () => {
    available.value = null;
    if (!form.value.currency) return;
    common.ajax(urls.availableUrl, 'GET', { currency: form.value.currency }, (data) => {
        if (data.success) available.value = data.data.available;
    }, onAjaxError);
};

const loadSettlements = () => {
    loading.value = true;
    const params = {};
    if (filters.value.status) params.status = filters.value.status;
    if (filters.value.currency) params.currency = filters.value.currency;

    common.ajax(urls.listUrl, 'GET', params, (data) => {
        if (data.success) {
            settlements.value = data.data;
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
    form.value = { currency: currencyOptions.value[0]?.value || '', amount: '', notes: '' };
    available.value = null;
    showModal.value = true;
    loadAvailable();
};

const closeModal = () => {
    showModal.value = false;
};

const saveSettlement = () => {
    saving.value = true;
    common.ajax(urls.createUrl, 'POST', JSON.stringify(form.value), (data) => {
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: data.message });
            closeModal();
            loadSettlements();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
        saving.value = false;
    }, (...args) => {
        onAjaxError(...args);
        saving.value = false;
    });
};

const showDetail = (settlement) => new Promise((resolve) => {
    common.ajax(urls.detailUrl.replace('__ID__', settlement.id), 'GET', null, (data) => {
        if (data.success) {
            selected.value = data.data;
            accountForm.value = { payoutAccountNumber: '', payoutBankName: '', payoutAccountHolder: '' };
            settleForm.value = { method: 'efectivo', reference: '' };
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
    loadSettlements();
};

const approveSettlement = () => {
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

const assignAccount = () => {
    actionLoading.value = true;
    common.ajax(urls.assignAccountUrl.replace('__ID__', selected.value.id), 'PUT', JSON.stringify(accountForm.value), async (data) => {
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

const settleSettlement = () => {
    actionLoading.value = true;
    const payload = {
        settlementMethod: settleForm.value.method,
        settlementReference: settleForm.value.reference || null,
    };
    common.ajax(urls.settleUrl.replace('__ID__', selected.value.id), 'PUT', JSON.stringify(payload), async (data) => {
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

const closeSettlement = () => {
    actionLoading.value = true;
    common.ajax(urls.closeUrl.replace('__ID__', selected.value.id), 'PUT', null, async (data) => {
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

const statusBadgeClass = (status) => {
    if (status === 'closed') return 'bg-emerald-100 text-emerald-800';
    if (status === 'settled') return 'bg-teal-100 text-teal-800';
    if (status === 'pending_settlement') return 'bg-blue-100 text-blue-800';
    if (status === 'approved_admin') return 'bg-indigo-100 text-indigo-800';
    return 'bg-amber-100 text-amber-800';
};

const formatCurrency = (amount, currency) => {
    if (!amount) return '—';
    return new Intl.NumberFormat('es-ES', { style: 'currency', currency: currency || 'USD' }).format(amount);
};

onMounted(() => {
    loadCurrencies();
    loadSettlements();
});
</script>
