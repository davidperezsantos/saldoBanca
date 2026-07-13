import { createSaldoApp } from '../app.js';
import PageComponent from '../dashboard.vue';

const app = createSaldoApp(PageComponent);
app.mount('#vue-app');
