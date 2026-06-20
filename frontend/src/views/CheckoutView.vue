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
          <h3>Dirección de envío</h3>
          
          <h4 class="checkout-subtitle">Añadir nueva</h4>
          <div class="address-form">
            <input v-model="addressForm.country" class="input" placeholder="País" />
            <input v-model="addressForm.city" class="input" placeholder="Ciudad" />
            <input v-model="addressForm.postal_code" class="input" placeholder="Código postal" />
            <input v-model="addressForm.address_line" class="input" placeholder="Dirección" />
            <button class="btn-secondary" type="button" @click="createAddress">Guardar dirección</button>
          </div>

          <h4 class="checkout-subtitle" style="margin-top: 25px;">Direcciones guardadas</h4>
          <div v-if="addresses.length" class="saved-addresses-grid">
            <div 
              v-for="a in addresses" 
              :key="a.id" 
              class="address-card" 
              :class="{ active: selectedAddressId === a.id }"
              @click="selectedAddressId = a.id"
            >
              <div class="address-card-header">
                <span class="address-check"></span>
                <span class="address-city">{{ a.city }}</span>
              </div>
              <div class="address-card-body">
                <p class="address-line">{{ a.address_line }}</p>
                <p class="address-zip">{{ a.postal_code }} {{ a.country ? `- ${a.country}` : '' }}</p>
              </div>
            </div>
          </div>
          <p v-else class="muted" style="margin-top: 10px; text-align: center;">No tienes direcciones guardadas. Crea una arriba.</p>
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

          <div class="coupon-title">
            <span>Si tienes algún cupón de descuento, añádelo:</span>
          </div>
          <div class="coupon-section">
            <input v-model="coupon" class="input coupon-input" placeholder="Codigo de cupon" />
            <button class="btn btn-apply-coupon" type="button" @click="applyCoupon">Aplicar</button>
          </div>
          <p v-if="couponSuccess" class="coupon-success">{{ couponSuccess }}</p>
          <p v-if="couponError" class="coupon-error">{{ couponError }}</p>
        </div>

        <div class="card summary-card">
          <h3>Resumen</h3>
          
          <div class="summary-items-list">
            <div v-for="item in cart.items" :key="item.id" class="summary-item-row">
              <div class="summary-item-image">
                <img :src="api.mediaUrl(item.image_url) || placeholderImage" :alt="item.name" />
              </div>
              <div class="summary-item-details">
                <h4 class="summary-item-name" :title="item.name">{{ item.name }}</h4>
                <p class="summary-item-meta">Cant: {{ item.quantity }} × ${{ Number(item.price).toFixed(2) }}</p>
              </div>
              <div class="summary-item-total">
                ${{ (Number(item.price) * Number(item.quantity)).toFixed(2) }}
              </div>
            </div>
          </div>

          <div class="summary-divider"></div>

          <div class="summary-totals">
            <div class="totals-row">
              <span>Artículos:</span>
              <span>{{ cart.count }}</span>
            </div>
            <div class="totals-row total-final">
              <span>Total:</span>
              <template v-if="appliedCoupon">
                <div class="totals-with-coupon">
                  <span class="old-total">${{ Number(cart.total).toFixed(2) }}</span>
                  <strong class="new-total">${{ Number(finalTotal).toFixed(2) }}</strong>
                </div>
              </template>
              <template v-else>
                <strong>${{ Number(cart.total).toFixed(2) }}</strong>
              </template>
            </div>
          </div>

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
import { useToastStore } from '../stores/toast'
import placeholderImage from '../assets/img/placeholder.png'

const store = inject('store')
const cart = useCartStore()
const router = useRouter()
const toast = useToastStore()

const paymentMethod = ref('card_online')
const coupon = ref('')
const addresses = ref([])
const selectedAddressId = ref(null)
const addressForm = ref({ country: '', city: '', postal_code: '', address_line: '' })
const loading = ref(false)
const checkoutMessage = ref('')

const appliedCoupon = ref(null)
const couponError = ref('')
const couponSuccess = ref('')

const finalTotal = computed(() => {
  const base = Number(cart.total)
  if (appliedCoupon.value) {
    return base - (base * (appliedCoupon.value.discount_percentage / 100))
  }
  return base
})

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
  if (!addressForm.value.address_line || !addressForm.value.city) {
    return toast.error('Completa los campos obligatorios: dirección y ciudad')
  }
  try {
    const res = await api.post('/api/user/addresses', addressForm.value)
    await loadAddresses()
    if (res?.id) selectedAddressId.value = res.id
    addressForm.value = { country: '', city: '', postal_code: '', address_line: '' }
    toast.success('Dirección guardada correctamente')
  } catch (err) {
    toast.error(err.message || 'Error al guardar la dirección')
  }
}

const applyCoupon = async () => {
  couponError.value = ''
  couponSuccess.value = ''
  appliedCoupon.value = null

  if (!coupon.value.trim()) {
    return
  }

  try {
    const res = await api.get('/api/coupons/active')
    const list = res.data || []
    const found = list.find(c => c.code.toLowerCase() === coupon.value.trim().toLowerCase())
    if (found) {
      appliedCoupon.value = found
      couponSuccess.value = `¡Cupón "${found.code}" del ${found.discount_percentage}% aplicado con éxito!`
      toast.success(couponSuccess.value)
    } else {
      couponError.value = 'El cupón introducido no es válido o está inactivo.'
      toast.error(couponError.value)
    }
  } catch (err) {
    couponError.value = 'Error al validar el cupón.'
    console.error(err)
  }
}

