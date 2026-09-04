import api from '@/services/api'

export async function listPrestataires() {
  const { data } = await api.get('/prestataires')
  return Array.isArray(data) ? data : (data.items ?? [])
}

export async function getPrestataire(id) {
  const { data } = await api.get(`/prestataires/${id}`)
  return data
}

export async function createPrestataire(payload) {
  const { data } = await api.post('/prestataires', payload)
  return data
}

export async function updatePrestataire(id, payload) {
  const { data } = await api.put(`/prestataires/${id}`, payload)
  return data
}

export async function deletePrestataire(id) {
  const { data } = await api.delete(`/prestataires/${id}`)
  return data
}

export async function listAllPrestations() {
  const { data } = await api.get('/prestataires/prestations')
  return Array.isArray(data) ? data : (data.items ?? [])
}

export async function listPrestations(prestataireId) {
  const { data } = await api.get(`/prestataires/${prestataireId}/prestations`)
  return Array.isArray(data) ? data : (data.items ?? [])
}

export async function createPrestation(prestataireId, payload) {
  const { data } = await api.post(`/prestataires/${prestataireId}/prestations`, payload)
  return data
}

export async function updatePrestation(prestationId, payload) {
  const { data } = await api.put(`/prestataires/prestations/${prestationId}`, payload)
  return data
}

export async function deletePrestation(prestationId) {
  const { data } = await api.delete(`/prestataires/prestations/${prestationId}`)
  return data
}

export async function payPrestation(prestationId, payload) {
  const { data } = await api.post(`/prestataires/prestations/${prestationId}/pay`, payload)
  return data
}

export async function changePrestationStatus(prestationId, workStatus) {
  const { data } = await api.patch(`/prestataires/prestations/${prestationId}/status`, { workStatus })
  return data
}

export async function duplicatePrestation(prestationId) {
  const { data } = await api.post(`/prestataires/prestations/${prestationId}/duplicate`)
  return data
}

export async function resetPrestationPayments(prestationId) {
  const { data } = await api.post(`/prestataires/prestations/${prestationId}/reset-payments`)
  return data
}
