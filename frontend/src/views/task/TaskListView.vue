<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Tag from 'primevue/tag'
import Menu from 'primevue/menu'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import timeGridPlugin from '@fullcalendar/timegrid'
import listPlugin from '@fullcalendar/list'
import interactionPlugin from '@fullcalendar/interaction'
import frLocale from '@fullcalendar/core/locales/fr'
import AppTablePanelHeader from '@/domains/shared/components/AppTablePanelHeader.vue'
import AppTableState from '@/domains/shared/components/AppTableState.vue'
import AppTableSettingsPopover from '@/domains/shared/components/AppTableSettingsPopover.vue'
import AppRowContextMenu from '@/domains/shared/components/AppRowContextMenu.vue'
import AppEntityDataView from '@/domains/shared/components/AppEntityDataView.vue'
import AppMobileFab from '@/domains/shared/components/AppMobileFab.vue'
import AppFilterSelect from '@/domains/shared/components/AppFilterSelect.vue'
import { useAppMobileLayout } from '@/domains/layout/composables/useAppMobileLayout'
import { useTableSettings } from '@/domains/shared/composables/useTableSettings'
import { sortByField } from '@/domains/shared/utils/sortByField'
import TaskFormFields from '@/domains/task/components/TaskFormFields.vue'
import TaskPreviewPopover from '@/domains/task/components/TaskPreviewPopover.vue'
import TaskResourceTimeline from '@/domains/task/components/timeline/TaskResourceTimeline.vue'
import { listTasks, createTask, updateTask, deleteTask } from '@/domains/task/services/taskService'
import { listSites } from '@/domains/site/services/siteService'
import { listEmployees } from '@/domains/employee/services/employeeService'
import { taskStatusLabel, taskStatusSeverity, formatDateFr, TASK_STATUS_OPTIONS } from '@/domains/shared/utils/entLabels'
import { toApiDate, parseApiDate, toApiDateTime, parseApiDateTime, periodToApiParams } from '@/domains/shared/utils/dateUtils'
import AppPeriodFilter from '@/domains/shared/components/AppPeriodFilter.vue'
import AppPersonNameCell from '@/domains/shared/components/AppPersonNameCell.vue'
import { hasRequiredText, requiredMessage } from '@/domains/shared/utils/formValidation'
import { useFormFieldErrors } from '@/domains/shared/composables/useFormFieldErrors'
import { useConfirm } from 'primevue/useconfirm'
import { useAsyncAction } from '@/domains/shared/composables/useAsyncAction'
import { usePermissions } from '@/domains/auth/composables/usePermissions'
import { useAppToast } from '@/domains/shared/composables/useAppToast'

const toast = useAppToast()
const confirm = useConfirm()
const { hasPermission } = usePermissions()
const { isAppMobile } = useAppMobileLayout()

const items = ref([])
const siteOptions = ref([])
const employeeOptions = ref([])
const siteMap = ref({})
const employeeMap = ref({})
const employeePhotoMap = ref({})
const searchTerm = ref('')
const filterSiteId = ref(null)
const filterEmployeeId = ref(null)
const filterStatus = ref(null)
const filterPeriod = ref(null)
const viewMode = ref('table')
const loading = ref(true)
const error = ref(null)
const reloading = ref(false)
const dialog = ref(false)
const editingId = ref(null)
const actionItem = ref(null)
const actionMenu = ref()
const menuModel = ref([])
const rowContextMenu = ref()
const taskPreview = ref()

const canUpdate = computed(() => hasPermission('task.tasks.update'))
const canDelete = computed(() => hasPermission('task.tasks.delete'))

const viewOptions = [
  { label: 'Tableau', value: 'table' },
  { label: 'Calendrier', value: 'calendar' },
  { label: 'Planning', value: 'timeline' },
]

const statusFilterOptions = [{ label: 'Tous', value: null }, ...TASK_STATUS_OPTIONS]

const TASK_COLUMNS = [
  { key: 'title', label: 'Titre', defaultVisible: true },
  { key: 'site', label: 'Site', defaultVisible: true },
  { key: 'employee', label: 'Employé', defaultVisible: true },
  { key: 'dateDue', label: 'Échéance', defaultVisible: true },
  { key: 'status', label: 'Statut', defaultVisible: true },
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
} = useTableSettings('table_tasks', TASK_COLUMNS, {
  defaultSortField: 'dateDue',
})

const canCreate = computed(() => hasPermission('task.tasks.create'))

