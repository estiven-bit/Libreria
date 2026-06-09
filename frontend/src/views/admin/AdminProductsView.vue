<template>
  <section class="section admin">
    <AdminSidebar />
    <div class="admin-content">
      <div class="page-header">
        <h2>Gestión de productos</h2>
        <button class="btn btn-primary" type="button" @click="createOpen = true">
          + Crear producto
        </button>
      </div>

      <!-- Lista de productos -->
      <div v-if="products.length === 0" class="empty-state card">
        <p>Todavía no hay productos. ¡Crea el primero!</p>
      </div>

      <div class="card product-row" v-for="product in products" :key="product.id">
        <div class="product-thumb">
          <img v-if="product.image_url" :src="api.mediaUrl(product.image_url)" :alt="product.name" class="thumb-img" />
          <div v-else class="thumb-placeholder">📚</div>
        </div>
        <div class="product-info">
          <strong>{{ product.name }}</strong>
          <span class="muted">{{ product.price }} € · Stock: {{ product.stock }}</span>
        </div>
        <div class="actions">
          <button class="btn ghost" type="button" @click="openEdit(product)">Editar</button>
          <button class="btn ghost danger" type="button" @click="deleteProduct(product.id)">Eliminar</button>
        </div>
      </div>

      <!-- Modal CREAR producto -->
      <div v-if="createOpen" class="modal-backdrop" @click.self="closeCreate">
        <div class="modal card" :class="{ 'modal-busy': isCreating }">
          <div class="modal-header">
            <h3>Nuevo producto</h3>
            <button class="btn-close" type="button" @click="closeCreate">✕</button>
          </div>

          <div class="form-grid">
            <div class="form-field full">
              <label for="new-name">Nombre del libro *</label>
              <input id="new-name" v-model="form.name" class="input" placeholder="Ej. El Principito" />
            </div>

            <div class="form-field">
              <label for="new-price">Precio (€) *</label>
              <input id="new-price" v-model="form.price" class="input" type="number" step="0.01" min="0" placeholder="Ej. 12.99" />
            </div>

            <div class="form-field">
              <label for="new-stock">Unidades en stock *</label>
              <input id="new-stock" v-model="form.stock" class="input" type="number" min="0" placeholder="Ej. 50" />
            </div>

            <div class="form-field full">
              <label for="new-category">Categoría / Edad recomendada *</label>
              <select id="new-category" v-model.number="form.category_id" class="input">
                <option disabled value="">-- Selecciona una categoría --</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>

            <div class="form-field full">
              <label for="new-desc">Descripción</label>
              <textarea id="new-desc" v-model="form.description" class="input" rows="3"
                placeholder="Breve descripción del libro (autor, temática, edad recomendada…)"></textarea>
            </div>

            <div class="form-field full">
              <label>Imágenes del producto</label>
              <label class="upload-button">
                <span class="upload-icon">⇪</span>
                <span>Subir imágenes</span>
                <small>JPG o PNG · puedes elegir varias</small>
                <input type="file" accept="image/jpeg,image/png" multiple @change="onPickFile" />
              </label>
              <div v-if="newImageFiles.length" class="file-preview">
                <span v-for="(f, i) in newImageFiles" :key="i" class="file-badge">{{ f.name }}</span>
              </div>
            </div>
          </div>

          <div class="modal-actions">
            <button class="btn ghost" type="button" :disabled="isCreating" @click="closeCreate">Cancelar</button>
            <button class="btn btn-primary" type="button" :disabled="isCreating" @click="createProduct">
              {{ isCreating ? 'Creando producto…' : 'Crear producto' }}
            </button>
          </div>
          <p v-if="isCreating" class="creating-hint">No cierres esta ventana hasta que termine.</p>
        </div>
      </div>

      <!-- Modal EDITAR producto -->
      <div v-if="editOpen" class="modal-backdrop" @click.self="editOpen = false">
        <div class="modal card">
          <div class="modal-header">
            <h3>Editar producto</h3>
            <button class="btn-close" type="button" @click="editOpen = false">✕</button>
          </div>

          <div class="form-grid">
            <div class="form-field full">
              <label>Nombre del libro</label>
              <input v-model="editForm.name" class="input" placeholder="Ej. El Principito" />
            </div>

            <div class="form-field">
              <label>Precio (€)</label>
              <input v-model="editForm.price" class="input" type="number" step="0.01" min="0" placeholder="Ej. 12.99" />
            </div>

            <div class="form-field">
              <label>Unidades en stock</label>
              <input v-model="editForm.stock" class="input" type="number" min="0" placeholder="Ej. 50" />
            </div>

            <div class="form-field full">
              <label>Categoría / Edad recomendada</label>
              <select v-model.number="editForm.category_id" class="input">
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>

            <div class="form-field full">
              <label>Descripción</label>
              <textarea v-model="editForm.description" class="input" rows="3"
                placeholder="Breve descripción del libro…"></textarea>
            </div>

            <div class="form-field full">
              <label>Añadir más imágenes</label>
              <label class="upload-button">
                <span class="upload-icon">⇪</span>
                <span>Subir imágenes</span>
                <small>JPG o PNG · selección múltiple</small>
                <input type="file" accept="image/jpeg,image/png" multiple @change="onPickEditFile" />
              </label>
              <div v-if="editImageFiles.length" class="file-preview">
                <span v-for="(f, i) in editImageFiles" :key="i" class="file-badge">{{ f.name }}</span>
              </div>
            </div>
          </div>

          <div class="modal-actions">
            <button class="btn ghost" type="button" :disabled="isSaving" @click="editOpen = false">Cancelar</button>
            <button class="btn btn-primary" type="button" :disabled="isSaving" @click="saveEdit">
              {{ isSaving ? 'Guardando...' : 'Guardar cambios' }}
            </button>
          </div>
        </div>
      </div>

    </div>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../../services/api'
