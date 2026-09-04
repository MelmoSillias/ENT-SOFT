<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Select from 'primevue/select'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import DatePicker from 'primevue/datepicker'
import AutoComplete from 'primevue/autocomplete'
import { getProjectDetail, updateProject, createProjectEvent } from '@/domains/project/services/projectService'
import ProjectSitesTable from '@/domains/project/components/ProjectSitesTable.vue'
import TransactionAttachments from '@/domains/finance/components/TransactionAttachments.vue'
import AppMobileSegmentTabs from '@/domains/shared/components/AppMobileSegmentTabs.vue'
import AppDetailInfoList from '@/domains/shared/components/AppDetailInfoList.vue'
import { useAppMobileLayout } from '@/domains/layout/composables/useAppMobileLayout'
import {
  formatDateFr,
  formatDateTimeFr,
  projectStatusLabel,
  projectStatusSeverity,
  PROJECT_STATUS_OPTIONS,
} from '@/domains/shared/utils/entLabels'
import { formatMontant } from '@/domains/shared/utils/formatMontant'
import { DEVISE_APP } from '@/domains/shared/constants/devise'
import { toApiDate } from '@/domains/shared/utils/dateUtils'
import { useAppToast } from '@/domains/shared/composables/useAppToast'
import { usePermissions } from '@/domains/auth/composables/usePermissions'

const MAX_INFO_LABEL_LENGTH = 80

const route = useRoute()
const router = useRouter()
const toast = useAppToast()
const { hasPermission } = usePermissions()
const { isAppMobile } = useAppMobileLayout()

const project = ref(null)
const loading = ref(true)
const error = ref(null)
const activeTab = ref('0')
const sitesTabReady = ref(false)

const projectTabItems = computed(() => [
  { value: '0', label: 'Informations', shortLabel: 'Infos' },
  { value: '1', label: `Sites (${project.value?.sites?.length ?? 0})`, shortLabel: 'Sites' },
  { value: '2', label: `Événements (${project.value?.events?.length ?? 0})`, shortLabel: 'Évén.' },
  { value: '3', label: 'Documents', shortLabel: 'Docs' },
])

const identityItems = computed(() => {
  if (!project.value) return []
  return [
    { key: 'code', label: 'Code', icon: 'pi pi-hashtag', value: project.value.code },
    { key: 'title', label: 'Titre', icon: 'pi pi-folder', value: project.value.title },
    { key: 'client', label: 'Client', icon: 'pi pi-building', value: project.value.clientTitle || project.value.clientId },
    { key: 'object', label: 'Objet', icon: 'pi pi-align-left', value: project.value.object || null, full: true },
    { key: 'status', label: 'Statut', icon: 'pi pi-flag', value: projectStatusLabel(project.value.status) },
  ]
})

const planningItems = computed(() => {
  if (!project.value) return []
  return [
    { key: 'dateDebut', label: 'Date début', icon: 'pi pi-calendar', value: formatDateFr(project.value.dateDebut) },
    { key: 'dateFin', label: 'Date fin', icon: 'pi pi-calendar-minus', value: formatDateFr(project.value.dateFin) },
    { key: 'budget', label: 'Budget', icon: 'pi pi-wallet', value: formatMontant(project.value.budget, DEVISE_APP) },
    { key: 'createdAt', label: 'Créé le', icon: 'pi pi-clock', value: formatDateTimeFr(project.value.createdAt) },
    { key: 'updatedAt', label: 'Modifié le', icon: 'pi pi-history', value: formatDateTimeFr(project.value.updatedAt) },
  ]
})

const statusSaving = ref(false)

const eventDialog = ref(false)
const eventSaving = ref(false)
const eventForm = ref({ title: '', date: null })
const eventErrors = ref({})

const sitesInfoLabels = ref([])
const chipError = ref('')
const infosSaving = ref(false)

const canUpdateProject = computed(() => hasPermission('project.projects.update'))
const canCreateEvent = computed(() => hasPermission('project.events.create'))

