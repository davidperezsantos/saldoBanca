<template>
    <div class="container mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-800">{{ $t('oauth_clients.title') }}</h1>
            <Button :label="$t('oauth_clients.create')" icon="pi pi-plus" @click="openCreateModal" />
        </div>

        <div class="card">
            <DataTable :value="clients" :loading="loading" stripedRows responsiveLayout="scroll" scrollable>
                <Column field="name" :header="$t('oauth_clients.name')" style="min-width: 120px">
                    <template #body="{ data }">
                        <span class="block truncate max-w-40" :title="data.name">{{ data.name }}</span>
                    </template>
                </Column>
                <Column field="identifier" :header="$t('oauth_clients.client_id')" style="min-width: 140px">
                    <template #body="{ data }">
                        <span class="px-2 py-1 text-xs font-mono bg-gray-100 rounded block truncate max-w-32" :title="data.identifier">{{ data.identifier }}</span>
                    </template>
                </Column>
                <Column field="secret" :header="$t('oauth_clients.secret')" style="min-width: 120px">
                    <template #body="{ data }">
                        <span v-if="data.secret" class="px-2 py-1 text-xs font-mono bg-gray-100 rounded block truncate max-w-36 select-all" :title="data.secret">{{ data.secret }}</span>
                        <span v-else class="text-gray-400 text-xs">{{ $t('oauth_clients.public') }}</span>
                    </template>
                </Column>
                <Column field="grants" :header="$t('oauth_clients.grants')">
                    <template #body="{ data }">
                        <div class="flex flex-wrap gap-1">
                            <span v-for="g in data.grants" :key="g" class="px-2 py-0.5 text-xs bg-blue-100 text-blue-800 rounded-full">{{ g }}</span>
                        </div>
                    </template>
                </Column>
                <Column field="active" :header="$t('common.status')">
                    <template #body="{ data }">
                        <span :class="['px-2 py-1 text-xs rounded-full', data.active ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800']">
                            {{ data.active ? $t('common.active') : $t('common.inactive') }}
                        </span>
                    </template>
                </Column>
                <Column :header="$t('common.actions')" style="width: 12rem">
                    <template #body="{ data }">
                        <div class="flex gap-2">
                            <Button icon="pi pi-eye" class="p-button-rounded p-button-info p-button-text" @click="viewClient(data)" />
                            <Button icon="pi pi-pencil" class="p-button-rounded p-button-warning p-button-text" @click="editClient(data)" />
                            <Button icon="pi pi-trash" class="p-button-rounded p-button-danger p-button-text" @click="deleteClient(data)" />
                            <Button
                                :icon="data.active ? 'pi pi-power-off' : 'pi pi-check-circle'"
                                :class="['p-button-rounded p-button-text', data.active ? 'p-button-danger' : 'p-button-success']"
                                @click="toggleStatus(data)"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
            <div v-if="!clients.length && !loading" class="text-center py-8 text-gray-500">
                {{ $t('common.no_data') }}
            </div>
        </div>

        <Dialog v-model:visible="showModal" :header="editingId ? $t('common.edit') : $t('oauth_clients.create')" :modal="true" :style="{ width: '550px' }">
            <div class="flex flex-col gap-4">
                <div class="form-group">
                    <label class="form-label">{{ $t('oauth_clients.name') }} *</label>
                    <InputText v-model="form.name" class="w-full" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="form-label">{{ $t('oauth_clients.client_id') }}</label>
                        <InputText :value="editingId ? form.identifier : $t('oauth_clients.auto_generated')" class="w-full" readonly />
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ $t('oauth_clients.secret') }}</label>
                        <InputText :value="editingId && form.secret ? '••••••••' : $t('oauth_clients.auto_generated')" class="w-full" readonly :type="editingId ? 'password' : 'text'" />
                    </div>
                </div>
                <div class="flex items-center gap-6">
                    <div class="flex items-center">
                        <input type="checkbox" v-model="form.public" :id="'publicClient'" class="h-4 w-4 text-blue-600 rounded border-gray-300" @change="onPublicChange" />
                        <label :for="'publicClient'" class="ml-2 text-sm text-gray-700">{{ $t('oauth_clients.public') }}</label>
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" v-model="form.allowPlainTextPkce" :id="'allowPkce'" class="h-4 w-4 text-blue-600 rounded border-gray-300" />
                        <label :for="'allowPkce'" class="ml-2 text-sm text-gray-700">{{ $t('oauth_clients.allow_plain_text_pkce') }}</label>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ $t('oauth_clients.grants') }}</label>
                    <div class="flex flex-wrap gap-2">
                        <span v-for="grant in availableGrants" :key="grant" class="cursor-pointer">
                            <span
                                :class="['px-3 py-1 text-sm rounded-full border', form.grants.includes(grant) ? 'bg-blue-100 text-blue-800 border-blue-300' : 'bg-gray-50 text-gray-500 border-gray-200 hover:border-blue-200']"
                                @click="toggleArray(form.grants, grant)"
                            >{{ grant }}</span>
                        </span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">{{ $t('oauth_clients.redirect_uris') }}</label>
                    <div v-for="(uri, i) in form.redirectUris" :key="i" class="flex gap-2 mb-2">
                        <InputText v-model="form.redirectUris[i]" class="w-full" placeholder="https://ejemplo.com/callback" />
                        <Button icon="pi pi-times" class="p-button-rounded p-button-text p-button-danger" @click="form.redirectUris.splice(i, 1)" />
                    </div>
                    <Button :label="$t('oauth_clients.add_uri')" icon="pi pi-plus" class="p-button-text p-button-sm" @click="form.redirectUris.push('')" />
                </div>
                <div class="form-group">
                    <label class="form-label">{{ $t('oauth_clients.scopes') }}</label>
                    <div class="flex flex-wrap gap-2">
                        <span v-for="scope in availableScopes" :key="scope" class="cursor-pointer">
                            <span
                                :class="['px-3 py-1 text-sm rounded-full border', form.scopes.includes(scope) ? 'bg-purple-100 text-purple-800 border-purple-300' : 'bg-gray-50 text-gray-500 border-gray-200 hover:border-purple-200']"
                                @click="toggleArray(form.scopes, scope)"
                            >{{ scope }}</span>
                        </span>
                    </div>
                </div>
                <div class="form-group flex items-center">
                    <input type="checkbox" v-model="form.active" :id="'activeClient'" class="h-4 w-4 text-blue-600 rounded border-gray-300" />
                    <label :for="'activeClient'" class="ml-2 text-sm text-gray-700">{{ $t('common.active') }}</label>
                </div>
            </div>
            <template #footer>
                <Button :label="$t('common.cancel')" class="p-button-text" @click="closeModal" />
                <Button :label="$t('common.save')" @click="saveClient" :loading="saving" />
            </template>
        </Dialog>

        <Dialog v-model:visible="showDetail" :header="$t('oauth_clients.client_details')" :modal="true" :style="{ width: '520px' }" :dismissableMask="true">
            <div v-if="selectedClient" class="flex flex-col gap-3">
                <div class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-2 items-start">
                    <strong class="whitespace-nowrap">{{ $t('oauth_clients.name') }}:</strong>
                    <span class="break-words min-w-0">{{ selectedClient.name }}</span>

                    <strong class="whitespace-nowrap">{{ $t('oauth_clients.client_id') }}:</strong>
                    <div class="flex items-center gap-1 min-w-0">
                        <span class="font-mono text-sm bg-gray-100 px-2 py-1 rounded select-all cursor-pointer truncate max-w-full block" @click="copyToClipboard(selectedClient.identifier)">{{ selectedClient.identifier }}</span>
                        <i class="pi pi-copy text-gray-400 cursor-pointer hover:text-gray-600 shrink-0" @click="copyToClipboard(selectedClient.identifier)"></i>
                    </div>

                    <template v-if="selectedClient.secret">
                        <strong class="whitespace-nowrap">{{ $t('oauth_clients.secret') }}:</strong>
                        <div class="flex items-center gap-1 min-w-0">
                            <span class="font-mono text-sm bg-gray-100 px-2 py-1 rounded select-all cursor-pointer break-all min-w-0" @click="copyToClipboard(selectedClient.secret)">{{ selectedClient.secret }}</span>
                            <i class="pi pi-copy text-gray-400 cursor-pointer hover:text-gray-600 shrink-0" @click="copyToClipboard(selectedClient.secret)"></i>
                        </div>
                    </template>
                    <template v-else>
                        <strong class="whitespace-nowrap">{{ $t('oauth_clients.public') }}:</strong>
                        <span>{{ $t('common.yes') }}</span>
                    </template>

                    <strong class="whitespace-nowrap">{{ $t('oauth_clients.grants') }}:</strong>
                    <div class="flex flex-wrap gap-1">
                        <span v-for="g in selectedClient.grants" :key="g" class="px-2 py-0.5 text-xs bg-blue-100 text-blue-800 rounded-full">{{ g }}</span>
                        <span v-if="!selectedClient.grants?.length" class="text-gray-400">-</span>
                    </div>

                    <strong class="whitespace-nowrap">{{ $t('oauth_clients.redirect_uris') }}:</strong>
                    <div v-if="selectedClient.redirectUris?.length" class="flex flex-col gap-1 min-w-0">
                        <span v-for="uri in selectedClient.redirectUris" :key="uri" class="text-sm text-gray-600 break-all bg-gray-50 px-2 py-1 rounded">{{ uri }}</span>
                    </div>
                    <span v-else class="text-gray-400">-</span>

                    <strong class="whitespace-nowrap">{{ $t('oauth_clients.scopes') }}:</strong>
                    <div class="flex flex-wrap gap-1">
                        <span v-for="s in selectedClient.scopes" :key="s" class="px-2 py-0.5 text-xs bg-purple-100 text-purple-800 rounded-full">{{ s }}</span>
                        <span v-if="!selectedClient.scopes?.length" class="text-gray-400">-</span>
                    </div>

                    <strong class="whitespace-nowrap">{{ $t('common.status') }}:</strong>
                    <span :class="['px-2 py-0.5 text-xs rounded-full w-fit', selectedClient.active ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800']">
                        {{ selectedClient.active ? $t('common.active') : $t('common.inactive') }}
                    </span>
                </div>
            </div>
            <template #footer>
                <Button :label="$t('common.close')" class="p-button-text" @click="showDetail = false" />
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
import { useToast } from 'primevue/usetoast';

