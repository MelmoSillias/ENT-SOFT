import api from '@/services/api'

export async function listProjects(params = {}) {
  const { data } = await api.get('/projects', { params })
  return data.items ?? data
}

export async function getProject(id) {
  const { data } = await api.get(`/projects/${id}`)
  return data
}

export async function getProjectDetail(id) {
  const { data } = await api.get(`/projects/${id}/detail`)
  return data
}

export async function createProject(payload) {
  const { data } = await api.post('/projects', payload)
  return data
}

export async function updateProject(id, payload) {
  const { data } = await api.put(`/projects/${id}`, payload)
  return data
}

export async function deleteProject(id) {
  const { data } = await api.delete(`/projects/${id}`)
  return data
}

export async function addSiteToProject(projectId, payload) {
  const { data } = await api.post(`/projects/${projectId}/sites`, payload)
  return data
}

export async function updateProjectSite(projectSiteId, payload) {
  const { data } = await api.put(`/projects/sites/${projectSiteId}`, payload)
  return data
}

export async function removeSiteFromProject(projectSiteId) {
  const { data } = await api.delete(`/projects/sites/${projectSiteId}`)
  return data
}

export async function createProjectEvent(projectId, payload) {
  const { data } = await api.post(`/projects/${projectId}/events`, payload)
  return data
}
