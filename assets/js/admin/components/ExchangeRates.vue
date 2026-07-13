<template>
    <div class="container mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-800">{{ $t('exchange_rates.title') }}</h1>
            <Button :label="$t('exchange_rates.fetch_rates')" icon="pi pi-refresh" @click="fetchRates" :loading="fetching" />
        </div>

        <div class="card mb-6">
            <div class="flex flex-wrap gap-4 items-center">
                <Select v-model="filterProvider" :options="providers" optionLabel="name" optionValue="id" class="w-64" :placeholder="$t('common.all')" @change="loadRates" />
            </div>
        </div>

        <div class="card">
            <DataTable :value="rates" :loading="loading" stripedRows responsiveLayout="scroll">
                <Column field="providerName" :header="$t('exchange_providers.title')" />
                <Column field="fromCurrency" :header="$t('exchange_rates.pair')">
                    <template #body="{ data }">
                        <span class="px-2 py-1 text-xs font-mono bg-blue-100 text-blue-800 rounded">
                            {{ data.fromCurrency }} / {{ data.toCurrency }}
                        </span>
                    </template>
                </Column>
                <Column field="rate" :header="$t('exchange_rates.rate')">
                    <template #body="{ data }">
                        <span class="font-semibold">{{ parseFloat(data.rate).toFixed(6) }}</span>
                    </template>
                </Column>
                <Column field="inverseRate" :header="$t('exchange_rates.inverse_rate')">
                    <template #body="{ data }">
                        {{ data.inverseRate ? parseFloat(data.inverseRate).toFixed(6) : '—' }}
                    </template>
                </Column>
                <Column field="fetchedAt" :header="$t('exchange_rates.fetched_at')">
                    <template #body="{ data }">
                        {{ formatDate(data.fetchedAt) }}
                    </template>
                </Column>
                <Column field="isActive" :header="$t('common.status')">
                    <template #body="{ data }">
                        <span :class="['px-2 py-1 text-xs rounded-full', data.isActive ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-800']">
                            {{ data.isActive ? $t('common.active') : $t('common.inactive') }}
                        </span>
                    </template>
                </Column>
            </DataTable>
            <div v-if="!rates.length && !loading" class="text-center py-8 text-gray-500">
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
import Select from 'primevue/select';
import { useToast } from 'primevue/usetoast';

const { t } = useI18n();
const toast = useToast();

const rates = ref([]);
const providers = ref([]);
const loading = ref(false);
const fetching = ref(false);
const filterProvider = ref('');

const loadProviders = async () => {
    try {
        const response = await fetch('/exchange-providers/list');
        const data = await response.json();
        if (data.success) providers.value = data.data;
    } catch (e) {}
};

const loadRates = async () => {
    loading.value = true;
    try {
        const params = new URLSearchParams();
        if (filterProvider.value) params.append('providerId', filterProvider.value);

        const response = await fetch(`/exchange-rates/list?${params}`);
        const data = await response.json();

        if (data.success) {
            rates.value = data.data;
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: t('common.error'), detail: error.message });
    } finally {
        loading.value = false;
    }
};

const fetchRates = async () => {
    fetching.value = true;
    try {
        const response = await fetch('/exchange-rates/fetch', { method: 'POST' });
        const data = await response.json();
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: data.message });
            loadRates();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: t('common.error'), detail: error.message });
    } finally {
        fetching.value = false;
    }
};

const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('es-ES', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
};

onMounted(() => {
    loadProviders();
    loadRates();
});
</script>
