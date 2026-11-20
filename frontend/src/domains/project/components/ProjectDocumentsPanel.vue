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
import { toApiDate } from '@/domains/shared/utils/dateUtils'
import { formatDateFr } from '@/domains/shared/utils/entLabels'
import { listProjectDocuments, createProjectDocument, updateProjectDocument, deleteProjectDocument } from '@/domains/project/services/projectDocumentService'

const props = defineProps({ project: { type: Object, required: true } })
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
const editingId = ref(null)
const errors = ref({})
const printMenu = ref()
const printTarget = ref(null)
let uid = 0
const emptyLine = () => ({ _key: ++uid, description: '', quantity: 1, observation: 'RAS' })
const emptyForm = () => ({ type: null, number: '', object: '', clientId: props.project.clientId, date: new Date(), lines: [emptyLine()] })
const form = ref(emptyForm())
const printOptions = computed(() => ['classic', 'modern'].map((template) => ({
  label: template === 'classic' ? 'Modèle classique' : 'Modèle moderne',
  items: [
    { label: 'Imprimer', icon: 'pi pi-print', command: () => printSelected(template, false) },
    { label: 'Télécharger le PDF', icon: 'pi pi-file-pdf', command: () => printSelected(template, true) },
  ],
})))

async function load() {
  const projectId = props.project.id
  loading.value = true
  error.value = ''
  try {
    const data = await listProjectDocuments(projectId)
    if (projectId === props.project.id) items.value = data
  } catch (e) {
    if (projectId === props.project.id) error.value = e.response?.data?.error || 'Impossible de charger les documents.'
  } finally {
    if (projectId === props.project.id) loading.value = false
  }
}
watch(() => props.project.id, () => {
  items.value = []
  dialog.value = false
  load()
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

function openForm(item = null) {
  editingId.value = item?.id ?? null
  errors.value = {}
  form.value = item ? {
    type: item.type, number: item.number, object: item.object ?? '', clientId: item.clientId,
    date: new Date(`${item.date}T00:00:00`),
    lines: item.lines.map((line) => ({ ...line, _key: ++uid })),
  } : emptyForm()
  dialog.value = true
  loadClients()
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
  const projectId = props.project.id
  saving.value = true
  try {
    const payload = {
      type: form.value.type, number: form.value.number.trim(), clientId: form.value.clientId,
      date: toApiDate(form.value.date),
            object: form.value.object.trim() || null,
      lines: form.value.lines.map(({ description, quantity, observation }) => ({ description: description.trim(), quantity, observation: observation.trim() || 'RAS' })),
    }
    const document = editingId.value
      ? await updateProjectDocument(projectId, editingId.value, payload)
      : await createProjectDocument(projectId, payload)
    if (projectId !== props.project.id) return
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
  const projectId = props.project.id
  confirm.require({
    header: 'Supprimer le document', message: `Supprimer définitivement le bon de livraison « ${item.number} » ?`,
    icon: 'pi pi-exclamation-triangle', rejectLabel: 'Annuler', acceptLabel: 'Supprimer',
    acceptProps: { severity: 'danger' },
    accept: async () => {
      try {
        await deleteProjectDocument(projectId, item.id)
        if (projectId === props.project.id) items.value = items.value.filter((document) => document.id !== item.id)
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
  const options = { projectId: props.project.id, template, page: 'a4', orientation: 'portrait', download: true }
  if (pdf) exportDocument('delivery_note', printTarget.value.id, 'pdf', options)
  else printDocument('delivery_note', printTarget.value.id, options)
}
</script>

<template>
  <section class="project-documents" aria-label="Documents générés du projet">
    <div class="documents-toolbar">
      <div><h3>Documents du projet</h3><p>Créez vos bons de livraison et imprimez-les au format classique ou moderne.</p></div>
      <div v-if="canUpdate" class="document-actions">
        <AppDataImportButton :key="project.id" :types="['bons_livraison']" :project-id="project.id" label="Importer des documents du projet" @imported="load" />
        <Button label="Ajouter un document" icon="pi pi-plus" @click="openForm()" />
      </div>
    </div>
    <div v-if="error" role="alert" class="documents-error">
      {{ error }} <Button label="Réessayer" text size="small" @click="load" />
    </div>
    <DataTable v-else :value="items" :loading="loading" data-key="id" paginator :rows="10" :rows-per-page-options="[10, 25, 50]" scrollable>
      <template #empty>Aucun document. Ajoutez votre premier bon de livraison.</template>
      <Column field="number" header="Numéro" sortable />
      <Column header="Type"><template #body="{ data }">{{ documentTypes.find((type) => type.value === data.type)?.label || data.type }}</template></Column>
      <Column field="date" header="Date" sortable><template #body="{ data }">{{ formatDateFr(data.date) }}</template></Column>
      <Column field="clientTitle" header="Client" sortable />
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

    <Dialog v-model:visible="dialog" :header="editingId ? 'Modifier le document' : 'Ajouter un document'" modal :style="{ width: '900px' }" :breakpoints="{ '960px': '95vw' }" :closable="!saving" :close-on-escape="!saving">
      <form id="project-document-form" class="document-form" @submit.prevent="save">
        <div class="field">
          <label for="project-document-type">Type de document *</label>
          <Select v-model="form.type" input-id="project-document-type" :options="documentTypes" option-label="label" option-value="value" placeholder="Choisir le type de document" :disabled="Boolean(editingId) || saving" :invalid="Boolean(errors.type)" fluid />
          <AppFieldError :message="errors.type" />
        </div>
        <template v-if="form.type === 'delivery_note'">
          <div class="ent-form-grid">
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
          </div>
          <div class="documents-toolbar"><h3>Lignes du bon de livraison</h3><Button type="button" label="Ajouter une ligne" icon="pi pi-plus" outlined size="small" @click="form.lines.push(emptyLine())" /></div>
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
        </template>
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
.document-actions { display: flex; gap: .25rem; }
.documents-error { color: var(--p-red-500); }
.delivery-line { padding: 1rem; border: 1px solid var(--p-content-border-color); border-radius: .5rem; }
.delivery-line-fields { display: grid; grid-template-columns: minmax(0, 2fr) minmax(100px, .7fr) minmax(0, 1fr); gap: 1rem; margin-top: .5rem; }
.field { display: flex; flex-direction: column; gap: .4rem; }
@media (max-width: 640px) { .delivery-line-fields { grid-template-columns: 1fr; } }
</style>
