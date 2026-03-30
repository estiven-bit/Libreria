<template>
  <footer class="footer">
    <div class="footer-container">
      <div class="footer-content">
        <div class="footer-section">
          <div class="footer-brand">
            <span class="brand-star">✨</span>
            <h4>Librería Gabi</h4>
          </div>
          <p class="footer-text">Acompañamos el crecimiento de tus pequeños con historias mágicas y aventuras inolvidables para soñar despiertos.</p>
        </div>

        <div class="footer-section">
          <h4>Contacto</h4>
          <ul class="footer-links">
            <li><span class="icon">📍</span> Yantzaza, Ecuador</li>
            <li><span class="icon">📧</span> info@libreria-gabi.com</li>
            <li><span class="icon">📞</span> +593 999 999 999</li>
          </ul>
        </div>

        <div class="footer-section">
          <h4>Síguenos</h4>
          <div class="social-icons">
            <a href="#" class="social-link">FB</a>
            <a href="#" class="social-link">IG</a>
            <a href="#" class="social-link">TK</a>
          </div>
          <p class="copyright">© 2026 Librería Gabi. <br> Todos los derechos reservados.</p>
        </div>
      </div>
    </div>
  </footer>
</template>

<style scoped>
.footer {
  /* Fondo y colores */
  background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
  color: #ffffff;
  padding: 60px 0;

  /* --- AQUÍ ESTÁ EL TRUCO PARA RELLENAR EL ANCHO --- */
  width: 100vw;           /* 100% del ancho de la ventana del navegador */
  position: relative;     
  left: 50%;
  right: 50%;
  margin-left: -50vw;     /* Lo saca a la fuerza hacia la izquierda */
  margin-right: -50vw;    /* Lo saca a la fuerza hacia la derecha */
}

.footer-container {
  /* El contenido dentro sí lo centramos para que no pegue a los bordes físicos */
  width: 100%;
  max-width: 1400px; 
  margin: 0 auto;
  padding: 0 5%; 
  box-sizing: border-box;
}

.footer-content {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 40px;
}

.footer-section {
  flex: 1;
  min-width: 280px;
}

/* --- TUS ESTILOS DE MARCA --- */
.footer-brand {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
}

.footer-brand h4 {
  margin: 0;
  color: #ff9f43;
  font-size: 1.6rem;
}

.footer-text {
  color: #d1d1d1;
  line-height: 1.7;
  max-width: 400px;
}

.footer-links {
  list-style: none;
  padding: 0;
}

.footer-links li {
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.social-icons {
  display: flex;
  gap: 15px;
  margin-top: 15px;
}

.social-link {
  width: 45px;
  height: 45px;
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,159,67,0.3);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #ff9f43;
  text-decoration: none;
  transition: 0.3s ease;
}

.social-link:hover {
  background: #ff9f43;
  color: #1a1a2e;
  transform: translateY(-5px);
}

.copyright {
  margin-top: 40px;
  border-top: 1px solid rgba(255,255,255,0.1);
  padding-top: 20px;
  font-size: 0.85rem;
  color: #888;
}

@media (max-width: 768px) {
  .nav-links {
    position: absolute;
    top: 72px;
    right: 16px;
    background: white;
    flex-direction: column;
    padding: 12px;
    border-radius: 12px;
    box-shadow: var(--shadow);
    display: none;
  }
}
</style>