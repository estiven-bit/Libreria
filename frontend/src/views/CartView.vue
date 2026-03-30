<template>
  <div class="page-container">
    <video autoplay muted loop playsinline class="video-bg">
      <source src="../assets/video-fondo-carrito.mp4" type="video/mp4">
      Tu navegador no soporta el video.
    </video>

    <section class="section">
      <h2 class="title">Tu Carrito</h2>

      <div v-if="store.cart.length === 0" class="card glass-card empty-cart">
        <span class="icon">📚</span>
        <p>Tu carrito está esperando ser llenado de historias.</p>
        <RouterLink to="/catalogo" class="btn btn-save">Explorar Catálogo</RouterLink>
      </div>

      <div v-else class="cart-container">
        <div class="cart-list">
          <CartItem
            v-for="item in store.cart"
            :key="item.id"
            :item="item"
            class="glass-card item-row"
            @update="(qty) => updateItem(item.id, qty)"
            @remove="() => removeItem(item.id)"
          />
        </div>

        <div class="cart-summary glass-card">
          <div class="total-row">
            <span>Total a pagar:</span>
            <span class="total-amount">${{ store.cartTotal }}</span>
          </div>
          <RouterLink class="btn btn-checkout" to="/checkout">
            Finalizar Compra
          </RouterLink>
          <RouterLink class="btn btn-continue" to="/catalogo">
            Seguir comprando
          </RouterLink>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
// Cambiado inject por importación directa para mayor estabilidad
import { store } from '../store'
import { api } from '../services/api'
import CartItem from '../components/CartItem.vue'

const updateItem = async (id, qty) => {
  store.updateCart(id, qty)
  if (store.user) {
    try {
      await api.patch('/api/cart/update', { product_id: id, quantity: qty })
    } catch (e) {
      console.error("Error actualizando cantidad", e)
    }
  }
}

const removeItem = async (id) => {
  store.removeFromCart(id)
  if (store.user) {
    try {
      await api.delete('/api/cart/remove', { data: { product_id: id } })
    } catch (e) {
      console.error("Error eliminando producto", e)
    }
  }
}
</script>

<style scoped>
.page-container {
  position: relative;
  min-height: 100vh;
  padding: 120px 20px 50px;
  display: flex;
  justify-content: center;
}

/* Fondo de video */
.video-bg {
  position: fixed;
  top: 0; left: 0;
  width: 100%; height: 100%;
  object-fit: cover;
  z-index: -1;
}

.section {
  max-width: 900px;
  width: 100%;
  z-index: 1;
}

.title {
  color: #ffffff;
  font-size: 2.5rem;
  margin-bottom: 30px;
  text-align: center;
  text-shadow: 2px 2px 4px rgba(0,0,0,0.7);
}

/* Estilo Glassmorphism */
.glass-card {
  background: rgba(255, 255, 255, 0.12);
  backdrop-filter: blur(15px) saturate(160%);
  -webkit-backdrop-filter: blur(15px) saturate(160%);
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 20px;
  color: white;
  text-shadow: 1px 1px 2px rgba(0,0,0,0.8);
}

/* Carrito Vacío */
.empty-cart {
  padding: 60px;
  text-align: center;
}
.empty-cart .icon { font-size: 4rem; display: block; margin-bottom: 20px; }

/* Layout del Carrito */
.cart-container {
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: 25px;
  align-items: start;
}

.item-row {
  margin-bottom: 15px;
  padding: 15px;
  transition: transform 0.2s;
}
.item-row:hover { transform: scale(1.01); }

/* Resumen */
.cart-summary {
  padding: 30px;
  position: sticky;
  top: 120px;
}

.total-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 1.2rem;
  margin-bottom: 25px;
  border-bottom: 1px solid rgba(255,255,255,0.2);
  padding-bottom: 15px;
}

.total-amount {
  font-size: 1.8rem;
  font-weight: 800;
  color: #ff9f43;
}

/* Botones */
.btn {
  display: block;
  width: 100%;
  padding: 15px;
  border-radius: 50px;
  text-align: center;
  font-weight: 700;
  text-decoration: none;
  transition: all 0.3s;
  margin-bottom: 10px;
  border: none;
  cursor: pointer;
}

.btn-checkout {
  background: #ff9f43;
  color: white;
  box-shadow: 0 4px 15px rgba(255, 159, 67, 0.4);
}
.btn-checkout:hover { background: #ff8c1a; transform: translateY(-2px); }

.btn-continue {
  background: rgba(255, 255, 255, 0.1);
  color: white;
  border: 1px solid white;
}
.btn-continue:hover { background: rgba(255, 255, 255, 0.2); }

.btn-save { background: #ff9f43; color: white; width: auto; display: inline-block; padding: 12px 30px; }

/* Responsive */
@media (max-width: 850px) {
  .cart-container { grid-template-columns: 1fr; }
  .cart-summary { position: static; }
  .title { font-size: 2rem; }
}
</style>