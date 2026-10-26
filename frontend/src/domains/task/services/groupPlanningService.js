import api from '@/services/api'

export async function getGroupPlanning(date) {
  const { data } = await api.get('/group-plannings', { params: { date } })
  return data
}

export async function saveGroupPlanning(date, document) {
  const groups = Array.isArray(document) ? document : (document?.groups || [])
  const withheldEmployeeIds = Array.isArray(document) ? [] : (document?.withheldEmployeeIds || [])
  const withheldSiteIds = Array.isArray(document) ? [] : (document?.withheldSiteIds || [])
  const { data } = await api.put('/group-plannings', {
    date,
    groups,
    withheldEmployeeIds,
    withheldSiteIds,
  })
  return data
}
