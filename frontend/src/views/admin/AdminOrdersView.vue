<template>
  <section class="section admin">
    <AdminSidebar />
    <div class="admin-content">
      <h2>Gestion de pedidos</h2>
      <div class="card" v-for="order in orders" :key="order.id">
        <p>#{{ order.id }} - {{ order.status }} - ${{ order.total_price }}</p>
      </div>
    </div>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../../services/api'
import AdminSidebar from '../../components/AdminSidebar.vue'

const orders = ref([])

onMounted(async () => {
  const res = await api.get('/api/orders')
  orders.value = res.data || []
})
</script>
