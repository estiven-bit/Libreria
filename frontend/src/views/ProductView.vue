<template>
  <div>
    <section class="section product-page" v-if="error">
      <p class="msg-error">{{ error }}</p>
      <RouterLink to="/catalogo" class="btn">Volver al catalogo</RouterLink>
    </section>

    <section class="section product-page" v-else-if="product">
      <div class="product-detail">
        <div class="gallery">
          <div class="main-image-wrap">
            <button
              v-if="canNavigate"
              type="button"
              class="gallery-arrow gallery-arrow--prev"
              aria-label="Imagen anterior"
              @click="prevImage"
            >
              ‹
            </button>
            <img class="main-img" :src="mainImage" :alt="product.name" />
            <button
              v-if="canNavigate"
              type="button"
              class="gallery-arrow gallery-arrow--next"
              aria-label="Imagen siguiente"
              @click="nextImage"
            >
              ›
            </button>
          </div>
          <div v-if="thumbnails.length > 1" class="thumbs">
            <button
              v-for="(img, idx) in thumbnails"
              :key="idx"
              type="button"
              class="thumb"
              :class="{ active: currentImageIndex === idx }"
              @click="currentImageIndex = idx"
            >
              <img :src="img" alt="" />
            </button>
          </div>
        </div>
        <div class="detail-body">
          <p v-if="product.category?.name" class="category-pill">{{ product.category.name }}</p>
          <h2>{{ product.name }}</h2>
          <p class="description">{{ product.description }}</p>
          <p class="price">${{ Number(product.price).toFixed(2) }}</p>
          <p v-if="product.stock != null" class="stock">Stock: {{ product.stock }}</p>
          <div class="row">
            <input v-model.number="quantity" class="input" type="number" min="1" />
            <button class="btn" type="button" @click="addToCart">Agregar al carrito</button>
          </div>
        </div>
      </div>
    </section>

    <section v-else class="section product-page">
      <p>Cargando producto...</p>
    </section>
  </div>
</template>

<script setup>
import { computed, inject, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '../services/api'
import { useCartStore } from '../stores/cart'
import { useToastStore } from '../stores/toast'
import placeholderImage from '../assets/img/placeholder.png'

const legacyStore = inject('store')
const toast = useToastStore()
const route = useRoute()
const cart = useCartStore()
const product = ref(null)
const error = ref('')
const quantity = ref(1)
const currentImageIndex = ref(0)

const thumbnails = computed(() => {
  const imgs = product.value?.images
  if (Array.isArray(imgs) && imgs.length) {
    return imgs.map((i) => api.mediaUrl(i.image_url)).filter(Boolean)
  }
  if (product.value?.image_url) {
    return [api.mediaUrl(product.value.image_url)]
  }
  return [placeholderImage]
})

const canNavigate = computed(() => thumbnails.value.length > 1)

const mainImage = computed(() => {
  const list = thumbnails.value
  if (!list.length) return placeholderImage
  const idx = Math.min(currentImageIndex.value, list.length - 1)
  return list[idx]
})

const prevImage = () => {
  const n = thumbnails.value.length
  if (n <= 1) return
  currentImageIndex.value = (currentImageIndex.value - 1 + n) % n
}

const nextImage = () => {
  const n = thumbnails.value.length
  if (n <= 1) return
  currentImageIndex.value = (currentImageIndex.value + 1) % n
}

const productId = computed(() => {
  if (route.name === 'producto' && route.params.id != null) {
    const id = Number(route.params.id)
    return Number.isNaN(id) ? null : id
  }
  const slug = route.params.slug || ''
  const id = Number(String(slug).split('-')[0])
  return Number.isNaN(id) ? null : id
})

watch(
  () => product.value,
  (p) => {
    if (!p) return
    currentImageIndex.value = 0
  },
)

async function load() {
  error.value = ''
  product.value = null
  const id = productId.value
  if (id == null) {
    error.value = 'Producto no valido.'
    return
  }
  try {
    const res = await api.get(`/api/products/${id}`)
    product.value = res.data
  } catch (e) {
    error.value = e.message || 'No se pudo cargar el producto.'
  }
}

const addToCart = () => {
  if (!product.value) return
  cart.addItem({
    id: product.value.id,
    name: product.value.name,
    price: Number(product.value.price),
    quantity: quantity.value,
    image_url: mainImage.value,
  })
  if (legacyStore.user) {
    api.post('/api/cart/add', { product_id: product.value.id, quantity: quantity.value }).catch(() => {})
  }
  toast.success('Producto anadido al carrito')
}

onMounted(load)
watch(productId, () => load())
</script>

<style scoped>
.product-page {
  max-width: 1100px;
  margin: 0 auto;
  padding: 2rem 5% 4rem;
  min-height: calc(100vh - 88px - 340px); /* 88px header padding, 340px footer approx */
  display: flex;
  flex-direction: column;
  justify-content: center;
  box-sizing: border-box;
}
.product-detail {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 2rem;
  align-items: start;
  width: 100%;
}
.gallery {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.main-image-wrap {
  position: relative;
  width: 100%;
  aspect-ratio: 4 / 3;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 16px;
  background: #f1f5f9;
  overflow: hidden;
}
.main-img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  display: block;
}
.gallery-arrow {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  z-index: 2;
  width: 44px;
  height: 44px;
  border: none;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.92);
  color: #1a233d;
  font-size: 1.75rem;
  line-height: 1;
  cursor: pointer;
  box-shadow: 0 2px 12px rgba(15, 23, 42, 0.15);
  transition: background 0.2s, transform 0.2s;
}
.gallery-arrow:hover {
  background: #fff;
  transform: translateY(-50%) scale(1.05);
}
.gallery-arrow--prev {
  left: 12px;
}
.gallery-arrow--next {
  right: 12px;
}
.thumbs {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.thumb {
  padding: 0;
  border: 2px solid transparent;
  border-radius: 10px;
  overflow: hidden;
  cursor: pointer;
  background: none;
  width: 64px;
  height: 64px;
}
.thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.thumb.active {
  border-color: #ff9f43;
}
.detail-body h2 {
  font-size: 1.75rem;
  margin: 0 0 12px;
}
.category-pill {
  display: inline-block;
  background: #1a233d;
  color: #ff9f43;
  font-size: 0.75rem;
  font-weight: 800;
  text-transform: uppercase;
  padding: 6px 12px;
  border-radius: 999px;
  margin-bottom: 10px;
}
.description {
  color: #475569;
  margin-bottom: 16px;
}
.price {
  font-size: 1.5rem;
  font-weight: 800;
  color: #ff6b6b;
  margin-bottom: 8px;
}
.stock {
  font-size: 0.9rem;
  color: #64748b;
  margin-bottom: 16px;
}
.row {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: center;
}
.row .input {
  max-width: 100px;
  margin-bottom: 0;
}
.msg-error {
  color: #b91c1c;
  margin-bottom: 16px;
}
@media (max-width: 768px) {
  .product-detail {
    grid-template-columns: 1fr;
  }
  .main-image-wrap {
    aspect-ratio: 1 / 1;
  }
  .gallery-arrow {
    width: 36px;
    height: 36px;
    font-size: 1.4rem;
  }
  .gallery-arrow--prev {
    left: 6px;
  }
  .gallery-arrow--next {
    right: 6px;
  }
}
</style>
