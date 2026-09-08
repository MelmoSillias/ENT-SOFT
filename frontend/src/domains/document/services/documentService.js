import api from '@/services/api'
import { useUploadQueue } from '@/domains/shared/uploads/uploadQueue'

export async function listDocuments(params = {}) {
  const { data } = await api.get('/documents', { params })
  return data.items ?? data
}

/**
 * Upload chunké (fichiers volumineux) : le fichier part par blocs de 4 Mo
 * via /api/uploads, puis le document est créé avec l'uploadSessionId.
 */
export async function uploadDocumentFile({ file, title, ownerType, ownerId, description = null }) {
  const queue = useUploadQueue()
  const staged = await queue.stage(file)
  const uploadSessionId = await queue.commit(staged.localId)
  const { data } = await api.post('/documents/upload', {
    uploadSessionId,
    title: title || file.name,
    ownerType,
    ownerId,
    description,
  })
  return data
}

/** @deprecated Upload multipart direct (limité par nginx/PHP). Préférer uploadDocumentFile. */
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
