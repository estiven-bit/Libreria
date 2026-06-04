<template>
  <section class="orders-page section">
    <h1 class="title">Mis pedidos</h1>
    <p v-if="error" class="err">{{ error }}</p>
    <p v-else-if="!orders.length && !loading" class="empty">Aún no tienes compras.</p>
    <p v-if="loading" class="muted">Cargando…</p>

    <div v-for="order in orders" :key="order.id" class="card order-card">
      <div class="row-top">
        <span class="badge">#{{ order.id }}</span>
        <span class="status">{{ order.status }}</span>
      </div>
      <p class="total">Total: <strong>${{ Number(order.total_price).toFixed(2) }}</strong></p>
      <p class="meta">Pago: {{ order.payment_method }} · {{ formatDate(order.created_at) }}</p>
    </div>

    <RouterLink to="/catalogo" class="btn link-catalog">Seguir comprando</RouterLink>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../services/api'
import { decodeJwtPayload } from '../services/auth'

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

onMounted(async () => {
  loading.value = true
  error.value = ''
  try {
    const token = localStorage.getItem('token')
    const jwt = decodeJwtPayload(token)
    const userId = Number(jwt?.sub || 0)
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
.orders-page {
  max-width: 720px;
  margin: 0 auto;
  padding-bottom: 3rem;
}
.title {
  font-size: 1.75rem;
  margin-bottom: 1.25rem;
  color: #1a233d;
}
.order-card {
  margin-bottom: 1rem;
  padding: 1.25rem;
  border-radius: 16px;
  background: #fff;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
}
.row-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}
.badge {
  font-weight: 800;
  color: #ff6b6b;
}
.status {
  text-transform: capitalize;
  font-size: 0.85rem;
  font-weight: 700;
  color: #64748b;
}
.total {
  margin: 0.35rem 0;
}
.meta {
  font-size: 0.88rem;
  color: #64748b;
  margin: 0;
}
.err {
  color: #b91c1c;
  margin-bottom: 1rem;
}
.empty,
.muted {
  color: #64748b;
  margin-bottom: 1rem;
}
.link-catalog {
  display: inline-block;
  margin-top: 1rem;
  text-align: center;
}
</style>
