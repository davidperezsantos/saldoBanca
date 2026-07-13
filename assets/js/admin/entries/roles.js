import { createSaldoApp } from '../app.js';
import PageComponent from '../components/Roles.vue';

const app = createSaldoApp(PageComponent);
app.mount('#vue-app');
