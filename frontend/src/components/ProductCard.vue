<template>
  <div class="product-card">
    <div class="product-image">
      <img :src="image" alt="producto" />
    </div>
    <div class="product-info">
      <h3>{{ product.name }}</h3>
      <p class="price">${{ product.price }}</p>
      <RouterLink :to="`/producto/${product.id}`" class="btn">Ver detalle</RouterLink>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import placeholderImage from '../assets/img/placeholder.png'
const props = defineProps({
  product: { type: Object, required: true },
})

const image = computed(() => props.product.image_url || placeholderImage)
</script>