watch(activeTab, (tab) => {
  if (tab === '1') sitesTabReady.value = true
})

async function load() {
  loading.value = true
  error.value = null
  try {
    project.value = await getProjectDetail(route.params.id)
    syncSitesInfoLabels()
  } catch (e) {
    error.value = e.response?.data?.error || 'Impossible de charger le projet.'
  } finally {
    loading.value = false
  }
}

function syncSitesInfoLabels() {
  sitesInfoLabels.value = (project.value?.sitesInformations ?? [])
    .map((c) => c?.label)
    .filter(Boolean)
  chipError.value = ''
}

function sanitizeInfoLabel(raw) {
  return String(raw ?? '')
    .replace(/<[^>]*>/g, '')
    .replace(/[\u0000-\u001F\u007F]/g, '')
    .trim()
    .slice(0, MAX_INFO_LABEL_LENGTH)
}

function onInfoLabelsUpdate(next) {
  chipError.value = ''
  const seen = new Set()
  const cleaned = []
  for (const item of next ?? []) {
    const label = sanitizeInfoLabel(item)
    if (!label) continue
    const key = label.toLowerCase()
    if (seen.has(key)) continue
    seen.add(key)
    cleaned.push(label)
  }
  if ((next?.length ?? 0) > cleaned.length) {
    chipError.value = 'Libellé invalide, trop long ou déjà présent.'
  }
  sitesInfoLabels.value = cleaned
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

async function changeStatus(nextStatus) {
  if (!project.value || !nextStatus || nextStatus === project.value.status) return
  statusSaving.value = true
  try {
    await updateProject(project.value.id, { status: nextStatus })
    project.value.status = nextStatus
    toast.add({ severity: 'success', summary: 'Statut', detail: 'Statut du projet mis à jour.', life: 2500 })
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Statut',
      detail: e.response?.data?.error || 'Impossible de modifier le statut.',
      life: 4000,
    })
  } finally {
    statusSaving.value = false
  }
}

function openEventDialog() {
  eventForm.value = { title: '', date: new Date() }
  eventErrors.value = {}
  eventDialog.value = true
}

async function saveEvent() {
  const errs = {}
  if (!eventForm.value.title?.trim()) errs.title = 'Le titre est requis.'
  if (!eventForm.value.date) errs.date = 'La date est requise.'
  eventErrors.value = errs
  if (Object.keys(errs).length) return

  eventSaving.value = true
  try {
    await createProjectEvent(project.value.id, {
      title: eventForm.value.title.trim(),
      date: toApiDate(eventForm.value.date),
    })
    eventDialog.value = false
    toast.add({ severity: 'success', summary: 'Événement', detail: 'Événement ajouté.', life: 2500 })
    await load()
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Événement',
      detail: e.response?.data?.error || "Impossible d'ajouter l'événement.",
      life: 4000,
    })
  } finally {
    eventSaving.value = false
  }
}

async function saveSitesInformations() {
  if (!project.value) return
  infosSaving.value = true
  try {
    const sitesInformations = labelsToSitesInformations(
      sitesInfoLabels.value,
      project.value.sitesInformations ?? [],
    )
    await updateProject(project.value.id, { sitesInformations })
    project.value.sitesInformations = sitesInformations
    toast.add({
      severity: 'success',
      summary: 'Informations',
      detail: 'Informations supplémentaires enregistrées.',
      life: 2500,
    })
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Informations',
      detail: e.response?.data?.error || 'Enregistrement impossible.',
      life: 4000,
    })
  } finally {
    infosSaving.value = false
  }
}

onMounted(load)
</script>

