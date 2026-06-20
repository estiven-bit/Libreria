<template>
  <header :class="['nav', { 'scrolled': isScrolled, 'menu-open': isMenuOpen }]">
    <RouterLink to="/" class="brand-link" @click="isMenuOpen = false">
      <div class="brand">
        <div class="brand-logo-container">
          <img src="../assets/logo.png" alt="Librería Gabi Logo" class="nav-logo" />
        </div>
        <div class="brand-text">
          <p class="brand-title">Librería Gabi</p>
          <p class="brand-subtitle">Mundo de cuentos</p>
        </div>
      </div>
    </RouterLink>
    <!-- Mobile Notification Bell (outside of the collapsible menu) -->
    <div v-if="store.user" class="notifications-container notifications-container--mobile">
      <button class="bell-btn" @click="toggleNotifications" aria-label="Notificaciones">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="bell-icon">
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
          <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
        </svg>
        <span v-if="unreadCount > 0" class="badge"></span>
      </button>

      <div v-if="isNotificationsOpen" class="notifications-dropdown">
        <div class="notifications-header">
          <h4>Notificaciones</h4>
          <button class="close-notif-btn" @click="isNotificationsOpen = false" aria-label="Cerrar">×</button>
        </div>
        <div class="notifications-list">
          <div v-if="notifications.length === 0" class="no-notifications">
            Sin notificaciones
          </div>
          <div v-else v-for="notif in notifications" :key="notif.id" :class="['notif-item', { 'unread': !notif.is_read }]">
            <p class="notif-message">{{ notif.message }}</p>
            <span class="notif-date">{{ new Date(notif.created_at).toLocaleString('es-ES') }}</span>
          </div>
        </div>
      </div>
    </div>

    <button class="menu-toggle" @click="isMenuOpen = !isMenuOpen" aria-label="Abrir menú">
      <span class="bar"></span>
      <span class="bar"></span>
      <span class="bar"></span>
    </button>

    <nav :class="['nav-links', { 'active': isMenuOpen }]">
      <RouterLink to="/" @click="isMenuOpen = false">Inicio</RouterLink>
      <RouterLink to="/catalogo" @click="isMenuOpen = false">Catálogo</RouterLink>
      <RouterLink to="/contacto" @click="isMenuOpen = false">Quiénes Somos</RouterLink>
      
      <RouterLink v-if="store.user" to="/carrito" class="cart-link" @click="isMenuOpen = false">
        Carrito
      </RouterLink>

      <RouterLink v-if="store.user" to="/mis-pedidos" @click="isMenuOpen = false">
        Mis pedidos
      </RouterLink>

      <RouterLink
        v-if="isAdmin"
        to="/admin"
        class="admin-link"
        @click="isMenuOpen = false"
      >
        Panel Admin
      </RouterLink>

      <!-- Desktop Notification Bell (inside of the menu on desktop) -->
      <div v-if="store.user" class="notifications-container notifications-container--desktop">
        <button class="bell-btn" @click="toggleNotifications" aria-label="Notificaciones">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="bell-icon">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
          </svg>
          <span v-if="unreadCount > 0" class="badge"></span>
        </button>

        <div v-if="isNotificationsOpen" class="notifications-dropdown">
          <div class="notifications-header">
            <h4>Notificaciones</h4>
            <button class="close-notif-btn" @click="isNotificationsOpen = false" aria-label="Cerrar">×</button>
          </div>
          <div class="notifications-list">
            <div v-if="notifications.length === 0" class="no-notifications">
              Sin notificaciones
            </div>
            <div v-else v-for="notif in notifications" :key="notif.id" :class="['notif-item', { 'unread': !notif.is_read }]">
              <p class="notif-message">{{ notif.message }}</p>
              <span class="notif-date">{{ new Date(notif.created_at).toLocaleString('es-ES') }}</span>
            </div>
          </div>
        </div>
      </div>

      <RouterLink v-if="!store.user" to="/login" class="login-btn" @click="isMenuOpen = false">
        Login
      </RouterLink>
      
      <RouterLink v-else to="/perfil" class="user-avatar" @click="isMenuOpen = false">
        {{ store.user.name.charAt(0).toUpperCase() }}
      </RouterLink>
    </nav>
  </header>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue'
import { store } from '../store' 
import { api } from '../services/api'

const isScrolled = ref(false)
const isMenuOpen = ref(false)
const isNotificationsOpen = ref(false)
const notifications = ref([])

