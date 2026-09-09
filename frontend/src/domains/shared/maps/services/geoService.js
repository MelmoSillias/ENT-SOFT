import api from '@/services/api'

export async function geocodeSearch(q, limit = 5) {
  const { data } = await api.get('/geo/geocode', { params: { q, limit } })
  return data.items ?? []
}

export async function reverseGeocode(lat, lng) {
  const { data } = await api.get('/geo/reverse', { params: { lat, lng } })
  return data.item ?? null
}

/**
 * @param {{ lat: number, lng: number }} from
 * @param {{ lat: number, lng: number }} to
 * @param {string} [profile]
 */
export async function getDirections(from, to, profile = 'driving-car') {
  const { data } = await api.post('/geo/directions', { from, to, profile })
  return data
}
