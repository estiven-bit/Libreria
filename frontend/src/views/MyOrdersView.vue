<template>
  <div class="page-container">
    <div class="video-background">
      <video autoplay muted loop playsinline class="video-bg">
        <source src="../assets/vieo-fondo-perfil.mp4" type="video/mp4">
        Tu navegador no soporta videos.
      </video>
      <div class="video-overlay"></div>
    </div>

    <section class="orders-page section">
      <h1 class="title">Mis pedidos</h1>
      <p v-if="error" class="err">{{ error }}</p>
      <p v-else-if="!orders.length && !loading" class="empty">Aún no tienes compras.</p>
      <p v-if="loading" class="muted">Cargando…</p>

      <div v-for="order in orders" :key="order.id" class="glass-card order-card">
        <div class="order-status-row">
          <span class="status-badge" :class="order.status">{{ translateStatus(order.status) }}</span>
          <span class="order-date">{{ formatDate(order.created_at) }}</span>
        </div>

        <div class="order-items-list">
          <div v-for="item in order.items" :key="item.id" class="order-item-row">
            <div class="item-cover">
              <img :src="getImageUrl(item)" :alt="item.product_name" />
            </div>
            <div class="item-info">
              <h3 class="item-title">{{ item.product_name }}</h3>
              <p class="item-qty-price">Cantidad: {{ item.quantity }} · ${{ Number(item.price).toFixed(2) }} c/u</p>
            </div>
          </div>
        </div>

        <div class="order-footer">
          <div class="payment-info">
            <span class="pay-method">{{ formatPaymentMethod(order.payment_method) }}</span>
          </div>
          <div class="order-total">
            Total: <span class="total-price">${{ Number(order.total_price).toFixed(2) }}</span>
          </div>
        </div>
      </div>

      <RouterLink to="/catalogo" class="btn link-catalog">Seguir comprando</RouterLink>
    </section>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../services/api'
import { store } from '../store'
import placeholderImage from '../assets/img/placeholder.png'

const orders = ref([])
const loading = ref(true)
const error = ref('')

function formatDate(s) {
  if (!s) return ''
  try {
    return new Date(s).toLocaleString('es-ES')
  } catch {
    return s
  }
}

function formatPaymentMethod(m) {
  if (m === 'card_online') return 'Tarjeta (Pago online)'
  if (m === 'cash_on_delivery') return 'Pago al recibir (Contra reembolso)'
  return m
}

function translateStatus(s) {
  const statusMap = {
    pending: 'Pendiente',
    paid: 'Pagado',
    cancelled: 'Cancelado',
    preparing: 'Preparándose',
    ready: 'Listo para entregar',
    shipped: 'Enviado',
    delivered: 'Entregado'
  }
  return statusMap[s] || s
}

const getImageUrl = (item) => {
  if (!item.primary_image_id) return placeholderImage
  return api.mediaUrl(`/api/products/${item.product_id}/images/${item.primary_image_id}`)
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

.video-background {
  position: fixed;
  top: 0; left: 0;
  width: 100%; height: 100vh;
  z-index: -1;
  overflow: hidden;
}
.video-bg { width: 100%; height: 100%; object-fit: cover; }
.video-overlay {
  position: absolute;
  top: 0; left: 0;
  width: 100%; height: 100%;
  background: rgba(15, 23, 42, 0.4);
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
  border-radius: 20px;
  color: #ffffff;
  padding: 24px 28px;
  margin-bottom: 20px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

.order-status-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.25rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  padding-bottom: 0.75rem;
}

.status-badge {
  font-size: 0.82rem;
  font-weight: 800;
  text-transform: uppercase;
  padding: 4px 10px;
  border-radius: 6px;
  background-color: rgba(255, 255, 255, 0.1);
  letter-spacing: 0.5px;
}

.status-badge.pending {
  color: #ff9f43;
  background-color: rgba(255, 159, 67, 0.15);
}

.status-badge.paid {
  color: #10b981;
  background-color: rgba(16, 185, 129, 0.15);
}

.status-badge.cancelled {
  color: #ff6b6b;
  background-color: rgba(255, 107, 107, 0.15);
}

.status-badge.preparing {
  color: #a855f7;
  background-color: rgba(168, 85, 247, 0.15);
}

.status-badge.ready {
  color: #3b82f6;
  background-color: rgba(59, 130, 246, 0.15);
}

.status-badge.shipped {
  color: #06b6d4;
  background-color: rgba(6, 182, 212, 0.15);
}

.status-badge.delivered {
  color: #10b981;
  background-color: rgba(16, 185, 129, 0.25);
}

.order-date {
  font-size: 0.8rem;
  color: #cbd5e1;
}

.order-items-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  margin-bottom: 1.25rem;
}

.order-item-row {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.item-cover {
  width: 50px;
  height: 68px;
  border-radius: 6px;
  overflow: hidden;
  background: rgba(15, 23, 42, 0.4);
  border: 1px solid rgba(255, 255, 255, 0.1);
  flex-shrink: 0;
}

.item-cover img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.item-info {
  flex: 1;
  min-width: 0;
  text-align: left;
}

.item-title {
  margin: 0 0 4px;
  font-size: 0.98rem;
  font-weight: 700;
  color: #ffffff;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.item-qty-price {
  margin: 0;
  font-size: 0.82rem;
  color: #94a3b8;
}

.order-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  padding-top: 0.75rem;
}

.payment-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  text-align: left;
}

.pay-method {
  font-size: 0.85rem;
  color: #cbd5e1;
}

.order-total {
  font-size: 0.95rem;
  color: #cbd5e1;
}

.total-price {
  font-size: 1.15rem;
  font-weight: 800;
  color: #ff9f43;
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
