<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Card from 'primevue/card'
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
import ProjectFormFields from '@/domains/project/components/ProjectFormFields.vue'
import { listProjects, createProject, updateProject, deleteProject } from '@/domains/project/services/projectService'
import { listClients } from '@/domains/client/services/clientService'
import { projectStatusLabel, projectStatusSeverity, formatDateFr } from '@/domains/shared/utils/entLabels'
import { toApiDate, parseApiDate } from '@/domains/shared/utils/dateUtils'
import { hasRequiredText, requiredMessage } from '@/domains/shared/utils/formValidation'
import { useFormFieldErrors } from '@/domains/shared/composables/useFormFieldErrors'
import { useConfirm } from 'primevue/useconfirm'
import { useAsyncAction } from '@/domains/shared/composables/useAsyncAction'
import { usePermissions } from '@/domains/auth/composables/usePermissions'
import { useAppToast } from '@/domains/shared/composables/useAppToast'
import { formatMontant } from '@/domains/shared/utils/formatMontant'
import { DEVISE_APP } from '@/domains/shared/constants/devise'

const router = useRouter()
const toast = useAppToast()
const confirm = useConfirm()
const { hasPermission } = usePermissions()
const { isAppMobile } = useAppMobileLayout()

const items = ref([])
const clientOptions = ref([])
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

const PROJECT_COLUMNS = [
  { key: 'code', label: 'Code', defaultVisible: true },
  { key: 'title', label: 'Titre', defaultVisible: true },
  { key: 'status', label: 'Statut', defaultVisible: true },
  { key: 'nbSites', label: 'Nb sites', defaultVisible: true },
  { key: 'budget', label: 'Budget', defaultVisible: true },
  { key: 'dateDebut', label: 'Début', defaultVisible: true },
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
} = useTableSettings('table_projects', PROJECT_COLUMNS, {
  defaultSortField: 'title',
})

const canCreate = computed(() => hasPermission('project.projects.create'))

function emptyForm() {
  return {
    title: '',
    clientId: null,
    object: '',
    dateDebut: null,
    dateFin: null,
    status: 'draft',
    budget: 0,
    sitesInfoLabels: [],
  }
}

function slugifyInfoKey(label) {
  return String(label ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '')
    || 'info'
}

function labelsToSitesInformations(labels, existing = []) {
  const byLabel = new Map(
    (existing ?? [])
      .filter((c) => c?.label && c?.key)
      .map((c) => [String(c.label).trim().toLowerCase(), c.key]),
  )
  const used = new Set()
  return (labels ?? [])
    .map((label) => String(label).trim())
    .filter(Boolean)
    .map((label) => {
      let key = byLabel.get(label.toLowerCase()) || slugifyInfoKey(label)
      let n = 2
      const base = key
      while (used.has(key)) {
        key = `${base}_${n++}`
      }
      used.add(key)
      return { key, label }
    })
}

const form = ref(emptyForm())

const { errors: fieldErrors, validate: validateForm, resetErrors } = useFormFieldErrors(() => {
  const errs = {}
  if (!hasRequiredText(form.value.title)) errs.title = requiredMessage('Titre')
  if (!form.value.clientId) errs.clientId = requiredMessage('Client')
  return errs
})

async function fetchItems() {
  items.value = await listProjects()
}

async function loadClients() {
  const clients = await listClients()
  clientOptions.value = clients.map((c) => ({ label: `${c.code} — ${c.title}`, value: c.id }))
}

async function load() {
  loading.value = true
  error.value = null
  try {
    await Promise.all([fetchItems(), loadClients()])
  } catch (e) {
    error.value = e.response?.data?.error || 'Impossible de charger les projets.'
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

const filteredItems = computed(() => {
  const q = searchTerm.value.trim().toLowerCase()
  let list = items.value
  if (q) {
    list = list.filter((item) =>
      [item.code, item.title, item.object].filter(Boolean).join(' ').toLowerCase().includes(q),
    )
  }
  return sortByField(list, sortField.value, sortOrder.value)
})

const countLabel = computed(() => `${filteredItems.value.length}${searchTerm.value.trim() ? ` / ${items.value.length}` : ''}`)
const dialogTitle = computed(() => (editingId.value ? 'Modifier projet' : 'Nouveau projet'))

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
    clientId: item.clientId,
    object: item.object ?? '',
    dateDebut: parseApiDate(item.dateDebut),
    dateFin: parseApiDate(item.dateFin),
    status: item.status ?? 'draft',
    budget: item.budget ?? 0,
    sitesInfoLabels: (item.sitesInformations ?? [])
      .filter((c) => c?.label && !['status_raw', 'status_source', 'status-source'].includes(c.key))
      .map((c) => c.label),
  }
  resetErrors()
  dialog.value = true
}

function openDetail(item) {
  router.push({ name: 'project-detail', params: { id: item.id } })
}

