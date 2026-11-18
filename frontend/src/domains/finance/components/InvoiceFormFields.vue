<script setup>
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import InputNumber from 'primevue/inputnumber'
import ToggleSwitch from 'primevue/toggleswitch'
import Button from 'primevue/button'
import draggable from 'vuedraggable'
import AppFieldError from '@/domains/shared/components/AppFieldError.vue'
import { DEVISE_APP } from '@/domains/shared/constants/devise'
import { formatMontant } from '@/domains/shared/utils/formatMontant'
import { INVOICE_STATUS_OPTIONS } from '@/domains/shared/utils/entLabels'
import { computed, watch } from 'vue'

const form = defineModel({ type: Object, required: true })
defineProps({
  errors: { type: Object, default: () => ({}) },
  clientOptions: { type: Array, default: () => [] },
  projectOptions: { type: Array, default: () => [] },
})
let lineUid = 0
const newLineKey = () => `il-${Date.now()}-${++lineUid}`
const lines = computed({
  get: () => form.value.lines ?? [],
  set: (value) => { form.value.lines = value },
})
watch(lines, () => {
  for (const line of lines.value) if (!line._key) line._key = newLineKey()
}, { immediate: true })
function addLine() {
  lines.value = [...lines.value, { _key: newLineKey(), description: '', unit: 'Lot', quantity: 1, unitPrice: 0 }]
}
function removeLine(index) {
  lines.value = lines.value.filter((_, i) => i !== index)
}
function moveLine(index, delta) {
  const target = index + delta
  if (target < 0 || target >= lines.value.length) return
  const next = [...lines.value]
  const [item] = next.splice(index, 1)
  next.splice(target, 0, item)
  lines.value = next
}
function onProjectSelect() {
  if (form.value.projectId) form.value.projectLabel = ''
}
function onProjectLabelInput() {
  if (String(form.value.projectLabel || '').trim()) form.value.projectId = null
}
</script>

<template>
  <div class="invoice-form">
    <section class="invoice-info" aria-label="Informations de la facture">
      <h3>Informations de la facture</h3>
      <div class="ent-form-grid">
        <div class="field">
          <label for="invoice-date">Date <span class="required">*</span></label>
          <DatePicker v-model="form.date" input-id="invoice-date" date-format="dd/mm/yy" show-icon :invalid="Boolean(errors.date)" fluid />
          <AppFieldError :message="errors.date" />
        </div>
        <div class="field">
          <label for="invoice-status">Statut</label>
          <Select v-model="form.status" input-id="invoice-status" :options="INVOICE_STATUS_OPTIONS" option-label="label" option-value="value" fluid />
        </div>
        <div class="field field--wide">
          <label for="invoice-client">Client <span class="required">*</span></label>
          <Select v-model="form.clientId" input-id="invoice-client" :options="clientOptions" option-label="label" option-value="value" placeholder="Sélectionner" :invalid="Boolean(errors.clientId)" filter fluid />
          <AppFieldError :message="errors.clientId" />
        </div>
        <div class="field field--wide">
          <label for="invoice-project">Projet</label>
          <Select v-model="form.projectId" input-id="invoice-project" :options="projectOptions" option-label="label" option-value="value" placeholder="Projet existant (optionnel)" show-clear filter fluid @update:model-value="onProjectSelect" />
          <InputText v-model="form.projectLabel" aria-label="Libellé libre du projet" placeholder="Ou texte libre pour l’impression" fluid @update:model-value="onProjectLabelInput" />
          <small class="field-hint">Choisir un projet réel ou saisir un libellé libre (pas les deux).</small>
        </div>
        <div class="field field--wide">
          <div class="vat-toggle">
            <ToggleSwitch v-model="form.vatEnabled" input-id="invoice-vat" />
            <label for="invoice-vat">Appliquer la TVA</label>
          </div>
          <AppFieldError :message="errors.vatEnabled" />
        </div>
        <div v-if="form.vatEnabled" class="field field--wide">
          <label for="invoice-vat-rate">Taux de TVA (%)</label>
          <InputNumber v-model="form.vatRate" input-id="invoice-vat-rate" :min="0" :max="100" :max-fraction-digits="2" :invalid="Boolean(errors.vatRate)" suffix=" %" fluid @input="form.vatRate = $event.value" />
          <AppFieldError :message="errors.vatRate" />
        </div>
      </div>
    </section>
    <section class="invoice-lines" aria-label="Lignes de la facture">
      <div class="invoice-lines__header">
        <h3>Lignes</h3>
        <Button type="button" label="Ajouter une ligne" icon="pi pi-plus" size="small" outlined @click="addLine" />
      </div>
      <AppFieldError :message="errors.lines" />
      <p v-if="!lines.length" class="field-hint">Aucune ligne. Ajoutez-en une.</p>
      <draggable v-model="lines" item-key="_key" handle=".invoice-line__handle" class="invoice-lines__cards" :animation="150" :delay="150" :delay-on-touch-only="true">
        <template #item="{ element: line, index }">
          <div class="invoice-line">
            <div class="invoice-line__top">
              <span class="invoice-line__index">Ligne {{ index + 1 }}</span>
              <div class="invoice-line__actions">
                <button type="button" class="invoice-line__handle" :aria-label="`Déplacer la ligne ${index + 1}`" title="Glisser pour déplacer"><i class="pi pi-bars" aria-hidden="true" /></button>
                <Button type="button" icon="pi pi-arrow-up" text rounded size="small" :disabled="index === 0" aria-label="Monter la ligne" @click="moveLine(index, -1)" />
                <Button type="button" icon="pi pi-arrow-down" text rounded size="small" :disabled="index === lines.length - 1" aria-label="Descendre la ligne" @click="moveLine(index, 1)" />
                <Button type="button" icon="pi pi-trash" text rounded size="small" severity="danger" aria-label="Supprimer la ligne" @click="removeLine(index)" />
              </div>
            </div>
            <div class="field">
              <label :for="`${line._key}-description`">Désignation</label>
              <Textarea v-model="line.description" :id="`${line._key}-description`" placeholder="Désignation" :rows="2" auto-resize fluid class="invoice-line__description" />
            </div>
            <div class="field">
              <label :for="`${line._key}-unit`">Unité</label>
              <InputText v-model="line.unit" :id="`${line._key}-unit`" placeholder="Lot" fluid />
            </div>
            <div class="invoice-line__prices">
              <div class="field">
                <label :for="`${line._key}-quantity`">Quantité</label>
                <InputNumber v-model="line.quantity" :input-id="`${line._key}-quantity`" :min="0" :max-fraction-digits="2" fluid @input="line.quantity = $event.value" />
              </div>
              <div class="field">
                <label :for="`${line._key}-price`">Prix U. HT</label>
                <InputNumber v-model="line.unitPrice" :input-id="`${line._key}-price`" mode="currency" :currency="DEVISE_APP.code" locale="fr-FR" :min="0" :min-fraction-digits="0" :max-fraction-digits="0" fluid @input="line.unitPrice = $event.value" />
              </div>
            </div>
            <p class="invoice-line__amount">Total HT : {{ formatMontant(Number(line.quantity || 0) * Number(line.unitPrice || 0), DEVISE_APP) }}</p>
          </div>
        </template>
      </draggable>
    </section>
  </div>
