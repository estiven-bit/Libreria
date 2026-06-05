<template>
  <div>
    <section class="section">
      <div class="checkout-header">
        <h2>Finalizar Compra</h2>
        <p v-if="store.user && !store.user.is_active" class="activation-warning">
          Tu cuenta no esta activa. Revisa tu correo electronico para activarla y poder comprar.
        </p>
        <p v-if="checkoutMessage" class="muted">{{ checkoutMessage }}</p>
      </div>

      <div class="checkout-grid" :class="{ 'inactive-user': !store.user?.is_active }">
        <div class="card">
          <h3>Direccion de envio</h3>
          <div v-if="addresses.length">
            <label class="radio" v-for="a in addresses" :key="a.id">
              <input v-model="selectedAddressId" type="radio" :value="a.id" />
              {{ a.address_line }}, {{ a.city }} ({{ a.postal_code }})
            </label>
          </div>
          <p v-else class="muted">No tienes direcciones guardadas. Crea una para continuar.</p>

          <div class="address-form">
            <input v-model="addressForm.country" class="input" placeholder="Pais" />
            <input v-model="addressForm.city" class="input" placeholder="Ciudad" />
            <input v-model="addressForm.postal_code" class="input" placeholder="Codigo postal" />
            <input v-model="addressForm.address_line" class="input" placeholder="Direccion" />
            <button class="btn-secondary" type="button" @click="createAddress">Guardar direccion</button>
          </div>
        </div>

        <div class="card">
          <h3>Metodo de pago</h3>
          <label class="radio">
            <input v-model="paymentMethod" type="radio" value="card_online" />
            Tarjeta (online)
          </label>
          <label class="radio">
            <input v-model="paymentMethod" type="radio" value="cash_on_delivery" />
            Pago al recibir
          </label>
          <input v-model="coupon" class="input" placeholder="Codigo de cupon" />
        </div>

        <div class="card">
          <h3>Resumen</h3>
          <p>Items: {{ cart.count }}</p>
          <p class="total">Total: <strong>${{ Number(cart.total).toFixed(2) }}</strong></p>

          <button
            v-if="paymentMethod === 'card_online'"
            class="btn-confirm btn-card"
            :disabled="isActionDisabled || loading"
            @click="payWithCard"
          >
            {{ loading ? 'Procesando...' : actionLabel('Pagar con Tarjeta') }}
          </button>

          <button
            v-else
            class="btn-confirm"
            :disabled="isActionDisabled || loading"
            @click="placeCashOrder"
          >
            {{ loading ? 'Procesando...' : actionLabel('Confirmar pedido') }}
          </button>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, inject, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../services/api'
import { useCartStore } from '../stores/cart'

const store = inject('store')
const cart = useCartStore()
const router = useRouter()

const paymentMethod = ref('card_online')
const coupon = ref('')
const addresses = ref([])
const selectedAddressId = ref(null)
const addressForm = ref({ country: '', city: '', postal_code: '', address_line: '' })
const loading = ref(false)
const checkoutMessage = ref('')

const isActionDisabled = computed(() => (
  !store.user?.is_active || !selectedAddressId.value || cart.count < 1
))

const actionLabel = (baseLabel) => {
  if (!store.user?.is_active) return 'Cuenta no activa'
  if (!selectedAddressId.value) return 'Selecciona una direccion'
  if (cart.count < 1) return 'Carrito vacio'
  return baseLabel
}

const loadAddresses = async () => {
  const res = await api.get('/api/user/addresses')
  addresses.value = res.data || []
  if (!selectedAddressId.value && addresses.value.length) {
    selectedAddressId.value = addresses.value[0].id
  }
}

const createAddress = async () => {
  const res = await api.post('/api/user/addresses', addressForm.value)
  await loadAddresses()
  if (res?.id) selectedAddressId.value = res.id
  addressForm.value = { country: '', city: '', postal_code: '', address_line: '' }
}

const createOrder = async (method) => {
  const response = await api.post('/api/orders', {
    payment_method: method,
    coupon_code: coupon.value,
    user_email: store.user?.email,
    address_id: selectedAddressId.value,
  })

  if (response?.status !== 'success' || !response?.order_id) {
    throw new Error('No se pudo confirmar la creacion del pedido')
  }

  return response.order_id
}

const placeCashOrder = async () => {
  if (isActionDisabled.value || loading.value) return

  loading.value = true
  checkoutMessage.value = ''

  try {
    const orderId = await createOrder('cash_on_delivery')
    cart.clear()
    await router.push('/mis-pedidos')
    alert(`Pedido #${orderId} creado con exito`)
  } catch (err) {
    alert(err.message || 'Error al procesar el pedido')
  } finally {
    loading.value = false
  }
}

const payWithCard = async () => {
  if (isActionDisabled.value || loading.value) return

  loading.value = true
  checkoutMessage.value = 'Creando pedido y preparando la pasarela segura...'

  try {
    const orderId = await createOrder('card_online')
    const session = await api.post('/api/checkout/create-session', { order_id: orderId })

    if (!session?.payment_url) {
      throw new Error('No se pudo obtener la URL de pago')
    }

    window.location.assign(session.payment_url)
  } catch (err) {
    checkoutMessage.value = ''
    loading.value = false
    alert(err.message || 'Error al procesar el pedido')
  }
}

onMounted(loadAddresses)
</script>

<style scoped>
.section { padding: 40px 8% 40px; }
.activation-warning {
  background: #fff3cd;
  color: #856404;
  padding: 15px;
  border-radius: 8px;
  border: 1px solid #ffeeba;
  font-weight: 600;
}
.inactive-user { opacity: 0.6; pointer-events: none; }
.checkout-grid { display: grid; grid-template-columns: 1fr 350px; gap: 30px; margin-top: 20px; }
.card { background: #f8fafc; padding: 25px; border-radius: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
.radio { display: block; margin-bottom: 10px; cursor: pointer; }
.btn-confirm {
  width: 100%;
  padding: 15px;
  background: #ff9f43;
  color: white;
  border: none;
  border-radius: 10px;
  font-weight: 700;
  cursor: pointer;
}
.btn-card {
  background: linear-gradient(135deg, #0ea5e9, #10b981);
}
.btn-confirm:disabled { background: #cbd5e1; cursor: not-allowed; }
.btn-secondary {
  width: 100%;
  padding: 12px;
  border-radius: 10px;
  border: 1px solid #cbd5e1;
  background: white;
  cursor: pointer;
  font-weight: 700;
}
.address-form {
  display: grid;
  grid-template-columns: 1fr;
  gap: 10px;
  margin-top: 12px;
}
.muted { opacity: 0.7; }

@media (max-width: 900px) {
  .checkout-grid {
    grid-template-columns: 1fr;
  }
}
</style>
