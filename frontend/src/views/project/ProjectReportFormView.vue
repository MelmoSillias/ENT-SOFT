<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Button from 'primevue/button'
import Card from 'primevue/card'
import DatePicker from 'primevue/datepicker'
import InputText from 'primevue/inputtext'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import ReportSiteAttachments from '@/domains/project/components/ReportSiteAttachments.vue'
import {
  createProjectReport,
  deleteProjectReportDocument,
  getProjectReport,
  updateProjectReport,
} from '@/domains/project/services/projectReportService'
import { getProjectDetail, listProjects } from '@/domains/project/services/projectService'
import { listSites } from '@/domains/site/services/siteService'
import { listEmployees } from '@/domains/employee/services/employeeService'
import { listDocuments, updateDocument, uploadDocumentFile } from '@/domains/document/services/documentService'
import { parseApiDate, toApiDate } from '@/domains/shared/utils/dateUtils'
import { useAppToast } from '@/domains/shared/composables/useAppToast'

const route = useRoute()
const router = useRouter()
const toast = useAppToast()

const loading = ref(true)
const saving = ref(false)
const error = ref(null)
const projects = ref([])
const sites = ref([])
const employees = ref([])
const projectSites = ref([])
const pickerProjectId = ref(null)
const pickerProjectSiteIds = ref([])
const pickerSiteIds = ref([])
const removedDocumentIds = ref([])

const STATUS_OPTIONS = [
  { label: 'En attente', value: 'pending' },
  { label: 'En cours', value: 'in_progress' },
  { label: 'Terminé', value: 'completed' },
  { label: 'Bloqué', value: 'blocked' },
]
const REPORT_STATUS_OPTIONS = [
  { label: 'Brouillon', value: 'draft' },
  { label: 'Finalisé', value: 'finalized' },
]

function emptyForm() {
  return {
    startDate: new Date(),
    endDate: new Date(),
    description: '',
    status: 'draft',
    sites: [],
  }
}

const form = ref(emptyForm())
const isEditing = computed(() => Boolean(route.params.id))
const title = computed(() => isEditing.value ? `Modifier ${form.value.code || 'le rapport'}` : 'Nouveau rapport d’activité')
const employeeOptions = computed(() => employees.value.map((employee) => ({
  label: employeeDisplayName(employee),
  value: employee.id,
})))
const projectOptions = computed(() => projects.value.map((project) => ({
  label: `${project.code || ''} — ${project.title}`.replace(/^ — /, ''),
  value: project.id,
})))
const projectSiteOptions = computed(() => projectSites.value.map((site) => ({
  label: `${site.siteCode || ''} — ${site.siteTitle || 'Site'}`.replace(/^ — /, ''),
  value: site.id,
})))
const siteOptions = computed(() => sites.value.map((site) => ({
  label: `${site.code || ''} — ${site.title}`.replace(/^ — /, ''),
  value: site.id,
})))

function employeeDisplayName(employee) {
  return employee.fullName || employee.name
    || [employee.firstName || employee.prenom, employee.lastName || employee.nom].filter(Boolean).join(' ')
    || employee.email
    || 'Personne'
}

function newLine(data = {}) {
  const attendees = data.attendees || []
  return {
    id: data.id || null,
    projectId: data.projectId || null,
    projectSiteId: data.projectSiteId || null,
    siteId: data.siteId || null,
    projectCode: data.projectCode || '',
    projectTitle: data.projectTitle || '',
    siteCode: data.siteCode || data.code || '',
    siteTitle: data.siteTitle || data.title || 'Site',
    status: data.status || 'pending',
    observation: data.observation || data.comment || '',
    plannedTask: data.plannedTask || data.description || '',
    groupLabel: data.groupLabel || '',
    attendeeIds: attendees.map((entry) => typeof entry === 'string' ? entry : entry.id).filter(Boolean),
    attendeeSnapshots: attendees,
    attachments: data.attachments || [],
  }
}

