import { createSaldoApp } from '../app.js';
import PageComponent from '../components/Users.vue';

const app = createSaldoApp(PageComponent);
app.mount('#vue-app');
