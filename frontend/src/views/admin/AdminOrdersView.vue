<template>
  <section class="section admin">
    <AdminSidebar />
    <div class="admin-content">
      <h2>Gestión de pedidos</h2>

      <div v-if="orders.length === 0" class="empty-state card">
        <p>No hay pedidos registrados.</p>
      </div>

      <div class="card order-card" v-for="order in orders" :key="order.id">
        <div class="order-header">
          <span class="order-id">Pedido #{{ order.id }}</span>
          <span class="order-date">{{ new Date(order.created_at).toLocaleString('es-ES') }}</span>
        </div>

        <div class="order-details">
          <div class="detail-item">
            <strong>Cliente:</strong>
            <span>{{ order.user_name || 'Desconocido' }} ({{ order.user_email }})</span>
          </div>
          <div class="detail-item">
            <strong>Total:</strong>
            <span class="price-badge">${{ Number(order.total_price).toFixed(2) }}</span>
          </div>
          <div class="detail-item">
            <strong>Método de pago:</strong>
            <span>{{ order.payment_method === 'card_online' ? '💳 Tarjeta' : '💵 Pago al recibir' }}</span>
          </div>
        </div>

        <div class="order-actions">
          <div class="select-wrapper">
            <select v-model="order.status" class="input select-status">
              <option value="pending">Pendiente (pending)</option>
              <option value="paid">Pagado (paid)</option>
              <option value="cancelled">Cancelado (cancelled)</option>
              <option value="shipped">Enviado (shipped)</option>
              <option value="delivered">Entregado (delivered)</option>
            </select>
          </div>
          <button class="btn btn-save" type="button" @click="save(order)">Guardar Estado</button>
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

const toast = useToastStore()
const orders = ref([])

const load = async () => {
  try {
    const res = await api.get('/api/admin/orders')
    orders.value = res.data || []
  } catch (e) {
    toast.error('Error al cargar la lista de pedidos')
  }
}

const save = async (order) => {
  try {
    await api.patch(`/api/admin/orders/${order.id}`, { status: order.status })
    toast.success(`Pedido #${order.id} actualizado a "${order.status}" con éxito`)
    await load()
  } catch (e) {
    toast.error(e.message || 'Error al actualizar el pedido')
  }
}

onMounted(async () => {
  await load()
})
</script>

<style scoped>
.empty-state {
  text-align: center;
  padding: 40px 20px;
  color: #64748b;
  font-style: italic;
}

.order-card {
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin-bottom: 16px;
}

.order-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 10px;
}

.order-id {
  font-size: 1.1rem;
  font-weight: 800;
  color: #1e293b;
}

.order-date {
  font-size: 0.85rem;
  color: #94a3b8;
}

.order-details {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.detail-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 0.95rem;
  color: #334155;
}

.detail-item strong {
  font-size: 0.78rem;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.price-badge {
  font-weight: bold;
  color: #ff6b6b;
}

.order-actions {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-top: 8px;
  flex-wrap: wrap;
}

.select-wrapper {
  flex: 1;
  min-width: 200px;
}

.select-status {
  width: 100%;
  margin-bottom: 0;
}

.btn-save {
  background: #ff9f43;
  color: white;
  border: none;
  font-weight: bold;
  cursor: pointer;
  white-space: nowrap;
}

@media (max-width: 640px) {
  .order-details {
    grid-template-columns: 1fr;
  }
}
</style>