<template>
  <section class="dashboard-page">
    <div v-if="loading" class="dashboard-page__state">Chargement…</div>
    <div v-else-if="error" class="dashboard-page__state">{{ error }}</div>

    <Card v-else-if="project" class="dashboard-panel">
      <template #title>
        <div class="detail-header">
          <div class="detail-header__main">
            <span class="detail-header__code">{{ project.code }}</span>
            <h1 class="detail-header__title">{{ project.title }}</h1>
            <div class="detail-header__meta">
              <Tag :value="projectStatusLabel(project.status)" :severity="projectStatusSeverity(project.status)" />
              <Select
                v-if="canUpdateProject"
                :model-value="project.status"
                :options="PROJECT_STATUS_OPTIONS"
                option-label="label"
                option-value="value"
                placeholder="Changer le statut"
                class="detail-header__status"
                :disabled="statusSaving"
                @update:model-value="changeStatus"
              />
            </div>
          </div>
          <div class="detail-header__actions">
            <Button
              v-if="canCreateEvent"
              label="Événement"
              icon="pi pi-calendar-plus"
              outlined
              size="small"
              @click="openEventDialog"
            />
            <Button label="Retour" icon="pi pi-arrow-left" text @click="router.push({ name: 'projects' })" />
          </div>
        </div>
      </template>
      <template #content>
        <AppMobileSegmentTabs
          v-if="isAppMobile"
          v-model="activeTab"
          :items="projectTabItems"
        />
        <Tabs v-model:value="activeTab">
          <TabList v-if="!isAppMobile">
            <Tab value="0">Informations</Tab>
            <Tab value="1">Sites ({{ project.sites?.length ?? 0 }})</Tab>
            <Tab value="2">Événements ({{ project.events?.length ?? 0 }})</Tab>
            <Tab value="3">Documents</Tab>
          </TabList>
          <TabPanels>
            <TabPanel value="0">
              <div class="detail-info">
                <section class="detail-section">
                  <h2 class="detail-section__title">Identité</h2>
                  <AppDetailInfoList :items="identityItems" />
                </section>

                <section class="detail-section">
                  <h2 class="detail-section__title">Planning & budget</h2>
                  <AppDetailInfoList :items="planningItems" />
                </section>

                <section v-if="project.lots?.length" class="detail-section">
                  <h2 class="detail-section__title">Lots</h2>
                  <ul class="detail-lots">
                    <li v-for="lot in project.lots" :key="lot.id">
                      <span class="detail-lots__code">{{ lot.code }}</span>
                      <span>{{ lot.title || '—' }}</span>
                    </li>
                  </ul>
                </section>

                <section class="detail-section detail-section--infos">
                  <div class="detail-section__head">
                    <h2 class="detail-section__title">Informations supplémentaires (sites)</h2>
                    <Button
                      v-if="canUpdateProject"
                      label="Enregistrer"
                      icon="pi pi-save"
                      size="small"
                      :loading="infosSaving"
                      @click="saveSitesInformations"
                    />
                  </div>
                  <p class="detail-section__hint">
                    Ces libellés définissent les colonnes dynamiques de l’onglet Sites.
                  </p>
                  <AutoComplete
                    :model-value="sitesInfoLabels"
                    :disabled="!canUpdateProject"
                    placeholder="Saisir un libellé puis Entrée"
                    multiple
                    :typeahead="false"
                    fluid
                    class="pst-info-chips"
                    @update:model-value="onInfoLabelsUpdate"
                  />
                  <small v-if="chipError" class="field-error">{{ chipError }}</small>
                  <small v-else class="field-hint">
                    Saisir un texte libre puis Entrée pour créer un tag. Survolez un tag pour le supprimer.
                  </small>
                </section>
              </div>
            </TabPanel>
            <TabPanel value="1">
              <!-- Mount once on first visit so frozen columns layout correctly without remounting later -->
              <ProjectSitesTable
                v-if="sitesTabReady"
                :sites="project.sites"
                :sites-informations="project.sitesInformations"
                :lots="project.lots"
                :project-id="project.id"
                :client-id="project.clientId"
                @refresh="load"
              />
            </TabPanel>
            <TabPanel value="2">
              <div class="detail-tab-toolbar">
                <Button
                  v-if="canCreateEvent"
                  label="Ajouter un événement"
                  icon="pi pi-plus"
                  size="small"
                  @click="openEventDialog"
                />
              </div>
              <DataTable v-if="project.events?.length" :value="project.events" striped-rows>
                <Column field="title" header="Titre" />
                <Column header="Date">
                  <template #body="{ data }">{{ formatDateFr(data.date) }}</template>
                </Column>
              </DataTable>
              <p v-else class="dashboard-page__state">Aucun événement.</p>
            </TabPanel>
            <TabPanel value="3">
              <TransactionAttachments :owner-id="project.id" owner-type="project" />
            </TabPanel>
          </TabPanels>
        </Tabs>
      </template>
    </Card>

    <Dialog
      v-model:visible="eventDialog"
      header="Ajouter un événement"
      modal
      style="width: min(420px, 95vw)"
    >
      <div class="event-form">
        <div class="field">
          <label>Titre <span class="required">*</span></label>
          <InputText v-model="eventForm.title" :invalid="Boolean(eventErrors.title)" fluid />
          <small v-if="eventErrors.title" class="field-error">{{ eventErrors.title }}</small>
        </div>
        <div class="field">
          <label>Date <span class="required">*</span></label>
          <DatePicker
            v-model="eventForm.date"
            date-format="dd/mm/yy"
            show-icon
            :invalid="Boolean(eventErrors.date)"
            fluid
          />
          <small v-if="eventErrors.date" class="field-error">{{ eventErrors.date }}</small>
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" text severity="secondary" @click="eventDialog = false" />
        <Button label="Ajouter" icon="pi pi-check" :loading="eventSaving" @click="saveEvent" />
      </template>
    </Dialog>
  </section>
