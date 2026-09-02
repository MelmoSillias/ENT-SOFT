<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import ToggleSwitch from 'primevue/toggleswitch'
import Button from 'primevue/button'
import Message from 'primevue/message'
import api from '@/services/api'
import { useAsyncAction } from '@/domains/shared/composables/useAsyncAction'
import { usePrintSettingsStore } from '@/domains/impression/stores/printSettings'

const props = defineProps({
  canEdit: { type: Boolean, default: false },
})

const LOGO_KEY = 'AGENCE_LOGO_URL'
const ACCEPTED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif']
const ACCEPTED_IMAGE_ACCEPT = ACCEPTED_IMAGE_TYPES.join(',')
const MAX_LOGO_BYTES = 2 * 1024 * 1024

const KEYS = [
  { cle: 'IMPRESSION_FOOTER_TEXT', label: 'Pied de page', type: 'text' },
  { cle: 'IMPRESSION_SHOW_LOGO', label: 'Afficher le logo', type: 'boolean' },
  { cle: 'IMPRESSION_MARGIN_MM', label: 'Marges (mm)', type: 'integer' },
  { cle: 'IMPRESSION_DEFAULT_EXPORT_FORMAT', label: 'Format d\'export par défaut', type: 'select', options: [
    { value: 'pdf', label: 'PDF' },
    { value: 'excel', label: 'Excel' },
    { value: 'csv', label: 'CSV' },
    { value: 'word', label: 'Word' },
  ] },
  { cle: 'IMPRESSION_PAGE_TABLE', label: 'Format page — tableaux', type: 'select', options: [
    { value: 'a4', label: 'A4' },
    { value: 'a5', label: 'A5' },
  ] },
  { cle: 'IMPRESSION_ORIENTATION_TABLE', label: 'Orientation — tableaux', type: 'select', options: [
    { value: 'portrait', label: 'Portrait' },
    { value: 'landscape', label: 'Paysage' },
  ] },
  { cle: 'IMPRESSION_PAGE_TRANSFERT', label: 'Format page — reçu transfert', type: 'select', options: [
    { value: 'a4', label: 'A4' },
    { value: 'a5', label: 'A5' },
    { value: 'receipt_80mm', label: 'Ticket 80 mm' },
  ] },
  { cle: 'IMPRESSION_ORIENTATION_TRANSFERT', label: 'Orientation — reçu transfert', type: 'select', options: [
    { value: 'portrait', label: 'Portrait' },
    { value: 'landscape', label: 'Paysage' },
  ] },
]

const form = ref({})
const loading = ref(true)
const error = ref(null)
const success = ref(null)
const logoError = ref(null)
const logoInput = ref(null)
const printSettingsStore = usePrintSettingsStore()

const isReceipt = computed(() => form.value.IMPRESSION_PAGE_TRANSFERT === 'receipt_80mm')
const logoPreviewUrl = computed(() => {
  const url = String(form.value[LOGO_KEY] || '').trim()
  return url || null
})

watch(isReceipt, (receipt) => {
  if (receipt) {
    form.value.IMPRESSION_ORIENTATION_TRANSFERT = 'portrait'
  }
})

function validateLogoFile(file) {
  if (!file) {
    return 'Aucun fichier sélectionné.'
  }
  if (!ACCEPTED_IMAGE_TYPES.includes(file.type)) {
    return 'Seules les images JPEG, PNG, WebP et GIF sont acceptées.'
  }
  if (file.size > MAX_LOGO_BYTES) {
    return 'Le logo ne doit pas dépasser 2 Mo.'
  }
  return null
}

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
    form.value[LOGO_KEY] = map[LOGO_KEY] ?? ''
    for (const key of KEYS) {
      if (key.type === 'boolean') {
        form.value[key.cle] = ['1', 'true', 'yes', 'on'].includes(String(map[key.cle] ?? 'true').toLowerCase())
      } else if (key.type === 'integer') {
        form.value[key.cle] = Number(map[key.cle] ?? 10)
      } else {
        form.value[key.cle] = map[key.cle] ?? ''
      }
    }
  } catch (e) {
    error.value = e.response?.data?.error || 'Impossible de charger les paramètres d\'impression.'
  } finally {
    loading.value = false
  }
}

const { pending: saving, run: save } = useAsyncAction(async () => {
  success.value = null
  error.value = null
  try {
    for (const key of KEYS) {
      let valeur = form.value[key.cle]
      if (key.type === 'boolean') {
        valeur = valeur ? 'true' : 'false'
      } else {
        valeur = String(valeur ?? '')
      }
      await api.put(`/settings/${key.cle}`, { valeur })
    }
    printSettingsStore.invalidate()
    success.value = 'Paramètres d\'impression enregistrés.'
  } catch (e) {
    error.value = e.response?.data?.error || 'Erreur lors de la sauvegarde.'
  }
})

