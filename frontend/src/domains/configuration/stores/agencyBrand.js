import { defineStore } from 'pinia'
import api from '@/services/api'
import { appConfig } from '@/config/app'

const LOGO_KEY = 'AGENCE_LOGO_URL'
const NAME_KEY = 'AGENCE_NOM'

export const useAgencyBrandStore = defineStore('agencyBrand', {
  state: () => ({
    logoUrl: '',
    name: '',
    phone: '',
    email: '',
    address: '',
    city: '',
    website: '',
    loaded: false,
  }),

  getters: {
    branding: (state) => ({
      ...appConfig.branding,
      name: state.name || appConfig.branding.name,
      logoUrl: state.logoUrl || appConfig.branding.logoUrl,
      phone: state.phone,
      email: state.email,
      address: state.address,
      city: state.city,
      website: state.website,
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
      this.phone = String(map.AGENCE_TELEPHONE || '').trim()
      this.email = String(map.AGENCE_EMAIL || '').trim()
      this.address = String(map.AGENCE_ADRESSE || '').trim()
      this.city = String(map.AGENCE_VILLE || '').trim()
      this.website = String(map.AGENCE_SITE_WEB || '').trim()
      this.loaded = true
    },
  },
})
