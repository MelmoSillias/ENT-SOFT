<script setup>
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Button from 'primevue/button'
import AppFieldError from '@/domains/shared/components/AppFieldError.vue'
import { STOCK_DIRECTION_OPTIONS } from '@/domains/shared/utils/entLabels'

const form = defineModel({ type: Object, required: true })

defineProps({
  errors: { type: Object, default: () => ({}) },
  equipmentOptions: { type: Array, default: () => [] },
  clientOptions: { type: Array, default: () => [] },
  projectOptions: { type: Array, default: () => [] },
  siteOptions: { type: Array, default: () => [] },
})

function addLine() {
  form.value.lines = [...(form.value.lines ?? []), { equipmentId: null, quantity: 1 }]
}

function removeLine(index) {
  form.value.lines = (form.value.lines ?? []).filter((_, i) => i !== index)
}
</script>

<template>
  <div class="ent-form-grid">
    <div class="field">
      <label>Date <span class="required">*</span></label>
      <DatePicker v-model="form.date" date-format="dd/mm/yy" show-icon :invalid="Boolean(errors.date)" fluid />
      <AppFieldError :message="errors.date" />
    </div>
    <div class="field">
      <label>Direction</label>
      <Select v-model="form.direction" :options="STOCK_DIRECTION_OPTIONS" option-label="label" option-value="value" fluid />
    </div>
    <div class="field">
      <label>Unité <span class="required">*</span></label>
      <InputText v-model="form.unit" :invalid="Boolean(errors.unit)" fluid />
      <AppFieldError :message="errors.unit" />
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
  </div>

  <div class="movement-lines">
    <div class="movement-lines__header">
      <h3>Lignes d'équipement</h3>
      <Button label="Ajouter une ligne" icon="pi pi-plus" size="small" outlined @click="addLine" />
    </div>
    <AppFieldError :message="errors.lines" />
    <div v-for="(line, index) in form.lines" :key="index" class="movement-lines__row">
      <Select v-model="line.equipmentId" :options="equipmentOptions" option-label="label" option-value="value" placeholder="Équipement" filter fluid />
      <InputNumber v-model="line.quantity" :min="0" fluid />
      <Button icon="pi pi-trash" text rounded severity="danger" @click="removeLine(index)" />
    </div>
  </div>
</template>

<style scoped>
.ent-form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem 1rem;
}
.field { display: flex; flex-direction: column; gap: 0.35rem; }
.required { color: var(--p-red-500, #ef4444); }
.movement-lines { margin-top: 1rem; display: flex; flex-direction: column; gap: 0.5rem; }
.movement-lines__header { display: flex; justify-content: space-between; align-items: center; }
.movement-lines__header h3 { margin: 0; font-size: 0.95rem; }
.movement-lines__row { display: grid; grid-template-columns: 1fr 7rem auto; gap: 0.5rem; align-items: center; }
</style>
