import api from '@/services/api'

export async function listStockMovements() {
  const { data } = await api.get('/stock-movements')
  return data.items ?? data
}

export async function getStockMovement(id) {
  const { data } = await api.get(`/stock-movements/${id}`)
  return data
}

export async function createStockMovement(payload) {
  const { data } = await api.post('/stock-movements', payload)
  return data
}

export async function updateStockMovement(id, payload) {
  const { data } = await api.put(`/stock-movements/${id}`, payload)
  return data
}

export async function deleteStockMovement(id) {
  const { data } = await api.delete(`/stock-movements/${id}`)
  return data
}
