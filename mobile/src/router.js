import { createRouter, createWebHashHistory } from 'vue-router';
import Home from './views/Home.vue';
import Recargas from './views/Recargas.vue';
import Transferencias from './views/Transferencias.vue';
import Facturas from './views/Facturas.vue';
import Historial from './views/Historial.vue';
import Perfil from './views/Perfil.vue';
import Autorizados from './views/Autorizados.vue';
import Usuarios from './views/admin/Usuarios.vue';
import Roles from './views/admin/Roles.vue';
import OAuthClients from './views/admin/OAuthClients.vue';
import Administracion from './views/admin/Administracion.vue';
import Soporte from './views/admin/Soporte.vue';
import Cuentas from './views/admin/Cuentas.vue';
import AutorizadosAdmin from './views/admin/AutorizadosAdmin.vue';
import ExchangeProviders from './views/admin/ExchangeProviders.vue';
import ExchangeRates from './views/admin/ExchangeRates.vue';
import Currencies from './views/admin/Currencies.vue';

export const router = createRouter({
    history: createWebHashHistory(),
    routes: [
        { path: '/', name: 'home', component: Home },
        { path: '/recargas', name: 'recargas', component: Recargas },
        { path: '/transferencias', name: 'transferencias', component: Transferencias },
        { path: '/facturas', name: 'facturas', component: Facturas },
        { path: '/historial', name: 'historial', component: Historial },
        { path: '/perfil', name: 'perfil', component: Perfil },
        { path: '/autorizados', name: 'autorizados', component: Autorizados },
        { path: '/administracion', name: 'administracion', component: Administracion },
        { path: '/usuarios', name: 'usuarios', component: Usuarios },
        { path: '/roles', name: 'roles', component: Roles },
        { path: '/oauth-clients', name: 'oauth-clients', component: OAuthClients },
        { path: '/soporte', name: 'soporte', component: Soporte },
        { path: '/cuentas', name: 'cuentas', component: Cuentas },
        { path: '/autorizados-admin', name: 'autorizados-admin', component: AutorizadosAdmin },
        { path: '/exchange-providers', name: 'exchange-providers', component: ExchangeProviders },
        { path: '/exchange-rates', name: 'exchange-rates', component: ExchangeRates },
        { path: '/currencies', name: 'currencies', component: Currencies },
    ],
});
