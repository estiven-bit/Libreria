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
import { ref, onMounted, onUnmounted } from 'vue'
// 1. Importamos el store directamente (ajusta la ruta si es necesario)
import { store } from '../store' 

const isScrolled = ref(false)
const isMenuOpen = ref(false)

const handleScroll = () => { 
  isScrolled.value = window.scrollY > 50 
}

onMounted(() => window.addEventListener('scroll', handleScroll))
onUnmounted(() => window.removeEventListener('scroll', handleScroll))
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
  height: 65px; width: auto;
  transition: all 0.4s ease;
  filter: drop-shadow(0 4px 8px rgba(0,0,0,0.3));
  pointer-events: none; 
}
.nav.scrolled .nav-logo { height: 50px; }

/* --- NAVEGACIÓN --- */
.nav-links { display: flex; align-items: center; gap: 2rem; list-style: none; padding: 0; margin: 0; }
.nav-links a { text-decoration: none; color: #ffffff; font-weight: 600; font-size: 0.95rem; transition: 0.3s; }
.nav-links a:hover { color: #ff9f43; }

/* Estilo para la palabra Carrito */
.cart-link {
  color: #ff9f43 !important; /* Color resaltado para el carrito */
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
</style>