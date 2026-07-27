<template>
    <Card>
        <template #title>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-200">
                {{ $t('history.title') }}
            </h1>
        </template>
        <template #content>
            <div class="container mx-auto px-4 py-8">

                <div class="card mb-6">
                    <div class="flex flex-wrap gap-4 items-center justify-between">
                        <div class="flex gap-2">
                            <Select v-model="filters.movementType" :options="typeOptions" optionLabel="label"
                                optionValue="value" class="w-40" :placeholder="$t('history.movementType')"
                                @change="loadHistory" />
                            <InputText v-model="filters.dateFrom" type="date" class="w-40" @change="loadHistory" />
                            <InputText v-model="filters.dateTo" type="date" class="w-40" @change="loadHistory" />
                            <InputText v-model="filters.search" :placeholder="$t('common.search')"
                                @keyup.enter="loadHistory" />
                            <Button :label="$t('common.search')" @click="loadHistory" />
                            <Button :label="$t('common.export')" icon="pi pi-download" class="p-button-outlined"
                                @click="exportData" />
                        </div>
                    </div>
                </div>
                <DataTable :value="movements" :loading="loading" size="small" :paginator="true" :rows="10"
                    responsiveLayout="scroll">
                    <Column field="createdAt" :header="$t('history.date')">
                        <template #body="{ data }">
                            {{ formatDate(data.createdAt) }}
                        </template>
                    </Column>
                    <Column field="movementType" :header="$t('history.movementType')">
                        <template #body="{ data }">
                            <span
                                :class="['px-2 py-1 text-xs rounded-full font-medium', isCreditLike(data.movementType) ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800']">
                                {{ $t('history.' + data.movementType, data.movementType) }}
                            </span>
                        </template>
                    </Column>
                    <Column field="description" :header="$t('history.description')" />
                    <Column field="amount" :header="$t('history.amount')">
                        <template #body="{ data }">
                            <span :class="[isCreditLike(data.movementType) ? 'text-emerald-600' : 'text-red-600']">
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
                    <Column field="performedBy" :header="$t('recharges.authorizedBy')">
                        <template #body="{ data }">
                            {{ data.performedBy || '—' }}
                        </template>
                    </Column>
                    <Column :header="$t('common.actions')" style="width: 4rem">
                        <template #body="{ data }">
                            <Button v-if="data.referenceType && data.referenceId" icon="pi pi-history"
                                class="p-button-rounded p-button-text" @click="showTimeline(data)" />
                        </template>
                    </Column>
                </DataTable>

                <Dialog v-model:visible="showTimelineModal" :header="$t('history.timeline')" :modal="true"
                    :style="{ width: '550px' }">
                    <div v-if="timelineLoading" class="text-center py-6 text-gray-500">{{ $t('common.loading') }}</div>
                    <div v-else-if="!timeline.length" class="text-center py-6 text-gray-500">{{ $t('common.no_data') }}
                    </div>
                    <ol v-else class="flex flex-col gap-3">
                        <li v-for="event in timeline" :key="event.id" class="border-l-2 border-emerald-400 pl-3">
                            <div class="text-sm font-semibold text-gray-800">{{ event.status }}</div>
                            <div class="text-xs text-gray-500">{{ formatDate(event.createdAt) }}</div>
                            <div class="text-xs text-gray-600" v-if="event.performedBy">{{ $t('recharges.authorizedBy')
                                }}: {{
                                event.performedBy }}</div>
                            <div class="text-xs text-gray-500" v-if="event.notes">{{ event.notes }}</div>
                        </li>
                    </ol>
                    <template #footer>
                        <Button :label="$t('common.close')" class="p-button-text" @click="showTimelineModal = false" />
                    </template>
                </Dialog>
            </div>
        </template>
    </Card>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Dialog from 'primevue/dialog';
import { useToast } from 'primevue/usetoast';
import Card from 'primevue/card';
import common from '../../common/common.js';

const { t } = useI18n();
const toast = useToast();

const urls = document.getElementById('vue-app').dataset;
const onAjaxError = (jqXHR, textStatus, errorThrown) => {
    toast.add({ severity: 'error', summary: t('common.error'), detail: jqXHR?.responseJSON?.message || errorThrown || textStatus });
};

const movements = ref([]);
const loading = ref(false);
const showTimelineModal = ref(false);
const timeline = ref([]);
const timelineLoading = ref(false);

const filters = ref({ movementType: '', dateFrom: '', dateTo: '', search: '' });

// Los valores deben coincidir exactamente con BalanceMovement.movementType (ver los distintos
// `type:`/setMovementType() en BalanceService y sus llamadores) — antes tenía opciones ('credit',
// 'debit', 'transfer') que no correspondían a ningún valor real y nunca filtraban nada.
const typeOptions = [
    { label: t('common.all'), value: '' },
    { label: t('history.recharge'), value: 'recharge' },
    { label: t('history.transfer_out'), value: 'transfer_out' },
    { label: t('history.transfer_in'), value: 'transfer_in' },
    { label: t('history.invoice_pay'), value: 'invoice_pay' },
    { label: t('history.invoice_credit'), value: 'invoice_credit' },
    { label: t('history.adjustment'), value: 'adjustment' },
    { label: t('history.reconciliation_settlement'), value: 'reconciliation_settlement' },
    { label: t('history.authorized_spend'), value: 'authorized_spend' },
    { label: t('history.reserve'), value: 'reserve' },
    { label: t('history.release'), value: 'release' },
];

// "Créditos" (verde): movimientos que aumentan el saldo disponible de la cuenta mostrada.
const CREDIT_LIKE_TYPES = ['recharge', 'invoice_credit', 'adjustment', 'transfer_in', 'release'];
const isCreditLike = (movementType) => CREDIT_LIKE_TYPES.includes(movementType);

const loadHistory = () => {
    loading.value = true;
    const params = {};
    if (filters.value.movementType) params.movementType = filters.value.movementType;
    if (filters.value.dateFrom) params.dateFrom = filters.value.dateFrom;
    if (filters.value.dateTo) params.dateTo = filters.value.dateTo;

    common.ajax(urls.listUrl, 'GET', params, (data) => {
        if (data.success) {
            movements.value = data.data;
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
        loading.value = false;
    }, (...args) => {
        onAjaxError(...args);
        loading.value = false;
    });
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

const showTimeline = (movement) => {
    showTimelineModal.value = true;
    timelineLoading.value = true;
    timeline.value = [];
    const params = { entityType: movement.referenceType, entityId: movement.referenceId };

    common.ajax(urls.timelineUrl, 'GET', params, (data) => {
        if (data.success) {
            timeline.value = data.data;
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
        timelineLoading.value = false;
    }, (...args) => {
        onAjaxError(...args);
        timelineLoading.value = false;
    });
};

onMounted(() => {
    loadHistory();
});
</script>
