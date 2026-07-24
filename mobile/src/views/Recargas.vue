<script setup>
import { ref, onMounted } from 'vue';
import { apiClient } from '../api/client';

const loading = ref(true);
const error = ref('');
const recharges = ref([]);

const STATUS_LABELS = {
    pending: 'Pendiente',
    completed: 'Completada',
    cancelled: 'Cancelada',
    failed: 'Fallida',
};

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await apiClient.get('/recharges');
        recharges.value = data.data;
    } catch (e) {
        error.value = e.response?.data?.message || 'No se pudieron cargar las recargas';
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
  <div class="stack">
    <div class="card soon">
      <p class="soon-title">Recargar saldo</p>
      <p class="soon-text">Próximamente vas a poder recargar tu cuenta desde aquí con tarjeta.</p>
    </div>

    <div class="card">
      <h2>Mis recargas</h2>
      <p v-if="loading">Cargando...</p>
      <p v-else-if="error" class="error">{{ error }}</p>
      <p v-else-if="!recharges.length" class="empty">Todavía no tenés recargas.</p>
      <ul v-else class="list">
        <li v-for="r in recharges" :key="r.id" class="item">
          <div>
            <p class="item-title">{{ r.amount }} {{ r.currency }}</p>
            <p class="item-sub">{{ r.createdAt }}</p>
          </div>
          <span class="badge" :class="r.status">{{ STATUS_LABELS[r.status] ?? r.status }}</span>
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
.soon {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
}
.soon-title {
    margin: 0 0 0.25rem;
    font-weight: 700;
}
.soon-text {
    margin: 0;
    font-size: 0.85rem;
    opacity: 0.95;
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
.badge.completed {
    background: #e3f9ef;
    color: #0f9d58;
}
.badge.pending {
    background: #fff4e0;
    color: #b7791f;
}
.badge.failed, .badge.cancelled {
    background: #fdecea;
    color: #c0392b;
}
</style>
