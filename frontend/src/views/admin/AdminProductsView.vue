<template>
  <section class="section admin">
    <AdminSidebar />
    <div class="admin-content">
      <h2>Gestion de productos</h2>
      <div class="card form-card">
        <input v-model="form.name" class="input" placeholder="Nombre" />
        <input v-model="form.price" class="input" type="number" placeholder="Precio" />
        <input v-model="form.stock" class="input" type="number" placeholder="Stock" />
        <input v-model="form.category_id" class="input" type="number" placeholder="Categoria ID" />
        <textarea v-model="form.description" class="input" placeholder="Descripcion"></textarea>
        <button class="btn" @click="createProduct">Crear</button>
      </div>

      <div class="card" v-for="product in products" :key="product.id">
        <p>{{ product.name }} - ${{ product.price }}</p>
        <button class="btn ghost" @click="deleteProduct(product.id)">Eliminar</button>
      </div>
    </div>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../../services/api'
import AdminSidebar from '../../components/AdminSidebar.vue'

const products = ref([])
const form = ref({ name: '', price: 0, stock: 0, category_id: 1, description: '' })

const load = async () => {
  const res = await api.get('/api/products')
  products.value = res.data || []
}

const createProduct = async () => {
  await api.post('/api/admin/products', form.value)
  await load()
}

const deleteProduct = async (id) => {
  await api.delete(`/api/admin/products/${id}`)
  await load()
}

onMounted(load)
</script>