const { t } = useI18n();
const toast = useToast();

const clients = ref([]);
const loading = ref(false);
const saving = ref(false);
const showModal = ref(false);
const showDetail = ref(false);
const editingId = ref(null);
const selectedClient = ref(null);

const availableGrants = ['client_credentials', 'authorization_code', 'password', 'refresh_token', 'implicit'];
const availableScopes = ['api', 'profile', 'accounts', 'recharges', 'transfers', 'invoices', 'authorized'];

const form = ref({
    name: '',
    identifier: '',
    secret: '',
    public: false,
    allowPlainTextPkce: false,
    grants: ['client_credentials'],
    redirectUris: [],
    scopes: ['api'],
    active: true,
});

const toggleArray = (arr, value) => {
    const idx = arr.indexOf(value);
    if (idx === -1) arr.push(value);
    else arr.splice(idx, 1);
};

const onPublicChange = () => {
    if (form.value.public) form.value.secret = '';
};

const loadClients = async () => {
    loading.value = true;
    try {
        const response = await fetch('/oauth-clients/list');
        const data = await response.json();
        if (data.success) clients.value = data.data;
    } catch (error) {
        toast.add({ severity: 'error', summary: t('common.error'), detail: error.message });
    } finally {
        loading.value = false;
    }
};

const openCreateModal = () => {
    editingId.value = null;
    form.value = {
        name: '',
        identifier: '',
        secret: '',
        public: false,
        allowPlainTextPkce: false,
        grants: ['client_credentials'],
        redirectUris: [],
        scopes: ['api'],
        active: true,
    };
    showModal.value = true;
};

