/** Default map center (Ouagadougou area — adjust per deployment). */
export const DEFAULT_MAP_CENTER = [12.3714, -1.5197]
export const DEFAULT_MAP_ZOOM = 12

export const OSM_TILE_URL = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'
export const OSM_ATTRIBUTION =
  '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'

/**
 * External map deeplinks.
 * @param {number} lat
 * @param {number} lng
 */
export function googleMapsUrl(lat, lng) {
  return `https://www.google.com/maps?q=${lat},${lng}`
}

/**
 * @param {number} lat
 * @param {number} lng
 */
export function openStreetMapUrl(lat, lng) {
  return `https://www.openstreetmap.org/?mlat=${lat}&mlon=${lng}#map=16/${lat}/${lng}`
}

/**
 * Directions deeplink Google Maps.
 * @param {{ lat: number, lng: number }} from
 * @param {{ lat: number, lng: number }} to
 */
export function googleMapsDirectionsUrl(from, to) {
  return `https://www.google.com/maps/dir/?api=1&origin=${from.lat},${from.lng}&destination=${to.lat},${to.lng}`
}

/**
 * @param {number} meters
 */
export function formatDistance(meters) {
  if (!Number.isFinite(meters)) return '—'
  if (meters < 1000) return `${Math.round(meters)} m`
  return `${(meters / 1000).toFixed(1)} km`
}

/**
 * @param {number} seconds
 */
export function formatDuration(seconds) {
  if (!Number.isFinite(seconds)) return '—'
  const total = Math.round(seconds)
  const h = Math.floor(total / 3600)
  const m = Math.floor((total % 3600) / 60)
  if (h > 0) return `${h} h ${m} min`
  return `${Math.max(1, m)} min`
}
