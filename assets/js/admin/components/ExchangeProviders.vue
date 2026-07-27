<template>
    <Card>
        <template #title>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-200">
                {{ $t('exchange_providers.title') }}
            </h1>
        </template>
        <template #content>
            <div class="container mx-auto px-4 py-8">
                <div class="flex items-center justify-between mb-6">
                    <Button v-if="common.can('exchange.providers:create')" :label="$t('exchange_providers.create')" icon="pi pi-plus" @click="openCreateModal" />
                </div>

                <DataTable :value="providers" :loading="loading" size="small" :paginator="true" :rows="10"
                    responsiveLayout="scroll">
                    <Column field="name" :header="$t('common.name')" />
                    <Column field="code" :header="$t('common.code')">
                        <template #body="{ data }">
                            <span class="px-2 py-1 text-xs font-mono bg-gray-100 rounded">{{ data.code }}</span>
                        </template>
                    </Column>
                    <Column field="authType" header="Auth">
                        <template #body="{ data }">
                            <span class="text-xs uppercase">{{ data.authType || '—' }}</span>
                        </template>
                    </Column>
                    <Column field="apiUrl" :header="$t('exchange_providers.api_url')">
                        <template #body="{ data }">
                            <span class="text-sm text-gray-500 truncate max-w-xs block">{{ data.apiUrl || '—' }}</span>
                        </template>
                    </Column>
                    <Column field="isActive" header="Activo">
                        <template #body="{ data }">
                            <span v-if="data.isActive"
                                class="px-2 py-1 text-xs rounded-full bg-emerald-100 text-emerald-800">Sí</span>
                            <span v-else class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-500">No</span>
                        </template>
                    </Column>
                    <Column field="status" :header="$t('common.status')">
                        <template #body="{ data }">
                            <span
                                :class="['px-2 py-1 text-xs rounded-full', data.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800']">
                                {{ data.status === 'active' ? $t('common.active') : $t('common.inactive') }}
                            </span>
                        </template>
                    </Column>
                    <Column :header="$t('common.actions')" style="width: 10rem">
                        <template #body="{ data }">
                            <div class="flex gap-2">
                                <Button v-if="common.can('exchange.providers:edit')" icon="pi pi-pencil" class="p-button-rounded p-button-warning p-button-text"
                                    @click="editProvider(data)" />
                                <Button v-if="common.can('exchange.providers:edit')" :icon="data.status === 'active' ? 'pi pi-power-off' : 'pi pi-check-circle'"
                                    :class="['p-button-rounded p-button-text', data.status === 'active' ? 'p-button-danger' : 'p-button-success']"
                                    @click="toggleStatus(data)" />
                            </div>
                        </template>
                    </Column>
                </DataTable>

                <Dialog v-model:visible="showModal"
                    :header="editingId ? $t('common.edit') : $t('exchange_providers.create')" :modal="true"
                    :style="{ width: '580px' }">
                    <div class="flex flex-col gap-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="form-group">
                                <label class="form-label">{{ $t('common.name') }}</label>
                                <InputText v-model="form.name" class="w-full" />
                            </div>
                            <div class="form-group">
                                <label class="form-label">{{ $t('common.code') }}</label>
                                <InputText v-model="form.code" class="w-full" />
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">{{ $t('exchange_providers.api_url') }}</label>
                            <InputText v-model="form.apiUrl" class="w-full" type="url" />
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tipo de autenticación</label>
                            <Select v-model="form.authType" :options="authTypeOptions" optionLabel="label"
                                optionValue="value" class="w-full" />
                        </div>
                        <div v-if="form.authType === 'bearer'" class="form-group">
                            <label class="form-label">Token</label>
                            <InputText v-model="form.token" class="w-full" />
                        </div>
                        <div v-if="form.authType === 'header'" class="form-group">
                            <label class="form-label">API Key</label>
                            <InputText v-model="form.apiKey" class="w-full" />
                        </div>
                        <div v-if="form.authType === 'basic'" class="grid grid-cols-2 gap-4">
                            <div class="form-group">
                                <label class="form-label">Usuario</label>
                                <InputText v-model="form.username" class="w-full" />
                            </div>
                            <div class="form-group">
                                <label class="form-label">Contraseña</label>
                                <InputText v-model="form.password" class="w-full" type="password" />
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="flex items-center">
                                <input type="checkbox" v-model="form.isActive" id="isActive"
                                    class="h-4 w-4 text-blue-600 rounded border-gray-300" />
                                <label for="isActive" class="ml-2 text-sm text-gray-700">Proveedor activo (solo
                                    uno)</label>
                            </div>
                            <div class="flex items-center">
                                <Select v-model="form.status"
                                    :options="[{ label: t('common.active'), value: 'active' }, { label: t('common.inactive'), value: 'inactive' }]"
                                    optionLabel="label" optionValue="value" class="w-32" />
                            </div>
                        </div>
                    </div>
                    <template #footer>
                        <Button :label="$t('common.cancel')" class="p-button-text" @click="closeModal" />
                        <Button :label="$t('common.save')" @click="saveProvider" :loading="saving" />
                    </template>
                </Dialog>
            </div>
        </template>
    </Card>
