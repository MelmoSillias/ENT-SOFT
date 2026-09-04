<script setup>
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import AppFieldError from '@/domains/shared/components/AppFieldError.vue'

const form = defineModel({ type: Object, required: true })

defineProps({
  errors: { type: Object, default: () => ({}) },
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
    <div class="field ent-form-grid__full">
      <label>Adresse de service</label>
      <InputText v-model="form.address" placeholder="Ex. HAMDALLAYE ACI 2000, Immeuble TELECEL" fluid />
    </div>
    <div class="field">
      <label>Boîte postale</label>
      <InputText v-model="form.postalBox" placeholder="Ex. BP 2842" fluid />
    </div>
    <div class="field">
      <label>Ville</label>
      <InputText v-model="form.city" placeholder="Ex. Bamako" fluid />
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

.field-hint {
  color: var(--layout-text-muted);
  font-size: 0.75rem;
}

.required {
  color: var(--p-red-500, #ef4444);
}
</style>