function emptyForm() {
  return { title: '', description: '', siteId: null, employeeId: null, status: 'pending', dateDue: null, startAt: null, endAt: null }
}

const form = ref(emptyForm())

const { errors: fieldErrors, validate: validateForm, resetErrors } = useFormFieldErrors(() => {
  const errs = {}
  if (!hasRequiredText(form.value.title)) errs.title = requiredMessage('Titre')
  if (!form.value.siteId) errs.siteId = requiredMessage('Site')
  if (form.value.startAt && form.value.endAt && form.value.endAt <= form.value.startAt) {
    errs.endAt = 'La fin doit être après le début.'
  }
  if (form.value.endAt && !form.value.startAt) {
    errs.startAt = 'Renseignez le début si une fin est définie.'
  }
  return errs
})

async function loadRefs() {
  const [sites, employees] = await Promise.all([listSites(), listEmployees()])
  siteOptions.value = sites.map((s) => ({ label: `${s.code} — ${s.title}`, value: s.id }))
  employeeOptions.value = employees.map((e) => ({ label: e.name, value: e.id, photoUrl: e.photoUrl || null }))
  siteMap.value = Object.fromEntries(sites.map((s) => [s.id, s.title]))
  employeeMap.value = Object.fromEntries(employees.map((e) => [e.id, e.name]))
  employeePhotoMap.value = Object.fromEntries(employees.map((e) => [e.id, e.photoUrl || null]))
}

async function fetchItems() {
  const params = { ...periodToApiParams(filterPeriod.value) }
  if (filterSiteId.value) params.siteId = filterSiteId.value
  if (filterEmployeeId.value) params.employeeId = filterEmployeeId.value
  if (filterStatus.value) params.status = filterStatus.value
  items.value = await listTasks(params)
}

