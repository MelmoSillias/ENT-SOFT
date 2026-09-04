import { defineStore } from 'pinia'

export const useAppBusyStore = defineStore('appBusy', {
  state: () => ({
    exporting: false,
    exportLabel: '',
  }),

  actions: {
    startExport(label = 'Export en cours…') {
      this.exporting = true
      this.exportLabel = label
    },

    endExport() {
      this.exporting = false
      this.exportLabel = ''
    },
  },
})
