<script setup>
import { ref, onMounted } from 'vue';
import { apiClient } from '../api/client';
import { logout } from '../api/auth';

const emit = defineEmits(['logged-out']);

const loading = ref(true);
const error = ref('');
const account = ref(null);
const balance = ref(null);

async function loadAccountAndBalance() {
    loading.value = true;
    error.value = '';
    try {
        const { data: accountsRes } = await apiClient.get('/accounts');
        account.value = accountsRes.data[0] ?? null;

        if (account.value) {
            const { data: balanceRes } = await apiClient.get(`/balance/${account.value.id}`);
            balance.value = balanceRes.data;
        }
    } catch (e) {
        error.value = e.response?.data?.message || 'No se pudo cargar la cuenta';
    } finally {
        loading.value = false;
    }
}

async function handleLogout() {
    await logout();
    emit('logged-out');
}

onMounted(loadAccountAndBalance);
</script>

<template>
  <div class="card">
    <h1>Mi cuenta</h1>
    <p v-if="loading">Cargando...</p>
    <p v-else-if="error" class="error">{{ error }}</p>
    <template v-else-if="account">
      <p class="label">Cuenta</p>
      <p class="value">{{ account.accountNumber }}</p>
      <p class="label">Saldo disponible</p>
      <p class="balance">{{ balance?.available ?? '0.00' }} {{ balance?.currency ?? '' }}</p>
    </template>
    <p v-else>Tu usuario no tiene una cuenta de cliente asociada a esta app.</p>
    <button @click="handleLogout">Cerrar sesión</button>
  </div>
</template>

<style scoped>
.card {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    width: 100%;
    max-width: 360px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
h1 {
    margin: 0 0 0.5rem;
    font-size: 1.4rem;
    font-weight: 700;
    text-align: center;
    color: var(--primary-dark);
}
.label {
    margin: 0.5rem 0 0;
    font-size: 0.8rem;
    color: #777;
}
.value {
    margin: 0;
    font-size: 1.1rem;
}
.balance {
    margin: 0;
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--primary-dark);
}
button {
    margin-top: 1.5rem;
    padding: 0.7rem;
    border: 1px solid #d0d3d8;
    border-radius: 8px;
    background: white;
    color: #333;
    font-size: 1rem;
    cursor: pointer;
}
.error {
    color: #c0392b;
}
</style>
