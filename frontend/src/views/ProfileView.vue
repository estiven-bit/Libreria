<template>
  <div class="page-container">
    <div class="video-background">
      <video autoplay muted loop playsinline class="video-bg">
        <source src="../assets/vieo-fondo-perfil.mp4" type="video/mp4">
        Tu navegador no soporta videos.
      </video>
      <div class="video-overlay"></div>
    </div>

    <section class="profile-page section">
      <h2 class="title">Mi Perfil</h2>

      <!-- Card de info de usuario -->
      <div class="glass-card profile-card" v-if="store.user">
        <div class="user-avatar-circle">
          {{ store.user.name.charAt(0).toUpperCase() }}
        </div>
        <div class="user-info">
          <p class="user-name">{{ store.user.name }}</p>
          <p class="user-email">{{ store.user.email }}</p>
        </div>
        <button class="btn ghost-danger" @click="auth.logout()">Cerrar sesión</button>
      </div>

      <!-- Card de Gestión de Cuenta (SSO Centralizado) -->
      <div class="glass-card account-management-card" v-if="store.user">
        <h3>Gestión de Cuenta Centralizada</h3>
        <p class="section-desc">Administra tus datos personales, credenciales y dispositivos activos en nuestro sistema centralizado de identidad.</p>
        
        <div class="settings-grid">
          <a :href="getSettingsUrl('cuenta')" class="setting-link-card">
            <span class="setting-icon">👤</span>
            <div class="setting-text">
              <span class="setting-title">Datos personales</span>
              <span class="setting-subtitle">Modifica tu nombre, usuario o teléfono</span>
            </div>
            <span class="arrow-icon">→</span>
          </a>

          <a :href="getSettingsUrl('email')" class="setting-link-card">
            <span class="setting-icon">✉</span>
            <div class="setting-text">
              <span class="setting-title">Correo electrónico</span>
              <span class="setting-subtitle">Cambia tu email de acceso con verificación</span>
            </div>
            <span class="arrow-icon">→</span>
          </a>

          <a :href="getSettingsUrl('seguridad')" class="setting-link-card">
            <span class="setting-icon">🔒</span>
            <div class="setting-text">
              <span class="setting-title">Seguridad y Contraseña</span>
              <span class="setting-subtitle">Actualiza tus credenciales de seguridad</span>
            </div>
            <span class="arrow-icon">→</span>
          </a>

          <a :href="getSettingsUrl('sesiones')" class="setting-link-card">
            <span class="setting-icon">🖥</span>
            <div class="setting-text">
              <span class="setting-title">Dispositivos y Sesiones</span>
              <span class="setting-subtitle">Audita tus dispositivos con acceso activo</span>
            </div>
            <span class="arrow-icon">→</span>
          </a>
        </div>
      </div>

      <!-- Card de direcciones -->
      <div class="glass-card address-card">
        <h3>Mis Direcciones</h3>

        <ul v-if="addresses.length > 0" class="address-list">
          <li v-for="addr in addresses" :key="addr.id" class="address-item">
            <div class="addr-text">
              <span class="addr-line">{{ addr.address_line }}</span>
              <span class="addr-detail">{{ addr.city }}<span v-if="addr.postal_code">, {{ addr.postal_code }}</span><span v-if="addr.country"> — {{ addr.country }}</span></span>
            </div>
            <button class="btn-delete" @click="deleteAddress(addr.id)" title="Eliminar dirección">✕</button>
          </li>
        </ul>
        <p v-else class="empty-text">No tienes direcciones guardadas.</p>

        <hr class="divider" />

        <h4>Agregar nueva dirección</h4>
        <div class="address-form">
          <div class="form-row">
            <div class="form-field">
              <label>Dirección</label>
              <input v-model="addressLine" class="input glass-input" placeholder="Ej. Calle Principal 123" />
            </div>
            <div class="form-field">
              <label>Ciudad</label>
              <input v-model="city" class="input glass-input" placeholder="Ej. Yantzaza" />
            </div>
          </div>
          <div class="form-row">
            <div class="form-field">
              <label>Código postal</label>
              <input v-model="postalCode" class="input glass-input" placeholder="Ej. 19001" />
            </div>
            <div class="form-field">
              <label>País</label>
              <input v-model="country" class="input glass-input" placeholder="Ej. Ecuador" />
            </div>
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
import { useAuthStore } from '../stores/auth'
import { useToastStore } from '../stores/toast'

