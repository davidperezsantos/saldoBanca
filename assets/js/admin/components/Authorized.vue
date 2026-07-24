<template>
    <Card>
        <template #title>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-200">
                {{ $t('authorized.title') }}
            </h1>
        </template>
        <template #content>
            <div class="container mx-auto px-4 py-8">

                <div class="card mb-6">
                    <div class="flex flex-wrap gap-4 items-end">
                        <Button v-if="common.can('authorized:create')" :label="$t('authorized.create')" icon="pi pi-plus" :disabled="!selectedAccount"
                            @click="openCreateModal" />
                        <div class="flex-1">
                            <Select v-model="selectedAccount" :options="accounts" optionLabel="businessName"
                                optionValue="id" :placeholder="$t('authorized.selectAccount')" class="w-full" filter
                                showClear @change="loadAuthorized" />
                        </div>
                        <div class="flex gap-2">
                            <Select v-model="filterAccountType" :options="typeOptions" optionLabel="label"
                                optionValue="value" class="w-48" @change="loadAccounts" />
                            <Button :label="$t('common.search')" @click="loadAccounts" />
                        </div>
                    </div>
                </div>

                <div v-if="selectedAccount" class="mb-4 p-3 bg-gray-50 rounded-lg flex items-center gap-6">
                    <span class="text-sm text-gray-600">{{ $t('accounts.accountNumber') }}: <strong>{{
                        selectedAccountNumber }}</strong></span>
                    <span class="text-sm text-gray-600" v-if="moneda !== baseMoneda">{{ $t('accounts.availableBalance')
                    }} ({{ moneda }}):
                        <strong :class="parseFloat(saldoDisponible) > 0 ? 'text-emerald-600' : 'text-gray-500'">
                            {{ formatCurrency(saldoDisponible, moneda) }}
                        </strong>
                    </span>
                    <span class="text-sm text-gray-600">{{ $t('accounts.availableBalance') }} ({{ baseMoneda }}):
                        <strong :class="parseFloat(saldoBase) > 0 ? 'text-emerald-600' : 'text-gray-500'">
                            {{ formatCurrency(saldoBase, baseMoneda) }}
                        </strong>
                    </span>
                </div>
                <DataTable :value="authorizedUsers" :loading="loading" size="small" responsiveLayout="scroll"
                    :paginator="true" :rows="10">
                    <Column field="userName" header="Nombre y Apellidos" sortable />
                    <Column field="userEmail" header="Correo Electrónico" />
                    <Column field="documentNumber" :header="$t('accounts.documentNumber')" />
                    <Column field="maxAmount" :header="$t('authorized.maxAmount')">
                        <template #body="{ data }">
                            {{ data.maxAmount || '—' }}
                        </template>
                    </Column>
                    <Column field="dailyLimit" :header="$t('authorized.dailyLimit')">
                        <template #body="{ data }">
                            {{ data.dailyLimit || '—' }}
                        </template>
                    </Column>
                    <Column field="monthlyLimit" :header="$t('authorized.monthlyLimit')">
                        <template #body="{ data }">
                            {{ data.monthlyLimit || '—' }}
                        </template>
                    </Column>
                    <Column field="status" :header="$t('common.status')" sortable>
                        <template #body="{ data }">
                            <span
                                :class="['px-2 py-1 text-xs rounded-full', data.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800']">
                                {{ data.status === 'active' ? $t('common.active') : $t('common.inactive') }}
                            </span>
                        </template>
                    </Column>
                    <Column :header="$t('common.actions')" style="width: 14rem">
                        <template #body="{ data }">
                            <div class="flex gap-2">
                                <Button v-if="common.can('authorized:edit')" icon="pi pi-pencil" class="p-button-rounded p-button-warning p-button-text"
                                    @click="editAuthorized(data)" />
                                <Button v-if="common.can('authorized:edit')" icon="pi pi-key" class="p-button-rounded p-button-info p-button-text"
                                    @click="resetPassword(data)" />
                                <Button v-if="common.can('authorized:status')" :icon="data.status === 'active' ? 'pi pi-power-off' : 'pi pi-check-circle'"
                                    :class="['p-button-rounded p-button-text', data.status === 'active' ? 'p-button-danger' : 'p-button-success']"
                                    @click="toggleStatus(data)" />
                            </div>
                        </template>
                    </Column>
                </DataTable>

                <Dialog v-model:visible="showModal" :header="editingId ? $t('common.edit') : $t('authorized.create')"
                    :modal="true" :style="{ width: '550px' }">
                    <div class="flex flex-col gap-4">
                        <div class="form-group" v-if="!editingId">
                            <label class="form-label">{{ $t('accounts.title') }}</label>
                            <Select v-model="selectedAccount" :options="accounts" optionLabel="businessName"
                                optionValue="id" class="w-full" filter disabled />
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nombre y Apellidos</label>
                            <InputText v-model="form.userName" class="w-full" />
                        </div>
                        <div class="form-group">
                            <label class="form-label">Correo Electrónico</label>
                            <InputText v-model="form.userEmail" class="w-full" type="email" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="form-group">
                                <label class="form-label">{{ $t('accounts.documentType') }}</label>
                                <Select v-model="form.documentType" :options="documentTypes" optionLabel="label"
                                    optionValue="value" class="w-full" />
                            </div>
                            <div class="form-group">
                                <label class="form-label">{{ $t('accounts.documentNumber') }}</label>
                                <InputText v-model="form.documentNumber" class="w-full" />
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">{{ $t('accounts.phone') }}</label>
                            <InputGroup>
                                <Select v-model="form.phoneCountry" :options="countryCodes" optionLabel="label"
                                    class="w-52" filter>
                                    <template #value="slotProps">
                                        <div class="flex items-center gap-2" v-if="slotProps.value">
                                            <img :alt="slotProps.value.code"
                                                :src="`https://flagcdn.com/24x18/${slotProps.value.code}.png`"
                                                style="width: 18px; height: 14px" />
                                            <span>{{ slotProps.value.dial }}</span>
                                        </div>
                                        <span v-else>+58</span>
                                    </template>
                                    <template #option="slotProps">
                                        <div class="flex items-center gap-2">
                                            <img :alt="slotProps.option.code"
                                                :src="`https://flagcdn.com/24x18/${slotProps.option.code}.png`"
                                                style="width: 18px; height: 14px" />
                                            <span>{{ slotProps.option.label }} ({{ slotProps.option.dial }})</span>
                                        </div>
                                    </template>
                                </Select>
                                <InputText v-model="form.phone" class="w-full" type="tel" @keypress="onlyNumbers" />
                            </InputGroup>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="form-group">
                                <label class="form-label">{{ $t('authorized.maxAmount') }}</label>
                                <InputText v-model="form.maxAmount" class="w-full" type="number" />
                            </div>
                            <div class="form-group">
                                <label class="form-label">{{ $t('authorized.dailyLimit') }}</label>
                                <InputText v-model="form.dailyLimit" class="w-full" type="number" />
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">{{ $t('authorized.monthlyLimit') }}</label>
                            <InputText v-model="form.monthlyLimit" class="w-full" type="number" />
                        </div>
                    </div>
                    <template #footer>
                        <Button :label="$t('common.cancel')" class="p-button-text" @click="closeModal" />
                        <Button :label="$t('common.save')" @click="saveAuthorized" :loading="saving" />
                    </template>
                </Dialog>
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
import InputGroup from 'primevue/inputgroup';
import Select from 'primevue/select';
import Dialog from 'primevue/dialog';
import { useToast } from 'primevue/usetoast';
import Card from 'primevue/card';
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
const authorizedUsers = ref([]);
const loading = ref(false);
const saving = ref(false);
const showModal = ref(false);
const editingId = ref(null);
const selectedAccount = ref(null);
const selectedAccountNumber = ref('');
const saldoDisponible = ref('0.00');
const moneda = ref('USD');
const saldoBase = ref('0.00');
const baseMoneda = ref('USD');
const filterAccountType = ref('');
const countryCodes = countries;
const documentTypes = typeDocumentOptions;

