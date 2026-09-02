import axios from 'axios'
import {
  clearStoredTokens,
  getAccessToken,
  getRefreshToken,
  setStoredTokens,
} from '@/domains/auth/tokenStorage'

const apiBaseURL = import.meta.env.VITE_API_URL || '/api'
const refreshEndpoint = `${apiBaseURL.replace(/\/$/, '')}/token/refresh`

const api = axios.create({
  baseURL: apiBaseURL,
  headers: { 'Content-Type': 'application/json' },
})

let refreshPromise = null

function isAuthPublicRequest(config) {
  const url = String(config?.url || '')
  return url.includes('/login') || url.includes('/token/refresh')
}

async function syncTokensToAuthStore(accessToken, refreshToken) {
  try {
    const { getActivePinia } = await import('pinia')
    if (!getActivePinia()) {
      return
    }
    const { useAuthStore } = await import('@/domains/auth/stores/auth')
    const auth = useAuthStore()
    if (accessToken) {
      auth.setTokens(accessToken, refreshToken)
    } else {
      auth.logout()
    }
  } catch {
    // Pinia may be unavailable during early boot; storage already updated.
  }
}

async function refreshAccessToken() {
  if (!refreshPromise) {
    refreshPromise = (async () => {
      const refresh = getRefreshToken()
      if (!refresh) {
        throw new Error('Missing refresh token')
      }

      const { data } = await axios.post(refreshEndpoint, { refresh_token: refresh })
      setStoredTokens(data.access_token, data.refresh_token)
      await syncTokensToAuthStore(data.access_token, data.refresh_token)
      return data.access_token
    })().finally(() => {
      refreshPromise = null
    })
  }

  return refreshPromise
}

async function handleRefreshFailure() {
  clearStoredTokens()
  await syncTokensToAuthStore(null, null)
  if (window.location.pathname !== '/login') {
    window.location.href = '/login'
  }
}

api.interceptors.request.use((config) => {
  if (!isAuthPublicRequest(config)) {
    const token = getAccessToken()
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
  }
  return config
})

function isConnectivityProbe(config) {
  const url = String(config?.url || '')
  return url.includes('/health')
}

function isNetworkConnectivityError(error) {
  if (error.response) {
    return false
  }
  const code = error.code
  return (
    code === 'ERR_NETWORK'
    || code === 'ECONNABORTED'
    || code === 'ETIMEDOUT'
    || error.message === 'Network Error'
  )
}

async function notifyConnectivityUnreachable() {
  try {
    const { getActivePinia } = await import('pinia')
    if (!getActivePinia()) {
      return
    }
    const { useConnectivityStore } = await import('@/domains/shared/stores/connectivity')
    useConnectivityStore().markUnreachableFromRequest()
  } catch {
    // Pinia may be unavailable during early boot.
  }
}

async function notifyAccessDenied(error) {
  if (error?.config?.skipForbiddenToast || error?.isAccessDeniedNotified) {
    return
  }
  error.isAccessDeniedNotified = true
  try {
    const { getActivePinia } = await import('pinia')
    if (!getActivePinia()) {
      return
    }
    const { useAppToast } = await import('@/domains/shared/composables/useAppToast')
    const detail =
      error.response?.data?.error
      || 'Vous n\'avez pas la permission d\'effectuer cette action.'
    useAppToast().add({
      severity: 'warn',
      summary: 'Accès refusé',
      detail,
    })
  } catch {
    // Toast/PrimeVue may be unavailable during early boot.
  }
}

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const original = error.config
    if (
      error.response?.status === 401
      && original
      && !original._retry
      && !isAuthPublicRequest(original)
    ) {
      original._retry = true
      if (!getRefreshToken()) {
        return Promise.reject(error)
      }

      try {
        const accessToken = await refreshAccessToken()
        original.headers = original.headers || {}
        original.headers.Authorization = `Bearer ${accessToken}`
        return api(original)
      } catch {
        await handleRefreshFailure()
        return Promise.reject(error)
      }
    }

    if (error.response?.status === 403) {
      void notifyAccessDenied(error)
    }

    if (isNetworkConnectivityError(error) && !isConnectivityProbe(original)) {
      void notifyConnectivityUnreachable()
    }

    return Promise.reject(error)
  },
)

export default api
