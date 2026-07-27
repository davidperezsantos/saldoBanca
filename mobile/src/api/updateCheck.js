import { App } from '@capacitor/app';

// mobile-latest es un único release que el workflow de CI actualiza en cada build (ver
// .github/workflows/build-mobile-apk.yml) — no una por versión, así siempre hay una URL fija y
// pública para consultar "cuál es la versión más nueva" sin necesitar autenticación.
const RELEASE_URL = 'https://api.github.com/repos/davidperezsantos/saldoBanca/releases/tags/mobile-latest';

export async function checkForUpdate() {
    const info = await App.getInfo();
    const currentVersionCode = parseInt(info.build, 10);

    const releaseRes = await fetch(RELEASE_URL, {
        headers: { Accept: 'application/vnd.github+json' },
    });
    if (!releaseRes.ok) {
        throw new Error('No se pudo consultar la última versión');
    }
    const release = await releaseRes.json();

    const versionAsset = release.assets?.find((a) => a.name === 'version.json');
    const apkAsset = release.assets?.find((a) => a.name.endsWith('.apk'));
    if (!versionAsset || !apkAsset) {
        return { available: false, currentVersion: info.version };
    }

    const versionRes = await fetch(versionAsset.browser_download_url);
    const remote = await versionRes.json();

    return {
        available: remote.versionCode > currentVersionCode,
        currentVersion: info.version,
        latestVersion: remote.versionName,
        downloadUrl: apkAsset.browser_download_url,
    };
}
