<template>
  <section class="section admin">
    <AdminSidebar />
    <div class="admin-content">
      <h2>Gestión de cupones</h2>
      <div class="card form-card">
        <input v-model="code" class="input" placeholder="Código que se aplicará" />
        <input v-model.number="discount" class="input" type="number" placeholder="Porcentaje de descuento que se aplicará al producto" />
        <button type="button" class="btn" @click="create">Crear</button>
      </div>
      <div class="card coupon-row" v-for="coupon in coupons" :key="coupon.id">
        <div>
          <strong>{{ coupon.code }}</strong>
          <span class="muted">{{ coupon.discount_percentage }}%</span>
          <span class="pill" :class="{ off: coupon.active != 1 }">
            {{ coupon.active == 1 ? 'Activo' : 'Inactivo' }}
          </span>
        </div>
        <div class="actions">
          <button
            v-if="coupon.active == 1"
            type="button"
            class="btn ghost"
            @click="toggle(coupon, 0)"
          >
            Desactivar
          </button>
          <button v-else type="button" class="btn ghost" @click="toggle(coupon, 1)">Activar</button>
          <button type="button" class="btn ghost danger" @click="remove(coupon)">Eliminar</button>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../../services/api'
import AdminSidebar from '../../components/AdminSidebar.vue'
import { useToastStore } from '../../stores/toast'

const coupons = ref([])
const code = ref('')
const discount = ref(null)
const toast = useToastStore()

const load = async () => {
  const res = await api.get('/api/admin/coupons')
  coupons.value = res.data || []
}

const create = async () => {
  if (!code.value.trim() || discount.value === null) {
    return toast.error('El código y el porcentaje de descuento son obligatorios')
  }
  await api.post('/api/admin/coupons', { code: code.value, discount_percentage: discount.value, active: 1 })
  toast.success('Cupón creado')
  code.value = ''
  discount.value = null
  await load()
}

const toggle = async (c, v) => {
  await api.patch(`/api/admin/coupons/${c.id}`, { active: v })
  toast.success('Cupón actualizado')
  await load()
}

const remove = async (c) => {
  if (!confirm(`¿Eliminar cupón ${c.code}?`)) return
  try {
    await api.delete(`/api/admin/coupons/${c.id}`)
    toast.success('Cupón eliminado')
    await load()
  } catch (e) {
    toast.error(e.message || 'Error')
  }
}

onMounted(load)
</script>

<style scoped>
.form-card {
  display: grid;
  gap: 10px;
  margin-bottom: 16px;
}
.coupon-row {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 12px;
  align-items: center;
  margin-bottom: 12px;
}
.muted {
  display: block;
  font-size: 0.88rem;
  opacity: 0.8;
}
.pill {
  display: inline-block;
  margin-top: 6px;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 800;
  background: #dcfce7;
  color: #166534;
}
.pill.off {
  background: #f1f5f9;
  color: #64748b;
}
.actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.danger {
  color: #b91c1c;
}
</style>
