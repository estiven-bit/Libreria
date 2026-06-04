<template>
  <section class="section admin">
    <AdminSidebar />
    <div class="admin-content">
      <h2>Gestion de pedidos</h2>
      <div class="card" v-for="order in orders" :key="order.id">
        <p>
          #{{ order.id }} - {{ order.status }} - ${{ order.total_price }}
          <span v-if="order.user_email"> — {{ order.user_email }}</span>
        </p>
        <div class="row">
          <select v-model="order.status" class="input">
            <option value="pending">pending</option>
            <option value="paid">paid</option>
            <option value="cancelled">cancelled</option>
            <option value="shipped">shipped</option>
            <option value="delivered">delivered</option>
          </select>
          <button class="btn" @click="save(order)">Guardar</button>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../../services/api'
import AdminSidebar from '../../components/AdminSidebar.vue'

const orders = ref([])

const load = async () => {
  const res = await api.get('/api/admin/orders')
  orders.value = res.data || []
}

const save = async (order) => {
  await api.patch(`/api/admin/orders/${order.id}`, { status: order.status })
}

onMounted(async () => {
  await load()
})
</script>
