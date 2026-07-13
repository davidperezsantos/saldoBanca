<template>
    <div class="container mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-800">{{ $t('invoices.title') }}</h1>
            <Button :label="$t('invoices.create')" icon="pi pi-plus" @click="openCreateModal" />
        </div>

        <div class="card mb-6">
            <div class="flex flex-wrap gap-4 items-center justify-between">
                <div class="flex gap-2">
                    <Select v-model="filters.status" :options="statusOptions" optionLabel="label" optionValue="value" class="w-40" :placeholder="$t('common.status')" @change="loadInvoices" />
                    <InputText v-model="filters.search" :placeholder="$t('common.search')" @keyup.enter="loadInvoices" />
                    <Button :label="$t('common.search')" @click="loadInvoices" />
                </div>
            </div>
        </div>

        <div class="card">
            <DataTable :value="invoices" :loading="loading" stripedRows responsiveLayout="scroll">
                <Column field="invoiceNumber" :header="$t('invoices.invoiceNumber')" />
                <Column field="accountNumber" :header="$t('accounts.accountNumber')" />
                <Column field="customerName" :header="$t('accounts.businessName')" />
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
                            <Button icon="pi pi-dollar" class="p-button-rounded p-button-success p-button-text" v-if="data.status === 'pending'" @click="payInvoice(data)" :disabled="actionLoading" />
                            <Button icon="pi pi-ban" class="p-button-rounded p-button-warning p-button-text" v-if="data.status === 'paid'" @click="cancelInvoice(data)" :disabled="actionLoading" />
                            <Button icon="pi pi-refund" class="p-button-rounded p-button-info p-button-text" v-if="data.status === 'paid'" @click="refundInvoice(data)" :disabled="actionLoading" />
                            <Button icon="pi pi-eye" class="p-button-rounded p-button-text" @click="showDetail(data)" />
                        </div>
                    </template>
                </Column>
            </DataTable>
            <div v-if="!invoices.length && !loading" class="text-center py-8 text-gray-500">
                {{ $t('common.no_data') }}
            </div>
        </div>

        <Dialog v-model:visible="showModal" :header="$t('invoices.create')" :modal="true" :style="{ width: '550px' }">
            <div class="flex flex-col gap-4">
                <div class="form-group">
                    <label class="form-label">{{ $t('accounts.title') }}</label>
                    <Select v-model="form.accountId" :options="accounts" optionLabel="businessName" optionValue="id" class="w-full" filter />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">{{ $t('invoices.invoiceNumber') }}</label>
                        <InputText v-model="form.invoiceNumber" class="w-full" />
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ $t('recharges.currency') }}</label>
                        <Select v-model="form.currency" :options="[{label:'USD',value:'USD'},{label:'EUR',value:'EUR'},{label:'VES',value:'VES'},{label:'COP',value:'COP'}]" optionLabel="label" optionValue="value" class="w-full" />
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
                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">{{ $t('accounts.documentNumber') }}</label>
                        <InputText v-model="form.customerCode" class="w-full" :placeholder="$t('accounts.documentNumber')" />
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ $t('accounts.businessName') }}</label>
                        <InputText v-model="form.customerName" class="w-full" />
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

        <Dialog v-model:visible="showDetailModal" :header="$t('common.details')" :modal="true" :style="{ width: '600px' }">
            <div v-if="selectedInvoice" class="grid grid-cols-2 gap-4 text-sm">
                <div><span class="font-medium text-gray-600">{{ $t('invoices.invoiceNumber') }}:</span> {{ selectedInvoice.invoiceNumber }}</div>
                <div><span class="font-medium text-gray-600">{{ $t('accounts.accountNumber') }}:</span> {{ selectedInvoice.accountNumber }}</div>
                <div><span class="font-medium text-gray-600">{{ $t('invoices.amount') }}:</span> {{ formatCurrency(selectedInvoice.amount, selectedInvoice.currency) }}</div>
                <div><span class="font-medium text-gray-600">{{ $t('invoices.taxAmount') }}:</span> {{ formatCurrency(selectedInvoice.taxAmount, selectedInvoice.currency) }}</div>
                <div><span class="font-medium text-gray-600">{{ $t('invoices.totalAmount') }}:</span> {{ formatCurrency(selectedInvoice.totalAmount, selectedInvoice.currency) }}</div>
                <div><span class="font-medium text-gray-600">{{ $t('common.status') }}:</span> {{ $t('invoices.' + selectedInvoice.status) }}</div>
                <div><span class="font-medium text-gray-600">{{ $t('invoices.invoiceDate') }}:</span> {{ selectedInvoice.invoiceDate }}</div>
                <div><span class="font-medium text-gray-600">{{ $t('invoices.dueDate') }}:</span> {{ selectedInvoice.dueDate || '—' }}</div>
                <div v-if="selectedInvoice.paymentDate"><span class="font-medium text-gray-600">{{ $t('invoices.paymentDate') }}:</span> {{ selectedInvoice.paymentDate }}</div>
                <div v-if="selectedInvoice.customerName" class="col-span-2"><span class="font-medium text-gray-600">{{ $t('accounts.businessName') }}:</span> {{ selectedInvoice.customerName }}</div>
            </div>
            <template #footer>
                <Button :label="$t('common.close')" class="p-button-text" @click="showDetailModal = false" />
            </template>
        </Dialog>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import Dialog from 'primevue/dialog';
