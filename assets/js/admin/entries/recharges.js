import { createSaldoApp } from '../app.js';
import PageComponent from '../components/Recharges.vue';

const app = createSaldoApp(PageComponent);
app.mount('#vue-app');
