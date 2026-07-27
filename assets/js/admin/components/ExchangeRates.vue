<template>
    <Card>
        <template #title>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-200">
                {{ $t('exchange_rates.title') }}
            </h1>
        </template>
        <template #content>
            <div class="container mx-auto px-4 py-8">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex gap-2">
                        <Button v-if="common.can('exchange.rates:fetch')" :label="$t('exchange_rates.fetch_rates')" icon="pi pi-refresh" @click="fetchRates"
                            :loading="fetching" />
                        <Button v-if="common.can('exchange.rates:fetch')" :label="$t('exchange_rates.manual_create')" icon="pi pi-pencil"
                            class="p-button-outlined" @click="openManualModal" />
                    </div>
                    <Select v-model="filterProvider" :options="providers" optionLabel="name" optionValue="id"
                        class="w-64" :placeholder="$t('common.all')" @change="loadRates" />
                </div>
                <DataTable :value="rates" :loading="loading" size="small" :paginator="true" :rows="10"
                    responsiveLayout="scroll">
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
                            <span class="font-semibold">{{ parseFloat(data.rate).toFixed(4) }}</span>
                        </template>
                    </Column>
                    <Column field="inverseRate" :header="$t('exchange_rates.inverse_rate')">
                        <template #body="{ data }">
                            {{ data.inverseRate ? parseFloat(data.inverseRate).toFixed(4) : '—' }}
                        </template>
                    </Column>
                    <Column field="fetchedAt" :header="$t('exchange_rates.fetched_at')">
                        <template #body="{ data }">
                            {{ formatDate(data.fetchedAt) }}
                        </template>
                    </Column>
                    <Column field="isActive" :header="$t('common.status')">
                        <template #body="{ data }">
                            <div class="flex items-center gap-1">
                                <span
                                    :class="['px-2 py-1 text-xs rounded-full', data.isActive ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-800']">
                                    {{ data.isActive ? $t('common.active') : $t('common.inactive') }}
                                </span>
                                <i v-if="data.isLocked" class="pi pi-lock text-amber-500"
                                    :title="$t('exchange_rates.locked_badge')"></i>
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </div>

            <Dialog v-model:visible="showManualModal" :header="$t('exchange_rates.manual_rate_title')" :modal="true"
                :style="{ width: '450px' }">
                <div class="flex flex-col gap-4">
                    <div class="form-group">
                        <label class="form-label">{{ $t('exchange_rates.currency') }}</label>
                        <Select v-model="manualForm.toCurrency" :options="currencyOptions" optionLabel="label"
                            optionValue="value" class="w-full" filter />
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ $t('exchange_rates.rate') }}</label>
                        <InputNumber v-model="manualForm.rate" class="w-full" :minFractionDigits="4"
                            :maxFractionDigits="4" :min="0" />
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" v-model="manualForm.locked" id="manualLocked"
                            class="h-4 w-4 text-blue-600 rounded border-gray-300" />
                        <label for="manualLocked" class="ml-2 text-sm text-gray-700">{{ $t('exchange_rates.locked')
                            }}</label>
                    </div>
                </div>
                <template #footer>
                    <Button :label="$t('common.cancel')" class="p-button-text" @click="showManualModal = false" />
                    <Button :label="$t('common.save')" @click="saveManualRate" :loading="savingManual" />
                </template>
            </Dialog>

            <Toast />
        </template>
    </Card>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Select from 'primevue/select';
import InputNumber from 'primevue/inputnumber';
import Dialog from 'primevue/dialog';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import Card from 'primevue/card';
import common from '../../common/common.js';

const { t } = useI18n();
const toast = useToast();

const urls = document.getElementById('vue-app').dataset;
const onAjaxError = (jqXHR, textStatus, errorThrown) => {
    toast.add({ severity: 'error', summary: t('common.error'), detail: jqXHR?.responseJSON?.message || errorThrown || textStatus });
};

const rates = ref([]);
const providers = ref([]);
const currencyOptions = ref([]);
const loading = ref(false);
const fetching = ref(false);
const filterProvider = ref('');
const showManualModal = ref(false);
const savingManual = ref(false);
const manualForm = ref({ toCurrency: null, rate: null, locked: false });

const loadProviders = () => {
    common.ajax(urls.providersListUrl, 'GET', null, (data) => {
        if (data.success) providers.value = data.data;
    }, () => {});
};

const loadCurrencies = () => {
    common.ajax(urls.currenciesListUrl, 'GET', { active: 1 }, (data) => {
        if (data.success) currencyOptions.value = data.data.map(c => ({ label: c.code, value: c.code }));
    }, onAjaxError);
};

const loadRates = () => {
    loading.value = true;
    const params = {};
    if (filterProvider.value) params.providerId = filterProvider.value;

    common.ajax(urls.listUrl, 'GET', params, (data) => {
        if (data.success) {
            rates.value = data.data;
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
        loading.value = false;
    }, (...args) => {
        onAjaxError(...args);
        loading.value = false;
    });
};

const fetchRates = () => {
    fetching.value = true;
    common.ajax(urls.fetchUrl, 'POST', null, (data) => {
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: data.message });
            loadRates();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
        fetching.value = false;
    }, (...args) => {
        onAjaxError(...args);
        fetching.value = false;
    });
};

const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('es-ES', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
};

const openManualModal = () => {
    manualForm.value = { toCurrency: null, rate: null, locked: false };
    showManualModal.value = true;
};

const saveManualRate = () => {
    if (!manualForm.value.toCurrency || !manualForm.value.rate) {
        toast.add({ severity: 'warn', summary: t('common.warning'), detail: t('exchange_rates.currency_required') });
        return;
    }
    savingManual.value = true;
    const payload = {
        toCurrency: manualForm.value.toCurrency,
        rate: String(manualForm.value.rate),
        locked: manualForm.value.locked,
    };

    common.ajax(urls.manualCreateUrl, 'POST', JSON.stringify(payload), (data) => {
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: data.message });
            showManualModal.value = false;
            loadRates();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
        savingManual.value = false;
    }, (...args) => {
        onAjaxError(...args);
        savingManual.value = false;
    });
};

onMounted(() => {
    loadProviders();
    loadCurrencies();
    loadRates();
});
</script>
