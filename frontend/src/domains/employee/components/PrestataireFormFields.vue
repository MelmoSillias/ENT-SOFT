<script setup>
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import AppFieldError from '@/domains/shared/components/AppFieldError.vue'
import AppPhoneInput from '@/domains/shared/components/AppPhoneInput.vue'

const form = defineModel({ type: Object, required: true })

defineProps({
  errors: { type: Object, default: () => ({}) },
})
</script>

<template>
  <div class="ent-form-grid">
    <div class="field">
      <label>Prénom <span class="required">*</span></label>
      <InputText v-model="form.prenom" :invalid="Boolean(errors.prenom)" fluid />
      <AppFieldError :message="errors.prenom" />
    </div>
    <div class="field">
      <label>Nom <span class="required">*</span></label>
      <InputText v-model="form.nom" :invalid="Boolean(errors.nom)" fluid />
      <AppFieldError :message="errors.nom" />
    </div>
    <div class="field">
      <label>Email <span class="required">*</span></label>
      <InputText v-model="form.email" :invalid="Boolean(errors.email)" fluid />
      <AppFieldError :message="errors.email" />
    </div>
    <div class="field">
      <label>Téléphone <span class="required">*</span></label>
      <AppPhoneInput v-model="form.phone" :invalid="Boolean(errors.phone)" fluid />
      <AppFieldError :message="errors.phone" />
    </div>
    <div class="field ent-form-grid__full">
      <label>Adresse</label>
      <Textarea v-model="form.address" rows="2" auto-resize fluid />
    </div>
  </div>
</template>

<style scoped>
.ent-form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem 1rem;
}
.ent-form-grid__full { grid-column: 1 / -1; }
.field { display: flex; flex-direction: column; gap: 0.35rem; }
.required { color: var(--p-red-500, #ef4444); }
</style>
