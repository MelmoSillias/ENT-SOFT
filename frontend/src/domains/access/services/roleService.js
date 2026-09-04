import api from '@/services/api'

export async function listRoles({ enabledOnly = false } = {}) {
  const { data } = await api.get('/roles', { params: { enabledOnly } })
  return Array.isArray(data) ? data : (data.items ?? [])
}

export async function createRole(payload) {
  const { data } = await api.post('/roles', payload)
  return data
}

export async function updateRole(id, payload) {
  const { data } = await api.put(`/roles/${id}`, payload)
  return data
}

export async function deleteRole(id) {
  const { data } = await api.delete(`/roles/${id}`)
  return data
}
