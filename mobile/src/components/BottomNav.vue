<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { hasScope, hasAnyScope } from '../api/permissions';
import { roleVersion } from '../composables/role';

const { t } = useI18n();

const ALL_TABS = computed(() => [
    {
        key: 'account',
        to: '/',
        label: t('nav.account'),
        scope: 'accounts.read',
        path: 'M12 12c2.7 0 4.9-2.2 4.9-4.9S14.7 2.2 12 2.2 7.1 4.4 7.1 7.1 9.3 12 12 12Zm0 2.4c-3.5 0-10.4 1.8-10.4 5.3v2.1h20.8v-2.1c0-3.5-6.9-5.3-10.4-5.3Z',
    },
    {
        key: 'dashboard',
        to: '/',
        label: t('nav.dashboard'),
        scope: 'dashboard.read',
        path: 'M3 12l2-2 7-7 7 7 2 2M5 10v10a1 1 0 0 0 1 1h3m10-11v10a1 1 0 0 1-1 1h-3m-6 0a1 1 0 0 0 1-1v-4a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v4a1 1 0 0 0 1 1m-6 0h6',
        stroke: true,
    },
    {
        key: 'administracion',
        to: '/administracion',
        label: t('nav.administration'),
        scopes: ['users.read', 'roles.read', 'oauth_clients.read'],
        path: 'M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3Zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3Zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5Zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5Z',
        stroke: true,
    },
    {
        key: 'soporte',
        to: '/soporte',
        label: t('nav.support'),
        scopes: ['accounts_admin.read', 'authorized_admin.read', 'exchange_providers_admin.read', 'exchange_rates_admin.read', 'currencies_admin.read'],
        path: 'M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM12 14a7 7 0 0 0-7 7h14a7 7 0 0 0-7-7Z',
        stroke: true,
    },
    {
        key: 'recargas',
        to: '/recargas',
        label: t('nav.recharges'),
        scope: 'recharges.read',
        path: 'M12 2v20m0-20 5 5m-5-5-5 5M4 20h16',
        stroke: true,
    },
    {
        key: 'transferencias',
        to: '/transferencias',
        label: t('nav.transfers'),
        scope: 'transfers.read',
        path: 'M7 7h13m0 0-4-4m4 4-4 4M17 17H4m0 0 4 4m-4-4 4-4',
        stroke: true,
    },
    {
        key: 'facturas',
        to: '/facturas',
        label: t('nav.invoices'),
        scope: 'invoices.read',
        path: 'M6 2h9l5 5v15H6V2Zm9 0v5h5M9 12h6M9 16h6M9 8h2',
        stroke: true,
    },
    {
        key: 'historial',
        to: '/historial',
        label: t('nav.history'),
        scope: 'history.read',
        path: 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Zm0-16v6l4 2',
        stroke: true,
    },
]);

const tabs = ref([]);

async function load() {
    const visible = [];
    for (const tab of ALL_TABS.value) {
        const allowed = tab.scopes ? await hasAnyScope(tab.scopes) : await hasScope(tab.scope);
        if (allowed) {
            visible.push(tab);
        }
    }
    tabs.value = visible;
}

onMounted(load);
watch(roleVersion, load);
</script>

<template>
  <nav v-if="tabs.length > 0" class="bottom-nav">
    <router-link
      v-for="tab in tabs"
      :key="tab.key"
      :to="tab.to"
      class="tab"
      active-class="active"
      exact-active-class="active"
    >
      <svg viewBox="0 0 24 24" width="22" height="22" :fill="tab.stroke ? 'none' : 'currentColor'"
           :stroke="tab.stroke ? 'currentColor' : 'none'" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path :d="tab.path" />
      </svg>
      <span>{{ tab.label }}</span>
    </router-link>
  </nav>
</template>

<style scoped>
.bottom-nav {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    background: white;
    border-top: 1px solid #e5e7eb;
    box-shadow: 0 -4px 16px rgba(0, 0, 0, 0.06);
    padding-bottom: env(safe-area-inset-bottom, 0);
    z-index: 10;
}
.tab {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.2rem;
    padding: 0.55rem 0.25rem;
    color: #9aa1a9;
    text-decoration: none;
    font-size: 0.68rem;
    font-weight: 500;
    transition: color 150ms;
}
.tab.active {
    color: var(--primary-dark);
}
</style>
