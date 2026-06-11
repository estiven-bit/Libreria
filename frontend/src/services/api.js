const API_BASE = (import.meta.env.VITE_API_URL || 'https://libreria-backend-verdadero.vercel.app').replace(/\/$/, '')
const BFF_BASE = API_BASE.includes('/bff/') ? API_BASE.split('/bff/')[0] : API_BASE

function isAuthFailureEndpoint(path) {
  const p = path.startsWith('/') ? path : `/${path}`
  return (
    p === '/api/auth/login' ||
    p === '/api/register' ||
    p === '/api/auth/register'
  )
}

function getToken() {
  return localStorage.getItem('token')
}

function getCsrf() {
  return localStorage.getItem('csrf_token')
}

function buildHeaders(extra = {}) {
  const token = getToken()
  const csrf = getCsrf()

  return {
    'Content-Type': 'application/json',
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
    ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
    ...extra,
  }
}

function isMutating(method) {
  return ['POST', 'PUT', 'PATCH', 'DELETE'].includes((method || 'GET').toUpperCase())
}

/** Obtiene JWT+csrf alineados (claim csrf en el token) */
async function fetchCsrfFromServer() {
  const token = getToken()
  if (!token) return false

  const res = await fetch(`${API_BASE}/api/auth/csrf`, {
    method: 'GET',
    credentials: 'include',
    headers: { Authorization: `Bearer ${token}` },
  })

  const payload = res.headers.get('content-type')?.includes('application/json')
    ? await res.json()
    : null

  if (!res.ok || !payload?.csrf_token) {
    return false
  }

  localStorage.setItem('csrf_token', payload.csrf_token)
  if (payload.token) {
    localStorage.setItem('token', payload.token)
  }

  try {
    const { useAuthStore } = await import('../stores/auth')
    useAuthStore().hydrate()
  } catch {
    /* */
  }

  return true
}

let unauthorizedHandling = Promise.resolve()

async function handleUnauthorized() {
  unauthorizedHandling = unauthorizedHandling.then(async () => {
    try {
      const { useAuthStore } = await import('../stores/auth')
      useAuthStore().logout({ redirectHome: false })
    } catch {
      localStorage.removeItem('user')
      localStorage.removeItem('token')
      localStorage.removeItem('csrf_token')
    }

    try {
      const { default: router } = await import('../router')
      if (router.currentRoute.value.name !== 'login') {
        await router.push({ name: 'login' })
      }
    } catch {
      if (!window.location.pathname.endsWith('/login')) {
        window.location.assign('/login')
      }
    }
  })
  return unauthorizedHandling
}

async function request(path, options = {}) {
  const cleanPath = path.startsWith('/') ? path : `/${path}`
  const method = (options.method || 'GET').toUpperCase()
  const { _csrfRetry, ...fetchOpts } = options

  if (isMutating(method) && getToken() && !getCsrf()) {
    await fetchCsrfFromServer()
  }

  const url = `${API_BASE}${cleanPath}`

  let res = await fetch(url, {
    ...fetchOpts,
    method,
    credentials: 'include',
    headers: buildHeaders(fetchOpts.headers || {}),
  })

  const isJson = (res.headers.get('content-type') || '').includes('application/json')
  let payload = isJson ? await res.json() : null

  if (res.status === 401 && !isAuthFailureEndpoint(cleanPath)) {
    await handleUnauthorized()
  }

  const csrfInvalid =
    res.status === 403 &&
    payload &&
    String(payload.error || '').toLowerCase().includes('csrf')

  if (csrfInvalid && isMutating(method) && getToken() && !_csrfRetry) {
    const ok = await fetchCsrfFromServer()
    if (ok) {
      return request(cleanPath, { ...options, _csrfRetry: true })
    }
  }

  if (!res.ok) {
    const msg = payload?.error || payload?.message || 'Error en la respuesta del servidor'
    const err = new Error(msg)
    err.status = res.status
    err.payload = payload
    throw err
  }

  return payload
}

/**
 * Subida multipart (no fijar Content-Type; el navegador pone el boundary).
 */
async function postMultipart(path, formData) {
  const cleanPath = path.startsWith('/') ? path : `/${path}`
  const url = `${API_BASE}${cleanPath}`

  if (getToken() && !getCsrf()) {
    await fetchCsrfFromServer()
  }

  const token = getToken()
  const csrf = getCsrf()
  const headers = {}
  if (token) headers.Authorization = `Bearer ${token}`
  if (csrf) headers['X-CSRF-TOKEN'] = csrf

  let res = await fetch(url, {
    method: 'POST',
    credentials: 'include',
    headers,
    body: formData,
  })

  let payload = (res.headers.get('content-type') || '').includes('application/json')
    ? await res.json()
    : null

  const csrfInvalid =
    res.status === 403 &&
    payload &&
    String(payload.error || '').toLowerCase().includes('csrf')

  if (csrfInvalid && getToken()) {
    const ok = await fetchCsrfFromServer()
    if (ok) {
      const csrf2 = getCsrf()
      const h2 = { ...headers, ...(csrf2 ? { 'X-CSRF-TOKEN': csrf2 } : {}) }
      res = await fetch(url, { method: 'POST', credentials: 'include', headers: h2, body: formData })
      payload = (res.headers.get('content-type') || '').includes('application/json')
        ? await res.json()
        : null
    }
  }

  if (res.status === 401) {
    await handleUnauthorized()
  }

  if (!res.ok) {
    const msg = payload?.error || payload?.message || 'Error en la respuesta del servidor'
    const err = new Error(msg)
    err.status = res.status
    err.payload = payload
    throw err
  }

  return payload
}

export const api = {
  get: (path) => request(path, { method: 'GET' }),
  post: (path, data) => request(path, { method: 'POST', body: JSON.stringify(data ?? {}) }),
  put: (path, data) => request(path, { method: 'PUT', body: JSON.stringify(data ?? {}) }),
  patch: (path, data) => request(path, { method: 'PATCH', body: JSON.stringify(data ?? {}) }),
  delete: (path, data) =>
    request(path, { method: 'DELETE', body: data !== undefined ? JSON.stringify(data) : undefined }),
  postMultipart,
  fetchCsrfFromServer,
  BFF_BASE,
  mediaUrl(path) {
    if (!path) return ''
    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('data:')) return path
    const cleanPath = path.startsWith('/') ? path : `/${path}`
    return `${API_BASE}${cleanPath}`
  },
}
