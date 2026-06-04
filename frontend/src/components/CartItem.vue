<template>
  <div class="cart-item">
    <div>
      <h4>{{ item.name }}</h4>
      <p>${{ item.price }}</p>
      <p class="muted">Subtotal: ${{ (Number(item.price) * Number(item.quantity)).toFixed(2) }}</p>
    </div>
    <div class="cart-actions">
      <div class="qty">
        <button class="btn ghost" type="button" @click="$emit('dec')">-</button>
        <span class="qty-val">{{ item.quantity }}</span>
        <button class="btn ghost" type="button" @click="$emit('inc')">+</button>
      </div>
      <button class="btn ghost" @click="$emit('remove')">Eliminar</button>
    </div>
  </div>
</template>

<script setup>
defineProps({
  item: { type: Object, required: true },
})
</script>

<style scoped>
.muted {
  opacity: 0.8;
  font-size: 0.9rem;
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
</style>
