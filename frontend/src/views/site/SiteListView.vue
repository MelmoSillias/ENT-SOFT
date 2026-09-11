<script setup>
import { computed, defineAsyncComponent, onMounted, ref, watch } from 'vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Menu from 'primevue/menu'
import Dialog from 'primevue/dialog'
import SelectButton from 'primevue/selectbutton'
import AppTablePanelHeader from '@/domains/shared/components/AppTablePanelHeader.vue'
import AppTableState from '@/domains/shared/components/AppTableState.vue'
import AppTableSettingsPopover from '@/domains/shared/components/AppTableSettingsPopover.vue'
import AppRowContextMenu from '@/domains/shared/components/AppRowContextMenu.vue'
import AppEntityDataView from '@/domains/shared/components/AppEntityDataView.vue'
import AppMobileFab from '@/domains/shared/components/AppMobileFab.vue'
import AppMobileSegmentTabs from '@/domains/shared/components/AppMobileSegmentTabs.vue'
import AppDateTimeCell from '@/domains/shared/components/AppDateTimeCell.vue'
import { useAppMobileLayout } from '@/domains/layout/composables/useAppMobileLayout'
import { useTableSettings } from '@/domains/shared/composables/useTableSettings'
import { sortByField } from '@/domains/shared/utils/sortByField'
import SiteFormFields from '@/domains/site/components/SiteFormFields.vue'
import { listSites, createSite, updateSite, deleteSite } from '@/domains/site/services/siteService'
import { listClients } from '@/domains/client/services/clientService'
import { listEmployeePositions } from '@/domains/employee/services/employeePositionService'
import { periodToApiParams } from '@/domains/shared/utils/dateUtils'
import AppPeriodFilter from '@/domains/shared/components/AppPeriodFilter.vue'
import { hasRequiredText, requiredMessage } from '@/domains/shared/utils/formValidation'
import { useFormFieldErrors } from '@/domains/shared/composables/useFormFieldErrors'
import { useConfirm } from 'primevue/useconfirm'
import { useAsyncAction } from '@/domains/shared/composables/useAsyncAction'
import { usePermissions } from '@/domains/auth/composables/usePermissions'
import { useAppToast } from '@/domains/shared/composables/useAppToast'
import { formatDateTimeFr } from '@/domains/shared/utils/entLabels'

const SiteMapPanel = defineAsyncComponent(() => import('@/domains/site/components/SiteMapPanel.vue'))

const toast = useAppToast()
const confirm = useConfirm()
const { hasPermission, hasAnyPermission } = usePermissions()
const { isAppMobile } = useAppMobileLayout()

const canViewPositions = computed(() =>
  hasAnyPermission('employee.positions.view', 'employee.positions.checkin'),
)

const SITE_COLUMNS = [
  { key: 'code', label: 'Code', defaultVisible: true },
  { key: 'title', label: 'Titre', defaultVisible: true },
  { key: 'client', label: 'Client', defaultVisible: true, sortable: false },
  { key: 'location', label: 'Position', defaultVisible: true, sortable: false },
]

const {
  ROW_OPTIONS,
  columns: tableColumns,
  visibleColKeys,
  rows: tableRows,
  showIndex,
  sortField,
  sortOrder,
  sortOptions,
  isColVisible,
  toggleCol,
} = useTableSettings('table_sites', SITE_COLUMNS, {
  defaultSortField: 'title',
})

const tableFirst = ref(0)

const items = ref([])
const latestEmployeePositions = ref([])
const positionHistory = ref([])
const clientOptions = ref([])
const clientMap = ref({})
const searchTerm = ref('')
const loading = ref(true)
const error = ref(null)
const reloading = ref(false)
const dialog = ref(false)
const editingId = ref(null)
const actionItem = ref(null)
const actionMenu = ref()
const menuModel = ref([])
const rowContextMenu = ref()
const viewMode = ref('list')

