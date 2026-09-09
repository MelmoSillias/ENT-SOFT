<script setup>
import { computed, shallowRef, watch } from 'vue'
import { LMarker, LPopup, LIcon } from '@vue-leaflet/vue-leaflet'
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png'
import markerIcon from 'leaflet/dist/images/marker-icon.png'
import markerShadow from 'leaflet/dist/images/marker-shadow.png'

const props = defineProps({
  lat: { type: [Number, String], required: true },
  lng: { type: [Number, String], required: true },
  draggable: { type: Boolean, default: false },
  /**
   * Visual style of the pin.
   * - default: classic Leaflet pin (PNG via LIcon)
   * - picked / selected / me: colored DivIcon
   */
  variant: {
    type: String,
    default: 'default',
    validator: (v) => ['default', 'picked', 'selected', 'me'].includes(v),
  },
})

const emit = defineEmits(['dragend', 'click'])

const customIcon = shallowRef(null)
let Leaflet = null

const latNum = computed(() => Number(props.lat))
const lngNum = computed(() => Number(props.lng))
const latLng = computed(() => [latNum.value, lngNum.value])
const useDefaultIcon = computed(() => props.variant === 'default')

async function ensureLeaflet() {
  if (Leaflet) return Leaflet
  const leaflet = await import('leaflet/dist/leaflet-src.esm')
  Leaflet = leaflet.default ?? leaflet
  return Leaflet
}

async function rebuildCustomIcon() {
  if (useDefaultIcon.value) {
    customIcon.value = null
    return
  }
  const L = await ensureLeaflet()
  customIcon.value = L.divIcon({
    className: `app-map-pin app-map-pin--${props.variant}`,
    html: '<span class="app-map-pin__glyph" aria-hidden="true"></span>',
    iconSize: [32, 40],
    iconAnchor: [16, 40],
    popupAnchor: [0, -36],
  })
}

watch(() => props.variant, rebuildCustomIcon, { immediate: true })

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
    v-if="Number.isFinite(latNum) && Number.isFinite(lngNum) && (useDefaultIcon || customIcon)"
    :lat-lng="latLng"
    :icon="useDefaultIcon ? undefined : customIcon"
    :draggable="draggable"
    @dragend="onDragEnd"
    @click="onClick"
  >
    <LIcon
      v-if="useDefaultIcon"
      :icon-url="markerIcon"
      :icon-retina-url="markerIcon2x"
      :shadow-url="markerShadow"
      :icon-size="[25, 41]"
      :icon-anchor="[12, 41]"
      :popup-anchor="[1, -34]"
      :tooltip-anchor="[16, -28]"
      :shadow-size="[41, 41]"
    />
    <LPopup v-if="$slots.default">
      <slot />
    </LPopup>
  </LMarker>
</template>
