<template>
  <section class="section admin">
    <AdminSidebar />
    <div class="admin-content">
      <h2>Gestion de cupones</h2>
      <div class="card form-card">
        <input v-model="code" class="input" placeholder="Codigo" />
        <input v-model.number="discount" class="input" type="number" placeholder="Descuento %" />
        <button class="btn" @click="create">Crear</button>
      </div>
      <div class="card" v-for="coupon in coupons" :key="coupon.id">
        <p>{{ coupon.code }} - {{ coupon.discount_percentage }}%</p>
      </div>
    </div>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../../services/api'
import AdminSidebar from '../../components/AdminSidebar.vue'

const coupons = ref([])
const code = ref('')
const discount = ref(0)

const load = async () => {
  const res = await api.get('/api/admin/coupons')
  coupons.value = res.data || []
}

const create = async () => {
  await api.post('/api/admin/coupons', { code: code.value, discount_percentage: discount.value, active: 1 })
  await load()
}

onMounted(load)
</script>
