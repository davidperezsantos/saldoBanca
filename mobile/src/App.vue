<script setup>
import { ref, onMounted } from 'vue';
import { getToken } from './api/client';
import { logout } from './api/auth';
import { resetAccount } from './composables/account';
import Login from './views/Login.vue';
import BottomNav from './components/BottomNav.vue';

const checkingSession = ref(true);
const isLoggedIn = ref(false);

onMounted(async () => {
    isLoggedIn.value = !!(await getToken());
    checkingSession.value = false;
});

function onLoggedIn() {
    isLoggedIn.value = true;
}

async function handleLogout() {
    await logout();
    resetAccount();
    isLoggedIn.value = false;
}
</script>

<template>
  <div v-if="!checkingSession" class="app">
    <template v-if="isLoggedIn">
      <header class="topbar">
        <span class="brand">SaldoBanca</span>
        <button class="logout" @click="handleLogout" aria-label="Cerrar sesión">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
            <path d="M16 17l5-5-5-5M21 12H9" />
          </svg>
        </button>
      </header>
      <main class="shell">
        <router-view />
      </main>
      <BottomNav />
    </template>
    <div v-else class="centered">
      <Login @logged-in="onLoggedIn" />
    </div>
  </div>
</template>

<style>
:root {
    --primary: #34d399;
    --primary-dark: #14b8a6;
}
body {
    margin: 0;
    font-family: 'Inter', system-ui, sans-serif;
    background: #f4f5f7;
}
.app {
    min-height: 100vh;
}
.centered {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
}
.topbar {
    position: sticky;
    top: 0;
    z-index: 5;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.9rem 1.25rem;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
}
.brand {
    font-weight: 700;
    font-size: 1.05rem;
    letter-spacing: 0.01em;
}
.logout {
    background: rgba(255, 255, 255, 0.15);
    border: none;
    border-radius: 8px;
    color: white;
    padding: 0.4rem;
    display: flex;
    cursor: pointer;
}
.shell {
    max-width: 480px;
    margin: 0 auto;
    padding: 1.1rem 1rem 5.5rem;
    box-sizing: border-box;
}
</style>
