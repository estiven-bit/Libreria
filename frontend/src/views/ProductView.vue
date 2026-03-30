<template>
  <section class="section" v-if="product">
    <div class="product-detail">
      <img :src="image" alt="producto" />
      <div>
        <h2>{{ product.name }}</h2>
        <p>{{ product.description }}</p>
        <p class="price">${{ product.price }}</p>
        <div class="row">
          <input class="input" type="number" min="1" v-model.number="quantity" />
          <button class="btn" @click="addToCart">Agregar al carrito</button>
        </div>
      </div>
    </div>
  </section>
  <section v-else class="section">
    <p>Cargando producto...</p>
  </section>
</template>

<script setup>
import { onMounted, ref, computed, inject } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '../services/api'

const store = inject('store')
const route = useRoute()
const product = ref(null)
const quantity = ref(1)

const image = computed(() => product.value?.image_url || 'https://via.placeholder.com/420x300')

const addToCart = () => {
  if (!product.value) return
  store.addToCart({
    id: product.value.id,
    name: product.value.name,
    price: Number(product.value.price),
    quantity: quantity.value,
  })
  if (store.user) {
    api.post('/api/cart/add', { product_id: product.value.id, quantity: quantity.value })
  }
}

onMounted(async () => {
  const slug = route.params.slug || ''
  const id = Number(String(slug).split('-')[0])
  if (Number.isNaN(id)) return
  const res = await api.get(`/api/products/${id}`)
  product.value = res.data
})
</script>
