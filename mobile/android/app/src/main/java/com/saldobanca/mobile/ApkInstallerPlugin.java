package com.saldobanca.mobile;

import android.content.Intent;
import android.net.Uri;
import androidx.core.content.FileProvider;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;
import java.io.File;

/**
 * Dispara el instalador del sistema para un APK ya descargado a disco (ver Perfil.vue). Antes se
 * delegaba la descarga entera a Chrome (Custom Tab o navegador del sistema, ver git log) y en
 * ambos casos la barra de progreso de Chrome se quedaba trabada en 100% sin pasar a "Descarga
 * completa" — confirmado que el archivo en sí está bien (se bajó completo y válido por fuera de
 * Chrome). Este plugin evita el downloader de Chrome del todo: la app baja el APK con fetch() y
 * solo usa Android para el paso de instalar, vía un Intent.ACTION_VIEW sobre una content:// URI
 * (FileProvider, ya declarado en AndroidManifest.xml/file_paths.xml).
 */
@CapacitorPlugin(name = "ApkInstaller")
public class ApkInstallerPlugin extends Plugin {

    @PluginMethod
    public void install(PluginCall call) {
        String path = call.getString("path");
        if (path == null || path.isEmpty()) {
            call.reject("path is required");
            return;
        }

        try {
            File file = new File(path);
            if (!file.exists()) {
                call.reject("El archivo descargado no existe: " + path);
                return;
            }

            String authority = getContext().getPackageName() + ".fileprovider";
            Uri uri = FileProvider.getUriForFile(getContext(), authority, file);

            Intent intent = new Intent(Intent.ACTION_VIEW);
            intent.setDataAndType(uri, "application/vnd.android.package-archive");
            intent.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION);
            intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);

            getContext().startActivity(intent);
            call.resolve();
        } catch (Exception e) {
            call.reject("No se pudo abrir el instalador: " + e.getMessage(), e);
        }
    }
}
