<template>
  <div class="product-card">
    <div class="product-image">
      <img :src="image" alt="producto" />
    </div>
    <div class="product-info">
      <h3>{{ product.name }}</h3>
      <p class="price">${{ product.price }}</p>
      <RouterLink :to="`/product/${slug}`" class="btn">Ver detalle</RouterLink>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
const props = defineProps({
  product: { type: Object, required: true },
})

const image = computed(() => props.product.image_url || 'https://via.placeholder.com/260x180')
const slug = computed(() => {
  const base = props.product.name.toLowerCase().replace(/\\s+/g, '-')
  return props.product.id ? `${props.product.id}-${base}` : base
})
</script>
