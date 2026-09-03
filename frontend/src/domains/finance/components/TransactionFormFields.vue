<script setup>
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import AppFieldError from '@/domains/shared/components/AppFieldError.vue'
import { DEVISE_APP } from '@/domains/shared/constants/devise'
import {
  EXPENSE_CATEGORY_OPTIONS,
  TRANSACTION_CATEGORY_OPTIONS,
  TRANSACTION_STATUS_OPTIONS,
  TRANSACTION_TYPE_OPTIONS,
} from '@/domains/shared/utils/entLabels'

const form = defineModel({ type: Object, required: true })

defineProps({
  errors: { type: Object, default: () => ({}) },
  clientOptions: { type: Array, default: () => [] },
  projectOptions: { type: Array, default: () => [] },
  siteOptions: { type: Array, default: () => [] },
  expenseOnly: { type: Boolean, default: false },
})
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
      <InputNumber v-model="form.amount" mode="currency" :currency="DEVISE_APP.code" locale="fr-FR" :min-fraction-digits="0" :invalid="Boolean(errors.amount)" fluid />
      <AppFieldError :message="errors.amount" />
    </div>
    <div v-if="!expenseOnly" class="field">
      <label>Type</label>
      <Select v-model="form.type" :options="TRANSACTION_TYPE_OPTIONS" option-label="label" option-value="value" fluid />
    </div>
    <div class="field">
      <label>Catégorie</label>
      <Select
        v-model="form.category"
        :options="expenseOnly ? EXPENSE_CATEGORY_OPTIONS : TRANSACTION_CATEGORY_OPTIONS"
        option-label="label"
        option-value="value"
        fluid
      />
    </div>
    <div class="field">
      <label>Statut</label>
      <Select v-model="form.status" :options="TRANSACTION_STATUS_OPTIONS" option-label="label" option-value="value" fluid />
    </div>
    <div class="field">
      <label>Émetteur <span class="required">*</span></label>
      <InputText v-model="form.fromParty" :invalid="Boolean(errors.fromParty)" fluid />
      <AppFieldError :message="errors.fromParty" />
    </div>
    <div class="field">
      <label>Destinataire <span class="required">*</span></label>
      <InputText v-model="form.toParty" :invalid="Boolean(errors.toParty)" fluid />
      <AppFieldError :message="errors.toParty" />
    </div>
    <div class="field">
      <label>Client</label>
      <Select v-model="form.clientId" :options="clientOptions" option-label="label" option-value="value" show-clear filter fluid />
    </div>
    <div class="field">
      <label>Projet</label>
      <Select v-model="form.projectId" :options="projectOptions" option-label="label" option-value="value" show-clear filter fluid />
    </div>
    <div class="field">
      <label>Site</label>
      <Select v-model="form.siteId" :options="siteOptions" option-label="label" option-value="value" show-clear filter fluid />
    </div>
    <div class="field ent-form-grid__full">
      <label>Description</label>
      <Textarea v-model="form.description" rows="2" auto-resize fluid />
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
