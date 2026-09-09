<script setup>
import { computed } from 'vue'
import { LMarker, LPopup } from '@vue-leaflet/vue-leaflet'

const props = defineProps({
  lat: { type: Number, required: true },
  lng: { type: Number, required: true },
  draggable: { type: Boolean, default: false },
})

const emit = defineEmits(['dragend', 'click'])

const latLng = computed(() => [props.lat, props.lng])

function onDragEnd(event) {
  const marker = event?.target
  const pos = marker?.getLatLng?.()
  if (!pos) return
  emit('dragend', { lat: pos.lat, lng: pos.lng })
}

function onClick(event) {
  emit('click', event)
}
</script>

<template>
  <LMarker
    :lat-lng="latLng"
    :draggable="draggable"
    @dragend="onDragEnd"
    @click="onClick"
  >
    <LPopup v-if="$slots.default">
      <slot />
    </LPopup>
  </LMarker>
</template>
