<script setup>
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import InputNumber from 'primevue/inputnumber'
import AppFieldError from '@/domains/shared/components/AppFieldError.vue'
import { PROJECT_STATUS_OPTIONS } from '@/domains/shared/utils/entLabels'

const form = defineModel({ type: Object, required: true })

defineProps({
  errors: { type: Object, default: () => ({}) },
  clientOptions: { type: Array, default: () => [] },
  showCode: { type: Boolean, default: false },
})

const statusOptions = PROJECT_STATUS_OPTIONS
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
      <InputNumber v-model="form.budget" mode="currency" currency="EUR" locale="fr-FR" fluid />
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

.required {
  color: var(--p-red-500, #ef4444);
}
</style>
