<template>
  <section class="section-container">
    <div class="video-background">
      <video autoplay muted loop playsinline class="video-bg">
        <source src="../assets/video-fondo-catalogo.mp4" type="video/mp4">
        Tu navegador no soporta el video.
      </video>
      <div class="video-overlay"></div>
    </div>

    <div class="catalog-layout">
      <aside class="sidebar-wrapper">
        <div class="sidebar-sticky">
          <div class="catalog-header">
            <h2>Catálogo</h2>
            <div class="line"></div>
          </div>

          <div class="filter-section">
            <label>Buscar Libro</label>
            <input 
              v-model="search" 
              class="search-input" 
              placeholder="Ej: El Quijote..." 
            />
          </div>

          <div class="filter-section">
            <label>Categorías</label>
            <div class="category-list">
              <button 
                :class="['cat-link', { 'is-active': selected === null }]"
                @click="selectCategory(null)"
              >
                <span class="dot"></span> Todos
              </button>
              
              <button 
                v-for="cat in categories" 
                :key="cat.id" 
                :class="['cat-link', { 'is-active': selected === cat.id }]"
                @click="selectCategory(cat.id)"
              >
                <span class="dot"></span> {{ cat.name }}
              </button>
            </div>
          </div>
        </div>
      </aside>

      <main class="products-container">
        <div class="products-vertical-column">
          <ProductCard 
            v-for="product in filtered" 
            :key="product.id" 
            :product="product" 
            class="single-column-item"
          />
          
          <div v-if="filtered.length === 0" class="no-data">
            <p>No hay libros disponibles 📖</p>
          </div>
        </div>
      </main>
    </div>
  </section>
</template>

<script setup>
import { onMounted, ref, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import ProductCard from '../components/ProductCard.vue'
import { api } from '../services/api'

const route = useRoute()
const categories = ref([])
const products = ref([])
const selected = ref(null)
const search = ref('')

const filtered = computed(() => {
  const text = search.value.toLowerCase()
  return products.value.filter((p) => {
    const matchesCategory = !selected.value || p.category_id === parseInt(selected.value)
    const matchesSearch = p.name.toLowerCase().includes(text)
    return matchesCategory && matchesSearch
  })
})

const selectCategory = (id) => {
  selected.value = id
}

const loadData = async () => {
  try {
    const catsRes = await api.get('/categories')
    categories.value = catsRes.data || catsRes || []
    const prodsRes = await api.get('/products')
    products.value = prodsRes.data || prodsRes || []
    if (route.query.categoria) {
      selected.value = parseInt(route.query.categoria)
    }
  } catch (error) {
    console.error("Error cargando datos:", error)
  }
}

onMounted(loadData)

watch(() => route.query.categoria, (newVal) => {
  selected.value = newVal ? parseInt(newVal) : null
})
</script>

<style scoped>
/* --- FONDO --- */
.video-background {
  position: fixed;
  top: 0; left: 0;
  width: 100%; height: 100vh;
  z-index: -2;
  overflow: hidden;
}
.video-bg { width: 100%; height: 100%; object-fit: cover; }
.video-overlay {
  position: absolute;
  top: 0; left: 0;
  width: 100%; height: 100%;
  background: rgba(15, 23, 42, 0.4);
  z-index: -1;
}

/* --- LAYOUT --- */
.section-container {
  min-height: calc(100vh - 88px);
}

.catalog-layout {
  display: grid;
  grid-template-columns: 320px 1fr;
  gap: 60px;
  max-width: 1600px;
  margin: 0 auto;
  padding: 0 40px; /* Margen lateral en escritorio */
}

/* --- SIDEBAR IZQUIERDA (fijo al hacer scroll) --- */
.sidebar-wrapper {
  position: sticky;
  top: 100px;
  align-self: start;
  z-index: 10;
  max-height: calc(100vh - 116px);
}

.sidebar-sticky {
  width: 100%;
  max-height: calc(100vh - 116px);
  overflow-y: auto;
  background: rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(15px);
  -webkit-backdrop-filter: blur(15px);
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 20px;
  padding: 30px;
  box-shadow: 0 15px 35px rgba(0,0,0,0.3);
}

.catalog-header h2 {
  color: white;
  font-size: 1.8rem;
  margin: 0;
  font-weight: 800;
}

.line {
  width: 50px;
  height: 4px;
  background: #ff9f43;
  margin: 15px 0 25px;
  border-radius: 2px;
}

.filter-section label {
  display: block;
  color: #ff9f43;
  text-transform: uppercase;
  font-size: 0.75rem;
  font-weight: 900;
  margin-bottom: 12px;
}

.search-input {
  width: 100%;
  background: rgba(15, 23, 42, 0.5);
  border: 1px solid rgba(255, 255, 255, 0.3);
  padding: 12px 15px;
  border-radius: 10px;
  color: white;
  outline: none;
}

.category-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.cat-link {
  background: transparent;
  border: none;
  color: #ffffff;
  text-align: left;
  padding: 10px 12px;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 700;
  transition: 0.2s;
}

.cat-link.is-active {
  background: #ff9f43;
  color: #1e293b;
}

/* --- COLUMNA DE RESULTADOS --- */
.products-container {
  padding: 100px 0;
}

.products-vertical-column {
  display: flex;
  flex-direction: column;
  gap: 16px;
  max-width: 800px;
  width: 100%;
  padding: 24px;
  background: rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(15px);
  -webkit-backdrop-filter: blur(15px);
  border-radius: 20px;
  border: 1px solid rgba(255, 255, 255, 0.2);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

.single-column-item {
  width: 100%;
}

.no-data {
  text-align: center;
  padding: 100px 0;
  color: white;
  font-weight: 700;
}

/* --- AJUSTES MÓVIL (CORREGIDO) --- */
@media (max-width: 900px) {
  .catalog-layout {
    grid-template-columns: 1fr; /* Una sola columna */
    padding: 0 15px; /* Margen sutil para que no toque los bordes del móvil */
    gap: 20px;
  }

  .sidebar-wrapper {
    position: sticky;
    top: 88px;
    z-index: 20;
    max-height: none;
    padding-top: 16px;
  }

  .sidebar-sticky {
    width: 100%;
    max-height: none;
    overflow-y: visible;
    padding: 20px;
    margin-bottom: 12px;
  }

  .products-container { 
    padding: 0; /* Quitamos paddings excesivos para que el recuadro luzca bien */
    width: 100%;
  }

  .products-vertical-column {
    max-width: 100%;
    width: 100%;
    padding: 16px;
    margin: 0 auto;
    border-radius: 15px;
  }
}
</style>