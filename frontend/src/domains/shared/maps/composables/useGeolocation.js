import { ref } from 'vue'

/** Shared across map instances so opening a dialog does not re-prompt GPS. */
const POSITION_CACHE_MS = 3 * 60 * 1000

/** @type {{ lat: number, lng: number, accuracy: number|null } | null} */
let cachedPosition = null
let cachedAt = 0
/** @type {Promise<{ lat: number, lng: number, accuracy: number|null }> | null} */
let sharedPending = null

function clonePoint(point) {
  return point ? { lat: point.lat, lng: point.lng, accuracy: point.accuracy ?? null } : null
}

function readCache() {
  if (!cachedPosition || Date.now() - cachedAt > POSITION_CACHE_MS) return null
  return clonePoint(cachedPosition)
}

function storeCache(point) {
  cachedPosition = clonePoint(point)
  cachedAt = Date.now()
}

function wantsFreshFix(options) {
  return options.force === true || options.maximumAge === 0
}

async function requestPosition(options = {}) {
  if (typeof navigator === 'undefined' || !navigator.geolocation) {
    throw new Error('La géolocalisation n’est pas supportée par ce navigateur.')
  }
  const geoOptions = { ...options }
  delete geoOptions.force
  const position = await new Promise((resolve, reject) => {
    navigator.geolocation.getCurrentPosition(resolve, reject, {
      enableHighAccuracy: true,
      timeout: 15000,
      maximumAge: 60000,
      ...geoOptions,
    })
  })
  return {
    lat: position.coords.latitude,
    lng: position.coords.longitude,
    accuracy: Number.isFinite(position.coords.accuracy) ? position.coords.accuracy : null,
  }
}

function startSharedLocate(options, { share }) {
  const request = requestPosition(options)
    .then((point) => {
      storeCache(point)
      return point
    })
    .finally(() => {
      if (share && sharedPending === request) sharedPending = null
    })
  if (share) sharedPending = request
  return request
}

/**
 * Browser geolocation helper.
 * Successful fixes are cached for a few minutes and shared while in flight,
 * unless the caller asks for a fresh reading (`maximumAge: 0` or `force: true`).
 * @returns {{ locating: import('vue').Ref<boolean>, error: import('vue').Ref<string|null>, locate: (options?: PositionOptions & { force?: boolean }) => Promise<{lat:number,lng:number,accuracy:number|null}|null> }}
 */
export function useGeolocation() {
  const locating = ref(false)
  const error = ref(null)

  async function locate(options = {}) {
    const fresh = wantsFreshFix(options)
    if (!fresh) {
      const cached = readCache()
      if (cached) return cached
    }

    locating.value = true
    error.value = null
    try {
      const pending = fresh ? null : sharedPending
      const request = pending ?? startSharedLocate(options, { share: !fresh })
      return clonePoint(await request)
    } catch (e) {
      error.value = e?.message || 'Impossible d’obtenir la position.'
      return null
    } finally {
      locating.value = false
    }
  }

  return { locating, error, locate }
}
