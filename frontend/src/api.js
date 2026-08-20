import { createApiError, reportApplicationError } from './error-utils'

export const API = import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api'
export const STORAGE = API.replace(/\/api$/, '/storage')

export async function api(path, options = {}) {
  const token = localStorage.getItem('nova_token')
  const headers = {
    Accept: 'application/json',
    ...(options.body instanceof FormData ? {} : {'Content-Type': 'application/json'}),
    ...options.headers,
  }
  if (token) headers.Authorization = `Bearer ${token}`
  const editionId = token && localStorage.getItem('bsi_event_edition')
  if (editionId) headers['X-BSI-Edition'] = editionId
  const method = options.method || 'GET'

  let response
  try {
    response = await fetch(`${API}${path}`, {...options, headers})
  } catch (cause) {
    const error = createApiError({path, method, cause, network: true})
    reportApplicationError(error)
    throw error
  }

  const contentType = response.headers.get('content-type') || ''
  let data
  if (contentType.includes('json')) {
    try {
      const raw = await response.text()
      const indexes = ['{', '['].map(char => raw.indexOf(char)).filter(index => index >= 0)
      data = raw ? JSON.parse(raw.slice(indexes.length ? Math.min(...indexes) : 0)) : {}
    } catch (cause) {
      const error = createApiError({
        data: {message: 'Respons server tidak dapat dibaca. Hubungi pengelola jika masalah berulang.'},
        status: response.status,
        path,
        method,
        cause,
      })
      reportApplicationError(error)
      throw error
    }
  } else {
    data = response.ok ? await response.blob() : {}
  }

  if (!response.ok) {
    const error = createApiError({data, status: response.status, path, method})
    reportApplicationError(error)
    if (response.status === 401) {
      localStorage.removeItem('nova_token')
      localStorage.removeItem('nova_user')
      if (window.location.pathname !== '/login') setTimeout(() => window.location.assign('/login'), 250)
    }
    throw error
  }

  return data
}
