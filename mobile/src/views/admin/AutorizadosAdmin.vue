<script setup>
import { ref, onMounted, reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import { hasScope } from '../../api/permissions';
import {
    listAuthorizedAdmin,
    createAuthorizedAdmin,
    updateAuthorizedAdmin,
    deleteAuthorizedAdmin,
    changeAuthorizedAdminStatus,
} from '../../api/adminAuthorized';
import { formatMoney } from '../../utils/currency';
import documentTypes from '../../data/documentTypes.json';

const { t } = useI18n();

const loading = ref(true);
const error = ref('');
const authorized = ref([]);

const canCreate = ref(false);
const canUpdate = ref(false);
const canDelete = ref(false);
const canChangeStatus = ref(false);

const editingId = ref(null);
const forms = reactive({});
const saving = ref(false);
const saveError = ref('');

const showCreateForm = ref(false);
const createSaving = ref(false);
const createError = ref('');
const createdCredentials = ref(null);
const createForm = ref(emptyForm());

function emptyForm() {
    return {
        accountId: '',
        userName: '',
        userEmail: '',
        userPhone: '',
        documentType: documentTypes[0].value,
        documentNumber: '',
    };
}

function toggleCreateForm() {
    showCreateForm.value = !showCreateForm.value;
    createError.value = '';
    createdCredentials.value = null;
    if (!showCreateForm.value) {
        createForm.value = emptyForm();
    }
}

async function submitCreate() {
    createError.value = '';
    createSaving.value = true;
    try {
        const created = await createAuthorizedAdmin(createForm.value);
        createdCredentials.value = { username: created.username, password: created.password };
        createForm.value = emptyForm();
        await load();
    } catch (e) {
        createError.value = e.response?.data?.message || t('admin.authorizedAdmin.saveError');
    } finally {
        createSaving.value = false;
    }
}

async function load() {
    loading.value = true;
    error.value = '';
    try {
        authorized.value = await listAuthorizedAdmin();
    } catch (e) {
        error.value = e.response?.data?.message || t('admin.authorizedAdmin.error');
    } finally {
        loading.value = false;
    }
}

function startEdit(item) {
    editingId.value = item.id;
    saveError.value = '';
    forms[item.id] = {
        accountId: item.accountId,
        userName: item.userName,
        userEmail: item.userEmail,
        userPhone: item.userPhone ?? '',
        documentType: item.documentType,
        documentNumber: item.documentNumber,
    };
}

function cancelEdit() {
    editingId.value = null;
}

async function saveEdit(id) {
    saveError.value = '';
    saving.value = true;
    try {
        await updateAuthorizedAdmin(id, forms[id]);
        editingId.value = null;
        await load();
    } catch (e) {
        saveError.value = e.response?.data?.message || t('admin.authorizedAdmin.saveError');
    } finally {
        saving.value = false;
    }
}

async function toggleStatus(item) {
    saveError.value = '';
    saving.value = true;
    try {
        await changeAuthorizedAdminStatus(item.id, item.status === 'active' ? 'inactive' : 'active');
        await load();
    } catch (e) {
        saveError.value = e.response?.data?.message || t('admin.authorizedAdmin.statusError');
    } finally {
        saving.value = false;
    }
}

async function removeAuthorized(item) {
    saveError.value = '';
    saving.value = true;
    try {
        await deleteAuthorizedAdmin(item.id);
        await load();
    } catch (e) {
        saveError.value = e.response?.data?.message || t('admin.authorizedAdmin.deleteError');
    } finally {
        saving.value = false;
    }
}

onMounted(async () => {
    canCreate.value = await hasScope('authorized_admin.create');
    canUpdate.value = await hasScope('authorized_admin.update');
    canDelete.value = await hasScope('authorized_admin.delete');
    canChangeStatus.value = await hasScope('authorized_admin.status');
    await load();
});
</script>

<template>
  <div class="card">
    <div class="header-row">
      <h1>{{ t('admin.authorizedAdmin.title') }}</h1>
      <button v-if="canCreate" class="link-btn" @click="toggleCreateForm">
        {{ showCreateForm ? t('common.cancel') : t('admin.authorizedAdmin.newBtn') }}
      </button>
    </div>
    <p class="hint">{{ t('admin.authorizedAdmin.hint') }}</p>

    <form v-if="showCreateForm" class="edit-form create-form" @submit.prevent="submitCreate">
      <label>
        {{ t('admin.authorizedAdmin.accountIdLabel') }}
        <input v-model="createForm.accountId" type="text" required :placeholder="t('admin.authorizedAdmin.accountIdHint')" />
      </label>
      <label>
        {{ t('admin.authorizedAdmin.nameLabel') }}
        <input v-model="createForm.userName" type="text" required />
      </label>
      <label>
        {{ t('admin.authorizedAdmin.emailLabel') }}
        <input v-model="createForm.userEmail" type="email" required />
      </label>
      <label>
        {{ t('admin.authorizedAdmin.phoneLabel') }}
        <input v-model="createForm.userPhone" type="text" />
      </label>
      <label>
        {{ t('admin.authorizedAdmin.docTypeLabel') }}
        <select v-model="createForm.documentType">
          <option v-for="d in documentTypes" :key="d.value" :value="d.value">{{ d.label }}</option>
        </select>
      </label>
      <label>
        {{ t('admin.authorizedAdmin.docNumberLabel') }}
        <input v-model="createForm.documentNumber" type="text" required />
      </label>
      <p v-if="createError" class="error">{{ createError }}</p>
      <button type="submit" :disabled="createSaving">
        {{ createSaving ? t('common.saving') : t('common.save') }}
      </button>
    </form>

    <div v-if="createdCredentials" class="credentials-box">
      <p>{{ t('admin.authorizedAdmin.credentialsHint') }}</p>
      <p><strong>{{ t('admin.authorizedAdmin.usernameLabel') }}:</strong> {{ createdCredentials.username }}</p>
      <p><strong>{{ t('admin.authorizedAdmin.passwordLabel') }}:</strong> {{ createdCredentials.password }}</p>
    </div>

    <p v-if="loading">{{ t('common.loading') }}</p>
    <p v-else-if="error" class="error">{{ error }}</p>
    <p v-else-if="!authorized.length" class="empty">{{ t('admin.authorizedAdmin.empty') }}</p>

    <ul v-else class="list">
      <li v-for="item in authorized" :key="item.id" class="item">
        <div class="item-row">
          <p class="item-title">{{ item.userName }}</p>
          <span class="badge" :class="item.status">{{ item.status === 'active' ? t('admin.authorizedAdmin.statusActive') : t('admin.authorizedAdmin.statusInactive') }}</span>
        </div>
        <p class="item-phone">{{ item.accountNumber }} · {{ formatMoney(item.saldoDisponible, item.moneda) }}</p>
        <p class="item-phone">{{ item.userPhone || t('admin.authorizedAdmin.noPhone') }}</p>

        <div v-if="editingId !== item.id" class="actions">
          <button v-if="canUpdate" class="secondary" @click="startEdit(item)">{{ t('common.edit') }}</button>
          <button v-if="canChangeStatus" class="secondary" :disabled="saving" @click="toggleStatus(item)">
            {{ item.status === 'active' ? t('admin.authorizedAdmin.deactivate') : t('admin.authorizedAdmin.activate') }}
          </button>
          <button v-if="canDelete" class="secondary" :disabled="saving" @click="removeAuthorized(item)">
            {{ t('common.delete') }}
          </button>
        </div>

        <form v-else class="edit-form" @submit.prevent="saveEdit(item.id)">
          <label>
            {{ t('admin.authorizedAdmin.nameLabel') }}
            <input v-model="forms[item.id].userName" type="text" required />
          </label>
          <label>
            {{ t('admin.authorizedAdmin.emailLabel') }}
            <input v-model="forms[item.id].userEmail" type="email" required />
          </label>
          <label>
            {{ t('admin.authorizedAdmin.phoneLabel') }}
            <input v-model="forms[item.id].userPhone" type="text" />
          </label>
          <p v-if="saveError" class="error">{{ saveError }}</p>
          <div class="actions">
            <button type="button" class="secondary" @click="cancelEdit">{{ t('common.cancel') }}</button>
            <button type="submit" :disabled="saving">{{ saving ? t('common.saving') : t('common.save') }}</button>
          </div>
        </form>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
h1 {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--primary-dark);
}
.header-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}
.link-btn {
    background: none;
    border: none;
    color: var(--primary-dark);
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    padding: 0;
    white-space: nowrap;
}
.hint {
    margin: 0 0 0.5rem;
    font-size: 0.82rem;
    color: #777;
}
.create-form {
    padding: 0.9rem;
    background: #f7f9fa;
    border-radius: 8px;
    margin-bottom: 0.5rem;
}
.create-form select {
    padding: 0.5rem 0.6rem;
    border: 1px solid #d0d3d8;
    border-radius: 8px;
    font-size: 0.9rem;
    background: white;
}
.create-form button[type='submit'] {
    padding: 0.6rem;
    border: none;
    border-radius: 8px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    font-weight: 600;
    cursor: pointer;
}
.create-form button:disabled {
    opacity: 0.6;
}
.credentials-box {
    padding: 0.75rem 0.9rem;
    background: #fff4e0;
    border-radius: 8px;
    font-size: 0.82rem;
    color: #7a5a00;
    margin-bottom: 0.5rem;
}
.credentials-box p {
    margin: 0.2rem 0;
}
.empty {
    color: #888;
    font-size: 0.9rem;
}
.error {
    color: #c0392b;
    font-size: 0.85rem;
}
.list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
}
.item {
    padding: 0.7rem 0;
    border-bottom: 1px solid #f0f1f3;
}
.item:last-child {
    border-bottom: none;
}
.item-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.5rem;
}
.item-title {
    flex: 1;
    margin: 0;
    min-width: 0;
    overflow-wrap: break-word;
    font-weight: 600;
}
.item-phone {
    margin: 0.2rem 0 0;
    font-size: 0.78rem;
    color: #888;
}
.badge {
    flex-shrink: 0;
    font-size: 0.72rem;
    font-weight: 600;
    padding: 0.25rem 0.55rem;
    border-radius: 999px;
    background: #fdecea;
    color: #c0392b;
}
.badge.active {
    background: #e3f9ef;
    color: #0f9d58;
}
.actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 0.6rem;
}
.actions button,
.secondary {
    flex: 1;
    min-width: 90px;
    padding: 0.5rem;
    border: 1px solid #d0d3d8;
    border-radius: 8px;
    background: white;
    color: #333;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
}
.actions button[type='submit'] {
    border: none;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
}
.actions button:disabled {
    opacity: 0.6;
}
.edit-form {
    margin-top: 0.6rem;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}
.edit-form label {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    font-size: 0.8rem;
    color: #333;
}
.edit-form input {
    padding: 0.5rem 0.6rem;
    border: 1px solid #d0d3d8;
    border-radius: 8px;
    font-size: 0.9rem;
}
</style>
