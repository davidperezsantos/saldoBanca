<template>
    <Card>
        <template #title>
            <h1 class="text-2xl font-bold text-gray-800">{{ $t('payment_gateways.title') }}</h1>
        </template>
        <template #content>
            <div class="container mx-auto px-4 py-8">
                <div class="flex items-center justify-between mb-6">
                    <Button v-if="common.can('payment_gateway:create')" :label="$t('payment_gateways.create')" icon="pi pi-plus" @click="openCreateModal" />
                </div>
                <DataTable :value="gateways" :loading="loading" size="small" :paginator="true" :rows="10"
                    responsiveLayout="scroll">
                    <Column field="name" :header="$t('payment_gateways.name')" />
                    <Column field="code" :header="$t('payment_gateways.code')">
                        <template #body="{ data }">
                            <span class="px-2 py-1 text-xs font-mono bg-gray-100 rounded">{{ data.code }}</span>
                        </template>
                    </Column>
                    <Column field="authType" :header="$t('payment_gateways.auth_type')">
                        <template #body="{ data }">
                            <span :class="['px-2 py-1 text-xs rounded-full',
                                data.authType === 'token' ? 'bg-blue-100 text-blue-800' :
                                    data.authType === 'api_key' ? 'bg-green-100 text-green-800' :
                                        'bg-purple-100 text-purple-800']">
                                {{ $t('payment_gateways.' + data.authType) }}
                            </span>
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
                    <Column field="isDefault" :header="$t('payment_gateways.is_default')">
                        <template #body="{ data }">
                            <i
                                :class="data.isDefault ? 'pi pi-check text-emerald-500' : 'pi pi-minus text-gray-300'"></i>
                        </template>
                    </Column>
                    <Column :header="$t('common.actions')" style="width: 10rem">
                        <template #body="{ data }">
                            <div class="flex gap-2">
                                <Button v-if="common.can('payment_gateway:edit')" icon="pi pi-pencil" class="p-button-rounded p-button-warning p-button-text"
                                    @click="editGateway(data)" />
                                <Button v-if="common.can('payment_gateway:edit')" :icon="data.status === 'active' ? 'pi pi-power-off' : 'pi pi-check-circle'"
                                    :class="['p-button-rounded p-button-text', data.status === 'active' ? 'p-button-danger' : 'p-button-success']"
                                    @click="toggleStatus(data)" />
                            </div>
                        </template>
                    </Column>
                </DataTable>

                <Dialog v-model:visible="showModal"
                    :header="editingId ? $t('common.edit') : $t('payment_gateways.create')" :modal="true"
                    :style="{ width: '500px' }">
                    <div class="flex flex-col gap-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="form-group">
                                <label class="form-label">{{ $t('payment_gateways.name') }}</label>
                                <InputText v-model="form.name" class="w-full" />
                            </div>
                            <div class="form-group">
                                <label class="form-label">{{ $t('payment_gateways.code') }}</label>
                                <InputText v-model="form.code" class="w-full" />
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">{{ $t('payment_gateways.auth_type') }}</label>
                            <Select v-model="form.authType" :options="authOptions" optionLabel="label"
                                optionValue="value" class="w-full" />
                        </div>
                        <div class="form-group">
                            <label class="form-label">{{ $t('payment_gateways.notes') }}</label>
                            <Textarea v-model="form.notes" class="w-full" rows="2" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="form-group">
                                <label class="form-label">{{ $t('common.status') }}</label>
                                <Select v-model="form.status"
                                    :options="[{ label: t('common.active'), value: 'active' }, { label: t('common.inactive'), value: 'inactive' }]"
                                    optionLabel="label" optionValue="value" class="w-full" />
                            </div>
                            <div class="form-group flex items-center pt-6">
                                <input type="checkbox" v-model="form.isDefault" :id="'defaultGateway'"
                                    class="h-4 w-4 text-blue-600 rounded border-gray-300" />
                                <label :for="'defaultGateway'" class="ml-2 text-sm text-gray-700">{{
                                    $t('payment_gateways.is_default')
                                    }}</label>
                            </div>
                        </div>
                    </div>
                    <template #footer>
                        <Button :label="$t('common.cancel')" class="p-button-text" @click="closeModal" />
                        <Button :label="$t('common.save')" @click="saveGateway" :loading="saving" />
                    </template>
                </Dialog>

                <ConfirmDialog />
                <Toast />
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
import Textarea from 'primevue/textarea';
import Dialog from 'primevue/dialog';
import ConfirmDialog from 'primevue/confirmdialog';
import Toast from 'primevue/toast';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import common from '../../common/common.js';
import Card from 'primevue/card';

const { t } = useI18n();
const confirm = useConfirm();
const toast = useToast();

const urls = document.getElementById('vue-app').dataset;
const onAjaxError = (jqXHR, textStatus, errorThrown) => {
    toast.add({ severity: 'error', summary: t('common.error'), detail: jqXHR?.responseJSON?.message || errorThrown || textStatus });
};

const gateways = ref([]);
const loading = ref(false);
const saving = ref(false);
const showModal = ref(false);
const editingId = ref(null);

const authOptions = [
    { label: t('payment_gateways.token'), value: 'token' },
    { label: t('payment_gateways.api_key'), value: 'api_key' },
    { label: t('payment_gateways.user_pass'), value: 'user_pass' },
];

const form = ref({
    name: '',
    code: '',
    authType: 'token',
    status: 'active',
    isDefault: false,
    notes: '',
});

const loadGateways = () => {
    loading.value = true;
    common.ajax(urls.listUrl, 'GET', null, (data) => {
        if (data.success) gateways.value = data.data;
        loading.value = false;
    }, (...args) => {
        onAjaxError(...args);
        loading.value = false;
    });
};

const openCreateModal = () => {
    editingId.value = null;
    form.value = { name: '', code: '', authType: 'token', status: 'active', isDefault: false, notes: '' };
    showModal.value = true;
};

const editGateway = (gateway) => {
    editingId.value = gateway.id;
    form.value = {
        name: gateway.name,
        code: gateway.code,
        authType: gateway.authType,
        status: gateway.status,
        isDefault: gateway.isDefault,
        notes: gateway.notes || '',
    };
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingId.value = null;
};

const saveGateway = () => {
    saving.value = true;
    const url = editingId.value ? urls.detailUrl.replace('__ID__', editingId.value) : urls.createUrl;
    const method = editingId.value ? 'PUT' : 'POST';

    common.ajax(url, method, JSON.stringify(form.value), (data) => {
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: data.message });
            closeModal();
            loadGateways();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
        saving.value = false;
    }, (...args) => {
        onAjaxError(...args);
        saving.value = false;
    });
};

const toggleStatus = (gateway) => {
    const newStatus = gateway.status === 'active' ? 'inactive' : 'active';
    confirm.require({
        message: `¿${newStatus === 'active' ? 'Activar' : 'Desactivar'} "${gateway.name}"?`,
        header: 'Confirmar',
        icon: 'pi pi-question-circle',
        acceptClass: newStatus === 'active' ? 'p-button-success' : 'p-button-danger',
        accept: () => {
            common.ajax(urls.statusUrl.replace('__ID__', gateway.id), 'PUT', JSON.stringify({ status: newStatus }), (data) => {
                if (data.success) {
                    toast.add({ severity: 'success', summary: t('common.success'), detail: 'Estado cambiado' });
                    loadGateways();
                } else {
                    toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
                }
            }, onAjaxError);
        }
    });
};

onMounted(() => {
    loadGateways();
});
</script>
