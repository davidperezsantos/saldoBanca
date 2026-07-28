import { registerPlugin } from '@capacitor/core';

// Plugin nativo propio (android/app/.../ApkInstallerPlugin.java) — dispara el instalador del
// sistema para un APK ya descargado a disco. Ver Perfil.vue::openUpdate().
const ApkInstaller = registerPlugin('ApkInstaller');

export async function installApk(path) {
    await ApkInstaller.install({ path });
}
