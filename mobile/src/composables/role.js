import { ref } from 'vue';

// Se incrementa cada vez que RoleSwitcher cambia el rol activo de la sesión — BottomNav y Home
// lo observan para volver a evaluar sus hasScope() (los scopes cambiaron pero esos componentes no
// se remontan solos).
export const roleVersion = ref(0);

export function bumpRoleVersion() {
    roleVersion.value++;
}
