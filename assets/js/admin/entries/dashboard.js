import { createSaldoApp } from '../app.js';
import PageComponent from '../components/Dashboard.vue';

const app = createSaldoApp(PageComponent);
app.mount('#vue-app');
