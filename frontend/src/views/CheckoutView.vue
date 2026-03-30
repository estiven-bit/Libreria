<template>
  <section class="section">
    <div class="checkout-header">
      <h2>Finalizar Compra</h2>
      <p v-if="store.user && !store.user.is_active" class="activation-warning">
        ⚠️ Tu cuenta no está activa. Revisa tu correo electrónico para activarla y poder comprar.
      </p>
    </div>

    <div class="checkout-grid" :class="{ 'inactive-user': !store.user?.is_active }">
      <div class="card">
        <h3>Método de pago</h3>
        <label class="radio">
          <input type="radio" value="card_online" v-model="paymentMethod" />
          Tarjeta (online)
        </label>
        <label class="radio">
          <input type="radio" value="cash_on_delivery" v-model="paymentMethod" />
          Pago al recibir
        </label>
        <input v-model="coupon" class="input" placeholder="Código de cupón" />
      </div>

      <div class="card">
        <h3>Resumen</h3>
        <p>Items: {{ store.cart.length }}</p>
        <p class="total">Total: <strong>${{ store.cartTotal }}</strong></p>
        
        <button 
          class="btn-confirm" 
          :disabled="!store.user?.is_active" 
          @click="placeOrder"
        >
          {{ store.user?.is_active ? 'Confirmar pedido' : 'Cuenta no activa' }}
        </button>
      </div>
    </div>
  </section>
</template>

<script setup>
import { inject, ref } from 'vue'
import { api } from '../services/api'

const store = inject('store')
const paymentMethod = ref('card_online')
const coupon = ref('')

const placeOrder = async () => {
  if (!store.user?.is_active) return;

  try {
    await api.post('/api/orders', {
      payment_method: paymentMethod.value,
      coupon_code: coupon.value,
      user_email: store.user?.email,
    })
    store.clearCart()
    alert('¡Pedido realizado con éxito!')
  } catch (err) {
    alert('Error al procesar el pedido')
  }
}
</script>

<style scoped>
.section { padding: 120px 8% 40px; }
.activation-warning {
  background: #fff3cd; color: #856404; padding: 15px;
  border-radius: 8px; border: 1px solid #ffeeba; font-weight: 600;
}
.inactive-user { opacity: 0.6; pointer-events: none; }
.checkout-grid { display: grid; grid-template-columns: 1fr 350px; gap: 30px; margin-top: 20px; }
.card { background: #f8fafc; padding: 25px; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
.radio { display: block; margin-bottom: 10px; cursor: pointer; }
.btn-confirm {
  width: 100%; padding: 15px; background: #ff9f43; color: white;
  border: none; border-radius: 10px; font-weight: 700; cursor: pointer;
}
.btn-confirm:disabled { background: #cbd5e1; cursor: not-allowed; }
</style>