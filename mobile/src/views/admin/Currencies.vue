<script setup>
import { ref, onMounted, reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import { hasScope } from '../../api/permissions';
import { listCurrencies, createCurrency, updateCurrency, toggleCurrencyStatus } from '../../api/adminCurrencies';

const { t } = useI18n();

const loading = ref(true);
const error = ref('');
const currencies = ref([]);

const canCreate = ref(false);
const canUpdate = ref(false);
const canChangeStatus = ref(false);

const editingId = ref(null);
const forms = reactive({});
const saving = ref(false);
const saveError = ref('');

const showCreateForm = ref(false);
const createSaving = ref(false);
const createError = ref('');
const createForm = ref(emptyForm());

function emptyForm() {
    return { code: '', name: '', symbol: '' };
}

function toggleCreateForm() {
    showCreateForm.value = !showCreateForm.value;
    createError.value = '';
    if (!showCreateForm.value) {
        createForm.value = emptyForm();
    }
}

async function submitCreate() {
    createError.value = '';
    createSaving.value = true;
    try {
        await createCurrency(createForm.value);
        showCreateForm.value = false;
        createForm.value = emptyForm();
        await load();
    } catch (e) {
        createError.value = e.response?.data?.message || t('admin.currencies.saveError');
    } finally {
        createSaving.value = false;
    }
}

async function load() {
    loading.value = true;
    error.value = '';
    try {
        currencies.value = await listCurrencies();
    } catch (e) {
        error.value = e.response?.data?.message || t('admin.currencies.error');
    } finally {
        loading.value = false;
    }
}

function startEdit(item) {
    editingId.value = item.id;
    saveError.value = '';
    forms[item.id] = { name: item.name, symbol: item.symbol ?? '' };
}

function cancelEdit() {
    editingId.value = null;
}

async function saveEdit(id) {
    saveError.value = '';
    saving.value = true;
    try {
        await updateCurrency(id, forms[id]);
        editingId.value = null;
        await load();
    } catch (e) {
        saveError.value = e.response?.data?.message || t('admin.currencies.saveError');
    } finally {
        saving.value = false;
    }
}

async function toggleStatus(item) {
    saveError.value = '';
    saving.value = true;
    try {
        await toggleCurrencyStatus(item.id, !item.isActive);
        await load();
    } catch (e) {
        saveError.value = e.response?.data?.message || t('admin.currencies.statusError');
    } finally {
        saving.value = false;
    }
}

onMounted(async () => {
    canCreate.value = await hasScope('currencies_admin.create');
    canUpdate.value = await hasScope('currencies_admin.update');
    canChangeStatus.value = await hasScope('currencies_admin.status');
    await load();
});
</script>

<template>
  <div class="card">
    <div class="header-row">
      <h1>{{ t('admin.currencies.title') }}</h1>
      <button v-if="canCreate" class="link-btn" @click="toggleCreateForm">
        {{ showCreateForm ? t('common.cancel') : t('admin.currencies.newBtn') }}
      </button>
    </div>
    <p class="hint">{{ t('admin.currencies.hint') }}</p>

    <form v-if="showCreateForm" class="edit-form create-form" @submit.prevent="submitCreate">
      <label>
        {{ t('admin.currencies.codeLabel') }}
        <input v-model="createForm.code" type="text" maxlength="3" required />
      </label>
      <label>
        {{ t('admin.currencies.nameLabel') }}
        <input v-model="createForm.name" type="text" required />
      </label>
      <label>
        {{ t('admin.currencies.symbolLabel') }}
        <input v-model="createForm.symbol" type="text" maxlength="4" />
      </label>
      <p v-if="createError" class="error">{{ createError }}</p>
      <button type="submit" :disabled="createSaving">
        {{ createSaving ? t('common.saving') : t('common.save') }}
      </button>
    </form>

    <p v-if="loading">{{ t('common.loading') }}</p>
    <p v-else-if="error" class="error">{{ error }}</p>
    <p v-else-if="!currencies.length" class="empty">{{ t('admin.currencies.empty') }}</p>

    <ul v-else class="list">
      <li v-for="item in currencies" :key="item.id" class="item">
        <div class="item-row">
          <p class="item-title">{{ item.code }} — {{ item.name }}</p>
          <span class="badge" :class="{ active: item.isActive }">
            {{ item.isActive ? t('common.active') : t('common.inactive') }}
          </span>
        </div>
        <p v-if="item.symbol" class="item-phone">{{ item.symbol }}</p>

        <div v-if="editingId !== item.id" class="actions">
          <button v-if="canUpdate" class="secondary" @click="startEdit(item)">{{ t('common.edit') }}</button>
          <button v-if="canChangeStatus" class="secondary" :disabled="saving" @click="toggleStatus(item)">
            {{ item.isActive ? t('admin.currencies.deactivate') : t('admin.currencies.activate') }}
          </button>
        </div>

        <form v-else class="edit-form" @submit.prevent="saveEdit(item.id)">
          <label>
            {{ t('admin.currencies.nameLabel') }}
            <input v-model="forms[item.id].name" type="text" required />
          </label>
          <label>
            {{ t('admin.currencies.symbolLabel') }}
            <input v-model="forms[item.id].symbol" type="text" maxlength="4" />
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
</style>