async function load() {
  loading.value = true
  error.value = null
  try {
    await loadRefs()
    await fetchItems()
  } catch (e) {
    error.value = e.response?.data?.error || 'Impossible de charger les tâches.'
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
watch([filterSiteId, filterEmployeeId, filterStatus, filterPeriod], () => { if (!loading.value) reload() })

const filteredItems = computed(() => {
  const q = searchTerm.value.trim().toLowerCase()
  let list = items.value
  if (q) {
    list = list.filter((item) =>
      [item.title, item.description, siteMap.value[item.siteId], employeeMap.value[item.employeeId]].filter(Boolean).join(' ').toLowerCase().includes(q),
    )
  }
  const enriched = list.map((item) => ({
    ...item,
    _site: siteMap.value[item.siteId] || '',
    _employee: employeeMap.value[item.employeeId] || '',
  }))
  const fieldMap = { site: '_site', employee: '_employee' }
  const field = fieldMap[sortField.value] || sortField.value
  return sortByField(enriched, field, sortOrder.value)
})

const STATUS_COLORS = {
  pending: '#f59e0b',
  in_progress: '#3b82f6',
  done: '#22c55e',
  cancelled: '#94a3b8',
}

const calendarOptions = computed(() => ({
  plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
  initialView: 'dayGridMonth',
  locale: frLocale,
  height: 'auto',
  headerToolbar: {
    left: 'prev,next today',
    center: 'title',
    right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
  },
  buttonText: { month: 'Mois', week: 'Semaine', day: 'Jour', list: 'Liste' },
  dayMaxEventRows: 4,
  navLinks: true,
  weekNumbers: true,
  nowIndicator: true,
  events: filteredItems.value
    .filter((t) => t.dateDue)
    .map((t) => ({
      id: t.id,
      title: t.title,
      start: t.dateDue,
      allDay: true,
      backgroundColor: STATUS_COLORS[t.status] || '#64748b',
      borderColor: STATUS_COLORS[t.status] || '#64748b',
      extendedProps: { task: t },
    })),
  eventClick: (info) => {
    info.jsEvent.preventDefault()
    info.jsEvent.stopPropagation()
    taskPreview.value?.show(info.jsEvent, info.event.extendedProps.task, info.el)
  },
  dateClick: (info) => {
    if (!canCreate.value) return
    openCreate()
    form.value.dateDue = info.date
  },
}))

const timelineResources = computed(() =>
  employeeOptions.value.map((e) => ({ id: e.value, label: e.label, photoUrl: e.photoUrl || null })),
)

function onTimelineCreate({ resourceId, start }) {
  if (!canCreate.value) return
  openCreate()
  form.value.employeeId = resourceId
  form.value.startAt = start
  form.value.endAt = new Date(start.getTime() + 60 * 60 * 1000)
  form.value.dateDue = start
}

const countLabel = computed(() => `${filteredItems.value.length}`)
const dialogTitle = computed(() => (editingId.value ? 'Modifier tâche' : 'Nouvelle tâche'))

function openCreate() {
  editingId.value = null
  form.value = emptyForm()
  resetErrors()
  dialog.value = true
}

function openEdit(item) {
  editingId.value = item.id
  form.value = {
    title: item.title ?? '',
    description: item.description ?? '',
    siteId: item.siteId,
    employeeId: item.employeeId,
    status: item.status ?? 'pending',
    dateDue: parseApiDate(item.dateDue),
    startAt: parseApiDateTime(item.startAt),
    endAt: parseApiDateTime(item.endAt),
  }
  resetErrors()
  dialog.value = true
}

function buildMenuItems(item) {
  const menu = []
  if (canUpdate.value) menu.push({ label: 'Modifier', icon: 'pi pi-pencil', command: () => openEdit(item) })
  if (canDelete.value) menu.push({ label: 'Supprimer', icon: 'pi pi-trash', severity: 'danger', command: () => askDelete(item) })
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

function onPreviewTask({ event, task }) {
  taskPreview.value?.show(event, task)
}

function askDelete(item) {
  confirm.require({
    header: 'Supprimer la tâche',
    message: `Supprimer « ${item.title} » ?`,
    icon: 'pi pi-exclamation-triangle',
    rejectProps: { label: 'Annuler', severity: 'secondary', outlined: true },
    acceptProps: { label: 'Supprimer', severity: 'danger' },
    accept: () => runDelete(item),
  })
}

const { pending: deleting, run: runDelete } = useAsyncAction(async (item) => {
  try {
    await deleteTask(item.id)
    toast.add({ severity: 'success', summary: 'Tâche', detail: 'Supprimée.' })
    await fetchItems()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Tâche', detail: e.response?.data?.error || 'Erreur.' })
  }
})

const { pending: statusUpdating, run: runStatusChange } = useAsyncAction(async ({ task, status }) => {
  try {
    await updateTask(task.id, { status })
    taskPreview.value?.patchTask({ status })
    await fetchItems()
    toast.add({ severity: 'success', summary: 'Tâche', detail: 'Statut mis à jour.' })
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Tâche', detail: e.response?.data?.error || 'Erreur.' })
  }
})

const { pending: saving, run: saveItem } = useAsyncAction(async () => {
  if (!validateForm()) return
  const payload = {
    title: form.value.title.trim(),
    description: form.value.description || null,
    siteId: form.value.siteId,
    employeeId: form.value.employeeId || null,
    status: form.value.status,
    dateDue: toApiDate(form.value.dateDue),
    startAt: toApiDateTime(form.value.startAt),
    endAt: toApiDateTime(form.value.endAt),
  }
  try {
    if (editingId.value) await updateTask(editingId.value, payload)
    else await createTask(payload)
    dialog.value = false
    await fetchItems()
    toast.add({ severity: 'success', summary: 'Tâche', detail: 'Enregistrée.' })
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Tâche', detail: e.response?.data?.error || 'Erreur.' })
  }
})
</script>

<template>
  <section class="dashboard-page">
    <Card class="dashboard-panel">
      <template #title>
        <AppTablePanelHeader
          title="Planning & Tâches"
          :count-label="countLabel"
          create-label="Nouvelle tâche"
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
            <Select
              v-model="viewMode"
              :options="viewOptions"
              option-label="label"
              option-value="value"
              placeholder="Affichage"
              class="task-view-select"
              aria-label="Mode d'affichage"
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
                <AppFilterSelect
                  v-model="filterSiteId"
                  :options="[{ label: 'Tous les sites', value: null }, ...siteOptions]"
                  option-label="label"
                  option-value="value"
                  placeholder="Site"
                  show-clear
                  fluid
                  size="small"
                  class="app-table-settings__mb"
                />
                <AppFilterSelect
                  v-model="filterEmployeeId"
                  :options="[{ label: 'Tous les employés', value: null }, ...employeeOptions]"
                  option-label="label"
                  option-value="value"
                  placeholder="Employé"
                  show-clear
                  fluid
                  size="small"
                  class="app-table-settings__mb"
                />
                <AppFilterSelect
                  v-model="filterStatus"
                  :options="statusFilterOptions"
                  option-label="label"
                  option-value="value"
                  placeholder="Statut"
                  show-clear
                  fluid
                  size="small"
                />
              </template>
            </AppTableSettingsPopover>
          </template>
        </AppTablePanelHeader>
      </template>
      <template #content>
        <AppTableState :loading="loading" :error="error" :is-empty="!loading && !error && filteredItems.length === 0" @retry="load">
          <template v-if="viewMode === 'table'">
            <AppEntityDataView
              v-if="isAppMobile"
              :items="filteredItems"
              :rows="tableRows"
              :show-index="showIndex"
              :title-of="(item) => item.title"
              :subtitle-of="(item) => siteMap[item.siteId] || null"
              :meta-of="(item) => [employeeMap[item.employeeId], formatDateFr(item.dateDue)].filter(Boolean).join(' · ') || null"
              :status-of="(item) => ({ value: taskStatusLabel(item.status), severity: taskStatusSeverity(item.status) })"
              :avatar-of="(item) => item.employeeId && employeeMap[item.employeeId]
                ? {
                    name: employeeMap[item.employeeId],
                    photoUrl: employeePhotoMap[item.employeeId],
                    person: { name: employeeMap[item.employeeId], photoUrl: employeePhotoMap[item.employeeId] },
                    kind: 'employee',
                  }
                : null"
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
              :sort-field="sortField === 'site' || sortField === 'employee' ? undefined : (sortField || undefined)"
              :sort-order="sortOrder"
              @row-contextmenu="onRowContextMenu"
            >
              <Column v-if="showIndex" header="#" style="width: 3.5rem">
                <template #body="{ index }">{{ index + 1 }}</template>
              </Column>
              <Column v-if="isColVisible('title')" field="title" header="Titre" sortable />
              <Column v-if="isColVisible('site')" header="Site">
                <template #body="{ data }">{{ siteMap[data.siteId] || '—' }}</template>
              </Column>
              <Column v-if="isColVisible('employee')" header="Employé">
                <template #body="{ data }">
                  <AppPersonNameCell
                    v-if="data.employeeId && employeeMap[data.employeeId]"
                    :name="employeeMap[data.employeeId]"
                    :photo-url="employeePhotoMap[data.employeeId]"
                    :person="{ name: employeeMap[data.employeeId], photoUrl: employeePhotoMap[data.employeeId] }"
                    kind="employee"
                  />
                  <span v-else>—</span>
                </template>
              </Column>
              <Column v-if="isColVisible('dateDue')" field="dateDue" header="Échéance" sortable>
                <template #body="{ data }">{{ formatDateFr(data.dateDue) }}</template>
              </Column>
              <Column v-if="isColVisible('status')" field="status" header="Statut" sortable>
                <template #body="{ data }">
                  <Tag :value="taskStatusLabel(data.status)" :severity="taskStatusSeverity(data.status)" />
                </template>
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

          <div v-else-if="viewMode === 'calendar'" class="task-calendar">
            <FullCalendar :options="calendarOptions" />
          </div>

          <TaskResourceTimeline
            v-else
            :tasks="filteredItems"
            :resources="timelineResources"
            :status-colors="STATUS_COLORS"
            :can-create="canCreate"
            :mobile="isAppMobile"
            @preview-task="onPreviewTask"
            @create-at="onTimelineCreate"
          />
        </AppTableState>
      </template>
    </Card>

    <TaskPreviewPopover
      ref="taskPreview"
      :site-map="siteMap"
      :employee-map="employeeMap"
      :status-colors="STATUS_COLORS"
      :can-edit="canUpdate"
      :can-delete="canDelete"
      :status-updating="statusUpdating"
      @edit="openEdit"
      @delete="askDelete"
      @status-change="runStatusChange"
    />

    <AppMobileFab
      v-if="isAppMobile && canCreate"
      aria-label="Nouvelle tâche"
      @click="openCreate"
    />

    <Dialog v-model:visible="dialog" :header="dialogTitle" modal style="width: min(720px, 95vw)">
      <TaskFormFields v-model="form" :errors="fieldErrors" :site-options="siteOptions" :employee-options="employeeOptions" />
      <template #footer>
        <Button label="Annuler" severity="secondary" text :disabled="saving" @click="dialog = false" />
        <Button :label="editingId ? 'Enregistrer' : 'Créer'" icon="pi pi-check" :loading="saving" @click="saveItem" />
      </template>
    </Dialog>
  </section>
</template>

<style scoped>
.task-view-select {
  min-width: 8.5rem;
  flex-shrink: 0;
}
</style>
