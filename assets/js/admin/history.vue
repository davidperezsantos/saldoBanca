<template>
    <div class="container mx-auto px-4 py-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-200">
                {{ $t('saldo.history.title') }}
            </h1>
        </div>

        <div class="card mb-6">
            <div class="flex flex-wrap gap-4 items-center justify-between">
                <div class="flex gap-2">
                    <InputText v-model="accountId" :placeholder="$t('saldo.accounts.accountNumber')" class="w-48" />
                    <Select v-model="filterType" :options="movementTypes" optionLabel="label" optionValue="value"
                        :placeholder="$t('saldo.common.filter')" class="w-48" />
                    <Button :label="$t('saldo.common.search')" @click="loadHistory" />
                </div>
                <Button :label="$t('saldo.common.export')" icon="pi pi-download" @click="exportHistory" />
            </div>
        </div>

        <div class="card">
            <DataTable :value="movements" :loading="loading" stripedRows responsiveLayout="scroll">
                <Column field="movementType" :header="$t('saldo.history.movementType')" />
                <Column field="amount" :header="$t('saldo.history.amount')">
                    <template #body="{ data }">
                        <span :class="data.amount.startsWith('-') ? 'text-danger' : 'text-success'">
                            {{ data.amount }}
                        </span>
                    </template>
                </Column>
                <Column field="balanceBefore" :header="$t('saldo.history.balanceBefore')" />
                <Column field="balanceAfter" :header="$t('saldo.history.balanceAfter')" />
                <Column field="currency" :header="$t('saldo.recharges.currency')" />
                <Column field="description" :header="$t('saldo.history.description')" />
                <Column field="createdAt" :header="$t('saldo.history.date')" />
            </DataTable>
        </div>
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
import { useToast } from 'primevue/usetoast';

const { t } = useI18n();
const toast = useToast();

const movements = ref([]);
const loading = ref(false);
const accountId = ref('');
const filterType = ref(null);

const movementTypes = [
    { label: 'All', value: null },
    { label: 'Recharge', value: 'recharge' },
    { label: 'Transfer Out', value: 'transfer_out' },
    { label: 'Transfer In', value: 'transfer_in' },
    { label: 'Invoice Payment', value: 'invoice_pay' },
    { label: 'Adjustment', value: 'adjustment' },
];

const loadHistory = async () => {
    if (!accountId.value) {
        toast.add({ severity: 'warn', summary: t('saldo.common.error'), detail: 'Account ID is required' });
        return;
    }

    loading.value = true;
    try {
        const params = new URLSearchParams();
        if (filterType.value) params.append('movementType', filterType.value);

        const response = await fetch(`/saldo/history/${accountId.value}?${params}`);
        const data = await response.json();

        if (data.success) {
            movements.value = data.data;
        } else {
            toast.add({ severity: 'error', summary: t('saldo.common.error'), detail: data.message });
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: t('saldo.common.error'), detail: error.message });
    } finally {
        loading.value = false;
    }
};

const exportHistory = async () => {
    if (!accountId.value) {
        toast.add({ severity: 'warn', summary: t('saldo.common.error'), detail: 'Account ID is required' });
        return;
    }

    try {
        const response = await fetch(`/saldo/history/${accountId.value}/export`);
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `history-${accountId.value}.csv`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
    } catch (error) {
        toast.add({ severity: 'error', summary: t('saldo.common.error'), detail: error.message });
    }
};

onMounted(() => {
    // Don't load history on mount, wait for account ID
});
</script>
