import { ref } from 'vue'

/**
 * Browser geolocation helper.
 * @returns {{ locating: import('vue').Ref<boolean>, error: import('vue').Ref<string|null>, locate: () => Promise<{lat:number,lng:number}|null> }}
 */
export function useGeolocation() {
  const locating = ref(false)
  const error = ref(null)

  async function locate(options = {}) {
    locating.value = true
    error.value = null
    try {
      if (!navigator.geolocation) {
        error.value = 'La géolocalisation n’est pas supportée par ce navigateur.'
        return null
      }
      const position = await new Promise((resolve, reject) => {
        navigator.geolocation.getCurrentPosition(resolve, reject, {
          enableHighAccuracy: true,
          timeout: 15000,
          maximumAge: 60000,
          ...options,
        })
      })
      return {
        lat: position.coords.latitude,
        lng: position.coords.longitude,
        accuracy: Number.isFinite(position.coords.accuracy) ? position.coords.accuracy : null,
      }
    } catch (e) {
      error.value = e?.message || 'Impossible d’obtenir la position.'
      return null
    } finally {
      locating.value = false
    }
  }

  return { locating, error, locate }
}
