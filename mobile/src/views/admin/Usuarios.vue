<script setup>
import { ref, onMounted, reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import { hasScope } from '../../api/permissions';
import { listStaffUsers, createStaffUser, updateStaffUser, deleteStaffUser, toggleStaffUserStatus } from '../../api/adminUsers';

const { t } = useI18n();

const loading = ref(true);
const error = ref('');
const users = ref([]);
const roles = ref([]);

const canCreate = ref(false);
const canUpdate = ref(false);
const canDelete = ref(false);
const canToggleStatus = ref(false);

const editingId = ref(null);
const forms = reactive({});
const saving = ref(false);
const saveError = ref('');

const showCreateForm = ref(false);
const createSaving = ref(false);
const createError = ref('');
const createForm = ref(emptyCreateForm());

function emptyCreateForm() {
    return { email: '', name: '', password: '', roleIds: [], isActive: true };
}

function toggleCreateForm() {
    showCreateForm.value = !showCreateForm.value;
    createError.value = '';
    if (!showCreateForm.value) {
        createForm.value = emptyCreateForm();
    }
}

async function submitCreate() {
    createError.value = '';
    createSaving.value = true;
    try {
        await createStaffUser(createForm.value);
        showCreateForm.value = false;
        createForm.value = emptyCreateForm();
        await load();
    } catch (e) {
        createError.value = e.response?.data?.message || t('admin.users.saveError');
    } finally {
        createSaving.value = false;
    }
}

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const result = await listStaffUsers();
        users.value = result.users;
        roles.value = result.roles;
    } catch (e) {
        error.value = e.response?.data?.message || t('admin.users.error');
    } finally {
        loading.value = false;
    }
}

function startEdit(item) {
    editingId.value = item.id;
    saveError.value = '';
    forms[item.id] = {
        email: item.email,
        name: item.name ?? '',
        password: '',
        roleIds: item.roles.map((r) => r.id),
        isActive: item.isActive,
    };
}

function cancelEdit() {
    editingId.value = null;
}

async function saveEdit(id) {
    saveError.value = '';
    saving.value = true;
    try {
        const payload = { ...forms[id] };
        if (!payload.password) {
            delete payload.password;
        }
        await updateStaffUser(id, payload);
        editingId.value = null;
        await load();
    } catch (e) {
        saveError.value = e.response?.data?.message || t('admin.users.saveError');
    } finally {
        saving.value = false;
    }
}

async function toggleStatus(item) {
    saveError.value = '';
    saving.value = true;
    try {
        await toggleStaffUserStatus(item.id);
        await load();
    } catch (e) {
        saveError.value = e.response?.data?.message || t('admin.users.statusError');
    } finally {
        saving.value = false;
    }
}

async function removeUser(item) {
    saveError.value = '';
    saving.value = true;
    try {
        await deleteStaffUser(item.id);
        await load();
    } catch (e) {
        saveError.value = e.response?.data?.message || t('admin.users.deleteError');
    } finally {
        saving.value = false;
    }
}

function roleLabels(item) {
    return item.roles.map((r) => r.label).join(', ') || t('admin.users.noRole');
}

onMounted(async () => {
    canCreate.value = await hasScope('users.create');
    canUpdate.value = await hasScope('users.update');
    canDelete.value = await hasScope('users.delete');
    canToggleStatus.value = await hasScope('users.status');
    await load();
});
</script>

<template>
  <div class="card">
    <div class="header-row">
      <h1>{{ t('admin.users.title') }}</h1>
      <button v-if="canCreate" class="link-btn" @click="toggleCreateForm">
        {{ showCreateForm ? t('common.cancel') : t('admin.users.newBtn') }}
      </button>
    </div>
    <p class="hint">{{ t('admin.users.hint') }}</p>

    <form v-if="showCreateForm" class="edit-form create-form" @submit.prevent="submitCreate">
      <label>
        {{ t('admin.users.emailLabel') }}
        <input v-model="createForm.email" type="email" required />
      </label>
      <label>
        {{ t('admin.users.nameLabel') }}
        <input v-model="createForm.name" type="text" />
      </label>
      <label>
        {{ t('admin.users.passwordLabel') }}
        <input v-model="createForm.password" type="password" required />
      </label>
      <fieldset class="roles-fieldset">
        <legend>{{ t('admin.users.rolesLabel') }}</legend>
        <label v-for="role in roles" :key="role.id" class="role-checkbox">
          <input type="checkbox" :value="role.id" v-model="createForm.roleIds" />
          {{ role.label }}
        </label>
      </fieldset>
      <p v-if="createError" class="error">{{ createError }}</p>
      <button type="submit" :disabled="createSaving">
        {{ createSaving ? t('common.saving') : t('common.save') }}
      </button>
    </form>

    <p v-if="loading">{{ t('common.loading') }}</p>
    <p v-else-if="error" class="error">{{ error }}</p>
    <p v-else-if="!users.length" class="empty">{{ t('admin.users.empty') }}</p>

    <ul v-else class="list">
      <li v-for="item in users" :key="item.id" class="item">
        <div class="item-row">
          <p class="item-title">{{ item.name || item.email }}</p>
          <span class="badge" :class="{ active: item.isActive }">
            {{ item.isActive ? t('common.active') : t('common.inactive') }}
          </span>
        </div>
        <p class="item-phone">{{ item.email }}</p>
        <p class="item-phone">{{ roleLabels(item) }}</p>

        <div v-if="editingId !== item.id" class="actions">
          <button v-if="canUpdate" class="secondary" @click="startEdit(item)">{{ t('common.edit') }}</button>
          <button v-if="canToggleStatus" class="secondary" :disabled="saving" @click="toggleStatus(item)">
            {{ item.isActive ? t('admin.users.deactivate') : t('admin.users.activate') }}
          </button>
          <button v-if="canDelete" class="secondary" :disabled="saving" @click="removeUser(item)">
            {{ t('common.delete') }}
          </button>
        </div>

        <form v-else class="edit-form" @submit.prevent="saveEdit(item.id)">
          <label>
            {{ t('admin.users.emailLabel') }}
            <input v-model="forms[item.id].email" type="email" required />
          </label>
          <label>
            {{ t('admin.users.nameLabel') }}
            <input v-model="forms[item.id].name" type="text" />
          </label>
          <label>
            {{ t('admin.users.passwordLabel') }} ({{ t('admin.users.passwordKeepHint') }})
            <input v-model="forms[item.id].password" type="password" />
          </label>
          <fieldset class="roles-fieldset">
            <legend>{{ t('admin.users.rolesLabel') }}</legend>
            <label v-for="role in roles" :key="role.id" class="role-checkbox">
              <input type="checkbox" :value="role.id" v-model="forms[item.id].roleIds" />
              {{ role.label }}
            </label>
          </fieldset>
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
.roles-fieldset {
    border: 1px solid #d0d3d8;
    border-radius: 8px;
    padding: 0.5rem 0.7rem;
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}
.roles-fieldset legend {
    font-size: 0.78rem;
    color: #777;
    padding: 0 0.3rem;
}
.role-checkbox {
    flex-direction: row !important;
    align-items: center;
    gap: 0.4rem !important;
    font-size: 0.85rem;
}
</style>
