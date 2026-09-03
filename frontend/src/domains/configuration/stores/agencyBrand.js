import { defineStore } from 'pinia'
import api from '@/services/api'
import { appConfig } from '@/config/app'

const LOGO_KEY = 'AGENCE_LOGO_URL'
const NAME_KEY = 'AGENCE_NOM'

export const useAgencyBrandStore = defineStore('agencyBrand', {
  state: () => ({
    logoUrl: '',
    name: '',
    loaded: false,
  }),

  getters: {
    branding: (state) => ({
      ...appConfig.branding,
      name: state.name || appConfig.branding.name,
      logoUrl: state.logoUrl || appConfig.branding.logoUrl,
    }),
  },

  actions: {
    setLogoUrl(url) {
      this.logoUrl = String(url || '').trim()
    },

    async fetch() {
      const { data } = await api.get('/settings')
      const items = data.items ?? data
      const map = {}
      for (const item of items) {
        map[item.cle] = item.valeur
      }
      this.logoUrl = String(map[LOGO_KEY] || '').trim()
      this.name = String(map[NAME_KEY] || '').trim()
      this.loaded = true
    },
  },
})
