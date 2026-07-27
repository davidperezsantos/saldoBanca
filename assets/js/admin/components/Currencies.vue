<template>
    <Card>
        <template #title>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-200">
                {{ $t('currencies.title') }}
            </h1>
        </template>
        <template #content>
            <div class="container mx-auto px-4 py-8">

                <div class="card mb-6">
                    <div class="flex flex-wrap gap-4 items-end">
                        <Button v-if="common.can('currencies:create')" :label="$t('currencies.create')" icon="pi pi-plus" @click="openCreateModal" />
                    </div>
                </div>
                <DataTable :value="currencies" :loading="loading" :paginator="true" :rows="10" size="small"
                    responsiveLayout="scroll">
                    <Column field="code" :header="$t('common.code')">
                        <template #body="{ data }">
                            <span class="px-2 py-1 text-xs font-mono bg-gray-100 rounded">{{ data.code }}</span>
                        </template>
                    </Column>
                    <Column field="name" :header="$t('common.name')" />
                    <Column field="symbol" :header="$t('currencies.symbol')">
                        <template #body="{ data }">
                            {{ data.symbol || '—' }}
                        </template>
                    </Column>
                    <Column field="isActive" :header="$t('common.status')">
                        <template #body="{ data }">
                            <span v-if="data.isActive"
                                class="px-2 py-1 text-xs rounded-full bg-emerald-100 text-emerald-800">{{
                                $t('common.active') }}</span>
                            <span v-else class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-500">{{
                                $t('common.inactive') }}</span>
                        </template>
                    </Column>
                    <Column :header="$t('common.actions')" style="width: 10rem">
                        <template #body="{ data }">
                            <div class="flex gap-2">
                                <Button v-if="common.can('currencies:edit')" icon="pi pi-pencil" class="p-button-rounded p-button-warning p-button-text"
                                    @click="editCurrency(data)" />
                                <Button v-if="common.can('currencies:status')" :icon="data.isActive ? 'pi pi-power-off' : 'pi pi-check-circle'"
                                    :class="['p-button-rounded p-button-text', data.isActive ? 'p-button-danger' : 'p-button-success']"
                                    @click="toggleStatus(data)" />
                            </div>
                        </template>
                    </Column>
                </DataTable>

                <Dialog v-model:visible="showModal" :header="editingId ? $t('common.edit') : $t('currencies.create')"
                    :modal="true" :style="{ width: '480px' }">
                    <div class="flex flex-col gap-4">
                        <div class="form-group">
                            <label class="form-label">{{ $t('common.code') }}</label>
                            <InputText v-model="form.code" class="w-full" maxlength="3" :disabled="!!editingId"
                                placeholder="USD" />
                        </div>
                        <div class="form-group">
                            <label class="form-label">{{ $t('common.name') }}</label>
                            <InputText v-model="form.name" class="w-full" placeholder="Dólar estadounidense" />
                        </div>
                        <div class="form-group">
                            <label class="form-label">{{ $t('currencies.symbol') }}</label>
                            <InputText v-model="form.symbol" class="w-full" maxlength="5" placeholder="$" />
                        </div>
                        <div v-if="!editingId" class="flex items-center">
                            <input type="checkbox" v-model="form.isActive" id="isActive"
                                class="h-4 w-4 text-blue-600 rounded border-gray-300" />
                            <label for="isActive" class="ml-2 text-sm text-gray-700">{{ $t('common.active') }}</label>
                        </div>
                    </div>
                    <template #footer>
                        <Button :label="$t('common.cancel')" class="p-button-text" @click="closeModal" />
                        <Button :label="$t('common.save')" @click="saveCurrency" :loading="saving" />
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

const currencies = ref([]);
const loading = ref(false);
const saving = ref(false);
const showModal = ref(false);
const editingId = ref(null);

const form = ref({ code: '', name: '', symbol: '', isActive: true });

const loadCurrencies = () => {
    loading.value = true;
    common.ajax(urls.listUrl, 'GET', null, (data) => {
        if (data.success) currencies.value = data.data;
        loading.value = false;
    }, (...args) => {
        onAjaxError(...args);
        loading.value = false;
    });
};

const openCreateModal = () => {
    editingId.value = null;
    form.value = { code: '', name: '', symbol: '', isActive: true };
    showModal.value = true;
};

const editCurrency = (currency) => {
    editingId.value = currency.id;
    form.value = { code: currency.code, name: currency.name, symbol: currency.symbol || '', isActive: currency.isActive };
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingId.value = null;
};

const saveCurrency = () => {
    saving.value = true;
    const url = editingId.value ? urls.detailUrl.replace('__ID__', editingId.value) : urls.createUrl;
    const method = editingId.value ? 'PUT' : 'POST';

    common.ajax(url, method, JSON.stringify(form.value), (data) => {
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: data.message });
            closeModal();
            loadCurrencies();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
        saving.value = false;
    }, (...args) => {
        onAjaxError(...args);
        saving.value = false;
    });
};

const toggleStatus = (currency) => {
    const newActive = !currency.isActive;
    if (!confirm(`¿${newActive ? 'Activar' : 'Desactivar'} "${currency.name}"?`)) return;

    common.ajax(urls.statusUrl.replace('__ID__', currency.id), 'PUT', JSON.stringify({ isActive: newActive }), (data) => {
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: data.message });
            loadCurrencies();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
    }, onAjaxError);
};

onMounted(() => {
    loadCurrencies();
});
</script>
