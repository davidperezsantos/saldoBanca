<template>
    <Card>
        <template #title class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-800">
                {{ $t('accounts.title') }}
            </h1>
        </template>
        <template #content>

            <div class="container mx-auto px-4 py-8">
                <div class="">
                    <h1 class="text-2xl font-bold text-gray-800">

                    </h1>
                </div>

                <div class="card mb-6">
                    <div class="flex flex-wrap gap-4 items-center justify-between">

                        <Button v-if="common.can('clients:create')" :label="$t('accounts.create')" icon="pi pi-plus" @click="openCreateModal" />
                        <div class="flex gap-2">
                            <Select v-model="filterType" :options="typeOptions" optionLabel="label" optionValue="value"
                                class="w-48" @change="loadAccounts" />
                            <InputText v-model="search" :placeholder="$t('common.search')"
                                @keyup.enter="loadAccounts" />
                            <Button :label="$t('common.search')" @click="loadAccounts" />
                        </div>
                    </div>
                </div>
                <DataTable :value="accounts" :loading="loading" size="small" responsiveLayout="scroll" :paginator="true"
                    :rows="10">
                    <Column field="accountNumber" :header="$t('accounts.accountNumber')" />
                    <Column field="businessName" :header="$t('accounts.businessName')" />
                    <Column field="documentType" :header="$t('accounts.documentType')" />
                    <Column field="documentNumber" :header="$t('accounts.documentNumber')" />
                    <Column field="email" :header="$t('accounts.email')" />
                    <Column field="phone" :header="$t('accounts.phone')" />
                    <Column field="status" :header="$t('common.status')">
                        <template #body="{ data }">
                            <span
                                :class="['px-2 py-1 text-xs rounded-full', data.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800']">
                                {{ data.status === 'active' ? $t('common.active') : $t('common.inactive') }}
                            </span>
                        </template>
                    </Column>
                    <Column field="defaultCurrency" :header="$t('accounts.currency')" />
                    <Column field="saldoDisponible" :header="$t('accounts.availableBalance')" style="width: 130px">
                        <template #body="{ data }">
                            <span class="font-semibold"
                                :class="parseFloat(data.saldoDisponible) > 0 ? 'text-emerald-600' : 'text-gray-500'">
                                {{ formatCurrency(data.saldoDisponible, data.defaultCurrency) }}
                            </span>
                        </template>
                    </Column>
                    <Column :header="$t('common.actions')" style="width: 12rem">
                        <template #body="{ data }">
                            <div class="flex gap-2">
                                <Button v-if="common.can('clients:edit')" icon="pi pi-pencil" class="p-button-rounded p-button-warning p-button-text"
                                    @click="editAccount(data)" />
                                <Button v-if="data.accountType === 'business' && common.can('payout_accounts:view')" icon="pi pi-building-columns"
                                    class="p-button-rounded p-button-info p-button-text" title="Cuentas de pago"
                                    @click="openPayoutModal(data)" />
                                <Button v-if="common.can('clients:status')" :icon="data.status === 'active' ? 'pi pi-power-off' : 'pi pi-check-circle'"
                                    :class="['p-button-rounded p-button-text', data.status === 'active' ? 'p-button-danger' : 'p-button-success']"
                                    @click="toggleStatus(data)" />
                            </div>
                        </template>
                    </Column>
                </DataTable>

                <Dialog v-model:visible="showModal" :header="editingId ? $t('accounts.edit') : $t('accounts.create')"
                    :modal="true" :style="{ width: '550px' }">
                    <div class="flex flex-col gap-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="form-group">
                                <label class="form-label">{{ $t('accounts.accountType') }}</label>
                                <Select v-model="form.accountType" :options="typeOptions.filter(t => t.value)"
                                    optionLabel="label" optionValue="value" class="w-full"
                                    @change="onAccountTypeChange" />
                            </div>
                            <div class="form-group">
                                <label class="form-label">{{ $t('accounts.documentType') }}</label>
                                <Select v-model="form.documentType" :options="documentTypeOptions" optionLabel="label"
                                    optionValue="value" class="w-full" />
                            </div>
                        </div>

                        <div v-if="form.accountType === 'business'" class="form-group">
                            <label class="form-label">{{ $t('accounts.businessName') }}</label>
                            <InputText v-model="form.businessName" class="w-full" />
                        </div>
                        <div v-else class="form-group">
                            <label class="form-label">{{ $t('accounts.fullName') }}</label>
                            <InputText v-model="form.fullName" class="w-full" />
                        </div>

                        <div class="form-group">
                            <label class="form-label">{{ $t('accounts.documentNumber') }}</label>
                            <InputText v-model="form.documentNumber" class="w-full" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="form-group">
                                <label class="form-label">{{ $t('accounts.email') }}</label>
                                <InputText v-model="form.email" class="w-full" type="email" />
                            </div>
                            <div class="form-group">
                                <label class="form-label">{{ $t('accounts.phone') }}</label>
                                <InputGroup>
                                    <Select v-model="form.phoneCountry" :options="countryCodes" optionLabel="name"
                                        class="w-12rem">
                                        <template #value="slotProps">
                                            <div v-if="slotProps.value" class="flex align-items-center gap-2">
                                                <img :alt="slotProps.value.code"
                                                    :src="`https://flagcdn.com/24x18/${slotProps.value.code}.png`"
                                                    style="width: 18px; height: 14px" />
                                                <span class="text-sm font-mono">{{ slotProps.value.dial }}</span>
                                            </div>
                                        </template>
                                        <template #option="slotProps">
                                            <div class="flex align-items-center gap-2">
                                                <img :alt="slotProps.option.code"
                                                    :src="`https://flagcdn.com/24x18/${slotProps.option.code}.png`"
                                                    style="width: 18px; height: 14px" />
                                                <span class="text-sm">{{ slotProps.option.name }}</span>
                                                <span class="text-xs text-gray-400 ml-auto font-mono">{{
                                                    slotProps.option.dial }}</span>
                                            </div>
                                        </template>
                                    </Select>
                                    <InputText v-model="form.phone" class="w-full" type="tel" placeholder="4121234567"
                                        @keypress="onlyNumbers" />
                                </InputGroup>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">{{ $t('accounts.currency') }}</label>
                            <Select v-model="form.defaultCurrency" :options="currencyOptions" optionLabel="label"
                                optionValue="value" class="w-full" />
                        </div>
                        <div v-if="form.accountType === 'business'" class="form-group">
                            <label class="form-label">Split de pago en conciliaciones (opcional)</label>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="form-group">
                                    <label class="form-label text-sm">{{ systemCurrencies.currency || 'Moneda base' }} (%)</label>
                                    <InputText :model-value="form.payoutCurrencyPercent"
                                        @update:model-value="onCurrencyPercentInput" class="w-full" type="number"
                                        min="0" max="100" step="0.01" />
                                </div>
                                <div class="form-group">
                                    <label class="form-label text-sm">{{ systemCurrencies.currencySecondary || 'Moneda secundaria' }} (%)</label>
                                    <InputText :model-value="form.payoutSecondaryCurrencyPercent"
                                        @update:model-value="onSecondaryPercentInput" class="w-full" type="number"
                                        min="0" max="100" step="0.01" />
                                </div>
                            </div>
                            <p v-if="form.payoutCurrencyPercent || form.payoutSecondaryCurrencyPercent" class="text-xs mt-1"
                                :class="payoutSum === 100 ? 'text-emerald-600' : 'text-red-600'">
                                Suma: {{ payoutSum }}%
                            </p>
                        </div>
                    </div>
                    <template #footer>
                        <Button :label="$t('common.cancel')" class="p-button-text" @click="closeModal" />
                        <Button :label="$t('common.save')" @click="saveAccount" :loading="saving" />
                    </template>
                </Dialog>

                <Dialog v-model:visible="showPayoutModal" header="Cuentas de pago del negocio" :modal="true" :style="{ width: '700px' }">
                    <div class="flex flex-col gap-4">
                        <DataTable :value="payoutAccounts" :loading="payoutLoading" size="small">
                            <Column field="alias" header="Alias" />
                            <Column field="currency" header="Moneda" style="width: 6rem" />
                            <Column field="accountNumber" header="No. de cuenta" />
                            <Column field="bankName" header="Banco" />
                            <Column field="isActive" header="Activa" style="width: 5rem">
                                <template #body="{ data }">
                                    <span :class="['px-2 py-1 text-xs rounded-full', data.isActive ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800']">
                                        {{ data.isActive ? 'Sí' : 'No' }}
                                    </span>
                                </template>
                            </Column>
                            <Column style="width: 6rem">
                                <template #body="{ data }">
                                    <div class="flex gap-1">
                                        <Button v-if="common.can('payout_accounts:edit')" icon="pi pi-pencil"
                                            class="p-button-rounded p-button-warning p-button-text"
                                            @click="editPayoutAccount(data)" />
                                        <Button v-if="common.can('payout_accounts:delete')" icon="pi pi-trash"
                                            class="p-button-rounded p-button-danger p-button-text"
                                            @click="deletePayoutAccount(data)" />
                                    </div>
                                </template>
                            </Column>
                        </DataTable>

                        <div v-if="common.can('payout_accounts:create') || common.can('payout_accounts:edit')" class="card p-4 border rounded-lg">
                            <p class="font-semibold mb-3">{{ payoutForm.id ? 'Editar cuenta de pago' : 'Nueva cuenta de pago' }}</p>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="form-group">
                                    <label class="form-label">Alias</label>
                                    <InputText v-model="payoutForm.alias" class="w-full" placeholder="Ej: Cuenta principal USD" />
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Moneda</label>
                                    <Select v-model="payoutForm.currency" :options="currencyOptions" optionLabel="label"
                                        optionValue="value" class="w-full" />
                                </div>
                                <div class="form-group">
                                    <label class="form-label">No. de cuenta</label>
                                    <InputText v-model="payoutForm.accountNumber" class="w-full" />
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Banco</label>
                                    <InputText v-model="payoutForm.bankName" class="w-full" />
                                </div>
                                <div class="form-group">
                                    <label class="form-label">SWIFT</label>
                                    <InputText v-model="payoutForm.swift" class="w-full" />
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Titular</label>
                                    <InputText v-model="payoutForm.accountHolder" class="w-full" />
                                </div>
                            </div>
                            <div class="flex justify-end gap-2 mt-3">
                                <Button v-if="payoutForm.id" :label="$t('common.cancel')" class="p-button-text" @click="resetPayoutForm" />
                                <Button :label="$t('common.save')" @click="savePayoutAccount" :loading="payoutSaving" />
                            </div>
                        </div>
                    </div>
                    <template #footer>
                        <Button :label="$t('common.close')" class="p-button-text" @click="showPayoutModal = false" />
                    </template>
                </Dialog>
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
import Select from 'primevue/select';
import InputGroup from 'primevue/inputgroup';
import Dialog from 'primevue/dialog';
import Card from 'primevue/card';
import { useToast } from 'primevue/usetoast';
import countries from '../../../static/countries.json';
import typeDocumentOptions from '../../../static/type_document.json';
import common from '../../common/common.js';

