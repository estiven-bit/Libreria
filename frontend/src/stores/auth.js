import { defineStore } from 'pinia'
import { ref } from 'vue'
import { loginRequest } from '../services/auth'
import { store as legacyStore } from '../store'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(JSON.parse(localStorage.getItem('user') || 'null'))
  const token = ref(localStorage.getItem('token'))

  function hydrate() {
    user.value = JSON.parse(localStorage.getItem('user') || 'null')
    token.value = localStorage.getItem('token')
  }

  async function login(email, password) {
    const data = await loginRequest(email, password)
    legacyStore.setAuth(data.user, data.token)
    user.value = data.user
    token.value = data.token
    return data
  }

  function logout() {
    legacyStore.logout()
    localStorage.removeItem('csrf_token')
    user.value = null
    token.value = null
  }

  return { user, token, hydrate, login, logout }
})
