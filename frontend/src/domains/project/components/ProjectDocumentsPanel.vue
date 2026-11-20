<script setup>
import { computed, ref, watch } from 'vue'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import DatePicker from 'primevue/datepicker'
import Menu from 'primevue/menu'
import { useConfirm } from 'primevue/useconfirm'
import AppFieldError from '@/domains/shared/components/AppFieldError.vue'
import AppDataImportButton from '@/domains/shared/components/AppDataImportButton.vue'
import { usePermissions } from '@/domains/auth/composables/usePermissions'
import { useAppToast } from '@/domains/shared/composables/useAppToast'
import { usePrintDocument } from '@/domains/impression/composables/usePrintDocument'
import { listClients } from '@/domains/client/services/clientService'
import { listProjects } from '@/domains/project/services/projectService'
import { toApiDate } from '@/domains/shared/utils/dateUtils'
import { formatDateFr } from '@/domains/shared/utils/entLabels'
import { listProjectDocuments, createProjectDocument, updateProjectDocument, deleteProjectDocument } from '@/domains/project/services/projectDocumentService'

const props = defineProps({ project: { type: Object, default: null } })
const projectId = computed(() => props.project?.id ?? null)
const toast = useAppToast()
const confirm = useConfirm()
const { hasPermission } = usePermissions()
const { printDocument, exportDocument } = usePrintDocument()
const canUpdate = computed(() => hasPermission('project.projects.update'))
const canDelete = computed(() => hasPermission('project.projects.delete'))
const canPrint = computed(() => hasPermission('impression.documents.print'))
const documentTypes = [{ label: 'Bon de livraison', value: 'delivery_note' }]
const items = ref([])
const loading = ref(false)
const error = ref('')
const dialog = ref(false)
const saving = ref(false)
const clientsLoading = ref(false)
const clientsError = ref('')
const clientOptions = ref([])
const projects = ref([])
const projectsLoading = ref(false)
const projectsError = ref('')
const search = ref('')
const projectFilter = ref(null)
const projectOptions = computed(() => projects.value.map((project) => ({ label: `${project.code} — ${project.title}`, value: project.id })))
const projectLabel = (id) => projectOptions.value.find((option) => option.value === id)?.label || id || 'Sans projet'
const filteredItems = computed(() => items.value.filter((item) => {
  if (projectFilter.value === 'unassigned' ? item.projectId : projectFilter.value && item.projectId !== projectFilter.value) return false
  const text = search.value.trim().toLocaleLowerCase('fr')
  return !text || [item.number, item.object, item.clientTitle, projectLabel(item.projectId)].some((value) => String(value ?? '').toLocaleLowerCase('fr').includes(text))
}))
const editingId = ref(null)
const errors = ref({})
const printMenu = ref()
const printTarget = ref(null)
let uid = 0
const emptyLine = () => ({ _key: ++uid, description: '', quantity: 1, observation: 'RAS' })
const emptyForm = () => ({ type: null, projectId: projectId.value, number: '', object: '', clientId: props.project?.clientId ?? null, date: new Date(), lines: [emptyLine()] })
const form = ref(emptyForm())
const printOptions = computed(() => ['classic', 'modern'].map((template) => ({
  label: template === 'classic' ? 'Modèle classique' : 'Modèle moderne',
  items: [
    { label: 'Imprimer', icon: 'pi pi-print', command: () => printSelected(template, false) },
    { label: 'Télécharger le PDF', icon: 'pi pi-file-pdf', command: () => printSelected(template, true) },
  ],
})))

async function load() {
  const scopeId = projectId.value
  loading.value = true
  error.value = ''
  try {
    const data = await listProjectDocuments(scopeId)
    if (scopeId === projectId.value) items.value = data
  } catch (e) {
    if (scopeId === projectId.value) error.value = e.response?.data?.error || 'Impossible de charger les documents.'
  } finally {
    if (scopeId === projectId.value) loading.value = false
  }
}
watch(projectId, () => {
  items.value = []
  dialog.value = false
  load()
  if (!projectId.value) loadProjects()
}, { immediate: true })

