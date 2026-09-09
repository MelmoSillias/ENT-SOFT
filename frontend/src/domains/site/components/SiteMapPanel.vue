<script setup>
import { computed, ref, watch } from 'vue'
import Button from 'primevue/button'
import Select from 'primevue/select'
import AppMap from '@/domains/shared/maps/components/AppMap.vue'
import AppMapMarker from '@/domains/shared/maps/components/AppMapMarker.vue'
import AppMapRoute from '@/domains/shared/maps/components/AppMapRoute.vue'
import { useGeolocation } from '@/domains/shared/maps/composables/useGeolocation'
import { fitMapToPoints } from '@/domains/shared/maps/composables/useMapFitBounds'
import { getDirections } from '@/domains/shared/maps/services/geoService'
import {
  DEFAULT_MAP_CENTER,
  DEFAULT_MAP_ZOOM,
  formatDistance,
  formatDuration,
  googleMapsDirectionsUrl,
  googleMapsUrl,
  openStreetMapUrl,
} from '@/domains/shared/maps/mapDefaults'
import { usePermissions } from '@/domains/auth/composables/usePermissions'
import { useAppToast } from '@/domains/shared/composables/useAppToast'

const props = defineProps({
  /** Filtered site list from parent. */
  sites: { type: Array, default: () => [] },
  clientMap: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['edit'])

const toast = useAppToast()
const { hasPermission } = usePermissions()
const canRoute = computed(() => hasPermission('geo.use'))

const mapRef = ref(null)
const selectedId = ref(null)
const routeFromId = ref(null)
const routeCoords = ref([])
const routeDistance = ref(null)
const routeDuration = ref(null)
const routeLoading = ref(false)
const profile = ref('driving-car')
const myPosition = ref(null)

const { locating, locate } = useGeolocation()

const profileOptions = [
  { label: 'Voiture', value: 'driving-car' },
  { label: 'À pied', value: 'foot-walking' },
  { label: 'Vélo', value: 'cycling-regular' },
]

const sitesWithCoords = computed(() =>
  (props.sites || []).filter(
    (s) => Number.isFinite(s.latitude) && Number.isFinite(s.longitude),
  ),
)

const sitesWithoutCoordsCount = computed(
  () => (props.sites || []).length - sitesWithCoords.value.length,
)

const selectedSite = computed(() =>
  sitesWithCoords.value.find((s) => s.id === selectedId.value) || null,
)

function onMapReady(map) {
  fitToSites(map)
}

function fitToSites(map = mapRef.value?.getMap?.()) {
  const points = sitesWithCoords.value.map((s) => ({ lat: s.latitude, lng: s.longitude }))
  if (myPosition.value) points.push(myPosition.value)
  if (routeCoords.value.length) {
    fitMapToPoints(map, routeCoords.value, { maxZoom: 15 })
    return
  }
  fitMapToPoints(map, points.length ? points : [{ lat: DEFAULT_MAP_CENTER[0], lng: DEFAULT_MAP_CENTER[1] }], {
    maxZoom: points.length ? 14 : DEFAULT_MAP_ZOOM,
  })
}

watch(
  () => sitesWithCoords.value.map((s) => s.id).join(','),
  () => fitToSites(),
)

function selectSite(site) {
  selectedId.value = site.id
}

async function captureMyPosition() {
  const point = await locate()
  if (!point) {
    toast.add({ severity: 'warn', summary: 'Géo', detail: 'Position indisponible.' })
    return
  }
  myPosition.value = point
  fitToSites()
}

function clearRoute() {
  routeCoords.value = []
  routeDistance.value = null
  routeDuration.value = null
  routeFromId.value = null
}

async function routeFromMyPosition(site) {
  if (!canRoute.value) {
    toast.add({ severity: 'warn', summary: 'Itinéraire', detail: 'Permission geo.use requise.' })
    return
  }
  let from = myPosition.value
  if (!from) {
    from = await locate()
    if (!from) {
      toast.add({ severity: 'warn', summary: 'Itinéraire', detail: 'Position indisponible.' })
      return
    }
    myPosition.value = from
  }
  selectedId.value = site.id
  await computeRoute(from, { lat: site.latitude, lng: site.longitude })
}

async function routeBetweenSites() {
  if (!canRoute.value) return
  const fromSite = sitesWithCoords.value.find((s) => s.id === routeFromId.value)
  const toSite = selectedSite.value
  if (!fromSite || !toSite || fromSite.id === toSite.id) {
    toast.add({
      severity: 'warn',
      summary: 'Itinéraire',
      detail: 'Sélectionnez un site de départ et un site d’arrivée distincts.',
    })
    return
  }
  await computeRoute(
    { lat: fromSite.latitude, lng: fromSite.longitude },
    { lat: toSite.latitude, lng: toSite.longitude },
  )
}

async function computeRoute(from, to) {
  routeLoading.value = true
  try {
    const result = await getDirections(from, to, profile.value)
    routeCoords.value = result.coordinates || []
    routeDistance.value = result.distanceMeters
    routeDuration.value = result.durationSeconds
    fitToSites()
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Itinéraire',
      detail: e.response?.data?.error || 'Impossible de calculer l’itinéraire.',
    })
  } finally {
    routeLoading.value = false
  }
}

