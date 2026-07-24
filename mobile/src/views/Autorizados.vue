<script setup>
import { ref, onMounted, reactive } from 'vue';
import { apiClient } from '../api/client';

const loading = ref(true);
const error = ref('');
const authorized = ref([]);
const editingId = ref(null);
const forms = reactive({});
const saving = ref(false);
const saveError = ref('');

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await apiClient.get('/authorized');
        authorized.value = data.data;
    } catch (e) {
        error.value = e.response?.data?.message || 'No se pudieron cargar los autorizados';
    } finally {
        loading.value = false;
    }
}

function startEdit(item) {
    editingId.value = item.id;
    saveError.value = '';
    forms[item.id] = {
        userName: item.userName,
        userEmail: item.userEmail,
        documentType: item.documentType,
        documentNumber: item.documentNumber,
        maxAmount: item.maxAmount ?? '',
        dailyLimit: item.dailyLimit ?? '',
        monthlyLimit: item.monthlyLimit ?? '',
    };
}

function cancelEdit() {
    editingId.value = null;
}

async function saveEdit(id) {
    saveError.value = '';
    saving.value = true;
    try {
        await apiClient.put(`/authorized/${id}`, forms[id]);
        editingId.value = null;
        await load();
    } catch (e) {
        saveError.value = e.response?.data?.message || 'No se pudo guardar';
    } finally {
        saving.value = false;
    }
}

async function toggleStatus(item) {
    saveError.value = '';
    saving.value = true;
    try {
        await apiClient.put(`/authorized/${item.id}/status`, {
            status: item.status === 'active' ? 'inactive' : 'active',
        });
        await load();
    } catch (e) {
        saveError.value = e.response?.data?.message || 'No se pudo cambiar el estado';
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>

<template>
  <div class="card">
    <h1>Usuarios autorizados</h1>
    <p class="hint">Personas que pueden operar sobre tu cuenta.</p>

    <p v-if="loading">Cargando...</p>
    <p v-else-if="error" class="error">{{ error }}</p>
    <p v-else-if="!authorized.length" class="empty">Todavía no autorizaste a nadie.</p>

    <ul v-else class="list">
      <li v-for="item in authorized" :key="item.id" class="item">
        <div class="item-row">
          <div>
            <p class="item-title">{{ item.userName }}</p>
            <p class="item-sub">
              Máx. por operación {{ item.maxAmount ?? '—' }} · Diario {{ item.dailyLimit ?? '—' }} · Mensual {{ item.monthlyLimit ?? '—' }}
            </p>
          </div>
          <span class="badge" :class="item.status">{{ item.status === 'active' ? 'Activo' : 'Inactivo' }}</span>
        </div>

        <div v-if="editingId !== item.id" class="actions">
          <button class="secondary" @click="startEdit(item)">Editar</button>
          <button class="secondary" :disabled="saving" @click="toggleStatus(item)">
            {{ item.status === 'active' ? 'Desactivar' : 'Activar' }}
          </button>
        </div>

        <form v-else class="edit-form" @submit.prevent="saveEdit(item.id)">
          <label>
            Nombre
            <input v-model="forms[item.id].userName" type="text" required />
          </label>
          <label>
            Email
            <input v-model="forms[item.id].userEmail" type="email" required />
          </label>
          <div class="row">
            <label>
              Máx. por operación
              <input v-model="forms[item.id].maxAmount" type="number" step="0.01" />
            </label>
            <label>
              Límite diario
              <input v-model="forms[item.id].dailyLimit" type="number" step="0.01" />
            </label>
          </div>
          <label>
            Límite mensual
            <input v-model="forms[item.id].monthlyLimit" type="number" step="0.01" />
          </label>
          <p v-if="saveError" class="error">{{ saveError }}</p>
          <div class="actions">
            <button type="button" class="secondary" @click="cancelEdit">Cancelar</button>
            <button type="submit" :disabled="saving">{{ saving ? 'Guardando...' : 'Guardar' }}</button>
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
.hint {
    margin: 0 0 0.5rem;
    font-size: 0.82rem;
    color: #777;
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
    margin: 0;
    font-weight: 600;
}
.item-sub {
    margin: 0.15rem 0 0;
    font-size: 0.75rem;
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
    gap: 0.5rem;
    margin-top: 0.6rem;
}
.actions button,
.secondary {
    flex: 1;
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
.row {
    display: flex;
    gap: 0.6rem;
}
.row label {
    flex: 1;
}
</style>
