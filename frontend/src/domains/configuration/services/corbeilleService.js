import api from '@/services/api'

export async function listCorbeilleComptes() {
  const { data } = await api.get('/corbeille/comptes')
  return data.items ?? data
}

export async function listCorbeilleClients() {
  const { data } = await api.get('/corbeille/clients')
  return data.items ?? data
}

export async function restoreCorbeilleCompte(id) {
  const { data } = await api.post(`/corbeille/comptes/${id}/restore`)
  return data
}

export async function restoreCorbeilleClient(id) {
  const { data } = await api.post(`/corbeille/clients/${id}/restore`)
  return data
}
