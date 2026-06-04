<template>
  <section class="section admin">
    <AdminSidebar />
    <div class="admin-content">
      <h2>Gestion de productos</h2>

      <div class="card form-card">
        <input v-model="form.name" class="input" placeholder="Nombre" />
        <input v-model="form.price" class="input" type="number" step="0.01" min="0" placeholder="Precio" />
        <input v-model="form.stock" class="input" type="number" min="0" placeholder="Stock" />
        <select v-model.number="form.category_id" class="input">
          <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
        <textarea v-model="form.description" class="input" placeholder="Descripcion"></textarea>
        <label class="upload-button">
          <span class="upload-icon">⇪</span>
          <span>Subir imagenes</span>
          <small>JPG o PNG, puedes elegir varias</small>
          <input type="file" accept="image/jpeg,image/png" multiple @change="onPickFile" />
        </label>
        <button class="btn" type="button" @click="createProduct">Crear</button>
      </div>

      <div class="card product-row" v-for="product in products" :key="product.id">
        <div class="product-info">
          <strong>{{ product.name }}</strong>
          <span class="muted">${{ product.price }} · Stock {{ product.stock }}</span>
        </div>
        <div class="actions">
          <button class="btn ghost" type="button" @click="openEdit(product)">Editar</button>
          <button class="btn ghost danger" type="button" @click="deleteProduct(product.id)">Eliminar</button>
        </div>
      </div>

      <div v-if="editOpen" class="modal-backdrop" @click.self="editOpen = false">
        <div class="modal card">
          <h3>Editar producto</h3>
          <input v-model="editForm.name" class="input" placeholder="Nombre" />
          <input v-model="editForm.price" class="input" type="number" step="0.01" min="0" />
          <input v-model="editForm.stock" class="input" type="number" min="0" />
          <select v-model.number="editForm.category_id" class="input">
            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
          <textarea v-model="editForm.description" class="input" placeholder="Descripcion"></textarea>
          <label class="upload-button">
            <span class="upload-icon">⇪</span>
            <span>Anadir imagenes</span>
            <small>JPG o PNG, seleccion multiple</small>
            <input type="file" accept="image/jpeg,image/png" multiple @change="onPickEditFile" />
          </label>
          <div class="modal-actions">
            <button class="btn ghost" type="button" @click="editOpen = false">Cancelar</button>
            <button class="btn" type="button" @click="saveEdit">Guardar</button>
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
const form = ref({ name: '', price: 0, stock: 0, category_id: 1, description: '' })
const editOpen = ref(false)
const editForm = ref({
  id: null,
  name: '',
  price: 0,
  stock: 0,
  category_id: 1,
  description: '',
})

const newImageFiles = ref([])
const editImageFiles = ref([])

const onPickFile = (e) => {
  newImageFiles.value = Array.from(e.target.files || [])
}

const onPickEditFile = (e) => {
  editImageFiles.value = Array.from(e.target.files || [])
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
  if (list.length) {
    const firstId = list[0].id
    if (!form.value.category_id) form.value.category_id = firstId
  }
}

const loadProducts = async () => {
  const res = await api.get('/api/products')
  products.value = res.data || []
}

const createProduct = async () => {
  const res = await api.post('/api/admin/products', { ...form.value })
  const id = res.id
  if (id && newImageFiles.value.length) {
    for (const file of newImageFiles.value) {
      await uploadImage(id, file)
    }
    newImageFiles.value = []
  }
  toast.success('Producto creado')
  await loadProducts()
}

const deleteProduct = async (id) => {
  await api.delete(`/api/admin/products/${id}`)
  await loadProducts()
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
  editOpen.value = true
}

const saveEdit = async () => {
  const { id, ...body } = editForm.value
  await api.put(`/api/admin/products/${id}`, body)
  if (editImageFiles.value.length && id) {
    for (const file of editImageFiles.value) {
      await uploadImage(id, file)
    }
    editImageFiles.value = []
  }
  toast.success('Producto actualizado')
  editOpen.value = false
  await loadProducts()
}

onMounted(async () => {
  await loadCategories()
  await loadProducts()
})
</script>

<style scoped>
.form-card,
.modal {
  display: grid;
  gap: 12px;
}
.product-row {
  display: grid;
  grid-template-columns: 1fr auto;
  align-items: center;
  gap: 16px;
}
.actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  justify-content: flex-end;
}
.muted {
  opacity: 0.75;
  font-size: 0.9rem;
  display: block;
}
.danger {
  color: #b91c1c;
}
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.55);
  z-index: 2000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}
.modal {
  max-width: 520px;
  width: 100%;
  max-height: 90vh;
  overflow: auto;
}
.modal-actions {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
}
.upload-button {
  position: relative;
  display: grid;
  gap: 4px;
  justify-items: start;
  padding: 16px 18px;
  border-radius: 16px;
  background: linear-gradient(135deg, #0ea5e9, #10b981);
  color: #fff;
  font-weight: 800;
  cursor: pointer;
  overflow: hidden;
}
.upload-button small {
  font-size: 0.75rem;
  opacity: 0.92;
}
.upload-icon {
  font-size: 1.2rem;
}
.upload-button input[type='file'] {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
}
@media (max-width: 640px) {
  .product-row {
    grid-template-columns: 1fr;
  }
}
</style>
