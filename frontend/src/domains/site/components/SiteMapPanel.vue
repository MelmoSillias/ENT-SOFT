<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import Button from 'primevue/button'
import Select from 'primevue/select'
import Dialog from 'primevue/dialog'
import Accordion from 'primevue/accordion'
import AccordionPanel from 'primevue/accordionpanel'
import AccordionHeader from 'primevue/accordionheader'
import AccordionContent from 'primevue/accordioncontent'
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
import { useAppMobileLayout } from '@/domains/layout/composables/useAppMobileLayout'

const props = defineProps({
  /** Filtered site list from parent. */
  sites: { type: Array, default: () => [] },
  clientMap: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['edit', 'create-at', 'assign-location'])

const toast = useAppToast()
const { isAppMobile } = useAppMobileLayout()
const { hasPermission } = usePermissions()
const canRoute = computed(() => hasPermission('geo.use'))
const canCreate = computed(() => hasPermission('site.sites.create'))
const canUpdate = computed(() => hasPermission('site.sites.update'))
const canPlaceSite = computed(() => canCreate.value || canUpdate.value)

const mapRef = ref(null)
const inlineHost = ref(null)
const dialogHost = ref(null)
const teleportTo = ref(null)
const selectedId = ref(null)
const routeFromId = ref(null)
const routeCoords = ref([])
const routeDistance = ref(null)
const routeDuration = ref(null)
const routeLoading = ref(false)
const profile = ref('driving-car')
const myPosition = ref(null)
const expanded = ref(false)
const pickedPoint = ref(null)
const assignSiteId = ref(null)
const sideAccordion = ref(
  hasPermission('site.sites.update')
    ? ['link']
    : hasPermission('site.sites.create')
      ? ['create']
      : [],
)

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

const activePoint = computed(() => {
  if (pickedPoint.value) return pickedPoint.value
  if (selectedSite.value) {
    return { lat: selectedSite.value.latitude, lng: selectedSite.value.longitude }
  }
  return null
})

const mapHeight = computed(() => {
  if (expanded.value) return '100%'
  return isAppMobile.value ? '320px' : '460px'
})

const dialogContentStyle = computed(() => ({
  padding: '0.85rem',
  height: isAppMobile.value ? 'auto' : 'min(80vh, 740px)',
  maxHeight: '85vh',
  overflow: isAppMobile.value ? 'auto' : 'hidden',
}))

function onMapReady(map) {
  fitToSites(map)
}

function invalidateMap() {
  nextTick(() => {
    requestAnimationFrame(() => {
      try {
        const api = mapRef.value
        if (!api || typeof api.getMap !== 'function') return
        const leafletMap = api.getMap()
        if (!leafletMap || typeof leafletMap.invalidateSize !== 'function') return
        leafletMap.invalidateSize()
      } catch {
        /* ignore */
      }
    })
  })
}

function fitToSites(map) {
  let leafletMap = map
  if (!leafletMap) {
    const api = mapRef.value
    leafletMap = api && typeof api.getMap === 'function' ? api.getMap() : null
  }

  const points = sitesWithCoords.value.map((s) => ({ lat: s.latitude, lng: s.longitude }))
  if (myPosition.value) points.push(myPosition.value)
  if (pickedPoint.value) points.push(pickedPoint.value)
  if (routeCoords.value.length) {
    fitMapToPoints(leafletMap, routeCoords.value, { maxZoom: 15 })
    return
  }
  fitMapToPoints(
    leafletMap,
    points.length ? points : [{ lat: DEFAULT_MAP_CENTER[0], lng: DEFAULT_MAP_CENTER[1] }],
    { maxZoom: points.length ? 14 : DEFAULT_MAP_ZOOM },
  )
}

watch(
  () => sitesWithCoords.value.map((s) => s.id).join(','),
  () => fitToSites(),
)

watch(pickedPoint, (point) => {
  if (point && canPlaceSite.value) {
    sideAccordion.value = hasPermission('site.sites.update') ? ['link'] : ['create']
  }
})

watch(expanded, async (open, wasOpen) => {
  if (open || !wasOpen) return
  // Move content back before Dialog tears down its host.
  teleportTo.value = inlineHost.value
  await nextTick()
  invalidateMap()
})

onMounted(() => {
  teleportTo.value = inlineHost.value
})

async function onDialogShow() {
  await nextTick()
  teleportTo.value = dialogHost.value
  await nextTick()
  invalidateMap()
  fitToSites()
}

function selectSite(site) {
  selectedId.value = site.id
  pickedPoint.value = null
  assignSiteId.value = null
}

function openExpanded() {
  expanded.value = true
}

function onMapClick({ lat, lng, originalEvent }) {
  const target = originalEvent?.originalEvent?.target || originalEvent?.target
  if (target?.closest?.('.leaflet-marker-icon, .app-map-pin, .leaflet-popup')) {
    return
  }
  pickedPoint.value = { lat, lng }
  selectedId.value = null
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

function assignPickedToSite() {
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

function openActiveWithMaps() {
  if (!activePoint.value) {
    toast.add({ severity: 'warn', summary: 'Maps', detail: 'Sélectionnez un point sur la carte.' })
    return
  }
  openWithMaps(activePoint.value.lat, activePoint.value.lng)
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

function siteVariant(site) {
  return site.id === selectedId.value ? 'selected' : 'default'
}
</script>

<template>
  <div class="site-map-panel" :class="{ 'site-map-panel--mobile': isAppMobile }">
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
          v-if="activePoint"
          type="button"
          label="Ouvrir avec Maps"
          icon="pi pi-external-link"
          size="small"
          severity="secondary"
          outlined
          @click="openActiveWithMaps"
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
          label="Agrandir"
          icon="pi pi-window-maximize"
          size="small"
          severity="secondary"
          text
          @click="openExpanded"
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

    <div
      ref="inlineHost"
      class="site-map-panel__host"
      :class="{ 'site-map-panel__host--hidden': expanded }"
    />

    <p v-if="!sitesWithCoords.length && !pickedPoint" class="site-map-panel__empty">
      Aucun site avec coordonnées dans la sélection actuelle.
    </p>
  </div>

  <Dialog
    v-model:visible="expanded"
    header="Carte des sites"
    modal
    dismissable-mask
    class="site-map-dialog"
    :style="{ width: 'min(1100px, 96vw)' }"
    :content-style="dialogContentStyle"
    @show="onDialogShow"
  >
    <div
      ref="dialogHost"
      class="site-map-panel__host site-map-panel__host--dialog"
      :class="{ 'site-map-panel__host--mobile': isAppMobile }"
    />
  </Dialog>

  <Teleport v-if="teleportTo" :to="teleportTo">
    <div
      class="site-map-panel__body"
      :class="{
        'site-map-panel__body--mobile': isAppMobile,
        'site-map-panel__body--dialog': expanded,
      }"
    >
      <div class="site-map-panel__map-wrap">
        <AppMap
          ref="mapRef"
          :center="DEFAULT_MAP_CENTER"
          :zoom="DEFAULT_MAP_ZOOM"
          :height="mapHeight"
          :fill="expanded"
          cursor="crosshair"
          @ready="onMapReady"
          @click="onMapClick"
        >
          <AppMapMarker
            v-for="site in sitesWithCoords"
            :key="site.id"
            :lat="site.latitude"
            :lng="site.longitude"
            :variant="siteVariant(site)"
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
            variant="me"
          >
            Ma position
          </AppMapMarker>

          <AppMapMarker
            v-if="pickedPoint"
            :lat="pickedPoint.lat"
            :lng="pickedPoint.lng"
            variant="picked"
          >
            Point sélectionné
          </AppMapMarker>

          <AppMapRoute :coordinates="routeCoords" />
        </AppMap>
        <p class="site-map-panel__map-hint">
          Cliquez sur la carte pour sélectionner un point.
        </p>
      </div>

      <aside class="site-map-panel__side">
        <template v-if="pickedPoint">
          <div class="site-map-side__head">
            <i class="pi pi-map-marker site-map-side__icon site-map-side__icon--picked" />
            <div>
              <h3 class="site-map-side__title">Point sélectionné</h3>
              <p class="muted site-map-side__coords">
                {{ pickedPoint.lat.toFixed(6) }}, {{ pickedPoint.lng.toFixed(6) }}
              </p>
            </div>
          </div>

          <div class="site-map-side__actions">
            <Button
              type="button"
              label="Ouvrir avec Maps"
              icon="pi pi-external-link"
              size="small"
              severity="secondary"
              outlined
              fluid
              @click="openWithMaps(pickedPoint.lat, pickedPoint.lng)"
            />
            <Button
              type="button"
              label="Effacer"
              icon="pi pi-times"
              size="small"
              severity="secondary"
              text
              fluid
              @click="clearPickedPoint"
            />
          </div>

          <Accordion
            v-if="canPlaceSite"
            v-model:value="sideAccordion"
            multiple
            class="site-map-side__accordion"
          >
            <AccordionPanel v-if="canCreate" value="create">
              <AccordionHeader>Nouveau site à cet emplacement</AccordionHeader>
              <AccordionContent>
                <p class="site-map-side__help">
                  Ouvre le formulaire de création avec ces coordonnées préremplies.
                </p>
                <Button
                  type="button"
                  label="Créer un site"
                  icon="pi pi-plus"
                  size="small"
                  fluid
                  @click="openCreateAtPicked"
                />
              </AccordionContent>
            </AccordionPanel>
            <AccordionPanel v-if="canUpdate" value="link">
              <AccordionHeader>Lier à un site existant</AccordionHeader>
              <AccordionContent>
                <p class="site-map-side__help">
                  Affecte cette position à un site déjà créé.
                </p>
                <Select
                  v-model="assignSiteId"
                  :options="siteAssignOptions"
                  option-label="label"
                  option-value="value"
                  placeholder="Choisir un site…"
                  filter
                  show-clear
                  fluid
                  class="site-map-side__select"
                />
                <Button
                  type="button"
                  label="Affecter la position"
                  icon="pi pi-check"
                  size="small"
                  fluid
                  class="site-map-side__assign-btn"
                  :disabled="!assignSiteId"
                  @click="assignPickedToSite"
                />
              </AccordionContent>
            </AccordionPanel>
          </Accordion>
        </template>

        <template v-else-if="selectedSite">
          <div class="site-map-side__head">
            <i class="pi pi-building site-map-side__icon site-map-side__icon--site" />
            <div>
              <h3 class="site-map-side__title">{{ selectedSite.title }}</h3>
              <p class="muted">{{ selectedSite.code }}</p>
            </div>
          </div>
          <p v-if="clientMap[selectedSite.clientId]" class="muted site-map-side__meta">
            Client : {{ clientMap[selectedSite.clientId] }}
          </p>
          <p class="muted site-map-side__coords">
            {{ Number(selectedSite.latitude).toFixed(6) }},
            {{ Number(selectedSite.longitude).toFixed(6) }}
          </p>
          <div class="site-map-side__actions">
            <Button
              type="button"
              label="Modifier"
              icon="pi pi-pencil"
              size="small"
              fluid
              @click="emit('edit', selectedSite)"
            />
            <Button
              type="button"
              label="Ouvrir avec Maps"
              icon="pi pi-external-link"
              size="small"
              severity="secondary"
              outlined
              fluid
              @click="openWithMaps(selectedSite.latitude, selectedSite.longitude)"
            />
            <Button
              v-if="canRoute"
              type="button"
              label="Itinéraire depuis moi"
              icon="pi pi-directions"
              size="small"
              severity="secondary"
              text
              fluid
              :loading="routeLoading"
              @click="routeFromMyPosition(selectedSite)"
            />
          </div>
        </template>

        <template v-else>
          <div class="site-map-side__empty">
            <i class="pi pi-map" />
            <p>Sélectionnez un site ou cliquez sur la carte pour choisir un point.</p>
          </div>
        </template>
      </aside>
    </div>
  </Teleport>
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
  align-items: center;
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

.site-map-panel__host {
  min-height: 0;
}

.site-map-panel__host--hidden {
  display: none;
}

.site-map-panel__host--dialog {
  height: 100%;
  min-height: min(70vh, 640px);
}

.site-map-panel__host--dialog.site-map-panel__host--mobile {
  min-height: 0;
  height: auto;
}

.site-map-panel__body {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(260px, 320px);
  gap: 0.85rem;
  align-items: stretch;
  min-height: 0;
}

.site-map-panel__body--dialog {
  height: 100%;
}

.site-map-panel__body--dialog .site-map-panel__map-wrap {
  min-height: 0;
  height: 100%;
}

.site-map-panel__body--dialog .site-map-panel__map-wrap :deep(.app-map) {
  height: 100%;
  min-height: 280px;
}

.site-map-panel__body--mobile {
  grid-template-columns: 1fr;
}

.site-map-panel__map-wrap {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  min-width: 0;
}

.site-map-panel__map-hint {
  margin: 0;
  font-size: 0.75rem;
  color: var(--layout-text-muted, #64748b);
}

.site-map-panel__side {
  border: 1px solid var(--layout-border, #e5e7eb);
  border-radius: 0.5rem;
  background: var(--layout-surface-muted, #f8fafc);
  padding: 0.85rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  min-height: 12rem;
  overflow: auto;
}

.site-map-panel__body--mobile .site-map-panel__side {
  order: 2;
  min-height: 0;
}

.site-map-panel__body--mobile .site-map-panel__map-wrap {
  order: 1;
}

.site-map-side__head {
  display: flex;
  gap: 0.65rem;
  align-items: flex-start;
}

.site-map-side__icon {
  width: 2rem;
  height: 2rem;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  background: #fff;
  border: 1px solid var(--layout-border, #e5e7eb);
}

.site-map-side__icon--picked {
  color: #ea580c;
}

.site-map-side__icon--site {
  color: #1a3066;
}

.site-map-side__title {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 600;
  line-height: 1.3;
}

.site-map-side__coords,
.site-map-side__meta {
  margin: 0.15rem 0 0;
  font-size: 0.8125rem;
  word-break: break-all;
}

.site-map-side__actions {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.site-map-side__help {
  margin: 0 0 0.65rem;
  font-size: 0.8125rem;
  color: var(--layout-text-muted, #64748b);
}

.site-map-side__select {
  margin-bottom: 0.55rem;
}

.site-map-side__assign-btn {
  margin-top: 0.15rem;
}

.site-map-side__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  text-align: center;
  color: var(--layout-text-muted, #64748b);
  padding: 1.25rem 0.5rem;
  flex: 1;
}

.site-map-side__empty i {
  font-size: 1.5rem;
}

.site-map-side__empty p {
  margin: 0;
  font-size: 0.875rem;
  max-width: 16rem;
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

@media (max-width: 900px) {
  .site-map-panel__body:not(.site-map-panel__body--mobile) {
    grid-template-columns: 1fr;
  }

  .site-map-panel__body:not(.site-map-panel__body--mobile) .site-map-panel__side {
    order: 2;
  }
}
</style>
