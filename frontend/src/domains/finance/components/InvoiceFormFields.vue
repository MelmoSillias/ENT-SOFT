<script setup>
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import AppFieldError from '@/domains/shared/components/AppFieldError.vue'
import { DEVISE_APP } from '@/domains/shared/constants/devise'
import { INVOICE_STATUS_OPTIONS } from '@/domains/shared/utils/entLabels'
import { computed, watch } from 'vue'

const form = defineModel({ type: Object, required: true })

defineProps({
  errors: { type: Object, default: () => ({}) },
  clientOptions: { type: Array, default: () => [] },
  projectOptions: { type: Array, default: () => [] },
})

const statusOptions = INVOICE_STATUS_OPTIONS
let lineUid = 0

function newLineKey() {
  return `il-${Date.now()}-${++lineUid}`
}

const lines = computed({
  get: () => form.value.lines ?? [],
  set: (value) => {
    form.value.lines = value
  },
})

const linesTotal = computed(() =>
  lines.value.reduce((sum, line) => sum + Number(line.quantity || 0) * Number(line.unitPrice || 0), 0),
)

function ensureLineKeys() {
  for (const line of lines.value) {
    if (!line._key) line._key = newLineKey()
  }
}

watch(lines, ensureLineKeys, { immediate: true })

function addLine() {
  lines.value = [
    ...lines.value,
    { _key: newLineKey(), description: '', unit: 'Lot', quantity: 1, unitPrice: 0 },
  ]
}

function removeLine(index) {
  lines.value = lines.value.filter((_, i) => i !== index)
}

function lineAmount(line) {
  return Number(line.quantity || 0) * Number(line.unitPrice || 0)
}

function onRowReorder(event) {
  lines.value = event.value
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

    <DataTable
      :value="lines"
      data-key="_key"
      size="small"
      class="invoice-lines__table"
      @row-reorder="onRowReorder"
    >
      <template #empty>
        <div class="invoice-lines__empty">Aucune ligne. Ajoutez-en une à la volée.</div>
      </template>

      <Column :row-reorder="true" :reorderable-column="false" style="width: 2.5rem" />
      <Column header="Libellé">
        <template #body="{ data }">
          <InputText v-model="data.description" placeholder="Libellé" fluid />
        </template>
      </Column>
      <Column header="Unité" style="width: 5.5rem">
        <template #body="{ data }">
          <InputText v-model="data.unit" placeholder="Unit" fluid />
        </template>
      </Column>
      <Column header="Qté" style="width: 6rem">
        <template #body="{ data }">
          <InputNumber v-model="data.quantity" :min="0" :min-fraction-digits="0" :max-fraction-digits="2" fluid />
        </template>
      </Column>
      <Column header="Prix unit." style="width: 8.5rem">
        <template #body="{ data }">
          <InputNumber
            v-model="data.unitPrice"
            mode="currency"
            :currency="DEVISE_APP.code"
            locale="fr-FR"
            :min-fraction-digits="0"
            :max-fraction-digits="0"
            fluid
          />
        </template>
      </Column>
      <Column header="Montant" style="width: 6.5rem">
        <template #body="{ data }">
          <span class="invoice-lines__amount">{{ lineAmount(data) }}</span>
        </template>
      </Column>
      <Column style="width: 3rem">
        <template #body="{ index }">
          <Button icon="pi pi-trash" text rounded severity="danger" @click="removeLine(index)" />
        </template>
      </Column>
    </DataTable>

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
  padding: 0.5rem 0;
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

.invoice-lines__table :deep(.p-datatable-tbody > tr > td) {
  padding: 0.35rem 0.4rem;
  vertical-align: middle;
}

.invoice-lines__table :deep(.p-datatable-thead > tr > th) {
  padding: 0.4rem;
  font-size: 0.8rem;
}

.invoice-lines__table :deep(.p-datatable-reorderable-row-handle) {
  cursor: grab;
  color: var(--layout-text-muted);
}

.invoice-lines__table :deep(.p-datatable-reorderable-row-handle:active) {
  cursor: grabbing;
}

.invoice-lines__table :deep(.p-datatable-dragpoint-top) {
  box-shadow: inset 0 2px 0 0 var(--p-primary-color, #3b82f6);
}

.invoice-lines__table :deep(.p-datatable-dragpoint-bottom) {
  box-shadow: inset 0 -2px 0 0 var(--p-primary-color, #3b82f6);
}
</style>
