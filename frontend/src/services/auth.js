import { api } from './api'
import { store } from '../store'

export async function login(email, password) {
  const data = await api.post('/api/auth/login', { email, password })
  localStorage.setItem('csrf_token', data.csrf_token)
  store.setAuth(data.user, data.token)
  return data
}

export async function register(payload) {
  return api.post('/api/register', payload)
}
