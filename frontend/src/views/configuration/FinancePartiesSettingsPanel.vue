<script setup>
import { onMounted, ref } from 'vue'
import AutoComplete from 'primevue/autocomplete'
import Button from 'primevue/button'
import Message from 'primevue/message'
import api from '@/services/api'
import { useAsyncAction } from '@/domains/shared/composables/useAsyncAction'
import { parseSettingList, serializeSettingList } from '@/domains/configuration/utils/settingList'

const props = defineProps({
  canEdit: { type: Boolean, default: false },
})

const EMETTEURS_KEY = 'FINANCE_EMETTEURS'
const DESTINATAIRES_KEY = 'FINANCE_DESTINATAIRES'
const CATEGORIES_KEY = 'FINANCE_CATEGORIES_DEPENSES'

const DEFAULT_CATEGORIES = [
  'Dépense projet',
  'Dépense site',
  'Dépense matériel',
  'Dépense équipement',
  'Autre dépense',
]

const emetteurs = ref([])
const destinataires = ref([])
const categoriesDepenses = ref([])
const loading = ref(true)
const error = ref(null)
const success = ref(null)

async function load() {
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get('/settings')
    const items = Array.isArray(data) ? data : (data.items ?? [])
    const map = Object.fromEntries(items.map((item) => [item.cle, item.valeur]))
    emetteurs.value = parseSettingList(map[EMETTEURS_KEY])
    destinataires.value = parseSettingList(map[DESTINATAIRES_KEY])
    const cats = parseSettingList(map[CATEGORIES_KEY])
    categoriesDepenses.value = cats.length ? cats : [...DEFAULT_CATEGORIES]
  } catch (e) {
    error.value = e.response?.data?.error || 'Impossible de charger les listes finance.'
  } finally {
    loading.value = false
  }
}

function sanitizeList(next) {
  return parseSettingList(next)
}

const { pending: saving, run: save } = useAsyncAction(async () => {
  success.value = null
  error.value = null
  try {
    await api.put(`/settings/${EMETTEURS_KEY}`, {
      valeur: serializeSettingList(emetteurs.value),
    })
    await api.put(`/settings/${DESTINATAIRES_KEY}`, {
      valeur: serializeSettingList(destinataires.value),
    })
    await api.put(`/settings/${CATEGORIES_KEY}`, {
      valeur: serializeSettingList(categoriesDepenses.value),
    })
    success.value = 'Paramètres finance enregistrés.'
  } catch (e) {
    error.value = e.response?.data?.error || 'Erreur lors de la sauvegarde.'
  }
})

onMounted(load)
</script>

<template>
  <div class="finance-parties-settings">
    <h3 class="finance-parties-settings__title">Finance — listes paramétrables</h3>
    <p class="finance-parties-settings__intro">
      Listes proposées dans les formulaires de transactions et de dépenses. Saisir un libellé puis Entrée pour l’ajouter.
    </p>
    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
    <Message v-if="success" severity="success" :closable="false">{{ success }}</Message>

    <div v-if="loading">Chargement…</div>
    <template v-else>
      <div class="field">
        <label>Catégories de dépenses</label>
        <AutoComplete
          :model-value="categoriesDepenses"
          placeholder="Ex. Carburant, Frais de mission…"
          multiple
          :typeahead="false"
          fluid
          :disabled="!canEdit"
          class="finance-parties-settings__chips"
          @update:model-value="categoriesDepenses = sanitizeList($event)"
        />
      </div>
      <div class="field">
        <label>Émetteurs</label>
        <AutoComplete
          :model-value="emetteurs"
          placeholder="Ex. Client, ENT TECHNOLOGY…"
          multiple
          :typeahead="false"
          fluid
          :disabled="!canEdit"
          class="finance-parties-settings__chips"
          @update:model-value="emetteurs = sanitizeList($event)"
        />
      </div>
      <div class="field">
        <label>Destinataires / récepteurs</label>
        <AutoComplete
          :model-value="destinataires"
          placeholder="Ex. Fournisseur, Banque…"
          multiple
          :typeahead="false"
          fluid
          :disabled="!canEdit"
          class="finance-parties-settings__chips"
          @update:model-value="destinataires = sanitizeList($event)"
        />
      </div>
      <Button
        v-if="canEdit"
        label="Enregistrer"
        icon="pi pi-save"
        class="mt-3"
        :loading="saving"
        @click="save"
      />
    </template>
  </div>
</template>

<style scoped>
.finance-parties-settings__title {
  margin: 0 0 0.35rem;
  font-size: 1rem;
  font-weight: 600;
}

.finance-parties-settings__intro {
  margin: 0 0 1rem;
  color: var(--layout-text-muted);
  font-size: 0.875rem;
}

.field {
  margin-bottom: 0.85rem;
}

.field label {
  display: block;
  margin-bottom: 0.35rem;
  font-size: 0.85rem;
  font-weight: 600;
}

.mt-3 {
  margin-top: 0.75rem;
}

.finance-parties-settings__chips :deep(.p-chip-remove-icon) {
  opacity: 0.55;
}

.finance-parties-settings__chips :deep(.p-chip:hover .p-chip-remove-icon),
.finance-parties-settings__chips :deep(.p-chip:focus-within .p-chip-remove-icon) {
  opacity: 1;
}
</style>
