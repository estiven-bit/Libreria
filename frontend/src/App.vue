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

const store = inject('store')
const cart = useCartStore()

onMounted(async () => {
  if (store.user) {
    try {
      const res = await api.get('/api/cart')
      const items = (res.items || []).map((item) => ({
        id: item.product_id,
        name: item.name,
        price: Number(item.price),
        quantity: Number(item.quantity),
      }))
      cart.setItems(items)
    } catch (error) {
      // fallback: mantener carrito local
    }
  }
})
</script>
