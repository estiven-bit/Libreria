<template>
  <div class="page-container">
    <video autoplay muted loop playsinline class="video-bg">
      <source src="../assets/vieo-fondo-perfil.mp4" type="video/mp4">
      Tu navegador no soporta videos.
    </video>

    <section class="section">
      <h2 class="title">Mi Perfil</h2>
      
      <div class="card glass-card profile-card" v-if="store.user">
        <div class="user-info">
          <p><strong>Nombre:</strong> {{ store.user.name }}</p>
          <p><strong>Email:</strong> {{ store.user.email }}</p>
        </div>
        <button class="btn ghost-danger" @click="store.logout()">Cerrar sesión</button>
      </div>

      <div class="card glass-card address-card">
        <h3>Mis Direcciones</h3>
        
        <ul v-if="addresses.length > 0" class="address-list">
          <li v-for="addr in addresses" :key="addr.id" class="address-item">
            <span class="icon">📍</span>
            {{ addr.address_line }}, {{ addr.city }} ({{ addr.postal_code }})
          </li>
        </ul>
        <p v-else class="empty-text">No tienes direcciones guardadas.</p>

        <hr class="divider" />

        <h4>Agregar nueva dirección</h4>
        <div class="address-form">
          <div class="input-group">
            <input v-model="addressLine" class="input glass-input" placeholder="Dirección" />
            <input v-model="city" class="input glass-input" placeholder="Ciudad" />
          </div>
          <div class="input-group">
            <input v-model="postalCode" class="input glass-input" placeholder="Código postal" />
            <input v-model="country" class="input glass-input" placeholder="País" />
          </div>
          <button class="btn btn-save" @click="saveAddress">Guardar Dirección</button>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { store } from '../store' 
import { api } from '../services/api'

const addresses = ref([])
const addressLine = ref('')
const city = ref('')
const postalCode = ref('')
const country = ref('')

const loadAddresses = async () => {
  try {
    const res = await api.get('/api/user/addresses')
    addresses.value = res.data || res || [] 
  } catch (error) {
    console.error("Error cargando direcciones:", error)
  }
}

const saveAddress = async () => {
  if (!addressLine.value || !city.value) return alert("Completa los campos principales")
  
  try {
    await api.post('/api/user/addresses', {
      address_line: addressLine.value,
      city: city.value,
      postal_code: postalCode.value,
      country: country.value,
    })
    addressLine.value = city.value = postalCode.value = country.value = ''
    await loadAddresses()
  } catch (error) {
    alert("Error al guardar la dirección")
  }
}

onMounted(loadAddresses)
</script>

<style scoped>
.page-container {
  position: relative;
  min-height: 100vh;
  padding-top: 100px;
  display: flex;
  justify-content: center;
  overflow: hidden;
}

/* Estilos del Video de Fondo */
.video-bg {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  z-index: -1;
}

.section {
  max-width: 800px;
  width: 100%;
  padding: 20px;
  z-index: 1;
}

/* Título con borde reforzado */
.title {
  color: #ffffff;
  margin-bottom: 20px;
  font-size: 2rem;
  /* Sombra negra sólida para simular borde */
  text-shadow: 1px 1px 2px #000, -1px -1px 2px #000, 1px -1px 2px #000, -1px 1px 2px #000;
}

/* EFECTO TRANSLÚCIDO (Glassmorphism) */
.glass-card {
  background: rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(15px) saturate(180%);
  -webkit-backdrop-filter: blur(15px) saturate(180%);
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 16px;
  color: #ffffff;
  /* Borde negro sutil para todo el texto dentro de la tarjeta */
  text-shadow: 1px 1px 1px rgba(0, 0, 0, 0.8);
}

.card {
  padding: 30px;
  margin-bottom: 25px;
  box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
}

.card h3, .card h4 {
  color: #ff9f43;
  margin-bottom: 15px;
  /* Resalte extra para los encabezados naranjas */
  text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.9);
}

/* Inputs Translúcidos */
.glass-input {
  background: rgba(255, 255, 255, 0.1) !important;
  border: 1px solid rgba(255, 255, 255, 0.3) !important;
  color: white !important;
  text-shadow: 1px 1px 1px rgba(0, 0, 0, 0.5);
}

.glass-input::placeholder {
  color: rgba(255, 255, 255, 0.8);
  text-shadow: 1px 1px 1px rgba(0, 0, 0, 0.9);;
}

.input-group {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 15px;
}

.input {
  padding: 12px;
  border-radius: 8px;
  outline: none;
  transition: all 0.3s;
}

.input:focus {
  border-color: #ff9f43;
  background: rgba(255, 255, 255, 0.2) !important;
}

.btn {
  padding: 12px 25px;
  border-radius: 50px;
  border: none;
  font-weight: 700;
  cursor: pointer;
  transition: 0.3s;
  /* Borde en el texto de los botones */
  text-shadow: 1px 1px 1px rgba(0, 0, 0, 0.3);
}

.btn-save {
  background: #ff9f43;
  color: white;
  margin-top: 10px;
}

.btn-save:hover {
  background: #ff8c1a;
  transform: translateY(-2px);
}

.ghost-danger {
  background: rgba(239, 68, 68, 0.2);
  color: #ff8e8e;
  border: 1px solid #ef4444;
}

.ghost-danger:hover {
  background: rgba(239, 68, 68, 0.4);
}

.address-item {
  padding: 12px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  color: #e2e8f0;
}

.divider {
  border: 0;
  border-top: 1px solid rgba(255, 255, 255, 0.2);
  margin: 25px 0;
}

.empty-text {
  color: #cbd5e1;
  font-style: italic;
}
</style>