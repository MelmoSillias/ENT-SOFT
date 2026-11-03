<script setup>
import { computed, onMounted, ref } from 'vue'
import Button from 'primevue/button'
import Select from 'primevue/select'
import Message from 'primevue/message'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import api from '@/services/api'

const formats = ref([])
const type = ref(null)
const file = ref(null)
const report = ref(null)
const error = ref('')
const busy = ref(false)
const done = ref(false)
const definition = computed(() => formats.value.find((item) => item.type === type.value))
const canExecute = computed(() => report.value?.valid && report.value.ready > 0 && !done.value && !busy.value)

onMounted(async () => {
  busy.value = true
  try {
    const { data } = await api.get('/data-import/formats')
    formats.value = data.items
    type.value = data.items[0]?.type ?? null
  } catch (e) {
    error.value = e.response?.data?.error || 'Impossible de charger les formats d’import.'
  } finally {
    busy.value = false
  }
})

function reset() {
  report.value = null
  error.value = ''
  done.value = false
}

function selectFile(event) {
  reset()
  file.value = event.target.files?.[0] ?? null
  if (file.value?.size > 20 * 1024 * 1024) {
    error.value = 'Le fichier dépasse la limite de 20 Mo.'
    file.value = null
    event.target.value = ''
  }
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
    const { data } = await api.get(`/data-import/${type.value}/template`, { responseType: 'blob' })
    download(data, `modele-${type.value}.csv`)
  } catch {
    error.value = 'Impossible de télécharger le modèle.'
  } finally {
    busy.value = false
  }
}

