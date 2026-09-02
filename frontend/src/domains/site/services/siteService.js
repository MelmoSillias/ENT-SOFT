import api from '@/services/api'

export async function listSites(params = {}) {
  const { data } = await api.get('/sites', { params })
  return data.items ?? data
}

export async function getSite(id) {
  const { data } = await api.get(`/sites/${id}`)
  return data
}

export async function createSite(payload) {
  const { data } = await api.post('/sites', payload)
  return data
}

export async function updateSite(id, payload) {
  const { data } = await api.put(`/sites/${id}`, payload)
  return data
}

export async function deleteSite(id) {
  const { data } = await api.delete(`/sites/${id}`)
  return data
}
