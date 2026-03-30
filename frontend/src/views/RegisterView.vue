<template>
  <section class="auth-container">
    <div class="video-background">
      <video autoplay muted loop playsinline id="video-fondo">
        <source src="../assets/video-fondo-registrar.mp4" type="video/mp4">
        Tu navegador no soporta el video.
      </video>
      <div class="video-overlay"></div>
    </div>

    <div class="form-card">
      <div class="form-header">
        <span class="form-icon">✨</span>
        <h2>Crear cuenta</h2>
        <p>Únete a nuestra comunidad de lectores</p>
      </div>

      <div class="form-body">
        <div class="input-group">
          <label>Nombre completo</label>
          <input v-model="name" class="input" placeholder="Ej. Gabriel García" />
        </div>

        <div class="input-group">
          <label>Correo electrónico</label>
          <input v-model="email" class="input" type="email" placeholder="tu@email.com" />
        </div>

        <div class="input-group">
          <label>Teléfono Celular</label>
          <input v-model="phone" class="input" type="tel" placeholder="Ej. 0990000000" />
        </div>

        <div class="input-group">
          <label>Contraseña</label>
          <input v-model="password" class="input" type="password" placeholder="Mín. 8 carac, Mayús y Núm" />
        </div>

        <div class="input-group">
          <label>Confirmar contraseña</label>
          <input v-model="confirmPassword" class="input" type="password" placeholder="••••••••" />
        </div>

        <button class="btn-submit" @click="handleRegister">
          <span>Registrarme</span>
        </button>

        <div class="messages">
          <p v-if="message" class="success">✅ {{ message }}</p>
          <p v-if="error" class="error">⚠️ {{ error }}</p>
        </div>
      </div>

      <div class="form-footer">
        <p>¿Ya tienes cuenta? <RouterLink to="/login">Inicia sesión aquí</RouterLink></p>
      </div>
    </div>
  </section>
</template>

<script setup>
import { ref, inject } from 'vue'
import { useRouter } from 'vue-router'
import { register } from '../services/auth'

// Inicialización de herramientas
const store = inject('store')
const router = useRouter()

// Estados del formulario
const name = ref('')
const email = ref('')
const phone = ref('') 
const password = ref('')
const confirmPassword = ref('')
const message = ref('')
const error = ref('')

const handleRegister = async () => {
  // Limpiar mensajes previos
  error.value = ''
  message.value = ''

  // 1. Validaciones de front-end
  if (!name.value || !email.value || !phone.value || !password.value) {
    error.value = 'Todos los campos son obligatorios';
    return;
  }

  if (!email.value.includes('@')) { 
    error.value = 'Email inválido'; 
    return 
  }

  if (phone.value.length < 10) {
    error.value = 'El teléfono debe tener al menos 10 dígitos';
    return
  }

  const passwordRegex = /^(?=.*[A-Z])(?=.*\d).{8,}$/;
  if (!passwordRegex.test(password.value)) {
    error.value = 'La contraseña requiere: 8 caracteres, una mayúscula y un número';
    return
  }

  if (password.value !== confirmPassword.value) { 
    error.value = 'Las contraseñas no coinciden'; 
    return 
  }

  try {
    // 2. Llamada a la API
    const res = await register({
      name: name.value,
      email: email.value,
      phone: phone.value,
      password: password.value,
      confirm_password: confirmPassword.value,
    })
    
    // 3. Manejo de respuesta exitosa
    if (res && res.success) {      
      
      // Guardamos el CSRF si viene, pero NO llamamos a store.setAuth
      if(res.csrf_token) {
        localStorage.setItem('csrf_token', res.csrf_token);
      }
      
      // Mensaje informativo claro
      message.value = '¡Registro exitoso! Por favor, revisa tu correo para activar tu cuenta antes de iniciar sesión.';
      
      // IMPORTANTE: No ejecutamos store.setAuth(res.user, res.token) aquí.
      // Al no ejecutarlo, el 'store.user' sigue siendo null y el Header no cambia.

      // Redirección con un pequeño retraso
      setTimeout(() => {
        // Redirigimos a la home, pero como no hay user en el store, 
        // el Header mostrará correctamente "Login / Registro"
        router.push('/');
      }, 4000); // 4 segundos para que le de tiempo a leer que debe revisar el email
      
    } else {
      error.value = res.error || res.message || 'No se pudo completar el registro.';
    }

  } catch (err) { 
    console.error("Detalle técnico del error:", err);
    if (err.message && err.message !== "Failed to fetch") {
        error.value = err.message;
    } else {
        error.value = 'No hay conexión con el servidor. Verifica que el backend esté encendido.';
    }
  }
}
</script>

<style scoped>
/* --- TUS ESTILOS MANTENIDOS INTACTOS --- */
.video-background {
  position: fixed;
  top: 0; left: 0;
  width: 100%; height: 100vh;
  z-index: -2;
  overflow: hidden;
}

#video-fondo {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.video-overlay {
  position: absolute;
  top: 0; left: 0;
  width: 100%; height: 100%;
  background: rgba(15, 23, 42, 0.4);
  z-index: -1;
}

.auth-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: flex-end; 
  padding: 100px 10% 40px 0;
  box-sizing: border-box;
}

.form-card {
  background: rgba(30, 41, 59, 0.7);
  backdrop-filter: blur(15px);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 20px;
  padding: 30px;
  width: 100%;
  max-width: 400px;
  box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
  animation: slideIn 0.8s ease-out;
}

@keyframes slideIn {
  from { opacity: 0; transform: translateX(30px); }
  to { opacity: 1; transform: translateX(0); }
}

.form-header { text-align: center; margin-bottom: 20px; }
.form-icon { font-size: 2rem; margin-bottom: 8px; display: block; }
.form-header h2 { color: #f8fafc; font-size: 1.6rem; font-weight: 700; margin: 0; }
.form-header p { color: #cbd5e1; font-size: 0.85rem; margin-top: 5px; }

.form-body { display: flex; flex-direction: column; gap: 12px; }
.input-group { display: flex; flex-direction: column; gap: 5px; }
.input-group label { color: #94a3b8; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }

.input {
  background: rgba(15, 23, 42, 0.7);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 10px;
  padding: 12px 14px;
  color: #f1f5f9;
  font-size: 0.95rem;
  transition: 0.3s;
}

.input:focus {
  outline: none;
  border-color: #38bdf8;
  background: rgba(15, 23, 42, 0.9);
}

.btn-submit {
  margin-top: 15px;
  background: linear-gradient(135deg, #ff9f43 0%, #ff6b6b 100%);
  color: white;
  padding: 14px;
  border-radius: 10px;
  border: none;
  font-weight: 700;
  text-transform: uppercase;
  cursor: pointer;
  transition: 0.3s;
}

.btn-submit:hover {
  transform: translateY(-2px);
  filter: brightness(1.1);
  box-shadow: 0 10px 15px rgba(255, 107, 107, 0.3);
}

.messages { margin-top: 15px; text-align: center; font-size: 0.9rem; }
.success { color: #4ade80; }
.error { color: #f87171; }

.form-footer {
  margin-top: 20px;
  text-align: center;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  padding-top: 15px;
}
.form-footer p { color: #f1f5f9; font-size: 0.9rem; }
.form-footer a { color: #38bdf8; text-decoration: none; font-weight: 600; }

@media (max-width: 992px) {
  .auth-container {
    justify-content: center;
    padding: 100px 20px 40px 20px;
  }
}
</style>