import { createSaldoApp } from '../app.js';
import PageComponent from '../components/ExchangeProviders.vue';

const app = createSaldoApp(PageComponent);
app.mount('#vue-app');