async function loadClients() {
  clientsLoading.value = true
  clientsError.value = ''
  try {
    const clients = await listClients()
    clientOptions.value = clients.map((client) => ({ label: `${client.code} — ${client.title}`, value: client.id }))
  } catch (e) {
    clientsError.value = e.response?.data?.error || 'Impossible de charger les clients.'
  } finally {
    clientsLoading.value = false
  }
}

async function loadProjects() {
  projectsLoading.value = true
  projectsError.value = ''
  try {
    projects.value = await listProjects()
  } catch (e) {
    projectsError.value = e.response?.data?.error || 'Impossible de charger les projets. Vous pouvez enregistrer sans projet.'
  } finally {
    projectsLoading.value = false
  }
}

function selectProject(id) {
  const project = projects.value.find((item) => item.id === id)
  if (project?.clientId && !form.value.clientId) form.value.clientId = project.clientId
}

function openForm(item = null) {
  editingId.value = item?.id ?? null
  errors.value = {}
  form.value = item ? {
    type: item.type, projectId: item.projectId ?? null, number: item.number, object: item.object ?? '', clientId: item.clientId,
    date: new Date(`${item.date}T00:00:00`),
    lines: item.lines.map((line) => ({ ...line, _key: ++uid })),
  } : emptyForm()
  dialog.value = true
  loadClients()
  if (!projectId.value) loadProjects()
}

function moveLine(index, delta) {
  const target = index + delta
  const [line] = form.value.lines.splice(index, 1)
  form.value.lines.splice(target, 0, line)
}

async function save() {
  const nextErrors = {}
  if (form.value.type !== 'delivery_note') nextErrors.type = 'Sélectionnez un type de document.'
  if (!form.value.number.trim()) nextErrors.number = 'Numéro manuel requis.'
  if (!form.value.clientId) nextErrors.clientId = 'Client requis.'
  if (!toApiDate(form.value.date)) nextErrors.date = 'Date requise.'
  if (!form.value.lines.length || form.value.lines.some((line) => !line.description.trim() || !Number.isFinite(line.quantity) || line.quantity <= 0)) {
    nextErrors.lines = 'Ajoutez au moins une ligne. Chaque ligne doit avoir une désignation et une quantité strictement positive.'
  }
  errors.value = nextErrors
  if (Object.keys(nextErrors).length) return
  const scopeId = projectId.value
  saving.value = true
  try {
    const payload = {
      type: form.value.type, number: form.value.number.trim(), clientId: form.value.clientId,
      projectId: scopeId ?? form.value.projectId ?? null,
      date: toApiDate(form.value.date),
      object: form.value.object.trim() || null,
      lines: form.value.lines.map(({ description, quantity, observation }) => ({ description: description.trim(), quantity, observation: observation.trim() || 'RAS' })),
    }
    const document = editingId.value
      ? await updateProjectDocument(scopeId, editingId.value, payload)
      : await createProjectDocument(scopeId, payload)
    if (scopeId !== projectId.value) return
    items.value = [document, ...items.value.filter((item) => item.id !== document.id)]
    dialog.value = false
    toast.add({ severity: 'success', summary: 'Documents', detail: 'Document enregistré.', life: 3000 })
  } catch (e) {
    errors.value.submit = e.response?.data?.error || 'Impossible d’enregistrer le document.'
  } finally {
    saving.value = false
  }
}

