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
          <div v-if="legacyStore.user" class="row">
            <input v-model.number="quantity" class="input" type="number" min="1" />
            <button class="btn" type="button" @click="addToCart">Agregar al carrito</button>
          </div>
        </div>
      </div>

      <!-- Sección de valoraciones y opiniones -->
      <div class="reviews-section card">
        <h3>Opiniones de los lectores</h3>
        
        <div v-if="reviews.length === 0" class="no-reviews">
          <p>Nadie ha valorado este libro todavía. ¡Sé el primero en opinar!</p>
        </div>

        <div v-else class="reviews-list">
          <div v-for="r in reviews" :key="r.id" class="review-item">
            <div class="review-header">
              <span class="reviewer-name">{{ r.user_name }}</span>
              <span v-if="r.verified_purchase" class="verified-badge">Compra verificada</span>
              <span class="review-date">{{ new Date(r.created_at).toLocaleDateString('es-ES') }}</span>
            </div>
            <div class="stars">
              <span v-for="star in 5" :key="star" :class="['star', { active: star <= r.rating }]">★</span>
            </div>
            <p class="review-text">{{ r.comment }}</p>
          </div>
        </div>

        <div class="review-form-wrapper">
          <h4 v-if="legacyStore.user">Escribe tu opinión</h4>
          <form v-if="legacyStore.user" @submit.prevent="submitReview" class="review-form">
            <div class="rating-select-wrapper">
              <label>Valoración:</label>
              <div class="star-rating-selector">
                <button 
                  v-for="star in 5" 
                  :key="star" 
                  type="button"
                  class="selector-star-btn"
                  @click="rating = star"
                >
                  <span :class="['selector-star', { active: star <= rating }]">★</span>
                </button>
              </div>
            </div>
            
            <div class="form-group">
              <textarea 
                v-model="comment" 
                class="input review-textarea" 
                placeholder="Escribe aquí tu reseña sobre el libro..." 
                rows="3"
                required
              ></textarea>
            </div>

            <button type="submit" class="btn btn-submit-review" :disabled="submitting">
              {{ submitting ? 'Enviando...' : 'Publicar opinión' }}
            </button>
          </form>
          <div v-else class="login-prompt">
            <p>Debes <RouterLink to="/login">iniciar sesión</RouterLink> para dejar tu opinión sobre este libro.</p>
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

const reviews = ref([])
const rating = ref(5)
const comment = ref('')
const submitting = ref(false)

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

const loadReviews = async () => {
  const id = productId.value
  if (!id) return
  try {
    const res = await api.get(`/api/products/${id}/reviews`)
    reviews.value = res.data || []
  } catch (err) {
    console.error('Error cargando reseñas:', err)
  }
}

async function load() {
  error.value = ''
  product.value = null
  reviews.value = []
  const id = productId.value
  if (id == null) {
    error.value = 'Producto no valido.'
    return
  }
  try {
    const res = await api.get(`/api/products/${id}`)
    product.value = res.data
    await loadReviews()
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

const submitReview = async () => {
  if (!comment.value.trim()) {
    return toast.error('El comentario no puede estar vacío.')
  }
  submitting.value = true
  try {
    await api.post(`/api/products/${product.value.id}/reviews`, {
      rating: rating.value,
      comment: comment.value,
    })
    toast.success('Opinión guardada correctamente.')
    comment.value = ''
    rating.value = 5
    await loadReviews()
  } catch (e) {
    toast.error(e.message || 'Error al enviar valoración.')
  } finally {
    submitting.value = false
  }
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
  margin-bottom: 3rem;
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

/* --- SECCIÓN VALORACIONES --- */
.reviews-section {
  width: 100%;
  background: #f8fafc;
  padding: 30px;
  border-radius: 20px;
  box-shadow: 0 4px 15px rgba(0,0,0,0.03);
  box-sizing: border-box;
}

.reviews-section h3 {
  margin-top: 0;
  margin-bottom: 20px;
  color: #1e293b;
  font-weight: 800;
  font-size: 1.35rem;
}

.no-reviews {
  padding: 20px 0;
  color: #64748b;
  font-style: italic;
}

.reviews-list {
  display: flex;
  flex-direction: column;
  gap: 20px;
  margin-bottom: 30px;
}

.review-item {
  padding-bottom: 15px;
  border-bottom: 1px solid #e2e8f0;
}

.review-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 6px;
  flex-wrap: wrap;
}

.reviewer-name {
  font-weight: 700;
  color: #1e293b;
}

.verified-badge {
  background: #dcfce7;
  color: #166534;
  font-size: 0.7rem;
  font-weight: 800;
  padding: 2px 8px;
  border-radius: 99px;
  text-transform: uppercase;
}

.review-date {
  font-size: 0.8rem;
  color: #94a3b8;
  margin-left: auto;
}

.stars {
  display: flex;
  gap: 3px;
  margin-bottom: 8px;
}

.star {
  font-size: 1.1rem;
  color: #cbd5e1;
}

.star.active {
  color: #f59e0b;
}

.review-text {
  margin: 0;
  color: #334155;
  line-height: 1.5;
  font-size: 0.95rem;
}

.review-form-wrapper {
  background: white;
  padding: 24px;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.02);
  border: 1px solid #e2e8f0;
}

.review-form-wrapper h4 {
  margin-top: 0;
  margin-bottom: 16px;
  color: #1e293b;
  font-size: 1.05rem;
}

.rating-select-wrapper {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
}

.rating-select-wrapper label {
  font-weight: 700;
  font-size: 0.9rem;
  color: #475569;
}

.star-rating-selector {
  display: flex;
  gap: 4px;
}

.selector-star-btn {
  background: none;
  border: none;
  cursor: pointer;
  padding: 0;
}

.selector-star {
  font-size: 1.5rem;
  color: #cbd5e1;
  transition: color 0.1s;
}

.selector-star.active {
  color: #f59e0b;
}

.review-textarea {
  width: 100%;
  min-height: 80px;
  box-sizing: border-box;
}

.btn-submit-review {
  margin-top: 12px;
}

.login-prompt {
  text-align: center;
  color: #64748b;
  font-size: 0.95rem;
}

.login-prompt a {
  color: #ff9f43;
  font-weight: bold;
  text-decoration: none;
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
  .review-date {
    margin-left: 0;
    width: 100%;
  }
}
</style>
