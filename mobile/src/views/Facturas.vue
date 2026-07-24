<script setup>
import { ref, onMounted } from 'vue';
import { apiClient } from '../api/client';
import { useAccount, loadAccount } from '../composables/account';

const { account } = useAccount();
const loading = ref(true);
const error = ref('');
const invoices = ref([]);

const payingId = ref(null);
const pinRequested = ref(false);
const pinCode = ref('');
const payError = ref('');
const paySuccess = ref('');
const payBusy = ref(false);

const STATUS_LABELS = {
    pending: 'Pendiente',
    paid: 'Pagada',
    cancelled: 'Cancelada',
    refunded: 'Reembolsada',
};

async function load() {
    loading.value = true;
    error.value = '';
    try {
        await loadAccount();
        const { data } = await apiClient.get('/invoices');
        invoices.value = data.data;
    } catch (e) {
        error.value = e.response?.data?.message || 'No se pudieron cargar las facturas';
    } finally {
        loading.value = false;
    }
}

function startPay(invoice) {
    payingId.value = invoice.id;
    pinRequested.value = false;
    pinCode.value = '';
    payError.value = '';
    paySuccess.value = '';
}

function cancelPay() {
    payingId.value = null;
}

async function requestPin() {
    payError.value = '';
    payBusy.value = true;
    try {
        await apiClient.post(`/accounts/${account.value.id}/request-pin`);
        pinRequested.value = true;
    } catch (e) {
        payError.value = e.response?.data?.message || 'No se pudo enviar el código';
    } finally {
        payBusy.value = false;
    }
}

async function confirmPay() {
    payError.value = '';
    payBusy.value = true;
    try {
        await apiClient.put(`/invoices/${payingId.value}/pay`, { pinCode: pinCode.value });
        paySuccess.value = 'Factura pagada';
        payingId.value = null;
        await load();
    } catch (e) {
        payError.value = e.response?.data?.message || 'No se pudo pagar la factura';
    } finally {
        payBusy.value = false;
    }
}

onMounted(load);
</script>

<template>
  <div class="stack">
    <div class="card">
      <h2>Mis facturas</h2>
      <p v-if="loading">Cargando...</p>
      <p v-else-if="error" class="error">{{ error }}</p>
      <p v-else-if="!invoices.length" class="empty">No tenés facturas.</p>
      <ul v-else class="list">
        <li v-for="i in invoices" :key="i.id" class="item">
          <div class="item-row">
            <div>
              <p class="item-title">{{ i.invoiceNumber }}</p>
              <p class="item-sub">{{ i.totalAmount }} {{ i.currency }} · vence {{ i.dueDate ?? '—' }}</p>
            </div>
            <span class="badge" :class="i.status">{{ STATUS_LABELS[i.status] ?? i.status }}</span>
          </div>

          <button v-if="i.status === 'pending' && payingId !== i.id" class="pay-btn" @click="startPay(i)">
            Pagar
          </button>

          <div v-if="payingId === i.id" class="pay-box">
            <template v-if="!pinRequested">
              <p class="hint">Te vamos a enviar un código de verificación por WhatsApp.</p>
              <div class="pay-actions">
                <button class="secondary" @click="cancelPay">Cancelar</button>
                <button :disabled="payBusy" @click="requestPin">
                  {{ payBusy ? 'Enviando...' : 'Enviar código' }}
                </button>
              </div>
            </template>
            <template v-else>
              <label>
                Código recibido por WhatsApp
                <input v-model="pinCode" type="text" inputmode="numeric" placeholder="0000" />
              </label>
              <div class="pay-actions">
                <button class="secondary" @click="cancelPay">Cancelar</button>
                <button :disabled="payBusy || !pinCode" @click="confirmPay">
                  {{ payBusy ? 'Confirmando...' : 'Confirmar pago' }}
                </button>
              </div>
            </template>
            <p v-if="payError" class="error">{{ payError }}</p>
          </div>
        </li>
      </ul>
      <p v-if="paySuccess" class="success">{{ paySuccess }}</p>
    </div>
  </div>
</template>

<style scoped>
.stack {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.card {
    background: white;
    border-radius: 12px;
    padding: 1.25rem;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
}
h2 {
    margin: 0 0 0.75rem;
    font-size: 1.05rem;
}
.empty {
    color: #888;
    font-size: 0.9rem;
}
.error {
    color: #c0392b;
    font-size: 0.85rem;
}
.success {
    color: #0f9d58;
    font-size: 0.85rem;
    margin-top: 0.75rem;
}
.list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.item {
    padding: 0.6rem 0;
    border-bottom: 1px solid #f0f1f3;
}
.item:last-child {
    border-bottom: none;
}
.item-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.item-title {
    margin: 0;
    font-weight: 600;
}
.item-sub {
    margin: 0;
    font-size: 0.78rem;
    color: #888;
}
.badge {
    font-size: 0.72rem;
    font-weight: 600;
    padding: 0.25rem 0.55rem;
    border-radius: 999px;
    background: #eef2f5;
    color: #666;
    text-transform: capitalize;
}
.badge.paid {
    background: #e3f9ef;
    color: #0f9d58;
}
.badge.pending {
    background: #fff4e0;
    color: #b7791f;
}
.badge.cancelled, .badge.refunded {
    background: #fdecea;
    color: #c0392b;
}
.pay-btn {
    margin-top: 0.5rem;
    padding: 0.45rem 0.9rem;
    border: none;
    border-radius: 8px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
}
.pay-box {
    margin-top: 0.6rem;
    padding: 0.75rem;
    background: #f7f9fa;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}
.hint {
    margin: 0;
    font-size: 0.82rem;
    color: #555;
}
.pay-box label {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    font-size: 0.82rem;
    color: #333;
}
.pay-box input {
    padding: 0.55rem 0.65rem;
    border: 1px solid #d0d3d8;
    border-radius: 8px;
    font-size: 1rem;
    letter-spacing: 0.1em;
}
.pay-actions {
    display: flex;
    gap: 0.5rem;
}
.pay-actions button {
    flex: 1;
    padding: 0.5rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
}
.pay-actions button:disabled {
    opacity: 0.6;
}
.pay-actions button.secondary {
    background: white;
    border: 1px solid #d0d3d8;
    color: #333;
}
</style>
