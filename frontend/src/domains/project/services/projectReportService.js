import api from '@/services/api'

const base = '/project-reports'

export async function listProjectReports(params = {}) {
  const { data } = await api.get(base, { params })
  return data.items ?? data
}

export async function getProjectReport(id) {
  const { data } = await api.get(`${base}/${id}`)
  return data
}

export async function createProjectReport(payload) {
  const { data } = await api.post(base, payload)
  return data
}

export async function updateProjectReport(id, payload) {
  const { data } = await api.put(`${base}/${id}`, payload)
  return data
}

export async function deleteProjectReport(id) {
  const { data } = await api.delete(`${base}/${id}`)
  return data
}

export async function deleteProjectReportDocument(reportId, documentId) {
  const { data } = await api.delete(`${base}/${reportId}/documents/${documentId}`)
  return data
}

export async function exportProjectReport(id, format, { bundle = false } = {}) {
  const { data } = await api.get(`${base}/${id}/export`, {
    params: { format, bundle: bundle ? 1 : 0 },
    responseType: 'blob',
  })
  return data
}
