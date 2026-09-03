import api from '@/services/api'

export async function listCorbeilleClients() {
  const { data } = await api.get('/corbeille/clients')
  return data.items ?? data
}

export async function restoreCorbeilleClient(id) {
  const { data } = await api.post(`/corbeille/clients/${id}/restore`)
  return data
}
