<script setup>
import { computed, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue'
import { onBeforeRouteLeave, onBeforeRouteUpdate } from 'vue-router'
import { getImportConflicts } from '@/domains/shared/utils/importReview'
import { getImportDefinition } from '@/domains/shared/utils/dataImport'
import Button from 'primevue/button'
import Select from 'primevue/select'
import Message from 'primevue/message'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Checkbox from 'primevue/checkbox'
import Tag from 'primevue/tag'
import api from '@/services/api'

const props = defineProps({
  importType: { type: String, default: '' },
  allowedTypes: { type: Array, default: () => [] },
  projectId: { type: String, default: '' },
})
const emit = defineEmits(['imported', 'busy-change'])
const uid = useId()
const formats = ref([])
const type = ref(props.importType || null)
const batches = ref([])
const step = ref(1)
const error = ref('')
const busy = ref(false)
const attempted = ref(false)
const confirmed = ref([])
const fileInput = ref()
const definition = computed(() => formats.value.find((item) => item.type === type.value))
const columns = computed(() => definition.value ? [...definition.value.required, ...definition.value.optional] : [])
const rows = computed(() => batches.value.flatMap((batch, index) => (batch.report?.rows || []).map((row) => ({
  ...row, fileName: batch.file.name, key: `${index}:${row.line}`,
}))))
const readyRows = computed(() => rows.value.filter((row) => row.status === 'ready'))
const created = computed(() => rows.value.filter((row) => row.status === 'created').length)
const skipped = computed(() => rows.value.filter((row) => row.status === 'skipped').length)
const conflicts = computed(() => getImportConflicts(type.value, rows.value))
const valid = computed(() => !!definition.value && (!getImportDefinition(type.value)?.requiresProject || !!props.projectId) && batches.value.length > 0 && batches.value.every((batch) => batch.report?.valid && !batch.error) && !conflicts.value.length)
const canExecute = computed(() => valid.value && readyRows.value.length > 0 && readyRows.value.every((row) => confirmed.value.includes(row.key)) && !attempted.value && !busy.value)
const steps = ['Fichiers', 'Analyse et aperçu', 'Validations']
const statusLabels = { ready: 'Prête', skipped: 'Déjà importée', error: 'Erreur', created: 'Importée' }
const statusSeverity = { ready: 'info', skipped: 'secondary', error: 'danger', created: 'success' }
watch(busy, (value) => emit('busy-change', value), { flush: 'sync' })
onBeforeRouteLeave(() => !busy.value)
onBeforeRouteUpdate(() => !busy.value)
let mounted = true
onBeforeUnmount(() => { mounted = false })

onMounted(async () => {
  busy.value = true
  try {
    const { data } = await api.get('/data-import/formats', { timeout: 30000 })
    formats.value = data.items.filter((item) =>
      (!props.allowedTypes.length || props.allowedTypes.includes(item.type))
      && (!getImportDefinition(item.type)?.requiresProject || !!props.projectId),
    )
    type.value = props.importType || (props.allowedTypes.length ? null : formats.value[0]?.type) || null
    if (props.importType && !definition.value) error.value = 'Ce type d’import n’est pas disponible pour votre profil.'
  } catch (e) {
    error.value = e.response?.data?.error || 'Impossible de charger les formats d’import.'
  } finally {
    busy.value = false
  }
})

function resetReview() {
  confirmed.value = []
  attempted.value = false
  error.value = ''
  for (const batch of batches.value) {
    batch.report = null
    batch.error = ''
    batch.done = false
  }
}

function changeType() {
  batches.value = []
  if (fileInput.value) fileInput.value.value = ''
  resetReview()
}

function selectFiles(event) {
  const files = Array.from(event.target.files || [])
  resetReview()
  batches.value = []
  if (files.length > 10) error.value = 'Sélectionnez au maximum 10 fichiers à la fois.'
  else if (files.some((file) => !/\.(csv|zip)$/i.test(file.name))) error.value = 'Seuls les fichiers CSV et ZIP sont acceptés.'
  else if (files.some((file) => file.size > 20 * 1024 * 1024)) error.value = 'Chaque fichier doit respecter la limite de 20 Mo.'
  else batches.value = files.map((file) => ({ file, report: null, error: '', done: false }))
  event.target.value = ''
}

function removeFile(index) {
  batches.value.splice(index, 1)
  resetReview()
}

function download(blob, name) {
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = name
  link.click()
  setTimeout(() => URL.revokeObjectURL(url), 1000)
}

async function template() {
  busy.value = true
  error.value = ''
  try {
    const { data } = await api.get(`/data-import/${type.value}/template`, { responseType: 'blob', timeout: 30000 })
    download(data, `modele-${type.value}.csv`)
  } catch {
    error.value = 'Impossible de télécharger le modèle.'
  } finally {
    busy.value = false
  }
}

async function request(batch, action) {
  const body = new FormData()
  body.append('file', batch.file)
  const importDefinition = getImportDefinition(type.value)
  if (importDefinition?.requiresProject && !props.projectId) throw new Error('Projet requis pour cet import.')
  if (props.projectId && (importDefinition?.requiresProject || importDefinition?.supportsProject)) {
    body.append('projectId', props.projectId)
  }
  if (action === 'execute') body.append('digest', batch.report.digest)
  const { data } = await api.post(`/data-import/${type.value}/${action}`, body, {
    headers: { 'Content-Type': 'multipart/form-data' },
    timeout: 120000,
  })
  return data
}

function failure(e) {
  return e.response?.data?.error || (e.response?.status === 413
    ? 'Le serveur refuse cette taille de fichier. Réduisez le lot ou ses justificatifs.'
    : 'Opération non confirmée. Relancez l’analyse avant de réessayer : les lignes déjà importées seront ignorées.')
}

async function analyze() {
  if (!batches.value.length || !definition.value || busy.value || (getImportDefinition(type.value)?.requiresProject && !props.projectId)) return
  resetReview()
  step.value = 2
  busy.value = true
  try {
    for (const batch of batches.value) {
      try {
        if (!mounted) break
        batch.report = await request(batch, 'preview')
      } catch (e) {
        batch.report = e.response?.data?.rows ? e.response.data : null
        batch.error = failure(e)
      }
    }
  } finally {
    busy.value = false
  }
}

async function execute() {
  if (!canExecute.value) return
  busy.value = true
  attempted.value = true
  let changed = false
  try {
    // Each file is atomic on the server; stop at the first failure in a multi-file import.
    for (const batch of batches.value) {
      try {
        if (!mounted) break
        batch.report = await request(batch, 'execute')
        batch.done = batch.report.valid
        changed ||= batch.report.created > 0
        if (!batch.report.valid) break
      } catch (e) {
        if (e.response?.data?.rows) batch.report = e.response.data
        batch.error = failure(e)
        error.value = 'L’import a été interrompu. Les fichiers précédemment importés restent enregistrés. Relancez l’analyse pour vérifier le résultat avant toute nouvelle tentative.'
        // A lost response may hide a successful commit; refresh the table in either case.
        changed = true
        break
      }
    }
  } finally {
    busy.value = false
    if (changed) emit('imported', { type: type.value, created: created.value })
  }
}

function downloadReport() {
  const cell = (value) => `"${String(value ?? '').replace(/^[=+@-]/, "'$&").replaceAll('"', '""')}"`
  const reportRows = [['fichier', 'ligne', 'id_import', 'statut', 'message', 'id_logiciel', 'pieces_jointes'],
    ...rows.value.map((row) => [row.fileName, row.line, row.idImport, row.status, row.message, row.entityId, row.attachments]),
    ...batches.value.filter((batch) => batch.error).map((batch) => [batch.file.name, '', '', 'error', batch.error, '', ''])]
  download(new Blob(['\uFEFF' + reportRows.map((row) => row.map(cell).join(';')).join('\r\n')], { type: 'text/csv;charset=utf-8' }), `bilan-import-${type.value}.csv`)
}
</script>

<template>
  <section class="data-import" :aria-busy="busy" :aria-label="`Import ${definition?.label || 'de données'}`">
    <ol class="import-steps" aria-label="Étapes de l’import">
      <li v-for="(label, index) in steps" :key="label" :class="{ 'is-active': step === index + 1, 'is-complete': step > index + 1 }" :aria-current="step === index + 1 ? 'step' : undefined">
        <span class="import-step-number">{{ index + 1 }}</span><span>{{ label }}</span>
      </li>
    </ol>
    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
    <Message v-if="!busy && !formats.length && !error" severity="info" :closable="false">Votre profil ne dispose d’aucun droit de création pour les types importables.</Message>

    <div v-if="step === 1 && !importType && formats.length" class="import-field">
      <label :for="`${uid}-type`">{{ allowedTypes.length ? 'Type de document à importer *' : 'Type de données' }}</label>
      <Select v-model="type" :input-id="`${uid}-type`" :options="formats" option-label="label" option-value="type" :placeholder="allowedTypes.length ? 'Choisir le type de document' : 'Choisir le type de données'" :disabled="busy" @update:model-value="changeType" />
      <small v-if="!definition">Choisissez un type pour accéder à son modèle et à la sélection des fichiers.</small>
    </div>
    <template v-if="definition">
      <template v-if="step === 1">
        <div class="import-heading"><h3>Choisir les fichiers à importer</h3><p>Téléchargez le modèle, complétez-le puis sélectionnez vos fichiers. Aucune donnée n’est créée pendant l’analyse.</p></div>
        <div class="import-actions">

          <Button label="Télécharger le modèle CSV" icon="pi pi-download" severity="secondary" outlined :disabled="busy" @click="template" />
        </div>
        <details class="import-guide">
          <summary>Format attendu et conseils — {{ definition.label }}</summary>
          <p><strong>Colonnes obligatoires :</strong> {{ definition.required.join(', ') }}.</p>
          <p><strong>Colonnes facultatives :</strong> {{ definition.optional.join(', ') }}.</p>
          <ul>
            <li>CSV UTF-8, séparateur point-virgule (;), première ligne contenant les noms de colonnes. Export possible depuis Excel.</li>
            <li>Dates : AAAA-MM-JJ. Montants : 12500 ou 12500,50, sans séparateur de milliers, dans la devise du logiciel.</li>
            <li><code>id_import</code> : identifiant stable et unique par type. Les lignes identiques déjà importées sont ignorées ; un contenu modifié avec le même identifiant est refusé.</li>
            <li>Références : code du client/site ou email du prestataire, ou <code>@id_import</code> d’un import précédent. Importez d’abord les références nécessaires.</li>
            <li v-if="type === 'prestations'">Prestations initialement non payées. <code>statut_travail</code> : pending, in_progress ou completed (pending par défaut).</li>
            <li v-if="type === 'depenses'">Dépenses enregistrées comme terminées. Catégorie libre (100 caractères maximum).</li>
            <li v-if="type === 'revenus'"><code>statut</code> : pending, completed ou cancelled (pending par défaut). Catégorie libre.</li>
            <li v-if="type === 'sites'">Latitude et longitude doivent être renseignées ensemble, en degrés décimaux.</li>
            <li v-if="type === 'equipements'"><code>unite</code> : unit, lot, meter, m2, m3, kg, liter, piece, box, set, hour ou day. Aucun mouvement de stock créé.</li>
          </ul>
          <template v-if="type === 'bons_livraison'">
            <h4>Une ligne CSV = un bon de livraison</h4>
            <p>{{ props.projectId ? 'Les documents sont rattachés au projet ouvert.' : 'Les documents seront importés sans projet. Vous pourrez les rattacher ensuite depuis leur formulaire de modification.' }} <code>numero</code> est le numéro manuel ; <code>client_code</code> désigne un client existant ou <code>@id_import</code> d’un client déjà importé. <code>objet</code> est facultatif.</p>
            <p><code>lignes</code> contient une liste JSON non vide de désignations, quantités strictement positives et observations facultatives (RAS par défaut), par exemple avant échappement CSV :</p>
            <pre>[{"description":"Matériel livré","quantity":2,"observation":"RAS"}]</pre>
            <p>Les guillemets à l’intérieur d’une cellule CSV doivent être doublés. L’identifiant <code>id_import</code> est unique par projet et par type ; aucune fiche existante n’est modifiée.</p>
          </template>
          <template v-if="type === 'factures'">
            <h4>Une ligne CSV = une facture</h4>
            <p><code>client_code</code> désigne un client existant. La numérotation est automatique. Aucun paiement n’est créé.</p>
            <p><code>lignes</code> contient une liste JSON, par exemple (avant échappement CSV par Excel) :</p>
            <pre>[{"description":"Prestation","quantity":1,"unitPrice":12500,"unit":"unit"}]</pre>
            <p><code>statut</code> : draft (défaut), quote ou invoiced. <code>tva_active</code> : true/false ou 1/0 (false par défaut). <code>taux_tva</code> : de 0 à 100 (18 par défaut).</p>
            <p><code>conditions_paiement</code> : liste JSON de textes, par exemple <code>["Paiement à 30 jours"]</code>. Les guillemets à l’intérieur d’une cellule CSV doivent être doublés.</p>
          </template>
          <template v-if="type === 'depenses' || type === 'revenus'">
            <h4>Joindre des justificatifs</h4>
            <p>ZIP contenant <code>donnees.csv</code> à la racine et un dossier <code>pieces/</code>. Dans <code>pieces_jointes</code>, séparez les chemins par <code>|</code>.</p>
            <pre>pieces/recu-001.pdf|pieces/photo-001.jpg</pre>
            <p>PDF, JPG, PNG, WEBP, DOCX, XLSX ou TXT. Le droit d’ajouter des documents est nécessaire.</p>
          </template>
          <p>Par fichier : 500 lignes, CSV de 2 Mo, envoi de 20 Mo, justificatif de 10 Mo, ZIP décompressé de 50 Mo. Aucune fiche existante n’est modifiée.</p>
        </details>
        <div class="import-upload">
          <i class="pi pi-cloud-upload" aria-hidden="true" />
          <div class="import-field">
            <label :for="`${uid}-files`">Sélectionner des fichiers CSV ou ZIP</label>
            <input :id="`${uid}-files`" ref="fileInput" type="file" accept=".csv,.zip" multiple :disabled="busy" :aria-describedby="`${uid}-limits`" @change="selectFiles" />
            <small :id="`${uid}-limits`">10 fichiers maximum, 20 Mo par fichier. Une nouvelle sélection remplace la précédente.</small>
          </div>
        </div>
        <ul v-if="batches.length" class="import-files">
          <li v-for="(batch, index) in batches" :key="index">
            <span><i class="pi pi-file" aria-hidden="true" /> {{ batch.file.name }} <small>({{ (batch.file.size / 1024).toFixed(1) }} Ko)</small></span>
            <Button icon="pi pi-times" text rounded severity="secondary" :aria-label="`Retirer ${batch.file.name}`" :disabled="busy" @click="removeFile(index)" />
          </li>
        </ul>
      </template>

      <template v-else>
        <div class="import-heading">
          <h3>{{ step === 2 ? 'Analyse et aperçu des données' : attempted ? 'Résultat de l’import' : 'Valider chaque ligne' }}</h3>
          <p v-if="step === 2">Vérifiez les valeurs et le résultat du contrôle pour chaque ligne. Corrigez les fichiers en erreur avant de poursuivre.</p>
          <p v-else-if="!attempted">Cochez chaque ligne prête après vérification. Toutes les lignes doivent être validées pour autoriser l’import.</p>
        </div>
        <div class="import-summary" aria-live="polite" role="status">
          <Tag :value="`${batches.length} fichier(s)`" severity="secondary" />
          <Tag :value="`${readyRows.length} prête(s)`" severity="info" />
          <Tag :value="`${skipped} déjà importée(s)`" severity="secondary" />
          <Tag v-if="created" :value="`${created} créée(s)`" severity="success" />
          <Tag v-if="rows.some(row => row.status === 'error')" :value="`${rows.filter(row => row.status === 'error').length} erreur(s)`" severity="danger" />
        </div>
        <Message v-for="(batch, index) in batches.filter(item => item.error)" :key="index" severity="error" :closable="false">{{ batch.file.name }} : {{ batch.error }}</Message>
        <Message v-for="conflict in conflicts" :key="`${conflict.field}:${conflict.value}`" severity="error" :closable="false">{{ conflict.message }} Corrigez les fichiers avant de poursuivre.</Message>
        <Message v-if="rows.some(row => row.status === 'error')" severity="error" :closable="false">Corrigez chaque ligne en erreur dans le fichier source, puis sélectionnez le fichier corrigé et relancez l’analyse. Aucun fichier n’est importé tant que l’analyse contient une erreur.</Message>
        <Message v-if="attempted && batches.every(batch => batch.done)" severity="success" :closable="false">Import terminé : {{ created }} ligne(s) créée(s), {{ skipped }} ligne(s) déjà importée(s). Vous pouvez télécharger le bilan.</Message>
        <DataTable v-if="rows.length" :value="rows" paginator :rows="10" :rows-per-page-options="[10, 25, 50]" scrollable data-key="key" size="small" :loading="busy" class="import-preview" :table-style="{ minWidth: '48rem' }">
          <Column v-if="step === 3 && !attempted" header="Validation" frozen style="min-width: 8rem">
            <template #body="{ data }">
              <Checkbox v-if="data.status === 'ready'" v-model="confirmed" :value="data.key" :input-id="`${uid}-${data.key}`" :aria-label="`Valider la ligne ${data.line} de ${data.fileName} (${data.idImport})`" :disabled="busy" />
              <span v-else class="import-muted">Non requise</span>
            </template>
          </Column>
          <Column field="fileName" header="Fichier" style="min-width: 10rem" />
          <Column field="line" header="Ligne" />
          <Column header="Statut" style="min-width: 10rem"><template #body="{ data }"><Tag :value="statusLabels[data.status] || data.status" :severity="statusSeverity[data.status] || 'secondary'" /></template></Column>
          <Column field="message" header="Contrôle" style="min-width: 14rem" />
          <Column v-for="column in columns" :key="column" :header="column" style="min-width: 10rem; max-width: 24rem">
            <template #body="{ data }"><span class="import-cell">{{ data.data?.[column] || '—' }}</span></template>
          </Column>
          <Column v-if="type === 'depenses' || type === 'revenus'" field="attachments" header="Justificatifs" />
          <Column v-if="attempted" field="entityId" header="Identifiant créé" />
        </DataTable>
        <p v-if="step === 3 && !attempted" class="import-muted" aria-live="polite">{{ readyRows.filter(row => confirmed.includes(row.key)).length }} / {{ readyRows.length }} ligne(s) validée(s). Les lignes déjà importées sont ignorées automatiquement.</p>
        <Message v-if="step === 3 && !attempted" severity="info" :closable="false">Les fichiers sont importés dans l’ordre de sélection. Chaque fichier est enregistré intégralement ou annulé ; si un fichier échoue, les précédents restent enregistrés et les suivants ne sont pas traités.</Message>
      </template>

      <div class="import-footer">
        <div class="import-actions">
          <Button v-if="step > 1" :label="attempted ? 'Nouvel import' : 'Précédent'" icon="pi pi-arrow-left" severity="secondary" text :disabled="busy" @click="step = attempted ? 1 : step - 1" />
          <Button v-if="rows.length && step > 1" label="Télécharger le bilan" icon="pi pi-download" severity="secondary" text :disabled="busy" @click="downloadReport" />
        </div>
        <Button v-if="step === 1" label="Analyser les fichiers" icon="pi pi-search" :loading="busy" :disabled="!batches.length || busy" @click="analyze" />
        <Button v-else-if="step === 2" label="Passer aux validations" icon="pi pi-arrow-right" icon-pos="right" :disabled="!valid || !readyRows.length || busy" @click="step = 3" />
        <Button v-else-if="!attempted" :label="`Importer ${readyRows.length} ligne(s)`" icon="pi pi-upload" :disabled="!canExecute" :loading="busy" @click="execute" />
        <Button v-else label="Relancer l’analyse" icon="pi pi-refresh" severity="secondary" :disabled="busy" @click="analyze" />
      </div>
    </template>
  </section>
</template>

<style scoped>
.data-import { display: flex; flex-direction: column; gap: 1.25rem; min-width: 0; padding-block: .5rem; }
.import-heading h3 { margin: 0 0 .5rem; font-size: 1.125rem; }
.import-heading p, .import-muted { color: var(--p-text-muted-color); margin: 0; line-height: 1.6; }
.import-steps { list-style: none; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .75rem; padding: 0 0 1.25rem; margin: 0; border-bottom: 1px solid var(--p-content-border-color); }
.import-steps li { display: flex; align-items: center; gap: .625rem; color: var(--p-text-muted-color); font-size: .875rem; }
.import-step-number { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; width: 2rem; height: 2rem; border-radius: 50%; background: var(--p-content-hover-background); border: 1px solid var(--p-content-border-color); font-weight: 600; }
.import-steps .is-active { color: var(--p-primary-color); font-weight: 600; }
.is-active .import-step-number, .is-complete .import-step-number { background: var(--p-primary-color); color: var(--p-primary-contrast-color); border-color: var(--p-primary-color); }
.import-actions, .import-summary { display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; }
.import-field { display: flex; flex-direction: column; gap: .625rem; min-width: 0; }
.import-field label { font-weight: 500; }
.import-field small, .import-files small { color: var(--p-text-muted-color); line-height: 1.5; }
.import-guide { padding: 1rem 1.25rem; border: 1px solid var(--p-content-border-color); border-radius: .75rem; overflow-wrap: anywhere; }
.import-guide summary { cursor: pointer; font-weight: 500; line-height: 1.6; }
.import-guide p, .import-guide li { line-height: 1.6; margin-block: .625rem; }
.import-guide ul { padding-left: 1.25rem; }
.import-guide h4 { margin-bottom: .5rem; }
.import-guide pre { white-space: pre-wrap; overflow-wrap: anywhere; background: var(--p-content-hover-background); padding: .75rem; border-radius: .5rem; }
.import-upload { display: flex; gap: 1.25rem; align-items: center; padding: 1.5rem; border: 1px dashed var(--p-content-border-color); border-radius: .75rem; background: var(--p-content-background); }
.import-upload > i { color: var(--p-primary-color); font-size: 2rem; }
input[type=file] { max-width: 100%; min-width: 0; font: inherit; font-size: .875rem; }
input[type=file]::file-selector-button { padding: .5rem .75rem; margin-right: .75rem; border: 1px solid var(--p-content-border-color); border-radius: .375rem; background: var(--p-content-hover-background); color: var(--p-text-color); cursor: pointer; }
.import-files { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: .5rem; }
.import-files li { display: flex; align-items: center; justify-content: space-between; gap: .75rem; border: 1px solid var(--p-content-border-color); border-radius: .5rem; padding: .5rem .75rem; }
.import-files li > span { min-width: 0; overflow-wrap: anywhere; }
.import-preview { min-width: 0; max-width: 100%; }
.import-cell { display: block; white-space: pre-wrap; overflow-wrap: anywhere; max-height: 10rem; overflow-y: auto; }
.import-footer { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; padding-top: 1.25rem; border-top: 1px solid var(--p-content-border-color); }
@media (max-width: 640px) {
  .import-steps { gap: .5rem; }
  .import-steps li { flex-direction: column; text-align: center; font-size: .75rem; }
  .import-upload { padding: 1rem; align-items: flex-start; }
  .import-upload > i { display: none; }
  .import-footer { align-items: stretch; flex-direction: column; }
  .import-footer > .import-actions { justify-content: space-between; }
}
</style>
