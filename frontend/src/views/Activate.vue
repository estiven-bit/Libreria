    <template>
    <div class="activate-container">
        <div class="card shadow">
        <div v-if="loading" class="loading">
            <div class="spinner"></div>
            <p>Verificando tu cuenta...</p>
        </div>

        <div v-else-if="success" class="result success">
            <div class="icon">✅</div>
            <h2>¡Cuenta Activada!</h2>
            <p>Ya puedes realizar compras en Librería Gabi.</p>
            <a href="/" class="btn-primary">Ir al Inicio</a>
        </div>

        <div v-else class="result error">
            <div class="icon">❌</div>
            <h2>Error de Activación</h2>
            <p>{{ errorMessage || 'El enlace es inválido o ya ha expirado.' }}</p>
            <RouterLink to="/" class="btn-outline">Volver al Inicio</RouterLink>
        </div>
        </div>
    </div>
    </template>

    <script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
// 1. IMPORTACIÓN CORREGIDA: Importamos el objeto 'store' directamente
import { store } from '../store'; 

const route = useRoute();
const loading = ref(true);
const success = ref(false);
const errorMessage = ref('');
const hasStarted = ref(false);

onMounted(async () => {
    // Evita que Vue ejecute esto dos veces en modo desarrollo
    if (hasStarted.value) return; 
    hasStarted.value = true;

    const token = route.query.token;
    if (!token) {
        loading.value = false;
        errorMessage.value = "Token no encontrado en la URL.";
        return;
    }

    try {
        const baseUrl = 'http://localhost/libreria_gabi/backend/public/index.php/api/activate';
        const response = await fetch(`${baseUrl}?token=${token}`);
        
        const contentType = response.headers.get("content-type");
        if (!contentType || !contentType.includes("application/json")) {
            throw new Error("El servidor no respondió con un JSON válido.");
        }

        const data = await response.json();
        
        if (data.success) {
            success.value = true;
            
            // 2. LÓGICA DE LOGIN AUTOMÁTICO
            if (data.token && data.user) {
                // Usamos el método setAuth de tu nuevo store
                // Esto guarda automáticamente en localStorage ('user' y 'token')
                store.setAuth(data.user, data.token);
                
                console.log("Sesión iniciada automáticamente:", data.user.name);
            }
        } else {
            // Error enviado desde el PHP (ej: Token expirado)
            errorMessage.value = data.message;
        }
    } catch (error) {
        console.error("Error en la activación:", error);
        errorMessage.value = "Error de conexión. Revisa la consola o verifica la cuenta.";
    } finally {
        loading.value = false;
    }
});
</script>

    <style scoped>
    .activate-container {
    display: flex; justify-content: center; align-items: center;
    min-height: 80vh; padding: 20px;
    }
    .card {
    background: white; padding: 40px; border-radius: 20px;
    text-align: center; max-width: 400px; width: 100%;
    }
    .icon { font-size: 4rem; margin-bottom: 20px; }
    h2 { color: #1a233d; margin-bottom: 15px; }
    p { color: #64748b; margin-bottom: 30px; }
    .btn-primary {
    background: linear-gradient(135deg, #ff9f43, #ff6b6b);
    color: white; padding: 12px 30px; border-radius: 50px;
    text-decoration: none; font-weight: 700; display: inline-block;
    }
    .btn-outline { color: #ff9f43; text-decoration: none; font-weight: 600; }
    .spinner {
    border: 4px solid rgba(0,0,0,0.1); width: 36px; height: 36px;
    border-radius: 50%; border-left-color: #ff9f43;
    animation: spin 1s linear infinite; margin: 0 auto 20px;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    </style>