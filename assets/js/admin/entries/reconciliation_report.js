import { createSaldoApp } from '../app.js';
import PageComponent from '../components/ReconciliationReport.vue';

const app = createSaldoApp(PageComponent);
app.mount('#vue-app');
