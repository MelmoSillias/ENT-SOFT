<script setup>
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import InputNumber from 'primevue/inputnumber'
import AppFieldError from '@/domains/shared/components/AppFieldError.vue'
import { INVOICE_STATUS_OPTIONS } from '@/domains/shared/utils/entLabels'

const form = defineModel({ type: Object, required: true })

defineProps({
  errors: { type: Object, default: () => ({}) },
  clientOptions: { type: Array, default: () => [] },
  projectOptions: { type: Array, default: () => [] },
})

const statusOptions = INVOICE_STATUS_OPTIONS
</script>

<template>
  <div class="ent-form-grid">
    <div class="field">
      <label>Date <span class="required">*</span></label>
      <DatePicker v-model="form.date" date-format="dd/mm/yy" show-icon :invalid="Boolean(errors.date)" fluid />
      <AppFieldError :message="errors.date" />
    </div>
    <div class="field">
      <label>Montant <span class="required">*</span></label>
      <InputNumber v-model="form.amount" mode="currency" currency="EUR" locale="fr-FR" :invalid="Boolean(errors.amount)" fluid />
      <AppFieldError :message="errors.amount" />
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
      <label>Projet</label>
      <Select
        v-model="form.projectId"
        :options="projectOptions"
        option-label="label"
        option-value="value"
        placeholder="Optionnel"
        show-clear
        filter
        fluid
      />
    </div>
    <div class="field">
      <label>Statut</label>
      <Select v-model="form.status" :options="statusOptions" option-label="label" option-value="value" fluid />
    </div>
  </div>
</template>

<style scoped>
.ent-form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem 1rem;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.required {
  color: var(--p-red-500, #ef4444);
}
</style>
