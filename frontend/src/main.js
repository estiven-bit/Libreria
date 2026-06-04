import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import { store } from './store'
import { createPinia } from 'pinia'
import { useAuthStore } from './stores/auth'
import './styles/main.css'

// Punto de entrada SPA.
const app = createApp(App)
const pinia = createPinia()
app.use(pinia)
useAuthStore().hydrate()
app.provide('store', store)
app.use(router)
app.mount('#app')