const auth = useAuthStore()
const toast = useToastStore()

const getSettingsUrl = (section) => {
  const userId = store.user?.id || 0
  const returnUrl = encodeURIComponent(window.location.origin + '/perfil')
  return `${api.BFF_BASE}/idp/settings?section=${section}&user=${userId}&return=${returnUrl}`
}

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
  if (!addressLine.value || !city.value) return toast.error("Completa los campos obligatorios: dirección y ciudad")

  try {
    await api.post('/api/user/addresses', {
      address_line: addressLine.value,
      city: city.value,
      postal_code: postalCode.value,
      country: country.value,
    })
    addressLine.value = city.value = postalCode.value = country.value = ''
    await loadAddresses()
    toast.success('Dirección guardada')
  } catch (error) {
    toast.error(error?.message || 'Error al guardar la dirección')
  }
}

const deleteAddress = async (id) => {
  if (!confirm('¿Seguro que quieres borrar esta dirección?')) return
  try {
    await api.delete(`/api/user/addresses/${id}`)
    await loadAddresses()
    toast.success('Dirección eliminada')
  } catch (error) {
    toast.error(error?.message || 'Error al eliminar la dirección')
  }
}

onMounted(loadAddresses)
</script>

<style scoped>
.page-container {
  position: relative;
  min-height: calc(100vh - 88px);
  padding: 30px 20px 40px;
  display: flex;
  justify-content: center;
  box-sizing: border-box;
}

/* Video de Fondo */
.video-background {
  position: fixed;
  top: 0; left: 0;
  width: 100%; height: 100vh;
  z-index: -1;
  overflow: hidden;
}
.video-bg { width: 100%; height: 100%; object-fit: cover; }
.video-overlay {
  position: absolute;
  top: 0; left: 0;
  width: 100%; height: 100%;
  background: rgba(15, 23, 42, 0.4);
}

.section {
  max-width: 760px;
  width: 100%;
  z-index: 1;
}

/* Título */
.title {
  color: #ffffff;
  margin-bottom: 20px;
  font-size: 2rem;
  text-shadow: 1px 1px 2px #000, -1px -1px 2px #000, 1px -1px 2px #000, -1px 1px 2px #000;
}

