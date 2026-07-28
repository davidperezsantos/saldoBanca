<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { Filesystem, Directory } from '@capacitor/filesystem';
import { apiClient, setUser, getAvailableRoles, getActiveRole, getDefaultRoleId, setDefaultRoleId } from '../api/client';
import { useUser, initials } from '../composables/user';
import { useAccount, loadAccount } from '../composables/account';
import { hasScope } from '../api/permissions';
import { checkForUpdate } from '../api/updateCheck';
import { installApk } from '../api/apkInstaller';
import PhoneInput from '../components/PhoneInput.vue';

const { t } = useI18n();
const emit = defineEmits(['logged-out']);

const { user } = useUser();
const { account } = useAccount();
const canSeeAuthorized = ref(false);
const hasAccount = ref(false);

const updateInfo = ref(null);
const updateError = ref('');
const downloadingUpdate = ref(false);

const availableRoles = ref([]);
const activeRole = ref(null);
const defaultRoleId = ref(null);
const settingDefault = ref(false);

onMounted(async () => {
    canSeeAuthorized.value = await hasScope('authorized.read');
    hasAccount.value = await hasScope('accounts.read');

    availableRoles.value = await getAvailableRoles();
    activeRole.value = await getActiveRole();
    defaultRoleId.value = await getDefaultRoleId();

    try {
        const info = await checkForUpdate();
        if (info.available) {
            updateInfo.value = info;
        }
    } catch (e) {
        // Silencioso a propósito: si falla el chequeo (sin internet, GitHub caído, etc.) la
        // pantalla sigue funcionando normal, simplemente no muestra el aviso de actualización.
    }
});

async function makeActiveRoleDefault() {
    if (!activeRole.value) return;
    settingDefault.value = true;
    try {
        await setDefaultRoleId(activeRole.value.id);
        defaultRoleId.value = activeRole.value.id;
    } finally {
        settingDefault.value = false;
    }
}

// Antes se delegaba la descarga entera a Chrome (Custom Tab, y después navegador del sistema —
// ver git log) y en los dos casos la barra de progreso de Chrome se quedaba trabada en 100% sin
// pasar nunca a "Descarga completa" — confirmado con una descarga manual por fuera de Chrome que
// el archivo en sí está bien (se baja completo y es un APK válido), así que el problema está en
// cómo Chrome maneja esa descarga puntual, no en el archivo. Ahora la app baja el APK ella misma
// con fetch() y solo le pide a Android el paso de instalar (ApkInstallerPlugin, nativo).
async function openUpdate() {
    updateError.value = '';
    downloadingUpdate.value = true;
    try {
        const response = await fetch(updateInfo.value.downloadUrl);
        if (!response.ok) {
            throw new Error(`download failed: ${response.status}`);
        }
        const blob = await response.blob();
        const base64 = await blobToBase64(blob);

        const written = await Filesystem.writeFile({
            path: 'update.apk',
            data: base64,
            directory: Directory.Cache,
        });

        await installApk(written.uri.replace('file://', ''));
    } catch (e) {
        updateError.value = t('profile.updateError');
    } finally {
        downloadingUpdate.value = false;
    }
}

function blobToBase64(blob) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onloadend = () => resolve(String(reader.result).split(',')[1]);
        reader.onerror = () => reject(reader.error);
        reader.readAsDataURL(blob);
    });
}

const nameInput = ref(user.value?.name || '');
const phoneInput = ref(user.value?.phone || '');
const editError = ref('');
const editSuccess = ref('');
const editSubmitting = ref(false);

async function saveProfile() {
    editError.value = '';
    editSuccess.value = '';

    if (!nameInput.value.trim()) {
        editError.value = t('profile.nameRequired');
        return;
    }

    editSubmitting.value = true;
    try {
        const { data } = await apiClient.put('/me', {
            name: nameInput.value.trim(),
            phone: phoneInput.value,
        });
        const updatedUser = { ...user.value, name: data.data.name, phone: data.data.phone };
        user.value = updatedUser;
        await setUser(updatedUser);
        editSuccess.value = t('profile.editSuccess');
    } catch (e) {
        editError.value = e.response?.data?.message || t('profile.editError');
    } finally {
        editSubmitting.value = false;
    }
}