async function hydrateReport(report) {
  form.value = {
    id: report.id,
    code: report.code,
    startDate: parseApiDate(report.startDate) || new Date(),
    endDate: parseApiDate(report.endDate || report.startDate) || new Date(),
    description: report.description || '',
    status: report.status || 'draft',
    sites: (report.sites || report.lines || []).map(newLine),
  }
  await Promise.all(form.value.sites.filter((line) => line.id && !line.attachments.length).map(async (line) => {
    const documents = await listDocuments({ ownerType: 'activity_report_site', ownerId: line.id })
    line.attachments = documents.map((document) => ({
      ...document,
      url: document.fileUrl || document.url || document.filePath,
      pending: false,
      originalTitle: document.title,
      originalDescription: document.description || '',
    }))
  }))
}

function loadPlanningPrefill() {
  const raw = sessionStorage.getItem('activity-report-prefill')
  if (!raw) return
  sessionStorage.removeItem('activity-report-prefill')
  try {
    const prefill = JSON.parse(raw)
    form.value.startDate = parseApiDate(prefill.startDate) || new Date()
    form.value.endDate = parseApiDate(prefill.endDate || prefill.startDate) || form.value.startDate
    form.value.sites = (prefill.sites || []).map(newLine)
  } catch {
    // Ignore stale or malformed navigation state.
  }
}

async function loadProjectSites(projectId) {
  pickerProjectId.value = projectId
  pickerProjectSiteIds.value = []
  projectSites.value = projectId ? (await getProjectDetail(projectId)).sites || [] : []
}

function addProjectSites() {
  const existing = new Set(form.value.sites.map((line) => line.projectSiteId).filter(Boolean))
  const project = projects.value.find((item) => item.id === pickerProjectId.value)
  const added = projectSites.value
    .filter((site) => pickerProjectSiteIds.value.includes(site.id) && !existing.has(site.id))
    .map((site) => newLine({
      ...site,
      projectId: pickerProjectId.value,
      projectSiteId: site.id,
      projectCode: project?.code || '',
      projectTitle: project?.title || '',
      observation: site.comment || '',
      attendees: (site.employeeIds || []).map((id) => ({
        id,
        name: employeeDisplayName(employees.value.find((employee) => employee.id === id) || { id }),
        kind: 'employee',
      })),
    }))
  form.value.sites.push(...added)
  pickerProjectSiteIds.value = []
}

function addStandaloneSites() {
  const existing = new Set(form.value.sites.map((line) => line.siteId).filter(Boolean))
  const added = sites.value
    .filter((site) => pickerSiteIds.value.includes(site.id) && !existing.has(site.id))
    .map((site) => newLine({ siteId: site.id, siteCode: site.code, siteTitle: site.title }))
  form.value.sites.push(...added)
  pickerSiteIds.value = []
}

function removeLine(index) {
  const [line] = form.value.sites.splice(index, 1)
  removedDocumentIds.value.push(...(line?.attachments || []).filter((item) => item.id).map((item) => item.id))
}

function payload() {
  return {
    startDate: toApiDate(form.value.startDate),
    endDate: toApiDate(form.value.endDate || form.value.startDate),
    description: form.value.description.trim() || null,
    status: form.value.status,
    sites: form.value.sites.map((line) => ({
      id: line.id || undefined,
      projectId: line.projectId || null,
      projectSiteId: line.projectSiteId || null,
      siteId: line.siteId || null,
      projectCode: line.projectCode || null,
      projectTitle: line.projectTitle || null,
      siteCode: line.siteCode || null,
      siteTitle: line.siteTitle,
      status: line.status,
      observation: line.observation || null,
      plannedTask: line.plannedTask || null,
      groupLabel: line.groupLabel || null,
      attendees: line.attendeeIds.map((id) => {
        const employee = employees.value.find((entry) => entry.id === id)
        const snapshot = line.attendeeSnapshots.find((entry) => entry.id === id)
        return {
          id,
          name: employee ? employeeDisplayName(employee) : snapshot?.name || 'Personne',
          kind: snapshot?.kind || 'employee',
        }
      }),
    })),
  }
}

