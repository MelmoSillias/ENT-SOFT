import api from '@/services/api'

export async function getGroupPlanning(date) {
  const { data } = await api.get('/group-plannings', { params: { date } })
  return data
}

export async function saveGroupPlanning(date, groups) {
  const { data } = await api.put('/group-plannings', { date, groups })
  return data
}
