import api from '@/services/api'

/**
 * @param {{ latitude: number, longitude: number, accuracy?: number|null }} payload
 */
export async function checkInPosition(payload) {
  const { data } = await api.post('/employee-positions/checkin', payload)
  return data
}

/**
 * @param {{ latestOnly?: boolean, employeeId?: string, limit?: number }} [params]
 */
export async function listEmployeePositions(params = {}) {
  const query = {}
  if (params.latestOnly) query.latestOnly = 1
  if (params.employeeId) query.employeeId = params.employeeId
  if (params.limit != null) query.limit = params.limit
  const { data } = await api.get('/employee-positions', { params: query })
  return data.items ?? data
}
