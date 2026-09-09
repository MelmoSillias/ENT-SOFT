<script setup>
import { computed } from 'vue'
import { LPolyline } from '@vue-leaflet/vue-leaflet'

const props = defineProps({
  /** list of { lat, lng } */
  coordinates: {
    type: Array,
    default: () => [],
  },
  color: {
    type: String,
    default: '#2563eb',
  },
  weight: {
    type: Number,
    default: 4,
  },
})

const latLngs = computed(() =>
  (props.coordinates || [])
    .filter((p) => Number.isFinite(p.lat) && Number.isFinite(p.lng))
    .map((p) => [p.lat, p.lng]),
)
</script>

<template>
  <LPolyline
    v-if="latLngs.length >= 2"
    :lat-lngs="latLngs"
    :color="color"
    :weight="weight"
  />
</template>