const createOrder = async (method) => {
  const response = await api.post('/api/orders', {
    payment_method: method,
    coupon_code: appliedCoupon.value ? appliedCoupon.value.code : '',
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
    toast.success(`¡Pedido #${orderId} creado con éxito!`)
  } catch (err) {
    toast.error(err.message || 'Error al procesar el pedido')
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
    toast.error(err.message || 'Error al procesar el pedido')
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

.coupon-section {
  display: flex;
  gap: 8px;
  margin-top: 10px;
}
.coupon-input {
  flex: 1;
  margin-bottom: 0;
}
.btn-apply-coupon {
  padding: 0 15px;
  background: #1a233d;
  color: white;
  border: none;
  border-radius: 10px;
  font-weight: bold;
  cursor: pointer;
}
.coupon-success {
  color: #166534;
  font-size: 0.82rem;
  font-weight: bold;
  margin: 6px 0 0;
}
.coupon-error {
  color: #b91c1c;
  font-size: 0.82rem;
  font-weight: bold;
  margin: 6px 0 0;
}
.old-total {
  text-decoration: line-through;
  opacity: 0.6;
  margin-right: 8px;
}

@media (max-width: 900px) {
  .checkout-grid {
    grid-template-columns: 1fr;
  }
}

.coupon-title {
  margin-top: 15px;
  font-size: 0.88rem;
  font-weight: 600;
  color: #475569;
}

/* Shipping Address Refinements */
.checkout-subtitle {
  font-size: 1.05rem;
  font-weight: 700;
  color: #1a233d;
  margin: 20px 0 10px;
  border-bottom: 1px dashed #e2e8f0;
  padding-bottom: 6px;
}

.saved-addresses-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 15px;
  margin-top: 12px;
}

.address-card {
  background: white;
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  padding: 15px;
  cursor: pointer;
  transition: all 0.25s ease;
  position: relative;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}

.address-card:hover {
  border-color: #cbd5e1;
  transform: translateY(-2px);
  box-shadow: 0 4px 10px rgba(0,0,0,0.05);
}

.address-card.active {
  border-color: #ff9f43;
  background: #fffdf9;
  box-shadow: 0 4px 12px rgba(255, 159, 67, 0.15);
}

.address-card-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 8px;
}

.address-check {
  width: 18px;
  height: 18px;
  border-radius: 50%;
  border: 2px solid #cbd5e1;
  display: inline-block;
  position: relative;
  flex-shrink: 0;
  transition: all 0.2s ease;
}

.address-card.active .address-check {
  border-color: #ff9f43;
  background: #ff9f43;
}

.address-card.active .address-check::after {
  content: '';
  position: absolute;
  top: 4px;
  left: 4px;
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: white;
}

.address-city {
  font-weight: 700;
  font-size: 0.95rem;
  color: #1e293b;
  text-transform: capitalize;
}

.address-card-body {
  font-size: 0.85rem;
  color: #64748b;
  line-height: 1.4;
}

.address-line {
  margin: 0;
  font-weight: 500;
  color: #334155;
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.address-zip {
  margin: 4px 0 0;
  font-size: 0.8rem;
  opacity: 0.85;
}

/* Checkout Summary Card Item Breakdown */
.summary-card {
  display: flex;
  flex-direction: column;
}

.summary-items-list {
  max-height: 240px;
  overflow-y: auto;
  margin: 15px 0;
  padding-right: 5px;
}

.summary-items-list::-webkit-scrollbar {
  width: 4px;
}
.summary-items-list::-webkit-scrollbar-track {
  background: rgba(0, 0, 0, 0.05);
  border-radius: 4px;
}
.summary-items-list::-webkit-scrollbar-thumb {
  background: rgba(0, 0, 0, 0.15);
  border-radius: 4px;
}

.summary-item-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 0;
  border-bottom: 1px solid #f1f5f9;
}

.summary-item-row:last-child {
  border-bottom: none;
}

.summary-item-image {
  width: 40px;
  height: 54px;
  border-radius: 6px;
  overflow: hidden;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  flex-shrink: 0;
}

.summary-item-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.summary-item-details {
  flex: 1;
  min-width: 0;
}

.summary-item-name {
  font-size: 0.88rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 2px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.summary-item-meta {
  font-size: 0.78rem;
  color: #64748b;
  margin: 0;
}

.summary-item-total {
  font-size: 0.88rem;
  font-weight: 700;
  color: #334155;
  flex-shrink: 0;
}

.summary-divider {
  height: 1px;
  background: #e2e8f0;
  margin: 15px 0;
}

.summary-totals {
  margin-bottom: 20px;
}

.totals-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.9rem;
  color: #64748b;
  margin-bottom: 8px;
}

.total-final {
  font-size: 1.15rem;
  color: #1e293b;
  font-weight: 800;
  margin-top: 12px;
  margin-bottom: 0;
}

.totals-with-coupon {
  display: flex;
  align-items: center;
  gap: 8px;
}

.new-total {
  color: #ff9f43;
}
</style>
