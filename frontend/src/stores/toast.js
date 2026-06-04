import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useToastStore = defineStore('toast', () => {
  const items = ref([])
  let seq = 0

  function remove(id) {
    items.value = items.value.filter((t) => t.id !== id)
  }

  function push(message, type = 'info', ms = 3800) {
    const id = ++seq
    items.value.push({ id, message, type })
    window.setTimeout(() => remove(id), ms)
    return id
  }

  return {
    items,
    success: (m) => push(m, 'success'),
    error: (m) => push(m, 'error'),
    info: (m) => push(m, 'info'),
    remove,
  }
})