const { t } = useI18n();
const toast = useToast();

const urls = document.getElementById('vue-app').dataset;
const onAjaxError = (jqXHR, textStatus, errorThrown) => {
    toast.add({ severity: 'error', summary: t('common.error'), detail: jqXHR?.responseJSON?.message || errorThrown || textStatus });
};

const accounts = ref([]);
const currencyOptions = ref([]);
const systemCurrencies = ref({ currency: '', currencySecondary: '' });
const loading = ref(false);
const saving = ref(false);
const showModal = ref(false);
const editingId = ref(null);
const search = ref('');
const filterType = ref('');
const countryCodes = countries;
const documentTypeOptions = typeDocumentOptions;

const typeOptions = [
    { label: 'Todos', value: '' },
    { label: 'Cliente', value: 'personal' },
    { label: 'Negocio', value: 'business' },
];

const form = ref({
    accountType: 'personal',
    businessName: '',
    fullName: '',
    documentType: 'CI',
    documentNumber: '',
    email: '',
    phone: '',
    phoneCountry: countryCodes[0],
    defaultCurrency: 'USD',
    payoutCurrencyPercent: null,
    payoutSecondaryCurrencyPercent: null,
});

const payoutSum = computed(() => {
    const a = parseFloat(form.value.payoutCurrencyPercent) || 0;
    const b = parseFloat(form.value.payoutSecondaryCurrencyPercent) || 0;
    return Math.round((a + b) * 100) / 100;
});

