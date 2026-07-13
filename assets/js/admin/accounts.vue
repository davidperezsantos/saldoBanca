<template>
    <div class="container mx-auto px-4 py-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-200">
                {{ $t('saldo.accounts.title') }}
            </h1>
        </div>

        <div class="card mb-6">
            <div class="flex flex-wrap gap-4 items-center justify-between">
                <div class="flex gap-2">
                    <InputText
                        v-model="search"
                        :placeholder="$t('saldo.common.search')"
                        @keyup.enter="loadAccounts"
                    />
                    <Button
                        :label="$t('saldo.common.search')"
                        @click="loadAccounts"
                    />
                </div>
                <Button
                    :label="$t('saldo.accounts.create')"
                    icon="pi pi-plus"
                    @click="openCreateModal"
                />
            </div>
        </div>

        <div class="card">
            <DataTable
                :value="accounts"
                :loading="loading"
                stripedRows
                responsiveLayout="scroll"
            >
                <Column field="accountNumber" :header="$t('saldo.accounts.accountNumber')" />
                <Column field="businessName" :header="$t('saldo.accounts.businessName')" />
                <Column field="documentType" :header="$t('saldo.accounts.documentType')" />
                <Column field="documentNumber" :header="$t('saldo.accounts.documentNumber')" />
                <Column field="status" :header="$t('saldo.accounts.status')">
                    <template #body="{ data }">
                        <span :class="getStatusBadgeClass(data.status)">
                            {{ data.status }}
                        </span>
                    </template>
                </Column>
                <Column field="defaultCurrency" :header="$t('saldo.accounts.currency')" />
                <Column field="saldoDisponible" :header="$t('saldo.accounts.availableBalance')" style="width: 130px">
                    <template #body="{ data }">
                        <span class="font-semibold" :class="parseFloat(data.saldoDisponible) > 0 ? 'text-emerald-600' : 'text-gray-500'">
                            {{ formatCurrency(data.saldoDisponible, data.defaultCurrency) }}
                        </span>
                    </template>
                </Column>
                <Column :header="$t('saldo.common.actions')">
                    <template #body="{ data }">
                        <div class="flex gap-2">
                            <Button
                                icon="pi pi-eye"
                                class="p-button-rounded p-button-info p-button-text"
                                @click="viewAccount(data)"
                            />
                            <Button
                                icon="pi pi-pencil"
                                class="p-button-rounded p-button-warning p-button-text"
                                @click="editAccount(data)"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>

        <Dialog
            v-model:visible="showModal"
            :header="modalTitle"
            :modal="true"
            :style="{ width: '500px' }"
        >
            <div class="flex flex-col gap-4">
                <div class="form-group">
                    <label class="form-label">{{ $t('saldo.accounts.accountType') }}</label>
                    <Select
                        v-model="formData.accountType"
                        :options="accountTypes"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                    />
                </div>
                <div class="form-group">
                    <label class="form-label">{{ $t('saldo.accounts.businessName') }}</label>
                    <InputText v-model="formData.businessName" class="w-full" />
                </div>
                <div class="form-group">
                    <label class="form-label">{{ $t('saldo.accounts.documentType') }}</label>
                    <Select
                        v-model="formData.documentType"
                        :options="documentTypes"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                    />
                </div>
                <div class="form-group">
                    <label class="form-label">{{ $t('saldo.accounts.documentNumber') }}</label>
                    <InputText v-model="formData.documentNumber" class="w-full" />
                </div>
                <div class="form-group">
                    <label class="form-label">{{ $t('saldo.accounts.email') }}</label>
                    <InputText v-model="formData.email" class="w-full" />
                </div>
                <div class="form-group">
                    <label class="form-label">{{ $t('saldo.accounts.phone') }}</label>
                    <InputText v-model="formData.phone" class="w-full" />
                </div>
                <div class="form-group">
                    <label class="form-label">{{ $t('saldo.accounts.currency') }}</label>
                    <Select
                        v-model="formData.defaultCurrency"
                        :options="currencies"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                    />
                </div>
            </div>
            <template #footer>
                <Button
                    :label="$t('saldo.common.cancel')"
                    class="p-button-text"
                    @click="closeModal"
                />
                <Button
                    :label="$t('saldo.common.save')"
                    @click="saveAccount"
                    :loading="saving"
                />
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
import Dialog from 'primevue/dialog';
import Select from 'primevue/select';
import { useToast } from 'primevue/usetoast';

const { t } = useI18n();
const toast = useToast();

const accounts = ref([]);
const loading = ref(false);
const saving = ref(false);
const search = ref('');
const showModal = ref(false);
const modalTitle = ref('');
const editingId = ref(null);

const formData = ref({
    accountType: 'business',
    businessName: '',
    documentType: 'NIT',
    documentNumber: '',
    email: '',
    phone: '',
    defaultCurrency: 'USD',
});

const accountTypes = [
    { label: 'Business', value: 'business' },
    { label: 'Personal', value: 'personal' },
];

const documentTypes = [
    { label: 'NIT', value: 'NIT' },
    { label: 'CC', value: 'CC' },
    { label: 'RIF', value: 'RIF' },
    { label: 'RUC', value: 'RUC' },
];

const currencies = [
    { label: 'USD', value: 'USD' },
    { label: 'EUR', value: 'EUR' },
    { label: 'VES', value: 'VES' },
];

const loadAccounts = async () => {
    loading.value = true;
    try {
        const params = new URLSearchParams();
        if (search.value) params.append('search', search.value);

        const response = await fetch(`/saldo/accounts?${params}`);
        const data = await response.json();

        if (data.success) {
            accounts.value = data.data;
        } else {
            toast.add({ severity: 'error', summary: t('saldo.common.error'), detail: data.message });
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: t('saldo.common.error'), detail: error.message });
    } finally {
        loading.value = false;
    }
};

const openCreateModal = () => {
    editingId.value = null;
    modalTitle.value = t('saldo.accounts.create');
    formData.value = {
        accountType: 'business',
        businessName: '',
        documentType: 'NIT',
        documentNumber: '',
        email: '',
        phone: '',
        defaultCurrency: 'USD',
    };
    showModal.value = true;
};

const editAccount = (account) => {
    editingId.value = account.id;
    modalTitle.value = t('saldo.accounts.edit');
    formData.value = { ...account };
    showModal.value = true;
};

const viewAccount = (account) => {
    // TODO: Implement view account details
    console.log('View account:', account);
};

const closeModal = () => {
    showModal.value = false;
    editingId.value = null;
};

const saveAccount = async () => {
    saving.value = true;
    try {
        const url = editingId.value ? `/saldo/accounts/${editingId.value}` : '/saldo/accounts';
        const method = editingId.value ? 'PUT' : 'POST';

        const response = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData.value),
        });

        const data = await response.json();

        if (data.success) {
            toast.add({ severity: 'success', summary: t('saldo.common.success'), detail: data.message });
            closeModal();
            loadAccounts();
        } else {
            toast.add({ severity: 'error', summary: t('saldo.common.error'), detail: data.message });
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: t('saldo.common.error'), detail: error.message });
    } finally {
        saving.value = false;
    }
};

const formatCurrency = (value, currency = 'USD') => {
    const num = parseFloat(value) || 0;
    return new Intl.NumberFormat('es-US', { style: 'currency', currency }).format(num);
};

const getStatusBadgeClass = (status) => {
    const classes = {
        active: 'badge badge-success',
        suspended: 'badge badge-warning',
        closed: 'badge badge-danger',
    };
    return classes[status] || 'badge badge-info';
};

onMounted(() => {
    loadAccounts();
});
</script>
