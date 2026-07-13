<template>
    <div class="container mx-auto px-4 py-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-200">
                {{ $t('saldo.dashboard.title') }}
            </h1>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <div class="card">
                <div class="text-sm text-gray-500">{{ $t('saldo.dashboard.totalBalance') }}</div>
                <div class="text-2xl font-bold text-gray-800">{{ formatCurrency(stats.totalBalance) }}</div>
            </div>
            <div class="card">
                <div class="text-sm text-gray-500">{{ $t('saldo.dashboard.totalRecharged') }}</div>
                <div class="text-2xl font-bold text-green-600">{{ formatCurrency(stats.totalRecharged) }}</div>
            </div>
            <div class="card">
                <div class="text-sm text-gray-500">{{ $t('saldo.dashboard.totalTransferred') }}</div>
                <div class="text-2xl font-bold text-blue-600">{{ formatCurrency(stats.totalTransferred) }}</div>
            </div>
            <div class="card">
                <div class="text-sm text-gray-500">{{ $t('saldo.dashboard.totalInvoiced') }}</div>
                <div class="text-2xl font-bold text-orange-600">{{ formatCurrency(stats.totalInvoiced) }}</div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="card">
                <h2 class="text-lg font-semibold mb-4">{{ $t('saldo.dashboard.recentActivity') }}</h2>
                <DataTable :value="recentActivity" :rows="5" stripedRows>
                    <Column field="movementType" :header="$t('saldo.history.movementType')" />
                    <Column field="amount" :header="$t('saldo.history.amount')">
                        <template #body="{ data }">
                            <span :class="data.amount.startsWith('-') ? 'text-danger' : 'text-success'">
                                {{ data.amount }}
                            </span>
                        </template>
                    </Column>
                    <Column field="createdAt" :header="$t('saldo.history.date')" />
                </DataTable>
            </div>

            <div class="card">
                <h2 class="text-lg font-semibold mb-4">{{ $t('saldo.accounts.title') }}</h2>
                <DataTable :value="accounts" :rows="5" stripedRows>
                    <Column field="accountNumber" :header="$t('saldo.accounts.accountNumber')" />
                    <Column field="businessName" :header="$t('saldo.accounts.businessName')" />
                    <Column field="status" :header="$t('saldo.accounts.status')">
                        <template #body="{ data }">
                            <span :class="getStatusBadgeClass(data.status)">
                                {{ data.status }}
                            </span>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import { useToast } from 'primevue/usetoast';

const { t } = useI18n();
const toast = useToast();

const stats = ref({
    totalBalance: '0.00',
    totalRecharged: '0.00',
    totalTransferred: '0.00',
    totalInvoiced: '0.00',
});

const recentActivity = ref([]);
const accounts = ref([]);

const loadDashboard = async () => {
    try {
        // TODO: Implement dashboard API call
        // For now, use mock data
        stats.value = {
            totalBalance: '15,000.00',
            totalRecharged: '25,000.00',
            totalTransferred: '5,000.00',
            totalInvoiced: '5,000.00',
        };

        recentActivity.value = [
            { movementType: 'Recharge', amount: '1,000.00', createdAt: '2024-01-15' },
            { movementType: 'Transfer', amount: '-500.00', createdAt: '2024-01-14' },
            { movementType: 'Invoice', amount: '-200.00', createdAt: '2024-01-13' },
        ];

        accounts.value = [
            { accountNumber: 'SAL240101ABC', businessName: 'Business 1', status: 'active' },
            { accountNumber: 'SAL240101DEF', businessName: 'Business 2', status: 'active' },
        ];
    } catch (error) {
        toast.add({ severity: 'error', summary: t('saldo.common.error'), detail: error.message });
    }
};

const formatCurrency = (value) => {
    return `$${value}`;
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
    loadDashboard();
});
</script>