function openExternal(site, kind) {
  const lat = site.latitude
  const lng = site.longitude
  const url =
    kind === 'osm'
      ? openStreetMapUrl(lat, lng)
      : googleMapsUrl(lat, lng)
  window.open(url, '_blank', 'noopener,noreferrer')
}

function openGoogleDirections(site) {
  const to = { lat: site.latitude, lng: site.longitude }
  const from = myPosition.value || to
  window.open(googleMapsDirectionsUrl(from, to), '_blank', 'noopener,noreferrer')
}
</script>

<template>
  <div class="site-map-panel">
    <div class="site-map-panel__toolbar">
      <div class="site-map-panel__stats">
        <span>{{ sitesWithCoords.length }} site(s) géolocalisé(s)</span>
        <span v-if="sitesWithoutCoordsCount" class="muted">
          · {{ sitesWithoutCoordsCount }} sans position
        </span>
      </div>
      <div class="site-map-panel__actions">
        <Button
          type="button"
          label="Ma position"
          icon="pi pi-map-marker"
          size="small"
          severity="secondary"
          outlined
          :loading="locating"
          @click="captureMyPosition"
        />
        <Button
          type="button"
          label="Recadrer"
          icon="pi pi-search"
          size="small"
          severity="secondary"
          text
          @click="fitToSites()"
        />
        <Button
          v-if="routeCoords.length"
          type="button"
          label="Effacer trajet"
          icon="pi pi-times"
          size="small"
          severity="secondary"
          text
          @click="clearRoute"
        />
      </div>
    </div>

    <div v-if="canRoute" class="site-map-panel__route-bar">
      <Select
        v-model="profile"
        :options="profileOptions"
        option-label="label"
        option-value="value"
        class="site-map-panel__profile"
      />
      <Select
        v-model="routeFromId"
        :options="sitesWithCoords"
        option-label="title"
        option-value="id"
        placeholder="Départ (site)"
        show-clear
        filter
        class="site-map-panel__from"
      />
      <Button
        type="button"
        label="Itinéraire sites"
        icon="pi pi-directions"
        size="small"
        :loading="routeLoading"
        :disabled="!routeFromId || !selectedId"
        @click="routeBetweenSites"
      />
    </div>

    <div v-if="routeDistance != null" class="site-map-panel__summary">
      <span><i class="pi pi-directions" /> {{ formatDistance(routeDistance) }}</span>
      <span><i class="pi pi-clock" /> {{ formatDuration(routeDuration) }}</span>
    </div>

    <div class="site-map-panel__map-wrap">
      <AppMap
        ref="mapRef"
        :center="DEFAULT_MAP_CENTER"
        :zoom="DEFAULT_MAP_ZOOM"
        height="420px"
        @ready="onMapReady"
      >
        <AppMapMarker
          v-for="site in sitesWithCoords"
          :key="site.id"
          :lat="site.latitude"
          :lng="site.longitude"
          @click="selectSite(site)"
        >
          <div class="site-map-popup">
            <strong>{{ site.code }} — {{ site.title }}</strong>
            <p v-if="clientMap[site.clientId]" class="muted">{{ clientMap[site.clientId] }}</p>
            <div class="site-map-popup__actions">
              <Button type="button" label="Modifier" size="small" text @click="emit('edit', site)" />
              <Button
                v-if="canRoute"
                type="button"
                label="Depuis moi"
                size="small"
                text
                :loading="routeLoading"
                @click="routeFromMyPosition(site)"
              />
              <Button type="button" label="Google" size="small" text @click="openExternal(site, 'google')" />
              <Button type="button" label="OSM" size="small" text @click="openExternal(site, 'osm')" />
              <Button type="button" label="Trajet Google" size="small" text @click="openGoogleDirections(site)" />
            </div>
          </div>
        </AppMapMarker>

        <AppMapMarker
          v-if="myPosition"
          :lat="myPosition.lat"
          :lng="myPosition.lng"
        >
          Ma position
        </AppMapMarker>

        <AppMapRoute :coordinates="routeCoords" />
      </AppMap>
    </div>

    <p v-if="!sitesWithCoords.length" class="site-map-panel__empty">
      Aucun site avec coordonnées dans la sélection actuelle. Ajoutez une position via le formulaire.
    </p>
  </div>
</template>

<style scoped>
.site-map-panel {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.site-map-panel__toolbar,
.site-map-panel__route-bar,
.site-map-panel__summary {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem 0.75rem;
}

.site-map-panel__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  margin-left: auto;
}

.site-map-panel__stats {
  font-size: 0.875rem;
}

.site-map-panel__profile {
  min-width: 8rem;
}

.site-map-panel__from {
  min-width: 12rem;
  flex: 1;
}

.site-map-panel__summary {
  font-size: 0.875rem;
  color: var(--layout-text-muted, #64748b);
}

.site-map-panel__summary i {
  margin-right: 0.3rem;
}

.muted {
  color: var(--layout-text-muted, #64748b);
}

.site-map-panel__empty {
  margin: 0;
  font-size: 0.875rem;
  color: var(--layout-text-muted, #64748b);
}

.site-map-popup {
  min-width: 12rem;
}

.site-map-popup p {
  margin: 0.25rem 0 0.5rem;
  font-size: 0.8125rem;
}

.site-map-popup__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.15rem;
}
</style>
