<template>
  <div class="callback-container">
    <div class="loader-card">
      <div class="spinner"></div>
      <h2>Procesando inicio de sesión…</h2>
      <p>Espere un momento mientras confirmamos sus datos en la librería.</p>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../services/api'
import { useAuthStore } from '../stores/auth'
import { useToastStore } from '../stores/toast'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const toast = useToastStore()

onMounted(async () => {
  const code = route.query.code
  const state = route.query.state

  if (!code || !state) {
    toast.error('Parámetros de autenticación inválidos o faltantes.')
    router.push('/login')
    return
  }

  try {
    // 1. Canjear el código por la sesión en el BFF
    const res = await fetch(`${api.BFF_BASE}/bff/callback?code=${code}&state=${state}`, {
      method: 'GET',
      credentials: 'include',
    })
    
    const payload = await res.json().catch(() => ({}))
    if (!res.ok) {
      throw new Error(payload.error || 'No se pudo completar el intercambio de token en el BFF')
    }

    // 2. Usar claims del callback (evita petición extra a /bff/me)
    const userLoaded = payload.user
      ? auth.applyUserFromClaims(payload.user)
      : await auth.hydrate()
    if (userLoaded) {
      toast.success('¡Sesión iniciada correctamente!')
      router.push('/perfil')
    } else {
      throw new Error('No se pudo recuperar el perfil del usuario después del login')
    }
  } catch (error) {
    console.error('Error en callback oidc:', error)
    toast.error(`Error de autenticación: ${error.message}`)
    router.push('/login')
  }
})
</script>

<style scoped>
.callback-container {
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
  border: 5px solid #ff9f43;
  border-top-color: transparent;
  border-radius: 50%;
  margin: 0 auto 1.5rem;
  animation: spin 1s linear infinite;
}
h2 {
  font-size: 1.35rem;
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
