<script setup>
import { ref, watch, onMounted } from 'vue';
import { hasScope } from '../api/permissions';
import { roleVersion } from '../composables/role';
import MiCuenta from './MiCuenta.vue';
import Dashboard from './admin/Dashboard.vue';

// Los usuarios staff (super_admin/admin/support) no tienen Account — en vez de MiCuenta (saldo,
// tarjeta, etc.) su "/" es el Dashboard. "dashboard.read" lo tienen los tres roles de staff
// (viene de dashboard:view en SeedRolesCommand), así que sirve como señal genérica de "es staff"
// sin tener que listar cada scope de cada módulo admin nuevo que se vaya agregando. roleVersion
// cambia cuando RoleSwitcher cambia el rol activo, así que esto se re-evalúa sin recargar la app.
const mode = ref(null);

async function resolve() {
    mode.value = (await hasScope('dashboard.read')) ? 'staff' : 'account';
}

onMounted(resolve);
watch(roleVersion, resolve);
</script>

<template>
  <Dashboard v-if="mode === 'staff'" />
  <MiCuenta v-else-if="mode === 'account'" />
</template>
