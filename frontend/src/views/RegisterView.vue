<template>
  <div class="register-redirect-container">
    <div class="loader-card">
      <div class="spinner"></div>
      <h2>Redirigiendo al Registro centralizado…</h2>
      <p>Estamos conectando con el portal de registro de la librería.</p>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { api } from '../services/api'
import { useToastStore } from '../stores/toast'

const toast = useToastStore()

onMounted(() => {
  try {
    // Redirigir al registro del IdP con retorno al origin de la app
    const returnUrl = encodeURIComponent(window.location.origin + '/')
    const registerUrl = `${api.BFF_BASE}/idp/register?return=${returnUrl}`
    window.location.assign(registerUrl)
  } catch (error) {
    console.error('Error al redirigir al registro centralizado:', error)
    toast.error(`Error al redirigir al registro: ${error.message}`)
  }
})
</script>

<style scoped>
.register-redirect-container {
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