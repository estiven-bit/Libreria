import { defineStore } from 'pinia'
import { computed, ref, watch } from 'vue'

const STORAGE_KEY = 'cart_items'

function loadInitial() {
  try {
    return JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]')
  } catch {
    return []
  }
}

export const useCartStore = defineStore('cart', () => {
  const items = ref(loadInitial())

  watch(
    items,
    (v) => {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(v))
    },
    { deep: true },
  )

  const count = computed(() => items.value.reduce((sum, it) => sum + Number(it.quantity || 0), 0))
  const total = computed(() =>
    items.value.reduce((sum, it) => sum + Number(it.price || 0) * Number(it.quantity || 0), 0),
  )

  function setItems(next) {
    items.value = Array.isArray(next) ? next : []
  }

  function addItem(item) {
    const id = item?.id
    if (!id) return

    const qty = Math.max(1, Number(item.quantity || 1))
    const existing = items.value.find((i) => i.id === id)
    if (existing) existing.quantity += qty
    else items.value.push({ ...item, quantity: qty })
  }

  function setQuantity(id, quantity) {
    const q = Math.max(1, Number(quantity || 1))
    const existing = items.value.find((i) => i.id === id)
    if (existing) existing.quantity = q
  }

  function inc(id) {
    const existing = items.value.find((i) => i.id === id)
    if (existing) existing.quantity = Math.max(1, Number(existing.quantity || 1) + 1)
  }

  function dec(id) {
    const existing = items.value.find((i) => i.id === id)
    if (existing) existing.quantity = Math.max(1, Number(existing.quantity || 1) - 1)
  }

  function remove(id) {
    items.value = items.value.filter((i) => i.id !== id)
  }

  function clear() {
    items.value = []
  }

  return { items, count, total, setItems, addItem, setQuantity, inc, dec, remove, clear }
})

