import { onScopeDispose, ref, shallowRef, watch } from 'vue'

/** Shared across map instances so opening a dialog does not re-prompt GPS. */
const POSITION_CACHE_MS = 3 * 60 * 1000

/** @type {{ lat: number, lng: number, accuracy: number|null } | null} */
let cachedPosition = null
let cachedAt = 0
/** @type {Promise<{ lat: number, lng: number, accuracy: number|null }> | null} */
let sharedPending = null

const livePosition = shallowRef(null)
const liveError = ref(null)
/** @type {number | null} */
let watchId = null
let watchSubscribers = 0

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

function publishPoint(point) {
  const next = clonePoint(point)
  storeCache(next)
  livePosition.value = next
  liveError.value = null
  return next
}

function geoErrorMessage(error) {
  const code = error?.code
  if (code === 1) return 'Autorisation de localisation refusée.'
  if (code === 2) return 'Position indisponible.'
  if (code === 3) return 'Délai dépassé pour obtenir la position.'
  return error?.message || 'Impossible d’obtenir la position.'
}

function toPoint(position) {
  return {
    lat: position.coords.latitude,
    lng: position.coords.longitude,
    accuracy: Number.isFinite(position.coords.accuracy) ? position.coords.accuracy : null,
  }
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
  return toPoint(position)
}

function startSharedLocate(options, { share }) {
  const request = requestPosition(options)
    .then((point) => publishPoint(point))
    .finally(() => {
      if (share && sharedPending === request) sharedPending = null
    })
  if (share) sharedPending = request
  return request
}

function ensureWatch() {
  if (watchId != null) return
  if (typeof navigator === 'undefined' || !navigator.geolocation?.watchPosition) {
    liveError.value = 'La géolocalisation n’est pas supportée par ce navigateur.'
    return
  }
  const cached = readCache()
  if (cached && !livePosition.value) livePosition.value = cached

  watchId = navigator.geolocation.watchPosition(
    (position) => {
      publishPoint(toPoint(position))
    },
    (error) => {
      liveError.value = geoErrorMessage(error)
    },
    {
      enableHighAccuracy: true,
      timeout: 20000,
      maximumAge: 1000,
    },
  )
}

function releaseWatch() {
  watchSubscribers = Math.max(0, watchSubscribers - 1)
  if (watchSubscribers > 0 || watchId == null) return
  navigator.geolocation.clearWatch(watchId)
  watchId = null
}

/**
 * Browser geolocation helper.
 * Successful one-shot fixes are cached for a few minutes and shared while in flight,
 * unless the caller asks for a fresh reading (`maximumAge: 0` or `force: true`).
 * `startWatch()` follows the device with `watchPosition` (one shared watch).
 */
export function useGeolocation() {
  const locating = ref(false)
  const error = ref(null)
  const position = shallowRef(clonePoint(livePosition.value) || readCache())
  let subscribed = false

  const stopSync = watch(livePosition, (point) => {
    if (!subscribed) return
    position.value = clonePoint(point)
  })
  const stopErrorSync = watch(liveError, (message) => {
    if (!subscribed || !message) return
    error.value = message
  })

  function startWatch() {
    if (subscribed) return
    subscribed = true
    watchSubscribers += 1
    position.value = clonePoint(livePosition.value) || readCache()
    error.value = liveError.value
    ensureWatch()
  }

  function stopWatch() {
    if (!subscribed) return
    subscribed = false
    releaseWatch()
  }

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
      error.value = geoErrorMessage(e)
      return null
    } finally {
      locating.value = false
    }
  }

  onScopeDispose(() => {
    stopWatch()
    stopSync()
    stopErrorSync()
  })

  return { locating, error, position, locate, startWatch, stopWatch }
}
