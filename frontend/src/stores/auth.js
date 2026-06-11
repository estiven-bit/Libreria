import { defineStore } from 'pinia'
import { ref } from 'vue'
import { store as legacyStore } from '../store'
import { api } from '../services/api'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(JSON.parse(localStorage.getItem('user') || 'null'))
  const token = ref(null) // Las sesiones ahora son HttpOnly en el BFF

  let hydrationPromise = null

  function applyUserFromClaims(claims) {
    if (!claims) return false
    const normalizedUser = {
      id: parseInt(claims.sub),
      name: claims.name,
      email: claims.email,
      role: claims.role === 'admin' ? 'ADMINISTRADOR' : 'USUARIO',
      is_active: 1,
    }
    localStorage.setItem('user', JSON.stringify(normalizedUser))
    user.value = normalizedUser
    legacyStore.user = normalizedUser
    return true
  }

  async function hydrate() {
    if (hydrationPromise) return hydrationPromise

    hydrationPromise = (async () => {
      try {
        const res = await fetch(`${api.BFF_BASE}/bff/me`, {
          method: 'GET',
          credentials: 'include',
        })
        if (!res.ok) {
          throw new Error('Error de comunicación con el BFF')
        }
        const data = await res.json()
        if (data.authenticated && data.user) {
          applyUserFromClaims(data.user)
          return true
        } else {
          await logout({ redirectHome: false })
          return false
        }
      } catch (error) {
        console.error('Error al hidratar sesión BFF:', error)
        return !!user.value
      } finally {
        hydrationPromise = null
      }
    })()

    return hydrationPromise
  }

  async function login(email, password) {
    throw new Error('Flujo de login por contraseña deshabilitado en favor de SSO.')
  }

  async function logout({ redirectHome = true } = {}) {
    try {
      await fetch(`${api.BFF_BASE}/bff/logout`, {
        method: 'POST',
        credentials: 'include',
      })
    } catch (error) {
      console.error('BFF logout falló:', error)
    }
    legacyStore.logout()
    localStorage.removeItem('user')
    user.value = null

    if (!redirectHome) return

    try {
      const { default: router } = await import('../router')
      if (router.currentRoute.value.name !== 'home') {
        await router.push({ name: 'home' })
      }
    } catch {
      if (window.location.pathname !== '/') {
        window.location.assign('/')
      }
    }
  }

  return { user, token, hydrate, applyUserFromClaims, login, logout }
})