const viewModeOptions = computed(() => {
  const options = [
    { label: 'Liste', value: 'list', icon: 'pi pi-list' },
    { label: 'Carte', value: 'map', icon: 'pi pi-map' },
  ]
  if (canViewPositions.value) {
    options.push({ label: 'Positions', value: 'positions', icon: 'pi pi-map-marker' })
  }
  return options
})

const mobileViewTabs = computed(() => {
  const tabs = [
    { value: 'list', label: 'Liste', shortLabel: 'Liste' },
    { value: 'map', label: 'Carte', shortLabel: 'Carte' },
  ]
  if (canViewPositions.value) {
    tabs.push({ value: 'positions', label: 'Positions', shortLabel: 'Pos.' })
  }
  return tabs
})

const canCreate = computed(() => hasPermission('site.sites.create'))

function emptyForm() {
  return { code: '', title: '', description: '', clientId: null, latitude: null, longitude: null }
}

const form = ref(emptyForm())

function validateLocationPair() {
  const lat = form.value.latitude
  const lng = form.value.longitude
  const hasLat = lat !== null && lat !== undefined && lat !== ''
  const hasLng = lng !== null && lng !== undefined && lng !== ''
  if (!hasLat && !hasLng) return {}
  if (hasLat !== hasLng) {
    return { location: 'Latitude et longitude doivent être renseignées ensemble.' }
  }
  const latN = Number(lat)
  const lngN = Number(lng)
  if (!Number.isFinite(latN) || latN < -90 || latN > 90) {
    return { latitude: 'Latitude invalide (−90 à 90).' }
  }
  if (!Number.isFinite(lngN) || lngN < -180 || lngN > 180) {
    return { longitude: 'Longitude invalide (−180 à 180).' }
  }
  return {}
}

const { errors: fieldErrors, validate: validateForm, resetErrors } = useFormFieldErrors(() => {
  const errs = {}
  if (!editingId.value && !hasRequiredText(form.value.code)) errs.code = requiredMessage('Code')
  if (!hasRequiredText(form.value.title)) errs.title = requiredMessage('Titre')
  Object.assign(errs, validateLocationPair())
  return errs
})

async function loadClients() {
  const clients = await listClients()
  clientOptions.value = clients.map((c) => ({ label: `${c.code} — ${c.title}`, value: c.id }))
  clientMap.value = Object.fromEntries(clients.map((c) => [c.id, c.title]))
}

const filterPeriod = ref(null)

async function fetchItems() {
  items.value = await listSites(periodToApiParams(filterPeriod.value))
}

async function fetchPositions() {
  if (!canViewPositions.value) {
    latestEmployeePositions.value = []
    positionHistory.value = []
    return
  }
  try {
    const [latest, history] = await Promise.all([
      listEmployeePositions({ latestOnly: true }),
      listEmployeePositions({ limit: 200 }),
    ])
    latestEmployeePositions.value = Array.isArray(latest) ? latest : []
    positionHistory.value = Array.isArray(history) ? history : []
  } catch {
    latestEmployeePositions.value = []
    positionHistory.value = []
  }
}

async function load() {
  loading.value = true
  error.value = null
  try {
    await Promise.all([fetchItems(), loadClients(), fetchPositions()])
  } catch (e) {
    error.value = e.response?.data?.error || 'Impossible de charger les sites.'
  } finally {
    loading.value = false
  }
}

async function reload() {
  reloading.value = true
  try {
    await Promise.all([fetchItems(), fetchPositions()])
  } finally {
    reloading.value = false
  }
}

onMounted(load)

watch(filterPeriod, () => {
  reload()
})

watch(canViewPositions, (ok) => {
  if (!ok && viewMode.value === 'positions') {
    viewMode.value = 'list'
  }
})