import AdminSidebar from '../../components/AdminSidebar.vue'
import { useToastStore } from '../../stores/toast'

const toast = useToastStore()
const products = ref([])
const categories = ref([])

const createOpen = ref(false)
const form = ref({ name: '', price: 0, stock: 0, category_id: '', description: '' })

const editOpen = ref(false)
const editForm = ref({ id: null, name: '', price: 0, stock: 0, category_id: '', description: '' })

const newImageFiles = ref([])
const editImageFiles = ref([])

const isCreating = ref(false)
const isSaving = ref(false)

const onPickFile = (e) => { newImageFiles.value = Array.from(e.target.files || []) }
const onPickEditFile = (e) => { editImageFiles.value = Array.from(e.target.files || []) }

const closeCreate = () => {
  if (isCreating.value) return
  createOpen.value = false
  form.value = { name: '', price: 0, stock: 0, category_id: categories.value[0]?.id ?? '', description: '' }
  newImageFiles.value = []
}

const uploadImage = async (productId, file) => {
  if (!file || !productId) return
  const fd = new FormData()
  fd.append('image', file)
  await api.postMultipart(`/api/admin/products/${productId}/image`, fd)
}

const loadCategories = async () => {
  const res = await api.get('/api/categories')
  const list = res.data || []
  categories.value = list
  if (list.length) form.value.category_id = list[0].id
}

const loadProducts = async () => {
  const res = await api.get('/api/products')
  products.value = res.data || []
}

const createProduct = async () => {
  if (isCreating.value) return
  if (!form.value.name) return toast.error('El nombre del libro es obligatorio')
  isCreating.value = true
  try {
    const res = await api.post('/api/admin/products', { ...form.value })
    const id = res.id
    let imageErrors = 0
    if (id && newImageFiles.value.length) {
      for (const file of newImageFiles.value) {
        try {
          await uploadImage(id, file)
        } catch {
          imageErrors++
        }
      }
    }
    closeCreate()
    await loadProducts()
    if (imageErrors > 0) {
      toast.error(`Producto creado, pero ${imageErrors} imagen(es) no se pudieron subir`)
    } else {
      toast.success('Producto creado correctamente')
    }
  } catch (e) {
    toast.error(e?.message || 'Error al crear el producto')
  } finally {
    isCreating.value = false
  }
}

const deleteProduct = async (id) => {
  if (!confirm('¿Seguro que quieres eliminar este producto?')) return
  try {
    await api.delete(`/api/admin/products/${id}`)
    toast.success('Producto eliminado')
    await loadProducts()
  } catch (e) {
    toast.error(e?.message || 'Error al eliminar')
  }
}