async function uploadPending(saved) {
  const savedLines = saved.sites || saved.lines || []
  for (let index = 0; index < form.value.sites.length; index += 1) {
    const line = form.value.sites[index]
    const ownerId = savedLines[index]?.id || line.id
    if (!ownerId) continue
    for (const attachment of line.attachments.filter((item) => item.pending && item.file)) {
      await uploadDocumentFile({
        file: attachment.file,
        title: attachment.title || attachment.file.name,
        description: attachment.description || null,
        ownerType: 'activity_report_site',
        ownerId,
      })
    }
    for (const attachment of line.attachments.filter((item) => !item.pending && item.id)) {
      if (
        attachment.title !== attachment.originalTitle
        || (attachment.description || '') !== attachment.originalDescription
      ) {
        await updateDocument(attachment.id, {
          title: attachment.title,
          description: attachment.description || null,
        })
      }
    }
  }
  await Promise.all(removedDocumentIds.value.map((id) => deleteProjectReportDocument(saved.id, id)))
}

async function save() {
  if (!form.value.startDate || !form.value.endDate || !form.value.sites.length) {
    toast.add({ severity: 'warn', summary: 'Rapport', detail: 'Renseignez une période et au moins un site.' })
    return
  }
  saving.value = true
  try {
    const saved = isEditing.value
      ? await updateProjectReport(route.params.id, payload())
      : await createProjectReport(payload())
    await uploadPending(saved)
    toast.add({ severity: 'success', summary: 'Rapport', detail: 'Rapport enregistré.' })
    await router.replace({ name: 'project-report-edit', params: { id: saved.id || route.params.id } })
    if (saved.id || route.params.id) await hydrateReport(await getProjectReport(saved.id || route.params.id))
    removedDocumentIds.value = []
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Rapport', detail: e.response?.data?.error || 'Enregistrement impossible.' })
  } finally {
    saving.value = false
  }
}

