import api from '@/services/api'

export const impressionService = {
  async getSettings() {
    const { data } = await api.get('/impressions/settings')
    return data
  },

  async fetchDocument(type, id, { format = 'html', page, orientation, disposition = 'inline', from, to } = {}) {
    const { data } = await api.get(`/impressions/documents/${type}/${id}`, {
      params: { format, page, orientation, disposition, from, to },
      responseType: 'blob',
    })
    return data
  },

  async printTable(tableType, payload) {
    const { data } = await api.post(`/impressions/tables/${tableType}/print`, payload, {
      responseType: 'blob',
    })
    return data
  },

  async exportTable(tableType, payload) {
    const { data } = await api.post(`/impressions/tables/${tableType}/export`, payload, {
      responseType: 'blob',
    })
    return data
  },
}