const filteredItems = computed(() => {
  const q = searchTerm.value.trim().toLowerCase()
  let list = items.value
  if (q) {
    list = list.filter((item) =>
      [item.code, item.title, item.description, clientMap.value[item.clientId]].filter(Boolean).join(' ').toLowerCase().includes(q),
    )
  }
  return sortByField(list, sortField.value, sortOrder.value)
})

const filteredPositions = computed(() => {
  const q = searchTerm.value.trim().toLowerCase()
  let list = positionHistory.value
  if (q) {
    list = list.filter((item) =>
      [item.employeeName, item.latitude, item.longitude, item.recordedAt]
        .filter((v) => v != null)
        .join(' ')
        .toLowerCase()
        .includes(q),
    )
  }
  return list
})

const countLabel = computed(() => {
  if (viewMode.value === 'positions') return `${filteredPositions.value.length}`
  return `${filteredItems.value.length}`
})

const isEmptyState = computed(() => {
  if (loading.value || error.value) return false
  if (viewMode.value === 'positions') return filteredPositions.value.length === 0
  if (viewMode.value === 'list') return filteredItems.value.length === 0
  return false
})

function positionCoordsLabel(item) {
  if (!Number.isFinite(item.latitude) || !Number.isFinite(item.longitude)) return '—'
  return `${Number(item.latitude).toFixed(5)}, ${Number(item.longitude).toFixed(5)}`
}

const dialogTitle = computed(() => (editingId.value ? 'Modifier site' : 'Nouveau site'))

function openCreate() {
  editingId.value = null
  form.value = emptyForm()
  resetErrors()
  dialog.value = true
}

function openEdit(item) {
  editingId.value = item.id
  form.value = {
    code: item.code,
    title: item.title ?? '',
    description: item.description ?? '',
    clientId: item.clientId,
    latitude: item.latitude ?? null,
    longitude: item.longitude ?? null,
  }
  resetErrors()
  dialog.value = true
}

function locationLabel(item) {
  if (!Number.isFinite(item.latitude) || !Number.isFinite(item.longitude)) return '—'
  return `${Number(item.latitude).toFixed(5)}, ${Number(item.longitude).toFixed(5)}`
}

function buildMenuItems(item) {
  const menu = []
  if (hasPermission('site.sites.update')) menu.push({ label: 'Modifier', icon: 'pi pi-pencil', command: () => openEdit(item) })
  if (hasPermission('site.sites.delete')) menu.push({ label: 'Supprimer', icon: 'pi pi-trash', command: () => askDelete(item) })
  return menu
}

function toggleMenu(event, item) {
  actionItem.value = item
  menuModel.value = buildMenuItems(item)
  actionMenu.value?.toggle(event)
}

function onRowContextMenu(event) {
  rowContextMenu.value?.onContextMenu(event.originalEvent, event.data)
}

function askDelete(item) {
  confirm.require({
    header: 'Supprimer le site',
    message: `Supprimer « ${item.title} » ?`,
    icon: 'pi pi-exclamation-triangle',
    rejectProps: { label: 'Annuler', severity: 'secondary', outlined: true },
    acceptProps: { label: 'Supprimer', severity: 'danger' },
    accept: () => runDelete(item),
  })
}

const { pending: deleting, run: runDelete } = useAsyncAction(async (item) => {
  try {
    await deleteSite(item.id)
    toast.add({ severity: 'success', summary: 'Site', detail: 'Supprimé.' })
    await fetchItems()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Site', detail: e.response?.data?.error || 'Erreur.' })
  }
})

const { pending: creatingFromMap, run: createFromMap } = useAsyncAction(async (payload) => {
  const { done, ...data } = payload || {}
  try {
    await createSite({
      code: data.code,
      title: data.title,
      description: data.description ?? null,
      clientId: data.clientId ?? null,
      latitude: data.latitude ?? null,
      longitude: data.longitude ?? null,
    })
    await fetchItems()
    toast.add({ severity: 'success', summary: 'Site', detail: 'Créé.' })
    done?.(true)
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Site', detail: e.response?.data?.error || 'Erreur.' })
    done?.(false)
  }
})