/* Glassmorphism cards */
.glass-card {
  background: rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(15px);
  -webkit-backdrop-filter: blur(15px);
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 20px;
  color: #ffffff;
  padding: 24px 28px;
  margin-bottom: 20px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

/* Profile card: avatar + info + botón en fila */
.profile-card {
  display: flex;
  align-items: center;
  gap: 20px;
  flex-wrap: wrap;
}

.user-avatar-circle {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: linear-gradient(135deg, #ff9f43, #ff6b6b);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  font-weight: 800;
  color: white;
  flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(255, 107, 107, 0.4);
}

.user-info {
  flex: 1;
  min-width: 0;
}

.user-name {
  font-weight: 700;
  font-size: 1.1rem;
  margin: 0 0 4px;
  text-shadow: 1px 1px 2px rgba(0,0,0,0.7);
}

.user-email {
  color: rgba(255,255,255,0.75);
  font-size: 0.9rem;
  margin: 0;
  text-shadow: none;
}

/* Address card */
.address-card h3 {
  color: #ff9f43;
  margin: 0 0 16px;
  font-size: 1.15rem;
  text-shadow: 1px 1px 2px rgba(0,0,0,0.8);
}

.address-card h4 {
  color: #ff9f43;
  margin: 0 0 12px;
  font-size: 0.95rem;
  text-shadow: 1px 1px 2px rgba(0,0,0,0.8);
}

/* Lista de direcciones */
.address-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.address-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 0;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.address-item:last-child {
  border-bottom: none;
}

.addr-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.addr-line {
  font-weight: 600;
  font-size: 0.95rem;
  text-shadow: 1px 1px 1px rgba(0,0,0,0.6);
}

.addr-detail {
  font-size: 0.82rem;
  color: rgba(255,255,255,0.65);
}

.btn-delete {
  background: rgba(239, 68, 68, 0.15);
  border: 1px solid rgba(239, 68, 68, 0.4);
  color: #ff8e8e;
  border-radius: 50%;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 0.8rem;
  font-weight: 700;
  transition: 0.2s;
  flex-shrink: 0;
}

.btn-delete:hover {
  background: rgba(239, 68, 68, 0.35);
  transform: scale(1.1);
}

/* Formulario de nueva dirección */
.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-bottom: 12px;
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.form-field label {
  font-size: 0.75rem;
  font-weight: 600;
  color: rgba(255,255,255,0.7);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* Inputs */
.glass-input {
  background: rgba(255, 255, 255, 0.08) !important;
  border: 1px solid rgba(255, 255, 255, 0.25) !important;
  color: white !important;
}

.glass-input::placeholder {
  color: rgba(255, 255, 255, 0.5);
}

.input {
  padding: 10px 12px;
  border-radius: 10px;
  outline: none;
  transition: all 0.3s;
  font-size: 0.9rem;
  width: 100%;
  box-sizing: border-box;
}

.input:focus {
  border-color: #ff9f43 !important;
  background: rgba(255, 255, 255, 0.15) !important;
}

/* Botones */
.btn {
  padding: 10px 22px;
  border-radius: 50px;
  border: none;
  font-weight: 700;
  cursor: pointer;
  transition: 0.3s;
}

.btn-save {
  background: linear-gradient(135deg, #ff9f43, #ff6b6b);
  color: white;
  margin-top: 4px;
  font-size: 0.9rem;
  box-shadow: 0 4px 12px rgba(255, 107, 107, 0.3);
}

.btn-save:hover {
  filter: brightness(1.1);
  transform: translateY(-2px);
}

.ghost-danger {
  background: rgba(239, 68, 68, 0.15);
  color: #ff8e8e;
  border: 1px solid rgba(239, 68, 68, 0.4);
  margin-left: auto;
  font-size: 0.88rem;
}

.ghost-danger:hover {
  background: rgba(239, 68, 68, 0.3);
}

.divider {
  border: 0;
  border-top: 1px solid rgba(255, 255, 255, 0.15);
  margin: 18px 0;
}

.empty-text {
  color: rgba(255,255,255,0.55);
  font-style: italic;
  font-size: 0.9rem;
}

@media (max-width: 600px) {
  .form-row { grid-template-columns: 1fr; }
  .profile-card { flex-direction: column; align-items: flex-start; }
  .ghost-danger { margin-left: 0; }
}

/* Account management grid */
.account-management-card h3 {
  color: #ff9f43;
  margin: 0 0 4px;
  font-size: 1.15rem;
  text-shadow: 1px 1px 2px rgba(0,0,0,0.8);
}

.section-desc {
  font-size: 0.85rem;
  color: rgba(255, 255, 255, 0.7);
  margin: 0 0 20px;
}

.settings-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 15px;
}

.setting-link-card {
  display: flex;
  align-items: center;
  gap: 14px;
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  padding: 14px 18px;
  color: #ffffff;
  text-decoration: none;
  transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
}

.setting-link-card:hover {
  background: rgba(255, 255, 255, 0.12);
  border-color: #ff9f43;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(255, 159, 67, 0.2);
}

.setting-icon {
  font-size: 1.35rem;
  width: 38px;
  height: 38px;
  background: rgba(255, 159, 67, 0.15);
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.setting-text {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-width: 0;
}

.setting-title {
  font-weight: 700;
  font-size: 0.92rem;
}

.setting-subtitle {
  font-size: 0.75rem;
  color: rgba(255, 255, 255, 0.6);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.arrow-icon {
  font-weight: 700;
  color: rgba(255, 255, 255, 0.4);
  transition: transform 0.3s;
}

.setting-link-card:hover .arrow-icon {
  color: #ff9f43;
  transform: translateX(4px);
}

@media (max-width: 650px) {
  .settings-grid {
    grid-template-columns: 1fr;
  }
}
</style>