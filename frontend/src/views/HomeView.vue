<template>
  <div class="video-container">
    <video autoplay muted loop playsinline id="video-fondo">
      <source src="../assets/video-fondo-inicio.mp4" type="video/mp4">
      Tu navegador no soporta el video.
    </video>
    <div class="video-overlay"></div>
  </div>

  <section class="hero">
    <div class="hero-content">
      <h1 class="text-white">Libros infantiles que <br> inspiran sonrisas</h1>
      
      <p v-if="store.user" class="text-white welcome-msg">
        ¡Hola, {{ store.user.name }}! Qué bueno verte de nuevo.
      </p>
      <p class="text-white">Explora cuentos, aventuras y libros educativos para cada etapa.</p>
      
      <div class="hero-actions">
        <RouterLink class="btn btn-catalogo" to="/catalogo">Ver catálogo</RouterLink>
        
        <RouterLink v-if="!store.user" class="btn btn-registro" to="/registro">
          Crear cuenta
        </RouterLink>

        <RouterLink v-else class="btn btn-registro" to="/perfil">
          Mi Perfil
        </RouterLink>
      </div>
    </div>

    <div class="hero-card">
      <h3 class="text-with-border">Promoción</h3>
      <p class="text-with-border">Cupón de bienvenida: <br> <strong>GABI10</strong></p>
      <span class="discount-badge">10% de descuento</span>
    </div>
  </section>
</template>

<script setup>
// IMPORTANTE: Importamos el store para que el v-if funcione
import { store } from '../store'
</script>

<style scoped>
/* --- FONDO Y VIDEO --- */
.video-container {
  position: fixed;
  top: 0; left: 0;
  width: 100%; height: 100vh;
  z-index: -2; overflow: hidden;
}
#video-fondo { width: 100%; height: 100%; object-fit: cover; }
.video-overlay {
  position: fixed;
  top: 0; left: 0;
  width: 100%; height: 100vh;
  background: linear-gradient(to right, rgba(0,0,0,0.5) 0%, transparent 50%, rgba(0,0,0,0.5) 100%);
  z-index: -1;
}

/* --- HERO --- */
.hero {
  position: relative;
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 8%;
  color: #fff;
}
.hero-content { max-width: 550px; text-align: left; }
.text-white {
  color: #ffffff !important;
  text-shadow: 2px 2px 10px rgba(0, 0, 0, 0.8);
}
.welcome-msg {
  font-weight: 700;
  color: #ff9f43 !important;
  margin-bottom: 0.5rem;
  font-size: 1.5rem;
}
.hero-content h1 { font-size: 3.8rem; line-height: 1.1; margin-bottom: 1.5rem; }
.hero-content p { font-size: 1.3rem; margin-bottom: 2rem; }

/* --- BOTONES --- */
.hero-actions { display: flex; gap: 15px; }

.btn {
  padding: 14px 30px;
  border-radius: 50px;
  font-weight: 700;
  text-decoration: none;
  transition: all 0.3s ease;
  border: none;
  display: inline-block;
}

.btn-catalogo {
  background-color: #ff9f43;
  color: white;
  box-shadow: 0 4px 15px rgba(255, 159, 67, 0.4);
}

.btn-registro {
  background-color: #ffffff !important;
  border: 2px solid #ff9f43 !important;
  color: #ff9f43 !important;
  text-shadow: none !important;
  text-transform: uppercase;
  font-size: 0.9rem;
  letter-spacing: 0.5px;
}

.btn-registro:hover {
  background-color: #ff9f43 !important;
  color: #ffffff !important;
}

.btn:hover {
  transform: translateY(-3px);
  filter: brightness(1.1);
  box-shadow: 0 6px 20px rgba(0,0,0,0.3);
}

/* --- TARJETA PROMO --- */
.hero-card {
  background: rgba(255, 255, 255, 0.1); 
  backdrop-filter: blur(15px);
  border: 2px solid rgba(255, 255, 255, 0.4);
  border-radius: 30px;
  padding: 40px;
  width: 320px;
  text-align: center;
  box-shadow: 0 15px 35px rgba(0,0,0,0.4);
}
.text-with-border {
  color: white;
  text-shadow: -1px -1px 0 #000, 1px -1px 0 #000, -1px 1px 0 #000, 1px 1px 0 #000;
}
.discount-badge {
  display: block;
  background: #ff9f43;
  color: white;
  padding: 12px;
  border-radius: 50px;
  font-weight: 800;
}

/* --- SECCIÓN CATEGORÍAS --- */
.categories-section {
  position: relative;
  background: #ffffff;
  padding: 60px 5%;
  z-index: 10;
}
.categories-section h2 {
  text-align: center;
  font-size: 2.2rem;
  margin-bottom: 40px;
  color: #2d3436;
}
.categories-row {
  display: flex;
  justify-content: center;
  gap: 15px;
  overflow-x: auto;
  padding-bottom: 15px;
}
.category-card {
  background: #fff9e6;
  padding: 15px 25px;
  border-radius: 15px;
  font-weight: 700;
  color: #ff9f43;
  transition: all 0.3s;
  cursor: pointer;
  white-space: nowrap;
  border: 2px solid transparent;
}
.category-card:hover {
  background: #ff9f43;
  color: white;
  transform: translateY(-5px);
}

/* --- RESPONSIVE --- */
@media (max-width: 1024px) {
  .hero { flex-direction: column; text-align: center; gap: 50px; padding-top: 120px; }
  .hero-content { max-width: 100%; }
  .hero-actions { justify-content: center; }
  .hero-card { margin: 0 auto; }
}

@media (max-width: 768px) {
  .hero-content h1 { font-size: 2.5rem; }
  .hero-actions { flex-direction: column; }
  .btn { width: 100%; }
  .categories-row { justify-content: flex-start; }
}
</style>