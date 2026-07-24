import { createSaldoApp } from '../app.js';
import PageComponent from '../components/Currencies.vue';

const app = createSaldoApp(PageComponent);
app.mount('#vue-app');
