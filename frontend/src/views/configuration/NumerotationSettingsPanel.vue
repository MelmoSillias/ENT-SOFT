<script setup>
import { computed, onMounted, ref } from 'vue'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Button from 'primevue/button'
import Message from 'primevue/message'
import api from '@/services/api'
import { useAsyncAction } from '@/domains/shared/composables/useAsyncAction'

const props = defineProps({
  canEdit: { type: Boolean, default: false },
})

const GROUPS = [
  {
    id: 'transfert',
    label: 'Transferts',
    prefixKey: 'REFERENCE_TRANSFERT_PREFIXE',
    digitsKey: 'REFERENCE_TRANSFERT_NB_CHIFFRES',
    titleKey: 'REFERENCE_TRANSFERT_TITRE_RECU',
    defaultTitle: 'RECU DE TRANSFERT',
  },
  {
    id: 'depot',
    label: 'Dépôts client',
    prefixKey: 'REFERENCE_DEPOT_PREFIXE',
    digitsKey: 'REFERENCE_DEPOT_NB_CHIFFRES',
    titleKey: 'REFERENCE_DEPOT_TITRE_RECU',
    defaultTitle: 'RECU DE DEPOT',
  },
  {
    id: 'retrait',
    label: 'Retraits client',
    prefixKey: 'REFERENCE_RETRAIT_PREFIXE',
    digitsKey: 'REFERENCE_RETRAIT_NB_CHIFFRES',
    titleKey: 'REFERENCE_RETRAIT_TITRE_RECU',
    defaultTitle: 'RECU DE RETRAIT',
  },
  {
    id: 'change',
    label: 'Changes client',
    prefixKey: 'REFERENCE_CHANGE_PREFIXE',
    digitsKey: 'REFERENCE_CHANGE_NB_CHIFFRES',
    titleKey: 'REFERENCE_CHANGE_TITRE_RECU',
    defaultTitle: 'RECU DE CHANGE',
  },
]

const DIGIT_OPTIONS = Array.from({ length: 8 }, (_, i) => ({
  value: String(i + 1),
  label: String(i + 1),
}))

const form = ref({})
const loading = ref(true)
const error = ref(null)
const success = ref(null)

const allKeys = GROUPS.flatMap((group) => [group.prefixKey, group.digitsKey, group.titleKey])

function previewForGroup(group) {
  const prefix = String(form.value[group.prefixKey] ?? '')
  const digits = Math.min(8, Math.max(1, Number(form.value[group.digitsKey] ?? 3) || 3))
  const sample = String(1).padStart(digits, '0')

  return `${prefix}${sample}`
}

const previews = computed(() =>
  Object.fromEntries(GROUPS.map((group) => [group.id, previewForGroup(group)])),
)

async function load() {
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get('/settings')
    const items = data.items ?? data
    const map = {}
    for (const item of items) {
      map[item.cle] = item.valeur
    }
    for (const group of GROUPS) {
      form.value[group.prefixKey] = map[group.prefixKey] ?? ''
      form.value[group.digitsKey] = map[group.digitsKey] ?? '3'
      form.value[group.titleKey] = map[group.titleKey] ?? group.defaultTitle
    }
  } catch (e) {
    error.value = e.response?.data?.error || 'Impossible de charger les paramètres de numérotation.'
  } finally {
    loading.value = false
  }
}

const { pending: saving, run: save } = useAsyncAction(async () => {
  success.value = null
  error.value = null
  try {
    for (const key of allKeys) {
      await api.put(`/settings/${key}`, { valeur: String(form.value[key] ?? '') })
    }
    success.value = 'Paramètres de numérotation enregistrés.'
  } catch (e) {
    error.value = e.response?.data?.error || 'Erreur lors de la sauvegarde.'
  }
})

onMounted(load)
</script>

<template>
  <div class="numerotation-settings">
    <p class="numerotation-settings__intro">
      Chaque type d'opération possède sa propre suite de numéros. Les transferts depuis compte client
      utilisent la même numérotation que les reçus de transfert.
    </p>
    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
    <Message v-if="success" severity="success" :closable="false">{{ success }}</Message>

    <div v-if="loading">Chargement…</div>
    <template v-else>
      <section
        v-for="group in GROUPS"
        :key="group.id"
        class="numerotation-settings__group"
      >
        <h3 class="numerotation-settings__group-title">{{ group.label }}</h3>

        <div class="field">
          <label :for="`${group.id}-prefixe`">Préfixe (optionnel)</label>
          <InputText
            :id="`${group.id}-prefixe`"
            v-model="form[group.prefixKey]"
            fluid
            :readonly="!canEdit"
            placeholder="Ex. REC-"
          />
          <small class="numerotation-settings__hint">
            Laisser vide pour obtenir 001, 002…
          </small>
        </div>

        <div class="field">
          <label :for="`${group.id}-digits`">Nombre de chiffres</label>
          <Select
            :id="`${group.id}-digits`"
            v-model="form[group.digitsKey]"
            :options="DIGIT_OPTIONS"
            option-label="label"
            option-value="value"
            :disabled="!canEdit"
            fluid
          />
        </div>

        <div class="field">
          <label :for="`${group.id}-title`">Titre du reçu</label>
          <InputText
            :id="`${group.id}-title`"
            v-model="form[group.titleKey]"
            fluid
            :readonly="!canEdit"
          />
          <small class="numerotation-settings__hint">
            Texte affiché en haut du reçu imprimé.
          </small>
        </div>

        <p class="numerotation-settings__preview">
          Exemple : <strong>{{ previews[group.id] }}</strong>
        </p>
      </section>

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
.numerotation-settings__intro {
  margin: 0 0 1rem;
  color: var(--layout-text-muted);
  font-size: 0.875rem;
}

.numerotation-settings__group {
  margin-bottom: 1.5rem;
  padding-bottom: 1.25rem;
  border-bottom: 1px solid var(--p-content-border-color, #e2e8f0);
}

.numerotation-settings__group:last-of-type {
  border-bottom: none;
}

.numerotation-settings__group-title {
  margin: 0 0 0.85rem;
  font-size: 1rem;
}

.numerotation-settings__hint {
  display: block;
  margin-top: 0.25rem;
  color: var(--layout-text-muted);
  font-size: 0.75rem;
}

.numerotation-settings__preview {
  margin: 0.5rem 0 0;
  font-size: 0.875rem;
  color: var(--layout-text-muted);
}

.field {
  margin-bottom: 0.85rem;
}

.field label {
  display: block;
  margin-bottom: 0.35rem;
  font-weight: 600;
  font-size: 0.875rem;
}
</style>
