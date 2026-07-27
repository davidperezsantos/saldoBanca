<template>
    <Card>
        <template #title>
            <h1 class="text-2xl font-bold text-gray-800">{{ $t('recharges.title') }}</h1>
        </template>
        <template #content>
            <div class="container mx-auto px-4 py-8">
                <div class="card mb-6">
                    <div class="flex flex-wrap gap-4 items-center justify-between">
                    <Button v-if="common.can('recharges:create')" :label="$t('recharges.create')" icon="pi pi-plus" @click="openCreateModal" />
                        <div class="flex gap-2">
                            <Select v-model="filters.status" :options="statusOptions" optionLabel="label"
                                optionValue="value" class="w-40" :placeholder="$t('common.status')"
                                @change="loadRecharges" />
                            <Select v-model="filters.rechargeType" :options="typeOptions" optionLabel="label"
                                optionValue="value" class="w-40" :placeholder="$t('recharges.type')"
                                @change="loadRecharges" />
                            <InputText v-model="filters.search" :placeholder="$t('common.search')"
                                @keyup.enter="loadRecharges" />
                            <Button :label="$t('common.search')" @click="loadRecharges" />
                        </div>
                    </div>
                </div>

                <div class="card">
                    <DataTable :value="recharges" :loading="loading" size="small" :paginator="true" :rows="10"
                        responsiveLayout="scroll">
                        <Column field="receiptNumber" :header="$t('common.code')" />
                        <Column field="accountNumber" :header="$t('accounts.accountNumber')" />
                        <Column field="amount" :header="$t('recharges.amount')">
                            <template #body="{ data }">
                                <div v-if="data.originalAmount && data.originalCurrency" class="flex flex-col">
                                    <span class="text-xs text-gray-500">{{ formatCurrency(data.originalAmount,
                                        data.originalCurrency) }}</span>
                                    <span class="font-semibold">{{ formatCurrency(data.amount, data.currency) }}</span>
                                </div>
                                <span v-else>{{ formatCurrency(data.amount, data.currency) }}</span>
                            </template>
                        </Column>
                        <Column field="rechargeType" :header="$t('recharges.type')">
                            <template #body="{ data }">
                                <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">
                                    {{ data.rechargeType }}
                                </span>
                            </template>
                        </Column>
                        <Column field="referenceNumber" :header="$t('recharges.reference')" />
                        <Column field="paymentMethod" :header="$t('recharges.paymentMethod')" />
                        <Column field="status" :header="$t('common.status')">
                            <template #body="{ data }">
                                <span :class="['px-2 py-1 text-xs rounded-full font-medium',
                                    data.status === 'completed' ? 'bg-emerald-100 text-emerald-800' :
                                        data.status === 'pending' ? 'bg-amber-100 text-amber-800' :
                                            data.status === 'failed' ? 'bg-red-100 text-red-800' :
                                                'bg-gray-100 text-gray-800']">
                                    {{ $t('recharges.' + data.status) }}
                                </span>
                            </template>
                        </Column>
                        <Column field="createdAt" :header="$t('common.createdAt')">
                            <template #body="{ data }">
                                {{ formatDate(data.createdAt) }}
                            </template>
                        </Column>
                        <Column :header="$t('common.actions')" style="width: 12rem">
                            <template #body="{ data }">
                                <div class="flex gap-1">
                                    <Button icon="pi pi-check" class="p-button-rounded p-button-success p-button-text"
                                        v-if="data.status === 'pending' && common.can('recharges:complete')" @click="completeRecharge(data)"
                                        :disabled="actionLoading" />
                                    <Button icon="pi pi-times" class="p-button-rounded p-button-danger p-button-text"
                                        v-if="data.status === 'pending' && common.can('recharges:fail')" @click="failRecharge(data)"
                                        :disabled="actionLoading" />
                                    <Button icon="pi pi-ban" class="p-button-rounded p-button-warning p-button-text"
                                        v-if="data.status === 'pending' && common.can('recharges:cancel')" @click="cancelRecharge(data)"
                                        :disabled="actionLoading" />
                                    <Button v-if="common.can('recharges:details')" icon="pi pi-eye" class="p-button-rounded p-button-info p-button-text"
                                        @click="showDetail(data)" />
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                    <div v-if="!recharges.length && !loading" class="text-center py-8 text-gray-500">
                        {{ $t('common.no_data') }}
                    </div>
                </div>

                <Dialog v-model:visible="showModal" :header="editingId ? $t('common.edit') : $t('recharges.create')"
                    :modal="true" :style="{ width: '550px' }">
                    <div class="flex flex-col gap-4">
                        <div class="form-group">
                            <label class="form-label">{{ $t('accounts.title') }}</label>
                            <Select v-model="form.accountId" :options="accounts" optionLabel="businessName"
                                optionValue="id" class="w-full" filter />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="form-group">
                                <label class="form-label">{{ $t('recharges.amount') }}</label>
                                <InputNumber v-model="form.amount" class="w-full" :min="0" :max="99999999" />
                            </div>
                            <div class="form-group">
                                <label class="form-label">{{ $t('recharges.currency') }}</label>
                                <Select v-model="form.currency" :options="currencyOptions" optionLabel="label"
                                    optionValue="value" class="w-full" />
                            </div>
                        </div>

                        <Button label="Calcular conversión" icon="pi pi-calculator" class="w-full p-button-sm"
                            @click="calcularConversion" :loading="calculating"
                            :disabled="!form.amount || !form.currency" />

                        <div v-if="conversionResult" class="p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-gray-600">Monto ingresado:</span>
                                    <strong class="ml-1">{{ formatCurrency(conversionResult.originalAmount,
                                        conversionResult.originalCurrency) }}</strong>
                                    <span class="mx-2 text-gray-400">→</span>
                                    <span class="text-gray-600">Monto a recargar ({{ conversionResult.baseCurrency
                                        }}):</span>
                                    <strong class="ml-1 text-blue-700">{{
                                        formatCurrency(conversionResult.convertedAmount,
                                        conversionResult.baseCurrency) }}</strong>
                                    <div v-if="conversionResult.rate" class="text-xs text-gray-400 mt-1">
                                        Tasa: 1 {{ conversionResult.originalCurrency }} = {{ conversionResult.rate }} {{
                                            conversionResult.baseCurrency }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="form-group">
                                <label class="form-label">{{ $t('recharges.type') }}</label>
                                <Select v-model="form.rechargeType"
                                    :options="[{ label: 'Manual', value: 'manual' }, { label: 'External', value: 'external' }, { label: 'Transfer', value: 'transfer' }]"
                                    optionLabel="label" optionValue="value" class="w-full" />
                            </div>
                            <div class="form-group">
                                <label class="form-label">{{ $t('recharges.paymentMethod') }}</label>
                                <Select v-model="form.paymentMethod"
                                    :options="[{ label: 'Cash', value: 'cash' }, { label: 'Transfer', value: 'transfer' }, { label: 'Card', value: 'card' }, { label: 'Check', value: 'check' }]"
                                    optionLabel="label" optionValue="value" class="w-full" />
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">{{ $t('recharges.reference') }}</label>
                            <InputText v-model="form.referenceNumber" class="w-full" />
                        </div>
                        <div class="form-group">
                            <label class="form-label">{{ $t('common.notes') }}</label>
                            <Textarea v-model="form.notes" class="w-full" rows="3" />
                        </div>
                    </div>
                    <template #footer>
                        <Button :label="$t('common.cancel')" class="p-button-text" @click="closeModal" />
                        <Button :label="$t('common.save')" @click="saveRecharge" :loading="saving" />
                    </template>
                </Dialog>

                <Dialog v-model:visible="showDetailModal" :header="$t('common.details')" :modal="true"
                    :style="{ width: '600px' }">
                    <div v-if="selectedRecharge" class="grid grid-cols-2 gap-4 text-sm">
                        <div><span class="font-medium text-gray-600">{{ $t('accounts.accountNumber') }}:</span> {{
                            selectedRecharge.accountNumber }}</div>
                        <div><span class="font-medium text-gray-600">{{ $t('recharges.amount') }}:</span>
                            <span v-if="selectedRecharge.originalAmount && selectedRecharge.originalCurrency">
                                {{ formatCurrency(selectedRecharge.originalAmount, selectedRecharge.originalCurrency) }}
                                → {{
                                    formatCurrency(selectedRecharge.amount, selectedRecharge.currency) }}
                                <span v-if="selectedRecharge.exchangeRate" class="text-xs text-gray-400">(tasa: {{
                                    selectedRecharge.exchangeRate }})</span>
                            </span>
                            <span v-else>{{ formatCurrency(selectedRecharge.amount, selectedRecharge.currency) }}</span>
                        </div>
                        <div><span class="font-medium text-gray-600">{{ $t('recharges.type') }}:</span> {{
                            selectedRecharge.rechargeType
                            }}</div>
                        <div><span class="font-medium text-gray-600">{{ $t('recharges.reference') }}:</span> {{
                            selectedRecharge.referenceNumber || '—' }}</div>
                        <div><span class="font-medium text-gray-600">{{ $t('recharges.paymentMethod') }}:</span> {{
                            selectedRecharge.paymentMethod || '—' }}</div>
                        <div><span class="font-medium text-gray-600">{{ $t('common.status') }}:</span> {{
                            $t('recharges.' +
                            selectedRecharge.status) }}</div>
                        <div><span class="font-medium text-gray-600">{{ $t('common.createdAt') }}:</span> {{
                            formatDate(selectedRecharge.createdAt) }}</div>
                        <div v-if="selectedRecharge.notes" class="col-span-2"><span class="font-medium text-gray-600">{{
                            $t('common.notes') }}:</span> {{ selectedRecharge.notes }}</div>
                    </div>
                    <template #footer>
                        <Button :label="$t('common.close')" class="p-button-text" @click="showDetailModal = false" />
                    </template>
                </Dialog>

                <Dialog v-model:visible="showFailModal" :header="$t('recharges.fail')" :modal="true"
                    :style="{ width: '400px' }">
                    <div class="form-group">
                        <label class="form-label">{{ $t('common.reason') }}</label>
                        <Textarea v-model="failReason" class="w-full" rows="3" />
                    </div>
                    <template #footer>
                        <Button :label="$t('common.cancel')" class="p-button-text" @click="showFailModal = false" />
                        <Button :label="$t('common.confirm')" class="p-button-danger" @click="confirmFail"
                            :loading="actionLoading" />
                    </template>
                </Dialog>

                <ConfirmDialog />
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
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import Dialog from 'primevue/dialog';
import ConfirmDialog from 'primevue/confirmdialog';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import Card from 'primevue/card';
import common from '../../common/common.js';

const { t } = useI18n();
const toast = useToast();
const confirm = useConfirm();

const urls = document.getElementById('vue-app').dataset;
const onAjaxError = (jqXHR, textStatus, errorThrown) => {
    toast.add({ severity: 'error', summary: t('common.error'), detail: jqXHR?.responseJSON?.message || errorThrown || textStatus });
};

const recharges = ref([]);
const accounts = ref([]);
const currencyOptions = ref([]);
const loading = ref(false);
const saving = ref(false);
const actionLoading = ref(false);
const showModal = ref(false);
const showDetailModal = ref(false);
const showFailModal = ref(false);
const editingId = ref(null);
const selectedRecharge = ref(null);
const failRechargeTarget = ref(null);
const failReason = ref('');
const conversionResult = ref(null);
const calculating = ref(false);

const filters = ref({ status: '', rechargeType: '', search: '' });

const statusOptions = [
    { label: t('common.all'), value: '' },
    { label: t('recharges.pending'), value: 'pending' },
    { label: t('recharges.completed'), value: 'completed' },
    { label: t('recharges.failed'), value: 'failed' },
    { label: t('recharges.cancelled'), value: 'cancelled' },
];

const typeOptions = [
    { label: t('common.all'), value: '' },
    { label: t('recharges.manual'), value: 'manual' },
    { label: t('recharges.external'), value: 'external' },
    { label: t('recharges.transfer'), value: 'transfer' },
];

const form = ref({
    accountId: '',
    amount: null,
    currency: 'USD',
    rechargeType: 'manual',
    paymentMethod: 'cash',
    referenceNumber: '',
    notes: '',
    originalAmount: null,
    originalCurrency: null,
});

const loadAccounts = () => {
    common.ajax(urls.accountsListUrl, 'GET', { limit: 200 }, (data) => {
        if (data.success) accounts.value = data.data;
    }, onAjaxError);
};

// Solo ofrece en el select las monedas activas del nomenclador (antes era un array fijo
// [USD, EUR, VES, COP] hardcodeado en el propio componente).
const loadCurrencies = () => {
    common.ajax(urls.currenciesListUrl, 'GET', { active: 1 }, (data) => {
        if (data.success) currencyOptions.value = data.data.map(c => ({ label: c.code, value: c.code }));
    }, onAjaxError);
};

const loadRecharges = () => {
    loading.value = true;
    const params = {};
    if (filters.value.status) params.status = filters.value.status;
    if (filters.value.rechargeType) params.rechargeType = filters.value.rechargeType;

    common.ajax(urls.listUrl, 'GET', params, (data) => {
        if (data.success) {
            recharges.value = data.data;
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
    editingId.value = null;
    form.value = { accountId: '', amount: null, currency: 'USD', rechargeType: 'manual', paymentMethod: 'cash', referenceNumber: '', notes: '', originalAmount: null, originalCurrency: null };
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingId.value = null;
    conversionResult.value = null;
};

const calcularConversion = () => {
    if (!form.value.amount || !form.value.currency) return;
    calculating.value = true;
    common.ajax(urls.convertUrl, 'GET', { amount: form.value.amount, currency: form.value.currency }, (data) => {
        if (data.success) {
            conversionResult.value = data.data;
            form.value.originalAmount = String(form.value.amount);
            form.value.originalCurrency = form.value.currency;
            form.value.amount = parseFloat(data.data.convertedAmount);
            form.value.currency = data.data.baseCurrency;
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
        calculating.value = false;
    }, (...args) => {
        onAjaxError(...args);
        calculating.value = false;
    });
};

const saveRecharge = () => {
    saving.value = true;
    common.ajax(urls.createUrl, 'POST', JSON.stringify({ ...form.value, amount: String(form.value.amount) }), (data) => {
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: data.message });
            closeModal();
            loadRecharges();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
        saving.value = false;
    }, (...args) => {
        onAjaxError(...args);
        saving.value = false;
    });
};

const completeRecharge = (recharge) => {
    confirm.require({
        message: `¿Completar recarga de ${formatCurrency(recharge.amount, recharge.currency)}?`,
        header: 'Confirmar',
        icon: 'pi pi-question-circle',
        acceptClass: 'p-button-success',
        accept: () => {
            actionLoading.value = true;
            common.ajax(urls.completeUrl.replace('__ID__', recharge.id), 'PUT', null, (data) => {
                if (data.success) {
                    toast.add({ severity: 'success', summary: t('common.success'), detail: data.message });
                    loadRecharges();
                } else {
                    toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
                }
                actionLoading.value = false;
            }, (...args) => {
                onAjaxError(...args);
                actionLoading.value = false;
            });
        }
    });
};

const failRecharge = (recharge) => {
    failRechargeTarget.value = recharge;
    failReason.value = '';
    showFailModal.value = true;
};

const confirmFail = () => {
    if (!failRechargeTarget.value) return;
    actionLoading.value = true;
    common.ajax(urls.failUrl.replace('__ID__', failRechargeTarget.value.id), 'PUT', JSON.stringify({ reason: failReason.value || 'Failed manually' }), (data) => {
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: data.message });
            showFailModal.value = false;
            loadRecharges();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
        actionLoading.value = false;
    }, (...args) => {
        onAjaxError(...args);
        actionLoading.value = false;
    });
};

const cancelRecharge = (recharge) => {
    confirm.require({
        message: `¿Cancelar recarga de ${formatCurrency(recharge.amount, recharge.currency)}?`,
        header: 'Confirmar',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-warning',
        accept: () => {
            actionLoading.value = true;
            common.ajax(urls.cancelUrl.replace('__ID__', recharge.id), 'PUT', null, (data) => {
                if (data.success) {
                    toast.add({ severity: 'success', summary: t('common.success'), detail: data.message });
                    loadRecharges();
                } else {
                    toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
                }
                actionLoading.value = false;
            }, (...args) => {
                onAjaxError(...args);
                actionLoading.value = false;
            });
        }
    });
};

const showDetail = (recharge) => {
    selectedRecharge.value = recharge;
    showDetailModal.value = true;
};

const formatCurrency = (amount, currency) => {
    if (!amount) return '—';
    return new Intl.NumberFormat('es-ES', { style: 'currency', currency: currency || 'USD' }).format(amount);
};

const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('es-ES', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
};

onMounted(() => {
    loadAccounts();
    loadCurrencies();
    loadRecharges();
});
</script>
