import api from '@/services/api'

export async function listTasks(params = {}) {
  const { data } = await api.get('/tasks', { params })
  return data.items ?? data
}

export async function getTask(id) {
  const { data } = await api.get(`/tasks/${id}`)
  return data
}

export async function createTask(payload) {
  const { data } = await api.post('/tasks', payload)
  return data
}

export async function updateTask(id, payload) {
  const { data } = await api.put(`/tasks/${id}`, payload)
  return data
}

export async function deleteTask(id) {
  const { data } = await api.delete(`/tasks/${id}`)
  return data
}
