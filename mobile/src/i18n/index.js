import { createI18n } from 'vue-i18n';
import es from './es.js';
import en from './en.js';

function detectLocale() {
    const lang = (navigator.language || navigator.userLanguage || 'es').toLowerCase();
    return lang.startsWith('en') ? 'en' : 'es';
}

export const i18n = createI18n({
    legacy: false,
    locale: detectLocale(),
    fallbackLocale: 'es',
    messages: { es, en },
});
