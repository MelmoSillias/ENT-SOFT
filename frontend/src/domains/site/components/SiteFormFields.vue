<script setup>
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Select from 'primevue/select'
import AppFieldError from '@/domains/shared/components/AppFieldError.vue'

const form = defineModel({ type: Object, required: true })

defineProps({
  errors: { type: Object, default: () => ({}) },
  clientOptions: { type: Array, default: () => [] },
  /** When true, code is shown read-only (edit mode). */
  showCode: { type: Boolean, default: false },
  /** When true (create mode), code is editable and required. */
  requireCode: { type: Boolean, default: false },
})
</script>

<template>
  <div class="ent-form-grid">
    <div v-if="showCode || requireCode" class="field">
      <label>Code <span v-if="requireCode" class="required">*</span></label>
      <InputText
        v-model="form.code"
        :disabled="showCode && !requireCode"
        :invalid="Boolean(errors.code)"
        placeholder="Ex. SIT-0001"
        fluid
      />
      <AppFieldError :message="errors.code" />
      <small v-if="showCode && !requireCode" class="field-hint">Non modifiable</small>
    </div>
    <div class="field ent-form-grid__full">
      <label>Titre <span class="required">*</span></label>
      <InputText v-model="form.title" :invalid="Boolean(errors.title)" fluid />
      <AppFieldError :message="errors.title" />
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
