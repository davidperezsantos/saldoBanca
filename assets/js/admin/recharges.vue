<template>
    <div class="container mx-auto px-4 py-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-200">
                {{ $t('saldo.recharges.title') }}
            </h1>
        </div>

        <div class="card mb-6">
            <div class="flex flex-wrap gap-4 items-center justify-between">
                <div class="flex gap-2">
                    <Select v-model="filterStatus" :options="statusOptions" optionLabel="label" optionValue="value"
                        :placeholder="$t('saldo.common.filter')" class="w-48" />
                    <Button :label="$t('saldo.common.filter')" @click="loadRecharges" />
                </div>
                <Button :label="$t('saldo.recharges.create')" icon="pi pi-plus" @click="openCreateModal" />
            </div>
        </div>

        <div class="card">
            <DataTable :value="recharges" :loading="loading" stripedRows responsiveLayout="scroll">
                <Column field="accountNumber" :header="$t('saldo.accounts.accountNumber')" />
                <Column field="amount" :header="$t('saldo.recharges.amount')" />
                <Column field="currency" :header="$t('saldo.recharges.currency')" />
                <Column field="rechargeType" :header="$t('saldo.recharges.type')" />
                <Column field="status" :header="$t('saldo.recharges.status')">
                    <template #body="{ data }">
                        <span :class="getStatusBadgeClass(data.status)">
                            {{ data.status }}
                        </span>
                    </template>
                </Column>
                <Column field="createdAt" :header="$t('saldo.history.date')" />
                <Column :header="$t('saldo.common.actions')">
                    <template #body="{ data }">
                        <div class="flex gap-2">
                            <Button v-if="data.status === 'pending'" icon="pi pi-check"
                                class="p-button-rounded p-button-success p-button-text"
                                @click="completeRecharge(data)" />
                            <Button v-if="data.status === 'pending'" icon="pi pi-times"
                                class="p-button-rounded p-button-danger p-button-text" @click="cancelRecharge(data)" />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>

        <Dialog v-model:visible="showModal" :header="$t('saldo.recharges.create')" :modal="true"
            :style="{ width: '500px' }">
            <div class="flex flex-col gap-4">
                <div class="form-group">
                    <label class="form-label">{{ $t('saldo.accounts.accountNumber') }}</label>
                    <InputText v-model="formData.accountNumber" class="w-full" />
                </div>
                <div class="form-group">
                    <label class="form-label">{{ $t('saldo.recharges.amount') }}</label>
                    <InputText v-model="formData.amount" class="w-full" type="number" />
                </div>
                <div class="form-group">
                    <label class="form-label">{{ $t('saldo.recharges.currency') }}</label>
                    <Select v-model="formData.currency" :options="currencies" optionLabel="label" optionValue="value"
                        class="w-full" />
                </div>
                <div class="form-group">
                    <label class="form-label">{{ $t('saldo.recharges.paymentMethod') }}</label>
                    <Select v-model="formData.paymentMethod" :options="paymentMethods" optionLabel="label"
                        optionValue="value" class="w-full" />
                </div>
                <div class="form-group">
                    <label class="form-label">{{ $t('saldo.recharges.reference') }}</label>
                    <InputText v-model="formData.referenceNumber" class="w-full" />
                </div>
            </div>
            <template #footer>
                <Button :label="$t('saldo.common.cancel')" class="p-button-text" @click="closeModal" />
                <Button :label="$t('saldo.common.save')" @click="saveRecharge" :loading="saving" />
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

const recharges = ref([]);
const loading = ref(false);
const saving = ref(false);
const showModal = ref(false);
const filterStatus = ref(null);

const formData = ref({
    accountNumber: '',
    amount: '',
    currency: 'USD',
    paymentMethod: '',
    referenceNumber: '',
});

const statusOptions = [
    { label: 'All', value: null },
    { label: 'Pending', value: 'pending' },
    { label: 'Completed', value: 'completed' },
    { label: 'Failed', value: 'failed' },
    { label: 'Cancelled', value: 'cancelled' },
];

const currencies = [
    { label: 'USD', value: 'USD' },
    { label: 'EUR', value: 'EUR' },
    { label: 'VES', value: 'VES' },
];

const paymentMethods = [
    { label: 'Bank Transfer', value: 'bank_transfer' },
    { label: 'Credit Card', value: 'credit_card' },
    { label: 'Cash', value: 'cash' },
    { label: 'Other', value: 'other' },
];

const loadRecharges = async () => {
    loading.value = true;
    try {
        const params = new URLSearchParams();
        if (filterStatus.value) params.append('status', filterStatus.value);

        const response = await fetch(`/saldo/recharges?${params}`);
        const data = await response.json();

        if (data.success) {
            recharges.value = data.data;
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
        amount: '',
        currency: 'USD',
        paymentMethod: '',
        referenceNumber: '',
    };
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
};

const saveRecharge = async () => {
    saving.value = true;
    try {
        const response = await fetch('/saldo/recharges', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData.value),
        });

        const data = await response.json();

        if (data.success) {
            toast.add({ severity: 'success', summary: t('saldo.common.success'), detail: data.message });
            closeModal();
            loadRecharges();
        } else {
            toast.add({ severity: 'error', summary: t('saldo.common.error'), detail: data.message });
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: t('saldo.common.error'), detail: error.message });
    } finally {
        saving.value = false;
    }
};

const completeRecharge = async (recharge) => {
    try {
        const response = await fetch(`/saldo/recharges/${recharge.id}/complete`, {
            method: 'PUT',
        });

        const data = await response.json();

        if (data.success) {
            toast.add({ severity: 'success', summary: t('saldo.common.success'), detail: data.message });
            loadRecharges();
        } else {
            toast.add({ severity: 'error', summary: t('saldo.common.error'), detail: data.message });
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: t('saldo.common.error'), detail: error.message });
    }
};

const cancelRecharge = async (recharge) => {
    try {
        const response = await fetch(`/saldo/recharges/${recharge.id}/cancel`, {
            method: 'PUT',
        });

        const data = await response.json();

        if (data.success) {
            toast.add({ severity: 'success', summary: t('saldo.common.success'), detail: data.message });
            loadRecharges();
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
        completed: 'badge badge-success',
        failed: 'badge badge-danger',
        cancelled: 'badge badge-info',
    };
    return classes[status] || 'badge badge-info';
};

onMounted(() => {
    loadRecharges();
});
</script>
