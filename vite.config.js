import { defineConfig } from 'vite';
import { fileURLToPath, URL } from 'node:url';
import vue from '@vitejs/plugin-vue';
import symfonyPlugin from 'vite-plugin-symfony';
import tailwindcss from '@tailwindcss/vite';
import Components from 'unplugin-vue-components/vite';
import { PrimeVueResolver } from 'unplugin-vue-components/resolvers';

export default defineConfig({
    plugins: [
        vue(),
        symfonyPlugin({
            refresh: true,
        }),
        tailwindcss(),
        Components({
            resolvers: [
                PrimeVueResolver(),
            ],
        }),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./assets', import.meta.url)),
        },
    },
    build: {
        outDir: 'public/build',
        rollupOptions: {
            input: {
                app: 'assets/js/admin/app.js',
                dashboard: 'assets/js/admin/entries/dashboard.js',
                users: 'assets/js/admin/entries/users.js',
                roles: 'assets/js/admin/entries/roles.js',
                accounts: 'assets/js/admin/entries/accounts.js',
                recharges: 'assets/js/admin/entries/recharges.js',
                transfers: 'assets/js/admin/entries/transfers.js',
                authorized: 'assets/js/admin/entries/authorized.js',
                invoices: 'assets/js/admin/entries/invoices.js',
                reconciliations: 'assets/js/admin/entries/reconciliations.js',
                reconciliation_report: 'assets/js/admin/entries/reconciliation_report.js',
                history: 'assets/js/admin/entries/history.js',
                exchange_rates: 'assets/js/admin/entries/exchange_rates.js',
                exchange_providers: 'assets/js/admin/entries/exchange_providers.js',
                payment_gateways: 'assets/js/admin/entries/payment_gateways.js',
                oauth_clients: 'assets/js/admin/entries/oauth_clients.js',
                currencies: 'assets/js/admin/entries/currencies.js',
                commission_settlements: 'assets/js/admin/entries/commission_settlements.js',
            },
        },
    },
});