function remove(item) {
  const scopeId = projectId.value
  confirm.require({
    header: 'Supprimer le document', message: `Supprimer définitivement le bon de livraison « ${item.number} » ?`,
    icon: 'pi pi-exclamation-triangle', rejectLabel: 'Annuler', acceptLabel: 'Supprimer',
    acceptProps: { severity: 'danger' },
    accept: async () => {
      try {
        await deleteProjectDocument(scopeId, item.id)
        if (scopeId === projectId.value) items.value = items.value.filter((document) => document.id !== item.id)
        toast.add({ severity: 'success', summary: 'Documents', detail: 'Document supprimé.', life: 3000 })
      } catch (e) {
        toast.add({ severity: 'error', summary: 'Documents', detail: e.response?.data?.error || 'Impossible de supprimer le document.' })
      }
    },
  })
}

function openPrint(event, item) {
  printTarget.value = item
  printMenu.value.toggle(event)
}
function printSelected(template, pdf) {
  if (!printTarget.value) return
  const options = { ...(projectId.value ? { projectId: projectId.value } : {}), template, page: 'a4', orientation: 'portrait', download: true }
  if (pdf) exportDocument('delivery_note', printTarget.value.id, 'pdf', options)
  else printDocument('delivery_note', printTarget.value.id, options)
}
</script>