const complementPercent = (value) => {
    const num = parseFloat(value);
    if (value === '' || value === null || isNaN(num)) return null;
    const complement = Math.round((100 - num) * 100) / 100;
    return String(Math.min(100, Math.max(0, complement)));
};

const onCurrencyPercentInput = (value) => {
    form.value.payoutCurrencyPercent = value;
    form.value.payoutSecondaryCurrencyPercent = complementPercent(value);
};

const onSecondaryPercentInput = (value) => {
    form.value.payoutSecondaryCurrencyPercent = value;
    form.value.payoutCurrencyPercent = complementPercent(value);
};

const loadAccounts = () => {
    loading.value = true;
    const params = {};
    if (search.value) params.search = search.value;
    if (filterType.value) params.accountType = filterType.value;

    common.ajax(urls.listUrl, 'GET', params, (data) => {
        if (data.success) {
            accounts.value = data.data;
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
        loading.value = false;
    }, (...args) => {
        onAjaxError(...args);
        loading.value = false;
    });
};

// Solo ofrece en el select las monedas activas del nomenclador (antes era un array fijo
// [USD, EUR, VES, COP] hardcodeado en el propio componente).
const loadCurrencies = () => {
    common.ajax(urls.currenciesListUrl, 'GET', { active: 1 }, (data) => {
        if (data.success) currencyOptions.value = data.data.map(c => ({ label: c.code, value: c.code }));
    }, onAjaxError);
};

const loadSystemCurrencies = () => {
    common.ajax(urls.systemCurrenciesUrl, 'GET', {}, (data) => {
        if (data.success) systemCurrencies.value = data.data;
    }, onAjaxError);
};

const openCreateModal = () => {
    editingId.value = null;
    const type = filterType.value || 'personal';
    form.value = {
        accountType: type,
        businessName: '',
        fullName: '',
        documentType: type === 'personal' ? 'CI' : 'NIT',
        documentNumber: '',
        email: '',
        phone: '',
        phoneCountry: countryCodes[0],
        defaultCurrency: 'USD',
        payoutCurrencyPercent: null,
        payoutSecondaryCurrencyPercent: null,
    };
    showModal.value = true;
};

const editAccount = (account) => {
    editingId.value = account.id;
    const phoneRaw = account.phone || '';
    const matchedCountry = countryCodes.find(c => phoneRaw.startsWith(c.dial)) || countryCodes[0];
    const phone = matchedCountry ? phoneRaw.slice(matchedCountry.dial.length) : phoneRaw;
    form.value = {
        accountType: account.accountType,
        businessName: account.accountType === 'business' ? account.businessName : '',
        fullName: account.accountType === 'personal' ? account.businessName : '',
        documentType: account.documentType,
        documentNumber: account.documentNumber,
        email: account.email || '',
        phone: phone,
        phoneCountry: matchedCountry,
        defaultCurrency: account.defaultCurrency,
        payoutCurrencyPercent: account.payoutCurrencyPercent,
        payoutSecondaryCurrencyPercent: account.payoutSecondaryCurrencyPercent,
    };
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingId.value = null;
};

const onAccountTypeChange = () => {
    if (form.value.accountType === 'personal' && form.value.documentType === 'NIT') {
        form.value.documentType = 'CI';
    } else if (form.value.accountType === 'business' && form.value.documentType === 'CI') {
        form.value.documentType = 'NIT';
    }
};

const saveAccount = () => {
    saving.value = true;
    const url = editingId.value ? urls.detailUrl.replace('__ID__', editingId.value) : urls.createUrl;
    const method = editingId.value ? 'PUT' : 'POST';

    const payload = {
        ...form.value,
        businessName: form.value.accountType === 'personal'
            ? form.value.fullName
            : form.value.businessName,
        phone: form.value.phone ? `${form.value.phoneCountry.dial}${form.value.phone}` : '',
        payoutCurrencyPercent: form.value.payoutCurrencyPercent === '' ? null : form.value.payoutCurrencyPercent,
        payoutSecondaryCurrencyPercent: form.value.payoutSecondaryCurrencyPercent === '' ? null : form.value.payoutSecondaryCurrencyPercent,
    };

    common.ajax(url, method, JSON.stringify(payload), (data) => {
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: data.message });
            closeModal();
            loadAccounts();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
        saving.value = false;
    }, (...args) => {
        onAjaxError(...args);
        saving.value = false;
    });
};

