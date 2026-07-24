<template>
    <Card>
        <template #title>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-200">
                {{ $t('users.title') }}
            </h1>
        </template>
        <template #content>
            <div class="container mx-auto px-4 py-8">
                <div class="card mb-6">
                    <div class="flex flex-wrap gap-4 items-center justify-between">
                        <div class="flex gap-2">
                            <InputText v-model="search" :placeholder="$t('users.search')" />
                        </div>
                        <Button v-if="common.can('administration.users:create')" :label="$t('users.create')" icon="pi pi-plus" @click="openCreateModal" />
                    </div>
                </div>

                <DataTable :value="filteredUsers" size="small" :paginator="true" :rows="10" responsiveLayout="scroll">
                    <Column field="username" :header="$t('users.username')" />
                    <Column field="email" :header="$t('users.email')" />
                    <Column field="name" :header="$t('users.name')" />
                    <Column field="role" :header="$t('users.role')">
                        <template #body="{ data }">
                            <span v-if="data.role"
                                class="px-2 py-1 text-xs font-medium bg-indigo-100 text-indigo-800 rounded-full">
                                {{ data.role.label }}
                            </span>
                            <span v-else class="text-gray-400">—</span>
                        </template>
                    </Column>
                    <Column field="isActive" :header="$t('users.status')">
                        <template #body="{ data }">
                            <span
                                :class="['px-2 py-1 text-xs rounded-full', data.isActive ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800']">
                                {{ $t(data.isActive ? 'common.active' : 'common.inactive') }}
                            </span>
                        </template>
                    </Column>
                    <Column field="lastLoginAt" :header="$t('users.lastLogin')">
                        <template #body="{ data }">
                            {{ data.lastLoginAt ? formatDate(data.lastLoginAt) : $t('users.never') }}
                        </template>
                    </Column>
                    <Column :header="$t('common.actions')">
                        <template #body="{ data }">
                            <div class="flex gap-2">
                                <Button v-if="common.can('administration.users:edit')" icon="pi pi-pencil" class="p-button-rounded p-button-warning p-button-text"
                                    @click="editUser(data)" />
                                <Button v-if="common.can('administration.users:status')" :icon="data.isActive ? 'pi pi-times-circle' : 'pi pi-check-circle'"
                                    class="p-button-rounded p-button-text"
                                    :class="data.isActive ? 'p-button-danger' : 'p-button-success'"
                                    @click="toggleUser(data)" />
                                <Button v-if="common.can('administration.users:delete')" icon="pi pi-trash" class="p-button-rounded p-button-danger p-button-text"
                                    @click="confirmDelete(data)" />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </div>

            <Dialog v-model:visible="showModal" :header="editingId ? $t('common.edit') : $t('users.create')"
                :modal="true" :style="{ width: '550px' }">
                <div class="flex flex-col gap-4">
                    <div v-if="editingId && form.username" class="form-group">
                        <label class="form-label">{{ $t('users.username') }}</label>
                        <InputText :value="form.username" class="w-full font-mono" readonly disabled />
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ $t('users.email') }} *</label>
                        <InputText v-model="form.email" class="w-full" type="email" />
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ $t('users.name') }}</label>
                        <InputText v-model="form.name" class="w-full" />
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ $t('common.password') }} {{ editingId ?
                            $t('users.passwordKeepHint') : '*'
                            }}</label>
                        <InputText v-model="form.password" class="w-full" type="password" />
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ $t('users.role') }}</label>
                        <Select v-model="form.roleId" :options="roles" optionLabel="label" optionValue="id"
                            class="w-full" showClear :placeholder="$t('users.noRole')" />
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" v-model="form.isActive" id="userActive"
                            class="h-4 w-4 text-blue-600 rounded border-gray-300" />
                        <label for="userActive" class="ml-2 text-sm text-gray-700">{{ $t('users.status') }}: {{
                            $t('common.active')
                            }}</label>
                    </div>
                </div>
                <template #footer>
                    <Button :label="$t('common.cancel')" class="p-button-text" @click="closeModal" />
                    <Button :label="$t('common.save')" @click="saveUser" :loading="saving" />
                </template>
            </Dialog>
        </template>
    </Card>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Dialog from 'primevue/dialog';
import Card from 'primevue/card';
import { useToast } from 'primevue/usetoast';
import common from '../../common/common.js';

const { t } = useI18n();
const toast = useToast();

const urls = document.getElementById('vue-app').dataset;
const onAjaxError = (jqXHR, textStatus, errorThrown) => {
    toast.add({ severity: 'error', summary: t('common.error'), detail: jqXHR?.responseJSON?.message || errorThrown || textStatus });
};

const users = ref([]);
const roles = ref([]);
const search = ref('');
const saving = ref(false);
const showModal = ref(false);
const editingId = ref(null);

const emptyForm = () => ({ username: '', email: '', name: '', password: '', roleId: null, isActive: true });
const form = ref(emptyForm());

const filteredUsers = computed(() => {
    if (!search.value) return users.value;
    const q = search.value.toLowerCase();
    return users.value.filter(u =>
        (u.username && u.username.toLowerCase().includes(q)) ||
        (u.email && u.email.toLowerCase().includes(q)) ||
        (u.name && u.name.toLowerCase().includes(q))
    );
});

const openCreateModal = () => {
    editingId.value = null;
    form.value = emptyForm();
    showModal.value = true;
};

const editUser = (user) => {
    editingId.value = user.id;
    form.value = {
        username: user.username || '',
        email: user.email || '',
        name: user.name || '',
        password: '',
        roleId: user.role ? user.role.id : null,
        isActive: user.isActive,
    };
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    editingId.value = null;
};

const saveUser = () => {
    if (!form.value.email) {
        toast.add({ severity: 'warn', summary: t('common.warning'), detail: t('users.emailRequired') });
        return;
    }
    if (!editingId.value && !form.value.password) {
        toast.add({ severity: 'warn', summary: t('common.warning'), detail: t('users.passwordRequired') });
        return;
    }

    saving.value = true;
    const body = {
        email: form.value.email,
        name: form.value.name,
        role_id: form.value.roleId || null,
        is_active: form.value.isActive,
    };
    if (form.value.password) body.password = form.value.password;

    const url = editingId.value ? urls.updateUrl.replace('__ID__', editingId.value) : urls.storeUrl;
    common.ajax(url, 'POST', JSON.stringify(body), (data) => {
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: t('common.success') });
            closeModal();
            window.location.reload();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.error || 'Error' });
        }
        saving.value = false;
    }, (...args) => {
        onAjaxError(...args);
        saving.value = false;
    });
};

const toggleUser = (user) => {
    if (!confirm(`¿Cambiar estado de "${user.email}"?`)) return;

    common.ajax(urls.toggleStatusUrl.replace('__ID__', user.id), 'POST', null, (data) => {
        if (data.success) {
            toast.add({ severity: 'success', summary: 'Éxito', detail: 'Estado cambiado' });
            window.location.reload();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.error || 'Error' });
        }
    }, onAjaxError);
};

const confirmDelete = (user) => {
    if (!confirm(`¿Eliminar el usuario "${user.email}"?`)) return;

    common.ajax(urls.deleteUrl.replace('__ID__', user.id), 'POST', null, (data) => {
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: 'Usuario eliminado' });
            window.location.reload();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.error || 'Error' });
        }
    }, onAjaxError);
};

const formatDate = (dateStr) => {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleDateString('es-ES', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
};

onMounted(() => {
    if (window.__USERS__) {
        users.value = window.__USERS__;
    }
    if (window.__ROLES__) {
        roles.value = window.__ROLES__;
    }
});
</script>
