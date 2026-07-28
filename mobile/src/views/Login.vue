<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { login } from '../api/auth';
import { apiClient, getDefaultRoleId, setDefaultRoleId } from '../api/client';

const { t } = useI18n();
const emit = defineEmits(['logged-in']);

const mode = ref('login');
const roleOptions = ref([]);

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
        const result = await login(username.value, password.value);
        if (result.requiresRoleSelection) {
            await resolveRole(result.roles);
        } else {
            emit('logged-in');
        }
    } catch (e) {
        error.value = e.response?.data?.message || t('login.error');
    } finally {
        loading.value = false;
    }
}

// Si el rol guardado como default en este dispositivo sigue entre los roles del usuario, entra
// directo sin preguntar; si no (primera vez, o se borró la preferencia), muestra el selector.
async function resolveRole(roles) {
    const defaultRoleId = await getDefaultRoleId();
    const matched = roles.find((r) => r.id === defaultRoleId);
    if (matched) {
        await completeLoginWithRole(matched.id);
    } else {
        roleOptions.value = roles;
        mode.value = 'pickRole';
    }
}

async function chooseRole(role) {
    error.value = '';
    loading.value = true;
    try {
        await setDefaultRoleId(role.id);
        await completeLoginWithRole(role.id);
    } catch (e) {
        error.value = e.response?.data?.message || t('login.error');
    } finally {
        loading.value = false;
    }
}

async function completeLoginWithRole(roleId) {
    await login(username.value, password.value, roleId);
    emit('logged-in');
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
        resetMessage.value = t('login.resetSuccess');
    } catch (e) {
        resetError.value = e.response?.data?.message || t('login.resetError');
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
                {{ t('login.username') }}
                <input v-model="username" type="text" required autocomplete="username" />
            </label>
            <label>
                {{ t('login.password') }}
                <input v-model="password" type="password" required autocomplete="current-password" />
            </label>
            <p v-if="error" class="error">{{ error }}</p>
            <button type="submit" :disabled="loading">
                {{ loading ? t('login.submitting') : t('login.submit') }}
            </button>
            <p class="link-row">
                <a href="#" class="link" @click.prevent="showReset">{{ t('login.forgotPassword') }}</a>
            </p>
        </form>

        <div v-else-if="mode === 'pickRole'" class="role-pick">
            <p class="reset-desc">{{ t('login.pickRoleDesc') }}</p>
            <button
                v-for="role in roleOptions"
                :key="role.id"
                type="button"
                class="role-option"
                :disabled="loading"
                @click="chooseRole(role)"
            >
                {{ role.label }}
            </button>
            <p v-if="error" class="error">{{ error }}</p>
        </div>

        <form v-else @submit.prevent="requestReset">
            <p class="reset-desc">{{ t('login.resetDesc') }}</p>
            <label>
                {{ t('login.username') }}
                <input v-model="resetUsername" type="text" required autocomplete="username" />
            </label>
            <p v-if="resetError" class="error">{{ resetError }}</p>
            <p v-if="resetMessage" class="success">{{ resetMessage }}</p>
            <button type="submit" :disabled="resetLoading">
                {{ resetLoading ? t('login.resetSending') : t('login.resetSend') }}
            </button>
            <p class="link-row">
                <a href="#" class="link" @click.prevent="showLogin">{{ t('login.back') }}</a>
            </p>
        </form>

        <p class="footer">{{ t('login.footer') }}</p>
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

.role-pick {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.role-option {
    padding: 0.7rem;
    border: 1px solid #d0d3d8;
    border-radius: 8px;
    background: white;
    color: var(--primary-dark);
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    text-align: center;
}

.role-option:disabled {
    opacity: 0.6;
    cursor: default;
}
</style>
