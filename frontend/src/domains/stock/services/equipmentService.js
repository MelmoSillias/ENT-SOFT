import api from '@/services/api'

export async function listEquipment(params = {}) {
  const { data } = await api.get('/equipment', { params })
  return data.items ?? data
}

export async function getEquipment(id) {
  const { data } = await api.get(`/equipment/${id}`)
  return data
}

export async function createEquipment(payload) {
  const { data } = await api.post('/equipment', payload)
  return data
}

export async function updateEquipment(id, payload) {
  const { data } = await api.put(`/equipment/${id}`, payload)
  return data
}

export async function deleteEquipment(id) {
  const { data } = await api.delete(`/equipment/${id}`)
  return data
}