const { run: assignLocationFromMap } = useAsyncAction(async ({ siteId, lat, lng }) => {
  const site = items.value.find((s) => s.id === siteId)
  if (!site) {
    toast.add({ severity: 'error', summary: 'Site', detail: 'Site introuvable.' })
    return
  }
  try {
    await updateSite(siteId, {
      title: site.title,
      description: site.description ?? null,
      clientId: site.clientId ?? null,
      latitude: lat,
      longitude: lng,
    })
    await fetchItems()
    toast.add({
      severity: 'success',
      summary: 'Site',
      detail: `Position affectée à « ${site.title} ».`,
    })
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Site', detail: e.response?.data?.error || 'Erreur.' })
  }
})

const { pending: saving, run: saveItem } = useAsyncAction(async () => {
  if (!validateForm()) return
  const payload = {
    title: form.value.title.trim(),
    description: form.value.description || null,
    clientId: form.value.clientId || null,
    latitude: form.value.latitude ?? null,
    longitude: form.value.longitude ?? null,
  }
  if (!editingId.value) {
    payload.code = form.value.code.trim()
  }
  try {
    if (editingId.value) await updateSite(editingId.value, payload)
    else await createSite(payload)
    dialog.value = false
    await fetchItems()
    toast.add({ severity: 'success', summary: 'Site', detail: 'Enregistré.' })
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Site', detail: e.response?.data?.error || 'Erreur.' })
  }
})
</script>

