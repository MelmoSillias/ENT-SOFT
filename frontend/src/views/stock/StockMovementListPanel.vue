<script setup>
import { computed, onMounted, ref } from 'vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Menu from 'primevue/menu'
import Dialog from 'primevue/dialog'
import AppTablePanelHeader from '@/domains/shared/components/AppTablePanelHeader.vue'
import AppTableState from '@/domains/shared/components/AppTableState.vue'
import AppTableSettingsPopover from '@/domains/shared/components/AppTableSettingsPopover.vue'
import AppRowContextMenu from '@/domains/shared/components/AppRowContextMenu.vue'
import AppEntityDataView from '@/domains/shared/components/AppEntityDataView.vue'
import AppMobileFab from '@/domains/shared/components/AppMobileFab.vue'
import { useAppMobileLayout } from '@/domains/layout/composables/useAppMobileLayout'
import { useTableSettings } from '@/domains/shared/composables/useTableSettings'
import { sortByField } from '@/domains/shared/utils/sortByField'
import StockMovementFormFields from '@/domains/stock/components/StockMovementFormFields.vue'
import { listStockMovements, createStockMovement, updateStockMovement, deleteStockMovement } from '@/domains/stock/services/stockMovementService'
import { listEquipment } from '@/domains/stock/services/equipmentService'
import { listClients } from '@/domains/client/services/clientService'
import { listProjects } from '@/domains/project/services/projectService'
import { listSites } from '@/domains/site/services/siteService'
import { formatDateFr, stockDirectionLabel, stockDirectionSeverity, equipmentUnitLabel } from '@/domains/shared/utils/entLabels'
import { toApiDate, parseApiDate } from '@/domains/shared/utils/dateUtils'
import { useFormFieldErrors } from '@/domains/shared/composables/useFormFieldErrors'
import { useConfirm } from 'primevue/useconfirm'
import { useAsyncAction } from '@/domains/shared/composables/useAsyncAction'
import { usePermissions } from '@/domains/auth/composables/usePermissions'
import { useAppToast } from '@/domains/shared/composables/useAppToast'

const emit = defineEmits(['changed'])

const toast = useAppToast()
const confirm = useConfirm()
const { hasPermission } = usePermissions()
const { isAppMobile } = useAppMobileLayout()

const items = ref([])
const equipmentOptions = ref([])
const equipmentMap = ref({})
const clientOptions = ref([])
const projectOptions = ref([])
const siteOptions = ref([])
const searchTerm = ref('')
const loading = ref(true)
const error = ref(null)
const reloading = ref(false)
const dialog = ref(false)
const editingId = ref(null)
const actionMenu = ref()
const menuModel = ref([])
const rowContextMenu = ref()

const MOVEMENT_COLUMNS = [
  { key: 'date', label: 'Date', defaultVisible: true },
  { key: 'direction', label: 'Type', defaultVisible: true },
  { key: 'quantity', label: 'Quantité', defaultVisible: true },
  { key: 'equipment', label: 'Équipements', defaultVisible: true },
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
} = useTableSettings('table_stock_movements', MOVEMENT_COLUMNS, {
  defaultSortField: 'date',
  defaultSortOrder: -1,
})

const canCreate = computed(() => hasPermission('stock.movements.create'))

function emptyForm() {
  return {
    date: new Date(),
    direction: 'in',
    clientId: null,
    projectId: null,
    siteId: null,
    lines: [{ equipmentId: null, quantity: 1 }],
  }
}

const form = ref(emptyForm())

const { errors: fieldErrors, validate: validateForm, resetErrors } = useFormFieldErrors(() => {
  const errs = {}
  if (!form.value.date) errs.date = 'Date requise.'
  const lines = (form.value.lines ?? []).filter((l) => l.equipmentId && Number(l.quantity) > 0)
  if (!lines.length) errs.lines = 'Ajoutez au moins une ligne d\'équipement.'
  return errs
})

async function loadRefs() {
  const [equipments, clients, projects, sites] = await Promise.all([
    listEquipment(),
    listClients(),
    listProjects(),
    listSites(),
  ])
  equipmentOptions.value = equipments.map((e) => ({
    label: `${e.code} — ${e.title}`,
    value: e.id,
    unit: e.unit,
  }))
  equipmentMap.value = Object.fromEntries(equipments.map((e) => [e.id, e]))
  clientOptions.value = clients.map((c) => ({ label: `${c.code} — ${c.title}`, value: c.id }))
  projectOptions.value = projects.map((p) => ({ label: `${p.code} — ${p.title}`, value: p.id }))
  siteOptions.value = sites.map((s) => ({ label: `${s.code} — ${s.title}`, value: s.id }))
}