async function load() {
  loading.value = true
  error.value = null
  try {
    const [projectRows, siteRows, employeeRows] = await Promise.all([
      listProjects(),
      listSites(),
      listEmployees(),
    ])
    projects.value = projectRows
    sites.value = siteRows
    employees.value = employeeRows
    if (isEditing.value) {
      await hydrateReport(await getProjectReport(route.params.id))
    } else {
      loadPlanningPrefill()
      const projectId = route.query.projectId
      const ids = String(route.query.projectSiteIds || '').split(',').filter(Boolean)
      if (projectId && ids.length) {
        await loadProjectSites(projectId)
        pickerProjectSiteIds.value = ids
        addProjectSites()
      }
    }
  } catch (e) {
    error.value = e.response?.data?.error || 'Impossible de charger le formulaire.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <section class="dashboard-page report-form-view">
    <div class="report-form-view__header">
      <Button icon="pi pi-arrow-left" label="Rapports" text @click="router.push({ name: 'projects', query: { tab: 'reports' } })" />
      <div>
        <h1>{{ title }}</h1>
        <p>Rapport multi-projets avec données et pièces jointes propres à chaque site.</p>
      </div>
      <Button label="Enregistrer" icon="pi pi-check" :loading="saving" :disabled="loading" @click="save" />
    </div>

    <p v-if="error" class="report-form-view__error">{{ error }}</p>
    <template v-else-if="!loading">
      <Card class="dashboard-panel">
        <template #title>Informations générales</template>
        <template #content>
          <div class="report-form-view__general">
            <label>Du<DatePicker v-model="form.startDate" date-format="dd/mm/yy" show-icon /></label>
            <label>Au<DatePicker v-model="form.endDate" date-format="dd/mm/yy" show-icon /></label>
            <label>État<Select v-model="form.status" :options="REPORT_STATUS_OPTIONS" option-label="label" option-value="value" /></label>
            <label class="report-form-view__description">
              Description
              <Textarea v-model="form.description" rows="3" auto-resize placeholder="Laisser vide pour générer automatiquement une description selon la période." />
            </label>
          </div>
        </template>
      </Card>

      <Card class="dashboard-panel">
        <template #title>Ajouter des sites</template>
        <template #content>
          <div class="report-form-view__pickers">
            <Select
              v-model="pickerProjectId"
              :options="projectOptions"
              option-label="label"
              option-value="value"
              filter
              placeholder="Choisir un projet"
              @update:model-value="loadProjectSites"
            />
            <MultiSelect
              v-model="pickerProjectSiteIds"
              :options="projectSiteOptions"
              option-label="label"
              option-value="value"
              filter
              display="chip"
              placeholder="Sites du projet"
            />
            <Button label="Ajouter la sélection" icon="pi pi-plus" :disabled="!pickerProjectSiteIds.length" @click="addProjectSites" />
          </div>
          <div class="report-form-view__pickers">
            <MultiSelect
              v-model="pickerSiteIds"
              :options="siteOptions"
              option-label="label"
              option-value="value"
              filter
              display="chip"
              placeholder="Ou choisir des sites hors projet"
            />
            <Button label="Ajouter les sites" icon="pi pi-plus" severity="secondary" :disabled="!pickerSiteIds.length" @click="addStandaloneSites" />
          </div>
        </template>
      </Card>

      <div class="report-form-view__sites">
        <Card v-for="(line, index) in form.sites" :key="line.id || `${line.siteId}-${index}`" class="dashboard-panel report-site">
          <template #title>
            <div class="report-site__title">
              <span>{{ line.siteCode ? `${line.siteCode} — ` : '' }}{{ line.siteTitle }}</span>
              <Button icon="pi pi-trash" text rounded severity="danger" aria-label="Retirer le site" @click="removeLine(index)" />
            </div>
          </template>
          <template #subtitle>
            {{ [line.projectCode, line.projectTitle, line.groupLabel].filter(Boolean).join(' — ') || 'Site hors projet' }}
          </template>
          <template #content>
            <div class="report-site__fields">
              <label>Statut<Select v-model="line.status" :options="STATUS_OPTIONS" option-label="label" option-value="value" /></label>
              <label>Tâche prévue<InputText v-model="line.plannedTask" /></label>
              <label class="report-site__wide">Observation<Textarea v-model="line.observation" rows="3" auto-resize /></label>
              <label class="report-site__wide">
                Personnes présentes
                <MultiSelect
                  v-model="line.attendeeIds"
                  :options="employeeOptions"
                  option-label="label"
                  option-value="value"
                  filter
                  display="chip"
                  placeholder="Choisir les intervenants"
                />
              </label>
              <ReportSiteAttachments
                v-model="line.attachments"
                class="report-site__wide"
                @remove-existing="removedDocumentIds.push($event)"
              />
            </div>
          </template>
        </Card>
        <div v-if="!form.sites.length" class="report-form-view__empty">
          <i class="pi pi-map-marker" />
          <p>Ajoutez des sites depuis un ou plusieurs projets, ou choisissez des sites hors projet.</p>
        </div>
      </div>
    </template>
  </section>
</template>

<style scoped>
.report-form-view {
  gap: 1rem;
}

.report-form-view__header,
.report-site__title {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.report-form-view__header > div,
.report-site__title span {
  flex: 1;
}

.report-form-view__header h1,
.report-form-view__header p {
  margin: 0;
}

.report-form-view__header p {
  color: var(--layout-text-muted, var(--p-text-muted-color));
}

.report-form-view__general,
.report-site__fields {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1rem;
}

label {
  display: grid;
  gap: 0.4rem;
  font-weight: 600;
}

.report-form-view__description,
.report-site__wide {
  grid-column: 1 / -1;
}

.report-form-view__pickers {
  display: grid;
  grid-template-columns: minmax(12rem, 0.7fr) minmax(16rem, 1.3fr) auto;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}

.report-form-view__sites {
  display: grid;
  gap: 1rem;
}

.report-form-view__empty {
  display: grid;
  justify-items: center;
  gap: 0.5rem;
  padding: 2.5rem;
  border: 1px dashed var(--layout-panel-border);
  border-radius: var(--layout-radius-lg, 0.9rem);
  color: var(--layout-text-muted, var(--p-text-muted-color));
  text-align: center;
}

.report-form-view__empty i {
  font-size: 2rem;
}

.report-form-view__error {
  color: var(--p-red-500);
}

@media (max-width: 800px) {
  .report-form-view__header {
    align-items: flex-start;
    flex-wrap: wrap;
  }

  .report-form-view__general,
  .report-site__fields,
  .report-form-view__pickers {
    grid-template-columns: 1fr;
  }
}
</style>
