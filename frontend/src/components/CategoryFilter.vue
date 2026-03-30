<template>
  <div class="category-filter">
    <button
      v-for="cat in categories"
      :key="cat.id"
      class="chip"
      :class="{ active: cat.id === selected }"
      @click="$emit('select', cat.id)"
    >
      {{ cat.name }}
    </button>
  </div>
</template>

<script setup>
defineProps({
  categories: { type: Array, default: () => [] },
  selected: { type: Number, default: null },
})
</script>
