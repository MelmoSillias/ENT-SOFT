<script setup>
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Button from 'primevue/button'
import AppFieldError from '@/domains/shared/components/AppFieldError.vue'
import { DEVISE_APP } from '@/domains/shared/constants/devise'
import { INVOICE_STATUS_OPTIONS } from '@/domains/shared/utils/entLabels'
import { computed, ref } from 'vue'

const form = defineModel({ type: Object, required: true })

defineProps({
  errors: { type: Object, default: () => ({}) },
  clientOptions: { type: Array, default: () => [] },
  projectOptions: { type: Array, default: () => [] },
})

const statusOptions = INVOICE_STATUS_OPTIONS
const dragFromIndex = ref(null)
let lineUid = 0

function newLineKey() {
  return `il-${Date.now()}-${++lineUid}`
}

const linesTotal = computed(() =>
  (form.value.lines ?? []).reduce((sum, line) => sum + Number(line.quantity || 0) * Number(line.unitPrice || 0), 0),
)

function addLine() {
  form.value.lines = [
    ...(form.value.lines ?? []),
    { _key: newLineKey(), description: '', unit: 'Lot', quantity: 1, unitPrice: 0 },
  ]
}

function removeLine(index) {
  form.value.lines = (form.value.lines ?? []).filter((_, i) => i !== index)
}

function lineKey(line, index) {
  if (!line._key) line._key = newLineKey()
  return line._key || `fallback-${index}`
}

function lineAmount(line) {
  return Number(line.quantity || 0) * Number(line.unitPrice || 0)
}

function onProjectSelect() {
  if (form.value.projectId) {
    form.value.projectLabel = ''
  }
}

function onProjectLabelInput() {
  if (String(form.value.projectLabel || '').trim()) {
    form.value.projectId = null
  }
}

function onDragStart(index, event) {
  dragFromIndex.value = index
  event.dataTransfer.effectAllowed = 'move'
  event.dataTransfer.setData('text/plain', String(index))
}

function onDragOver(index, event) {
  event.preventDefault()
  event.dataTransfer.dropEffect = 'move'
  if (dragFromIndex.value === null || dragFromIndex.value === index) return

  const lines = [...(form.value.lines ?? [])]
  const [moved] = lines.splice(dragFromIndex.value, 1)
  lines.splice(index, 0, moved)
  form.value.lines = lines
  dragFromIndex.value = index
}

function onDragEnd() {
  dragFromIndex.value = null
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
      <label>Statut</label>
      <Select v-model="form.status" :options="statusOptions" option-label="label" option-value="value" fluid />
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
    <div class="field field--project">
      <label>Projet</label>
      <Select
        v-model="form.projectId"
        :options="projectOptions"
        option-label="label"
        option-value="value"
        placeholder="Projet existant (optionnel)"
        show-clear
        filter
        fluid
        @update:model-value="onProjectSelect"
      />
      <InputText
        v-model="form.projectLabel"
        placeholder="Ou texte libre pour l’impression"
        fluid
        class="field--project-label"
        @update:model-value="onProjectLabelInput"
      />
      <small class="field-hint">Choisir un projet réel ou saisir un libellé libre (pas les deux).</small>
    </div>
  </div>

  <div class="invoice-lines">
    <div class="invoice-lines__header">
      <h3>Lignes</h3>
      <Button label="Ajouter une ligne" icon="pi pi-plus" size="small" outlined @click="addLine" />
    </div>
    <AppFieldError :message="errors.lines" />
    <div v-if="!(form.lines ?? []).length" class="invoice-lines__empty">Aucune ligne. Ajoutez-en une à la volée.</div>
    <div
      v-for="(line, index) in form.lines"
      :key="lineKey(line, index)"
      class="invoice-lines__row"
      :class="{ 'is-dragging': dragFromIndex === index }"
      @dragover="onDragOver(index, $event)"
      @drop.prevent
    >
      <span
        class="invoice-lines__drag"
        title="Glisser pour réordonner"
        aria-label="Glisser pour réordonner"
        role="button"
        tabindex="0"
        draggable="true"
        @dragstart="onDragStart(index, $event)"
        @dragend="onDragEnd"
      >
        <i class="pi pi-bars" aria-hidden="true" />
      </span>
      <InputText v-model="line.description" placeholder="Libellé" fluid />
      <InputText v-model="line.unit" placeholder="Unit" fluid />
      <InputNumber v-model="line.quantity" :min="0" :min-fraction-digits="0" :max-fraction-digits="2" fluid />
      <InputNumber v-model="line.unitPrice" mode="currency" :currency="DEVISE_APP.code" locale="fr-FR" :min-fraction-digits="0" :max-fraction-digits="0" fluid />
      <span class="invoice-lines__amount">{{ lineAmount(line) }}</span>
      <Button icon="pi pi-trash" text rounded severity="danger" @click="removeLine(index)" />
    </div>
    <p class="invoice-lines__total">Total : {{ linesTotal }} {{ DEVISE_APP.symbole }}</p>
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

.field--project-label {
  margin-top: 0.35rem;
}

.field-hint {
  color: var(--layout-text-muted);
  font-size: 0.75rem;
}

.required {
  color: var(--p-red-500, #ef4444);
}

.invoice-lines {
  margin-top: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
}

.invoice-lines__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.invoice-lines__header h3 {
  margin: 0;
  font-size: 0.95rem;
}

.invoice-lines__empty {
  color: var(--layout-text-muted);
  font-size: 0.85rem;
}

.invoice-lines__row {
  display: grid;
  grid-template-columns: 1.5rem 1fr 5rem 5.5rem 8rem 6rem auto;
  gap: 0.5rem;
  align-items: center;
  border-radius: 0.35rem;
  transition: background-color 0.12s ease, opacity 0.12s ease;
}

.invoice-lines__row.is-dragging {
  opacity: 0.55;
  background: color-mix(in srgb, var(--p-primary-color, #3b82f6) 8%, transparent);
}

.invoice-lines__drag {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.5rem;
  height: 1.5rem;
  padding: 0;
  border: 0;
  border-radius: 0.25rem;
  background: transparent;
  color: var(--layout-text-muted);
  cursor: grab;
  touch-action: none;
  user-select: none;
}

.invoice-lines__drag:active {
  cursor: grabbing;
}

.invoice-lines__drag:hover,
.invoice-lines__drag:focus-visible {
  color: var(--layout-text-color, inherit);
  background: color-mix(in srgb, var(--layout-text-muted) 16%, transparent);
  outline: none;
}

.invoice-lines__amount,
.invoice-lines__total {
  font-variant-numeric: tabular-nums;
  font-weight: 600;
}

.invoice-lines__total {
  margin: 0.25rem 0 0;
  text-align: right;
}
</style>
