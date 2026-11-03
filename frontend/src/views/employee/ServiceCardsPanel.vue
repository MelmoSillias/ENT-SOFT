<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import Button from 'primevue/button'
import Card from 'primevue/card'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Checkbox from 'primevue/checkbox'
import Message from 'primevue/message'
import Tag from 'primevue/tag'
import { usePermissions } from '@/domains/auth/composables/usePermissions'
import { useAgencyBrandStore } from '@/domains/configuration/stores/agencyBrand'
import { listEmployees } from '@/domains/employee/services/employeeService'
import { listPrestataires } from '@/domains/employee/services/prestataireService'
import { listRoles } from '@/domains/access/services/roleService'
import { personDisplayName, personPhotoUrl } from '@/domains/shared/utils/personDisplay'
import { CARD_TEMPLATES, buildServiceCardsDocument, cardsPerPage, renderServiceCard } from '@/domains/employee/utils/serviceCards'
import cardCss from '@/domains/employee/utils/serviceCards.css?raw'
import '@/domains/employee/utils/serviceCards.css'

const { hasPermission } = usePermissions()
const agency = useAgencyBrandStore()
const people = ref([])
const selected = ref([])
const loading = ref(false)
const printing = ref(false)
const error = ref('')
const printError = ref('')
const search = ref('')
const kind = ref('all')
const first = ref(0)
const previewKey = ref(null)
const options = ref({ template: 'ocean', orientation: 'landscape', withPhoto: true, medium: 'paper', cutMarks: true })
const kindOptions = computed(() => [
  { label: 'Toutes les personnes', value: 'all' },
  ...(hasPermission('employee.employees.view') ? [{ label: 'Employés', value: 'employee' }] : []),
  ...(hasPermission('employee.prestataires.view') ? [{ label: 'Prestataires', value: 'prestataire' }] : []),
])
const orientationOptions = [{ label: 'Paysage', value: 'landscape' }, { label: 'Portrait', value: 'portrait' }]
const mediumOptions = [{ label: 'Papier · planches A4', value: 'paper' }, { label: 'PVC · une carte par page', value: 'pvc' }]
const photoOptions = [{ label: 'Avec photo', value: true }, { label: 'Sans photo', value: false }]
const filtered = computed(() => {
  const query = search.value.trim().toLocaleLowerCase('fr')
  return people.value.filter((person) => (kind.value === 'all' || person.kind === kind.value)
    && (!query || [person.displayName, person.roleLabel, person.email, person.phone].join(' ').toLocaleLowerCase('fr').includes(query)))
})
const missingPhotos = computed(() => options.value.withPhoto ? selected.value.filter((person) => !person.photoUrl) : [])
const pageCount = computed(() => Math.ceil(selected.value.length / cardsPerPage(options.value.orientation, options.value.medium)))
const previewPerson = computed(() => people.value.find((person) => person.key === previewKey.value) || selected.value[0] || filtered.value[0])
const preview = computed(() => previewPerson.value ? renderServiceCard(previewPerson.value, options.value, agency.branding) : '')
const hiddenSelectionCount = computed(() => selected.value.filter((person) => !filtered.value.some((item) => item.key === person.key)).length)
const allFilteredSelected = computed(() => {
  const keys = new Set(selected.value.map((person) => person.key))
  return filtered.value.length > 0 && filtered.value.every((person) => keys.has(person.key))
})
watch([search, kind], () => { first.value = 0 })

async function load() {
  loading.value = true
  error.value = ''
  try {
    const [employees, prestataires, rolesResult] = await Promise.all([
      hasPermission('employee.employees.view') ? listEmployees() : [],
      hasPermission('employee.prestataires.view') ? listPrestataires() : [],
      listRoles().catch(() => []),
    ])
    const roles = new Map(rolesResult.map((role) => [role.code, role.libelle]))
    const normalize = (person, personKind) => ({ ...person, key: `${personKind}:${person.id}`, kind: personKind,
      displayName: personDisplayName(person), photoUrl: personPhotoUrl(person),
      roleLabel: personKind === 'employee' ? (roles.get(person.roleCode) || person.roleCode || 'Employé') : 'Prestataire',
    })
    people.value = [...employees.map((person) => normalize(person, 'employee')), ...prestataires.map((person) => normalize(person, 'prestataire'))]
      .sort((left, right) => left.displayName.localeCompare(right.displayName, 'fr'))
    const keys = new Set(selected.value.map((person) => person.key))
    selected.value = people.value.filter((person) => keys.has(person.key))
  } catch (cause) {
    error.value = cause.response?.data?.error || 'Impossible de charger les personnes. Réessayez avant de lancer une impression.'
  } finally {
    loading.value = false
  }
}

function selectFiltered() {
  const keys = new Set(selected.value.map((person) => person.key))
  selected.value = [...selected.value, ...filtered.value.filter((person) => !keys.has(person.key))]
}

function toggleFilteredSelection({ checked }) {
  if (checked) selectFiltered()
  else {
    const keys = new Set(filtered.value.map((person) => person.key))
    selected.value = selected.value.filter((person) => !keys.has(person.key))
  }
}

