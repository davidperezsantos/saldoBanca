<template>
    <Card>
        <template #title>
            <h1 class="text-2xl font-bold text-gray-800">{{ $t('invoices.title') }}</h1>
        </template>
        <template #content>
            <div class="container mx-auto px-4 py-8">
                <div class="card mb-6">
                    <div class="flex flex-wrap gap-4 items-center justify-between">
                        <Button v-if="common.can('invoices:create')" :label="$t('invoices.create')" icon="pi pi-plus" @click="openCreateModal" />
                        <div class="flex gap-2">
                            <Select v-model="filters.status" :options="statusOptions" optionLabel="label"
                                optionValue="value" class="w-40" :placeholder="$t('common.status')"
                                @change="loadInvoices" />
                            <InputText v-model="filters.search" :placeholder="$t('common.search')"
                                @keyup.enter="loadInvoices" />
                            <Button :label="$t('common.search')" @click="loadInvoices" />
                        </div>
                    </div>
                </div>
                <DataTable :value="invoices" :loading="loading" size="small" :paginator="true" :rows="10"
                    responsiveLayout="scroll">
                    <Column field="receiptNumber" :header="$t('common.code')" />
                    <Column field="invoiceNumber" :header="$t('invoices.invoiceNumber')" />
                    <Column field="accountNumber" :header="$t('accounts.accountNumber')" />
                    <Column field="accountName" :header="$t('invoices.client')" />
                    <Column field="businessAccountName" :header="$t('invoices.business')">
                        <template #body="{ data }">
                            {{ data.businessAccountName || '—' }}
                        </template>
                    </Column>
                    <Column field="totalAmount" :header="$t('invoices.totalAmount')">
                        <template #body="{ data }">
                            {{ formatCurrency(data.totalAmount, data.currency) }}
                        </template>
                    </Column>
                    <Column field="status" :header="$t('common.status')">
                        <template #body="{ data }">
                            <span :class="['px-2 py-1 text-xs rounded-full font-medium',
                                data.status === 'paid' ? 'bg-emerald-100 text-emerald-800' :
                                    data.status === 'pending' ? 'bg-amber-100 text-amber-800' :
                                        data.status === 'cancelled' ? 'bg-gray-100 text-gray-800' :
                                            'bg-red-100 text-red-800']">
                                {{ $t('invoices.' + data.status) }}
                            </span>
                        </template>
                    </Column>
                    <Column field="dueDate" :header="$t('invoices.dueDate')">
                        <template #body="{ data }">
                            {{ data.dueDate || '—' }}
                        </template>
                    </Column>
                    <Column field="paymentDate" :header="$t('invoices.paymentDate')">
                        <template #body="{ data }">
                            {{ data.paymentDate || '—' }}
                        </template>
                    </Column>
                    <Column :header="$t('common.actions')" style="width: 12rem">
                        <template #body="{ data }">
                            <div class="flex gap-1">
                                <Button icon="pi pi-dollar" class="p-button-rounded p-button-success p-button-text"
                                    v-if="data.status === 'pending' && common.can('invoices:pay')" @click="payInvoice(data)"
                                    :disabled="actionLoading" />
                                <Button icon="pi pi-ban" class="p-button-rounded p-button-danger p-button-text"
                                    v-if="data.status === 'paid' && common.can('invoices:cancel')" @click="cancelInvoice(data)"
                                    :disabled="actionLoading" />
                                <Button icon="pi pi-refresh" class="p-button-rounded p-button-info p-button-text"
                                    v-if="data.status === 'paid' && common.can('invoices:refund')" @click="refundInvoice(data)"
                                    :disabled="actionLoading" />
                                <Button v-if="common.can('invoices:details')" icon="pi pi-eye" class="p-button-rounded p-button-text"
                                    @click="showDetail(data)" />
                            </div>
                        </template>
                    </Column>
                </DataTable>

                <Dialog v-model:visible="showModal" :header="$t('invoices.create')" :modal="true"
                    :style="{ width: '550px' }">
                    <div class="flex flex-col gap-4">
                        <div class="form-group">
                            <label class="form-label">{{ $t('invoices.client') }}</label>
                            <Select v-model="form.accountId" :options="accounts" optionLabel="businessName"
                                optionValue="id" class="w-full" filter />
                        </div>
                        <div class="form-group">
                            <label class="form-label">{{ $t('invoices.business') }}</label>
                            <Select v-model="form.businessAccountId" :options="businessAccounts"
                                optionLabel="businessName" optionValue="id" class="w-full" filter showClear
                                :placeholder="$t('invoices.businessOptional')" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="form-group">
                                <label class="form-label">{{ $t('invoices.invoiceNumber') }}</label>
                                <InputText v-model="form.invoiceNumber" class="w-full" />
                            </div>
                            <div class="form-group">
                                <label class="form-label">{{ $t('recharges.currency') }}</label>
                                <Select v-model="form.currency" :options="currencyOptions" optionLabel="label"
                                    optionValue="value" class="w-full" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="form-group">
                                <label class="form-label">{{ $t('invoices.invoiceDate') }}</label>
                                <InputText v-model="form.invoiceDate" class="w-full" type="date" />
                            </div>
                            <div class="form-group">
                                <label class="form-label">{{ $t('invoices.dueDate') }}</label>
                                <InputText v-model="form.dueDate" class="w-full" type="date" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="form-group">
                                <label class="form-label">{{ $t('invoices.amount') }}</label>
                                <InputNumber v-model="form.amount" class="w-full" :min="0" />
                            </div>
                            <div class="form-group">
                                <label class="form-label">{{ $t('invoices.taxAmount') }}</label>
                                <InputNumber v-model="form.taxAmount" class="w-full" :min="0" />
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
                                    <span class="text-gray-600">Monto de la factura ({{ conversionResult.baseCurrency
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

                        <div class="form-group">
                            <label class="form-label">{{ $t('common.notes') }}</label>
                            <Textarea v-model="form.notes" class="w-full" rows="2" />
                        </div>
                    </div>
                    <template #footer>
                        <Button :label="$t('common.cancel')" class="p-button-text" @click="closeModal" />
                        <Button :label="$t('common.save')" @click="saveInvoice" :loading="saving" />
                    </template>
                </Dialog>

                <Dialog v-model:visible="showDetailModal" :header="$t('common.details')" :modal="true"
                    :style="{ width: '600px' }">
                    <div v-if="selectedInvoice" class="grid grid-cols-2 gap-4 text-sm">
                        <div><span class="font-medium text-gray-600">{{ $t('invoices.invoiceNumber') }}:</span> {{
                            selectedInvoice.invoiceNumber }}</div>
                        <div><span class="font-medium text-gray-600">{{ $t('accounts.accountNumber') }}:</span> {{
                            selectedInvoice.accountNumber }}</div>
                        <div><span class="font-medium text-gray-600">{{ $t('invoices.client') }}:</span> {{
                            selectedInvoice.accountName
                            }}</div>
                        <div><span class="font-medium text-gray-600">{{ $t('invoices.business') }}:</span> {{
                            selectedInvoice.businessAccountName || '—' }}</div>
                        <div><span class="font-medium text-gray-600">{{ $t('invoices.amount') }}:</span>
                            <span v-if="selectedInvoice.originalAmount && selectedInvoice.originalCurrency">
                                {{ formatCurrency(selectedInvoice.originalAmount, selectedInvoice.originalCurrency) }} →
                                {{
                                    formatCurrency(selectedInvoice.amount, selectedInvoice.currency) }}
                                <span v-if="selectedInvoice.exchangeRate" class="text-xs text-gray-400">(tasa: {{
                                    selectedInvoice.exchangeRate }})</span>
                            </span>
                            <span v-else>{{ formatCurrency(selectedInvoice.amount, selectedInvoice.currency) }}</span>
                        </div>
                        <div><span class="font-medium text-gray-600">{{ $t('invoices.taxAmount') }}:</span> {{
                            formatCurrency(selectedInvoice.taxAmount, selectedInvoice.currency) }}</div>
                        <div><span class="font-medium text-gray-600">{{ $t('invoices.totalAmount') }}:</span> {{
                            formatCurrency(selectedInvoice.totalAmount, selectedInvoice.currency) }}</div>
                        <div><span class="font-medium text-gray-600">{{ $t('common.status') }}:</span> {{ $t('invoices.'
                            +
                            selectedInvoice.status) }}</div>
                        <div><span class="font-medium text-gray-600">{{ $t('invoices.invoiceDate') }}:</span> {{
                            selectedInvoice.invoiceDate }}</div>
                        <div><span class="font-medium text-gray-600">{{ $t('invoices.dueDate') }}:</span> {{
                            selectedInvoice.dueDate ||
                            '—' }}</div>
                        <div v-if="selectedInvoice.paymentDate"><span class="font-medium text-gray-600">{{
                                $t('invoices.paymentDate')
                                }}:</span> {{ selectedInvoice.paymentDate }}</div>
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
import Toast from 'primevue/toast';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import Card from 'primevue/card';
import common from '../../common/common.js';

const { t } = useI18n();
const confirm = useConfirm();
const toast = useToast();

const urls = document.getElementById('vue-app').dataset;
const onAjaxError = (jqXHR, textStatus, errorThrown) => {
    toast.add({ severity: 'error', summary: t('common.error'), detail: jqXHR?.responseJSON?.message || errorThrown || textStatus });
};

const invoices = ref([]);
const accounts = ref([]);
const businessAccounts = ref([]);
const currencyOptions = ref([]);
const loading = ref(false);
const saving = ref(false);
const actionLoading = ref(false);
const showModal = ref(false);
const showDetailModal = ref(false);
const selectedInvoice = ref(null);
const conversionResult = ref(null);
const calculating = ref(false);

const filters = ref({ status: '', search: '' });

const statusOptions = [
    { label: t('common.all'), value: '' },
    { label: t('invoices.pending'), value: 'pending' },
    { label: t('invoices.paid'), value: 'paid' },
    { label: t('invoices.cancelled'), value: 'cancelled' },
    { label: t('invoices.refunded'), value: 'refunded' },
];

const form = ref({
    accountId: '',
    businessAccountId: null,
    invoiceNumber: '',
    invoiceDate: new Date().toISOString().split('T')[0],
    dueDate: '',
    amount: null,
    taxAmount: null,
    totalAmount: null,
    currency: 'USD',
    notes: '',
    originalAmount: null,
    originalCurrency: null,
});

// Cliente: a quien se le descuenta el saldo — puede ser cualquier tipo de cuenta.
const loadAccounts = () => {
    common.ajax(urls.accountsListUrl, 'GET', { limit: 200 }, (data) => {
        if (data.success) accounts.value = data.data;
    }, onAjaxError);
};

// Negocio: quien realizó la operación — opcional, y solo se ofrecen cuentas accountType=business
// (a diferencia del select de Cliente, que muestra todas).
const loadBusinessAccounts = () => {
    common.ajax(urls.accountsListUrl, 'GET', { accountType: 'business', limit: 200 }, (data) => {
        if (data.success) businessAccounts.value = data.data;
    }, onAjaxError);
};

// Solo ofrece en el select las monedas activas del nomenclador (antes era un array fijo
// [USD, EUR, VES, COP] hardcodeado en el propio componente).
const loadCurrencies = () => {
    common.ajax(urls.currenciesListUrl, 'GET', { active: 1 }, (data) => {
        if (data.success) currencyOptions.value = data.data.map(c => ({ label: c.code, value: c.code }));
    }, onAjaxError);
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

const loadInvoices = () => {
    loading.value = true;
    const params = {};
    if (filters.value.status) params.status = filters.value.status;

    common.ajax(urls.listUrl, 'GET', params, (data) => {
        if (data.success) {
            invoices.value = data.data;
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
    form.value = {
        accountId: '', businessAccountId: null, invoiceNumber: '', invoiceDate: new Date().toISOString().split('T')[0],
        dueDate: '', amount: null, taxAmount: null, totalAmount: null,
        currency: 'USD', notes: '', originalAmount: null, originalCurrency: null,
    };
    conversionResult.value = null;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    conversionResult.value = null;
};

const saveInvoice = () => {
    saving.value = true;
    const payload = {
        accountId: form.value.accountId,
        businessAccountId: form.value.businessAccountId || null,
        invoiceNumber: form.value.invoiceNumber,
        invoiceDate: form.value.invoiceDate,
        dueDate: form.value.dueDate || null,
        amount: String(form.value.amount || '0'),
        taxAmount: form.value.taxAmount ? String(form.value.taxAmount) : null,
        totalAmount: String(form.value.totalAmount || form.value.amount || '0'),
        currency: form.value.currency,
        notes: form.value.notes || null,
        originalAmount: form.value.originalAmount,
        originalCurrency: form.value.originalCurrency,
    };

    common.ajax(urls.createUrl, 'POST', JSON.stringify(payload), (data) => {
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: data.message });
            closeModal();
            loadInvoices();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
        saving.value = false;
    }, (...args) => {
        onAjaxError(...args);
        saving.value = false;
    });
};

const payInvoice = (invoice) => {
    const pinCode = prompt(`Código de la cuenta para pagar la factura ${invoice.invoiceNumber} (${formatCurrency(invoice.totalAmount, invoice.currency)}):`);
    if (!pinCode) return;
    actionLoading.value = true;
    common.ajax(urls.payUrl.replace('__ID__', invoice.id), 'PUT', JSON.stringify({ pinCode }), (data) => {
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: data.message });
            loadInvoices();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
        actionLoading.value = false;
    }, (...args) => {
        onAjaxError(...args);
        actionLoading.value = false;
    });
};

const cancelInvoice = (invoice) => {
    confirm.require({
        message: `¿Cancelar factura ${invoice.invoiceNumber}?`,
        header: 'Confirmar',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: () => {
            actionLoading.value = true;
            common.ajax(urls.cancelUrl.replace('__ID__', invoice.id), 'PUT', null, (data) => {
                if (data.success) {
                    toast.add({ severity: 'success', summary: t('common.success'), detail: data.message });
                    loadInvoices();
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

const refundInvoice = (invoice) => {
    confirm.require({
        message: `¿Reembolsar factura ${invoice.invoiceNumber}?`,
        header: 'Confirmar',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-warning',
        accept: () => {
            actionLoading.value = true;
            common.ajax(urls.refundUrl.replace('__ID__', invoice.id), 'PUT', null, (data) => {
                if (data.success) {
                    toast.add({ severity: 'success', summary: t('common.success'), detail: data.message });
                    loadInvoices();
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

const showDetail = (invoice) => {
    selectedInvoice.value = invoice;
    showDetailModal.value = true;
};

const formatCurrency = (amount, currency) => {
    if (!amount) return '—';
    return new Intl.NumberFormat('es-ES', { style: 'currency', currency: currency || 'USD' }).format(amount);
};

onMounted(() => {
    loadAccounts();
    loadBusinessAccounts();
    loadCurrencies();
    loadInvoices();
});
</script>