const isAdmin = computed(() => (store.user?.role || '') === 'ADMINISTRADOR')

const unreadCount = computed(() => {
  return notifications.value.filter(n => !n.is_read).length
})

const handleScroll = () => { 
  isScrolled.value = window.scrollY > 50 
}

const fetchNotifications = async () => {
  if (!store.user) return
  try {
    const res = await api.get('/api/notifications')
    notifications.value = res.data || []
  } catch (e) {
    console.error('Error fetching notifications:', e)
  }
}

const toggleNotifications = async () => {
  isNotificationsOpen.value = !isNotificationsOpen.value
  if (isNotificationsOpen.value && unreadCount.value > 0) {
    try {
      await api.post('/api/notifications/read')
      notifications.value.forEach(n => n.is_read = 1)
    } catch (e) {
      console.error('Error marking notifications as read:', e)
    }
  }
}

const closeNotifications = (e) => {
  if (!e.target.closest('.notifications-container')) {
    isNotificationsOpen.value = false;
  }
}

watch(() => store.user, (newUser) => {
  if (newUser) {
    fetchNotifications()
  } else {
    notifications.value = []
  }
}, { immediate: true })

let intervalId = null

onMounted(() => {
  window.addEventListener('scroll', handleScroll)
  window.addEventListener('click', closeNotifications)
  fetchNotifications()
  intervalId = setInterval(fetchNotifications, 20000)
})

onUnmounted(() => {
  window.removeEventListener('scroll', handleScroll)
  window.removeEventListener('click', closeNotifications)
  if (intervalId) clearInterval(intervalId)
})
</script>

<style scoped>
/* --- HEADER BASE --- */
.nav {
  position: fixed; top: 0; left: 0; width: 100%;
  display: flex; align-items: center; justify-content: space-between;
  padding: 0 8%; height: 80px; z-index: 1000; box-sizing: border-box;
  transition: all 0.4s ease; background-color: rgba(26, 35, 61, 0.98); backdrop-filter: blur(10px);
  /* ELIMINADO overflow: hidden de aquí para que el menú móvil funcione */
}

.nav.scrolled {
  height: 65px;
  background-color: rgba(22, 33, 62, 0.9);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
}

.brand { display: flex; align-items: center; gap: 15px; }

