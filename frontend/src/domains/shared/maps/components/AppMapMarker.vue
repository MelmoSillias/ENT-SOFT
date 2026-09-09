<script setup>
import { computed, shallowRef, watch } from 'vue'
import { LMarker, LPopup } from '@vue-leaflet/vue-leaflet'
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png'
import markerIcon from 'leaflet/dist/images/marker-icon.png'
import markerShadow from 'leaflet/dist/images/marker-shadow.png'

const props = defineProps({
  lat: { type: Number, required: true },
  lng: { type: Number, required: true },
  draggable: { type: Boolean, default: false },
  /**
   * Visual style of the pin.
   * - default: classic Leaflet pin
   * - picked: orange selection pin
   * - selected: brand/blue emphasis for selected site
   * - me: teal pin for geolocation
   */
  variant: {
    type: String,
    default: 'default',
    validator: (v) => ['default', 'picked', 'selected', 'me'].includes(v),
  },
})

const emit = defineEmits(['dragend', 'click'])

const icon = shallowRef(null)
let Leaflet = null

const latLng = computed(() => [props.lat, props.lng])

async function ensureLeaflet() {
  if (Leaflet) return Leaflet
  const leaflet = await import('leaflet/dist/leaflet-src.esm')
  Leaflet = leaflet.default ?? leaflet
  return Leaflet
}

async function rebuildIcon() {
  const L = await ensureLeaflet()

  if (props.variant === 'default') {
    icon.value = L.icon({
      iconUrl: markerIcon,
      iconRetinaUrl: markerIcon2x,
      shadowUrl: markerShadow,
      iconSize: [25, 41],
      iconAnchor: [12, 41],
      popupAnchor: [1, -34],
      tooltipAnchor: [16, -28],
      shadowSize: [41, 41],
    })
    return
  }

  icon.value = L.divIcon({
    className: `app-map-pin app-map-pin--${props.variant}`,
    html: '<span class="app-map-pin__glyph" aria-hidden="true"></span>',
    iconSize: [32, 40],
    iconAnchor: [16, 40],
    popupAnchor: [0, -36],
  })
}

watch(() => props.variant, rebuildIcon, { immediate: true })

function onDragEnd(event) {
  const marker = event?.target
  const pos = marker?.getLatLng?.()
  if (!pos) return
  emit('dragend', { lat: pos.lat, lng: pos.lng })
}

function onClick(event) {
  try {
    Leaflet?.DomEvent?.stopPropagation?.(event)
    event?.originalEvent?.stopPropagation?.()
  } catch {
    /* ignore */
  }
  emit('click', event)
}
</script>

<template>
  <LMarker
    v-if="icon"
    :lat-lng="latLng"
    :icon="icon"
    :draggable="draggable"
    @dragend="onDragEnd"
    @click="onClick"
  >
    <LPopup v-if="$slots.default">
      <slot />
    </LPopup>
  </LMarker>
</template>
