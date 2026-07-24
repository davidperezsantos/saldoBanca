<script setup>
import { ref } from 'vue';
import { login } from '../api/auth';
import { apiClient } from '../api/client';

const emit = defineEmits(['logged-in']);

const mode = ref('login');

const username = ref('');
const password = ref('');
const error = ref('');
const loading = ref(false);

const resetUsername = ref('');
const resetLoading = ref(false);
const resetMessage = ref('');
const resetError = ref('');

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

function showReset() {
    mode.value = 'reset';
    resetMessage.value = '';
    resetError.value = '';
}

function showLogin() {
    mode.value = 'login';
}

async function requestReset() {
    resetError.value = '';
    resetMessage.value = '';
    resetLoading.value = true;
    try {
        await apiClient.post('/password-reset/request', { username: resetUsername.value });
        resetMessage.value = 'Si el usuario existe, te enviamos un enlace por WhatsApp para restablecer tu contraseña.';
    } catch (e) {
        resetError.value = e.response?.data?.message || 'No se pudo enviar el enlace';
    } finally {
        resetLoading.value = false;
    }
}
</script>

<template>
    <div class="card">
        <div class="logo">
            <div class="icon">G</div>
            <h1>SaldoGrin</h1>
        </div>

        <form v-if="mode === 'login'" @submit.prevent="handleSubmit">
            <label>
                Usuario
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
            <p class="link-row">
                <a href="#" class="link" @click.prevent="showReset">¿Olvidaste tu contraseña?</a>
            </p>
        </form>

        <form v-else @submit.prevent="requestReset">
            <p class="reset-desc">Ingresá tu usuario y te enviamos un enlace por WhatsApp para restablecer tu contraseña.</p>
            <label>
                Usuario
                <input v-model="resetUsername" type="text" required autocomplete="username" />
            </label>
            <p v-if="resetError" class="error">{{ resetError }}</p>
            <p v-if="resetMessage" class="success">{{ resetMessage }}</p>
            <button type="submit" :disabled="resetLoading">
                {{ resetLoading ? 'Enviando...' : 'Enviar enlace' }}
            </button>
            <p class="link-row">
                <a href="#" class="link" @click.prevent="showLogin">← Volver</a>
            </p>
        </form>

        <p class="footer">Sistema de Gestión de Saldo v2.0</p>
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

.logo {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    margin-bottom: 32px;
}

.logo .icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #34d399 0%, #14b8a6 100%);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
    font-weight: bold;
    box-shadow: 0 8px 16px rgba(52, 211, 153, 0.3);
}

.logo h1 {
    font-size: 28px;
    font-weight: 700;
    color: #0f172a;
}

form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.success {
    color: #0f9d58;
    font-size: 0.85rem;
    margin: 0;
}

.reset-desc {
    margin: 0;
    font-size: 0.85rem;
    color: #666;
}

.link-row {
    margin: 0;
    text-align: center;
}

.link {
    color: var(--primary-dark);
    font-size: 0.85rem;
    font-weight: 600;
    text-decoration: none;
}

.link:hover {
    text-decoration: underline;
}

.footer {
    margin: 0.25rem 0 0;
    text-align: center;
    font-size: 0.72rem;
    color: #aaa;
}
</style>
