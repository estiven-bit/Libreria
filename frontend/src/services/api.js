// src/services/api.js

// Usamos la ruta que ya comprobamos que llega al PHP
const API_BASE = 'http://localhost/libreria_gabi/backend/public/index.php';

async function request(path, options = {}) {
    // Limpiamos el path para que no haya problemas de barras dobles
    const cleanPath = path.startsWith('/') ? path : `/${path}`;
    const url = `${API_BASE}${cleanPath}`;

    try {
        const res = await fetch(url, {
            ...options,
            headers: {
                'Content-Type': 'application/json',
                ...(options.headers || {})
            }
        });

        if (!res.ok) throw new Error('Error en la respuesta del servidor');
        return res.json();
    } catch (err) {
        console.error("Fallo en fetch:", err);
        throw err;
    }
}

// ESTA ES LA PARTE QUE TE FALTA (El export):
export const api = {
    get: (path) => request(path, { method: 'GET' }),
    post: (path, data) => request(path, { method: 'POST', body: JSON.stringify(data) }),
    // Puedes agregar put o delete si los necesitas
};