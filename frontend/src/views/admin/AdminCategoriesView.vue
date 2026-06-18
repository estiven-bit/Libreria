<template>
  <section class="section admin">
    <AdminSidebar />
    <div class="admin-content">
      <div class="page-header">
        <h2>Gestión de categorías</h2>
        <button class="btn btn-primary" type="button" @click="createOpen = true">
          + Nueva categoría
        </button>
      </div>

      <!-- Lista de categorías -->
      <div v-if="categories.length === 0" class="empty-state card">
        <p>Todavía no hay categorías creadas.</p>
      </div>

      <div class="card category-row" v-for="cat in categories" :key="cat.id">
        <div class="category-info">
          <strong>{{ cat.name }}</strong>
          <span v-if="getParentName(cat.parent_id)" class="parent-label">
            Subcategoría de: {{ getParentName(cat.parent_id) }}
          </span>
        </div>
        <div class="actions">
          <button class="btn ghost" type="button" @click="openEdit(cat)">Editar</button>
          <button class="btn ghost danger" type="button" @click="deleteCategory(cat)">Eliminar</button>
        </div>
      </div>

      <!-- Modal CREAR categoría -->
      <div v-if="createOpen" class="modal-backdrop" @click.self="closeCreate">
        <div class="modal card">
          <div class="modal-header">
            <h3>Nueva categoría</h3>
            <button class="btn-close" type="button" @click="closeCreate">✕</button>
          </div>

          <div class="form-grid">
            <div class="form-field full">
              <label for="new-cat-name">Nombre de la categoría *</label>
              <input id="new-cat-name" v-model="form.name" class="input" placeholder="Ej. Fantasía Juvenil" />
            </div>

            <div class="form-field full">
              <label for="new-cat-parent">Categoría Superior (Opcional)</label>
              <select id="new-cat-parent" v-model="form.parent_id" class="input">
                <option :value="null">-- Ninguna (Categoría Principal) --</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
          </div>

          <div class="modal-actions">
            <button class="btn ghost" type="button" @click="closeCreate">Cancelar</button>
            <button class="btn btn-primary" type="button" @click="createCategory">Crear categoría</button>
          </div>
        </div>
      </div>

      <!-- Modal EDITAR categoría -->
      <div v-if="editOpen" class="modal-backdrop" @click.self="editOpen = false">
        <div class="modal card">
          <div class="modal-header">
            <h3>Editar categoría</h3>
            <button class="btn-close" type="button" @click="editOpen = false">✕</button>
          </div>

          <div class="form-grid">
            <div class="form-field full">
              <label>Nombre de la categoría</label>
              <input v-model="editForm.name" class="input" />
            </div>

            <div class="form-field full">
              <label>Categoría Superior</label>
              <select v-model="editForm.parent_id" class="input">
                <option :value="null">-- Ninguna (Categoría Principal) --</option>
                <option 
                  v-for="c in filteredCategoriesForParent" 
                  :key="c.id" 
                  :value="c.id"
                >
                  {{ c.name }}
                </option>
              </select>
            </div>
          </div>

          <div class="modal-actions">
            <button class="btn ghost" type="button" @click="editOpen = false">Cancelar</button>
            <button class="btn btn-primary" type="button" @click="saveEdit">Guardar cambios</button>
          </div>
        </div>
      </div>

    </div>
  </section>
</template>

<script setup>
import { onMounted, ref, computed } from 'vue'
import { api } from '../../services/api'
import AdminSidebar from '../../components/AdminSidebar.vue'
import { useToastStore } from '../../stores/toast'

const toast = useToastStore()
const categories = ref([])

const createOpen = ref(false)
const form = ref({ name: '', parent_id: null })

const editOpen = ref(false)
const editForm = ref({ id: null, name: '', parent_id: null })

const loadCategories = async () => {
  try {
    const res = await api.get('/api/categories')
    categories.value = res.data || res || []
  } catch (e) {
    toast.error('Error al cargar categorías')
  }
}

const getParentName = (parentId) => {
  if (!parentId) return ''
  const parent = categories.value.find(c => c.id === parentId)
  return parent ? parent.name : ''
}

// Filtra las categorías para evitar que una categoría sea su propio padre
const filteredCategoriesForParent = computed(() => {
  return categories.value.filter(c => c.id !== editForm.value.id)
})

const closeCreate = () => {
  createOpen.value = false
  form.value = { name: '', parent_id: null }
}

const createCategory = async () => {
  if (!form.value.name.trim()) return toast.error('El nombre es obligatorio')
  try {
    await api.post('/api/admin/categories', form.value)
    toast.success('Categoría creada correctamente')
    closeCreate()
    await loadCategories()
  } catch (e) {
    toast.error(e.message || 'Error al crear la categoría')
  }
}

const openEdit = (cat) => {
  editForm.value = {
    id: cat.id,
    name: cat.name,
    parent_id: cat.parent_id
  }
  editOpen.value = true
}

const saveEdit = async () => {
  if (!editForm.value.name.trim()) return toast.error('El nombre es obligatorio')
  try {
    await api.patch(`/api/admin/categories/${editForm.value.id}`, {
      name: editForm.value.name,
      parent_id: editForm.value.parent_id
    })
    toast.success('Categoría actualizada')
    editOpen.value = false
    await loadCategories()
  } catch (e) {
    toast.error(e.message || 'Error al guardar cambios')
  }
}

const deleteCategory = async (cat) => {
  if (!confirm(`¿Seguro que quieres eliminar la categoría "${cat.name}"?`)) return
  try {
    await api.delete(`/api/admin/categories/${cat.id}`)
    toast.success('Categoría eliminada')
    await loadCategories()
  } catch (e) {
    toast.error(e.message || 'Error al eliminar')
  }
}

onMounted(loadCategories)
</script>

<style scoped>
.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 20px;
}
.page-header h2 { margin: 0; }

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

.empty-state {
  text-align: center;
  padding: 40px 20px;
  color: #64748b;
  font-style: italic;
}

.category-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  margin-bottom: 12px;
}

.category-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.parent-label {
  font-size: 0.8rem;
  color: #64748b;
}

.actions {
  display: flex;
  gap: 8px;
}

.danger { color: #b91c1c; }

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
  max-width: 500px;
  width: 100%;
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

.form-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 14px;
}
.form-field { display: flex; flex-direction: column; gap: 5px; }

.form-field label {
  font-size: 0.78rem;
  font-weight: 700;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}
</style>
