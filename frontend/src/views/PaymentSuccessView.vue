<template>
  <section class="section payment-success">
    <div class="card success-card">
      <h1>Pago exitoso</h1>
      <p v-if="loading" class="muted">Confirmando tu pedido con el backend...</p>
      <p v-else-if="error" class="err">{{ error }}</p>
      <p v-else>Tu pago fue validado y el pedido ya aparece como pagado.</p>
      <RouterLink to="/mis-pedidos" class="btn">Ir a mis pedidos</RouterLink>
    </div>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '../services/api'
import { useCartStore } from '../stores/cart'

const route = useRoute()
const cart = useCartStore()
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  const sessionId = String(route.query.session_id || '')
  const orderId = Number(route.query.order_id || 0)

  if (!sessionId || !orderId) {
    error.value = 'Faltan datos del pago para confirmar el pedido.'
    loading.value = false
    return
  }

  try {
    await api.post('/api/checkout/confirm-session', {
      session_id: sessionId,
      order_id: orderId,
    })
    cart.clear()
  } catch (e) {
    error.value = e.message || 'No se pudo confirmar el pago.'
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.payment-success {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 70vh;
}
.success-card {
  max-width: 520px;
  text-align: center;
  display: grid;
  gap: 16px;
}
.err {
  color: #b91c1c;
}
.muted {
  color: #64748b;
}
</style>
