import { createSaldoApp } from '../app.js';
import PageComponent from '../components/ExchangeRates.vue';

const app = createSaldoApp(PageComponent);
app.mount('#vue-app');
