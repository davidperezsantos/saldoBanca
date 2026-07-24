<script setup>
import { ref, onMounted } from 'vue';
import { apiClient } from '../api/client';
import { useAccount, loadAccount } from '../composables/account';

const { account } = useAccount();
const loading = ref(true);
const error = ref('');
const transfers = ref([]);

const showForm = ref(false);
const destinationAccountNumber = ref('');
const amount = ref('');
const notes = ref('');
const submitting = ref(false);
const formError = ref('');
const formSuccess = ref('');

const STATUS_LABELS = {
    pending: 'Pendiente',
    processed: 'Procesada',
    cancelled: 'Cancelada',
};

async function load() {
    loading.value = true;
    error.value = '';
    try {
        await loadAccount();
        const { data } = await apiClient.get('/transfers');
        transfers.value = data.data;
    } catch (e) {
        error.value = e.response?.data?.message || 'No se pudieron cargar las transferencias';
    } finally {
        loading.value = false;
    }
}

function direction(t) {
    return t.originAccountId === account.value?.id ? 'enviada' : 'recibida';
}

async function submitTransfer() {
    formError.value = '';
    formSuccess.value = '';
    submitting.value = true;
    try {
        await apiClient.post('/transfers', {
            destinationAccountNumber: destinationAccountNumber.value,
            amount: amount.value,
            currency: account.value?.defaultCurrency ?? 'USD',
            notes: notes.value || null,
        });
        formSuccess.value = 'Transferencia creada';
        destinationAccountNumber.value = '';
        amount.value = '';
        notes.value = '';
        showForm.value = false;
        await load();
    } catch (e) {
        formError.value = e.response?.data?.message || 'No se pudo crear la transferencia';
    } finally {
        submitting.value = false;
    }
}

onMounted(load);
</script>

<template>
  <div class="stack">
    <div class="card">
      <div class="card-header">
        <h2>Transferencias</h2>
        <button class="link-btn" @click="showForm = !showForm">
          {{ showForm ? 'Cancelar' : '+ Nueva' }}
        </button>
      </div>

      <form v-if="showForm" class="form" @submit.prevent="submitTransfer">
        <label>
          Cuenta destino
          <input v-model="destinationAccountNumber" type="text" required placeholder="Número de cuenta" />
        </label>
        <label>
          Monto ({{ account?.defaultCurrency ?? '' }})
          <input v-model="amount" type="number" step="0.01" min="0.01" required />
        </label>
        <label>
          Nota (opcional)
          <input v-model="notes" type="text" />
        </label>
        <p v-if="formError" class="error">{{ formError }}</p>
        <p v-if="formSuccess" class="success">{{ formSuccess }}</p>
        <button type="submit" :disabled="submitting">
          {{ submitting ? 'Enviando...' : 'Transferir' }}
        </button>
      </form>
    </div>

    <div class="card">
      <h2>Historial</h2>
      <p v-if="loading">Cargando...</p>
      <p v-else-if="error" class="error">{{ error }}</p>
      <p v-else-if="!transfers.length" class="empty">Todavía no tenés transferencias.</p>
      <ul v-else class="list">
        <li v-for="t in transfers" :key="t.id" class="item">
          <div>
            <p class="item-title">
              {{ direction(t) === 'enviada' ? '↑' : '↓' }}
              {{ t.amount }} {{ t.currency }}
            </p>
            <p class="item-sub">
              {{ direction(t) === 'enviada' ? 'a ' + t.destAccountNumber : 'de ' + t.originAccountNumber }}
              · {{ t.createdAt }}
            </p>
          </div>
          <span class="badge" :class="t.status">{{ STATUS_LABELS[t.status] ?? t.status }}</span>
        </li>
      </ul>
    </div>
  </div>
</template>

<style scoped>
.stack {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.card {
    background: white;
    border-radius: 12px;
    padding: 1.25rem;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
}
.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
h2 {
    margin: 0 0 0.75rem;
    font-size: 1.05rem;
}
.card-header h2 {
    margin: 0;
}
.link-btn {
    background: none;
    border: none;
    color: var(--primary-dark);
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    padding: 0;
}
.form {
    margin-top: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.form label {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    font-size: 0.85rem;
    color: #333;
}
.form input {
    padding: 0.6rem 0.7rem;
    border: 1px solid #d0d3d8;
    border-radius: 8px;
    font-size: 0.95rem;
}
.form button {
    padding: 0.65rem;
    border: none;
    border-radius: 8px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    font-weight: 600;
    cursor: pointer;
}
.form button:disabled {
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
.success {
    color: #0f9d58;
    font-size: 0.85rem;
}
.list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}
.item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.6rem 0;
    border-bottom: 1px solid #f0f1f3;
}
.item:last-child {
    border-bottom: none;
}
.item-title {
    margin: 0;
    font-weight: 600;
}
.item-sub {
    margin: 0;
    font-size: 0.78rem;
    color: #888;
}
.badge {
    font-size: 0.72rem;
    font-weight: 600;
    padding: 0.25rem 0.55rem;
    border-radius: 999px;
    background: #eef2f5;
    color: #666;
    text-transform: capitalize;
}
.badge.processed {
    background: #e3f9ef;
    color: #0f9d58;
}
.badge.pending {
    background: #fff4e0;
    color: #b7791f;
}
.badge.cancelled {
    background: #fdecea;
    color: #c0392b;
}
</style>
