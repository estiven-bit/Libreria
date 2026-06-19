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
            <span v-if="order.coupon_code" class="coupon-tag">Cupón: {{ order.coupon_code }} (-${{ Number(order.discount_amount).toFixed(2) }})</span>
          </div>
          <div class="detail-item">
            <strong>Método de pago:</strong>
            <span>{{ order.payment_method === 'card_online' ? '💳 Tarjeta' : '💵 Pago al recibir' }}</span>
          </div>
        </div>

        <!-- Order Items -->
        <div class="admin-order-items">
          <div v-for="item in order.items" :key="item.id" class="admin-item-row">
            <div class="item-cover">
              <img :src="getImageUrl(item)" :alt="item.product_name" />
            </div>
            <div class="item-info">
              <span class="item-title">{{ item.product_name }}</span>
              <span class="item-meta">Cant: {{ item.quantity }} · ${{ Number(item.price).toFixed(2) }} u.</span>
            </div>
          </div>
        </div>

        <div class="order-actions">
          <div class="select-wrapper">
            <select v-model="order.status" class="input select-status">
              <option value="pending">Pendiente (pending)</option>
              <option value="preparing">Preparando (preparing)</option>
              <option value="ready">Listo para entregar (ready)</option>
              <option value="delivered">Entregado (delivered)</option>
              <option value="cancelled">Cancelado (cancelled)</option>
              <option value="paid">Pagado (paid)</option>
              <option value="shipped">Enviado (shipped)</option>
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
import placeholderImage from '../../assets/img/placeholder.png'

const toast = useToastStore()
const orders = ref([])

const getImageUrl = (item) => {
  if (!item.primary_image_id) return placeholderImage
  return api.mediaUrl(`/api/products/${item.product_id}/images/${item.primary_image_id}`)
}

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

.coupon-tag {
  font-size: 0.78rem;
  color: #10b981;
  font-weight: bold;
  margin-top: 2px;
}

.admin-order-items {
  display: flex;
  flex-direction: column;
  gap: 10px;
  border-top: 1px dashed #e2e8f0;
  padding-top: 12px;
  margin-top: 4px;
}

.admin-item-row {
  display: flex;
  align-items: center;
  gap: 12px;
}

.item-cover {
  width: 40px;
  height: 54px;
  border-radius: 4px;
  overflow: hidden;
  background: #f1f5f9;
  border: 1px solid #cbd5e1;
}

.item-cover img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.item-info {
  display: flex;
  flex-direction: column;
  text-align: left;
}

.item-title {
  font-size: 0.9rem;
  font-weight: 700;
  color: #1e293b;
}

.item-meta {
  font-size: 0.78rem;
  color: #64748b;
}
</style>