import { useToast } from 'primevue/usetoast';

const { t } = useI18n();
const toast = useToast();

const invoices = ref([]);
const accounts = ref([]);
const loading = ref(false);
const saving = ref(false);
const actionLoading = ref(false);
const showModal = ref(false);
const showDetailModal = ref(false);
const selectedInvoice = ref(null);

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
    invoiceNumber: '',
    invoiceDate: new Date().toISOString().split('T')[0],
    dueDate: '',
    amount: null,
    taxAmount: null,
    totalAmount: null,
    currency: 'USD',
    customerCode: '',
    customerName: '',
    notes: '',
});

const loadAccounts = async () => {
    try {
        const response = await fetch('/accounts/list?limit=200');
        const data = await response.json();
        if (data.success) accounts.value = data.data;
    } catch (error) {
        toast.add({ severity: 'error', summary: t('common.error'), detail: error.message });
    }
};

const loadInvoices = async () => {
    loading.value = true;
    try {
        const params = new URLSearchParams();
        if (filters.value.status) params.append('status', filters.value.status);

        const response = await fetch(`/invoices/list?${params}`);
        const data = await response.json();

        if (data.success) {
            invoices.value = data.data;
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
    form.value = {
        accountId: '', invoiceNumber: '', invoiceDate: new Date().toISOString().split('T')[0],
        dueDate: '', amount: null, taxAmount: null, totalAmount: null,
        currency: 'USD', customerCode: '', customerName: '', notes: '',
    };
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
};

const saveInvoice = async () => {
    saving.value = true;
    try {
        const payload = {
            accountId: form.value.accountId,
            invoiceNumber: form.value.invoiceNumber,
            invoiceDate: form.value.invoiceDate,
            dueDate: form.value.dueDate || null,
            amount: String(form.value.amount || '0'),
            taxAmount: form.value.taxAmount ? String(form.value.taxAmount) : null,
            totalAmount: String(form.value.totalAmount || form.value.amount || '0'),
            currency: form.value.currency,
            customerCode: form.value.customerCode || null,
            customerName: form.value.customerName || null,
            notes: form.value.notes || null,
        };

        const response = await fetch('/invoices', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await response.json();
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: data.message });
            closeModal();
            loadInvoices();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: t('common.error'), detail: error.message });
    } finally {
        saving.value = false;
    }
};

const payInvoice = async (invoice) => {
    if (!confirm(`¿Pagar factura ${invoice.invoiceNumber} por ${formatCurrency(invoice.totalAmount, invoice.currency)}?`)) return;
    actionLoading.value = true;
    try {
        const response = await fetch(`/invoices/${invoice.id}/pay`, { method: 'PUT' });
        const data = await response.json();
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: data.message });
            loadInvoices();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: t('common.error'), detail: error.message });
    } finally {
        actionLoading.value = false;
    }
};

const cancelInvoice = async (invoice) => {
    if (!confirm(`¿Cancelar factura ${invoice.invoiceNumber}?`)) return;
    actionLoading.value = true;
    try {
        const response = await fetch(`/invoices/${invoice.id}/cancel`, { method: 'PUT' });
        const data = await response.json();
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: data.message });
            loadInvoices();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: t('common.error'), detail: error.message });
    } finally {
        actionLoading.value = false;
    }
};

const refundInvoice = async (invoice) => {
    if (!confirm(`¿Reembolsar factura ${invoice.invoiceNumber}?`)) return;
    actionLoading.value = true;
    try {
        const response = await fetch(`/invoices/${invoice.id}/refund`, { method: 'PUT' });
        const data = await response.json();
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: data.message });
            loadInvoices();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: t('common.error'), detail: error.message });
    } finally {
        actionLoading.value = false;
    }
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
    loadInvoices();
});
</script>
