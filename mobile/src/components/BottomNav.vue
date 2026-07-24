<script setup>
import { ref, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { hasScope } from '../api/permissions';

const { t } = useI18n();

const ALL_TABS = computed(() => [
    {
        to: '/',
        label: t('nav.account'),
        scope: 'accounts.read',
        path: 'M12 12c2.7 0 4.9-2.2 4.9-4.9S14.7 2.2 12 2.2 7.1 4.4 7.1 7.1 9.3 12 12 12Zm0 2.4c-3.5 0-10.4 1.8-10.4 5.3v2.1h20.8v-2.1c0-3.5-6.9-5.3-10.4-5.3Z',
    },
    {
        to: '/recargas',
        label: t('nav.recharges'),
        scope: 'recharges.read',
        path: 'M12 2v20m0-20 5 5m-5-5-5 5M4 20h16',
        stroke: true,
    },
    {
        to: '/transferencias',
        label: t('nav.transfers'),
        scope: 'transfers.read',
        path: 'M7 7h13m0 0-4-4m4 4-4 4M17 17H4m0 0 4 4m-4-4 4-4',
        stroke: true,
    },
    {
        to: '/facturas',
        label: t('nav.invoices'),
        scope: 'invoices.read',
        path: 'M6 2h9l5 5v15H6V2Zm9 0v5h5M9 12h6M9 16h6M9 8h2',
        stroke: true,
    },
    {
        to: '/historial',
        label: t('nav.history'),
        scope: 'history.read',
        path: 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Zm0-16v6l4 2',
        stroke: true,
    },
]);

const tabs = ref([]);

onMounted(async () => {
    const visible = [];
    for (const tab of ALL_TABS.value) {
        if (await hasScope(tab.scope)) {
            visible.push(tab);
        }
    }
    tabs.value = visible;
});
</script>

<template>
  <nav v-if="tabs.length > 1" class="bottom-nav">
    <router-link
      v-for="tab in tabs"
      :key="tab.to"
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
