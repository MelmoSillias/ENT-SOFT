import api from '@/services/api'

export async function listFinancialTransactions() {
  const { data } = await api.get('/financial-transactions')
  return data.items ?? data
}

export async function getFinancialTransaction(id) {
  const { data } = await api.get(`/financial-transactions/${id}`)
  return data
}

export async function createFinancialTransaction(payload) {
  const { data } = await api.post('/financial-transactions', payload)
  return data
}

export async function updateFinancialTransaction(id, payload) {
  const { data } = await api.put(`/financial-transactions/${id}`, payload)
  return data
}

export async function deleteFinancialTransaction(id) {
  const { data } = await api.delete(`/financial-transactions/${id}`)
  return data
}
