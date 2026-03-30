<template>
  <section class="about-section">
    <div class="video-background">
      <video autoplay muted loop playsinline class="video-bg">
        <source src="../assets/video-fondo-sobre-nosotros.mp4" type="video/mp4">
        Tu navegador no soporta el video.
      </video>
      <div class="video-overlay"></div>
    </div>

    <div class="about-container">
      <div class="about-header">
        <h1>Nuestra Historia</h1>
        <div class="accent-line"></div>
        <p class="intro-text">
          En <strong>Librería Gabi</strong>, no solo vendemos libros; abrimos puertas a mundos desconocidos. 
          Nacimos en el corazón de Yantzaza con el sueño de convertir cada lectura en una aventura inolvidable.
        </p>
      </div>

      <div class="about-grid">
        <div class="about-card glass">
          <div class="icon">🎯</div>
          <h3>Misión</h3>
          <p>
            Fomentar el amor por la lectura en cada hogar ecuatoriano, ofreciendo una curaduría 
            excepcional de cuentos y literatura.
          </p>
        </div>

        <div class="about-card glass">
          <div class="icon">👁️</div>
          <h3>Visión</h3>
          <p>
            Ser la librería digital referente en el Ecuador, reconocida por nuestra calidez humana y 
            nuestra comunidad de lectores.
          </p>
        </div>

        <div class="about-card glass">
          <div class="icon">🤝</div>
          <h3>Valores</h3>
          <p>
            Nos mueve la pasión por la cultura, la honestidad y el compromiso con nuestra gran familia lectora.
          </p>
        </div>
      </div>

      <div class="contact-card glass">
        <h3>📍 Ubicación & Contacto</h3>
        <div class="contact-info">
          <p>Estamos en <strong>Yantzaza, Ecuador</strong>.</p>
          <div class="details">
            <span>📧 info@libreria-gabi.ec</span>
            <span>📞 +593 99 000 0000</span>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
/* --- VIDEO DE FONDO --- */
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

/* --- CONTENEDOR (Padding superior ajustado y menos inferior) --- */
.about-section {
  min-height: 100vh;
  padding: 60px 5% 40px; /* Reducido de 100px a 60px arriba y 40px abajo */
  color: white;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  display: flex;
  align-items: center; /* Centra el contenido verticalmente si sobra espacio */
}

.about-container {
  max-width: 1100px;
  margin: 0 auto;
  width: 100%;
}

/* --- HEADER --- */
.about-header {
  text-align: center;
  margin-bottom: 40px; /* Reducido de 60px a 40px */
}

h1 {
  font-size: 2.8rem; /* Ligeramente más pequeño */
  font-weight: 800;
  text-shadow: 2px 2px 10px rgba(0,0,0,0.5);
  margin: 0;
}

.accent-line {
  width: 60px;
  height: 4px;
  background: #ff9f43;
  margin: 15px auto;
  border-radius: 5px;
}

.intro-text {
  font-size: 1.1rem;
  max-width: 700px;
  margin: 0 auto;
  line-height: 1.5;
}

/* --- GRID MISION/VISION (Tarjetas más bajas) --- */
.about-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 20px; /* Reducido de 30px a 20px */
  margin-bottom: 30px; /* Reducido de 50px a 30px */
}

.glass {
  background: rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(15px);
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 20px;
  padding: 25px 30px; /* Más compacto internamente */
  box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

.icon {
  font-size: 2rem;
  margin-bottom: 10px;
}

h3 {
  color: #ff9f43;
  font-size: 1.3rem;
  margin-bottom: 10px;
}

/* --- CONTACT CARD --- */
.contact-card {
  text-align: center;
  max-width: 600px;
  margin: 0 auto;
  padding: 20px; /* Más compacto */
}

.contact-info .details {
  display: flex;
  justify-content: center;
  gap: 25px;
  margin-top: 10px;
  font-weight: 700;
}

/* --- RESPONSIVE --- */
@media (max-width: 768px) {
  h1 { font-size: 2rem; }
  .about-section { padding: 40px 5% 20px; } /* Aún más compacto en móvil */
  .contact-info .details { flex-direction: column; gap: 5px; }
}
</style>