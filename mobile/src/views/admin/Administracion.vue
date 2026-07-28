<script setup>
import { ref, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { hasScope } from '../../api/permissions';
import { roleVersion } from '../../composables/role';

const { t } = useI18n();

const canUsers = ref(false);
const canRoles = ref(false);
const canOAuth = ref(false);

async function load() {
    canUsers.value = await hasScope('users.read');
    canRoles.value = await hasScope('roles.read');
    canOAuth.value = await hasScope('oauth_clients.read');
}

onMounted(load);
watch(roleVersion, load);
</script>

<template>
  <div class="stack">
    <div class="card">
      <h1>{{ t('admin.administracion.title') }}</h1>
      <p class="hint">{{ t('admin.administracion.hint') }}</p>
      <div class="cards-grid">
        <router-link v-if="canUsers" to="/usuarios" class="dash-card">
          <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3Zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3Zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5Zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5Z" />
          </svg>
          <span>{{ t('admin.dashboard.users') }}</span>
        </router-link>
        <router-link v-if="canRoles" to="/roles" class="dash-card">
          <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <path d="M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm-3 6v11m-4-4h8" />
            <circle cx="12" cy="7" r="3" />
          </svg>
          <span>{{ t('admin.dashboard.roles') }}</span>
        </router-link>
        <router-link v-if="canOAuth" to="/oauth-clients" class="dash-card">
          <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 12a3 3 0 1 0 6 0 3 3 0 0 0-6 0Zm3-9v3m0 12v3m9-9h-3M6 12H3m14.5-6.5-2.1 2.1m-8.8 8.8-2.1 2.1m0-13 2.1 2.1m8.8 8.8 2.1 2.1" />
          </svg>
          <span>{{ t('admin.dashboard.oauthClients') }}</span>
        </router-link>
      </div>
      <p v-if="!canUsers && !canRoles && !canOAuth" class="empty">{{ t('admin.administracion.empty') }}</p>
    </div>
  </div>
</template>

<style scoped>
.stack {
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
}
.card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
}
h1 {
    margin: 0 0 0.3rem;
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--primary-dark);
}
.hint {
    margin: 0 0 1rem;
    font-size: 0.82rem;
    color: #777;
}
.empty {
    color: #888;
    font-size: 0.9rem;
}
.cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(96px, 1fr));
    gap: 0.75rem;
}
.dash-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 1rem 0.5rem;
    border-radius: 12px;
    background: #f7f9fa;
    color: var(--primary-dark);
    text-decoration: none;
    font-size: 0.78rem;
    font-weight: 600;
    text-align: center;
}
</style>
