import { createSaldoApp } from '../app.js';
import PageComponent from '../components/Authorized.vue';

const app = createSaldoApp(PageComponent);
app.mount('#vue-app');
