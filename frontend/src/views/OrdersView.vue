<template>
  <section class="section">
    <h2>Historial de pedidos</h2>
    <div class="card" v-for="order in orders" :key="order.id">
      <p><strong>Pedido:</strong> #{{ order.id }}</p>
      <p><strong>Estado:</strong> {{ order.status }}</p>
      <p><strong>Total:</strong> ${{ order.total_price }}</p>
      <p><strong>Pago:</strong> {{ order.payment_method }}</p>
    </div>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../services/api'

const orders = ref([])

onMounted(async () => {
  const res = await api.get('/api/orders')
  orders.value = res.data || []
})
</script>
