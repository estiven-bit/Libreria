<template>
  <div class="login-redirect-container">
    <div class="loader-card">
      <div class="spinner"></div>
      <h2>Redirigiendo a Librería Gabi Accounts…</h2>
      <p>Estamos conectando con tu cuenta de identidad única.</p>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { api } from '../services/api'
import { useToastStore } from '../stores/toast'

const toast = useToastStore()

onMounted(async () => {
  try {
    // 1. Obtener la URL de autorización desde el BFF
    const isLocal = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1'
    const clientId = isLocal ? 'libreria-gabi-dev' : 'libreria-gabi-prod'
    
    const res = await fetch(`${api.BFF_BASE}/bff/start?client=${clientId}`, {
      method: 'GET',
    })
    
    if (!res.ok) {
      throw new Error('Error al iniciar el flujo de sesión en el BFF')
    }
    
    const data = await res.json()
    if (data.authorize_url) {
      // 2. Redirigir la página completa al IdP
      window.location.assign(data.authorize_url)
    } else {
      throw new Error('No se recibió la URL de autorización del IdP')
    }
  } catch (error) {
    console.error('Error al redirigir al login centralizado:', error)
    toast.error(`Error de login centralizado: ${error.message}`)
  }
})
</script>

<style scoped>
.login-redirect-container {
  min-height: calc(100vh - 88px);
  display: grid;
  place-items: center;
  background: radial-gradient(circle at top, #fff1c2, #fff9e6);
  padding: 1.5rem;
}
.loader-card {
  background: white;
  border-radius: 20px;
  padding: 3rem 2rem;
  text-align: center;
  box-shadow: 0 10px 40px rgba(0,0,0,0.08);
  border: 1px solid rgba(226, 232, 240, 0.8);
  max-width: 400px;
  width: 100%;
}
.spinner {
  width: 50px;
  height: 50px;
  border: 5px solid #ff6b6b;
  border-top-color: transparent;
  border-radius: 50%;
  margin: 0 auto 1.5rem;
  animation: spin 1s linear infinite;
}
h2 {
  font-size: 1.25rem;
  font-weight: 800;
  color: #1a233d;
  margin: 0 0 0.5rem;
}
p {
  font-size: 0.9rem;
  color: #64748b;
  margin: 0;
}
@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
