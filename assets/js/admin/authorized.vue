<template>
    <div class="container mx-auto px-4 py-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-200">
                {{ $t('saldo.authorized.title') }}
            </h1>
        </div>

        <div class="card mb-6">
            <div class="flex flex-wrap gap-4 items-center justify-between">
                <div class="flex gap-2">
                    <InputText
                        v-model="search"
                        :placeholder="$t('saldo.common.search')"
                        @keyup.enter="loadAuthorized"
                    />
                    <Button
                        :label="$t('saldo.common.search')"
                        @click="loadAuthorized"
                    />
                </div>
                <Button
                    :label="$t('saldo.authorized.create')"
                    icon="pi pi-plus"
                    @click="openCreateModal"
                />
            </div>
        </div>

        <div class="card">
            <DataTable
                :value="authorizedUsers"
                :loading="loading"
                stripedRows
                responsiveLayout="scroll"
            >
                <Column field="accountNumber" :header="$t('saldo.accounts.accountNumber')" />
                <Column field="userName" :header="$t('saldo.authorized.userName')" />
                <Column field="documentNumber" :header="$t('saldo.authorized.documentNumber')" />
                <Column field="dailyLimit" :header="$t('saldo.authorized.dailyLimit')" />
                <Column field="monthlyLimit" :header="$t('saldo.authorized.monthlyLimit')" />
                <Column field="status" :header="$t('saldo.recharges.status')">
                    <template #body="{ data }">
                        <span :class="getStatusBadgeClass(data.status)">
                            {{ data.status }}
                        </span>
                    </template>
                </Column>
                <Column :header="$t('saldo.common.actions')">
                    <template #body="{ data }">
                        <div class="flex gap-2">
                            <Button
                                icon="pi pi-pencil"
                                class="p-button-rounded p-button-warning p-button-text"
                                @click="editAuthorized(data)"
                            />
                            <Button
                                v-if="data.status === 'active'"
                                icon="pi pi-ban"
                                class="p-button-rounded p-button-danger p-button-text"
                                @click="suspendAuthorized(data)"
                            />
                            <Button
                                v-else
                                icon="pi pi-check"
                                class="p-button-rounded p-button-success p-button-text"
                                @click="activateAuthorized(data)"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>

        <Dialog
            v-model:visible="showModal"
            :header="modalTitle"
            :modal="true"
            :style="{ width: '500px' }"
        >
            <div class="flex flex-col gap-4">
                <div class="form-group">
                    <label class="form-label">{{ $t('saldo.accounts.accountNumber') }}</label>
                    <InputText v-model="formData.accountNumber" class="w-full" />
                </div>
                <div class="form-group">
                    <label class="form-label">{{ $t('saldo.authorized.userName') }}</label>
                    <InputText v-model="formData.userName" class="w-full" />
                </div>
                <div class="form-group">
                    <label class="form-label">{{ $t('saldo.authorized.userEmail') }}</label>
                    <InputText v-model="formData.userEmail" class="w-full" type="email" />
                </div>
                <div class="form-group">
                    <label class="form-label">{{ $t('saldo.authorized.documentNumber') }}</label>
                    <InputText v-model="formData.documentNumber" class="w-full" />
                </div>
                <div class="form-group">
                    <label class="form-label">{{ $t('saldo.authorized.maxAmount') }}</label>
                    <InputText v-model="formData.maxAmount" class="w-full" type="number" />
                </div>
                <div class="form-group">
                    <label class="form-label">{{ $t('saldo.authorized.dailyLimit') }}</label>
                    <InputText v-model="formData.dailyLimit" class="w-full" type="number" />
                </div>
                <div class="form-group">
                    <label class="form-label">{{ $t('saldo.authorized.monthlyLimit') }}</label>
                    <InputText v-model="formData.monthlyLimit" class="w-full" type="number" />
                </div>
            </div>
            <template #footer>
                <Button
                    :label="$t('saldo.common.cancel')"
                    class="p-button-text"
                    @click="closeModal"
                />
                <Button
                    :label="$t('saldo.common.save')"
                    @click="saveAuthorized"
                    :loading="saving"
                />
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

const authorizedUsers = ref([]);
const loading = ref(false);
const saving = ref(false);
const search = ref('');
const showModal = ref(false);
const modalTitle = ref('');
const editingId = ref(null);

const formData = ref({
    accountNumber: '',
    userName: '',
    userEmail: '',
    documentNumber: '',
    maxAmount: '',
    dailyLimit: '',
    monthlyLimit: '',
});

const loadAuthorized = async () => {
    loading.value = true;
    try {
        const params = new URLSearchParams();
        if (search.value) params.append('search', search.value);

        const response = await fetch(`/saldo/authorized?${params}`);
        const data = await response.json();

        if (data.success) {
            authorizedUsers.value = data.data;
        } else {
            toast.add({ severity: 'error', summary: t('saldo.common.error'), detail: data.message });
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: t('saldo.common.error'), detail: error.message });
    } finally {
        loading.value = false;
    }
};

const openCreateModal = () => {
    editingId.value = null;
    modalTitle.value = t('saldo.authorized.create');
    formData.value = {
        accountNumber: '',
        userName: '',
        userEmail: '',
        documentNumber: '',
        maxAmount: '',
        dailyLimit: '',
        monthlyLimit: '',
    };
    showModal.value = true;
};

const editAuthorized = (authorized) => {
    editingId.value = authorized.id;
    modalTitle.value = t('saldo.authorized.edit');
    formData.value = { ...authorized };
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingId.value = null;
};

const saveAuthorized = async () => {
    saving.value = true;
    try {
        const url = editingId.value ? `/saldo/authorized/${editingId.value}` : '/saldo/authorized';
        const method = editingId.value ? 'PUT' : 'POST';

        const response = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData.value),
        });

        const data = await response.json();

        if (data.success) {
            toast.add({ severity: 'success', summary: t('saldo.common.success'), detail: data.message });
            closeModal();
            loadAuthorized();
        } else {
            toast.add({ severity: 'error', summary: t('saldo.common.error'), detail: data.message });
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: t('saldo.common.error'), detail: error.message });
    } finally {
        saving.value = false;
    }
};

const suspendAuthorized = async (authorized) => {
    try {
        const response = await fetch(`/saldo/authorized/${authorized.id}/status`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ status: 'suspended' }),
        });

        const data = await response.json();

        if (data.success) {
            toast.add({ severity: 'success', summary: t('saldo.common.success'), detail: data.message });
            loadAuthorized();
        } else {
            toast.add({ severity: 'error', summary: t('saldo.common.error'), detail: data.message });
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: t('saldo.common.error'), detail: error.message });
    }
};

const activateAuthorized = async (authorized) => {
    try {
        const response = await fetch(`/saldo/authorized/${authorized.id}/status`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ status: 'active' }),
        });

        const data = await response.json();

        if (data.success) {
            toast.add({ severity: 'success', summary: t('saldo.common.success'), detail: data.message });
            loadAuthorized();
        } else {
            toast.add({ severity: 'error', summary: t('saldo.common.error'), detail: data.message });
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: t('saldo.common.error'), detail: error.message });
    }
};

const getStatusBadgeClass = (status) => {
    const classes = {
        active: 'badge badge-success',
        suspended: 'badge badge-warning',
    };
    return classes[status] || 'badge badge-info';
};

onMounted(() => {
    loadAuthorized();
});
</script>
