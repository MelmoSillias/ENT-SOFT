<script setup>
import { ref } from 'vue'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import InputNumber from 'primevue/inputnumber'
import Chips from 'primevue/chips'
import AppFieldError from '@/domains/shared/components/AppFieldError.vue'
import { DEVISE_APP } from '@/domains/shared/constants/devise'
import { PROJECT_STATUS_OPTIONS } from '@/domains/shared/utils/entLabels'

const MAX_INFO_LABEL_LENGTH = 80

const form = defineModel({ type: Object, required: true })

defineProps({
  errors: { type: Object, default: () => ({}) },
  clientOptions: { type: Array, default: () => [] },
  showCode: { type: Boolean, default: false },
})

const statusOptions = PROJECT_STATUS_OPTIONS
const chipError = ref('')

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
  form.value.sitesInfoLabels = cleaned
}
</script>

<template>
  <div class="ent-form-grid">
    <div v-if="showCode" class="field">
      <label>Code</label>
      <InputText v-model="form.code" disabled fluid />
      <small class="field-hint">Généré automatiquement</small>
    </div>
    <div class="field ent-form-grid__full">
      <label>Titre <span class="required">*</span></label>
      <InputText v-model="form.title" :invalid="Boolean(errors.title)" fluid />
      <AppFieldError :message="errors.title" />
    </div>
    <div class="field">
      <label>Client <span class="required">*</span></label>
      <Select
        v-model="form.clientId"
        :options="clientOptions"
        option-label="label"
        option-value="value"
        placeholder="Sélectionner"
        :invalid="Boolean(errors.clientId)"
        filter
        fluid
      />
      <AppFieldError :message="errors.clientId" />
    </div>
    <div class="field">
      <label>Statut</label>
      <Select v-model="form.status" :options="statusOptions" option-label="label" option-value="value" fluid />
    </div>
    <div class="field ent-form-grid__full">
      <label>Objet</label>
      <Textarea v-model="form.object" rows="2" auto-resize fluid />
    </div>
    <div class="field">
      <label>Date début</label>
      <DatePicker v-model="form.dateDebut" date-format="dd/mm/yy" show-icon fluid />
    </div>
    <div class="field">
      <label>Date fin</label>
      <DatePicker v-model="form.dateFin" date-format="dd/mm/yy" show-icon fluid />
    </div>
    <div class="field">
      <label>Budget</label>
      <InputNumber
        v-model="form.budget"
        mode="currency"
        :currency="DEVISE_APP.code"
        locale="fr-FR"
        :min-fraction-digits="0"
        :max-fraction-digits="0"
        fluid
      />
    </div>
    <div class="field ent-form-grid__full">
      <label>Informations supplémentaires (sites)</label>
      <Chips
        :model-value="form.sitesInfoLabels"
        placeholder="Saisir un libellé puis Entrée (ex. Start Date)"
        fluid
        class="pst-info-chips"
        @update:model-value="onInfoLabelsUpdate"
      />
      <small v-if="chipError" class="field-error">{{ chipError }}</small>
      <small v-else class="field-hint">
        Saisir un texte libre puis Entrée pour créer un tag. Survolez un tag pour le supprimer.
      </small>
    </div>
  </div>
</template>

<style scoped>
.ent-form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem 1rem;
}

.ent-form-grid__full {
  grid-column: 1 / -1;
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
</style>