const openEdit = (p) => {
  editForm.value = {
    id: p.id,
    name: p.name,
    price: Number(p.price),
    stock: Number(p.stock),
    category_id: Number(p.category_id),
    description: p.description || '',
  }
  editImageFiles.value = []
  editOpen.value = true
}

const saveEdit = async () => {
  if (isSaving.value) return
  isSaving.value = true
  const { id, ...body } = editForm.value
  try {
    await api.put(`/api/admin/products/${id}`, body)
    if (editImageFiles.value.length && id) {
      for (const file of editImageFiles.value) await uploadImage(id, file)
      editImageFiles.value = []
    }
    toast.success('Producto actualizado')
    editOpen.value = false
    await loadProducts()
  } catch (e) {
    toast.error(e?.message || 'Error al guardar')
  } finally {
    isSaving.value = false
  }
}

onMounted(async () => {
  await loadCategories()
  await loadProducts()
})
</script>

<style scoped>
/* Cabecera de la página */
.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 20px;
}
.page-header h2 { margin: 0; }

/* Botón principal */
.btn-primary {
  background: linear-gradient(135deg, #ff9f43, #ff6b6b);
  color: #fff;
  border: none;
  font-weight: 700;
  box-shadow: 0 4px 12px rgba(255, 107, 107, 0.3);
}
.btn-primary:hover {
  filter: brightness(1.08);
  transform: translateY(-2px);
}

/* Estado vacío */
.empty-state {
  text-align: center;
  padding: 40px 20px;
  color: #64748b;
  font-style: italic;
}

/* Fila de producto */
.product-row {
  display: grid;
  grid-template-columns: 48px 1fr auto;
  align-items: center;
  gap: 16px;
}
.thumb-img {
  width: 44px;
  height: 44px;
  object-fit: cover;
  border-radius: 8px;
}
.thumb-placeholder {
  width: 44px;
  height: 44px;
  background: #f1f5f9;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.3rem;
}
.actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  justify-content: flex-end;
}
.muted {
  opacity: 0.65;
  font-size: 0.85rem;
  display: block;
  margin-top: 2px;
}
.danger { color: #b91c1c; }

/* Modal */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.6);
  z-index: 2000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}
.modal {
  max-width: 540px;
  width: 100%;
  max-height: 90vh;
  overflow-y: auto;
}
.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
}
.modal-header h3 { margin: 0; font-size: 1.15rem; }
.btn-close {
  background: none;
  border: none;
  font-size: 1.1rem;
  cursor: pointer;
  color: #94a3b8;
  line-height: 1;
  padding: 4px 8px;
  border-radius: 6px;
  transition: 0.2s;
}
.btn-close:hover { background: #f1f5f9; color: #1e293b; }

.modal-actions {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  margin-top: 20px;
}
.modal-busy {
  pointer-events: none;
  opacity: 0.85;
}
.creating-hint {
  margin: 12px 0 0;
  font-size: 0.85rem;
  color: #64748b;
  text-align: center;
}

/* Formulario con grid */
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}
.form-field { display: flex; flex-direction: column; gap: 5px; }
.form-field.full { grid-column: 1 / -1; }

.form-field label {
  font-size: 0.78rem;
  font-weight: 700;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

/* Subida de imagen */
.upload-button {
  position: relative;
  display: grid;
  gap: 4px;
  justify-items: start;
  padding: 14px 18px;
  border-radius: 12px;
  background: linear-gradient(135deg, #0ea5e9, #10b981);
  color: #fff;
  font-weight: 700;
  cursor: pointer;
  overflow: hidden;
}
.upload-button small { font-size: 0.75rem; opacity: 0.9; }
.upload-icon { font-size: 1.2rem; }
.upload-button input[type='file'] {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
}

/* Preview de archivos seleccionados */
.file-preview {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 6px;
}
.file-badge {
  background: #e2e8f0;
  border-radius: 20px;
  padding: 3px 10px;
  font-size: 0.75rem;
  color: #334155;
  max-width: 180px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

@media (max-width: 640px) {
  .product-row { grid-template-columns: 48px 1fr; }
  .actions { grid-column: 1 / -1; }
  .form-grid { grid-template-columns: 1fr; }
  .form-field.full { grid-column: 1; }
}
</style>
