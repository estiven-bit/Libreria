<template>
  <section class="section admin">
    <AdminSidebar />
    <div class="admin-content dashboard">
      <header class="dash-header">
        <h1>Panel administrador</h1>
        <p class="subtitle">Resumen de tu librería</p>
      </header>

      <div class="stats-grid">
        <article v-for="card in stats" :key="card.label" class="stat-card">
          <span class="stat-icon" aria-hidden="true">{{ card.icon }}</span>
          <h2 class="stat-value">{{ card.value }}</h2>
          <p class="stat-label">{{ card.label }}</p>
        </article>
      </div>
    </div>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../../services/api'
import AdminSidebar from '../../components/AdminSidebar.vue'

const stats = ref([
  { label: 'Pedidos', value: 0, icon: '📦' },
  { label: 'Usuarios', value: 0, icon: '👥' },
  { label: 'Productos', value: 0, icon: '📚' },
])


onMounted(async () => {
  try {
    const res = await api.get('/api/admin/stats')
    const d = res.data || {}
    stats.value = [
      { label: 'Pedidos', value: d.orders ?? 0, icon: '📦' },
      { label: 'Usuarios', value: d.users ?? 0, icon: '👥' },
      { label: 'Productos', value: d.products ?? 0, icon: '📚' },
    ]
  } catch {
    /* api.js ya maneja 401 */
  }
})
</script>

<style scoped>
.dashboard {
  text-align: center;
  max-width: 960px;
  margin-left: auto;
  margin-right: auto;
}

.dash-header {
  margin-bottom: 2rem;
}

.dash-header h1 {
  font-size: 1.85rem;
  font-weight: 800;
  color: #1a233d;
  margin: 0 0 0.35rem;
}

.subtitle {
  color: #64748b;
  margin: 0;
  font-size: 1rem;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.25rem;
  justify-content: center;
}

.stat-card {
  background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
  border-radius: 20px;
  padding: 1.75rem 1.25rem;
  box-shadow: 0 10px 40px rgba(26, 35, 61, 0.08), 0 2px 8px rgba(0, 0, 0, 0.04);
  border: 1px solid rgba(226, 232, 240, 0.9);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stat-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 16px 48px rgba(26, 35, 61, 0.12), 0 4px 12px rgba(0, 0, 0, 0.06);
}

.stat-icon {
  font-size: 2rem;
  display: block;
  margin-bottom: 0.5rem;
}

.stat-value {
  font-size: 2.25rem;
  font-weight: 800;
  color: #ff6b6b;
  margin: 0 0 0.25rem;
  line-height: 1.1;
}

.stat-label {
  margin: 0;
  font-weight: 600;
  color: #475569;
  font-size: 0.95rem;
}

</style>
