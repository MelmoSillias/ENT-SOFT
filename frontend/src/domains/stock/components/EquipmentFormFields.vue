<script setup>
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Select from 'primevue/select'
import AppFieldError from '@/domains/shared/components/AppFieldError.vue'
import { EQUIPMENT_UNIT_OPTIONS } from '@/domains/shared/utils/entLabels'

const form = defineModel({ type: Object, required: true })

defineProps({
  errors: { type: Object, default: () => ({}) },
  clientOptions: { type: Array, default: () => [] },
  showCode: { type: Boolean, default: false },
})
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
      <label>Unité <span class="required">*</span></label>
      <Select
        v-model="form.unit"
        :options="EQUIPMENT_UNIT_OPTIONS"
        option-label="label"
        option-value="value"
        :invalid="Boolean(errors.unit)"
        fluid
      />
      <AppFieldError :message="errors.unit" />
    </div>
    <div class="field ent-form-grid__full">
      <label>Description</label>
      <Textarea v-model="form.description" rows="3" auto-resize fluid />
    </div>
    <div class="field ent-form-grid__full">
      <label>Client</label>
      <Select
        v-model="form.clientId"
        :options="clientOptions"
        option-label="label"
        option-value="value"
        placeholder="Sélectionner un client"
        show-clear
        filter
        fluid
      />
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
