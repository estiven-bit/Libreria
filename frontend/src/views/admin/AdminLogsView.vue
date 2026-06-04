<template>
  <section class="section admin">
    <AdminSidebar />
    <div class="admin-content">
      <h2>Logs</h2>

      <div class="card" v-for="log in logs" :key="log.id">
        <p class="muted">{{ log.created_at }}</p>
        <pre class="pre">{{ log.event }}</pre>
      </div>
    </div>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../../services/api'
import AdminSidebar from '../../components/AdminSidebar.vue'

const logs = ref([])

onMounted(async () => {
  const res = await api.get('/api/admin/logs?limit=200')
  logs.value = res.data || []
})
</script>

<style scoped>
.pre {
  white-space: pre-wrap;
  word-break: break-word;
  font-size: 0.9rem;
}
.muted {
  opacity: 0.7;
  margin-bottom: 8px;
}
</style>

