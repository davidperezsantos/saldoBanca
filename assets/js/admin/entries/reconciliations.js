import { createSaldoApp } from '../app.js';
import PageComponent from '../components/Reconciliations.vue';

const app = createSaldoApp(PageComponent);
app.mount('#vue-app');