function excludeMissingPhotos() {
  selected.value = selected.value.filter((person) => person.photoUrl)
}

let printFrame
function cleanupPrint() {
  printFrame?.remove()
  printFrame = null
}

async function printCards() {
  if (loading.value || error.value || !selected.value.length || missingPhotos.value.length || printing.value) return
  printing.value = true
  printError.value = ''
  cleanupPrint()
  try {
    const html = buildServiceCardsDocument(selected.value, options.value, agency.branding, cardCss)
    printFrame = document.createElement('iframe')
    printFrame.title = 'Impression des cartes de service'
    printFrame.style.cssText = 'position:fixed;left:-10000px;top:0;width:1px;height:1px;border:0;'
    document.body.appendChild(printFrame)
    const printWindow = printFrame.contentWindow
    const printDocument = printWindow.document
    printDocument.open()
    printDocument.write(html)
    printDocument.close()
    const frame = printFrame
    await Promise.all(Array.from(printDocument.images, (img) => new Promise((resolve, reject) => {
      const timer = setTimeout(() => { finish(false) }, 15000)
      const finish = (ok) => {
        clearTimeout(timer)
        img.onload = null
        img.onerror = null
        if (ok) resolve()
        else reject(new Error('Une photo ou le logo ne se charge pas. Vérifiez les images puis réessayez.'))
      }
      img.onload = () => finish(img.naturalWidth > 0)
      img.onerror = () => finish(false)
      if (img.complete) finish(img.naturalWidth > 0)
    })))
    await printDocument.fonts.ready
    await new Promise((resolve) => window.requestAnimationFrame(() => window.requestAnimationFrame(resolve)))
    if (printFrame !== frame) return
    printWindow.addEventListener('afterprint', cleanupPrint, { once: true })
    printWindow.focus()
    printWindow.print()
  } catch (cause) {
    printError.value = cause.message || 'Impossible de préparer l’impression.'
    cleanupPrint()
  } finally {
    printing.value = false
  }
}

onMounted(load)
onBeforeUnmount(cleanupPrint)
</script>