<template>
  <section class="dashboard-page">
    <Card class="dashboard-panel">
      <template #title>
        <AppTablePanelHeader
          title="Sites"
          :count-label="countLabel"
          create-label="Nouveau site"
          :show-create="canCreate"
          :hide-create-on-mobile="isAppMobile"
          :sticky="isAppMobile"
          :reloading="reloading"
          show-search
          v-model:search-term="searchTerm"
          search-placeholder="Rechercher…"
          @create="openCreate"
          @reload="reload"
        >
          <template #actions>
            <SelectButton
              v-if="!isAppMobile"
              v-model="viewMode"
              :options="viewModeOptions"
              option-label="label"
              option-value="value"
              :allow-empty="false"
            />
            <AppTableSettingsPopover
              v-model:visible-col-keys="visibleColKeys"
              v-model:rows="tableRows"
              v-model:show-index="showIndex"
              v-model:sort-field="sortField"
              v-model:sort-order="sortOrder"
              :columns="tableColumns"
              :row-options="ROW_OPTIONS"
              :sort-options="sortOptions"
              @toggle-col="toggleCol"
            >
              <template #filters>
                <p class="app-table-settings__title">Filtres</p>
                <AppPeriodFilter v-model="filterPeriod" />
              </template>
            </AppTableSettingsPopover>
          </template>
        </AppTablePanelHeader>
      </template>
      <template #content>
        <AppMobileSegmentTabs
          v-if="isAppMobile"
          v-model="viewMode"
          :items="mobileViewTabs"
        />

        <AppTableState :loading="loading" :error="error" :is-empty="isEmptyState" @retry="load">
          <SiteMapPanel
            v-if="viewMode === 'map' && !loading && !error"
            :sites="filteredItems"
            :employee-positions="latestEmployeePositions"
            :client-map="clientMap"
            :client-options="clientOptions"
            :create-saving="creatingFromMap"
            @edit="openEdit"
            @create="createFromMap"
            @assign-location="assignLocationFromMap"
          />

          <template v-else-if="viewMode === 'positions'">
            <AppEntityDataView
              v-if="isAppMobile"
              :items="filteredPositions"
              :rows="tableRows"
              :show-index="showIndex"
              :title-of="(item) => item.employeeName || 'Employé'"
              :code-of="(item) => formatDateTimeFr(item.recordedAt) || '—'"
              :subtitle-of="(item) => positionCoordsLabel(item)"
              :actions-of="() => []"
            />
            <DataTable
              v-else
              :value="filteredPositions"
              paginator
              :rows="tableRows"
              striped-rows
              sort-field="recordedAt"
              :sort-order="-1"
              v-model:first="tableFirst">
              <Column v-if="showIndex" header="#" style="width: 3.5rem">
                <template #body="{ index }">{{ tableFirst + index + 1 }}</template>
              </Column>
              <Column field="employeeName" header="Employé" sortable>
                <template #body="{ data }">{{ data.employeeName || '—' }}</template>
              </Column>
              <Column field="recordedAt" header="Horodatage" sortable>
                <template #body="{ data }"><AppDateTimeCell :value="data.recordedAt" /></template>
              </Column>
              <Column header="Coordonnées">
                <template #body="{ data }">{{ positionCoordsLabel(data) }}</template>
              </Column>
              <Column field="accuracy" header="Précision">
                <template #body="{ data }">
                  {{ Number.isFinite(data.accuracy) ? `${Math.round(data.accuracy)} m` : '—' }}
                </template>
              </Column>
            </DataTable>
          </template>

          <template v-else-if="viewMode === 'list'">
            <AppEntityDataView
              v-if="isAppMobile"
              :items="filteredItems"
              :rows="tableRows"
              :show-index="showIndex"
              :title-of="(item) => item.title"
              :code-of="(item) => item.code"
              :subtitle-of="(item) => clientMap[item.clientId] || item.description || null"
              :actions-of="buildMenuItems"
              :row-bindings-of="(item) => rowContextMenu?.rowBindings(item) ?? {}"
              @select="openEdit"
            />
            <DataTable
              v-else
              :value="filteredItems"
              paginator
              :rows="tableRows"
              striped-rows
              :sort-field="sortField || undefined"
              :sort-order="sortOrder"
              @row-contextmenu="onRowContextMenu"
              v-model:first="tableFirst">
              <Column v-if="showIndex" header="#" style="width: 3.5rem">
                <template #body="{ index }">{{ tableFirst + index + 1 }}</template>
              </Column>
              <Column v-if="isColVisible('code')" field="code" header="Code" sortable />
              <Column v-if="isColVisible('title')" field="title" header="Titre" sortable />
              <Column v-if="isColVisible('client')" header="Client">
                <template #body="{ data }">{{ clientMap[data.clientId] || '—' }}</template>
              </Column>
              <Column v-if="isColVisible('location')" header="Position">
                <template #body="{ data }">{{ locationLabel(data) }}</template>
              </Column>
              <Column header="Actions" style="width: 5rem">
                <template #body="{ data }">
                  <Button v-if="buildMenuItems(data).length" icon="pi pi-ellipsis-v" text rounded @click="toggleMenu($event, data)" />
                </template>
              </Column>
            </DataTable>
            <Menu v-if="!isAppMobile" ref="actionMenu" :model="menuModel" popup />
            <AppRowContextMenu ref="rowContextMenu" :actions-of="buildMenuItems" />
          </template>
        </AppTableState>
      </template>
    </Card>

    <AppMobileFab
      v-if="isAppMobile && canCreate"
      aria-label="Nouveau site"
      @click="openCreate"
    />

    <Dialog v-model:visible="dialog" :header="dialogTitle" modal style="width: min(720px, 95vw)">
      <SiteFormFields
        v-if="dialog"
        v-model="form"
        :errors="fieldErrors"
        :client-options="clientOptions"
        :show-code="Boolean(editingId)"
        :require-code="!editingId"
      />
      <template #footer>
        <Button label="Annuler" severity="secondary" text :disabled="saving" @click="dialog = false" />
        <Button :label="editingId ? 'Enregistrer' : 'Créer'" icon="pi pi-check" :loading="saving" @click="saveItem" />
      </template>
    </Dialog>
  </section>
</template>