const pinLoading = ref(false);
const pinMessage = ref('');
const pinError = ref('');

async function requestNewPin() {
    pinError.value = '';
    pinMessage.value = '';
    pinLoading.value = true;
    try {
        await loadAccount();
        await apiClient.post(`/accounts/${account.value.id}/request-pin`);
        pinMessage.value = t('profile.pinSuccess');
    } catch (e) {
        pinError.value = e.response?.data?.message || t('profile.pinError');
    } finally {
        pinLoading.value = false;
    }
}

const currentPassword = ref('');
const newPassword = ref('');
const confirmPassword = ref('');
const passwordError = ref('');
const passwordSuccess = ref('');
const submitting = ref(false);

async function changePassword() {
    passwordError.value = '';
    passwordSuccess.value = '';

    if (newPassword.value.length < 8) {
        passwordError.value = t('profile.passwordTooShort');
        return;
    }
    if (newPassword.value !== confirmPassword.value) {
        passwordError.value = t('profile.passwordMismatch');
        return;
    }

    submitting.value = true;
    try {
        await apiClient.put('/me/password', {
            currentPassword: currentPassword.value,
            newPassword: newPassword.value,
        });
        passwordSuccess.value = t('profile.passwordSuccess');
        currentPassword.value = '';
        newPassword.value = '';
        confirmPassword.value = '';
    } catch (e) {
        passwordError.value = e.response?.data?.message || t('profile.passwordError');
    } finally {
        submitting.value = false;
    }
}

function handleLogout() {
    emit('logged-out');
}
</script>

<template>
  <div class="stack">
    <div class="card profile-card">
      <div class="avatar-lg">{{ initials(user?.name) }}</div>
      <p class="name">{{ user?.name || user?.username }}</p>
      <p class="detail">@{{ user?.username }}</p>
      <p class="detail">{{ user?.email }}</p>
    </div>

    <div v-if="updateInfo" class="card update-card">
      <p class="update-title">{{ t('profile.updateAvailable', { version: updateInfo.latestVersion }) }}</p>
      <p v-if="downloadingUpdate" class="update-hint">{{ t('profile.updateDownloading') }}</p>
      <p v-if="updateError" class="error">{{ updateError }}</p>
      <button class="update-btn" :disabled="downloadingUpdate" @click="openUpdate">
        {{ downloadingUpdate ? t('profile.updateDownloadingBtn') : t('profile.updateButton') }}
      </button>
    </div>

    <div class="card">
      <h2>{{ t('profile.editTitle') }}</h2>
      <form class="form" @submit.prevent="saveProfile">
        <label>
          {{ t('profile.nameLabel') }}
          <input v-model="nameInput" type="text" required />
        </label>
        <label>
          {{ t('profile.phoneLabel') }}
          <PhoneInput v-model="phoneInput" />
        </label>
        <p v-if="editError" class="error">{{ editError }}</p>
        <p v-if="editSuccess" class="success">{{ editSuccess }}</p>
        <button type="submit" :disabled="editSubmitting">
          {{ editSubmitting ? t('common.saving') : t('common.save') }}
        </button>
      </form>
    </div>

    <div v-if="hasAccount" class="card">
      <h2>{{ t('profile.pinTitle') }}</h2>
      <p class="hint">{{ t('profile.pinHint') }}</p>
      <p v-if="pinError" class="error">{{ pinError }}</p>
      <p v-if="pinMessage" class="success">{{ pinMessage }}</p>
      <button class="secondary-btn" :disabled="pinLoading" @click="requestNewPin">
        {{ pinLoading ? t('common.sending') : t('profile.pinButton') }}
      </button>
    </div>

    <div v-if="availableRoles.length > 1" class="card">
      <h2>{{ t('profile.roleTitle') }}</h2>
      <p class="hint">{{ t('profile.roleActive', { role: activeRole?.label }) }}</p>
      <button
        v-if="activeRole && activeRole.id !== defaultRoleId"
        class="secondary-btn"
        :disabled="settingDefault"
        @click="makeActiveRoleDefault"
      >
        {{ settingDefault ? t('common.saving') : t('profile.roleMakeDefault') }}
      </button>
      <p v-else class="hint">{{ t('profile.roleIsDefault') }}</p>
    </div>

    <router-link v-if="canSeeAuthorized" to="/autorizados" class="card link-card">
      <span>{{ t('profile.authorizedLink') }}</span>
      <span class="chevron">›</span>
    </router-link>

    <div class="card">
      <h2>{{ t('profile.passwordTitle') }}</h2>
      <form class="form" @submit.prevent="changePassword">
        <label>
          {{ t('profile.currentPassword') }}
          <input v-model="currentPassword" type="password" required autocomplete="current-password" />
        </label>
        <label>
          {{ t('profile.newPassword') }}
          <input v-model="newPassword" type="password" required autocomplete="new-password" minlength="8" />
        </label>
        <label>
          {{ t('profile.confirmPassword') }}
          <input v-model="confirmPassword" type="password" required autocomplete="new-password" minlength="8" />
        </label>
        <p v-if="passwordError" class="error">{{ passwordError }}</p>
        <p v-if="passwordSuccess" class="success">{{ passwordSuccess }}</p>
        <button type="submit" :disabled="submitting">
          {{ submitting ? t('common.saving') : t('common.save') }}
        </button>
      </form>
    </div>

    <button class="logout-btn" @click="handleLogout">{{ t('profile.logout') }}</button>
  </div>
