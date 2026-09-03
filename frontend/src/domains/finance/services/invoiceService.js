import api from '@/services/api'

export async function listInvoices() {
  const { data } = await api.get('/invoices')
  return data.items ?? data
}

export async function getInvoice(id) {
  const { data } = await api.get(`/invoices/${id}`)
  return data
}

export async function createInvoice(payload) {
  const { data } = await api.post('/invoices', payload)
  return data
}

export async function updateInvoice(id, payload) {
  const { data } = await api.put(`/invoices/${id}`, payload)
  return data
}

export async function deleteInvoice(id) {
  const { data } = await api.delete(`/invoices/${id}`)
  return data
}

export async function payInvoice(id, payload) {
  const { data } = await api.post(`/invoices/${id}/pay`, payload)
  return data
}

export async function resetInvoice(id) {
  const { data } = await api.post(`/invoices/${id}/reset`)
  return data
}
