<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
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
  openWithMapsUrl,
} from '@/domains/shared/maps/mapDefaults'
import { usePermissions } from '@/domains/auth/composables/usePermissions'
import { useAppToast } from '@/domains/shared/composables/useAppToast'

const props = defineProps({
  /** Filtered site list from parent. */
  sites: { type: Array, default: () => [] },
  clientMap: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['edit', 'create-at', 'assign-location'])

const toast = useAppToast()
const { hasPermission } = usePermissions()
const canRoute = computed(() => hasPermission('geo.use'))
const canCreate = computed(() => hasPermission('site.sites.create'))
const canUpdate = computed(() => hasPermission('site.sites.update'))
const canPlaceSite = computed(() => canCreate.value || canUpdate.value)

const mapRef = ref(null)
const selectedId = ref(null)
const routeFromId = ref(null)
const routeCoords = ref([])
const routeDistance = ref(null)
const routeDuration = ref(null)
const routeLoading = ref(false)
const profile = ref('driving-car')
const myPosition = ref(null)
const expanded = ref(false)
const pickMode = ref(false)
const pickedPoint = ref(null)
const assignSiteId = ref(null)

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

const siteAssignOptions = computed(() =>
  (props.sites || []).map((s) => ({
    label: `${s.code} — ${s.title}`,
    value: s.id,
  })),
)

const selectedSite = computed(() =>
  sitesWithCoords.value.find((s) => s.id === selectedId.value) || null,
)

const mapCursor = computed(() => (pickMode.value ? 'crosshair' : 'grab'))
const mapHeight = computed(() => (expanded.value ? '100%' : '420px'))

function onMapReady(map) {
  fitToSites(map)
}

function invalidateMap() {
  nextTick(() => {
    requestAnimationFrame(() => {
      try {
        mapRef.value?.getMap?.()?.invalidateSize?.()
      } catch {
        /* ignore */
      }
    })
  })
}

function fitToSites(map = mapRef.value?.getMap?.()) {
  const points = sitesWithCoords.value.map((s) => ({ lat: s.latitude, lng: s.longitude }))
  if (myPosition.value) points.push(myPosition.value)
  if (pickedPoint.value) points.push(pickedPoint.value)
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

watch(expanded, (isExpanded) => {
  invalidateMap()
  document.body.style.overflow = isExpanded ? 'hidden' : ''
})

function onKeydown(event) {
  if (event.key === 'Escape' && expanded.value) {
    expanded.value = false
  }
  if (event.key === 'Escape' && pickMode.value) {
    pickMode.value = false
  }
}

onMounted(() => {
  window.addEventListener('keydown', onKeydown)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown)
  document.body.style.overflow = ''
})

function selectSite(site) {
  selectedId.value = site.id
  if (pickMode.value) return
}

function toggleExpanded() {
  expanded.value = !expanded.value
}

function togglePickMode() {
  if (!canPlaceSite.value) {
    toast.add({
      severity: 'warn',
      summary: 'Sites',
      detail: 'Permission création ou modification de site requise.',
    })
    return
  }
  pickMode.value = !pickMode.value
  if (!pickMode.value) return
  pickedPoint.value = null
  assignSiteId.value = null
  toast.add({
    severity: 'info',
    summary: 'Placer un site',
    detail: 'Cliquez sur la carte pour choisir un point.',
    life: 3500,
  })
}

function onMapClick({ lat, lng }) {
  if (!pickMode.value) return
  pickedPoint.value = { lat, lng }
  pickMode.value = false
  assignSiteId.value = null
}

function clearPickedPoint() {
  pickedPoint.value = null
  assignSiteId.value = null
}

function openCreateAtPicked() {
  if (!pickedPoint.value || !canCreate.value) return
  emit('create-at', { ...pickedPoint.value })
}

async function assignPickedToSite() {
  if (!pickedPoint.value || !assignSiteId.value || !canUpdate.value) {
    toast.add({
      severity: 'warn',
      summary: 'Affecter',
      detail: 'Sélectionnez un site existant.',
    })
    return
  }
  const payload = {
    siteId: assignSiteId.value,
    lat: pickedPoint.value.lat,
    lng: pickedPoint.value.lng,
  }
  clearPickedPoint()
  emit('assign-location', payload)
}

function openWithMaps(lat, lng) {
  window.open(openWithMapsUrl(lat, lng), '_blank', 'noopener,noreferrer')
}

function openSelectedWithMaps() {
  const site = selectedSite.value
  if (!site) {
    toast.add({ severity: 'warn', summary: 'Maps', detail: 'Sélectionnez un site sur la carte.' })
    return
  }
  openWithMaps(site.latitude, site.longitude)
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

function openGoogleDirections(site) {
  const to = { lat: site.latitude, lng: site.longitude }
  const from = myPosition.value || to
  window.open(googleMapsDirectionsUrl(from, to), '_blank', 'noopener,noreferrer')
}
</script>

<template>
  <div class="site-map-panel" :class="{ 'site-map-panel--expanded': expanded }">
    <div class="site-map-panel__toolbar">
      <div class="site-map-panel__stats">
        <span>{{ sitesWithCoords.length }} site(s) géolocalisé(s)</span>
        <span v-if="sitesWithoutCoordsCount" class="muted">
          · {{ sitesWithoutCoordsCount }} sans position
        </span>
        <span v-if="pickMode" class="site-map-panel__pick-hint">
          · Cliquez sur la carte…
        </span>
      </div>
      <div class="site-map-panel__actions">
        <Button
          v-if="canPlaceSite"
          type="button"
          :label="pickMode ? 'Annuler placement' : 'Placer un site'"
          :icon="pickMode ? 'pi pi-times' : 'pi pi-plus'"
          size="small"
          :severity="pickMode ? 'warn' : 'secondary'"
          :outlined="!pickMode"
          @click="togglePickMode"
        />
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
          v-if="selectedSite"
          type="button"
          label="Ouvrir avec Maps"
          icon="pi pi-external-link"
          size="small"
          severity="secondary"
          outlined
          @click="openSelectedWithMaps"
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
        <Button
          type="button"
          :label="expanded ? 'Réduire' : 'Agrandir'"
          :icon="expanded ? 'pi pi-window-minimize' : 'pi pi-window-maximize'"
          size="small"
          severity="secondary"
          text
          @click="toggleExpanded"
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

    <div v-if="pickedPoint" class="site-map-panel__picked">
      <div class="site-map-panel__picked-info">
        <strong>Point sélectionné</strong>
        <span class="muted">
          {{ pickedPoint.lat.toFixed(5) }}, {{ pickedPoint.lng.toFixed(5) }}
        </span>
      </div>
      <div class="site-map-panel__picked-actions">
        <Button
          type="button"
          label="Ouvrir avec Maps"
          icon="pi pi-external-link"
          size="small"
          severity="secondary"
          outlined
          @click="openWithMaps(pickedPoint.lat, pickedPoint.lng)"
        />
        <Button
          v-if="canCreate"
          type="button"
          label="Nouveau site"
          icon="pi pi-plus"
          size="small"
          @click="openCreateAtPicked"
        />
        <Select
          v-if="canUpdate"
          v-model="assignSiteId"
          :options="siteAssignOptions"
          option-label="label"
          option-value="value"
          placeholder="Site existant…"
          filter
          show-clear
          class="site-map-panel__assign"
        />
        <Button
          v-if="canUpdate"
          type="button"
          label="Affecter"
          icon="pi pi-check"
          size="small"
          :disabled="!assignSiteId"
          @click="assignPickedToSite"
        />
        <Button
          type="button"
          label="Effacer"
          icon="pi pi-times"
          size="small"
          severity="secondary"
          text
          @click="clearPickedPoint"
        />
      </div>
    </div>

    <div class="site-map-panel__map-wrap">
      <AppMap
        ref="mapRef"
        :center="DEFAULT_MAP_CENTER"
        :zoom="DEFAULT_MAP_ZOOM"
        :height="mapHeight"
        :fill="expanded"
        :cursor="mapCursor"
        @ready="onMapReady"
        @click="onMapClick"
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
              <Button
                type="button"
                label="Ouvrir avec Maps"
                size="small"
                text
                @click="openWithMaps(site.latitude, site.longitude)"
              />
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

        <AppMapMarker
          v-if="pickedPoint"
          :lat="pickedPoint.lat"
          :lng="pickedPoint.lng"
        >
          Point sélectionné
        </AppMapMarker>

        <AppMapRoute :coordinates="routeCoords" />
      </AppMap>
    </div>

    <p v-if="!sitesWithCoords.length && !pickedPoint" class="site-map-panel__empty">
      Aucun site avec coordonnées dans la sélection actuelle.
      Utilisez « Placer un site » ou ajoutez une position via le formulaire.
    </p>
  </div>
</template>

<style scoped>
.site-map-panel {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.site-map-panel--expanded {
  position: fixed;
  inset: 0;
  z-index: 1200;
  padding: 0.85rem 1rem 1rem;
  background: var(--layout-surface, #fff);
  box-sizing: border-box;
}

.site-map-panel--expanded .site-map-panel__map-wrap {
  flex: 1;
  min-height: 0;
}

.site-map-panel--expanded .site-map-panel__map-wrap :deep(.app-map) {
  height: 100%;
  min-height: 0;
  border-radius: 0.5rem;
}

.site-map-panel__toolbar,
.site-map-panel__route-bar,
.site-map-panel__summary,
.site-map-panel__picked {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem 0.75rem;
}

.site-map-panel__actions,
.site-map-panel__picked-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  margin-left: auto;
  align-items: center;
}

.site-map-panel__stats {
  font-size: 0.875rem;
}

.site-map-panel__pick-hint {
  color: var(--p-orange-500, #f59e0b);
  font-weight: 500;
}

.site-map-panel__profile {
  min-width: 8rem;
}

.site-map-panel__from,
.site-map-panel__assign {
  min-width: 12rem;
  flex: 1;
}

.site-map-panel__assign {
  max-width: 18rem;
}

.site-map-panel__summary {
  font-size: 0.875rem;
  color: var(--layout-text-muted, #64748b);
}

.site-map-panel__summary i {
  margin-right: 0.3rem;
}

.site-map-panel__picked {
  padding: 0.65rem 0.75rem;
  border-radius: 0.5rem;
  border: 1px solid var(--layout-border, #e5e7eb);
  background: var(--layout-surface-muted, #f8fafc);
}

.site-map-panel__picked-info {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  font-size: 0.875rem;
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