const { pending: uploadingLogo, run: uploadLogo } = useAsyncAction(async (file) => {
  logoError.value = null
  success.value = null
  const validationError = validateLogoFile(file)
  if (validationError) {
    logoError.value = validationError
    return
  }

  const body = new FormData()
  body.append('file', file)

  try {
    const { data } = await api.post('/settings/agence-logo', body, {
      transformRequest: [
        (payload, headers) => {
          if (headers && typeof headers.delete === 'function') {
            headers.delete('Content-Type')
          } else if (headers) {
            delete headers['Content-Type']
          }
          return payload
        },
      ],
    })
    form.value[LOGO_KEY] = data.valeur ?? ''
    printSettingsStore.invalidate()
    success.value = 'Logo mis à jour.'
  } catch (e) {
    logoError.value = e.response?.data?.error || 'Impossible d\'envoyer le logo.'
  } finally {
    if (logoInput.value) {
      logoInput.value.value = ''
    }
  }
})

const { pending: removingLogo, run: removeLogo } = useAsyncAction(async () => {
  logoError.value = null
  success.value = null
  try {
    const { data } = await api.delete('/settings/agence-logo')
    form.value[LOGO_KEY] = data.valeur ?? ''
    printSettingsStore.invalidate()
    success.value = 'Logo supprimé.'
  } catch (e) {
    logoError.value = e.response?.data?.error || 'Impossible de supprimer le logo.'
  }
})

function onLogoSelected(event) {
  const file = event.target?.files?.[0]
  if (!file) return
  uploadLogo(file)
}

onMounted(load)
</script>

<template>
  <div class="impression-settings">
    <p class="impression-settings__intro">
      Formats, orientations et identité des documents imprimés. L’identité agence (nom, adresse, téléphone)
      se configure aussi dans l’onglet Agence.
    </p>
    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
    <Message v-if="logoError" severity="error" :closable="false">{{ logoError }}</Message>
    <Message v-if="success" severity="success" :closable="false">{{ success }}</Message>

    <div v-if="loading">Chargement…</div>
    <template v-else>
      <div class="field logo-field">
        <label for="agence-logo-input">Logo</label>
        <div class="logo-field__preview" aria-live="polite">
          <img
            v-if="logoPreviewUrl"
            :src="logoPreviewUrl"
            alt="Logo agence"
            class="logo-field__image"
          >
          <span v-else class="logo-field__placeholder">Aucun logo</span>
        </div>
        <div class="logo-field__actions">
          <input
            id="agence-logo-input"
            ref="logoInput"
            type="file"
            class="logo-field__input"
            :accept="ACCEPTED_IMAGE_ACCEPT"
            :disabled="!canEdit || uploadingLogo || removingLogo"
            @change="onLogoSelected"
          >
          <Button
            v-if="canEdit"
            type="button"
            label="Choisir une image"
            icon="pi pi-upload"
            outlined
            :loading="uploadingLogo"
            :disabled="uploadingLogo || removingLogo"
            @click="logoInput?.click()"
          />
          <Button
            v-if="canEdit && logoPreviewUrl"
            type="button"
            label="Supprimer"
            icon="pi pi-trash"
            severity="danger"
            text
            :loading="removingLogo"
            :disabled="uploadingLogo || removingLogo"
            @click="removeLogo"
          />
        </div>
        <small class="impression-settings__hint">
          Images uniquement (JPEG, PNG, WebP, GIF), 2 Mo max.
        </small>
      </div>

      <div v-for="field in KEYS" :key="field.cle" class="field">
        <label>{{ field.label }}</label>
        <ToggleSwitch
          v-if="field.type === 'boolean'"
          v-model="form[field.cle]"
          :disabled="!canEdit"
        />
        <InputNumber
          v-else-if="field.type === 'integer'"
          v-model="form[field.cle]"
          :min="0"
          :max="30"
          :disabled="!canEdit"
          fluid
        />
        <Select
          v-else-if="field.type === 'select'"
          v-model="form[field.cle]"
          :options="field.options"
          option-label="label"
          option-value="value"
          :disabled="!canEdit || (field.cle === 'IMPRESSION_ORIENTATION_TRANSFERT' && isReceipt)"
          fluid
        />
        <InputText
          v-else
          v-model="form[field.cle]"
          fluid
          :readonly="!canEdit"
        />
        <small
          v-if="field.cle === 'IMPRESSION_ORIENTATION_TRANSFERT' && isReceipt"
          class="impression-settings__hint"
        >
          Le ticket 80 mm est toujours en portrait.
        </small>
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
.impression-settings__intro {
  margin: 0 0 1rem;
  color: var(--layout-text-muted);
  font-size: 0.875rem;
}

.impression-settings__hint {
  display: block;
  margin-top: 0.25rem;
  color: var(--layout-text-muted);
  font-size: 0.75rem;
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

.logo-field__preview {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 10rem;
  height: 6rem;
  margin-bottom: 0.65rem;
  border: 1px dashed var(--p-content-border-color, #cbd5e1);
  border-radius: 0.5rem;
  background: var(--p-content-background, #fff);
  overflow: hidden;
}

.logo-field__image {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
}

.logo-field__placeholder {
  color: var(--layout-text-muted);
  font-size: 0.8rem;
}

.logo-field__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  align-items: center;
}

.logo-field__input {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
</style>
