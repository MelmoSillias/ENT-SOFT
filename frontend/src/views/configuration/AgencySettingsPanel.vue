<script setup>
import { computed, onMounted, ref } from 'vue'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Message from 'primevue/message'
import api from '@/services/api'
import { useAsyncAction } from '@/domains/shared/composables/useAsyncAction'
import { useAgencyBrandStore } from '@/domains/configuration/stores/agencyBrand'

const props = defineProps({
  canEdit: { type: Boolean, default: false },
})

const AGENCY_KEYS = [
  { cle: 'AGENCE_NOM', label: 'Nom', type: 'text' },
  { cle: 'AGENCE_TELEPHONE', label: 'Téléphone', type: 'text' },
  { cle: 'AGENCE_ADRESSE', label: 'Adresse', type: 'text' },
  { cle: 'AGENCE_VILLE', label: 'Ville', type: 'text' },
  { cle: 'AGENCE_EMAIL', label: 'Email', type: 'email' },
  { cle: 'AGENCE_SITE_WEB', label: 'Site web', type: 'url' },
]

const form = ref({})
const loading = ref(true)
const error = ref(null)
const success = ref(null)
const agencyBrandStore = useAgencyBrandStore()

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
    for (const key of AGENCY_KEYS) {
      form.value[key.cle] = map[key.cle] ?? ''
    }
  } catch (e) {
    error.value = e.response?.data?.error || 'Impossible de charger les paramètres agence.'
  } finally {
    loading.value = false
  }
}

const { pending: saving, run: save } = useAsyncAction(async () => {
  success.value = null
  error.value = null
  try {
    for (const key of AGENCY_KEYS) {
      await api.put(`/settings/${key.cle}`, { valeur: form.value[key.cle] ?? '' })
    }
    agencyBrandStore.name = form.value.AGENCE_NOM ?? ''
    success.value = 'Paramètres agence enregistrés.'
  } catch (e) {
    error.value = e.response?.data?.error || 'Erreur lors de la sauvegarde.'
  }
})

onMounted(load)

const fields = computed(() => AGENCY_KEYS)
</script>

<template>
  <div class="agency-settings">
    <p class="agency-settings__intro">Informations affichées sur les reçus et documents officiels.</p>
    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
    <Message v-if="success" severity="success" :closable="false">{{ success }}</Message>

    <div v-if="loading">Chargement…</div>
    <template v-else>
      <div v-for="field in fields" :key="field.cle" class="field">
        <label>{{ field.label }}</label>
        <InputText v-model="form[field.cle]" :type="field.type" fluid :readonly="!canEdit" />
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
.agency-settings__intro {
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
</style>
