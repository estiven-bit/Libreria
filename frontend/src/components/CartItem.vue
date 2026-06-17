<template>
  <div class="cart-item">
    <div class="cart-item-image">
      <img :src="imageUrl" :alt="item.name" />
    </div>
    <div class="cart-item-details">
      <h4>{{ item.name }}</h4>
      <p class="price">${{ Number(item.price).toFixed(2) }}</p>
      <p class="muted">Subtotal: ${{ (Number(item.price) * Number(item.quantity)).toFixed(2) }}</p>
    </div>
    <div class="cart-actions">
      <div class="qty">
        <button class="btn ghost" type="button" @click="$emit('dec')">-</button>
        <span class="qty-val">{{ item.quantity }}</span>
        <button class="btn ghost" type="button" @click="$emit('inc')">+</button>
      </div>
      <button class="btn ghost btn-delete" type="button" @click="$emit('remove')">Eliminar</button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { api } from '../services/api'
import placeholderImage from '../assets/img/placeholder.png'

const props = defineProps({
  item: { type: Object, required: true },
})

const imageUrl = computed(() => api.mediaUrl(props.item.image_url) || placeholderImage)
</script>

<style scoped>
.cart-item {
  display: flex;
  align-items: center;
  gap: 15px;
  width: 100%;
}
.cart-item-image {
  flex: 0 0 70px;
  width: 70px;
  height: 90px;
  border-radius: 10px;
  overflow: hidden;
  background: rgba(15, 23, 42, 0.5);
  border: 1px solid rgba(255, 255, 255, 0.15);
}
.cart-item-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.cart-item-details {
  flex: 1;
  min-width: 0;
}
.cart-item-details h4 {
  margin: 0 0 4px;
  font-size: 1.1rem;
  color: white;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  font-weight: 700;
}
.price {
  margin: 0 0 4px;
  color: #ff9f43;
  font-weight: 800;
  font-size: 1.05rem;
}
.muted {
  opacity: 0.8;
  font-size: 0.85rem;
  margin: 0;
  color: #e2e8f0;
}
.cart-actions {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 8px;
}
.qty {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.qty-val {
  min-width: 24px;
  text-align: center;
  font-weight: 800;
}
.btn.ghost {
  background: rgba(255, 255, 255, 0.1);
  color: white;
  border: 1px solid rgba(255, 255, 255, 0.3);
  padding: 6px 12px;
  border-radius: 8px;
  cursor: pointer;
  font-size: 0.85rem;
  transition: all 0.2s;
  font-weight: 700;
}
.btn.ghost:hover {
  background: rgba(255, 255, 255, 0.25);
  border-color: white;
}
.btn-delete {
  color: #ff6b6b !important;
  border-color: rgba(255, 107, 107, 0.4) !important;
}
.btn-delete:hover {
  background: rgba(255, 107, 107, 0.2) !important;
  border-color: #ff6b6b !important;
}
@media (max-width: 600px) {
  .cart-item {
    flex-wrap: wrap;
    gap: 10px;
  }
  .cart-actions {
    flex-direction: row;
    align-items: center;
    width: 100%;
    justify-content: space-between;
    margin-top: 10px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    padding-top: 10px;
  }
}
</style>
