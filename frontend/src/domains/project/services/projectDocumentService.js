import api from '@/services/api'

const basePath = (projectId) => `/projects/${projectId}/documents`

export async function listProjectDocuments(projectId) {
  const { data } = await api.get(basePath(projectId))
  return data.items ?? data
}

export async function createProjectDocument(projectId, payload) {
  const { data } = await api.post(basePath(projectId), payload)
  return data
}

export async function updateProjectDocument(projectId, id, payload) {
  const { data } = await api.put(`${basePath(projectId)}/${id}`, payload)
  return data
}

export async function deleteProjectDocument(projectId, id) {
  await api.delete(`${basePath(projectId)}/${id}`)
}
