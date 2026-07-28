<script setup>
import { ref, computed, onMounted, reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import { hasScope } from '../../api/permissions';
import {
    listOAuthClients,
    revealOAuthClientSecret,
    createOAuthClient,
    updateOAuthClient,
    deleteOAuthClient,
    toggleOAuthClientStatus,
} from '../../api/adminOAuthClients';
import availableGrants from '../../data/availableGrants.json';
import availableScopes from '../../data/availableScopes.json';

const { t } = useI18n();

const loading = ref(true);
const error = ref('');
const clients = ref([]);

const canCreate = ref(false);
const canUpdate = ref(false);
const canDelete = ref(false);
const canToggleStatus = ref(false);

const scopeGroups = computed(() => {
    const groups = {};
    for (const scope of availableScopes) {
        const [resource] = scope.split('.');
        if (!groups[resource]) groups[resource] = [];
        groups[resource].push(scope);
    }
    return groups;
});

const editingId = ref(null);
const forms = reactive({});
const saving = ref(false);
const saveError = ref('');
const revealedSecrets = reactive({});

const showCreateForm = ref(false);
const createSaving = ref(false);
const createError = ref('');
const createForm = ref(emptyForm());

function emptyForm() {
    return {
        name: '',
        public: false,
        allowPlainTextPkce: false,
        grants: ['client_credentials'],
        redirectUris: [],
        scopes: [],
        active: true,
    };
}

function toggleCreateForm() {
    showCreateForm.value = !showCreateForm.value;
    createError.value = '';
    if (!showCreateForm.value) {
        createForm.value = emptyForm();
    }
}

function toggleArrayValue(arr, value) {
    const idx = arr.indexOf(value);
    if (idx === -1) arr.push(value);
    else arr.splice(idx, 1);
}

async function submitCreate() {
    if (!createForm.value.name) {
        createError.value = t('admin.oauthClients.nameRequired');
        return;
    }
    createError.value = '';
    createSaving.value = true;
    try {
        const created = await createOAuthClient({
            ...createForm.value,
            redirectUris: createForm.value.redirectUris.filter(Boolean),
        });
        showCreateForm.value = false;
        createForm.value = emptyForm();
        await load();
        if (created?.secret) {
            revealedSecrets[created.identifier] = created.secret;
        }
    } catch (e) {
        createError.value = e.response?.data?.message || t('admin.oauthClients.saveError');
    } finally {
        createSaving.value = false;
    }
}

async function load() {
    loading.value = true;
    error.value = '';
    try {
        clients.value = await listOAuthClients();
    } catch (e) {
        error.value = e.response?.data?.message || t('admin.oauthClients.error');
    } finally {
        loading.value = false;
    }
}

function startEdit(client) {
    editingId.value = client.identifier;
    saveError.value = '';
    forms[client.identifier] = {
        name: client.name,
        allowPlainTextPkce: client.allowPlainTextPkce,
        grants: [...client.grants],
        redirectUris: [...(client.redirectUris || [])],
        scopes: [...(client.scopes || [])],
        active: client.active,
    };
}

function cancelEdit() {
    editingId.value = null;
}

async function saveEdit(identifier) {
    saveError.value = '';
    saving.value = true;
    try {
        const payload = { ...forms[identifier], redirectUris: forms[identifier].redirectUris.filter(Boolean) };
        await updateOAuthClient(identifier, payload);
        editingId.value = null;
        await load();
    } catch (e) {
        saveError.value = e.response?.data?.message || t('admin.oauthClients.saveError');
    } finally {
        saving.value = false;
    }
}

async function toggleStatus(client) {
    saveError.value = '';
    saving.value = true;
    try {
        await toggleOAuthClientStatus(client.identifier, !client.active);
        await load();
    } catch (e) {
        saveError.value = e.response?.data?.message || t('admin.oauthClients.statusError');
    } finally {
        saving.value = false;
    }
}

async function removeClient(client) {
    saveError.value = '';
    saving.value = true;
    try {
        await deleteOAuthClient(client.identifier);
        await load();
    } catch (e) {
        saveError.value = e.response?.data?.message || t('admin.oauthClients.deleteError');
    } finally {
        saving.value = false;
    }
}

async function reveal(client) {
    if (!client.secret) return;
    try {
        revealedSecrets[client.identifier] = await revealOAuthClientSecret(client.identifier);
    } catch (e) {
        saveError.value = e.response?.data?.message || t('admin.oauthClients.error');
    }
}

onMounted(async () => {
    canCreate.value = await hasScope('oauth_clients.create');
    canUpdate.value = await hasScope('oauth_clients.update');
    canDelete.value = await hasScope('oauth_clients.delete');
    canToggleStatus.value = await hasScope('oauth_clients.status');
    await load();
});
</script>

<template>
  <div class="card">
    <div class="header-row">
      <h1>{{ t('admin.oauthClients.title') }}</h1>
      <button v-if="canCreate" class="link-btn" @click="toggleCreateForm">
        {{ showCreateForm ? t('common.cancel') : t('admin.oauthClients.newBtn') }}
      </button>
    </div>
    <p class="hint">{{ t('admin.oauthClients.hint') }}</p>

    <form v-if="showCreateForm" class="edit-form create-form" @submit.prevent="submitCreate">
      <label>
        {{ t('admin.oauthClients.nameLabel') }}
        <input v-model="createForm.name" type="text" required />
      </label>
      <label class="checkbox-row">
        <input type="checkbox" v-model="createForm.public" />
        {{ t('admin.oauthClients.publicLabel') }}
      </label>
      <label class="checkbox-row">
        <input type="checkbox" v-model="createForm.allowPlainTextPkce" />
        {{ t('admin.oauthClients.pkceLabel') }}
      </label>

      <div class="field-block">
        <p class="field-label">{{ t('admin.oauthClients.grantsLabel') }}</p>
        <div class="chip-row">
          <span
            v-for="grant in availableGrants"
            :key="grant"
            class="chip"
            :class="{ active: createForm.grants.includes(grant) }"
            @click="toggleArrayValue(createForm.grants, grant)"
          >{{ grant }}</span>
        </div>
      </div>

      <div class="field-block">
        <p class="field-label">{{ t('admin.oauthClients.redirectUrisLabel') }}</p>
        <div v-for="(uri, i) in createForm.redirectUris" :key="i" class="uri-row">
          <input v-model="createForm.redirectUris[i]" type="text" placeholder="https://ejemplo.com/callback" />
          <button type="button" class="remove-btn" @click="createForm.redirectUris.splice(i, 1)">×</button>
        </div>
        <button type="button" class="link-btn" @click="createForm.redirectUris.push('')">
          {{ t('admin.oauthClients.addUri') }}
        </button>
      </div>

      <details v-for="(scopes, resource) in scopeGroups" :key="resource" class="perm-group">
        <summary>{{ resource }}</summary>
        <span
          v-for="scope in scopes"
          :key="scope"
          class="chip small"
          :class="{ active: createForm.scopes.includes(scope) }"
          @click="toggleArrayValue(createForm.scopes, scope)"
        >{{ scope }}</span>
      </details>

      <p v-if="createError" class="error">{{ createError }}</p>
      <button type="submit" :disabled="createSaving">
        {{ createSaving ? t('common.saving') : t('common.save') }}
      </button>
    </form>

    <p v-if="loading">{{ t('common.loading') }}</p>
    <p v-else-if="error" class="error">{{ error }}</p>
    <p v-else-if="!clients.length" class="empty">{{ t('admin.oauthClients.empty') }}</p>

    <ul v-else class="list">
      <li v-for="client in clients" :key="client.identifier" class="item">
        <div class="item-row">
          <p class="item-title">{{ client.name }}</p>
          <span class="badge" :class="{ active: client.active }">
            {{ client.active ? t('common.active') : t('common.inactive') }}
          </span>
        </div>
        <p class="item-phone mono">{{ client.identifier }}</p>
        <p v-if="revealedSecrets[client.identifier]" class="item-phone mono secret">
          {{ revealedSecrets[client.identifier] }}
        </p>
        <p v-else-if="client.secret" class="item-phone mono">
          {{ client.secret }}
          <button type="button" class="link-btn" @click="reveal(client)">{{ t('admin.oauthClients.reveal') }}</button>
        </p>
        <p v-else class="item-phone">{{ t('admin.oauthClients.public') }}</p>
        <div v-if="client.grants.length" class="chip-row">
          <span v-for="g in client.grants" :key="g" class="chip small static">{{ g }}</span>
        </div>

        <div v-if="editingId !== client.identifier" class="actions">
          <button v-if="canUpdate" class="secondary" @click="startEdit(client)">{{ t('common.edit') }}</button>
          <button v-if="canToggleStatus" class="secondary" :disabled="saving" @click="toggleStatus(client)">
            {{ client.active ? t('admin.oauthClients.deactivate') : t('admin.oauthClients.activate') }}
          </button>
          <button v-if="canDelete" class="secondary" :disabled="saving" @click="removeClient(client)">
            {{ t('common.delete') }}
          </button>
        </div>

        <form v-else class="edit-form" @submit.prevent="saveEdit(client.identifier)">
          <label>
            {{ t('admin.oauthClients.nameLabel') }}
            <input v-model="forms[client.identifier].name" type="text" required />
          </label>
          <label class="checkbox-row">
            <input type="checkbox" v-model="forms[client.identifier].allowPlainTextPkce" />
            {{ t('admin.oauthClients.pkceLabel') }}
          </label>

          <div class="field-block">
            <p class="field-label">{{ t('admin.oauthClients.grantsLabel') }}</p>
            <div class="chip-row">
              <span
                v-for="grant in availableGrants"
                :key="grant"
                class="chip"
                :class="{ active: forms[client.identifier].grants.includes(grant) }"
                @click="toggleArrayValue(forms[client.identifier].grants, grant)"
              >{{ grant }}</span>
            </div>
          </div>

          <div class="field-block">
            <p class="field-label">{{ t('admin.oauthClients.redirectUrisLabel') }}</p>
            <div v-for="(uri, i) in forms[client.identifier].redirectUris" :key="i" class="uri-row">
              <input v-model="forms[client.identifier].redirectUris[i]" type="text" />
              <button type="button" class="remove-btn" @click="forms[client.identifier].redirectUris.splice(i, 1)">×</button>
            </div>
            <button type="button" class="link-btn" @click="forms[client.identifier].redirectUris.push('')">
              {{ t('admin.oauthClients.addUri') }}
            </button>
          </div>

          <details v-for="(scopes, resource) in scopeGroups" :key="resource" class="perm-group">
            <summary>{{ resource }}</summary>
            <span
              v-for="scope in scopes"
              :key="scope"
              class="chip small"
              :class="{ active: forms[client.identifier].scopes.includes(scope) }"
              @click="toggleArrayValue(forms[client.identifier].scopes, scope)"
            >{{ scope }}</span>
          </details>

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
.mono {
    font-family: monospace;
    word-break: break-all;
}
.secret {
    color: #c0392b;
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
.edit-form input[type='text'] {
    padding: 0.5rem 0.6rem;
    border: 1px solid #d0d3d8;
    border-radius: 8px;
    font-size: 0.9rem;
}
.checkbox-row {
    flex-direction: row !important;
    align-items: center;
    gap: 0.4rem !important;
}
.field-block {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}
.field-label {
    margin: 0;
    font-size: 0.8rem;
    color: #333;
}
.chip-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}
.chip {
    padding: 0.3rem 0.65rem;
    border-radius: 999px;
    border: 1px solid #d0d3d8;
    background: #f7f9fa;
    color: #777;
    font-size: 0.78rem;
    cursor: pointer;
}
.chip.small {
    font-size: 0.72rem;
    padding: 0.25rem 0.55rem;
    margin: 0.15rem 0.3rem 0.15rem 0;
    display: inline-block;
}
.chip.active {
    background: #eefaf5;
    border-color: var(--primary-dark);
    color: var(--primary-dark);
    font-weight: 600;
}
.chip.static {
    cursor: default;
    background: #eefaf5;
    border-color: transparent;
    color: var(--primary-dark);
}
.uri-row {
    display: flex;
    gap: 0.4rem;
    align-items: center;
}
.uri-row input {
    flex: 1;
}
.remove-btn {
    border: none;
    background: none;
    color: #c0392b;
    font-size: 1.1rem;
    cursor: pointer;
    padding: 0 0.3rem;
}
.perm-group {
    border: 1px solid #d0d3d8;
    border-radius: 8px;
    padding: 0.4rem 0.7rem;
}
.perm-group summary {
    font-size: 0.85rem;
    font-weight: 600;
    color: #333;
    cursor: pointer;
    padding: 0.3rem 0;
    text-transform: capitalize;
}
</style>