<template>
  <section class="project-documents" :aria-label="project ? 'Documents du projet' : 'Tous les documents'">
    <div class="documents-toolbar">
      <div><h3>{{ project ? 'Documents du projet' : 'Tous les documents' }}</h3><p>{{ project ? 'Créez et retrouvez les documents rattachés à ce projet.' : 'Retrouvez tous vos documents, avec ou sans rattachement à un projet.' }}</p></div>
      <div v-if="canUpdate" class="document-actions">
        <AppDataImportButton v-if="project" :key="project.id" :types="['bons_livraison']" :project-id="project.id" label="Importer des documents du projet" @imported="load" />
        <Button label="Ajouter un document" icon="pi pi-plus" @click="openForm()" />
      </div>
    </div>
    <div v-if="error" role="alert" class="documents-error">
      {{ error }} <Button label="Réessayer" text size="small" @click="load" />
    </div>
    <div class="documents-filters">
      <InputText v-model="search" placeholder="Rechercher un document…" aria-label="Rechercher un document" />
      <Select v-if="!project" v-model="projectFilter" :options="[{ label: 'Sans projet', value: 'unassigned' }, ...projectOptions]" option-label="label" option-value="value" filter show-clear :loading="projectsLoading" placeholder="Tous les projets" aria-label="Filtrer par projet" />
      <span class="documents-count">{{ filteredItems.length }} document(s)</span>
    </div>
    <div v-if="!project && projectsError" role="alert" class="documents-error">{{ projectsError }} <Button label="Réessayer" text size="small" @click="loadProjects" /></div>
    <DataTable v-if="!error" :value="filteredItems" :loading="loading" data-key="id" paginator :rows="10" :rows-per-page-options="[10, 25, 50]" scrollable>
      <template #empty>Aucun document à afficher. Ajoutez un document ou modifiez vos filtres.</template>
      <Column field="number" header="Numéro" sortable />
      <Column header="Type"><template #body="{ data }">{{ documentTypes.find((type) => type.value === data.type)?.label || data.type }}</template></Column>
      <Column field="date" header="Date" sortable><template #body="{ data }">{{ formatDateFr(data.date) }}</template></Column>
      <Column field="clientTitle" header="Client" sortable />
      <Column v-if="!project" field="projectId" header="Projet" sortable><template #body="{ data }">{{ projectLabel(data.projectId) }}</template></Column>
      <Column header="Lignes"><template #body="{ data }">{{ data.lines.length }}</template></Column>
      <Column header="Actions"><template #body="{ data }">
        <div class="document-actions">
          <Button v-if="canPrint" icon="pi pi-print" text rounded aria-label="Imprimer ou télécharger le document" @click="openPrint($event, data)" />
          <Button v-if="canUpdate" icon="pi pi-pencil" text rounded aria-label="Modifier le document" @click="openForm(data)" />
          <Button v-if="canDelete" icon="pi pi-trash" text rounded severity="danger" aria-label="Supprimer le document" @click="remove(data)" />
        </div>
      </template></Column>
    </DataTable>
    <Menu ref="printMenu" :model="printOptions" popup />

    <Dialog v-model:visible="dialog" :header="editingId ? 'Modifier le document' : 'Ajouter un document'" modal :draggable="false" :style="{ width: '1040px', maxWidth: '95vw' }" :breakpoints="{ '960px': '95vw' }" :closable="!saving" :close-on-escape="!saving">
      <form id="project-document-form" class="document-form" @submit.prevent="save">
        <p class="form-hint">Les champs marqués d’un * sont obligatoires. Le rattachement à un projet est facultatif depuis la liste générale.</p>
        <fieldset class="document-section" :disabled="saving">
          <legend>Informations générales</legend>
          <div class="document-info-grid">
        <div class="field">
          <label for="project-document-type">Type de document *</label>
          <Select v-model="form.type" input-id="project-document-type" :options="documentTypes" option-label="label" option-value="value" placeholder="Choisir le type de document" :disabled="Boolean(editingId) || saving" :invalid="Boolean(errors.type)" fluid />
          <AppFieldError :message="errors.type" />
        </div>
        <div class="field">
          <label for="delivery-project">Projet (facultatif)</label>
          <InputText v-if="project" id="delivery-project" :model-value="[project.code, project.title].filter(Boolean).join(' — ')" disabled fluid />
          <Select v-else v-model="form.projectId" input-id="delivery-project" :options="projectOptions" option-label="label" option-value="value" filter show-clear :loading="projectsLoading" placeholder="Sans projet" fluid @change="selectProject($event.value)" />
          <small>{{ project ? 'Ce document est rattaché au projet consulté.' : 'Laissez vide pour créer un document indépendant.' }}</small>
          <div v-if="!project && projectsError" role="alert" class="documents-error">{{ projectsError }} <Button type="button" label="Réessayer" text size="small" @click="loadProjects" /></div>
        </div>
        <template v-if="form.type === 'delivery_note'">
            <div class="field">
              <label for="delivery-number">Numéro manuel *</label>
              <InputText id="delivery-number" v-model="form.number" :maxlength="255" :invalid="Boolean(errors.number)" placeholder="Ex. : 0101/DG/2026" fluid />
              <small>Affiché dans le champ « Bon de commande N° » du modèle classique.</small>
              <AppFieldError :message="errors.number" />
            </div>
            <div class="field">
              <label for="delivery-date">Date du BL *</label>
              <DatePicker v-model="form.date" input-id="delivery-date" date-format="dd/mm/yy" show-icon :invalid="Boolean(errors.date)" fluid />
              <AppFieldError :message="errors.date" />
            </div>
            <div class="field field--wide">
              <label for="delivery-client">Client *</label>
              <Select v-model="form.clientId" input-id="delivery-client" :options="clientOptions" option-label="label" option-value="value" filter :loading="clientsLoading" :invalid="Boolean(errors.clientId)" placeholder="Sélectionner un client" fluid />
              <AppFieldError :message="errors.clientId" />
              <div v-if="clientsError" role="alert" class="documents-error">{{ clientsError }} <Button type="button" label="Réessayer" text size="small" @click="loadClients" /></div>
            </div>
            <div class="field field--wide">
              <label for="delivery-object">Objet (facultatif)</label>
              <InputText id="delivery-object" v-model="form.object" :maxlength="500" placeholder="Objet du bon de livraison" fluid />
            </div>
        </template>
          </div>
        </fieldset>
        <fieldset v-if="form.type === 'delivery_note'" class="document-section" :disabled="saving">
          <legend>Lignes du bon de livraison</legend>
          <div class="documents-toolbar"><span class="form-hint">{{ form.lines.length }} ligne(s) · Précisez les articles ou prestations livrés.</span><Button type="button" label="Ajouter une ligne" icon="pi pi-plus" outlined size="small" @click="form.lines.push(emptyLine())" /></div>
          <AppFieldError :message="errors.lines" />
          <div v-for="(line, index) in form.lines" :key="line._key" class="delivery-line">
            <div class="documents-toolbar"><strong>Ligne {{ index + 1 }}</strong><div class="document-actions">
              <Button type="button" icon="pi pi-arrow-up" text rounded size="small" :disabled="index === 0" aria-label="Monter la ligne" @click="moveLine(index, -1)" />
              <Button type="button" icon="pi pi-arrow-down" text rounded size="small" :disabled="index === form.lines.length - 1" aria-label="Descendre la ligne" @click="moveLine(index, 1)" />
              <Button type="button" icon="pi pi-trash" text rounded size="small" severity="danger" aria-label="Supprimer la ligne" @click="form.lines.splice(index, 1)" />
            </div></div>
            <div class="delivery-line-fields">
              <div class="field"><label :for="`delivery-description-${line._key}`">Désignation *</label><Textarea :id="`delivery-description-${line._key}`" v-model="line.description" :maxlength="2000" :rows="2" auto-resize fluid /></div>
              <div class="field"><label :for="`delivery-quantity-${line._key}`">Quantité *</label><InputNumber v-model="line.quantity" :input-id="`delivery-quantity-${line._key}`" :min="0" :max-fraction-digits="4" locale="fr-FR" fluid @input="line.quantity = $event.value" /></div>
              <div class="field"><label :for="`delivery-observation-${line._key}`">Observation</label><Textarea :id="`delivery-observation-${line._key}`" v-model="line.observation" :maxlength="2000" :rows="2" auto-resize fluid /></div>
            </div>
          </div>
        </fieldset>
        <AppFieldError :message="errors.submit" />
      </form>
      <template #footer>
        <Button label="Annuler" text :disabled="saving" @click="dialog = false" />
        <Button label="Enregistrer" icon="pi pi-check" type="submit" form="project-document-form" :loading="saving" :disabled="!form.type || clientsLoading || Boolean(clientsError)" />
      </template>
    </Dialog>
  </section>
