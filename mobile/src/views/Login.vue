<script setup>
import { ref } from 'vue';
import { login } from '../api/auth';

const emit = defineEmits(['logged-in']);

const username = ref('');
const password = ref('');
const error = ref('');
const loading = ref(false);

async function handleSubmit() {
    error.value = '';
    loading.value = true;
    try {
        await login(username.value, password.value);
        emit('logged-in');
    } catch (e) {
        error.value = e.response?.data?.message || 'No se pudo iniciar sesión';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
  <form class="card" @submit.prevent="handleSubmit">
    <h1>SaldoBanca</h1>
    <label>
      Usuario o email
      <input v-model="username" type="text" required autocomplete="username" />
    </label>
    <label>
      Contraseña
      <input v-model="password" type="password" required autocomplete="current-password" />
    </label>
    <p v-if="error" class="error">{{ error }}</p>
    <button type="submit" :disabled="loading">
      {{ loading ? 'Ingresando...' : 'Ingresar' }}
    </button>
  </form>
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
    gap: 1rem;
}
h1 {
    margin: 0 0 0.5rem;
    font-size: 1.4rem;
    font-weight: 700;
    text-align: center;
    color: var(--primary-dark);
}
label {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    font-size: 0.9rem;
    color: #333;
}
input {
    padding: 0.6rem 0.75rem;
    border: 1px solid #d0d3d8;
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 150ms;
}
input:focus {
    outline: none;
    border-color: var(--primary-dark);
}
button {
    padding: 0.7rem;
    border: none;
    border-radius: 8px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
}
button:disabled {
    opacity: 0.6;
    cursor: default;
}
.error {
    color: #c0392b;
    font-size: 0.85rem;
    margin: 0;
}
</style>
