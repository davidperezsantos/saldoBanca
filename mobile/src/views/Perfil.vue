<script setup>
import { ref } from 'vue';
import { apiClient } from '../api/client';
import { useUser, initials } from '../composables/user';

const emit = defineEmits(['logged-out']);

const { user } = useUser();

const currentPassword = ref('');
const newPassword = ref('');
const confirmPassword = ref('');
const passwordError = ref('');
const passwordSuccess = ref('');
const submitting = ref(false);

async function changePassword() {
    passwordError.value = '';
    passwordSuccess.value = '';

    if (newPassword.value.length < 8) {
        passwordError.value = 'La contraseña nueva debe tener al menos 8 caracteres';
        return;
    }
    if (newPassword.value !== confirmPassword.value) {
        passwordError.value = 'Las contraseñas nuevas no coinciden';
        return;
    }

    submitting.value = true;
    try {
        await apiClient.put('/me/password', {
            currentPassword: currentPassword.value,
            newPassword: newPassword.value,
        });
        passwordSuccess.value = 'Contraseña actualizada';
        currentPassword.value = '';
        newPassword.value = '';
        confirmPassword.value = '';
    } catch (e) {
        passwordError.value = e.response?.data?.message || 'No se pudo cambiar la contraseña';
    } finally {
        submitting.value = false;
    }
}

function handleLogout() {
    emit('logged-out');
}
</script>

<template>
  <div class="stack">
    <div class="card profile-card">
      <div class="avatar-lg">{{ initials(user?.name) }}</div>
      <p class="name">{{ user?.name || user?.username }}</p>
      <p class="detail">@{{ user?.username }}</p>
      <p class="detail">{{ user?.email }}</p>
    </div>

    <div class="card">
      <h2>Cambiar contraseña</h2>
      <form class="form" @submit.prevent="changePassword">
        <label>
          Contraseña actual
          <input v-model="currentPassword" type="password" required autocomplete="current-password" />
        </label>
        <label>
          Contraseña nueva
          <input v-model="newPassword" type="password" required autocomplete="new-password" minlength="8" />
        </label>
        <label>
          Confirmar contraseña nueva
          <input v-model="confirmPassword" type="password" required autocomplete="new-password" minlength="8" />
        </label>
        <p v-if="passwordError" class="error">{{ passwordError }}</p>
        <p v-if="passwordSuccess" class="success">{{ passwordSuccess }}</p>
        <button type="submit" :disabled="submitting">
          {{ submitting ? 'Guardando...' : 'Guardar' }}
        </button>
      </form>
    </div>

    <button class="logout-btn" @click="handleLogout">Cerrar sesión</button>
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
.profile-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.2rem;
    padding: 1.75rem 1.25rem;
}
.avatar-lg {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}
.name {
    margin: 0;
    font-weight: 700;
    font-size: 1.05rem;
}
.detail {
    margin: 0;
    font-size: 0.85rem;
    color: #888;
}
h2 {
    margin: 0 0 0.9rem;
    font-size: 1.05rem;
}
.form {
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
.error {
    color: #c0392b;
    font-size: 0.85rem;
    margin: 0;
}
.success {
    color: #0f9d58;
    font-size: 0.85rem;
    margin: 0;
}
.logout-btn {
    padding: 0.7rem;
    border: 1px solid #f3c2c2;
    border-radius: 8px;
    background: white;
    color: #c0392b;
    font-weight: 600;
    cursor: pointer;
}
</style>