</template>

<style scoped>
.project-documents, .document-form { display: flex; flex-direction: column; gap: 1rem; }
.documents-toolbar { display: flex; justify-content: space-between; align-items: center; gap: .75rem; flex-wrap: wrap; }
.documents-toolbar h3 { margin: 0; }
.documents-toolbar p, .field small { color: var(--p-text-muted-color); margin: .35rem 0 0; }
.document-actions { display: flex; gap: .25rem; flex-wrap: wrap; }
.documents-filters { display: flex; gap: .75rem; align-items: center; flex-wrap: wrap; }
.documents-filters > :first-child { flex: 1; min-width: 12rem; }
.documents-filters :deep(.p-select) { min-width: 14rem; max-width: 100%; }
.documents-count, .form-hint { color: var(--p-text-muted-color); font-size: .875rem; }
.form-hint { margin: 0; }
.document-section { display: flex; flex-direction: column; gap: 1rem; min-width: 0; margin: 0; padding: 1rem; border: 1px solid var(--p-content-border-color); border-radius: .75rem; }
.document-section legend { padding: 0 .5rem; font-weight: 600; }
.document-info-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
.document-info-grid .field--wide { grid-column: 1 / -1; }
.document-info-grid .field, .delivery-line-fields .field { min-width: 0; }
.documents-error { color: var(--p-red-500); }
.delivery-line { padding: 1rem; border: 1px solid var(--p-content-border-color); border-radius: .5rem; }
.delivery-line-fields { display: grid; grid-template-columns: minmax(0, 2fr) minmax(100px, .7fr) minmax(0, 1fr); gap: 1rem; margin-top: .5rem; }
.field { display: flex; flex-direction: column; gap: .4rem; }
@media (max-width: 640px) {
  .document-info-grid, .delivery-line-fields { grid-template-columns: 1fr; }
  .documents-filters > * { width: 100%; }
  .document-section, .delivery-line { padding: .75rem; }
}
</style>
