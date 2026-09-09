<script setup>
import { computed, ref, watch } from 'vue'
import InputNumber from 'primevue/inputnumber'
import Button from 'primevue/button'
import AutoComplete from 'primevue/autocomplete'
import AppMap from '@/domains/shared/maps/components/AppMap.vue'
import AppMapMarker from '@/domains/shared/maps/components/AppMapMarker.vue'
import { useGeolocation } from '@/domains/shared/maps/composables/useGeolocation'
import { fitMapToPoints } from '@/domains/shared/maps/composables/useMapFitBounds'
import { geocodeSearch, reverseGeocode } from '@/domains/shared/maps/services/geoService'
import { DEFAULT_MAP_CENTER, DEFAULT_MAP_ZOOM } from '@/domains/shared/maps/mapDefaults'

const latitude = defineModel('latitude', { default: null })
const longitude = defineModel('longitude', { default: null })

const props = defineProps({
  height: { type: String, default: '260px' },
  disabled: { type: Boolean, default: false },
  /** Enable address search (requires geo.use + ORS). */
  enableGeocode: { type: Boolean, default: true },
})

const mapRef = ref(null)
const addressLabel = ref('')
const geocodeSuggestions = ref([])
const geocodeQuery = ref(null)
const reverseBusy = ref(false)
const geocodeBusy = ref(false)
const { locating, error: geoError, locate } = useGeolocation()

const hasPoint = computed(
  () => Number.isFinite(latitude.value) && Number.isFinite(longitude.value),
)

const mapCenter = computed(() =>
  hasPoint.value ? [latitude.value, longitude.value] : [...DEFAULT_MAP_CENTER],
)

const mapZoom = computed(() => (hasPoint.value ? 15 : DEFAULT_MAP_ZOOM))

function setPoint(lat, lng, { reverse = true } = {}) {
  if (props.disabled) return
  latitude.value = Number(lat)
  longitude.value = Number(lng)
  if (reverse && props.enableGeocode) {
    void refreshReverse()
  }
}

function onMapClick({ lat, lng }) {
  setPoint(lat, lng)
}

function onMarkerDrag({ lat, lng }) {
  setPoint(lat, lng)
}

function onLatInput(value) {
  latitude.value = value
  if (hasPoint.value && props.enableGeocode) void refreshReverse()
}

function onLngInput(value) {
  longitude.value = value
  if (hasPoint.value && props.enableGeocode) void refreshReverse()
}

function clearPoint() {
  if (props.disabled) return
  latitude.value = null
  longitude.value = null
  addressLabel.value = ''
  geocodeQuery.value = null
}

async function useMyLocation() {
  const point = await locate()
  if (!point) return
  setPoint(point.lat, point.lng)
  const map = mapRef.value?.getMap?.()
  fitMapToPoints(map, [point], { maxZoom: 16 })
}

async function refreshReverse() {
  if (!hasPoint.value || !props.enableGeocode) return
  reverseBusy.value = true
  try {
    const item = await reverseGeocode(latitude.value, longitude.value)
    addressLabel.value = item?.label || ''
  } catch {
    addressLabel.value = ''
  } finally {
    reverseBusy.value = false
  }
}

async function searchAddress(event) {
  const q = String(event.query || '').trim()
  if (q.length < 3 || !props.enableGeocode) {
    geocodeSuggestions.value = []
    return
  }
  geocodeBusy.value = true
  try {
    geocodeSuggestions.value = await geocodeSearch(q, 6)
  } catch {
    geocodeSuggestions.value = []
  } finally {
    geocodeBusy.value = false
  }
}

function onSelectSuggestion(event) {
  const item = event?.value
  if (!item || !Number.isFinite(item.lat) || !Number.isFinite(item.lng)) return
  setPoint(item.lat, item.lng, { reverse: false })
  addressLabel.value = item.label || ''
  geocodeQuery.value = item
  const map = mapRef.value?.getMap?.()
  fitMapToPoints(map, [{ lat: item.lat, lng: item.lng }], { maxZoom: 16 })
}

watch(
  () => [latitude.value, longitude.value],
  () => {
    if (hasPoint.value) {
      const map = mapRef.value?.getMap?.()
      fitMapToPoints(map, [{ lat: latitude.value, lng: longitude.value }], { maxZoom: 16 })
    }
  },
)
</script>

<template>
  <div class="app-map-marker-picker">
    <div v-if="enableGeocode" class="app-map-marker-picker__search">
      <label>Rechercher une adresse</label>
      <AutoComplete
        v-model="geocodeQuery"
        :suggestions="geocodeSuggestions"
        option-label="label"
        placeholder="Adresse, lieu…"
        force-selection
        fluid
        :disabled="disabled || geocodeBusy"
        @complete="searchAddress"
        @item-select="onSelectSuggestion"
      />
    </div>

    <AppMap
      ref="mapRef"
      :center="mapCenter"
      :zoom="mapZoom"
      :height="height"
      @click="onMapClick"
    >
      <AppMapMarker
        v-if="hasPoint"
        :lat="latitude"
        :lng="longitude"
        :draggable="!disabled"
        @dragend="onMarkerDrag"
      />
    </AppMap>

    <div class="app-map-marker-picker__coords">
      <div class="field">
        <label>Latitude</label>
        <InputNumber
          :model-value="latitude"
          :min-fraction-digits="0"
          :max-fraction-digits="7"
          :min="-90"
          :max="90"
          :disabled="disabled"
          fluid
          @update:model-value="onLatInput"
        />
      </div>
      <div class="field">
        <label>Longitude</label>
        <InputNumber
          :model-value="longitude"
          :min-fraction-digits="0"
          :max-fraction-digits="7"
          :min="-180"
          :max="180"
          :disabled="disabled"
          fluid
          @update:model-value="onLngInput"
        />
      </div>
    </div>

    <p v-if="addressLabel" class="app-map-marker-picker__label">
      <i class="pi pi-map-marker" />
      {{ reverseBusy ? '…' : addressLabel }}
    </p>
    <p v-if="geoError" class="app-map-marker-picker__error">{{ geoError }}</p>

    <div class="app-map-marker-picker__actions">
      <Button
        type="button"
        label="Ma position"
        icon="pi pi-map-marker"
        severity="secondary"
        outlined
        size="small"
        :loading="locating"
        :disabled="disabled"
        @click="useMyLocation"
      />
      <Button
        v-if="hasPoint"
        type="button"
        label="Effacer"
        icon="pi pi-times"
        severity="secondary"
        text
        size="small"
        :disabled="disabled"
        @click="clearPoint"
      />
    </div>
    <small class="app-map-marker-picker__hint">
      Cliquez sur la carte ou déplacez le marqueur pour fixer la position.
    </small>
  </div>
</template>

<style scoped>
.app-map-marker-picker {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
}

.app-map-marker-picker__search,
.field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.app-map-marker-picker__coords {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem;
}

.app-map-marker-picker__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.app-map-marker-picker__label {
  margin: 0;
  font-size: 0.875rem;
  color: var(--layout-text-muted, #64748b);
}

.app-map-marker-picker__error {
  margin: 0;
  font-size: 0.8125rem;
  color: var(--p-red-500, #ef4444);
}

.app-map-marker-picker__hint {
  color: var(--layout-text-muted, #64748b);
  font-size: 0.75rem;
}

label {
  font-size: 0.8125rem;
  font-weight: 500;
}
</style>
