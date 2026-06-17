<template>
  <div class="app-shell">
    <NavBar />
    <main class="page">
      <router-view v-slot="{ Component }">
        <transition name="fade" mode="out-in">
          <component :is="Component" />
        </transition>
      </router-view>
    </main>
    <FooterBar />
    <ToastStack />
  </div>
</template>

<script setup>
import { inject, onMounted } from 'vue'
import { api } from './services/api'
import NavBar from './components/NavBar.vue'
import FooterBar from './components/FooterBar.vue'
import ToastStack from './components/ToastStack.vue'
import { useCartStore } from './stores/cart'
import { useAuthStore } from './stores/auth'

const store = inject('store')
const cart = useCartStore()

onMounted(async () => {
  // Precalentar el BFF para reducir cold start al hacer login
  fetch(`${api.BFF_BASE}/bff/health`, { method: 'GET' }).catch(() => {})

  // Evitamos la condición de carrera si estamos en la página del callback OIDC
  if (window.location.pathname !== '/oidc-callback' && !window.location.pathname.startsWith('/oidc-callback')) {
    const auth = useAuthStore()
    await auth.hydrate()
    if (store.user) {
      try {
        const res = await api.get('/api/cart')
        const items = (res.items || []).map((item) => {
          const imageId = Number(item.primary_image_id || 0)
          return {
            id: item.product_id,
            name: item.name,
            price: Number(item.price),
            quantity: Number(item.quantity),
            image_url: imageId > 0 ? `/api/products/${item.product_id}/images/${imageId}` : null,
          }
        })
        cart.setItems(items)
      } catch (error) {
        // fallback: mantener carrito local
      }
    }
  }
})
</script>