</template>

<style scoped>
.invoice-form { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1.5rem; align-items: start; }
.invoice-info, .invoice-lines { min-width: 0; }
h3 { margin: 0 0 0.75rem; font-size: 1rem; }
.ent-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.75rem; }
.field { display: flex; flex-direction: column; gap: 0.35rem; min-width: 0; }
.field--wide { grid-column: 1 / -1; }
.field-hint { color: var(--layout-text-muted); font-size: 0.75rem; }
.required { color: var(--p-red-500, #ef4444); }
.vat-toggle, .invoice-lines__header, .invoice-line__top { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; }
.vat-toggle { justify-content: flex-start; }
.invoice-lines__header { flex-wrap: wrap; margin-bottom: 0.75rem; }
.invoice-lines__header h3 { margin: 0; }
.invoice-lines__cards { display: flex; flex-direction: column; gap: 0.75rem; }
.invoice-line { display: flex; flex-direction: column; gap: 0.75rem; padding: 0.75rem; border: 1px solid var(--layout-panel-border); border-radius: var(--layout-radius-sm, 0.5rem); background: var(--layout-panel-bg); min-width: 0; }
.invoice-line__index { font-size: 0.85rem; font-weight: 650; color: var(--layout-text-muted); }
.invoice-line__actions { display: flex; align-items: center; gap: 0.125rem; }
.invoice-line__handle { cursor: grab; touch-action: none; background: transparent; color: var(--layout-text-muted); border: 0; padding: 0.5rem; }
.invoice-line__handle:active { cursor: grabbing; }
.invoice-line__description { min-height: 4rem; resize: vertical; }
.invoice-line__prices { display: grid; grid-template-columns: minmax(5rem, 1fr) minmax(10rem, 2fr); gap: 0.75rem; }
.invoice-line__prices :deep(.p-inputnumber), .invoice-line__prices :deep(input) { width: 100%; min-width: 0; }
.invoice-line__amount { margin: 0; text-align: right; font-weight: 600; font-variant-numeric: tabular-nums; }
@media (min-width: 768px) {
  .invoice-form { grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr); }
}
@media (max-width: 359px) {
  .invoice-line__prices { grid-template-columns: minmax(4rem, 1fr) minmax(8rem, 2fr); }
}
</style>
