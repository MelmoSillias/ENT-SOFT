import { defineStore } from 'pinia'
import api from '@/services/api'
import {
  clearStoredTokens,
  getAccessToken,
  getRefreshToken,
  setStoredTokens,
} from '@/domains/auth/tokenStorage'
import { useAgencyBrandStore } from '@/domains/configuration/stores/agencyBrand'

const OPERATIONAL_ROLES = ['AGENT', 'SUPERVISEUR', 'ADMIN']

export const useAuthStore = defineStore('auth', {
  state: () => ({
    accessToken: getAccessToken(),
    refreshToken: getRefreshToken(),
    user: null,
    permissions: [],
    loading: false,
  }),

  getters: {
    isAuthenticated: (state) => !!state.accessToken,
    hasPermission: (state) => (code) => state.permissions.includes(code),
    hasAnyPermission: (state) => (...codes) => codes.some((code) => state.permissions.includes(code)),
    hasModuleAccess: (state) => (modulePrefix) =>
      state.permissions.some((code) => code.startsWith(`${modulePrefix}.`)),
    hasRole: (state) => (...roles) => roles.includes(state.user?.role),
    isOperational: (state) => OPERATIONAL_ROLES.includes(state.user?.role),
  },

  actions: {
    setTokens(accessToken, refreshToken) {
      this.accessToken = accessToken
      this.refreshToken = refreshToken
      setStoredTokens(accessToken, refreshToken)
    },

    clearTokens() {
      this.accessToken = null
      this.refreshToken = null
      clearStoredTokens()
    },

    async login(login, password) {
      this.loading = true
      try {
        const { data } = await api.post('/login', { login, password })
        this.setTokens(data.access_token, data.refresh_token)
        try {
          await this.fetchMe()
          await useAgencyBrandStore().fetch().catch(() => {})
        } catch (error) {
          this.clearTokens()
          this.user = null
          this.permissions = []
          throw error
        }
        return true
      } finally {
        this.loading = false
      }
    },

    async fetchMe() {
      const { data } = await api.get('/me')
      this.user = data
      this.permissions = data.permissions || []
    },

    fetchCurrentUser() {
      return this.fetchMe()
    },

    logout() {
      this.clearTokens()
      this.user = null
      this.permissions = []
    },

    async changePassword(currentPassword, newPassword) {
      await api.post('/me/change-password', { currentPassword, newPassword })
    },
  },
})
