import { createApp } from 'vue';
import PrimeVue from 'primevue/config';
import Aura from '@primevue/themes/aura';
import ToastService from 'primevue/toastservice';
import ConfirmationService from 'primevue/confirmationservice';
import DialogService from 'primevue/dialogservice';
import i18n from '../translations';

import 'primeicons/primeicons.css';
import '../../styles/app.css';

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