/* El overflow: hidden lo aplicamos SOLO al contenedor del logo */
.brand-logo-container {
  display: flex; align-items: center;
  height: 80px; 
  overflow: hidden; 
}
.brand-title { font-weight: 800; color: #ffffff; font-size: 1.3rem; margin: 0; line-height: 1.1; }

.brand-subtitle { font-size: 0.75rem; color: #ff9f43; margin: 0; font-weight: 700; text-transform: uppercase; }
.brand-link {

  text-decoration: none !important;

  pointer-events: auto;

  z-index: 1001;

}
.nav.scrolled .brand-logo-container { height: 65px; }

.nav-logo {
  height: 70px; width: auto;
  transition: all 0.4s ease;
  filter: drop-shadow(0 4px 8px rgba(0,0,0,0.3));
  pointer-events: none; 
}
.nav.scrolled .nav-logo { height: 55px; }

/* --- NAVEGACIÓN --- */
.nav-links { display: flex; align-items: center; gap: 2rem; list-style: none; padding: 0; margin: 0; }
.nav-links a { text-decoration: none; color: #ffffff; font-weight: 600; font-size: 0.95rem; transition: 0.3s; }
.nav-links a:hover { color: #ff9f43; }

/* Estilo para la palabra Carrito */
.cart-link {
  color: #ff9f43 !important; /* Color resaltado para el carrito */
}

.admin-link {
  color: #9be7ff !important;
  font-weight: 800;
}

.login-btn {
  background: linear-gradient(135deg, #ff9f43, #ff6b6b);
  color: white !important; padding: 10px 25px; border-radius: 50px; font-weight: 800 !important;
}

.user-avatar {
  width: 42px; height: 42px;
  background: linear-gradient(135deg, #ff9f43, #ff6b6b);
  color: white !important; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-weight: 800; font-size: 1.2rem;
  border: 2px solid rgba(255, 255, 255, 0.3);
}

/* --- MENU TOGGLE --- */
.menu-toggle {
  display: none; flex-direction: column; gap: 6px; background: none; border: none; cursor: pointer; z-index: 1100;
}
.bar { width: 30px; height: 3px; background-color: #ffffff; border-radius: 2px; transition: 0.3s; }

/* --- RESPONSIVE --- */
@media (max-width: 992px) {
  .menu-toggle { display: flex; }

  .nav-links {
    position: fixed; top: 0; left: 0; width: 100%; height: 100vh; background-color: #1a233d;
    flex-direction: column; justify-content: center; align-items: center; gap: 2.5rem;
    opacity: 0; visibility: hidden; transform: translateY(-20px); transition: all 0.4s ease-in-out;
  }
  .nav-links.active { opacity: 1; visibility: visible; transform: translateY(0); }
  
  /* Animación del botón hamburguesa */
  .menu-open .bar:nth-child(1) { transform: translateY(9px) rotate(45deg); }
  .menu-open .bar:nth-child(2) { opacity: 0; }
  .menu-open .bar:nth-child(3) { transform: translateY(-9px) rotate(-45deg); }
}

/* --- NOTIFICACIONES --- */
.notifications-container {
  position: relative;
  display: flex;
  align-items: center;
}

/* Visibilidad de las campanas (móvil vs desktop) */
.notifications-container--mobile {
  display: none;
}
.notifications-container--desktop {
  display: flex;
}

@media (max-width: 992px) {
  .notifications-container--desktop {
    display: none;
  }
  .notifications-container--mobile {
    display: flex;
    margin-left: auto;
    margin-right: 20px;
    z-index: 1001; /* Queda visible al lado del botón de menú */
  }
}

.bell-btn {
  background: none;
  border: none;
  color: #ffffff;
  cursor: pointer;
  padding: 8px;
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: color 0.3s;
}

.bell-btn:hover {
  color: #ff9f43;
}

.bell-icon {
  width: 22px;
  height: 22px;
}

.badge {
  position: absolute;
  top: 5px;
  right: 5px;
  width: 10px;
  height: 10px;
  background-color: #ff6b6b;
  border-radius: 50%;
  border: 1.5px solid #1a233d;
}

.notifications-dropdown {
  position: absolute;
  top: 50px;
  right: -10px;
  width: 320px;
  max-height: 400px;
  background: rgba(26, 35, 61, 0.95);
  backdrop-filter: blur(15px);
  -webkit-backdrop-filter: blur(15px);
  border: 1px solid rgba(255, 255, 255, 0.15);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
  border-radius: 12px;
  z-index: 1050;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.notifications-header {
  padding: 12px 16px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.notifications-header h4 {
  margin: 0;
  font-size: 0.95rem;
  color: #ffffff;
  font-weight: 700;
}

.close-notif-btn {
  display: none;
  background: none;
  border: none;
  color: #94a3b8;
  font-size: 1.5rem;
  cursor: pointer;
  line-height: 1;
  padding: 0 4px;
}

.close-notif-btn:hover {
  color: #ffffff;
}

.notifications-list {
  overflow-y: auto;
  flex: 1;
}

.no-notifications {
  padding: 24px;
  text-align: center;
  color: #94a3b8;
  font-size: 0.85rem;
  font-style: italic;
}

.notif-item {
  padding: 12px 16px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
  transition: background-color 0.2s;
  text-align: left;
}

.notif-item:hover {
  background-color: rgba(255, 255, 255, 0.03);
}

.notif-item.unread {
  background-color: rgba(255, 159, 67, 0.05);
}

.notif-message {
  margin: 0 0 6px 0;
  font-size: 0.85rem;
  color: #f1f5f9;
  line-height: 1.4;
}

.notif-date {
  font-size: 0.72rem;
  color: #94a3b8;
}

/* Pantalla completa para móvil */
@media (max-width: 768px) {
  .notifications-dropdown {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    max-height: 100vh;
    border-radius: 0;
    border: none;
    z-index: 2000;
    background: #16213e; /* Fondo sólido oscuro premium para lectura clara */
  }

  .notifications-header {
    padding: 18px 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  }

  .notifications-header h4 {
    font-size: 1.15rem;
  }

  .close-notif-btn {
    display: block;
    font-size: 2rem;
    padding: 4px 8px;
    color: #ffffff;
    margin-right: 55px; /* Alineado en la misma posición de la campana, evitando solapamiento con el menú */
  }

  .notifications-list {
    padding: 10px 0;
  }

  .notif-item {
    padding: 18px 20px;
  }

  .notif-message {
    font-size: 0.95rem;
    margin-bottom: 8px;
  }

  .notif-date {
    font-size: 0.78rem;
  }
}
</style>