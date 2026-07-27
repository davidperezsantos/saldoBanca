<template>
    <Card>
        <template #title>
            <h1 class="text-2xl font-bold text-gray-800">{{ $t('roles.title') }}</h1>
        </template>
        <template #content>
            <div class="container mx-auto px-4 py-8">
                <div class="mb-6 flex items-center justify-between">
                    <Button v-if="common.can('administration.roles:create')" :label="$t('roles.create')" icon="pi pi-plus" @click="createRole" />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div v-for="item in rolesData" :key="item.role.id"
                        class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-shadow">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-10 h-10 bg-gradient-to-br from-indigo-400 to-purple-500 rounded-full flex items-center justify-center text-white font-semibold">
                                    {{ item.role.label.charAt(0).toUpperCase() }}
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800">{{ item.role.label }}</h3>
                                    <p class="text-xs text-gray-500 font-mono">{{ item.role.name }}</p>
                                </div>
                            </div>
                            <span v-if="item.role.isSystem"
                                class="px-2 py-1 text-xs font-medium bg-amber-100 text-amber-700 rounded-full">
                                {{ $t('common.system') }}
                            </span>
                        </div>

                        <div class="mb-4">
                            <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">{{
                                $t('roles.permissions') }} ({{ item.permCount }})</p>
                            <div class="flex flex-wrap gap-1 max-h-32 overflow-y-auto">
                                <span v-for="(perm, idx) in item.flatPermissions.slice(0, 12)" :key="idx"
                                    class="px-2 py-1 text-xs bg-emerald-50 text-emerald-700 rounded">
                                    {{ permLabels[perm] || perm }}
                                </span>
                                <span v-if="item.permCount > 12"
                                    class="px-2 py-1 text-xs bg-gray-100 text-gray-500 rounded">
                                    +{{ item.permCount - 12 }}
                                </span>
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 pt-4 border-t border-gray-100">
                            <Button v-if="common.can('administration.roles:edit')" icon="pi pi-pencil" class="p-button-rounded p-button-warning p-button-text"
                                @click="editRole(item.role)" />
                            <Button v-if="!item.role.isSystem && common.can('administration.roles:delete')" icon="pi pi-trash"
                                class="p-button-rounded p-button-danger p-button-text"
                                @click="confirmDelete(item.role)" />
                        </div>
                    </div>

                    <div v-if="rolesData.length === 0"
                        class="col-span-3 bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center text-gray-500">
                        {{ $t('common.no_data') }}
                    </div>
                </div>

                <ConfirmDialog />
                <Toast />
            </div>
        </template>
    </Card>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from 'primevue/button';
import common from '../../common/common.js';
import Card from 'primevue/card';
import ConfirmDialog from 'primevue/confirmdialog';
import Toast from 'primevue/toast';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';

const { t } = useI18n();
const confirm = useConfirm();
const toast = useToast();

const urls = document.getElementById('vue-app').dataset;
const rolesData = ref([]);
const permLabels = ref({});

const createRole = () => {
    window.location.href = urls.createPageUrl;
};

const editRole = (role) => {
    window.location.href = urls.editPageUrl.replace('__ID__', role.id);
};

const confirmDelete = (role) => {
    confirm.require({
        message: `¿Eliminar el rol "${role.label}"?`,
        header: 'Confirmar',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: () => {
            common.ajax(urls.deleteUrl.replace('__ID__', role.id), 'POST', null, (data) => {
                if (data.success) {
                    window.location.reload();
                } else {
                    toast.add({ severity: 'error', summary: t('common.error'), detail: data.error || 'Error al eliminar' });
                }
            }, (jqXHR, textStatus, errorThrown) => {
                toast.add({ severity: 'error', summary: t('common.error'), detail: jqXHR?.responseJSON?.message || errorThrown || textStatus });
            });
        }
    });
};

onMounted(() => {
    if (window.__ROLES_DATA__) {
        rolesData.value = window.__ROLES_DATA__;
    }
    if (window.__PERM_LABELS__) {
        permLabels.value = window.__PERM_LABELS__;
    }
});
</script>
