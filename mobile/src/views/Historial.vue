<script setup>
import { ref, onMounted } from 'vue';
import { apiClient } from '../api/client';
import { useAccount, loadAccount } from '../composables/account';

const { account } = useAccount();
const loading = ref(true);
const error = ref('');
const movements = ref([]);

async function load() {
    loading.value = true;
    error.value = '';
    try {
        await loadAccount();
        if (!account.value) {
            movements.value = [];
            return;
        }
        const { data } = await apiClient.get('/history', {
            params: { accountId: account.value.id, limit: 50 },
        });
        movements.value = data.data;
    } catch (e) {
        error.value = e.response?.data?.message || 'No se pudo cargar el historial';
    } finally {
        loading.value = false;
    }
}

function isCredit(amount) {
    return !String(amount).startsWith('-');
}

onMounted(load);
</script>

<template>
  <div class="card">
    <h2>Historial de movimientos</h2>
    <p v-if="loading">Cargando...</p>
    <p v-else-if="error" class="error">{{ error }}</p>
    <p v-else-if="!movements.length" class="empty">Todavía no hay movimientos.</p>
    <ul v-else class="list">
      <li v-for="m in movements" :key="m.id" class="item">
        <div>
          <p class="item-title">{{ m.description || m.movementType }}</p>
          <p class="item-sub">{{ m.createdAt }}</p>
        </div>
        <span class="amount" :class="{ credit: isCredit(m.amount), debit: !isCredit(m.amount) }">
          {{ isCredit(m.amount) ? '+' : '' }}{{ m.amount }} {{ m.currency }}
        </span>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.card {
    background: white;
    border-radius: 12px;
    padding: 1.25rem;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
}
h2 {
    margin: 0 0 0.75rem;
    font-size: 1.05rem;
}
.empty {
    color: #888;
    font-size: 0.9rem;
}
.error {
    color: #c0392b;
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
    font-size: 0.92rem;
}
.item-sub {
    margin: 0;
    font-size: 0.78rem;
    color: #888;
}
.amount {
    font-weight: 700;
    font-size: 0.92rem;
    white-space: nowrap;
}
.amount.credit {
    color: #0f9d58;
}
.amount.debit {
    color: #c0392b;
}
</style>
