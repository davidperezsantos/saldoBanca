<template>
    <div class="container mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-800">{{ $t('history.title') }}</h1>
        </div>

        <div class="card mb-6">
            <div class="flex flex-wrap gap-4 items-center justify-between">
                <div class="flex gap-2">
                    <Select v-model="filters.movementType" :options="typeOptions" optionLabel="label" optionValue="value" class="w-40" :placeholder="$t('history.movementType')" @change="loadHistory" />
                    <InputText v-model="filters.dateFrom" type="date" class="w-40" @change="loadHistory" />
                    <InputText v-model="filters.dateTo" type="date" class="w-40" @change="loadHistory" />
                    <InputText v-model="filters.search" :placeholder="$t('common.search')" @keyup.enter="loadHistory" />
                    <Button :label="$t('common.search')" @click="loadHistory" />
                    <Button :label="$t('common.export')" icon="pi pi-download" class="p-button-outlined" @click="exportData" />
                </div>
            </div>
        </div>

        <div class="card">
            <DataTable :value="movements" :loading="loading" stripedRows responsiveLayout="scroll">
                <Column field="createdAt" :header="$t('history.date')">
                    <template #body="{ data }">
                        {{ formatDate(data.createdAt) }}
                    </template>
                </Column>
                <Column field="movementType" :header="$t('history.movementType')">
                    <template #body="{ data }">
                        <span :class="['px-2 py-1 text-xs rounded-full font-medium',
                            data.movementType === 'credit' || data.movementType === 'recharge' || data.movementType === 'invoice_cancel' || data.movementType === 'adjustment' ? 'bg-emerald-100 text-emerald-800' :
                            'bg-red-100 text-red-800']">
                            {{ data.movementType }}
                        </span>
                    </template>
                </Column>
                <Column field="description" :header="$t('history.description')" />
                <Column field="amount" :header="$t('history.amount')">
                    <template #body="{ data }">
                        <span :class="[data.movementType === 'credit' || data.movementType === 'recharge' || data.movementType === 'adjustment' || data.movementType === 'invoice_cancel' ? 'text-emerald-600' : 'text-red-600']">
                            {{ formatCurrency(data.amount, data.currency) }}
                        </span>
                    </template>
                </Column>
                <Column field="balanceBefore" :header="$t('history.balanceBefore')">
                    <template #body="{ data }">
                        {{ formatCurrency(data.balanceBefore, data.currency) }}
                    </template>
                </Column>
                <Column field="balanceAfter" :header="$t('history.balanceAfter')">
                    <template #body="{ data }">
                        {{ formatCurrency(data.balanceAfter, data.currency) }}
                    </template>
                </Column>
                <Column field="accountNumber" :header="$t('accounts.accountNumber')" />
                <Column field="performedBy" :header="$t('recharges.authorizedBy')" />
            </DataTable>
            <div v-if="!movements.length && !loading" class="text-center py-8 text-gray-500">
                {{ $t('common.no_data') }}
            </div>
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

const filters = ref({ movementType: '', dateFrom: '', dateTo: '', search: '' });

const typeOptions = [
    { label: t('common.all'), value: '' },
    { label: t('history.credit'), value: 'credit' },
    { label: t('history.debit'), value: 'debit' },
    { label: 'Recharge', value: 'recharge' },
    { label: 'Adjustment', value: 'adjustment' },
    { label: 'Invoice Pay', value: 'invoice_pay' },
    { label: 'Transfer', value: 'transfer' },
];

const loadHistory = async () => {
    loading.value = true;
    try {
        const params = new URLSearchParams();
        if (filters.value.movementType) params.append('movementType', filters.value.movementType);
        if (filters.value.dateFrom) params.append('dateFrom', filters.value.dateFrom);
        if (filters.value.dateTo) params.append('dateTo', filters.value.dateTo);

        const response = await fetch(`/history/list?${params}`);
        const data = await response.json();

        if (data.success) {
            movements.value = data.data;
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: t('common.error'), detail: error.message });
    } finally {
        loading.value = false;
    }
};

const exportData = () => {
    const csvRows = [];
    const headers = ['Date', 'Type', 'Description', 'Amount', 'Balance Before', 'Balance After', 'Account', 'Performed By'];
    csvRows.push(headers.join(','));

    movements.value.forEach(m => {
        csvRows.push([
            m.createdAt, m.movementType, `"${m.description || ''}"`,
            m.amount, m.balanceBefore, m.balanceAfter,
            m.accountNumber, m.performedBy || ''
        ].join(','));
    });

    const blob = new Blob([csvRows.join('\n')], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `history_${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    URL.revokeObjectURL(url);
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
    loadHistory();
});
</script>