const typeOptions = [
    { label: 'Todos', value: '' },
    { label: 'Clientes', value: 'personal' },
    { label: 'Negocios', value: 'business' },
];

const form = ref({
    userName: '',
    userEmail: '',
    documentType: 'CC',
    documentNumber: '',
    phone: '',
    phoneCountry: countryCodes[0],
    maxAmount: '',
    dailyLimit: '',
    monthlyLimit: '',
});

const formatCurrency = (value, currency = 'USD') => {
    const num = parseFloat(value) || 0;
    return new Intl.NumberFormat('es-US', { style: 'currency', currency }).format(num);
};

const onlyNumbers = (e) => {
    if (!/[0-9]/.test(e.key)) e.preventDefault();
};

const updateAccountInfo = () => {
    const acc = accounts.value.find(a => a.id === selectedAccount.value);
    if (acc) {
        selectedAccountNumber.value = acc.accountNumber;
        saldoDisponible.value = acc.saldoDisponible || '0.00';
        moneda.value = acc.defaultCurrency || 'USD';
        saldoBase.value = acc.saldoBase || '0.00';
        baseMoneda.value = acc.baseCurrency || 'USD';
    }
};

const loadAccounts = () => new Promise((resolve) => {
    const params = { limit: 100 };
    if (filterAccountType.value) params.accountType = filterAccountType.value;

    common.ajax(urls.accountsListUrl, 'GET', params, (data) => {
        if (data.success) {
            accounts.value = data.data;
        }
        resolve();
    }, (...args) => {
        onAjaxError(...args);
        resolve();
    });
});