async function fetchItems() {
  items.value = await listStockMovements()
}

async function load() {
  loading.value = true
  error.value = null
  try {
    await Promise.all([fetchItems(), loadRefs()])
  } catch (e) {
    error.value = e.response?.data?.error || 'Impossible de charger les mouvements.'
  } finally {
    loading.value = false
  }
}

async function reload() {
  reloading.value = true
  try {
    await fetchItems()
  } finally {
    reloading.value = false
  }
}

onMounted(load)

function lineLabel(item) {
  return (item.lines ?? [])
    .map((l) => {
      const eq = equipmentMap.value[l.equipmentId]
      if (!eq) return '—'
      return `${eq.code} — ${eq.title}`
    })
    .join(', ')
}

function quantityLabel(item) {
  const units = [...new Set(
    (item.lines ?? [])
      .map((l) => equipmentMap.value[l.equipmentId]?.unit)
      .filter(Boolean)
      .map((u) => equipmentUnitLabel(u)),
  )]
  const unitSuffix = units.length === 1 ? ` ${units[0]}` : ''
  return `${item.quantity}${unitSuffix}`
}

const filteredItems = computed(() => {
  const q = searchTerm.value.trim().toLowerCase()
  let list = items.value
  if (q) {
    list = list.filter((item) =>
      [stockDirectionLabel(item.direction), lineLabel(item)].join(' ').toLowerCase().includes(q),
    )
  }
  const enriched = list.map((item) => ({
    ...item,
    _equipment: lineLabel(item),
  }))
  const field = sortField.value === 'equipment' ? '_equipment' : sortField.value
  return sortByField(enriched, field, sortOrder.value)
})

function openCreate(opts = {}) {
  editingId.value = null
  form.value = {
    ...emptyForm(),
    direction: opts.direction ?? 'in',
    clientId: opts.clientId ?? null,
    lines: [{ equipmentId: opts.equipmentId ?? null, quantity: opts.quantity ?? 1 }],
  }
  resetErrors()
  dialog.value = true
}

function openEdit(item) {
  editingId.value = item.id
  form.value = {
    date: parseApiDate(item.date),
    direction: item.direction ?? 'in',
    clientId: item.clientId,
    projectId: item.projectId,
    siteId: item.siteId,
    lines: (item.lines?.length ? item.lines : [{ equipmentId: null, quantity: 1 }]).map((l) => ({
      equipmentId: l.equipmentId,
      quantity: l.quantity,
    })),
  }
  resetErrors()
  dialog.value = true
}

defineExpose({ openCreate, reload: load })

function buildMenuItems(item) {
  const menu = []
  if (hasPermission('stock.movements.update')) menu.push({ label: 'Modifier', icon: 'pi pi-pencil', command: () => openEdit(item) })
  if (hasPermission('stock.movements.delete')) menu.push({ label: 'Supprimer', icon: 'pi pi-trash', severity: 'danger', command: () => askDelete(item) })
  return menu
}

function toggleMenu(event, item) {
  menuModel.value = buildMenuItems(item)
  actionMenu.value?.toggle(event)
}

function onRowContextMenu(event) {
  rowContextMenu.value?.onContextMenu(event.originalEvent, event.data)
}

function askDelete(item) {
  confirm.require({
    header: 'Supprimer le mouvement',
    message: 'Supprimer ce mouvement de stock ?',
    icon: 'pi pi-exclamation-triangle',
    rejectProps: { label: 'Annuler', severity: 'secondary', outlined: true },
    acceptProps: { label: 'Supprimer', severity: 'danger' },
    accept: () => runDelete(item),
  })
}

const { run: runDelete } = useAsyncAction(async (item) => {
  try {
    await deleteStockMovement(item.id)
    toast.add({ severity: 'success', summary: 'Mouvement', detail: 'Supprimé.' })
    await fetchItems()
    emit('changed')
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Mouvement', detail: e.response?.data?.error || 'Erreur.' })
  }
})

