<template>
    <div class="container mx-auto px-4 py-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-200">
                {{ $t('saldo.invoices.title') }}
            </h1>
        </div>

        <div class="card mb-6">
            <div class="flex flex-wrap gap-4 items-center justify-between">
                <div class="flex gap-2">
                    <Select v-model="filterStatus" :options="statusOptions" optionLabel="label" optionValue="value"
                        :placeholder="$t('saldo.common.filter')" class="w-48" />
                    <Button :label="$t('saldo.common.filter')" @click="loadInvoices" />
                </div>
                <Button :label="$t('saldo.invoices.create')" icon="pi pi-plus" @click="openCreateModal" />
            </div>
        </div>

        <div class="card">
            <DataTable :value="invoices" :loading="loading" stripedRows responsiveLayout="scroll">
                <Column field="invoiceNumber" :header="$t('saldo.invoices.invoiceNumber')" />
                <Column field="accountNumber" :header="$t('saldo.accounts.accountNumber')" />
                <Column field="totalAmount" :header="$t('saldo.invoices.totalAmount')" />
                <Column field="currency" :header="$t('saldo.recharges.currency')" />
                <Column field="status" :header="$t('saldo.recharges.status')">
                    <template #body="{ data }">
                        <span :class="getStatusBadgeClass(data.status)">
                            {{ data.status }}
                        </span>
                    </template>
                </Column>
                <Column field="invoiceDate" :header="$t('saldo.invoices.invoiceDate')" />
                <Column :header="$t('saldo.common.actions')">
                    <template #body="{ data }">
                        <div class="flex gap-2">
                            <Button v-if="data.status === 'pending'" icon="pi pi-check"
                                class="p-button-rounded p-button-success p-button-text" @click="payInvoice(data)" />
                            <Button v-if="data.status === 'pending'" icon="pi pi-times"
                                class="p-button-rounded p-button-danger p-button-text" @click="cancelInvoice(data)" />
                            <Button v-if="data.status === 'paid'" icon="pi pi-replay"
                                class="p-button-rounded p-button-warning p-button-text" @click="refundInvoice(data)" />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>

        <Dialog v-model:visible="showModal" :header="$t('saldo.invoices.create')" :modal="true"
            :style="{ width: '500px' }">
            <div class="flex flex-col gap-4">
                <div class="form-group">
                    <label class="form-label">{{ $t('saldo.accounts.accountNumber') }}</label>
                    <InputText v-model="formData.accountNumber" class="w-full" />
                </div>
                <div class="form-group">
                    <label class="form-label">{{ $t('saldo.invoices.invoiceNumber') }}</label>
                    <InputText v-model="formData.invoiceNumber" class="w-full" />
                </div>
                <div class="form-group">
                    <label class="form-label">{{ $t('saldo.invoices.invoiceDate') }}</label>
                    <InputText v-model="formData.invoiceDate" class="w-full" type="date" />
                </div>
                <div class="form-group">
                    <label class="form-label">{{ $t('saldo.invoices.amount') }}</label>
                    <InputText v-model="formData.amount" class="w-full" type="number" />
                </div>
                <div class="form-group">
                    <label class="form-label">{{ $t('saldo.invoices.taxAmount') }}</label>
                    <InputText v-model="formData.taxAmount" class="w-full" type="number" />
                </div>
                <div class="form-group">
                    <label class="form-label">{{ $t('saldo.invoices.totalAmount') }}</label>
                    <InputText v-model="formData.totalAmount" class="w-full" type="number" />
                </div>
                <div class="form-group">
                    <label class="form-label">{{ $t('saldo.recharges.currency') }}</label>
                    <Select v-model="formData.currency" :options="currencies" optionLabel="label" optionValue="value"
                        class="w-full" />
                </div>
            </div>
            <template #footer>
                <Button :label="$t('saldo.common.cancel')" class="p-button-text" @click="closeModal" />
                <Button :label="$t('saldo.common.save')" @click="saveInvoice" :loading="saving" />
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

const invoices = ref([]);
const loading = ref(false);
const saving = ref(false);
const showModal = ref(false);
const filterStatus = ref(null);

const formData = ref({
    accountNumber: '',
    invoiceNumber: '',
    invoiceDate: '',
    amount: '',
    taxAmount: '',
    totalAmount: '',
    currency: 'USD',
});

const statusOptions = [
    { label: 'All', value: null },
    { label: 'Pending', value: 'pending' },
    { label: 'Paid', value: 'paid' },
    { label: 'Cancelled', value: 'cancelled' },
    { label: 'Refunded', value: 'refunded' },
];

const currencies = [
    { label: 'USD', value: 'USD' },
    { label: 'EUR', value: 'EUR' },
    { label: 'VES', value: 'VES' },
];

const loadInvoices = async () => {
    loading.value = true;
    try {
        const params = new URLSearchParams();
        if (filterStatus.value) params.append('status', filterStatus.value);

        const response = await fetch(`/saldo/invoices?${params}`);
        const data = await response.json();

        if (data.success) {
            invoices.value = data.data;
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
    formData.value = {
        accountNumber: '',
        invoiceNumber: '',
        invoiceDate: '',
        amount: '',
        taxAmount: '',
        totalAmount: '',
        currency: 'USD',
    };
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
};

const saveInvoice = async () => {
    saving.value = true;
    try {
        const response = await fetch('/saldo/invoices', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData.value),
        });

        const data = await response.json();

        if (data.success) {
            toast.add({ severity: 'success', summary: t('saldo.common.success'), detail: data.message });
            closeModal();
            loadInvoices();
        } else {
            toast.add({ severity: 'error', summary: t('saldo.common.error'), detail: data.message });
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: t('saldo.common.error'), detail: error.message });
    } finally {
        saving.value = false;
    }
};

const payInvoice = async (invoice) => {
    try {
        const response = await fetch(`/saldo/invoices/${invoice.id}/pay`, {
            method: 'PUT',
        });

        const data = await response.json();

        if (data.success) {
            toast.add({ severity: 'success', summary: t('saldo.common.success'), detail: data.message });
            loadInvoices();
        } else {
            toast.add({ severity: 'error', summary: t('saldo.common.error'), detail: data.message });
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: t('saldo.common.error'), detail: error.message });
    }
};

const cancelInvoice = async (invoice) => {
    try {
        const response = await fetch(`/saldo/invoices/${invoice.id}/cancel`, {
            method: 'PUT',
        });

        const data = await response.json();

        if (data.success) {
            toast.add({ severity: 'success', summary: t('saldo.common.success'), detail: data.message });
            loadInvoices();
        } else {
            toast.add({ severity: 'error', summary: t('saldo.common.error'), detail: data.message });
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: t('saldo.common.error'), detail: error.message });
    }
};

const refundInvoice = async (invoice) => {
    try {
        const response = await fetch(`/saldo/invoices/${invoice.id}/refund`, {
            method: 'PUT',
        });

        const data = await response.json();

        if (data.success) {
            toast.add({ severity: 'success', summary: t('saldo.common.success'), detail: data.message });
            loadInvoices();
        } else {
            toast.add({ severity: 'error', summary: t('saldo.common.error'), detail: data.message });
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: t('saldo.common.error'), detail: error.message });
    }
};

const getStatusBadgeClass = (status) => {
    const classes = {
        pending: 'badge badge-warning',
        paid: 'badge badge-success',
        cancelled: 'badge badge-danger',
        refunded: 'badge badge-info',
    };
    return classes[status] || 'badge badge-info';
};

onMounted(() => {
    loadInvoices();
});
</script>
