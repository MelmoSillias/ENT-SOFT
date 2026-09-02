import api from '@/services/api'

export async function listClients(params = {}) {
  const { data } = await api.get('/clients', { params })
  return data.items ?? data
}

export async function getClient(id) {
  const { data } = await api.get(`/clients/${id}`)
  return data
}

export async function getClientDetail(id) {
  const { data } = await api.get(`/clients/${id}/detail`)
  return data
}

export async function createClient(payload) {
  const { data } = await api.post('/clients', payload)
  return data
}

export async function updateClient(id, payload) {
  const { data } = await api.put(`/clients/${id}`, payload)
  return data
}

export async function deleteClient(id) {
  const { data } = await api.delete(`/clients/${id}`)
  return data
}

export async function createClientComment(clientId, content) {
  const { data } = await api.post(`/clients/${clientId}/comments`, { content })
  return data
}
