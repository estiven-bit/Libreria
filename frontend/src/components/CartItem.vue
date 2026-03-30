<template>
  <div class="cart-item">
    <div>
      <h4>{{ item.name }}</h4>
      <p>${{ item.price }}</p>
    </div>
    <div class="cart-actions">
      <input type="number" min="1" :value="item.quantity" @change="$emit('update', Number($event.target.value))" />
      <button class="btn ghost" @click="$emit('remove')">Eliminar</button>
    </div>
  </div>
</template>

<script setup>
defineProps({
  item: { type: Object, required: true },
})
</script>
