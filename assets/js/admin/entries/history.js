import { createSaldoApp } from '../app.js';
import PageComponent from '../components/History.vue';

const app = createSaldoApp(PageComponent);
app.mount('#vue-app');
