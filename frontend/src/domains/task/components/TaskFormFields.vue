<script setup>
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import AppFieldError from '@/domains/shared/components/AppFieldError.vue'
import { TASK_STATUS_OPTIONS } from '@/domains/shared/utils/entLabels'

const form = defineModel({ type: Object, required: true })

defineProps({
  errors: { type: Object, default: () => ({}) },
  siteOptions: { type: Array, default: () => [] },
  employeeOptions: { type: Array, default: () => [] },
})

const statusOptions = TASK_STATUS_OPTIONS
</script>

<template>
  <div class="ent-form-grid">
    <div class="field ent-form-grid__full">
      <label>Titre <span class="required">*</span></label>
      <InputText v-model="form.title" :invalid="Boolean(errors.title)" fluid />
      <AppFieldError :message="errors.title" />
    </div>
    <div class="field">
      <label>Site <span class="required">*</span></label>
      <Select
        v-model="form.siteId"
        :options="siteOptions"
        option-label="label"
        option-value="value"
        placeholder="Sélectionner"
        :invalid="Boolean(errors.siteId)"
        filter
        fluid
      />
      <AppFieldError :message="errors.siteId" />
    </div>
    <div class="field">
      <label>Employé assigné</label>
      <Select
        v-model="form.employeeId"
        :options="employeeOptions"
        option-label="label"
        option-value="value"
        placeholder="Non assigné"
        show-clear
        filter
        fluid
      />
    </div>
    <div class="field">
      <label>Statut</label>
      <Select v-model="form.status" :options="statusOptions" option-label="label" option-value="value" fluid />
    </div>
    <div class="field">
      <label>Échéance</label>
      <DatePicker v-model="form.dateDue" date-format="dd/mm/yy" show-icon show-clear fluid />
    </div>
    <div class="field ent-form-grid__full">
      <label>Description</label>
      <Textarea v-model="form.description" rows="3" auto-resize fluid />
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

.required {
  color: var(--p-red-500, #ef4444);
}
</style>