const { pending: saving, run: saveItem } = useAsyncAction(async () => {
  if (!validateForm()) return
  const lines = (form.value.lines ?? []).filter((l) => l.equipmentId && Number(l.quantity) > 0)
  const quantity = lines.reduce((sum, l) => sum + Number(l.quantity || 0), 0)
  const payload = {
    date: toApiDate(form.value.date),
    direction: form.value.direction,
    quantity,
    clientId: form.value.clientId || null,
    projectId: form.value.projectId || null,
    siteId: form.value.siteId || null,
    lines,
  }
  try {
    if (editingId.value) await updateStockMovement(editingId.value, payload)
    else await createStockMovement(payload)
    dialog.value = false
    await fetchItems()
    emit('changed')
    toast.add({ severity: 'success', summary: 'Mouvement', detail: 'Enregistré.' })
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Mouvement', detail: e.response?.data?.error || 'Erreur.' })
  }
})
</script>

<template>
  <div>
    <AppTablePanelHeader
      title="Mouvements"
      :count-label="`${filteredItems.length}`"
      create-label="Nouveau mouvement"
      :show-create="canCreate"
      :hide-create-on-mobile="isAppMobile"
      :sticky="isAppMobile"
      :reloading="reloading"
      show-search
      v-model:search-term="searchTerm"
      search-placeholder="Rechercher…"
      @create="openCreate()"
      @reload="reload"
    >
      <template #actions>
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
        />
      </template>
    </AppTablePanelHeader>
    <AppTableState :loading="loading" :error="error" :is-empty="!loading && !error && filteredItems.length === 0" @retry="load">
      <AppEntityDataView
        v-if="isAppMobile"
        :items="filteredItems"
        :title-of="(item) => `${stockDirectionLabel(item.direction)} · ${quantityLabel(item)}`"
        :subtitle-of="(item) => lineLabel(item) || null"
        :meta-of="(item) => formatDateFr(item.date)"
        :status-of="(item) => ({ value: stockDirectionLabel(item.direction), severity: stockDirectionSeverity(item.direction) })"
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
        :sort-field="sortField === 'equipment' ? undefined : (sortField || undefined)"
        :sort-order="sortOrder"
        @row-contextmenu="onRowContextMenu"
      >
        <Column v-if="showIndex" header="#" style="width: 3.5rem">
          <template #body="{ index }">{{ index + 1 }}</template>
        </Column>
        <Column v-if="isColVisible('date')" field="date" header="Date" sortable>
          <template #body="{ data }">{{ formatDateFr(data.date) }}</template>
        </Column>
        <Column v-if="isColVisible('direction')" field="direction" header="Type" sortable>
          <template #body="{ data }">
            <Tag :value="stockDirectionLabel(data.direction)" :severity="stockDirectionSeverity(data.direction)" />
          </template>
        </Column>
        <Column v-if="isColVisible('quantity')" field="quantity" header="Quantité" sortable>
          <template #body="{ data }">{{ quantityLabel(data) }}</template>
        </Column>
        <Column v-if="isColVisible('equipment')" header="Équipements">
          <template #body="{ data }">{{ lineLabel(data) || '—' }}</template>
        </Column>
        <Column header="Actions" style="width: 5rem">
          <template #body="{ data }">
            <Button v-if="buildMenuItems(data).length" icon="pi pi-ellipsis-v" text rounded @click="toggleMenu($event, data)" />
          </template>
        </Column>
      </DataTable>
      <Menu v-if="!isAppMobile" ref="actionMenu" :model="menuModel" popup />
      <AppRowContextMenu ref="rowContextMenu" :actions-of="buildMenuItems" />
    </AppTableState>

    <AppMobileFab
      v-if="isAppMobile && canCreate"
      aria-label="Nouveau mouvement"
      @click="openCreate()"
    />

    <Dialog v-model:visible="dialog" :header="editingId ? 'Modifier mouvement' : 'Nouveau mouvement'" modal style="width: min(720px, 96vw)">
      <StockMovementFormFields
        v-model="form"
        :errors="fieldErrors"
        :equipment-options="equipmentOptions"
        :client-options="clientOptions"
        :project-options="projectOptions"
        :site-options="siteOptions"
      />
      <template #footer>
        <Button label="Annuler" severity="secondary" text :disabled="saving" @click="dialog = false" />
        <Button :label="editingId ? 'Enregistrer' : 'Créer'" icon="pi pi-check" :loading="saving" @click="saveItem" />
      </template>
    </Dialog>
  </div>
</template>
