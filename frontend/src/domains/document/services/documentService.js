import api from '@/services/api'

export async function listDocuments(params = {}) {
  const { data } = await api.get('/documents', { params })
  return data.items ?? data
}

export async function uploadDocument(formData) {
  const { data } = await api.post('/documents/upload', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data
}

export async function deleteDocument(id) {
  const { data } = await api.delete(`/documents/${id}`)
  return data
}