const formatCurrency = (value, currency = 'USD') => {
    const num = parseFloat(value) || 0;
    return new Intl.NumberFormat('es-US', { style: 'currency', currency }).format(num);
};

const onlyNumbers = (e) => {
    if (!/[0-9]/.test(e.key) && e.key !== 'Backspace' && e.key !== 'Tab' && e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') {
        e.preventDefault();
    }
};

const showPayoutModal = ref(false);
const payoutLoading = ref(false);
const payoutSaving = ref(false);
const payoutAccounts = ref([]);
const payoutAccountId = ref(null);
const emptyPayoutForm = () => ({
    id: null,
    alias: '',
    currency: 'USD',
    accountNumber: '',
    bankName: '',
    swift: '',
    accountHolder: '',
});
const payoutForm = ref(emptyPayoutForm());

const loadPayoutAccounts = () => {
    payoutLoading.value = true;
    common.ajax(urls.payoutAccountsUrl.replace('__ACCOUNT_ID__', payoutAccountId.value), 'GET', {}, (data) => {
        if (data.success) {
            payoutAccounts.value = data.data;
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
        payoutLoading.value = false;
    }, (...args) => {
        onAjaxError(...args);
        payoutLoading.value = false;
    });
};

const openPayoutModal = (account) => {
    payoutAccountId.value = account.id;
    resetPayoutForm();
    showPayoutModal.value = true;
    loadPayoutAccounts();
};

const resetPayoutForm = () => {
    payoutForm.value = emptyPayoutForm();
};

const editPayoutAccount = (payoutAccount) => {
    payoutForm.value = { ...payoutAccount };
};

const savePayoutAccount = () => {
    payoutSaving.value = true;
    const base = urls.payoutAccountsUrl.replace('__ACCOUNT_ID__', payoutAccountId.value);
    const url = payoutForm.value.id ? `${base}/${payoutForm.value.id}` : base;
    const method = payoutForm.value.id ? 'PUT' : 'POST';

    common.ajax(url, method, JSON.stringify(payoutForm.value), (data) => {
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: data.message });
            resetPayoutForm();
            loadPayoutAccounts();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
        payoutSaving.value = false;
    }, (...args) => {
        onAjaxError(...args);
        payoutSaving.value = false;
    });
};

const deletePayoutAccount = (payoutAccount) => {
    if (!confirm(`¿Eliminar la cuenta de pago "${payoutAccount.alias}"?`)) return;

    const url = `${urls.payoutAccountsUrl.replace('__ACCOUNT_ID__', payoutAccountId.value)}/${payoutAccount.id}`;
    common.ajax(url, 'DELETE', {}, (data) => {
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: data.message });
            loadPayoutAccounts();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
    }, onAjaxError);
};

const toggleStatus = (account) => {
    const newStatus = account.status === 'active' ? 'inactive' : 'active';
    if (!confirm(`¿${newStatus === 'active' ? 'Activar' : 'Desactivar'} "${account.businessName}"?`)) return;

    common.ajax(urls.statusUrl.replace('__ID__', account.id), 'PUT', JSON.stringify({ status: newStatus }), (data) => {
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: 'Estado cambiado' });
            loadAccounts();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
    }, onAjaxError);
};

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    filterType.value = params.get('type') || '';
    loadAccounts();
    loadCurrencies();
    loadSystemCurrencies();
});
</script>
