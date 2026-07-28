<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { getAvailableRoles, getActiveRole } from '../api/client';
import { switchActiveRole } from '../api/auth';
import { bumpRoleVersion } from '../composables/role';

const { t } = useI18n();

const roles = ref([]);
const activeRoleId = ref(null);
const open = ref(false);
const switching = ref(false);
const error = ref('');

async function refresh() {
    roles.value = await getAvailableRoles();
    const active = await getActiveRole();
    activeRoleId.value = active?.id ?? null;
}

onMounted(refresh);

async function pick(role) {
    open.value = false;
    if (role.id === activeRoleId.value || switching.value) {
        return;
    }
    error.value = '';
    switching.value = true;
    try {
        await switchActiveRole(role.id);
        await refresh();
        bumpRoleVersion();
    } catch (e) {
        error.value = e.response?.data?.message || t('roleSwitcher.error');
    } finally {
        switching.value = false;
    }
}
</script>

<template>
  <div v-if="roles.length > 1" class="role-fab-wrap">
    <div v-if="open" class="role-menu">
      <button
        v-for="role in roles"
        :key="role.id"
        type="button"
        class="role-menu-item"
        :class="{ active: role.id === activeRoleId }"
        :disabled="switching"
        @click="pick(role)"
      >
        {{ role.label }}
      </button>
      <p v-if="error" class="role-menu-error">{{ error }}</p>
    </div>
    <button
      type="button"
      class="role-fab"
      :aria-label="t('roleSwitcher.ariaLabel')"
      :disabled="switching"
      @click="open = !open"
    >
      ⇄
    </button>
  </div>
</template>

<style scoped>
.role-fab-wrap {
    position: fixed;
    right: 1rem;
    bottom: calc(4.6rem + env(safe-area-inset-bottom, 0px));
    z-index: 11;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.5rem;
}

.role-fab {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    border: none;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    font-size: 1.3rem;
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
    cursor: pointer;
}

.role-fab:disabled {
    opacity: 0.6;
}

.role-menu {
    background: white;
    border-radius: 10px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.18);
    padding: 0.4rem;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    min-width: 160px;
}

.role-menu-item {
    padding: 0.55rem 0.75rem;
    border: none;
    border-radius: 6px;
    background: transparent;
    color: #333;
    font-size: 0.88rem;
    font-weight: 600;
    text-align: left;
    cursor: pointer;
}

.role-menu-item.active {
    background: #eefaf5;
    color: var(--primary-dark);
}

.role-menu-item:disabled {
    opacity: 0.6;
    cursor: default;
}

.role-menu-error {
    margin: 0.2rem 0.3rem 0;
    font-size: 0.75rem;
    color: #c0392b;
}
</style>