<template>
  <div class="service-cards-panel">
    <header class="cards-title">
      <div><h2>Cartes de service</h2><p>Préparez les cartes de vos employés et prestataires, à l’unité ou en groupe.</p></div>
      <Button label="Actualiser" icon="pi pi-refresh" severity="secondary" :loading="loading" :disabled="printing" @click="load" />
    </header>
    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
    <div class="cards-layout">
      <Card>
        <template #title>1. Choisir les personnes</template>
        <template #content>
          <div class="cards-filters">
            <InputText v-model="search" placeholder="Nom, fonction, email…" aria-label="Rechercher une personne" />
            <Select v-model="kind" :options="kindOptions" option-label="label" option-value="value" aria-label="Type de personne" />
          </div>
          <div class="cards-selection-bar">
            <strong aria-live="polite">{{ selected.length }} sélectionnée(s)</strong>
            <Button label="Sélectionner tous les résultats" text size="small" :disabled="loading || !!error || !filtered.length" @click="selectFiltered" />
            <Button label="Tout désélectionner" text size="small" severity="secondary" :disabled="!selected.length" @click="selected = []" />
          </div>
          <Message v-if="hiddenSelectionCount" severity="info" :closable="false">{{ hiddenSelectionCount }} personne(s) sélectionnée(s) hors des filtres actuels seront aussi imprimées.</Message>
          <DataTable v-model:selection="selected" v-model:first="first" :value="filtered" data-key="key" :select-all="allFilteredSelected" :loading="loading" paginator :rows="10" :rows-per-page-options="[10, 25, 50]" scrollable :table-style="{ minWidth: '30rem' }" @select-all-change="toggleFilteredSelection" @row-click="previewKey = $event.data.key">
            <Column selection-mode="multiple" header-style="width: 3rem" />
            <Column field="displayName" header="Personne"><template #body="{ data }"><strong>{{ data.displayName }}</strong><small class="cards-person-role">{{ data.roleLabel }}</small></template></Column>
            <Column header="Type"><template #body="{ data }"><Tag :value="data.kind === 'employee' ? 'Employé' : 'Prestataire'" :severity="data.kind === 'employee' ? 'info' : 'secondary'" /></template></Column>
            <Column header="Photo"><template #body="{ data }"><Tag :value="data.photoUrl ? 'Disponible' : 'Manquante'" :severity="data.photoUrl ? 'success' : 'warn'" /></template></Column>
            <template #empty>Aucune personne ne correspond à votre recherche.</template>
          </DataTable>
        </template>
      </Card>
      <Card>
        <template #title>2. Préparer l’impression</template>
        <template #content>
          <div class="cards-options">
            <label for="cards-template">Modèle</label>
            <Select input-id="cards-template" v-model="options.template" :options="CARD_TEMPLATES" option-label="label" option-value="value"><template #option="{ option }"><div><strong>{{ option.label }}</strong><small class="cards-person-role">{{ option.description }}</small></div></template></Select>
            <div class="cards-option-grid">
              <div><label for="cards-orientation">Orientation</label><Select input-id="cards-orientation" v-model="options.orientation" :options="orientationOptions" option-label="label" option-value="value" /></div>
              <div><label for="cards-photo">Photo</label><Select input-id="cards-photo" v-model="options.withPhoto" :options="photoOptions" option-label="label" option-value="value" /></div>
            </div>
            <label for="cards-medium">Support</label>
            <Select input-id="cards-medium" v-model="options.medium" :options="mediumOptions" option-label="label" option-value="value" />
            <label v-if="options.medium === 'paper'" class="cards-check"><Checkbox v-model="options.cutMarks" input-id="cards-cut-marks" binary /> <span>Repères de découpe</span></label>
            <p class="cards-help">Format CR80 : {{ options.orientation === 'portrait' ? '53,98 × 85,60' : '85,60 × 53,98' }} mm. Zone de sécurité intérieure de 4 mm.</p>
          </div>
          <div class="cards-preview" aria-label="Aperçu de la carte">
            <div v-if="preview" v-html="preview" />
            <p v-else>Sélectionnez une personne pour voir sa carte.</p>
          </div>
          <label v-if="selected.length > 1" class="cards-preview-picker">Personne de l’aperçu<Select v-model="previewKey" :options="selected" option-label="displayName" option-value="key" :placeholder="previewPerson?.displayName" /></label>
          <Message v-if="missingPhotos.length" severity="warn" :closable="false">
            <strong>{{ missingPhotos.length }} personne(s) sans photo</strong>
            <ul class="cards-missing-list"><li v-for="person in missingPhotos" :key="person.key"><RouterLink :to="{ name: person.kind === 'employee' ? 'employee-detail' : 'prestataire-detail', params: { id: person.id } }">{{ person.displayName }}</RouterLink></li></ul>
            <p>Ajoutez les photos depuis les fiches RH puis actualisez, choisissez « Sans photo » ou retirez ces personnes.</p>
            <Button label="Retirer les personnes sans photo" severity="warn" text size="small" @click="excludeMissingPhotos" />
          </Message>
          <Message v-if="printError" severity="error" :closable="false">{{ printError }}</Message>
          <div class="cards-print-summary" aria-live="polite">{{ selected.length }} carte(s) · {{ pageCount }} {{ options.medium === 'pvc' ? 'page(s) PVC' : 'planche(s) A4' }}</div>
          <Button label="Imprimer la sélection" icon="pi pi-print" :loading="printing" :disabled="loading || !!error || !selected.length || !!missingPhotos.length" class="cards-print-button" @click="printCards" />
          <p class="cards-help">Dans la fenêtre d’impression : échelle 100 % (taille réelle), sans en-têtes ni pieds de page, avec les arrière-plans. Pour le PVC, configurez le pilote sur CR80 sans marges. Le rendu physique dépend des réglages de l’imprimante. Vous pouvez aussi enregistrer en PDF.</p>
        </template>
      </Card>
    </div>
  </div>
</template>

<style scoped>
.service-cards-panel { display: grid; gap: 1rem; }
.cards-title { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; }
.cards-title h2 { margin: 0 0 .4rem; }
.cards-title p, .cards-help { color: var(--p-text-muted-color, #64748b); margin: 0; }
.cards-layout { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(350px, 1fr); gap: 1rem; align-items: start; }
.cards-layout > * { min-width: 0; }
.cards-filters, .cards-selection-bar { display: flex; gap: .6rem; flex-wrap: wrap; align-items: center; margin-bottom: 1rem; }
.cards-filters > * { flex: 1; min-width: 10rem; }
.cards-person-role { display: block; color: var(--p-text-muted-color, #64748b); margin-top: .25rem; }
.cards-options { display: grid; gap: .5rem; }
.cards-options label, .cards-preview-picker { font-size: .875rem; font-weight: 600; }
.cards-option-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; }
.cards-option-grid > div { display: grid; gap: .5rem; min-width: 0; }
.cards-check { display: flex; gap: .5rem; align-items: center; margin: .5rem 0; }
.cards-help { font-size: .8rem; line-height: 1.5; margin: .5rem 0; }
.cards-preview { display: flex; justify-content: center; padding: 1.25rem .5rem; margin: 1rem 0; border-radius: .75rem; background: var(--p-surface-100, #eef2f6); overflow-x: auto; }
.cards-preview > div { flex-shrink: 0; }
.cards-preview-picker { display: grid; gap: .5rem; margin-bottom: 1rem; }
.cards-print-summary { margin: 1rem 0 .75rem; font-weight: 600; }
.cards-print-button { width: 100%; }
.cards-missing-list { max-height: 9rem; overflow-y: auto; padding-left: 1.2rem; }
@media (max-width: 1100px) { .cards-layout { grid-template-columns: 1fr; } }
@media (max-width: 400px) { .cards-option-grid { grid-template-columns: 1fr; } .cards-preview { justify-content: flex-start; } }
</style>
