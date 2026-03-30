<template>
  <div class="login-page">
    <div class="video-background">
      <video autoplay muted loop playsinline class="video-bg">
        <source src="../assets/video-fondo-login.mp4" type="video/mp4">
        Tu navegador no soporta el video.
      </video>
      <div class="video-overlay"></div>
    </div>

    <div class="login-layout">
      <div class="login-card">
        <div class="login-header">
          <h2>¡Hola de nuevo!</h2>
          <div class="accent-line"></div>
          <p class="subtitle">Entra para seguir explorando cuentos mágicos</p>
        </div>

        <form @submit.prevent="handleLogin" class="login-form">
          <div class="form-group">
            <label>Correo electrónico</label>
            <div class="input-wrapper">
              <input 
                v-model="email" 
                type="email" 
                placeholder="ejemplo@correo.com" 
                required
              />
            </div>
          </div>

          <div class="form-group">
            <label>Contraseña</label>
            <div class="input-wrapper">
              <input 
                v-model="password" 
                type="password" 
                placeholder="••••••••" 
                required
              />
            </div>
          </div>

          <button type="submit" class="btn-submit" :disabled="loading">
            <span v-if="!loading">Iniciar Sesión</span>
            <span v-else class="loader">Cargando...</span>
          </button>
        </form>

        <div class="register-cta">
          <p>¿Aún no tienes cuenta en la librería?</p>
          <RouterLink to="/registro" class="link-to-register">
            Crea tu cuenta aquí y obtén un 10% de descuento
          </RouterLink>
        </div>
      </div>
      
      <div class="spacer"></div>
    </div>
  </div>
</template>

<script setup>
import { ref, inject } from 'vue'
import { useRouter } from 'vue-router'

const store = inject('store')
const router = useRouter()

const email = ref('')
const password = ref('')
const loading = ref(false)

const handleLogin = async () => {
  loading.value = true
  try {
    await store.login({ email: email.value, password: password.value })
    router.push('/') 
  } catch (error) {
    alert("Error al iniciar sesión: " + error.message)
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
/* --- ESTILOS DEL VIDEO (MÁS BRILLO) --- */
.video-background {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100vh;
  z-index: -1;
  overflow: hidden;
}

.video-bg {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.video-overlay {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  /* Reducido de 0.65 a 0.3 para más brillo */
  background: rgba(15, 23, 42, 0.3); 
}

/* --- LAYOUT --- */
.login-page {
  min-height: 100vh;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.login-layout {
  display: grid;
  grid-template-columns: 1fr 1fr; /* Dividimos la pantalla en dos */
  min-height: 100vh;
  padding: 0 8%;
  align-items: center;
}

/* --- TARJETA A LA IZQUIERDA --- */
.login-card {
  background: rgba(255, 255, 255, 0.15); /* Ligeramente más opaca para compensar el brillo del video */
  backdrop-filter: blur(20px) saturate(160%);
  -webkit-backdrop-filter: blur(20px) saturate(160%);
  padding: 50px;
  border-radius: 24px;
  border: 1px solid rgba(255, 255, 255, 0.2);
  box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
  width: 100%;
  max-width: 450px;
  color: white;
  justify-self: start; /* Empuja hacia la izquierda del grid */
}

.login-header h2 {
  font-size: 2.2rem;
  font-weight: 800;
  margin: 0;
  color: #ffffff;
}

.accent-line {
  width: 60px;
  height: 5px;
  background: #ff9f43;
  margin: 20px 0; /* Alineada a la izquierda */
  border-radius: 2px;
}

.subtitle {
  color: #ffffff;
  font-size: 1rem;
  margin-bottom: 35px;
  font-weight: 500;
  text-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

/* --- FORMULARIO --- */
.login-form { text-align: left; }
.form-group { margin-bottom: 25px; }

label {
  display: block;
  font-weight: 800;
  margin-bottom: 10px;
  color: #ff9f43;
  font-size: 0.85rem;
  text-transform: uppercase;
  letter-spacing: 1px;
}

input {
  width: 100%;
  padding: 16px;
  background: rgba(255, 255, 255, 0.95);
  border: 2px solid transparent;
  border-radius: 12px;
  font-size: 1rem;
  color: #1e293b;
  box-sizing: border-box;
}

input:focus {
  outline: none;
  border-color: #ff9f43;
  background: #ffffff;
}

/* --- BOTÓN --- */
.btn-submit {
  width: 100%;
  background: linear-gradient(135deg, #ff9f43, #ff6b6b);
  color: white;
  padding: 18px;
  border: none;
  border-radius: 50px;
  font-weight: 800;
  font-size: 1.1rem;
  cursor: pointer;
  transition: all 0.3s ease;
  text-transform: uppercase;
  letter-spacing: 1.5px;
  box-shadow: 0 10px 20px rgba(255, 107, 107, 0.4);
}

.btn-submit:hover:not(:disabled) {
  transform: scale(1.02);
  filter: brightness(1.1);
}

/* --- PIE --- */
.register-cta {
  margin-top: 35px;
  padding-top: 25px;
  border-top: 1px solid rgba(255, 255, 255, 0.2);
}

.register-cta p { color: #ffffff; font-weight: 500; }

.link-to-register {
  color: #ff9f43;
  font-weight: 800;
  text-decoration: none;
  display: inline-block;
  margin-top: 10px;
  background: rgba(255, 159, 67, 0.1);
  padding: 5px 12px;
  border-radius: 8px;
}

/* --- RESPONSIVE --- */
@media (max-width: 900px) {
  .login-layout {
    grid-template-columns: 1fr;
    justify-items: center;
    padding: 0 20px;
  }
  .login-card {
    justify-self: center;
    padding: 35px 25px;
  }
  .spacer { display: none; }
}
</style>