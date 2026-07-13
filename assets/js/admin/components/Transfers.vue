<template>
    <div class="container mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-800">{{ $t('transfers.title') }}</h1>
            <Button :label="$t('transfers.create')" icon="pi pi-plus" @click="openCreateModal" />
        </div>

        <div class="card mb-6">
            <div class="flex flex-wrap gap-4 items-center justify-between">
                <div class="flex gap-2">
                    <Select v-model="filters.status" :options="statusOptions" optionLabel="label" optionValue="value" class="w-40" :placeholder="$t('common.status')" @change="loadTransfers" />
                    <InputText v-model="filters.search" :placeholder="$t('common.search')" @keyup.enter="loadTransfers" />
                    <Button :label="$t('common.search')" @click="loadTransfers" />
                </div>
            </div>
        </div>

        <div class="card">
            <DataTable :value="transfers" :loading="loading" stripedRows responsiveLayout="scroll">
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
                            <Button icon="pi pi-check" class="p-button-rounded p-button-success p-button-text" v-if="data.status === 'pending'" @click="processTransfer(data)" :disabled="actionLoading" />
                            <Button icon="pi pi-ban" class="p-button-rounded p-button-warning p-button-text" v-if="data.status === 'pending'" @click="cancelTransfer(data)" :disabled="actionLoading" />
                            <Button icon="pi pi-eye" class="p-button-rounded p-button-info p-button-text" @click="showDetail(data)" />
                        </div>
                    </template>
                </Column>
            </DataTable>
            <div v-if="!transfers.length && !loading" class="text-center py-8 text-gray-500">
                {{ $t('common.no_data') }}
            </div>
        </div>

        <Dialog v-model:visible="showModal" :header="$t('transfers.create')" :modal="true" :style="{ width: '550px' }">
            <div class="flex flex-col gap-4">
                <div class="form-group">
                    <label class="form-label">{{ $t('transfers.originAccount') }}</label>
                    <Select v-model="form.originAccountId" :options="accounts" optionLabel="businessName" optionValue="id" class="w-full" filter @change="loadLimits" />
                </div>
                <div class="form-group">
                    <label class="form-label">{{ $t('transfers.destinationAccount') }}</label>
                    <InputText v-model="form.destinationAccountNumber" class="w-full" :placeholder="$t('accounts.accountNumber')" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">{{ $t('transfers.amount') }}</label>
                        <InputNumber v-model="form.amount" class="w-full" :min="0" :max="99999999" />
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ $t('recharges.currency') }}</label>
                        <Select v-model="form.currency" :options="[{label:'USD',value:'USD'},{label:'EUR',value:'EUR'},{label:'VES',value:'VES'},{label:'COP',value:'COP'}]" optionLabel="label" optionValue="value" class="w-full" />
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

        <Dialog v-model:visible="showDetailModal" :header="$t('common.details')" :modal="true" :style="{ width: '600px' }">
            <div v-if="selectedTransfer" class="grid grid-cols-2 gap-4 text-sm">
                <div><span class="font-medium text-gray-600">{{ $t('transfers.originAccount') }}:</span> {{ selectedTransfer.originAccountNumber }}</div>
                <div><span class="font-medium text-gray-600">{{ $t('transfers.destinationAccount') }}:</span> {{ selectedTransfer.destAccountNumber }}</div>
                <div><span class="font-medium text-gray-600">{{ $t('transfers.amount') }}:</span> {{ formatCurrency(selectedTransfer.amount, selectedTransfer.currency) }}</div>
                <div><span class="font-medium text-gray-600">{{ $t('transfers.fee') }}:</span> {{ selectedTransfer.fee ? formatCurrency(selectedTransfer.fee, selectedTransfer.currency) : '—' }}</div>
                <div><span class="font-medium text-gray-600">{{ $t('common.status') }}:</span> {{ $t('transfers.' + selectedTransfer.status) }}</div>
                <div><span class="font-medium text-gray-600">{{ $t('common.createdAt') }}:</span> {{ formatDate(selectedTransfer.createdAt) }}</div>
                <div v-if="selectedTransfer.authorizedBy" class="col-span-2"><span class="font-medium text-gray-600">{{ $t('recharges.authorizedBy') }}:</span> {{ selectedTransfer.authorizedBy }}</div>
                <div v-if="selectedTransfer.notes" class="col-span-2"><span class="font-medium text-gray-600">{{ $t('common.notes') }}:</span> {{ selectedTransfer.notes }}</div>
            </div>
            <template #footer>
                <Button :label="$t('common.close')" class="p-button-text" @click="showDetailModal = false" />
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
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import Dialog from 'primevue/dialog';
import { useToast } from 'primevue/usetoast';

const { t } = useI18n();
const toast = useToast();

const transfers = ref([]);
const accounts = ref([]);
const loading = ref(false);
const saving = ref(false);
const actionLoading = ref(false);
const showModal = ref(false);
const showDetailModal = ref(false);
const limits = ref(null);
const selectedTransfer = ref(null);

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

const loadLimits = async () => {
    if (!form.value.originAccountId) { limits.value = null; return; }
    try {
        const response = await fetch(`/transfers/limits/${form.value.originAccountId}`);
        const data = await response.json();
        if (data.success) limits.value = data.data;
    } catch (e) { limits.value = null; }
};

const loadTransfers = async () => {
    loading.value = true;
    try {
        const params = new URLSearchParams();
        if (filters.value.status) params.append('status', filters.value.status);

        const response = await fetch(`/transfers/list?${params}`);
        const data = await response.json();

        if (data.success) {
            transfers.value = data.data;
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
    form.value = { originAccountId: '', destinationAccountNumber: '', amount: null, currency: 'USD', notes: '' };
    limits.value = null;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
};

const saveTransfer = async () => {
    saving.value = true;
    try {
        const response = await fetch('/transfers', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                originAccountId: form.value.originAccountId,
                destinationAccountNumber: form.value.destinationAccountNumber,
                amount: String(form.value.amount),
                currency: form.value.currency,
                notes: form.value.notes,
            }),
        });
        const data = await response.json();
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: data.message });
            closeModal();
            loadTransfers();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: t('common.error'), detail: error.message });
    } finally {
        saving.value = false;
    }
};

const processTransfer = async (transfer) => {
    if (!confirm(`¿Procesar transferencia de ${formatCurrency(transfer.amount, transfer.currency)}?`)) return;
    actionLoading.value = true;
    try {
        const response = await fetch(`/transfers/${transfer.id}/process`, { method: 'PUT' });
        const data = await response.json();
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: data.message });
            loadTransfers();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: t('common.error'), detail: error.message });
    } finally {
        actionLoading.value = false;
    }
};

const cancelTransfer = async (transfer) => {
    if (!confirm(`¿Cancelar transferencia de ${formatCurrency(transfer.amount, transfer.currency)}?`)) return;
    actionLoading.value = true;
    try {
        const response = await fetch(`/transfers/${transfer.id}/cancel`, { method: 'PUT' });
        const data = await response.json();
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: data.message });
            loadTransfers();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: t('common.error'), detail: error.message });
    } finally {
        actionLoading.value = false;
    }
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
    loadTransfers();
});
</script>