const loadAuthorized = () => {
    loading.value = true;
    if (selectedAccount.value) updateAccountInfo();
    const params = {};
    if (selectedAccount.value) params.accountId = selectedAccount.value;

    common.ajax(urls.listUrl, 'GET', params, (data) => {
        if (data.success) {
            authorizedUsers.value = data.data;
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
    form.value = { userName: '', userEmail: '', documentType: 'CC', documentNumber: '', phone: '', phoneCountry: countryCodes.find(c => c.dial === '+58') || countryCodes[0], maxAmount: '', dailyLimit: '', monthlyLimit: '' };
    showModal.value = true;
};

const editAuthorized = (auth) => {
    editingId.value = auth.id;
    const phoneRaw = auth.userPhone || '';
    const matchedCountry = countryCodes.find(c => phoneRaw.startsWith(c.dial)) || countryCodes[0];
    const phone = matchedCountry ? phoneRaw.slice(matchedCountry.dial.length) : phoneRaw;
    form.value = {
        userName: auth.userName,
        userEmail: auth.userEmail || '',
        documentType: auth.documentType,
        documentNumber: auth.documentNumber,
        phone: phone,
        phoneCountry: matchedCountry,
        maxAmount: auth.maxAmount || '',
        dailyLimit: auth.dailyLimit || '',
        monthlyLimit: auth.monthlyLimit || '',
    };
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingId.value = null;
};

const saveAuthorized = () => {
    saving.value = true;
    const body = {
        ...form.value,
        accountId: selectedAccount.value,
        userPhone: form.value.phone ? `${form.value.phoneCountry.dial}${form.value.phone}` : '',
    };
    delete body.phoneCountry;
    const url = editingId.value ? urls.detailUrl.replace('__ID__', editingId.value) : urls.createUrl;
    const method = editingId.value ? 'PUT' : 'POST';

    common.ajax(url, method, JSON.stringify(body), async (data) => {
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: data.message });
            closeModal();
            await loadAccounts();
            loadAuthorized();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
        saving.value = false;
    }, (...args) => {
        onAjaxError(...args);
        saving.value = false;
    });
};

const toggleStatus = (auth) => {
    const newStatus = auth.status === 'active' ? 'inactive' : 'active';
    if (!confirm(`¿${newStatus === 'active' ? 'Activar' : 'Desactivar'} a "${auth.userName}"?`)) return;

    common.ajax(urls.statusUrl.replace('__ID__', auth.id), 'PUT', JSON.stringify({ status: newStatus }), async (data) => {
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: 'Estado cambiado' });
            await loadAccounts();
            loadAuthorized();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
    }, onAjaxError);
};

const resetPassword = (auth) => {
    if (!confirm(`¿Restablecer contraseña de "${auth.userName}"? Se enviará una nueva por WhatsApp.`)) return;

    common.ajax(urls.resetPasswordUrl.replace('__ID__', auth.id), 'POST', null, (data) => {
        if (data.success) {
            toast.add({ severity: 'success', summary: 'Enlace enviado', detail: 'Enlace de restablecimiento enviado por WhatsApp' });
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
    }, onAjaxError);
};

onMounted(() => {
    loadAccounts();
});
</script>
