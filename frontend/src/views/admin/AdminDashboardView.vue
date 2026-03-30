<template>
  <section class="section admin">
    <AdminSidebar />
    <div class="admin-content">
      <h2>Panel administrador</h2>
      <div class="grid">
        <div class="card" v-for="card in stats" :key="card.label">
          <h3>{{ card.label }}</h3>
          <p>{{ card.value }}</p>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../../services/api'
import AdminSidebar from '../../components/AdminSidebar.vue'

const stats = ref([
  { label: 'Pedidos', value: 0 },
  { label: 'Usuarios', value: 0 },
  { label: 'Productos', value: 0 },
])

onMounted(async () => {
  const res = await api.get('/api/admin/stats')
  stats.value = [
    { label: 'Pedidos', value: res.data?.orders || 0 },
    { label: 'Usuarios', value: res.data?.users || 0 },
    { label: 'Productos', value: res.data?.products || 0 },
  ]
})
</script>
