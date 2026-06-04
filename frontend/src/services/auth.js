import { api } from './api'

export function decodeJwtPayload(token) {
  if (!token) return null

  try {
    const [, payload] = token.split('.')
    if (!payload) return null

    const normalized = payload.replace(/-/g, '+').replace(/_/g, '/')
    const padded = normalized.padEnd(Math.ceil(normalized.length / 4) * 4, '=')
    return JSON.parse(atob(padded))
  } catch {
    return null
  }
}

export async function loginRequest(email, password) {
  const data = await api.post('/api/auth/login', { email, password })
  if (data.csrf_token) {
    localStorage.setItem('csrf_token', data.csrf_token)
  }
  return data
}

export async function register(payload) {
  return api.post('/api/register', payload)
}
