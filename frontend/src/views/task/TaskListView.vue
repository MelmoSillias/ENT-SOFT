<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Tag from 'primevue/tag'
import Menu from 'primevue/menu'
import Dialog from 'primevue/dialog'
import SelectButton from 'primevue/selectbutton'
import DatePicker from 'primevue/datepicker'
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
import { listTasks, createTask, updateTask, deleteTask } from '@/domains/task/services/taskService'
import { listSites } from '@/domains/site/services/siteService'
import { listEmployees } from '@/domains/employee/services/employeeService'
import { taskStatusLabel, taskStatusSeverity, formatDateFr, TASK_STATUS_OPTIONS } from '@/domains/shared/utils/entLabels'
import { toApiDate, parseApiDate } from '@/domains/shared/utils/dateUtils'
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
const searchTerm = ref('')
const filterSiteId = ref(null)
const filterEmployeeId = ref(null)
const filterStatus = ref(null)
const viewMode = ref('table')
const calendarMonth = ref(new Date())
const loading = ref(true)
const error = ref(null)
const reloading = ref(false)
const dialog = ref(false)
const editingId = ref(null)
const actionItem = ref(null)
const actionMenu = ref()
const menuModel = ref([])
const rowContextMenu = ref()

const viewOptions = [
  { label: 'Tableau', value: 'table', icon: 'pi pi-list' },
  { label: 'Calendrier', value: 'calendar', icon: 'pi pi-calendar' },
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
  return { title: '', description: '', siteId: null, employeeId: null, status: 'pending', dateDue: null }
}

const form = ref(emptyForm())

const { errors: fieldErrors, validate: validateForm, resetErrors } = useFormFieldErrors(() => {
  const errs = {}
  if (!hasRequiredText(form.value.title)) errs.title = requiredMessage('Titre')
  if (!form.value.siteId) errs.siteId = requiredMessage('Site')
  return errs
})

async function loadRefs() {
  const [sites, employees] = await Promise.all([listSites(), listEmployees()])
  siteOptions.value = sites.map((s) => ({ label: `${s.code} — ${s.title}`, value: s.id }))
  employeeOptions.value = employees.map((e) => ({ label: e.name, value: e.id }))
  siteMap.value = Object.fromEntries(sites.map((s) => [s.id, s.title]))
  employeeMap.value = Object.fromEntries(employees.map((e) => [e.id, e.name]))
}

async function fetchItems() {
  const params = {}
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
watch([filterSiteId, filterEmployeeId, filterStatus], () => { if (!loading.value) reload() })

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

const calendarGroups = computed(() => {
  const month = calendarMonth.value.getMonth()
  const year = calendarMonth.value.getFullYear()
  const groups = {}
  for (const task of filteredItems.value) {
    if (!task.dateDue) continue
    const d = new Date(task.dateDue)
    if (d.getMonth() !== month || d.getFullYear() !== year) continue
    const key = task.dateDue
    if (!groups[key]) groups[key] = []
    groups[key].push(task)
  }
  return Object.entries(groups).sort(([a], [b]) => a.localeCompare(b))
})

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
  }
  resetErrors()
  dialog.value = true
}

function buildMenuItems(item) {
  const menu = []
  if (hasPermission('task.tasks.update')) menu.push({ label: 'Modifier', icon: 'pi pi-pencil', command: () => openEdit(item) })
  if (hasPermission('task.tasks.delete')) menu.push({ label: 'Supprimer', icon: 'pi pi-trash', severity: 'danger', command: () => askDelete(item) })
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

const { pending: saving, run: saveItem } = useAsyncAction(async () => {
  if (!validateForm()) return
  const payload = {
    title: form.value.title.trim(),
    description: form.value.description || null,
    siteId: form.value.siteId,
    employeeId: form.value.employeeId || null,
    status: form.value.status,
    dateDue: toApiDate(form.value.dateDue),
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
            <SelectButton v-model="viewMode" :options="viewOptions" option-label="label" option-value="value" :allow-empty="false" />
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
              :title-of="(item) => item.title"
              :subtitle-of="(item) => siteMap[item.siteId] || null"
              :meta-of="(item) => [employeeMap[item.employeeId], formatDateFr(item.dateDue)].filter(Boolean).join(' · ') || null"
              :status-of="(item) => ({ value: taskStatusLabel(item.status), severity: taskStatusSeverity(item.status) })"
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
                <template #body="{ data }">{{ employeeMap[data.employeeId] || '—' }}</template>
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

          <div v-else class="task-calendar">
            <div class="task-calendar__toolbar">
              <label>Mois</label>
              <DatePicker v-model="calendarMonth" view="month" date-format="MM yy" show-icon />
            </div>
            <div v-if="calendarGroups.length" class="task-calendar__groups">
              <Card v-for="[date, tasks] in calendarGroups" :key="date" class="task-calendar__day">
                <template #title>{{ formatDateFr(date) }}</template>
                <template #content>
                  <ul class="task-calendar__list">
                    <li v-for="task in tasks" :key="task.id">
                      <strong>{{ task.title }}</strong>
                      <Tag :value="taskStatusLabel(task.status)" :severity="taskStatusSeverity(task.status)" />
                      <span class="task-calendar__meta">{{ siteMap[task.siteId] }}</span>
                    </li>
                  </ul>
                </template>
              </Card>
            </div>
            <p v-else class="dashboard-page__state">Aucune tâche ce mois-ci.</p>
          </div>
        </AppTableState>
      </template>
    </Card>

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
.task-calendar__toolbar {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.task-calendar__groups {
  display: grid;
  gap: 0.75rem;
}

.task-calendar__list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: grid;
  gap: 0.5rem;
}

.task-calendar__list li {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
}

.task-calendar__meta {
  font-size: 0.8rem;
  color: var(--layout-text-muted);
}

.dashboard-page__state {
  padding: 2rem;
  text-align: center;
  color: var(--layout-text-muted);
}
</style>
