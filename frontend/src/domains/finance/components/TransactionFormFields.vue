<script setup>
import { computed, onMounted, ref } from 'vue'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import AutoComplete from 'primevue/autocomplete'
import AppFieldError from '@/domains/shared/components/AppFieldError.vue'
import { DEVISE_APP } from '@/domains/shared/constants/devise'
import {
  SYSTEM_TRANSACTION_CATEGORY_OPTIONS,
  TRANSACTION_STATUS_OPTIONS,
  TRANSACTION_TYPE_OPTIONS,
  transactionCategoryLabel,
} from '@/domains/shared/utils/entLabels'
import api from '@/services/api'
import { parseSettingList } from '@/domains/configuration/utils/settingList'

const DEFAULT_EXPENSE_CATEGORIES = [
  'Dépense projet',
  'Dépense site',
  'Dépense matériel',
  'Dépense équipement',
  'Autre dépense',
]

const form = defineModel({ type: Object, required: true })

const props = defineProps({
  errors: { type: Object, default: () => ({}) },
  clientOptions: { type: Array, default: () => [] },
  siteOptions: { type: Array, default: () => [] },
  expenseOnly: { type: Boolean, default: false },
})

const emetteurOptions = ref([])
const destinataireOptions = ref([])
const emetteurSuggestions = ref([])
const destinataireSuggestions = ref([])
const expenseCategoryLabels = ref([...DEFAULT_EXPENSE_CATEGORIES])

const expenseCategoryOptions = computed(() =>
  expenseCategoryLabels.value.map((label) => ({ label, value: label })),
)

const categoryOptions = computed(() => {
  const base = props.expenseOnly
    ? [...expenseCategoryOptions.value]
    : [...SYSTEM_TRANSACTION_CATEGORY_OPTIONS, ...expenseCategoryOptions.value]

  const current = form.value?.category
  if (current && !base.some((o) => o.value === current)) {
    return [{ label: transactionCategoryLabel(current), value: current }, ...base]
  }
  return base
})

function filterSuggestions(source, query) {
  const q = String(query ?? '').trim().toLowerCase()
  if (!q) return [...source]
  return source.filter((item) => item.toLowerCase().includes(q))
}

function completeEmetteur(event) {
  emetteurSuggestions.value = filterSuggestions(emetteurOptions.value, event.query)
}

function completeDestinataire(event) {
  destinataireSuggestions.value = filterSuggestions(destinataireOptions.value, event.query)
}

async function loadFinanceLists() {
  try {
    const { data } = await api.get('/settings')
    const items = Array.isArray(data) ? data : (data.items ?? [])
    const map = Object.fromEntries(items.map((item) => [item.cle, item.valeur]))
    emetteurOptions.value = parseSettingList(map.FINANCE_EMETTEURS)
    destinataireOptions.value = parseSettingList(map.FINANCE_DESTINATAIRES)
    const cats = parseSettingList(map.FINANCE_CATEGORIES_DEPENSES)
    expenseCategoryLabels.value = cats.length ? cats : [...DEFAULT_EXPENSE_CATEGORIES]
  } catch {
    emetteurOptions.value = []
    destinataireOptions.value = []
    expenseCategoryLabels.value = [...DEFAULT_EXPENSE_CATEGORIES]
  }
}

onMounted(loadFinanceLists)
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
        :options="categoryOptions"
        option-label="label"
        option-value="value"
        filter
        fluid
      />
    </div>
    <div v-if="!expenseOnly" class="field">
      <label>Statut</label>
      <Select v-model="form.status" :options="TRANSACTION_STATUS_OPTIONS" option-label="label" option-value="value" fluid />
    </div>
    <div class="field">
      <label>Émetteur</label>
      <AutoComplete
        v-model="form.fromParty"
        :suggestions="emetteurSuggestions"
        dropdown
        complete-on-focus
        placeholder="Choisir ou saisir…"
        fluid
        @complete="completeEmetteur"
      />
    </div>
    <div class="field">
      <label>Destinataire</label>
      <AutoComplete
        v-model="form.toParty"
        :suggestions="destinataireSuggestions"
        dropdown
        complete-on-focus
        placeholder="Choisir ou saisir…"
        fluid
        @complete="completeDestinataire"
      />
    </div>
    <div class="field">
      <label>Client</label>
      <Select v-model="form.clientId" :options="clientOptions" option-label="label" option-value="value" show-clear filter fluid />
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
