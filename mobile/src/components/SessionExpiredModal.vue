<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { showRenewPrompt, expiredUsername, forceLogout, markSessionActive } from '../composables/session';
import { login } from '../api/auth';

const { t } = useI18n();
const step = ref('confirm');
const password = ref('');
const error = ref('');
const loading = ref(false);

function reset() {
    step.value = 'confirm';
    password.value = '';
    error.value = '';
    loading.value = false;
}

function confirmRenew() {
    step.value = 'password';
}

function declineRenew() {
    showRenewPrompt.value = false;
    reset();
    forceLogout.value = true;
}

async function submitRenew() {
    error.value = '';
    loading.value = true;
    try {
        await login(expiredUsername.value, password.value);
        markSessionActive();
        showRenewPrompt.value = false;
        reset();
    } catch (e) {
        error.value = t('session.renewError');
    } finally {
        loading.value = false;
    }
}
</script>

<template>
  <div v-if="showRenewPrompt" class="overlay">
    <div class="modal">
      <template v-if="step === 'confirm'">
        <p class="title">{{ t('session.expiredTitle') }}</p>
        <p class="text">{{ t('session.expiredQuestion') }}</p>
        <div class="actions">
          <button class="secondary" @click="declineRenew">{{ t('common.no') }}</button>
          <button class="primary" @click="confirmRenew">{{ t('common.yes') }}</button>
        </div>
      </template>
      <template v-else>
        <p class="title">{{ t('session.renewTitle') }}</p>
        <p class="username">@{{ expiredUsername }}</p>
        <form @submit.prevent="submitRenew">
          <input
            v-model="password"
            type="password"
            :placeholder="t('login.password')"
            autofocus
            required
            autocomplete="current-password"
          />
          <p v-if="error" class="error">{{ error }}</p>
          <div class="actions">
            <button type="button" class="secondary" @click="declineRenew">{{ t('common.cancel') }}</button>
            <button type="submit" class="primary" :disabled="loading">
              {{ loading ? t('common.sending') : t('session.renewButton') }}
            </button>
          </div>
        </form>
      </template>
    </div>
  </div>
</template>

<style scoped>
.overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.25rem;
    z-index: 50;
}
.modal {
    width: 100%;
    max-width: 340px;
    background: white;
    border-radius: 14px;
    padding: 1.5rem;
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.2);
}
.title {
    margin: 0 0 0.5rem;
    font-weight: 700;
    font-size: 1.05rem;
    color: #0f172a;
}
.text {
    margin: 0 0 1.2rem;
    font-size: 0.9rem;
    color: #555;
}
.username {
    margin: 0 0 0.9rem;
    font-size: 0.85rem;
    color: #888;
}
form input {
    width: 100%;
    padding: 0.6rem 0.7rem;
    border: 1px solid #d0d3d8;
    border-radius: 8px;
    font-size: 0.95rem;
    box-sizing: border-box;
}
.error {
    color: #c0392b;
    font-size: 0.85rem;
    margin: 0.6rem 0 0;
}
.actions {
    display: flex;
    gap: 0.6rem;
    margin-top: 1.2rem;
}
.actions button {
    flex: 1;
    padding: 0.6rem;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    border: none;
}
.primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
}
.primary:disabled {
    opacity: 0.6;
}
.secondary {
    background: white;
    border: 1px solid #d0d3d8;
    color: #333;
}
</style>
