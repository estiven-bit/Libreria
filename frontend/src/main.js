import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import { store } from './store'
import './styles/main.css'

// Punto de entrada SPA.
const app = createApp(App)
app.provide('store', store)
app.use(router)
app.mount('#app')
