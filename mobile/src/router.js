import { createRouter, createWebHashHistory } from 'vue-router';
import MiCuenta from './views/MiCuenta.vue';
import Recargas from './views/Recargas.vue';
import Transferencias from './views/Transferencias.vue';
import Facturas from './views/Facturas.vue';
import Historial from './views/Historial.vue';
import Perfil from './views/Perfil.vue';

export const router = createRouter({
    history: createWebHashHistory(),
    routes: [
        { path: '/', name: 'cuenta', component: MiCuenta },
        { path: '/recargas', name: 'recargas', component: Recargas },
        { path: '/transferencias', name: 'transferencias', component: Transferencias },
        { path: '/facturas', name: 'facturas', component: Facturas },
        { path: '/historial', name: 'historial', component: Historial },
        { path: '/perfil', name: 'perfil', component: Perfil },
    ],
});
