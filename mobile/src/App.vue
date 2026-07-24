<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { getToken } from './api/client';
import { logout } from './api/auth';
import { resetAccount } from './composables/account';
import { useUser, loadUser, resetUser, initials } from './composables/user';
import Login from './views/Login.vue';
import BottomNav from './components/BottomNav.vue';

const router = useRouter();
const { t } = useI18n();
const { user } = useUser();
const checkingSession = ref(true);
const isLoggedIn = ref(false);

onMounted(async () => {
    isLoggedIn.value = !!(await getToken());
    if (isLoggedIn.value) {
        await loadUser();
    }
    checkingSession.value = false;
});

async function onLoggedIn() {
    isLoggedIn.value = true;
    await loadUser(true);
    router.push('/');
}

async function handleLogout() {
    await logout();
    resetAccount();
    resetUser();
    isLoggedIn.value = false;
    router.push('/');
}
</script>

<template>
    <div v-if="!checkingSession" class="app">
        <template v-if="isLoggedIn">
            <header class="topbar">
                <div class="logo">
                    <div class="icon">G</div>
                    <h1>SaldoGrin</h1>
                </div>
                <router-link to="/perfil" class="avatar" :aria-label="t('profile.ariaLabel')">
                    {{ initials(user?.name) }}
                </router-link>
            </header>
            <main class="shell">
                <router-view @logged-out="handleLogout" />
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

.avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    border: 1.5px solid rgba(255, 255, 255, 0.6);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.8rem;
    text-decoration: none;
}

.shell {
    max-width: 480px;
    margin: 0 auto;
    padding: 1.1rem 1rem 5.5rem;
    box-sizing: border-box;
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

.topbar .logo {
    margin-bottom: 0;
    gap: 0.5rem;
    justify-content: flex-start;
}

.topbar .logo .icon {
    width: 32px;
    height: 32px;
    font-size: 16px;
    border-radius: 9px;
    background: rgba(255, 255, 255, 0.2);
    box-shadow: none;
}

.topbar .logo h1 {
    font-size: 1.05rem;
    color: white;
    letter-spacing: 0.01em;
}
</style>
