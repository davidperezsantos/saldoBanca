<script setup>
import { ref, onMounted } from 'vue';
import { apiClient } from '../api/client';
import { useAccount, loadAccount } from '../composables/account';
import { useUser } from '../composables/user';

const { account } = useAccount();
const { user } = useUser();
const loading = ref(true);
const error = ref('');
const balance = ref(null);

function firstName(name) {
    return name ? name.trim().split(/\s+/)[0] : '';
}

async function load() {
    loading.value = true;
    error.value = '';
    try {
        await loadAccount();
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

onMounted(load);
</script>

<template>
  <div class="stack">
    <p class="greeting">Hola, {{ firstName(user?.name) || user?.username }}</p>

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
    </div>
  </div>
</template>

<style scoped>
.stack {
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
}
.greeting {
    margin: 0 0.2rem;
    font-size: 1.1rem;
    font-weight: 600;
    color: #333;
}
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
.error {
    color: #c0392b;
}
</style>
