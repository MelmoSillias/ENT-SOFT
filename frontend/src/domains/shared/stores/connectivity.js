import axios from 'axios'
import { defineStore } from 'pinia'

import { appConfig } from '@/config/app'

const STATUS_OK = 'ok'
const STATUS_BROWSER_OFFLINE = 'browserOffline'
const STATUS_API_UNREACHABLE = 'apiUnreachable'

const apiBaseURL = (import.meta.env.VITE_API_URL || '/api').replace(/\/$/, '')
const healthUrl = `${apiBaseURL}/health`

function isBrowserOnline() {
  return typeof navigator === 'undefined' ? true : navigator.onLine !== false
}

export const useConnectivityStore = defineStore('connectivity', {
  state: () => ({
    status: STATUS_OK,
    dialogVisible: false,
    dismissedWhileDown: false,
    checking: false,
    lastCheckedAt: null,
    monitoring: false,
    consecutiveFailures: 0,
  }),

  getters: {
    isDown: (state) => state.status !== STATUS_OK,
    showBanner: (state) => state.status !== STATUS_OK && state.dismissedWhileDown,
    deploymentMode: () => appConfig.connectivity.deploymentMode,
    isOnlineMode: () => appConfig.connectivity.deploymentMode === 'online',
  },

  actions: {
    startMonitoring() {
      if (this.monitoring || typeof window === 'undefined') {
        return
      }

      this.monitoring = true
      this._onBrowserOffline = () => this.handleBrowserOffline()
      this._onBrowserOnline = () => this.handleBrowserOnline()
      this._onVisibilityChange = () => this.handleVisibilityChange()

      if (this.isOnlineMode) {
        window.addEventListener('offline', this._onBrowserOffline)
        window.addEventListener('online', this._onBrowserOnline)
      }
      document.addEventListener('visibilitychange', this._onVisibilityChange)

      if (this.isOnlineMode && !isBrowserOnline()) {
        this.applyDownStatus(STATUS_BROWSER_OFFLINE)
        return
      }

      this.checkNow()
      this.schedulePoll()
    },

    stopMonitoring() {
      if (!this.monitoring || typeof window === 'undefined') {
        return
      }

      this.monitoring = false
      this.clearPoll()

      if (this._onBrowserOffline) {
        window.removeEventListener('offline', this._onBrowserOffline)
      }
      if (this._onBrowserOnline) {
        window.removeEventListener('online', this._onBrowserOnline)
      }
      if (this._onVisibilityChange) {
        document.removeEventListener('visibilitychange', this._onVisibilityChange)
      }

      this._onBrowserOffline = null
      this._onBrowserOnline = null
      this._onVisibilityChange = null
    },

    schedulePoll() {
      this.clearPoll()
      if (!this.monitoring || typeof window === 'undefined') {
        return
      }
      if (document.hidden) {
        return
      }
      if (this.isOnlineMode && !isBrowserOnline()) {
        return
      }

      this._pollTimer = window.setInterval(() => {
        this.checkNow()
      }, appConfig.connectivity.pollMs)
    },

    clearPoll() {
      if (this._pollTimer != null) {
        clearInterval(this._pollTimer)
        this._pollTimer = null
      }
    },

    handleBrowserOffline() {
      if (!this.isOnlineMode) {
        return
      }
      this.clearPoll()
      this.applyDownStatus(STATUS_BROWSER_OFFLINE)
    },

    async handleBrowserOnline() {
      if (!this.isOnlineMode) {
        return
      }
      await this.checkNow()
      this.schedulePoll()
    },

    async handleVisibilityChange() {
      if (!this.monitoring) {
        return
      }
      if (document.hidden) {
        this.clearPoll()
        return
      }
      if (this.isOnlineMode && !isBrowserOnline()) {
        this.applyDownStatus(STATUS_BROWSER_OFFLINE)
        return
      }
      await this.checkNow()
      this.schedulePoll()
    },

    async checkNow() {
      if (this.checking) {
        return
      }

      if (this.isOnlineMode && !isBrowserOnline()) {
        this.applyDownStatus(STATUS_BROWSER_OFFLINE)
        return
      }

      this.checking = true
      try {
        const { data } = await axios.get(healthUrl, {
          timeout: appConfig.connectivity.healthTimeoutMs,
          headers: { Accept: 'application/json' },
          validateStatus: (status) => status >= 200 && status < 300,
        })

        const healthy = data?.status === 'ok'
        this.lastCheckedAt = Date.now()

        if (healthy) {
          this.applyOk()
        } else {
          this.registerHealthFailure()
        }
      } catch {
        this.lastCheckedAt = Date.now()
        this.registerHealthFailure()
      } finally {
        this.checking = false
      }
    },

    registerHealthFailure() {
      this.consecutiveFailures += 1
      const threshold = appConfig.connectivity.failureThreshold
      if (this.consecutiveFailures >= threshold || this.status !== STATUS_OK) {
        this.applyDownStatus(STATUS_API_UNREACHABLE)
      }
    },

    /**
     * Immediate signal from Axios business requests (ERR_NETWORK / timeout).
     * Counts as one failure toward hysteresis, or keeps down state if already down.
     */
    markUnreachableFromRequest() {
      if (this.isOnlineMode && !isBrowserOnline()) {
        this.applyDownStatus(STATUS_BROWSER_OFFLINE)
        return
      }
      this.registerHealthFailure()
    },

    applyOk() {
      const wasDown = this.status !== STATUS_OK
      this.consecutiveFailures = 0
      this.status = STATUS_OK
      if (wasDown) {
        this.dialogVisible = false
        this.dismissedWhileDown = false
      }
    },

    applyDownStatus(nextStatus) {
      const wasOk = this.status === STATUS_OK
      this.status = nextStatus

      if (nextStatus === STATUS_BROWSER_OFFLINE) {
        this.consecutiveFailures = 0
      }

      // Auto-open only on transition to down; respect dismiss while still down.
      if (wasOk) {
        this.dismissedWhileDown = false
        this.dialogVisible = true
      }
    },

    dismissDialog() {
      this.dialogVisible = false
      if (this.status !== STATUS_OK) {
        this.dismissedWhileDown = true
      }
    },

    openDialog() {
      if (this.status !== STATUS_OK) {
        this.dialogVisible = true
        this.dismissedWhileDown = false
      }
    },

    setDialogVisible(visible) {
      if (visible) {
        this.openDialog()
      } else {
        this.dismissDialog()
      }
    },
  },
})
