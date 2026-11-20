import api from '@/services/api'
import { uploadEntityPhoto, deleteEntityPhoto } from '@/domains/shared/services/photoUploadService'

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

export function uploadClientLogo(id, file) {
  return uploadEntityPhoto(`/clients/${id}/logo`, file)
}

export function deleteClientLogo(id) {
  return deleteEntityPhoto(`/clients/${id}/logo`)
}

export async function createClientComment(clientId, content) {
  const { data } = await api.post(`/clients/${clientId}/comments`, { content })
  return data
}
