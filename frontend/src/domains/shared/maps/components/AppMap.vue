<script setup>
import { computed, shallowRef, ref } from 'vue'
import { LMap, LTileLayer } from '@vue-leaflet/vue-leaflet'
import 'leaflet/dist/leaflet.css'
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png'
import markerIcon from 'leaflet/dist/images/marker-icon.png'
import markerShadow from 'leaflet/dist/images/marker-shadow.png'
import {
  DEFAULT_MAP_CENTER,
  DEFAULT_MAP_ZOOM,
  OSM_ATTRIBUTION,
  OSM_TILE_URL,
} from '@/domains/shared/maps/mapDefaults'

const props = defineProps({
  center: {
    type: Array,
    default: () => [...DEFAULT_MAP_CENTER],
  },
  zoom: {
    type: Number,
    default: DEFAULT_MAP_ZOOM,
  },
  height: {
    type: String,
    default: '280px',
  },
  /** When true, map fills parent height (height prop ignored). */
  fill: {
    type: Boolean,
    default: false,
  },
  /** Cursor over the map (e.g. crosshair while placing a point). */
  cursor: {
    type: String,
    default: 'grab',
    validator: (v) => ['grab', 'crosshair', 'default', 'pointer'].includes(v),
  },
})

const emit = defineEmits(['ready', 'click'])

const mapReady = ref(false)
const leafletObject = shallowRef(null)
const iconsReady = ref(false)

const style = computed(() =>
  props.fill
    ? { height: '100%', width: '100%', minHeight: '240px' }
    : { height: props.height, width: '100%' },
)

const mapClass = computed(() => ({
  'app-map--fill': props.fill,
  [`app-map--cursor-${props.cursor}`]: true,
}))

async function ensureDefaultIcons() {
  // Must use the same Leaflet ESM instance as vue-leaflet (useGlobalLeaflet=false).
  const leaflet = await import('leaflet/dist/leaflet-src.esm')
  const L = leaflet.default ?? leaflet
  if (L?.Icon?.Default?.prototype) {
    delete L.Icon.Default.prototype._getIconUrl
    L.Icon.Default.mergeOptions({
      iconRetinaUrl: markerIcon2x,
      iconUrl: markerIcon,
      shadowUrl: markerShadow,
    })
  }
  iconsReady.value = true
}

async function onReady(map) {
  await ensureDefaultIcons()
  leafletObject.value = map
  mapReady.value = true
  // Invalidate size after dialog/layout settles.
  requestAnimationFrame(() => {
    try {
      map?.invalidateSize?.()
    } catch {
      /* ignore */
    }
  })
  emit('ready', map)
}

function onClick(event) {
  const latlng = event?.latlng
  if (!latlng) return
  emit('click', { lat: latlng.lat, lng: latlng.lng, originalEvent: event })
}

defineExpose({
  getMap: () => leafletObject.value,
  mapReady,
  iconsReady,
})
</script>

<template>
  <div class="app-map" :class="mapClass" :style="style">
    <LMap
      :zoom="zoom"
      :center="center"
      :use-global-leaflet="false"
      style="height: 100%; width: 100%"
      @ready="onReady"
      @click="onClick"
    >
      <LTileLayer :url="OSM_TILE_URL" :attribution="OSM_ATTRIBUTION" layer-type="base" name="OSM" />
      <slot />
    </LMap>
  </div>
</template>

<style scoped>
.app-map {
  overflow: hidden;
  border-radius: 0.5rem;
  border: 1px solid var(--layout-border, #e5e7eb);
  background: var(--layout-surface-muted, #f8fafc);
}

.app-map--fill {
  border-radius: 0;
  border: none;
}

.app-map--cursor-grab :deep(.leaflet-container) {
  cursor: grab;
}

.app-map--cursor-grab :deep(.leaflet-container.leaflet-dragging),
.app-map--cursor-grab :deep(.leaflet-container.leaflet-drag-target) {
  cursor: grabbing;
}

.app-map--cursor-crosshair :deep(.leaflet-container),
.app-map--cursor-crosshair :deep(.leaflet-container.leaflet-grab),
.app-map--cursor-crosshair :deep(.leaflet-interactive) {
  cursor: crosshair !important;
}

.app-map--cursor-pointer :deep(.leaflet-container) {
  cursor: pointer;
}

.app-map--cursor-default :deep(.leaflet-container) {
  cursor: default;
}
</style>
