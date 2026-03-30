import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
    root: 'frontend', // Vite entra aquí a buscar el index.html
    plugins: [vue()],
    logLevel: 'info',
    clearScreen: false,
    build: {
        outDir: '../dist', // Los archivos finales saldrán a una carpeta dist fuera de frontend
        emptyOutDir: true,
    },
    server: {
        host: true,
        port: 5173,
        watch: {
        usePolling: true,
        }
    }
    })