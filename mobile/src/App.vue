<script setup>
import { ref, onMounted } from 'vue';
import { getToken } from './api/client';
import Login from './views/Login.vue';
import MiCuenta from './views/MiCuenta.vue';

const checkingSession = ref(true);
const isLoggedIn = ref(false);

onMounted(async () => {
    isLoggedIn.value = !!(await getToken());
    checkingSession.value = false;
});

function onLoggedIn() {
    isLoggedIn.value = true;
}

function onLoggedOut() {
    isLoggedIn.value = false;
}
</script>

<template>
  <div v-if="!checkingSession" class="app">
    <MiCuenta v-if="isLoggedIn" @logged-out="onLoggedOut" />
    <Login v-else @logged-in="onLoggedIn" />
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
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
}
</style>
