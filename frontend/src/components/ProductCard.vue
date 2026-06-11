<template>
  <div class="product-card">
    <div class="product-image">
      <img :src="image" :alt="product.name" />
    </div>
    <div class="product-info">
      <h3>{{ product.name }}</h3>
      <p class="price">${{ Number(product.price).toFixed(2) }}</p>
      <RouterLink :to="`/producto/${product.id}`" class="btn btn-detail">Ver detalle</RouterLink>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { api } from '../services/api'
import placeholderImage from '../assets/img/placeholder.png'
const props = defineProps({
  product: { type: Object, required: true },
})

const image = computed(() => api.mediaUrl(props.product.image_url) || placeholderImage)
</script>

<style scoped>
.product-card {
  display: flex;
  align-items: center;
  gap: 1.25rem;
  padding: 1rem 1.25rem;
  background: rgba(30, 41, 59, 0.45);
  backdrop-filter: blur(14px);
  -webkit-backdrop-filter: blur(14px);
  border-radius: 14px;
  border: 1px solid rgba(255, 255, 255, 0.12);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}

.product-image {
  flex: 0 0 100px;
  width: 100px;
  height: 130px;
  border-radius: 10px;
  overflow: hidden;
  background: rgba(15, 23, 42, 0.5);
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.product-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.product-info {
  flex: 1;
  min-width: 0;
}

.product-info h3 {
  margin: 0 0 0.35rem;
  font-size: 1.05rem;
  color: #ffffff;
  line-height: 1.3;
}

.price {
  margin: 0 0 0.75rem;
  font-size: 1.1rem;
  font-weight: 800;
  color: #ff9f43;
}

.btn-detail {
  padding: 8px 18px;
  font-size: 0.85rem;
}

@media (max-width: 480px) {
  .product-image {
    flex: 0 0 72px;
    width: 72px;
    height: 96px;
  }

  .product-info h3 {
    font-size: 0.95rem;
  }
}
</style>
