<template>
    <div class="container mx-auto px-4 py-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-200">
                {{ $t('users.title') }}
            </h1>
        </div>

        <div class="card mb-6">
            <div class="flex flex-wrap gap-4 items-center justify-between">
                <div class="flex gap-2">
                    <InputText
                        v-model="search"
                        :placeholder="$t('users.search')"
                    />
                </div>
                <Button
                    :label="$t('users.create')"
                    icon="pi pi-plus"
                    @click="createUser"
                />
            </div>
        </div>

        <div class="card">
            <DataTable
                :value="filteredUsers"
                stripedRows
                responsiveLayout="scroll"
            >
                <Column field="username" :header="$t('users.username')" />
                <Column field="email" :header="$t('users.email')" />
                <Column field="name" :header="$t('users.name')" />
                <Column field="role" :header="$t('users.role')">
                    <template #body="{ data }">
                        <span v-if="data.role" class="px-2 py-1 text-xs font-medium bg-indigo-100 text-indigo-800 rounded-full">
                            {{ data.role.label }}
                        </span>
                        <span v-else class="text-gray-400">—</span>
                    </template>
                </Column>
                <Column field="isActive" :header="$t('users.status')">
                    <template #body="{ data }">
                        <span :class="['px-2 py-1 text-xs rounded-full', data.isActive ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800']">
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
                            <Button
                                icon="pi pi-pencil"
                                class="p-button-rounded p-button-warning p-button-text"
                                @click="editUser(data)"
                            />
                            <Button
                                :icon="data.isActive ? 'pi pi-times-circle' : 'pi pi-check-circle'"
                                class="p-button-rounded p-button-text"
                                :class="data.isActive ? 'p-button-danger' : 'p-button-success'"
                                @click="toggleUser(data)"
                            />
                            <Button
                                icon="pi pi-trash"
                                class="p-button-rounded p-button-danger p-button-text"
                                @click="confirmDelete(data)"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import { useToast } from 'primevue/usetoast';

const { t } = useI18n();
const toast = useToast();

const users = ref([]);
const search = ref('');

const filteredUsers = computed(() => {
    if (!search.value) return users.value;
    const q = search.value.toLowerCase();
    return users.value.filter(u =>
        (u.username && u.username.toLowerCase().includes(q)) ||
        (u.email && u.email.toLowerCase().includes(q)) ||
        (u.name && u.name.toLowerCase().includes(q))
    );
});

const createUser = () => {
    window.location.href = '/admin/users/create';
};

const editUser = (user) => {
    window.location.href = `/admin/users/${user.id}/edit`;
};

const toggleUser = async (user) => {
    if (!confirm(`¿Cambiar estado de "${user.email}"?`)) return;

    try {
        const response = await fetch(`/admin/users/${user.id}/toggle-status`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });
        const data = await response.json();
        if (data.success) {
            toast.add({ severity: 'success', summary: 'Éxito', detail: 'Estado cambiado' });
            window.location.reload();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.error || 'Error' });
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: t('common.error'), detail: error.message });
    }
};

const confirmDelete = async (user) => {
    if (!confirm(`¿Eliminar el usuario "${user.email}"?`)) return;

    try {
        const response = await fetch(`/admin/users/${user.id}/delete`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });
        const data = await response.json();
        if (data.success) {
            toast.add({ severity: 'success', summary: t('common.success'), detail: 'Usuario eliminado' });
            window.location.reload();
        } else {
            toast.add({ severity: 'error', summary: t('common.error'), detail: data.error || 'Error' });
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: t('common.error'), detail: error.message });
    }
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
});
</script>
