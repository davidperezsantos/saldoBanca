import { createApp } from 'vue';
import PrimeVue from 'primevue/config';
import Aura from '@primevue/themes/aura';
import ToastService from 'primevue/toastservice';
import ConfirmationService from 'primevue/confirmationservice';
import DialogService from 'primevue/dialogservice';
import i18n from '../translations';
import Alpine from 'alpinejs';
import 'primeicons/primeicons.css';
import '../../styles/app.css';

// El sidebar de admin_layout.html.twig usa x-data/x-show para los desplegables — antes venía de
// un <script> suelto apuntando a jsdelivr sin versión fijada ni integrity hash (riesgo de cadena
// de suministro para un script con acceso total al DOM en cada pantalla). Empaquetado acá con el
// resto de los assets, con la versión que fija package.json.
window.Alpine = Alpine;
Alpine.start();

export function createSaldoApp(rootComponent) {
    const app = createApp(rootComponent);

    app.use(PrimeVue, {
        theme: {
            preset: Aura,
            options: {
                darkModeSelector: '.dark-mode',
            },
        },
    });

    app.use(ToastService);
    app.use(ConfirmationService);
    app.use(DialogService);
    app.use(i18n);

    return app;
}
