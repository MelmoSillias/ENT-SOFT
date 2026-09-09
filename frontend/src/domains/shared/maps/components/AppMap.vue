<script setup>
import { computed, onMounted, shallowRef, ref } from 'vue'
import { LMap, LTileLayer } from '@vue-leaflet/vue-leaflet'
import L from 'leaflet'
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

if (typeof window !== 'undefined') {
  window.L = L
}

delete L.Icon.Default.prototype._getIconUrl
L.Icon.Default.mergeOptions({
  iconRetinaUrl: markerIcon2x,
  iconUrl: markerIcon,
  shadowUrl: markerShadow,
})

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
})

const emit = defineEmits(['ready', 'click'])

const mapReady = ref(false)
const leafletObject = shallowRef(null)

const style = computed(() =>
  props.fill
    ? { height: '100%', width: '100%', minHeight: '240px' }
    : { height: props.height, width: '100%' },
)

function onReady(map) {
  leafletObject.value = map
  mapReady.value = true
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
})
</script>

<template>
  <div class="app-map" :class="{ 'app-map--fill': fill }" :style="style">
    <LMap
      :zoom="zoom"
      :center="center"
      :use-global-leaflet="true"
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
</style>
