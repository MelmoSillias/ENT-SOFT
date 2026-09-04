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
  if (!q) return items.value
  return items.value.filter((item) =>
    [item.code, item.title, item.object].filter(Boolean).join(' ').toLowerCase().includes(q),
  )
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

function buildMenuItems(item) {
  const menu = [
    { label: 'Voir le détail', icon: 'pi pi-eye', command: () => router.push({ name: 'project-detail', params: { id: item.id } }) },
  ]
  if (hasPermission('project.projects.update')) menu.push({ label: 'Modifier', icon: 'pi pi-pencil', command: () => openEdit(item) })
  if (hasPermission('project.projects.delete')) menu.push({ label: 'Supprimer', icon: 'pi pi-trash', command: () => askDelete(item) })
  return menu
}

function toggleMenu(event, item) {
  actionItem.value = item
  menuModel.value = buildMenuItems(item)
  actionMenu.value?.toggle(event)
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
          :reloading="reloading"
          show-search
          v-model:search-term="searchTerm"
          search-placeholder="Rechercher…"
          @create="openCreate"
          @reload="reload"
        />
      </template>
      <template #content>
        <AppTableState :loading="loading" :error="error" :is-empty="!loading && !error && filteredItems.length === 0" @retry="load">
          <DataTable :value="filteredItems" paginator :rows="10" striped-rows>
            <Column field="code" header="Code" />
            <Column field="title" header="Titre" />
            <Column header="Statut">
              <template #body="{ data }">
                <Tag :value="projectStatusLabel(data.status)" :severity="projectStatusSeverity(data.status)" />
              </template>
            </Column>
            <Column header="Nb sites" field="nbSites" style="width: 6rem" />
            <Column header="Budget">
              <template #body="{ data }">{{ formatMontant(data.budget, DEVISE_APP) }}</template>
            </Column>
            <Column header="Début">
              <template #body="{ data }">{{ formatDateFr(data.dateDebut) }}</template>
            </Column>
            <Column header="Actions" style="width: 5rem">
              <template #body="{ data }">
                <Button icon="pi pi-ellipsis-v" text rounded :loading="deleting && actionItem?.id === data.id" @click="toggleMenu($event, data)" />
              </template>
            </Column>
          </DataTable>
          <Menu ref="actionMenu" :model="menuModel" popup />
        </AppTableState>
      </template>
    </Card>

    <Dialog v-model:visible="dialog" :header="dialogTitle" modal style="width: min(720px, 95vw)">
      <ProjectFormFields v-model="form" :errors="fieldErrors" :client-options="clientOptions" :show-code="Boolean(editingId)" />
      <template #footer>
        <Button label="Annuler" severity="secondary" text :disabled="saving" @click="dialog = false" />
        <Button :label="editingId ? 'Enregistrer' : 'Créer'" icon="pi pi-check" :loading="saving" @click="saveItem" />
      </template>
    </Dialog>
  </section>
</template>