async function run(action) {
  if (!file.value || !type.value || busy.value) return
  busy.value = true
  error.value = ''
  const body = new FormData()
  body.append('file', file.value)
  if (action === 'execute') body.append('digest', report.value.digest)
  else reset()
  try {
    const { data } = await api.post(`/data-import/${type.value}/${action}`, body, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    report.value = data
    done.value = action === 'execute' && data.valid
  } catch (e) {
    if (e.response?.data?.rows) report.value = e.response.data
    else report.value = null
    error.value = e.response?.data?.error || (e.response?.status === 413
      ? 'Le serveur refuse cette taille de fichier. Réduisez le lot ou ses justificatifs.'
      : 'L’import n’a pas pu être confirmé. Relancez le contrôle : les identifiants déjà importés seront ignorés.')
  } finally {
    busy.value = false
  }
}

function downloadReport() {
  // Quote every cell and neutralize spreadsheet formulas in user-provided identifiers/messages.
  const cell = (value) => `"${String(value ?? '').replace(/^[=+@-]/, "'$&").replaceAll('"', '""')}"`
  const rows = [['ligne', 'id_import', 'statut', 'message', 'id_logiciel', 'pieces_jointes'],
    ...report.value.rows.map((row) => [row.line, row.idImport, row.status, row.message, row.entityId, row.attachments])]
  download(new Blob(['\uFEFF' + rows.map((row) => row.map(cell).join(';')).join('\r\n')], { type: 'text/csv;charset=utf-8' }), `bilan-import-${type.value}.csv`)
}
</script>

<template>
  <section class="data-import" aria-labelledby="import-title" :aria-busy="busy">
    <div>
      <h2 id="import-title">Importer des données</h2>
      <p>Choisissez un type, remplissez son modèle, puis contrôlez le fichier avant de l’importer.</p>
    </div>
    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
    <Message v-if="!busy && !formats.length && !error" severity="info" :closable="false">
      Votre profil ne dispose d’aucun droit de création pour les types importables.
    </Message>
    <template v-if="formats.length">
      <div class="import-actions">
        <div class="import-field">
          <label for="import-type">Type de données</label>
          <Select v-model="type" input-id="import-type" :options="formats" option-label="label" option-value="type" :disabled="busy" @update:model-value="reset" />
        </div>
        <Button label="Télécharger le modèle CSV" icon="pi pi-download" severity="secondary" :disabled="busy || !type" @click="template" />
      </div>
      <div v-if="definition" class="import-guide">
        <p><strong>Colonnes obligatoires :</strong> {{ definition.required.join(', ') }}.</p>
        <p><strong>Colonnes facultatives :</strong> {{ definition.optional.join(', ') }}.</p>
        <ul>
          <li>CSV UTF-8, séparateur point-virgule (;), première ligne contenant les noms de colonnes. Export possible depuis Excel.</li>
          <li>Dates : AAAA-MM-JJ. Montants : 12500 ou 12500,50, sans séparateur de milliers, dans la devise du logiciel.</li>
          <li><code>id_import</code> : identifiant stable et unique par type (ex. DEP-2026-001). Une ligne identique déjà importée sera ignorée. Un contenu modifié avec le même identifiant sera signalé.</li>
          <li>Références : code du client/site ou email du prestataire. Vous pouvez aussi utiliser <code>@id_import</code> d’un import précédent. Importez d’abord les clients, prestataires et sites nécessaires.</li>
          <li v-if="type === 'prestations'">Prestations initialement non payées. <code>statut_travail</code> : pending, in_progress ou completed (pending par défaut).</li>
          <li v-if="type === 'depenses'">Dépenses enregistrées comme terminées. La catégorie est un texte libre (100 caractères maximum).</li>
          <li v-if="type === 'revenus'"><code>statut</code> : pending, completed ou cancelled (pending par défaut). La catégorie est un texte libre.</li>
          <li v-if="type === 'sites'">Latitude et longitude doivent être renseignées ensemble, en degrés décimaux.</li>
          <li v-if="type === 'equipements'"><code>unite</code> : unit, lot, meter, m2, m3, kg, liter, piece, box, set, hour ou day. Aucun mouvement de stock n’est créé.</li>
        </ul>
        <template v-if="type === 'depenses' || type === 'revenus'">
          <h3>Joindre des images ou des documents</h3>
          <p>Créez un ZIP contenant <code>donnees.csv</code> à la racine et un dossier <code>pieces/</code> avec vos justificatifs.</p>
          <p>Dans la cellule <code>pieces_jointes</code>, indiquez les chemins séparés par <code>|</code>, par exemple :</p>
          <pre>pieces/recu-001.pdf|pieces/photo-001.jpg</pre>
          <p>Formats : PDF, JPG, PNG, WEBP, DOCX, XLSX et TXT. Chaque justificatif sera rattaché à sa transaction. Le droit d’ajouter des documents est nécessaire.</p>
        </template>
        <p>Limites : 500 lignes, CSV de 2 Mo, fichier envoyé de 20 Mo, justificatif de 10 Mo, ZIP décompressé de 50 Mo. Aucune fiche existante n’est modifiée.</p>
      </div>
      <div class="import-actions">
        <div class="import-field">
          <label for="import-file">Fichier CSV ou ZIP</label>
          <input id="import-file" type="file" accept=".csv,.zip" :disabled="busy" @change="selectFile" />
        </div>
        <Button label="Contrôler le fichier" icon="pi pi-check-circle" :loading="busy" :disabled="!file || busy" @click="run('preview')" />
      </div>
      <template v-if="report">
        <Message :severity="report.errors ? 'error' : 'success'" :closable="false">
          {{ report.ready }} ligne(s) prête(s), {{ report.skipped }} déjà importée(s), {{ report.errors }} erreur(s), {{ report.created }} créée(s).
          <span v-if="report.errors"> Corrigez les erreurs puis relancez le contrôle. Aucune ligne de ce lot n’a été créée.</span>
          <span v-else-if="done"> Import terminé.<template v-if="type === 'depenses' || type === 'revenus'"> Les justificatifs sont consultables dans les transactions.</template></span>
        </Message>
        <div class="import-actions">
          <Button :label="`Importer ${report.ready} ligne(s)`" icon="pi pi-upload" :disabled="!canExecute" :loading="busy" @click="run('execute')" />
          <Button label="Télécharger le bilan" icon="pi pi-download" severity="secondary" :disabled="busy" @click="downloadReport" />
        </div>
        <DataTable :value="report.rows" paginator :rows="10" :rows-per-page-options="[10, 25, 50]" scrollable data-key="line" size="small">
          <Column field="line" header="Ligne" />
          <Column field="idImport" header="Identifiant d’import" />
          <Column field="message" header="Résultat" />
          <Column field="attachments" header="Justificatifs" />
          <Column field="entityId" header="Identifiant dans le logiciel" />
        </DataTable>
      </template>
    </template>
  </section>
</template>

<style scoped>
.data-import { display: flex; flex-direction: column; gap: 1.25rem; padding-top: 1rem; min-width: 0; }
.data-import h2, .data-import h3 { margin: 0 0 .5rem; }
.data-import p { margin: .5rem 0; }
.import-actions { display: flex; flex-wrap: wrap; gap: 1rem; align-items: end; }
.import-field { display: flex; flex-direction: column; gap: .5rem; min-width: min(100%, 16rem); }
.import-guide { padding: 1rem; border: 1px solid var(--p-content-border-color); border-radius: .75rem; overflow-wrap: anywhere; }
.import-guide ul { padding-left: 1.25rem; }
.import-guide li { margin-bottom: .5rem; }
.import-guide pre { white-space: pre-wrap; overflow-wrap: anywhere; }
input[type=file] { max-width: 100%; }
</style>
