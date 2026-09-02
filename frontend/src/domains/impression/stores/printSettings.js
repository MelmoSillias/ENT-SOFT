import { defineStore } from 'pinia'
import { impressionService } from '@/domains/impression/services/impressionService'

export const usePrintSettingsStore = defineStore('printSettings', {
  state: () => ({
    settings: null,
    loading: false,
    loaded: false,
  }),

  getters: {
    defaultPageFor: (state) => (type) => {
      const map = {
        table: state.settings?.default_page_table,
        transfert: state.settings?.default_page_transfert,
        releve_compte: state.settings?.default_page_table,
        operation_client: state.settings?.default_page_transfert,
      }
      return map[type] || 'a4'
    },
    defaultOrientationFor: (state) => (type) => {
      const map = {
        table: state.settings?.default_orientation_table,
        transfert: state.settings?.default_orientation_transfert,
        releve_compte: state.settings?.default_orientation_table,
        operation_client: state.settings?.default_orientation_transfert,
      }
      return map[type] || 'portrait'
    },
    defaultExportFormat: (state) => state.settings?.default_export_format || 'pdf',
  },

  actions: {
    async fetchSettings(force = false) {
      if (this.loaded && !force) {
        return this.settings
      }
      this.loading = true
      try {
        this.settings = await impressionService.getSettings()
        this.loaded = true
        return this.settings
      } finally {
        this.loading = false
      }
    },

    invalidate() {
      this.loaded = false
    },
  },
})
