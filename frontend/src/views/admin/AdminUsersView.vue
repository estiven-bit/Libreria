<template>
  <section class="section admin">
    <AdminSidebar />
    <div class="admin-content">
      <h2>Gestion de usuarios</h2>
      <div class="card" v-for="user in users" :key="user.id">
        <p>{{ user.name }} - {{ user.email }} - {{ user.role }}</p>
      </div>
    </div>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../../services/api'
import AdminSidebar from '../../components/AdminSidebar.vue'

const users = ref([])

onMounted(async () => {
  const res = await api.get('/api/admin/users')
  users.value = res.data || []
})
</script>
