<script setup>
import { computed, nextTick, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Button from 'primevue/button'
import Card from 'primevue/card'
import DatePicker from 'primevue/datepicker'
import InputText from 'primevue/inputtext'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import SelectButton from 'primevue/selectbutton'
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
const projectSitesByProjectId = ref({})
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
const PROJECT_MODE_OPTIONS = [
  { label: 'Projet enregistré', value: 'registered' },
  { label: 'Description manuelle', value: 'manual' },
]

function emptyForm() {
  return {
    startDate: new Date(),
    endDate: new Date(),
    description: '',
    status: 'draft',
    groups: [],
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
const siteOptions = computed(() => sites.value.map((site) => ({
  label: `${site.code || ''} — ${site.title}`.replace(/^ — /, ''),
  value: site.id,
})))

function createId() {
  if (typeof crypto !== 'undefined' && crypto.randomUUID) return crypto.randomUUID()
  return `grp-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

function uniqueIds(ids) {
  return [...new Set((ids || []).filter((id) => typeof id === 'string' && id))]
}

function newGroup(data = {}) {
  return {
    id: data.id || createId(),
    mode: data.mode || 'registered',
    projectId: data.projectId || null,
    projectCode: data.projectCode || '',
    projectTitle: data.projectTitle || '',
    manualLabel: data.manualLabel || '',
    projectSites: data.projectSites || [],
    selectedSiteIds: uniqueIds(data.selectedSiteIds || []),
    sites: (data.sites || []).map(newLine),
    loadingProjectSites: false,
  }
}

function groupLabel(group) {
  if (group.mode === 'registered') {
    return [group.projectCode, group.projectTitle].filter(Boolean).join(' — ') || 'Projet enregistré'
  }
  return group.manualLabel?.trim() || 'Description manuelle'
}

function groupTitle(index) {
  return `Projet ${index + 1}`
}

function lineKey(line) {
  return line.projectSiteId || line.siteId || line.id || null
}

function buildRegisteredLine(group, site) {
  const project = projects.value.find((projectRow) => projectRow.id === group.projectId)
  const attendeeIds = (site.employeeIds || []).slice(0, 1)
  return newLine({
    ...site,
    projectId: group.projectId,
    projectSiteId: site.id,
    projectCode: group.projectCode || project?.code || '',
    projectTitle: group.projectTitle || project?.title || '',
    siteId: site.siteId || null,
    siteCode: site.siteCode || '',
    siteTitle: site.siteTitle || 'Site',
    groupLabel: '',
    observation: site.comment || '',
    attendees: attendeeIds.map((id) => ({
      id,
      name: employeeDisplayName(employees.value.find((employee) => employee.id === id) || { id }),
      kind: 'employee',
    })),
  })
}

function buildManualLine(site, group) {
  return newLine({
    siteId: site.id,
    siteCode: site.code || '',
    siteTitle: site.title || 'Site',
    groupLabel: group.manualLabel || '',
  })
}

function usedProjectIds(exceptGroupId = null) {
  const ids = new Set()
  for (const group of form.value.groups) {
    if (group.id === exceptGroupId) continue
    if (group.projectId) ids.add(group.projectId)
  }
  return ids
}

function usedProjectSiteIds(exceptGroupId = null) {
  const ids = new Set()
  for (const group of form.value.groups) {
    if (group.id === exceptGroupId) continue
    for (const line of group.sites) {
      if (line.projectSiteId) ids.add(line.projectSiteId)
    }
  }
  return ids
}

function usedSiteIds(exceptGroupId = null) {
  const ids = new Set()
  for (const group of form.value.groups) {
    if (group.id === exceptGroupId) continue
    for (const line of group.sites) {
      if (line.siteId) ids.add(line.siteId)
    }
  }
  return ids
}

function groupProjectOptions(group) {
  const used = usedProjectIds(group.id)
  return projectOptions.value.filter((option) => option.value === group.projectId || !used.has(option.value))
}

function groupSiteOptions(group) {
  if (group.mode === 'registered') {
    const used = usedProjectSiteIds(group.id)
    return (group.projectSites || [])
      .filter((site) => !used.has(site.id) || group.selectedSiteIds.includes(site.id))
      .map((site) => ({
        label: `${site.siteCode || ''} — ${site.siteTitle || 'Site'}`.replace(/^ — /, ''),
        value: site.id,
      }))
  }

  const used = usedSiteIds(group.id)
  return sites.value
    .filter((site) => !used.has(site.id) || group.selectedSiteIds.includes(site.id))
    .map((site) => ({
      label: `${site.code || ''} — ${site.title || 'Site'}`.replace(/^ — /, ''),
      value: site.id,
    }))
}

async function ensureProjectSites(projectId) {
  if (!projectId) return []
  if (projectSitesByProjectId.value[projectId]) return projectSitesByProjectId.value[projectId]
  const detail = await getProjectDetail(projectId)
  const loadedSites = detail.sites || []
  projectSitesByProjectId.value = {
    ...projectSitesByProjectId.value,
    [projectId]: loadedSites,
  }
  return loadedSites
}

async function hydrateRegisteredGroups(groups) {
  await Promise.all(groups
    .filter((group) => group.mode === 'registered' && group.projectId)
    .map(async (group) => {
      group.loadingProjectSites = true
      try {
        group.projectSites = await ensureProjectSites(group.projectId)
      } finally {
        group.loadingProjectSites = false
      }
    }))
}

function syncGroupSites(group) {
  const rawOptions = group.mode === 'registered' ? (group.projectSites || []) : sites.value
  const optionsById = new Map(rawOptions.map((item) => [item.id, item]))
  const existingByKey = new Map(group.sites.map((line) => [lineKey(line), line]))
  const next = []

  for (const id of uniqueIds(group.selectedSiteIds)) {
    const site = optionsById.get(id)
    if (!site) continue
    const existing = existingByKey.get(id)
    next.push(existing || (group.mode === 'registered' ? buildRegisteredLine(group, site) : buildManualLine(site, group)))
  }

  group.sites = next
  group.selectedSiteIds = next.map(lineKey).filter(Boolean)
}

async function setGroupProject(group, projectId) {
  group.projectId = projectId || null
  group.projectCode = ''
  group.projectTitle = ''
  group.projectSites = []
  group.selectedSiteIds = []
  group.sites = []

  if (!projectId) return

  const project = projects.value.find((entry) => entry.id === projectId)
  group.projectCode = project?.code || ''
  group.projectTitle = project?.title || ''
  group.loadingProjectSites = true
  try {
    group.projectSites = await ensureProjectSites(projectId)
  } finally {
    group.loadingProjectSites = false
  }
}

function setGroupMode(group, mode) {
  group.mode = mode
  group.selectedSiteIds = []
  group.sites = []

  if (mode === 'manual') {
    group.projectId = null
    group.projectCode = ''
    group.projectTitle = ''
    group.projectSites = []
    group.loadingProjectSites = false
  } else {
    group.manualLabel = ''
  }
}

function buildGroupsFromLines(lines = []) {
  const groups = new Map()
  lines.forEach((line) => {
    const groupMode = line.projectId ? 'registered' : 'manual'
    const groupKey = groupMode === 'registered'
      ? `project:${line.projectId}`
      : `manual:${(line.groupLabel || '').trim() || line.siteCode || line.siteTitle || line.id || createId()}`

    if (!groups.has(groupKey)) {
      groups.set(groupKey, newGroup({
        mode: groupMode,
        projectId: line.projectId || null,
        projectCode: line.projectCode || '',
        projectTitle: line.projectTitle || '',
        manualLabel: groupMode === 'manual' ? (line.groupLabel || '') : '',
      }))
    }

    const group = groups.get(groupKey)
    group.sites.push(newLine(line))
    const key = line.projectSiteId || line.siteId
    if (key) group.selectedSiteIds.push(key)
  })

  return [...groups.values()].map((group) => ({
    ...group,
    selectedSiteIds: uniqueIds(group.selectedSiteIds),
  }))
}

function flattenGroupLines() {
  return form.value.groups.flatMap((group) => group.sites)
}

function collectRemovedAttachments(lines) {
  for (const line of lines) {
    removedDocumentIds.value.push(...(line?.attachments || []).filter((item) => item.id).map((item) => item.id))
  }
}

async function addGroup(initial = {}, { scroll = true } = {}) {
  const group = newGroup(initial)
  form.value.groups.push(group)
  if (group.projectId) {
    await setGroupProject(group, group.projectId)
    group.selectedSiteIds = uniqueIds(initial.selectedSiteIds || [])
    syncGroupSites(group)
  }
  if (scroll) {
    await nextTick()
    document.querySelector(`[data-report-group="${group.id}"]`)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }
  return group
}

function removeGroup(index) {
  const [group] = form.value.groups.splice(index, 1)
  if (!group) return
  collectRemovedAttachments(group.sites)
}

function removeGroupSite(group, index) {
  const [line] = group.sites.splice(index, 1)
  const key = lineKey(line)
  if (key) {
    group.selectedSiteIds = group.selectedSiteIds.filter((id) => id !== key)
  }
  collectRemovedAttachments([line])
}

async function upsertRegisteredGroup(projectId, selectedSiteIds = []) {
  const existing = form.value.groups.find((group) => group.mode === 'registered' && group.projectId === projectId)
  if (existing) {
    existing.selectedSiteIds = uniqueIds([...existing.selectedSiteIds, ...selectedSiteIds])
    syncGroupSites(existing)
    return existing
  }

  const group = await addGroup({ mode: 'registered', projectId, selectedSiteIds }, { scroll: false })
  group.selectedSiteIds = uniqueIds(selectedSiteIds)
  syncGroupSites(group)
  return group
}

function projectGroupSubtitle(group) {
  return group.mode === 'registered'
    ? [group.projectCode, group.projectTitle].filter(Boolean).join(' — ') || 'Projet enregistré'
    : group.manualLabel?.trim() || 'Description manuelle'
}

function isGroupInvalid(group) {
  if (group.mode === 'registered' && !group.projectId) return true
  if (group.mode === 'manual' && !group.manualLabel?.trim()) return true
  return !group.sites.length
}

function employeeDisplayName(employee) {
  return employee.fullName || employee.name
    || [employee.firstName || employee.prenom, employee.lastName || employee.nom].filter(Boolean).join(' ')
    || employee.email
    || 'Personne'
}

function newLine(data = {}) {
  const attendees = data.attendees || []
  const leader = attendees[0] || null
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
    teamLeaderId: typeof leader === 'string' ? leader : leader?.id || null,
    teamLeaderSnapshot: leader,
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
    groups: buildGroupsFromLines(report.sites || report.lines || []),
  }
  await hydrateRegisteredGroups(form.value.groups)
  await Promise.all(flattenGroupLines()
    .filter((line) => line.id && !line.attachments.length)
    .map(async (line) => {
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

async function loadPlanningPrefill() {
  const raw = sessionStorage.getItem('activity-report-prefill')
  if (!raw) return
  sessionStorage.removeItem('activity-report-prefill')
  try {
    const prefill = JSON.parse(raw)
    form.value.startDate = parseApiDate(prefill.startDate) || new Date()
    form.value.endDate = parseApiDate(prefill.endDate || prefill.startDate) || form.value.startDate
    form.value.groups = buildGroupsFromLines(prefill.sites || [])
    await hydrateRegisteredGroups(form.value.groups)
  } catch {
    // Ignore stale or malformed navigation state.
  }
}

function payload() {
  return {
    startDate: toApiDate(form.value.startDate),
    endDate: toApiDate(form.value.endDate || form.value.startDate),
    description: form.value.description.trim() || null,
    status: form.value.status,
    sites: form.value.groups.flatMap((group) => group.sites.map((line) => ({
      id: line.id || undefined,
      projectId: group.mode === 'registered' ? group.projectId || null : null,
      projectSiteId: group.mode === 'registered' ? line.projectSiteId || null : null,
      siteId: line.siteId || null,
      projectCode: group.mode === 'registered' ? group.projectCode || null : null,
      projectTitle: group.mode === 'registered' ? group.projectTitle || null : null,
      siteCode: line.siteCode || null,
      siteTitle: line.siteTitle,
      status: line.status,
      observation: line.observation || null,
      plannedTask: line.plannedTask || null,
      groupLabel: line.groupLabel || (group.mode === 'manual' ? group.manualLabel || null : null),
      attendees: line.teamLeaderId ? [line.teamLeaderId].map((id) => {
        const employee = employees.value.find((entry) => entry.id === id)
        const snapshot = line.teamLeaderSnapshot && line.teamLeaderSnapshot.id === id ? line.teamLeaderSnapshot : null
        return {
          id,
          name: employee ? employeeDisplayName(employee) : snapshot?.name || 'Personne',
          kind: snapshot?.kind || 'employee',
        }
      }) : [],
    }))),
  }
}

async function uploadPending(saved) {
  const savedLines = saved.sites || saved.lines || []
  const reportLines = flattenGroupLines()
  for (let index = 0; index < reportLines.length; index += 1) {
    const line = reportLines[index]
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
  if (!form.value.startDate || !form.value.endDate || !form.value.groups.length) {
    toast.add({ severity: 'warn', summary: 'Rapport', detail: 'Renseignez une période et ajoutez au moins un projet.' })
    return
  }
  const invalidGroup = form.value.groups.find(isGroupInvalid)
  if (invalidGroup) {
    toast.add({
      severity: 'warn',
      summary: 'Rapport',
      detail: invalidGroup.mode === 'registered'
        ? 'Choisissez un projet et au moins un site pour chaque carte.'
        : 'Renseignez la description de chaque carte manuelle et ajoutez au moins un site.',
    })
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
      await loadPlanningPrefill()
      const projectId = route.query.projectId
      const ids = String(route.query.projectSiteIds || '').split(',').filter(Boolean)
      if (projectId && ids.length) {
        await upsertRegisteredGroup(projectId, ids)
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
        <template #title>
          <div class="report-form-view__section-head">
            <span>Projets</span>
            <Button label="Ajouter un projet" icon="pi pi-plus" size="small" @click="addGroup()" />
          </div>
        </template>
        <template #content> 
          <div v-if="form.groups.length" class="report-form-view__groups">
            <Card
              v-for="(group, index) in form.groups"
              :key="group.id"
              class="dashboard-panel report-group"
              :data-report-group="group.id"
            >
              <template #title>
                <div class="report-group__title">
                  <div>
                    <span>{{ groupTitle(index) }}</span>
                    <small>{{ projectGroupSubtitle(group) }}</small>
                  </div>
                  <Button icon="pi pi-trash" text rounded severity="danger" aria-label="Retirer le projet" @click="removeGroup(index)" />
                </div>
              </template>

              <template #content>
                <div class="report-group__mode">
                  <SelectButton
                    v-model="group.mode"
                    :options="PROJECT_MODE_OPTIONS"
                    option-label="label"
                    option-value="value"
                    @update:model-value="(mode) => setGroupMode(group, mode)"
                  />
                </div>

                <div v-if="group.mode === 'registered'" class="report-group__fields">
                  <label>
                    Projet enregistré
                    <Select
                      v-model="group.projectId"
                      :options="groupProjectOptions(group)"
                      option-label="label"
                      option-value="value"
                      filter
                      placeholder="Choisir un projet"
                      :loading="group.loadingProjectSites"
                      @update:model-value="(projectId) => setGroupProject(group, projectId)"
                    />
                  </label>
                </div>

                <div v-else class="report-group__fields">
                  <label>
                    Description manuelle
                    <InputText v-model="group.manualLabel" placeholder="Décrire ce groupe manuel" />
                  </label>
                </div>

                <div class="report-group__fields">
                  <label class="report-group__wide">
                    Sites à intégrer
                    <MultiSelect
                      v-model="group.selectedSiteIds"
                      :options="groupSiteOptions(group)"
                      option-label="label"
                      option-value="value"
                      filter
                      display="chip"
                      :loading="group.loadingProjectSites"
                      :disabled="group.mode === 'registered' && !group.projectId"
                      placeholder="Rechercher et cocher des sites"
                      @update:model-value="() => syncGroupSites(group)"
                    />
                  </label>
                  <small class="report-group__hint report-group__wide">Les sites cochés apparaissent immédiatement ci-dessous.</small>
                </div>

                <div v-if="group.sites.length" class="report-group__sites">
                  <Card v-for="(line, lineIndex) in group.sites" :key="line.id || `${line.projectSiteId || line.siteId}-${lineIndex}`" class="dashboard-panel report-site">
                    <template #title>
                      <div class="report-site__title">
                        <span>{{ line.siteCode ? `${line.siteCode} — ` : '' }}{{ line.siteTitle }}</span>
                        <Button icon="pi pi-trash" text rounded severity="danger" aria-label="Retirer le site" @click="removeGroupSite(group, lineIndex)" />
                      </div>
                    </template>
                    <template #subtitle>
                      {{ [line.projectCode, line.projectTitle, line.groupLabel].filter(Boolean).join(' — ') || projectGroupSubtitle(group) }}
                    </template>
                    <template #content>
                      <div class="report-site__fields">
                        <label>Statut<Select v-model="line.status" :options="STATUS_OPTIONS" option-label="label" option-value="value" /></label>
                        <label>Tâche prévue<InputText v-model="line.plannedTask" /></label>
                        <label>Groupe / description<InputText v-model="line.groupLabel" placeholder="Groupe à la volée ou description manuelle" /></label>
                        <label class="report-site__wide">Observation<Textarea v-model="line.observation" rows="3" auto-resize /></label>
                        <label class="report-site__wide">
                          Chef d'équipe
                          <Select
                            v-model="line.teamLeaderId"
                            :options="employeeOptions"
                            option-label="label"
                            option-value="value"
                            filter
                            show-clear
                            placeholder="Choisir une personne"
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
                </div>

                <div v-else class="report-group__empty">
                  <i class="pi pi-map-marker" />
                  <p>Sélectionnez des sites pour les afficher ici.</p>
                </div>
              </template>
            </Card>
          </div>

          <div v-else class="report-form-view__empty">
            <i class="pi pi-folder-plus" />
            <p>Ajoutez un premier projet pour commencer le rapport.</p>
            <Button label="Ajouter un projet" icon="pi pi-plus" @click="addGroup()" />
          </div>
        </template>
      </Card>

      <Card v-if="false" class="dashboard-panel">
        <template #title>Ajouter des sites</template>
        <template #content>
          <div class="report-form-view__pickers report-form-view__pickers--group">
            <InputText v-model="pickerGroupLabel" placeholder="Groupe ou description manuelle" />
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
          <div class="report-form-view__pickers report-form-view__pickers--group">
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
          <div class="report-form-view__pickers report-form-view__pickers--create">
            <InputText v-model="newSiteCode" placeholder="Code du nouveau site" />
            <InputText v-model="newSiteTitle" placeholder="Titre du nouveau site" />
            <Button label="Créer et ajouter" icon="pi pi-plus" severity="secondary" :loading="creatingSite" @click="createAndAddSite" />
          </div>
        </template>
      </Card>

      <div v-if="false" class="report-form-view__sites">
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
              <label>Groupe / description<InputText v-model="line.groupLabel" placeholder="Groupe à la volée ou description manuelle" /></label>
              <label class="report-site__wide">Observation<Textarea v-model="line.observation" rows="3" auto-resize /></label>
              <label class="report-site__wide">
                Chef d'équipe
                <Select
                  v-model="line.teamLeaderId"
                  :options="employeeOptions"
                  option-label="label"
                  option-value="value"
                  filter
                  show-clear
                  placeholder="Choisir une personne"
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
      <Teleport to="body">
        <div class="report-form-view__save-fab">
          <Button label="" icon="pi pi-save" :loading="saving" :disabled="loading" @click="save" />
        </div>
      </Teleport>
    </template>
  </section>
</template>

<style scoped>
.report-form-view {
  gap: 1rem;
  padding-bottom: 5.5rem;
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

.dashboard-panel :deep(.p-card-body) {
  padding: 1rem 1.1rem;
}

.report-site :deep(.p-card-body) {
  padding-top: 0.85rem;
}

.report-site :deep(.p-card-title),
.report-site :deep(.p-card-subtitle) {
  padding-inline: 0;
}

.report-site :deep(.p-card-subtitle) {
  margin-top: -0.25rem;
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

.report-form-view__section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.report-form-view__section-head > span {
  font-weight: 700;
}

.report-form-view__hint {
  margin: 0 0 1rem;
  color: var(--layout-text-muted, var(--p-text-muted-color));
}

.report-form-view__groups {
  display: grid;
  gap: 1rem;
}

.report-group :deep(.p-card-body) {
  padding-top: 0.9rem;
}

.report-group__title,
.report-site__title {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
}

.report-group__title > div {
  display: grid;
  gap: 0.15rem;
  min-width: 0;
}

.report-group__title span {
  font-weight: 700;
}

.report-group__title small,
.report-group__hint {
  color: var(--layout-text-muted, var(--p-text-muted-color));
}

.report-group__mode {
  margin-bottom: 1rem;
}

.report-group__fields {
  display: grid;
  gap: 0.85rem;
  margin-bottom: 1rem;
}

.report-group__sites {
  display: grid;
  gap: 0.9rem;
}

.report-group__empty {
  display: grid;
  justify-items: center;
  gap: 0.45rem;
  padding: 1rem 0.25rem 0.25rem;
  color: var(--layout-text-muted, var(--p-text-muted-color));
  text-align: center;
}

.report-group__empty i {
  font-size: 1.4rem;
}

.report-form-view__save-fab {
  position: fixed;
  right: 1rem;
  bottom: 1rem;
  z-index: 40;
}

.report-form-view__pickers {
  display: grid;
  grid-template-columns: minmax(12rem, 0.7fr) minmax(16rem, 1.3fr) auto;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}

.report-form-view__pickers--group {
  grid-template-columns: minmax(12rem, 0.8fr) minmax(12rem, 0.9fr) minmax(16rem, 1.2fr) auto;
}

.report-form-view__pickers--create {
  grid-template-columns: minmax(12rem, 0.8fr) minmax(14rem, 1.2fr) auto;
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

  .report-form-view__section-head,
  .report-group__title {
    align-items: flex-start;
    flex-direction: column;
  }

  .report-form-view__save-fab {
    left: 3rem;
    right: 1.4rem;
  }

  .report-form-view__save-fab :deep(.p-button) {
    width: 100%;
    justify-content: center;
  }
}
</style>