function buildMenuItems(item) {
  const menu = [
    { label: 'Voir le détail', icon: 'pi pi-eye', command: () => openDetail(item) },
  ]
  if (hasPermission('project.projects.update')) menu.push({ label: 'Modifier', icon: 'pi pi-pencil', command: () => openEdit(item) })
  if (hasPermission('project.projects.delete')) menu.push({ label: 'Supprimer', icon: 'pi pi-trash', severity: 'danger', command: () => askDelete(item) })
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
    header: 'Supprimer le projet',
    message: `Supprimer « ${item.title} » ?`,
    icon: 'pi pi-exclamation-triangle',
    rejectProps: { label: 'Annuler', severity: 'secondary', outlined: true },
    acceptProps: { label: 'Supprimer', severity: 'danger' },
    accept: () => runDelete(item),
  })
}

const { pending: deleting, run: runDelete } = useAsyncAction(async (item) => {
  try {
    await deleteProject(item.id)
    toast.add({ severity: 'success', summary: 'Projet', detail: 'Supprimé.' })
    await fetchItems()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Projet', detail: e.response?.data?.error || 'Erreur.' })
  }
})

const { pending: saving, run: saveItem } = useAsyncAction(async () => {
  if (!validateForm()) return
  const existingInfos = editingId.value
    ? (items.value.find((p) => p.id === editingId.value)?.sitesInformations ?? [])
    : []
  const payload = {
    title: form.value.title.trim(),
    clientId: form.value.clientId,
    object: form.value.object || null,
    dateDebut: toApiDate(form.value.dateDebut),
    dateFin: toApiDate(form.value.dateFin),
    status: form.value.status,
    budget: form.value.budget ?? 0,
    sitesInformations: labelsToSitesInformations(form.value.sitesInfoLabels, existingInfos),
  }
  try {
    if (editingId.value) await updateProject(editingId.value, payload)
    else await createProject(payload)
    dialog.value = false
    await fetchItems()
    toast.add({ severity: 'success', summary: 'Projet', detail: 'Enregistré.' })
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Projet', detail: e.response?.data?.error || 'Erreur.' })
  }
})
</script>

<template>
  <section class="dashboard-page">
    <Card class="dashboard-panel">
      <template #title>
        <AppTablePanelHeader
          title="Projets"
          :count-label="countLabel"
          create-label="Nouveau projet"
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
      </template>
      <template #content>
        <AppTableState :loading="loading" :error="error" :is-empty="!loading && !error && filteredItems.length === 0" @retry="load">
          <AppEntityDataView
            v-if="isAppMobile"
            :items="filteredItems"
            :title-of="(item) => item.title"
            :code-of="(item) => item.code"
            :subtitle-of="(item) => item.object || null"
            :status-of="(item) => ({ value: projectStatusLabel(item.status), severity: projectStatusSeverity(item.status) })"
            :meta-of="(item) => formatMontant(item.budget, DEVISE_APP)"
            :actions-of="buildMenuItems"
            :row-bindings-of="(item) => rowContextMenu?.rowBindings(item) ?? {}"
            @select="openDetail"
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
          >
            <Column v-if="showIndex" header="#" style="width: 3.5rem">
              <template #body="{ index }">{{ index + 1 }}</template>
            </Column>
            <Column v-if="isColVisible('code')" field="code" header="Code" sortable />
            <Column v-if="isColVisible('title')" field="title" header="Titre" sortable />
            <Column v-if="isColVisible('status')" field="status" header="Statut" sortable>
              <template #body="{ data }">
                <Tag :value="projectStatusLabel(data.status)" :severity="projectStatusSeverity(data.status)" />
              </template>
            </Column>
            <Column v-if="isColVisible('nbSites')" header="Nb sites" field="nbSites" style="width: 6rem" sortable />
            <Column v-if="isColVisible('budget')" field="budget" header="Budget" sortable>
              <template #body="{ data }">{{ formatMontant(data.budget, DEVISE_APP) }}</template>
            </Column>
            <Column v-if="isColVisible('dateDebut')" field="dateDebut" header="Début" sortable>
              <template #body="{ data }">{{ formatDateFr(data.dateDebut) }}</template>
            </Column>
            <Column header="Actions" style="width: 5rem">
              <template #body="{ data }">
                <Button icon="pi pi-ellipsis-v" text rounded :loading="deleting && actionItem?.id === data.id" @click="toggleMenu($event, data)" />
              </template>
            </Column>
          </DataTable>
          <Menu v-if="!isAppMobile" ref="actionMenu" :model="menuModel" popup />
          <AppRowContextMenu ref="rowContextMenu" :actions-of="buildMenuItems" />
        </AppTableState>
      </template>
    </Card>

    <AppMobileFab
      v-if="isAppMobile && canCreate"
      aria-label="Nouveau projet"
      @click="openCreate"
    />

    <Dialog v-model:visible="dialog" :header="dialogTitle" modal style="width: min(720px, 95vw)">
      <ProjectFormFields v-model="form" :errors="fieldErrors" :client-options="clientOptions" :show-code="Boolean(editingId)" />
      <template #footer>
        <Button label="Annuler" severity="secondary" text :disabled="saving" @click="dialog = false" />
        <Button :label="editingId ? 'Enregistrer' : 'Créer'" icon="pi pi-check" :loading="saving" @click="saveItem" />
      </template>
    </Dialog>
  </section>
</template>
