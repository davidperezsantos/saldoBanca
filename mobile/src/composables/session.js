import { ref } from 'vue';

// true una vez que se completó al menos una llamada autenticada exitosa en esta sesión de app.
// Distingue "el token ya estaba vencido al abrir la app" (nunca llegó a ser true) de
// "el token venció mientras la usabas" (ya había sido true).
export const sessionActive = ref(false);

export const showRenewPrompt = ref(false);
export const expiredUsername = ref('');
export const forceLogout = ref(false);

export function markSessionActive() {
    sessionActive.value = true;
}

export function handleSessionExpired(username) {
    if (sessionActive.value) {
        sessionActive.value = false;
        expiredUsername.value = username || '';
        showRenewPrompt.value = true;
    } else {
        forceLogout.value = true;
    }
}

export function resetSession() {
    sessionActive.value = false;
    showRenewPrompt.value = false;
    expiredUsername.value = '';
    forceLogout.value = false;
}
