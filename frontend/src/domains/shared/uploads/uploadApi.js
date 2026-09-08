import api from '@/services/api'

export const MAX_UPLOAD_BYTES = 100 * 1024 * 1024

export async function createUploadSession(payload) {
  const { data } = await api.post('/uploads', payload)
  return data
}

export async function putUploadChunk(sessionId, index, blob) {
  const { data } = await api.put(`/uploads/${sessionId}/chunks/${index}`, blob, {
    headers: { 'Content-Type': 'application/octet-stream' },
    timeout: 120000,
    maxBodyLength: Infinity,
    maxContentLength: Infinity,
    transformRequest: [(d) => d],
  })
  return data
}

export async function getUploadStatus(sessionId) {
  const { data } = await api.get(`/uploads/${sessionId}`)
  return data
}

export async function completeUploadSession(sessionId) {
  const { data } = await api.post(`/uploads/${sessionId}/complete`)
  return data
}

export async function abortUploadSession(sessionId) {
  const { data } = await api.delete(`/uploads/${sessionId}`)
  return data
}

export async function sha256Hex(blob) {
  const buffer = await blob.arrayBuffer()
  const hash = await crypto.subtle.digest('SHA-256', buffer)
  return [...new Uint8Array(hash)].map((b) => b.toString(16).padStart(2, '0')).join('')
}

export function formatBytes(bytes) {
  if (!Number.isFinite(bytes) || bytes <= 0) return '0 o'
  const units = ['o', 'Ko', 'Mo', 'Go']
  const i = Math.min(units.length - 1, Math.floor(Math.log(bytes) / Math.log(1024)))
  const value = bytes / 1024 ** i
  return `${value >= 100 || i === 0 ? Math.round(value) : value.toFixed(1)} ${units[i]}`
}

export function formatSizeTransition(originalSize, finalSize) {
  if (originalSize && finalSize && originalSize !== finalSize) {
    return `${formatBytes(originalSize)} → ${formatBytes(finalSize)}`
  }
  return formatBytes(finalSize || originalSize || 0)
}
