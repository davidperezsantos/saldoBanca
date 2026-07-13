<template>
    <div class="container mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-800">{{ $t('authorized.title') }}</h1>
        </div>

        <div class="card mb-6">
            <div class="flex flex-wrap gap-4 items-end">
                <div class="flex-1">
                    <label class="form-label block text-sm font-medium text-gray-700 mb-1">{{ $t('accounts.title') }}</label>
                    <Select
                        v-model="selectedAccount"
                        :options="accounts"
                        optionLabel="businessName"
                        optionValue="id"
                        :placeholder="$t('authorized.selectAccount')"
                        class="w-full"
                        filter
                        @change="loadAuthorized"
                    />
                </div>
                <div class="flex gap-2">
                    <Select
                        v-model="filterAccountType"
                        :options="typeOptions"
                        optionLabel="label"
                        optionValue="value"
                        class="w-48"
                        @change="loadAccounts"
                    />
                    <Button :label="$t('common.search')" @click="loadAccounts" />
                </div>
                <Button
                    :label="$t('authorized.create')"
                    icon="pi pi-plus"
                    :disabled="!selectedAccount"
                    @click="openCreateModal"
                />
            </div>
        </div>

        <div v-if="selectedAccount" class="card">
            <div class="mb-4 p-3 bg-gray-50 rounded-lg flex items-center gap-4">
                <span class="text-sm text-gray-600">{{ $t('accounts.accountNumber') }}: <strong>{{ selectedAccountNumber }}</strong></span>
                <span class="text-sm text-gray-600">{{ $t('accounts.availableBalance') }}:
                    <strong :class="parseFloat(saldoDisponible) > 0 ? 'text-emerald-600' : 'text-gray-500'">
                        {{ formatCurrency(saldoDisponible, moneda) }}
                    </strong>
                </span>
            </div>
            <DataTable :value="authorizedUsers" :loading="loading" stripedRows responsiveLayout="scroll">
                <Column field="userName" :header="$t('authorized.userName')" />
                <Column field="userEmail" :header="$t('authorized.userEmail')" />
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
                <Column field="status" :header="$t('common.status')">
                    <template #body="{ data }">
                        <span :class="['px-2 py-1 text-xs rounded-full', data.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800']">
                            {{ data.status === 'active' ? $t('common.active') : $t('common.inactive') }}
                        </span>
                    </template>
                </Column>
                <Column :header="$t('common.actions')" style="width: 10rem">
                    <template #body="{ data }">
                        <div class="flex gap-2">
                            <Button icon="pi pi-pencil" class="p-button-rounded p-button-warning p-button-text" @click="editAuthorized(data)" />
                            <Button
                                :icon="data.status === 'active' ? 'pi pi-power-off' : 'pi pi-check-circle'"
                                :class="['p-button-rounded p-button-text', data.status === 'active' ? 'p-button-danger' : 'p-button-success']"
                                @click="toggleStatus(data)"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
            <div v-if="!authorizedUsers.length && !loading" class="text-center py-8 text-gray-500">
                {{ $t('common.no_data') }}
            </div>
        </div>

        <div v-else class="card">
            <div class="text-center py-12 text-gray-500">
                {{ $t('authorized.selectAccountFirst') }}
            </div>
        </div>

        <Dialog v-model:visible="showModal" :header="editingId ? $t('common.edit') : $t('authorized.create')" :modal="true" :style="{ width: '500px' }">
            <div class="flex flex-col gap-4">
                <div class="form-group">
                    <label class="form-label">{{ $t('authorized.userName') }}</label>
                    <InputText v-model="form.userName" class="w-full" />
                </div>
                <div class="form-group">
                    <label class="form-label">{{ $t('authorized.userEmail') }}</label>
                    <InputText v-model="form.userEmail" class="w-full" type="email" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">{{ $t('accounts.documentType') }}</label>
                        <Select v-model="form.documentType" :options="[{label:'CC',value:'CC'},{label:'CE',value:'CE'},{label:'NIT',value:'NIT'},{label:'RIF',value:'RIF'}]" optionLabel="label" optionValue="value" class="w-full" />
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ $t('accounts.documentNumber') }}</label>
                        <InputText v-model="form.documentNumber" class="w-full" />
                    </div>
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

<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Dialog from 'primevue/dialog';
import { useToast } from 'primevue/usetoast';

const { t } = useI18n();
const toast = useToast();

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
const filterAccountType = ref('');

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
    maxAmount: '',
    dailyLimit: '',
    monthlyLimit: '',
});

const formatCurrency = (value, currency = 'USD') => {
    const num = parseFloat(value) || 0;
    return new Intl.NumberFormat('es-US', { style: 'currency', currency }).format(num);
};

const updateAccountInfo = () => {
    const acc = accounts.value.find(a => a.id === selectedAccount.value);
    if (acc) {
        selectedAccountNumber.value = acc.accountNumber;
        saldoDisponible.value = acc.saldoDisponible || '0.00';
        moneda.value = acc.defaultCurrency || 'USD';
    }
};

const loadAccounts = async () => {
    try {
        const params = new URLSearchParams();
        if (filterAccountType.value) params.append('accountType', filterAccountType.value);
        params.append('limit', '100');

        const response = await fetch(`/accounts/list?${params}`);
        const data = await response.json();

        if (data.success) {
            accounts.value = data.data;
            if (!selectedAccount.value && data.data.length) {
                selectedAccount.value = data.data[0].id;
                updateAccountInfo();
                loadAuthorized();
            } else if (selectedAccount.value) {
                updateAccountInfo();
            }
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: t('common.error'), detail: error.message });
    }
};

const loadAuthorized = async () => {
    if (!selectedAccount.value) {
        authorizedUsers.value = [];
        return;
    }

    loading.value = true;
    try {
        updateAccountInfo();
        const response = await fetch(`/authorized/list?accountId=${selectedAccount.value}`);
        const data = await response.json();

        if (data.success) {
            authorizedUsers.value = data.data;
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: t('common.error'), detail: error.message });
    } finally {
        loading.value = false;
    }
};

const openCreateModal = () => {
    editingId.value = null;
    form.value = { userName: '', userEmail: '', documentType: 'CC', documentNumber: '', maxAmount: '', dailyLimit: '', monthlyLimit: '' };
    showModal.value = true;
};

const editAuthorized = (auth) => {
    editingId.value = auth.id;
    form.value = {
        userName: auth.userName,
        userEmail: auth.userEmail || '',
        documentType: auth.documentType,
        documentNumber: auth.documentNumber,
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

const saveAuthorized = async () => {
    saving.value = true;
    try {
        const body = { ...form.value, accountId: selectedAccount.value };
        const url = editingId.value ? `/authorized/${editingId.value}` : '/authorized';
        const method = editingId.value ? 'PUT' : 'POST';

        const response = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });

        const data = await response.json();
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: data.message });
            closeModal();
            loadAuthorized();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: t('common.error'), detail: error.message });
    } finally {
        saving.value = false;
    }
};

const toggleStatus = async (auth) => {
    const newStatus = auth.status === 'active' ? 'inactive' : 'active';
    if (!confirm(`¿${newStatus === 'active' ? 'Activar' : 'Desactivar'} a "${auth.userName}"?`)) return;

    try {
        const response = await fetch(`/authorized/${auth.id}/status`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ status: newStatus }),
        });
        const data = await response.json();
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: 'Estado cambiado' });
            loadAuthorized();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: t('common.error'), detail: error.message });
    }
};

onMounted(() => {
    loadAccounts();
});
</script>
