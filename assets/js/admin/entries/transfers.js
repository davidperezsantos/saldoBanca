import { createSaldoApp } from '../app.js';
import PageComponent from '../components/Transfers.vue';

const app = createSaldoApp(PageComponent);
app.mount('#vue-app');
