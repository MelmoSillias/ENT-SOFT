<script setup>
import { computed, shallowRef, watch } from 'vue'
import { LCircle, LMarker, LPopup } from '@vue-leaflet/vue-leaflet'
import { useGeolocation } from '@/domains/shared/maps/composables/useGeolocation'
import { formatDistance } from '@/domains/shared/maps/mapDefaults'

const emit = defineEmits(['update', 'error'])

/** Hide the accuracy halo when GPS is too coarse to be useful on the map. */
const MAX_ACCURACY_M = 800

const { position, error, startWatch } = useGeolocation()
startWatch()

const icon = shallowRef(null)

const latLng = computed(() =>
  position.value ? [position.value.lat, position.value.lng] : null,
)

const accuracyRadius = computed(() => {
  const accuracy = position.value?.accuracy
  if (!Number.isFinite(accuracy) || accuracy < 8 || accuracy > MAX_ACCURACY_M) return null
  return accuracy
})

const accuracyLabel = computed(() => {
  const accuracy = position.value?.accuracy
  return Number.isFinite(accuracy) ? formatDistance(accuracy) : ''
})

watch(
  position,
  (point) => {
    if (point) emit('update', point)
  },
  { immediate: true },
)

watch(error, (message) => {
  if (message) emit('error', message)
})

async function buildIcon() {
  const leaflet = await import('leaflet/dist/leaflet-src.esm')
  const L = leaflet.default ?? leaflet
  icon.value = L.divIcon({
    className: 'app-map-user-location',
    html: '<span class="app-map-user-location__ring" aria-hidden="true"></span><span class="app-map-user-location__dot" aria-hidden="true"></span>',
    iconSize: [28, 28],
    iconAnchor: [14, 14],
    popupAnchor: [0, -16],
  })
}

buildIcon()
</script>

<template>
  <template v-if="latLng && icon">
    <LCircle
      v-if="accuracyRadius"
      class-name="app-map-user-accuracy"
      :lat-lng="latLng"
      :radius="accuracyRadius"
      color="#2563eb"
      :weight="1"
      :opacity="0.45"
      fill-color="#2563eb"
      :fill-opacity="0.12"
      :interactive="false"
    />
    <LMarker :lat-lng="latLng" :icon="icon" :z-index-offset="900">
      <LPopup>
        <strong>Ma position</strong>
        <p v-if="accuracyLabel" class="app-map-user-popup__accuracy">
          Précision {{ accuracyLabel }}
        </p>
      </LPopup>
    </LMarker>
  </template>
</template>
