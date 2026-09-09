/**
 * Fit a Leaflet map instance to a list of { lat, lng } points.
 * @param {import('leaflet').Map | null | undefined} map
 * @param {Array<{ lat: number, lng: number }>} points
 * @param {{ padding?: number[], maxZoom?: number }} [options]
 */
export function fitMapToPoints(map, points, options = {}) {
  if (!map || !points?.length) return
  const valid = points.filter((p) => Number.isFinite(p.lat) && Number.isFinite(p.lng))
  if (!valid.length) return

  if (valid.length === 1) {
    map.setView([valid[0].lat, valid[0].lng], options.maxZoom ?? 14)
    return
  }

  const latLngs = valid.map((p) => [p.lat, p.lng])
  map.fitBounds(latLngs, {
    padding: options.padding ?? [40, 40],
    maxZoom: options.maxZoom ?? 16,
  })
}

export function useMapFitBounds() {
  return { fitMapToPoints }
}
