<template>
  <div class="app-shell">
    <NavBar />
    <main class="page">
      <router-view />
    </main>
    <FooterBar />
  </div>
</template>

<script setup>
import { inject, onMounted } from 'vue'
import { api } from './services/api'
import NavBar from './components/NavBar.vue'
import FooterBar from './components/FooterBar.vue'

const store = inject('store')

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
      store.setCart(items)
    } catch (error) {
      // fallback: mantener carrito local
    }
  }
})
</script>