const copyToClipboard = (text) => {
    navigator.clipboard.writeText(text).then(() => {
        toast.add({ severity: 'info', summary: t('common.success'), detail: t('oauth_clients.copied') });
    });
};

const editClient = (client) => {
    editingId.value = client.identifier;
    form.value = {
        name: client.name,
        identifier: client.identifier,
        secret: client.secret || '',
        public: !client.secret,
        allowPlainTextPkce: client.allowPlainTextPkce,
        grants: [...client.grants],
        redirectUris: [...(client.redirectUris || [])],
        scopes: [...(client.scopes || [])],
        active: client.active,
    };
    showModal.value = true;
};

const viewClient = (client) => {
    selectedClient.value = client;
    showDetail.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingId.value = null;
};

const saveClient = async () => {
    if (!form.value.name) {
        toast.add({ severity: 'warn', summary: t('common.warning'), detail: t('oauth_clients.name_required') });
        return;
    }
    saving.value = true;
    try {
        const url = editingId.value ? `/oauth-clients/${editingId.value}` : '/oauth-clients';
        const method = editingId.value ? 'PUT' : 'POST';

        const payload = {
            name: form.value.name,
            public: editingId.value ? undefined : form.value.public,
            allowPlainTextPkce: form.value.allowPlainTextPkce,
            grants: form.value.grants,
            redirectUris: form.value.redirectUris.filter(Boolean),
            scopes: form.value.scopes,
            ...(editingId.value ? {} : { active: form.value.active }),
        };
        if (editingId.value && form.value.active !== undefined) {
            payload.active = form.value.active;
        }

        const response = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await response.json();
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: data.message });
            closeModal();
            loadClients();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: t('common.error'), detail: error.message });
    } finally {
        saving.value = false;
    }
};

const deleteClient = async (client) => {
    if (!confirm(`${t('oauth_clients.confirm_delete')} "${client.name}"?`)) return;
    try {
        const response = await fetch(`/oauth-clients/${client.identifier}`, { method: 'DELETE' });
        const data = await response.json();
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: data.message });
            loadClients();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: t('common.error'), detail: error.message });
    }
};

const toggleStatus = async (client) => {
    const newActive = !client.active;
    if (!confirm(`${newActive ? t('oauth_clients.confirm_activate') : t('oauth_clients.confirm_deactivate')} "${client.name}"?`)) return;
    try {
        const response = await fetch(`/oauth-clients/${client.identifier}/status`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ active: newActive }),
        });
        const data = await response.json();
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: data.message });
            loadClients();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.message });
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: t('common.error'), detail: error.message });
    }
};

onMounted(() => {
    loadClients();
});
</script>
