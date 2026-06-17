<template>
  <div class="page-container">
    <video autoplay muted loop playsinline class="video-bg">
      <source src="../assets/vieo-fondo-perfil.mp4" type="video/mp4">
      Tu navegador no soporta videos.
    </video>

    <section class="orders-page section">
      <h1 class="title">Mis pedidos</h1>
      <p v-if="error" class="err">{{ error }}</p>
      <p v-else-if="!orders.length && !loading" class="empty">Aún no tienes compras.</p>
      <p v-if="loading" class="muted">Cargando…</p>

      <div v-for="order in orders" :key="order.id" class="glass-card order-card">
        <div class="row-top">
          <span class="badge">#{{ order.id }}</span>
          <span class="status">{{ order.status }}</span>
        </div>
        <p class="total">Total: <strong>${{ Number(order.total_price).toFixed(2) }}</strong></p>
        <p class="meta">Pago: {{ formatPaymentMethod(order.payment_method) }} · {{ formatDate(order.created_at) }}</p>
      </div>

      <RouterLink to="/catalogo" class="btn link-catalog">Seguir comprando</RouterLink>
    </section>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../services/api'
import { store } from '../store'

const orders = ref([])
const loading = ref(true)
const error = ref('')

function formatDate(s) {
  if (!s) return ''
  try {
    return new Date(s).toLocaleString('es')
  } catch {
    return s
  }
}

function formatPaymentMethod(m) {
  if (m === 'card_online') return 'Tarjeta (Pago online)'
  if (m === 'cash_on_delivery') return 'Pago al recibir (Contra reembolso)'
  return m
}

onMounted(async () => {
  loading.value = true
  error.value = ''
  try {
    const userId = Number(store.user?.id || 0)
    if (!userId) {
      throw new Error('Sesion invalida')
    }

    const res = await api.get('/api/user/orders')
    orders.value = (res.data || []).filter((order) => Number(order.user_id) === userId)
  } catch (e) {
    error.value = e.message || 'No se pudieron cargar los pedidos.'
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.page-container {
  position: relative;
  min-height: calc(100vh - 88px);
  padding: 30px 20px 40px;
  display: flex;
  justify-content: center;
  box-sizing: border-box;
}

.video-bg {
  position: fixed;
  top: 0; left: 0;
  width: 100%; height: 100%;
  object-fit: cover;
  z-index: -1;
}

.orders-page {
  max-width: 720px;
  width: 100%;
  z-index: 1;
}

.title {
  color: #ffffff;
  margin-bottom: 20px;
  font-size: 2rem;
  text-shadow: 1px 1px 2px #000, -1px -1px 2px #000, 1px -1px 2px #000, -1px 1px 2px #000;
}

.glass-card {
  background: rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(15px);
  -webkit-backdrop-filter: blur(15px);
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 18px;
  color: #ffffff;
  padding: 24px 28px;
  margin-bottom: 20px;
  box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
}

.row-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.badge {
  font-weight: 800;
  color: #ff9f43;
}

.status {
  text-transform: capitalize;
  font-size: 0.85rem;
  font-weight: 700;
  color: #e2e8f0;
}

.total {
  margin: 0.35rem 0;
}

.meta {
  font-size: 0.88rem;
  color: #e2e8f0;
  margin: 0;
}

.err {
  color: #ff6b6b;
  margin-bottom: 1rem;
}

.empty,
.muted {
  color: #e2e8f0;
  margin-bottom: 1rem;
}

.btn {
  display: block;
  width: 100%;
  padding: 15px;
  border-radius: 50px;
  text-align: center;
  font-weight: 700;
  text-decoration: none;
  transition: all 0.3s;
  margin-top: 1rem;
  background: #ff9f43;
  color: white;
  border: none;
  cursor: pointer;
  box-shadow: 0 4px 15px rgba(255, 159, 67, 0.4);
}

.btn:hover {
  background: #ff8c1a;
  transform: translateY(-2px);
}
</style>
