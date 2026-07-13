<template>
    <div class="container mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-800">
                {{ filterType === 'business' ? $t('accounts.businessTitle') : $t('accounts.title') }}
            </h1>
            <Button :label="$t('accounts.create')" icon="pi pi-plus" @click="openCreateModal" />
        </div>

        <div class="card mb-6">
            <div class="flex flex-wrap gap-4 items-center justify-between">
                <div class="flex gap-2">
                    <Select
                        v-model="filterType"
                        :options="typeOptions"
                        optionLabel="label"
                        optionValue="value"
                        class="w-48"
                        @change="loadAccounts"
                    />
                    <InputText
                        v-model="search"
                        :placeholder="$t('common.search')"
                        @keyup.enter="loadAccounts"
                    />
                    <Button :label="$t('common.search')" @click="loadAccounts" />
                </div>
            </div>
        </div>

        <div class="card">
            <DataTable :value="accounts" :loading="loading" stripedRows responsiveLayout="scroll">
                <Column field="accountNumber" :header="$t('accounts.accountNumber')" />
                <Column field="businessName" :header="$t('accounts.businessName')" />
                <Column field="documentType" :header="$t('accounts.documentType')" />
                <Column field="documentNumber" :header="$t('accounts.documentNumber')" />
                <Column field="email" :header="$t('accounts.email')" />
                <Column field="phone" :header="$t('accounts.phone')" />
                <Column field="status" :header="$t('common.status')">
                    <template #body="{ data }">
                        <span :class="['px-2 py-1 text-xs rounded-full', data.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800']">
                            {{ data.status === 'active' ? $t('common.active') : $t('common.inactive') }}
                        </span>
                    </template>
                </Column>
                <Column field="defaultCurrency" :header="$t('accounts.currency')" />
                <Column field="saldoDisponible" :header="$t('accounts.availableBalance')" style="width: 130px">
                    <template #body="{ data }">
                        <span class="font-semibold" :class="parseFloat(data.saldoDisponible) > 0 ? 'text-emerald-600' : 'text-gray-500'">
                            {{ formatCurrency(data.saldoDisponible, data.defaultCurrency) }}
                        </span>
                    </template>
                </Column>
                <Column :header="$t('common.actions')" style="width: 10rem">
                    <template #body="{ data }">
                        <div class="flex gap-2">
                            <Button icon="pi pi-pencil" class="p-button-rounded p-button-warning p-button-text" @click="editAccount(data)" />
                            <Button
                                :icon="data.status === 'active' ? 'pi pi-power-off' : 'pi pi-check-circle'"
                                :class="['p-button-rounded p-button-text', data.status === 'active' ? 'p-button-danger' : 'p-button-success']"
                                @click="toggleStatus(data)"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>

        <Dialog v-model:visible="showModal" :header="editingId ? $t('accounts.edit') : $t('accounts.create')" :modal="true" :style="{ width: '550px' }">
            <div class="flex flex-col gap-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">{{ $t('accounts.accountType') }}</label>
                        <Select v-model="form.accountType" :options="typeOptions" optionLabel="label" optionValue="value" class="w-full" />
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ $t('accounts.documentType') }}</label>
                        <Select v-model="form.documentType" :options="[{label:'NIT',value:'NIT'},{label:'CC',value:'CC'},{label:'RIF',value:'RIF'},{label:'RUC',value:'RUC'}]" optionLabel="label" optionValue="value" class="w-full" />
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ $t('accounts.businessName') }}</label>
                    <InputText v-model="form.businessName" class="w-full" />
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
                        <InputText v-model="form.phone" class="w-full" />
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ $t('accounts.currency') }}</label>
                    <Select v-model="form.defaultCurrency" :options="[{label:'USD',value:'USD'},{label:'EUR',value:'EUR'},{label:'VES',value:'VES'},{label:'COP',value:'COP'}]" optionLabel="label" optionValue="value" class="w-full" />
                </div>
            </div>
            <template #footer>
                <Button :label="$t('common.cancel')" class="p-button-text" @click="closeModal" />
                <Button :label="$t('common.save')" @click="saveAccount" :loading="saving" />
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
const loading = ref(false);
const saving = ref(false);
const showModal = ref(false);
const editingId = ref(null);
const search = ref('');
const filterType = ref('');

const typeOptions = [
    { label: 'Todos', value: '' },
    { label: 'Cliente', value: 'personal' },
    { label: 'Negocio', value: 'business' },
];

const form = ref({
    accountType: 'personal',
    businessName: '',
    documentType: 'NIT',
    documentNumber: '',
    email: '',
    phone: '',
    defaultCurrency: 'USD',
});

const loadAccounts = async () => {
    loading.value = true;
    try {
        const params = new URLSearchParams();
        if (search.value) params.append('search', search.value);
        if (filterType.value) params.append('accountType', filterType.value);

        const response = await fetch(`/accounts/list?${params}`);
        const data = await response.json();

        if (data.success) {
            accounts.value = data.data;
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
    form.value = { accountType: filterType.value || 'personal', businessName: '', documentType: 'NIT', documentNumber: '', email: '', phone: '', defaultCurrency: 'USD' };
    showModal.value = true;
};

const editAccount = (account) => {
    editingId.value = account.id;
    form.value = {
        accountType: account.accountType,
        businessName: account.businessName,
        documentType: account.documentType,
        documentNumber: account.documentNumber,
        email: account.email || '',
        phone: account.phone || '',
        defaultCurrency: account.defaultCurrency,
    };
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingId.value = null;
};

const saveAccount = async () => {
    saving.value = true;
    try {
        const url = editingId.value ? `/accounts/${editingId.value}` : '/accounts';
        const method = editingId.value ? 'PUT' : 'POST';

        const response = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(form.value),
        });

        const data = await response.json();
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: data.message });
            closeModal();
            loadAccounts();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: t('common.error'), detail: error.message });
    } finally {
        saving.value = false;
    }
};

const formatCurrency = (value, currency = 'USD') => {
    const num = parseFloat(value) || 0;
    return new Intl.NumberFormat('es-US', { style: 'currency', currency }).format(num);
};

const toggleStatus = async (account) => {
    const newStatus = account.status === 'active' ? 'inactive' : 'active';
    if (!confirm(`¿${newStatus === 'active' ? 'Activar' : 'Desactivar'} "${account.businessName}"?`)) return;

    try {
        const response = await fetch(`/accounts/${account.id}/status`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ status: newStatus }),
        });
        const data = await response.json();
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: 'Estado cambiado' });
            loadAccounts();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: t('common.error'), detail: error.message });
    }
};

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    filterType.value = params.get('type') || '';
    loadAccounts();
});
</script>
