import { reactive, computed } from 'vue'

// 1. Recuperar estado inicial de localStorage
// Usamos nombres simples para que coincidan con la respuesta del servidor
const savedCart = JSON.parse(localStorage.getItem('cart_items') || '[]')
const savedUser = JSON.parse(localStorage.getItem('user') || 'null')
const savedToken = localStorage.getItem('token')
const savedLang = localStorage.getItem('lang') || 'es'

const i18n = {
  es: {
    catalog: 'Catálogo',
    cart: 'Carrito',
    checkout: 'Checkout',
    login: 'Login',
    register: 'Registro',
  },
  en: {
    catalog: 'Catalog',
    cart: 'Cart',
    checkout: 'Checkout',
    login: 'Login',
    register: 'Register',
  },
}

export const store = reactive({
  // Estado
  user: savedUser,
  token: savedToken,
  cart: savedCart,
  lang: savedLang,

  // Traducciones
  t(key) {
    return i18n[this.lang]?.[key] || key
  },

  setLang(lang) {
    this.lang = lang
    localStorage.setItem('lang', lang)
  },

  // Autenticación (Login / Activación exitosa)
  setAuth(user, token) {
    this.user = user
    this.token = token
    localStorage.setItem('user', JSON.stringify(user))
    localStorage.setItem('token', token)
  },

  // Actualizar solo el estado de activación
  setActivationStatus(status) {
    if (this.user) {
      this.user.is_active = status ? 1 : 0
      localStorage.setItem('user', JSON.stringify(this.user))
    }
  },

  logout() {
    this.user = null
    this.token = null
    localStorage.removeItem('user')
    localStorage.removeItem('token')
    // Opcional: localStorage.removeItem('cart_items')
  },

  // Lógica del Carrito
  saveCart() {
    localStorage.setItem('cart_items', JSON.stringify(this.cart))
  },

  addToCart(item) {
    const existing = this.cart.find((i) => i.id === item.id)
    if (existing) {
      existing.quantity += item.quantity
    } else {
      this.cart.push({ ...item })
    }
    this.saveCart()
  },

  setCart(items) {
    this.cart = items
    this.saveCart()
  },

  updateCart(itemId, quantity) {
    const existing = this.cart.find((i) => i.id === itemId)
    if (existing) {
      existing.quantity = quantity
    }
    this.saveCart()
  },

  removeFromCart(itemId) {
    this.cart = this.cart.filter((i) => i.id !== itemId)
    this.saveCart()
  },

  clearCart() {
    this.cart = []
    this.saveCart()
  },

  // Total computado (usando la referencia al objeto reactivo)
  cartTotal: computed(() => {
    return store.cart.reduce((sum, item) => sum + (item.price * item.quantity), 0)
  }),
})