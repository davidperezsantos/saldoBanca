<template>
    <Card>
        <template #title>
            <h1 class="text-2xl font-bold text-gray-800">{{ $t('transfers.title') }}</h1>
        </template>
        <template #content>
            <div class="container mx-auto px-4 py-8">
                <div class="card mb-6">
                    <div class="flex flex-wrap gap-4 items-center justify-between">
                    <Button v-if="common.can('transfers:create')" :label="$t('transfers.create')" icon="pi pi-plus" @click="openCreateModal" />
                        <div class="flex gap-2">
                            <Select v-model="filters.status" :options="statusOptions" optionLabel="label"
                                optionValue="value" class="w-40" :placeholder="$t('common.status')"
                                @change="loadTransfers" />
                            <InputText v-model="filters.search" :placeholder="$t('common.search')"
                                @keyup.enter="loadTransfers" />
                            <Button :label="$t('common.search')" @click="loadTransfers" />
                        </div>
                    </div>
                </div>
                    <DataTable :value="transfers" :loading="loading" size="small" :paginator="true" :rows="10"
                        responsiveLayout="scroll">
                        <Column field="receiptNumber" :header="$t('common.code')" />
                        <Column field="originAccountNumber" :header="$t('transfers.originAccount')" />
                        <Column field="destAccountNumber" :header="$t('transfers.destinationAccount')" />
                        <Column field="amount" :header="$t('transfers.amount')">
                            <template #body="{ data }">
                                {{ formatCurrency(data.amount, data.currency) }}
                            </template>
                        </Column>
                        <Column field="fee" :header="$t('transfers.fee')">
                            <template #body="{ data }">
                                {{ data.fee ? formatCurrency(data.fee, data.currency) : '—' }}
                            </template>
                        </Column>
                        <Column field="status" :header="$t('common.status')">
                            <template #body="{ data }">
                                <span :class="['px-2 py-1 text-xs rounded-full font-medium',
                                    data.status === 'completed' ? 'bg-emerald-100 text-emerald-800' :
                                        data.status === 'pending' ? 'bg-amber-100 text-amber-800' :
                                            data.status === 'failed' ? 'bg-red-100 text-red-800' :
                                                'bg-gray-100 text-gray-800']">
                                    {{ $t('transfers.' + data.status) }}
                                </span>
                            </template>
                        </Column>
                        <Column field="createdAt" :header="$t('common.createdAt')">
                            <template #body="{ data }">
                                {{ formatDate(data.createdAt) }}
                            </template>
                        </Column>
                        <Column :header="$t('common.actions')" style="width: 10rem">
                            <template #body="{ data }">
                                <div class="flex gap-1">
                                    <Button icon="pi pi-check" class="p-button-rounded p-button-success p-button-text"
                                        v-if="data.status === 'pending' && common.can('transfers:process')" @click="processTransfer(data)"
                                        :disabled="actionLoading" />
                                    <Button icon="pi pi-ban" class="p-button-rounded p-button-warning p-button-text"
                                        v-if="data.status === 'pending' && common.can('transfers:cancel')" @click="cancelTransfer(data)"
                                        :disabled="actionLoading" />
                                    <Button v-if="common.can('transfers:details')" icon="pi pi-eye" class="p-button-rounded p-button-info p-button-text"
                                        @click="showDetail(data)" />
                                </div>
                            </template>
                        </Column>
                    </DataTable>

                <Dialog v-model:visible="showModal" :header="$t('transfers.create')" :modal="true"
                    :style="{ width: '550px' }">
                    <div class="flex flex-col gap-4">
                        <div class="form-group">
                            <label class="form-label">{{ $t('transfers.originAccount') }}</label>
                            <Select v-model="form.originAccountId" :options="accounts" optionLabel="businessName"
                                optionValue="id" class="w-full" filter @change="onOriginChange" />
                        </div>
                        <div class="form-group">
                            <label class="form-label">{{ $t('transfers.destinationAccount') }}</label>
                            <Select v-model="form.destinationAccountNumber" :options="destinationAccounts"
                                optionLabel="businessName" optionValue="accountNumber" class="w-full" filter
                                :disabled="!form.originAccountId"
                                :placeholder="!form.originAccountId ? $t('transfers.selectOriginFirst') : undefined" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="form-group">
                                <label class="form-label">{{ $t('transfers.amount') }}</label>
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
                                    <span class="text-gray-600">Monto a transferir ({{ conversionResult.baseCurrency
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

                        <div v-if="limits" class="text-sm text-gray-500 p-3 bg-gray-50 rounded">
                            {{ $t('common.available') }}: {{ formatCurrency(limits.available, limits.currency) }}
                        </div>
                        <div class="form-group">
                            <label class="form-label">{{ $t('common.notes') }}</label>
                            <Textarea v-model="form.notes" class="w-full" rows="2" />
                        </div>
                    </div>
                    <template #footer>
                        <Button :label="$t('common.cancel')" class="p-button-text" @click="closeModal" />
                        <Button :label="$t('common.save')" @click="saveTransfer" :loading="saving" />
                    </template>
                </Dialog>

                <Dialog v-model:visible="showDetailModal" :header="$t('common.details')" :modal="true"
                    :style="{ width: '600px' }">
                    <div v-if="selectedTransfer" class="grid grid-cols-2 gap-4 text-sm">
                        <div><span class="font-medium text-gray-600">{{ $t('transfers.originAccount') }}:</span> {{
                            selectedTransfer.originAccountNumber }}</div>
                        <div><span class="font-medium text-gray-600">{{ $t('transfers.destinationAccount') }}:</span> {{
                            selectedTransfer.destAccountNumber }}</div>
                        <div><span class="font-medium text-gray-600">{{ $t('transfers.amount') }}:</span>
                            <span v-if="selectedTransfer.originalAmount && selectedTransfer.originalCurrency">
                                {{ formatCurrency(selectedTransfer.originalAmount, selectedTransfer.originalCurrency) }}
                                → {{
                                    formatCurrency(selectedTransfer.amount, selectedTransfer.currency) }}
                            </span>
                            <span v-else>{{ formatCurrency(selectedTransfer.amount, selectedTransfer.currency) }}</span>
                        </div>
                        <div><span class="font-medium text-gray-600">{{ $t('transfers.fee') }}:</span> {{
                            selectedTransfer.fee ?
                                formatCurrency(selectedTransfer.fee, selectedTransfer.currency) : '—' }}</div>
                        <div><span class="font-medium text-gray-600">{{ $t('common.status') }}:</span> {{
                            $t('transfers.' +
                                selectedTransfer.status) }}</div>
                        <div><span class="font-medium text-gray-600">{{ $t('common.createdAt') }}:</span> {{
                            formatDate(selectedTransfer.createdAt) }}</div>
                        <div v-if="selectedTransfer.authorizedBy" class="col-span-2"><span
                                class="font-medium text-gray-600">{{
                                    $t('recharges.authorizedBy') }}:</span> {{ selectedTransfer.authorizedBy }}</div>
                        <div v-if="selectedTransfer.notes" class="col-span-2"><span class="font-medium text-gray-600">{{
                            $t('common.notes') }}:</span> {{ selectedTransfer.notes }}</div>
                    </div>
                    <template #footer>
                        <Button :label="$t('common.close')" class="p-button-text" @click="showDetailModal = false" />
                    </template>
                </Dialog>

                <ConfirmDialog />
                <Toast />
            </div>
        </template>
    </Card>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
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
import Toast from 'primevue/toast';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import common from '../../common/common.js';
import Card from 'primevue/card';

const { t } = useI18n();
const confirm = useConfirm();
const toast = useToast();

const urls = document.getElementById('vue-app').dataset;
const onAjaxError = (jqXHR, textStatus, errorThrown) => {
    toast.add({ severity: 'error', summary: t('common.error'), detail: jqXHR?.responseJSON?.message || errorThrown || textStatus });
};

const transfers = ref([]);
const accounts = ref([]);
const currencyOptions = ref([]);
const loading = ref(false);
const saving = ref(false);
const actionLoading = ref(false);
const showModal = ref(false);
const showDetailModal = ref(false);
const limits = ref(null);
const selectedTransfer = ref(null);
const conversionResult = ref(null);
const calculating = ref(false);

const filters = ref({ status: '', search: '' });

const statusOptions = [
    { label: t('common.all'), value: '' },
    { label: t('transfers.pending'), value: 'pending' },
    { label: t('transfers.completed'), value: 'completed' },
    { label: t('transfers.failed'), value: 'failed' },
    { label: t('transfers.cancelled'), value: 'cancelled' },
];

const form = ref({
    originAccountId: '',
    destinationAccountNumber: '',
    amount: null,
    currency: 'USD',
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

const loadLimits = () => {
    if (!form.value.originAccountId) { limits.value = null; return; }
    common.ajax(urls.limitsUrl.replace('__ID__', form.value.originAccountId), 'GET', null, (data) => {
        if (data.success) limits.value = data.data;
    }, () => { limits.value = null; });
};

// Excluye la cuenta origen seleccionada para que no se pueda elegir como destino (evita el
// ciclo de transferirse a sí misma).
const destinationAccounts = computed(() => accounts.value.filter(a => a.id !== form.value.originAccountId));

const onOriginChange = () => {
    loadLimits();
    const originAccount = accounts.value.find(a => a.id === form.value.originAccountId);
    if (originAccount && originAccount.accountNumber === form.value.destinationAccountNumber) {
        form.value.destinationAccountNumber = '';
    }
};

const loadTransfers = () => {
    loading.value = true;
    const params = {};
    if (filters.value.status) params.status = filters.value.status;

    common.ajax(urls.listUrl, 'GET', params, (data) => {
        if (data.success) {
            transfers.value = data.data;
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
    form.value = { originAccountId: '', destinationAccountNumber: '', amount: null, currency: 'USD', notes: '', originalAmount: null, originalCurrency: null };
    limits.value = null;
    conversionResult.value = null;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
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

const saveTransfer = () => {
    saving.value = true;
    const payload = {
        originAccountId: form.value.originAccountId,
        destinationAccountNumber: form.value.destinationAccountNumber,
        amount: String(form.value.amount),
        currency: form.value.currency,
        notes: form.value.notes,
        originalAmount: form.value.originalAmount,
        originalCurrency: form.value.originalCurrency,
    };
    common.ajax(urls.createUrl, 'POST', JSON.stringify(payload), (data) => {
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: data.message });
            closeModal();
            loadTransfers();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
        saving.value = false;
    }, (...args) => {
        onAjaxError(...args);
        saving.value = false;
    });
};

const processTransfer = (transfer) => {
    confirm.require({
        message: `¿Procesar transferencia de ${formatCurrency(transfer.amount, transfer.currency)}?`,
        header: 'Confirmar',
        icon: 'pi pi-question-circle',
        acceptClass: 'p-button-success',
        accept: () => {
            actionLoading.value = true;
            common.ajax(urls.processUrl.replace('__ID__', transfer.id), 'PUT', null, (data) => {
                if (data.success) {
                    toast.add({ severity: 'success', summary: t('common.success'), detail: data.message });
                    loadTransfers();
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

const cancelTransfer = (transfer) => {
    confirm.require({
        message: `¿Cancelar transferencia de ${formatCurrency(transfer.amount, transfer.currency)}?`,
        header: 'Confirmar',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: () => {
            actionLoading.value = true;
            common.ajax(urls.cancelUrl.replace('__ID__', transfer.id), 'PUT', null, (data) => {
                if (data.success) {
                    toast.add({ severity: 'success', summary: t('common.success'), detail: data.message });
                    loadTransfers();
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

const showDetail = (transfer) => {
    selectedTransfer.value = transfer;
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
    loadTransfers();
});
</script>
