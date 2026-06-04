<template>
  <section class="section admin">
    <AdminSidebar />
    <div class="admin-content">
      <h2>Gestión de usuarios</h2>
      <div class="card user-row" v-for="u in users" :key="u.id">
        <div>
          <strong>{{ u.name }}</strong>
          <span class="muted">{{ u.email }} · {{ u.role }}</span>
          <span class="pill" :class="{ on: u.is_active == 1 }">
            {{ u.is_active == 1 ? 'Activo' : 'Desactivado' }}
          </span>
        </div>
        <div class="actions">
          <button
            v-if="u.is_active == 1"
            type="button"
            class="btn ghost"
            @click="setActive(u, 0)"
          >
            Bloquear
          </button>
          <button
            v-else
            type="button"
            class="btn ghost"
            @click="setActive(u, 1)"
          >
            Activar
          </button>
          <button type="button" class="btn ghost danger" @click="removeUser(u)">Eliminar</button>
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

const users = ref([])
const toast = useToastStore()

const load = async () => {
  const res = await api.get('/api/admin/users')
  users.value = res.data || []
}

const setActive = async (u, v) => {
  await api.patch(`/api/admin/users/${u.id}`, { is_active: v })
  toast.success(v ? 'Usuario activado' : 'Usuario bloqueado')
  await load()
}

const removeUser = async (u) => {
  if (!confirm(`¿Eliminar a ${u.name}? Esta acción no se puede deshacer.`)) return
  try {
    await api.delete(`/api/admin/users/${u.id}`)
    toast.success('Usuario eliminado')
    await load()
  } catch (e) {
    toast.error(e.message || 'No se pudo eliminar')
  }
}

onMounted(load)
</script>

<style scoped>
.user-row {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 12px;
  align-items: center;
  margin-bottom: 12px;
}
.muted {
  display: block;
  font-size: 0.88rem;
  opacity: 0.8;
}
.pill {
  display: inline-block;
  margin-top: 6px;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 800;
  background: #fee2e2;
  color: #991b1b;
}
.pill.on {
  background: #dcfce7;
  color: #166534;
}
.actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.danger {
  color: #b91c1c;
}
</style>