</template>

<style scoped>
.detail-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  width: 100%;
}

.detail-header__code {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--layout-text-muted);
  text-transform: uppercase;
}

.detail-header__title {
  margin: 0.25rem 0 0.5rem;
  font-size: 1.25rem;
}

.detail-header__meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
}

.detail-header__status {
  min-width: 10rem;
}

.detail-header__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  align-items: center;
}

.detail-info {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.detail-section__title {
  margin: 0 0 0.75rem;
  font-size: 0.95rem;
  font-weight: 600;
  color: var(--layout-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.detail-section__head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.35rem;
}

.detail-section__head .detail-section__title {
  margin: 0;
}

.detail-section__hint {
  margin: 0 0 0.75rem;
  color: var(--layout-text-muted);
  font-size: 0.85rem;
}

.detail-section--infos {
  padding-top: 0.5rem;
  border-top: 1px solid var(--p-content-border-color, #e5e7eb);
}

.detail-lots {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0;
  border: 1px solid var(--layout-panel-border);
  border-radius: var(--layout-radius-sm, 0.5rem);
  overflow: hidden;
}

.detail-lots li {
  display: flex;
  gap: 0.75rem;
  padding: 0.7rem 0.9rem;
  border-bottom: 1px solid var(--layout-panel-border);
}

.detail-lots li:last-child {
  border-bottom: 0;
}

.detail-lots__code {
  font-weight: 600;
  min-width: 4rem;
  color: var(--layout-text-muted);
}

.detail-tab-toolbar {
  display: flex;
  justify-content: flex-end;
  margin-bottom: 0.75rem;
}

.event-form {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.field-hint {
  color: var(--layout-text-muted);
  font-size: 0.75rem;
}

.field-error {
  color: var(--p-red-500, #ef4444);
  font-size: 0.75rem;
}

.required {
  color: var(--p-red-500, #ef4444);
}

.pst-info-chips :deep(.p-chip-remove-icon) {
  opacity: 0;
  width: 0;
  margin: 0;
  overflow: hidden;
  transition: opacity 0.15s ease, width 0.15s ease, margin 0.15s ease;
}

.pst-info-chips :deep(.p-chip:hover .p-chip-remove-icon),
.pst-info-chips :deep(.p-chip:focus-within .p-chip-remove-icon) {
  opacity: 1;
  width: 1rem;
  margin-inline-start: 0.35rem;
}

.dashboard-page__state {
  padding: 2rem;
  text-align: center;
  color: var(--layout-text-muted);
}
</style>