</template>

<style scoped>
.stack {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.card {
    background: white;
    border-radius: 12px;
    padding: 1.25rem;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
}
.profile-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.2rem;
    padding: 1.75rem 1.25rem;
}
.avatar-lg {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}
.name {
    margin: 0;
    font-weight: 700;
    font-size: 1.05rem;
}
.detail {
    margin: 0;
    font-size: 0.85rem;
    color: #888;
}
.update-card {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
}
.update-title {
    margin: 0 0 0.7rem;
    font-weight: 600;
    font-size: 0.95rem;
}
.update-hint {
    margin: 0 0 0.7rem;
    font-size: 0.8rem;
    opacity: 0.85;
}
.update-btn {
    width: 100%;
    padding: 0.6rem;
    border: none;
    border-radius: 8px;
    background: white;
    color: var(--primary-dark);
    font-weight: 700;
    cursor: pointer;
}
.update-btn:disabled {
    opacity: 0.6;
    cursor: default;
}
h2 {
    margin: 0 0 0.9rem;
    font-size: 1.05rem;
}
.hint {
    margin: -0.4rem 0 0.9rem;
    font-size: 0.82rem;
    color: #777;
}
.secondary-btn {
    width: 100%;
    padding: 0.65rem;
    border: 1px solid #d0d3d8;
    border-radius: 8px;
    background: white;
    color: var(--primary-dark);
    font-weight: 600;
    cursor: pointer;
}
.secondary-btn:disabled {
    opacity: 0.6;
}
.form {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.form label {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    font-size: 0.85rem;
    color: #333;
}
.form input {
    padding: 0.6rem 0.7rem;
    border: 1px solid #d0d3d8;
    border-radius: 8px;
    font-size: 0.95rem;
}
.form button {
    padding: 0.65rem;
    border: none;
    border-radius: 8px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    font-weight: 600;
    cursor: pointer;
}
.form button:disabled {
    opacity: 0.6;
}
.error {
    color: #c0392b;
    font-size: 0.85rem;
    margin: 0;
}
.success {
    color: #0f9d58;
    font-size: 0.85rem;
    margin: 0;
}
.link-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    color: #333;
    font-weight: 600;
    font-size: 0.95rem;
    text-decoration: none;
}
.chevron {
    color: #aaa;
    font-size: 1.2rem;
}
.logout-btn {
    padding: 0.7rem;
    border: 1px solid #f3c2c2;
    border-radius: 8px;
    background: white;
    color: #c0392b;
    font-weight: 600;
    cursor: pointer;
}
</style>