</template>

<script setup>
import Card from 'primevue/card';
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Dialog from 'primevue/dialog';
import { useToast } from 'primevue/usetoast';
import common from '../../common/common.js';

const { t } = useI18n();
const toast = useToast();

const urls = document.getElementById('vue-app').dataset;
const onAjaxError = (jqXHR, textStatus, errorThrown) => {
    toast.add({ severity: 'error', summary: t('common.error'), detail: jqXHR?.responseJSON?.message || errorThrown || textStatus });
};

const providers = ref([]);
const loading = ref(false);
const saving = ref(false);
const showModal = ref(false);
const editingId = ref(null);

const authTypeOptions = [
    { label: 'Ninguno', value: 'none' },
    { label: 'Bearer Token', value: 'bearer' },
    { label: 'API Key en Header', value: 'header' },
    { label: 'Basic Auth', value: 'basic' },
];

const form = ref({
    name: '',
    code: '',
    apiUrl: '',
    authType: 'none',
    apiKey: '',
    username: '',
    password: '',
    token: '',
    status: 'active',
    isActive: false,
});

const loadProviders = () => {
    loading.value = true;
    common.ajax(urls.listUrl, 'GET', null, (data) => {
        if (data.success) providers.value = data.data;
        loading.value = false;
    }, (...args) => {
        onAjaxError(...args);
        loading.value = false;
    });
};

const openCreateModal = () => {
    editingId.value = null;
    form.value = { name: '', code: '', apiUrl: '', authType: 'none', apiKey: '', username: '', password: '', token: '', status: 'active', isActive: false };
    showModal.value = true;
};

const editProvider = (provider) => {
    editingId.value = provider.id;
    form.value = {
        name: provider.name,
        code: provider.code,
        apiUrl: provider.apiUrl || '',
        authType: provider.authType || 'none',
        apiKey: provider.apiKey || '',
        username: provider.username || '',
        password: '',
        token: '',
        status: provider.status,
        isActive: provider.isActive,
    };
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingId.value = null;
};

const saveProvider = () => {
    saving.value = true;
    const url = editingId.value ? urls.detailUrl.replace('__ID__', editingId.value) : urls.createUrl;
    const method = editingId.value ? 'PUT' : 'POST';

    const body = {
        name: form.value.name,
        code: form.value.code,
        apiUrl: form.value.apiUrl,
        authType: form.value.authType,
        status: form.value.status,
        isActive: form.value.isActive,
    };

    if (form.value.authType === 'bearer' && form.value.token) body.token = form.value.token;
    if (form.value.authType === 'header' && form.value.apiKey) body.apiKey = form.value.apiKey;
    if (form.value.authType === 'basic') {
        if (form.value.username) body.username = form.value.username;
        if (form.value.password) body.password = form.value.password;
    }

    common.ajax(url, method, JSON.stringify(body), (data) => {
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: data.message });
            closeModal();
            loadProviders();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
        saving.value = false;
    }, (...args) => {
        onAjaxError(...args);
        saving.value = false;
    });
};

const toggleStatus = (provider) => {
    const newStatus = provider.status === 'active' ? 'inactive' : 'active';
    if (!confirm(`¿${newStatus === 'active' ? 'Activar' : 'Desactivar'} "${provider.name}"?`)) return;

    common.ajax(urls.statusUrl.replace('__ID__', provider.id), 'PUT', JSON.stringify({ status: newStatus }), (data) => {
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: 'Estado cambiado' });
            loadProviders();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
    }, onAjaxError);
};

onMounted(() => {
    loadProviders();
});
</script>
